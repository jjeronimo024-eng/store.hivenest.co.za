<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../access/dbconfig.php';

function hivenest_billing_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_billing_json(?string $value): array
{
    if ($value === null || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    hivenest_billing_out(405, ['error' => 'GET required.']);
}

$customerId = (int)($_SESSION['customer_id'] ?? 0);
if ($customerId <= 0) {
    hivenest_billing_out(401, ['error' => 'Customer login required.']);
}

$db = hivenest_db();
if (!$db) {
    hivenest_billing_out(503, ['error' => 'Customer database is unavailable.']);
}

$profileStmt = $db->prepare("
    SELECT id, email, first_name, last_name, company_name, preferred_currency
    FROM customers
    WHERE id = :customer_id
    LIMIT 1
");
$profileStmt->execute(['customer_id' => $customerId]);
$profile = $profileStmt->fetch();
if (!$profile) {
    hivenest_billing_out(404, ['error' => 'Customer profile not found.']);
}

$orderStmt = $db->prepare("
    SELECT
        id,
        order_number,
        order_status,
        payment_status,
        provisioning_status,
        subtotal,
        tax_amount,
        discount_amount,
        total_amount,
        currency,
        display_currency,
        display_exchange_rate,
        display_subtotal,
        display_tax_amount,
        display_discount_amount,
        display_total_amount,
        display_rate_source,
        display_rate_captured_at,
        payment_method,
        payment_reference,
        myorderbox_transaction_id,
        processed_at,
        created_at,
        updated_at
    FROM orders
    WHERE customer_id = :customer_id
    ORDER BY id DESC
    LIMIT 100
");
$orderStmt->execute(['customer_id' => $customerId]);
$orders = $orderStmt->fetchAll() ?: [];

$itemsByOrder = [];
$promotionsByOrder = [];
if ($orders) {
    $ids = array_map(static fn($order) => (int)$order['id'], $orders);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $itemStmt = $db->prepare("
        SELECT
            id,
            order_id,
            service_id,
            product_name,
            domain_name,
            quantity,
            unit_price,
            setup_fee,
            billing_cycle,
            line_total,
            product_config,
            provisioning_status,
            provider_order_id,
            provider_action_id,
            provider_entity_id,
            provisioning_error,
            service_ready_notified_at,
            created_at
        FROM order_items
        WHERE order_id IN ({$placeholders})
        ORDER BY order_id DESC, id ASC
    ");
    $itemStmt->execute($ids);
    foreach ($itemStmt->fetchAll() ?: [] as $item) {
        $config = hivenest_billing_json($item['product_config'] ?? null);
        $itemsByOrder[(int)$item['order_id']][] = [
            'id' => (int)$item['id'],
            'service_id' => $item['service_id'] !== null ? (int)$item['service_id'] : null,
            'product_name' => (string)$item['product_name'],
            'domain_name' => $item['domain_name'] !== null ? (string)$item['domain_name'] : null,
            'quantity' => (int)$item['quantity'],
            'unit_price' => (float)$item['unit_price'],
            'setup_fee' => (float)($item['setup_fee'] ?? 0),
            'billing_cycle' => (string)$item['billing_cycle'],
            'line_total' => (float)$item['line_total'],
            'sku' => $config['sku'] ?? null,
            'domain_option' => $config['domain_option'] ?? null,
            'term_months' => isset($config['term_months']) ? (int)$config['term_months'] : null,
            'provisioning_status' => (string)$item['provisioning_status'],
            'provider_order_id' => $item['provider_order_id'] ?? null,
            'provider_action_id' => $item['provider_action_id'] ?? null,
            'provider_entity_id' => $item['provider_entity_id'] ?? null,
            'provisioning_error' => $item['provisioning_error'] ?? null,
            'service_ready_notified_at' => $item['service_ready_notified_at'] ?? null,
            'created_at' => $item['created_at'] ?? null,
        ];
    }
    try {
        $promotionStmt = $db->prepare("
            SELECT order_id, code, discount_amount
            FROM promotion_redemptions
            WHERE order_id IN ({$placeholders})
        ");
        $promotionStmt->execute($ids);
        foreach ($promotionStmt->fetchAll() ?: [] as $promotion) {
            $promotionsByOrder[(int)$promotion['order_id']] = [
                'code' => (string)$promotion['code'],
                'discount_amount' => (float)$promotion['discount_amount'],
            ];
        }
    } catch (Throwable $e) {
        // Older databases without promotion_redemptions still return billing.
    }
}

$totalPaid = 0.0;
$pendingTotal = 0.0;
$unpaidCount = 0;
$paidCount = 0;
$normalisedOrders = [];
foreach ($orders as $order) {
    $paymentStatus = (string)$order['payment_status'];
    $total = (float)$order['total_amount'];
    if ($paymentStatus === 'paid') {
        $totalPaid += $total;
        $paidCount++;
    } else {
        $pendingTotal += $total;
        $unpaidCount++;
    }
    $promotion = $promotionsByOrder[(int)$order['id']] ?? ['code' => '', 'discount_amount' => 0.0];
    $totalDiscount = (float)($order['discount_amount'] ?? 0);
    $promotionDiscount = max(0.0, min($totalDiscount, (float)$promotion['discount_amount']));
    $normalisedOrders[] = [
        'id' => (int)$order['id'],
        'order_number' => (string)$order['order_number'],
        'order_status' => (string)$order['order_status'],
        'payment_status' => $paymentStatus,
        'provisioning_status' => (string)($order['provisioning_status'] ?? 'pending'),
        'subtotal' => (float)$order['subtotal'],
        'tax_amount' => (float)($order['tax_amount'] ?? 0),
        'discount_amount' => $totalDiscount,
        'loyalty_discount_amount' => max(0.0, round($totalDiscount - $promotionDiscount, 2)),
        'promotion_code' => (string)$promotion['code'],
        'promotion_discount_amount' => $promotionDiscount,
        'total_amount' => $total,
        'currency' => (string)($order['currency'] ?: 'USD'),
        'display_currency' => $order['display_currency'] ?: null,
        'display_exchange_rate' => $order['display_exchange_rate'] !== null ? (float)$order['display_exchange_rate'] : null,
        'display_subtotal' => $order['display_subtotal'] !== null ? (float)$order['display_subtotal'] : null,
        'display_tax_amount' => $order['display_tax_amount'] !== null ? (float)$order['display_tax_amount'] : null,
        'display_discount_amount' => $order['display_discount_amount'] !== null ? (float)$order['display_discount_amount'] : null,
        'display_total_amount' => $order['display_total_amount'] !== null ? (float)$order['display_total_amount'] : null,
        'display_rate_source' => $order['display_rate_source'] ?: null,
        'display_rate_captured_at' => $order['display_rate_captured_at'] ?? null,
        'payment_method' => (string)$order['payment_method'],
        'payment_reference' => $order['payment_reference'] ?? null,
        'myorderbox_transaction_id' => $order['myorderbox_transaction_id'] ?? null,
        'processed_at' => $order['processed_at'] ?? null,
        'created_at' => $order['created_at'] ?? null,
        'updated_at' => $order['updated_at'] ?? null,
        'items' => $itemsByOrder[(int)$order['id']] ?? [],
    ];
}

hivenest_billing_out(200, [
    'profile' => [
        'id' => (int)$profile['id'],
        'email' => (string)$profile['email'],
        'first_name' => (string)($profile['first_name'] ?? ''),
        'last_name' => (string)($profile['last_name'] ?? ''),
        'company_name' => $profile['company_name'] ?? null,
        'preferred_currency' => (string)($profile['preferred_currency'] ?: 'USD'),
    ],
    'stats' => [
        'orders' => count($normalisedOrders),
        'paid_orders' => $paidCount,
        'unpaid_orders' => $unpaidCount,
        'total_paid' => $totalPaid,
        'pending_total' => $pendingTotal,
    ],
    'orders' => $normalisedOrders,
]);
