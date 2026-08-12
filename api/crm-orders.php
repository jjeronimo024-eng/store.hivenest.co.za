<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
    session_name('HIVENEST_ADMIN');
    session_start();
}

require_once __DIR__ . '/../access/dbconfig.php';

function hivenest_crm_orders_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_crm_orders_env(string $key, string $default = ''): string
{
    $path = __DIR__ . '/../Backend/.env';
    if (!is_readable($path)) return $default;
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$name, $value] = explode('=', $line, 2);
        if (trim($name) !== $key) continue;
        $value = trim($value);
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        return $value;
    }
    return $default;
}

function hivenest_crm_orders_b64url_decode(string $value): string|false
{
    $remainder = strlen($value) % 4;
    if ($remainder) $value .= str_repeat('=', 4 - $remainder);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function hivenest_crm_orders_b64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function hivenest_crm_orders_bearer_token(): string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(.+)/i', $header, $match)) return trim($match[1]);
    return trim((string)($_COOKIE['hivenest_admin_access_token'] ?? ''));
}

function hivenest_crm_orders_authed(): bool
{
    return !empty($_SESSION['admin_user']) && !empty($_SESSION['admin_login_time']);
}

function hivenest_crm_orders_verify_admin_jwt(PDO $db): bool
{
    $token = hivenest_crm_orders_bearer_token();
    if ($token === '') return false;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    [$header64, $payload64, $signature64] = $parts;
    $headerJson = hivenest_crm_orders_b64url_decode($header64);
    $payloadJson = hivenest_crm_orders_b64url_decode($payload64);
    if ($headerJson === false || $payloadJson === false) return false;
    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);
    if (!is_array($header) || !is_array($payload)) return false;
    if (($header['alg'] ?? '') !== hivenest_crm_orders_env('JWT_ALGORITHM', 'HS256')) return false;
    if (($payload['user_type'] ?? '') !== 'admin') return false;
    if (!empty($payload['exp']) && (int)$payload['exp'] < time()) return false;

    $secret = hivenest_crm_orders_env('JWT_SECRET_KEY');
    if ($secret === '') return false;
    $expected = hivenest_crm_orders_b64url_encode(hash_hmac('sha256', $header64 . '.' . $payload64, $secret, true));
    if (!hash_equals($expected, $signature64)) return false;

    $adminId = (int)($payload['sub'] ?? 0);
    if ($adminId <= 0) return false;
    $stmt = $db->prepare('SELECT id, username, email, role, is_active FROM admin_users WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => $adminId]);
    $admin = $stmt->fetch();
    if (!$admin) return false;

    $_SESSION['admin_user'] = [
        'id' => (int)$admin['id'],
        'username' => (string)$admin['username'],
        'email' => (string)$admin['email'],
        'role' => (string)($admin['role'] ?? 'admin'),
    ];
    $_SESSION['admin_login_time'] = $_SESSION['admin_login_time'] ?? time();
    $_SESSION['admin_last_seen'] = time();
    return true;
}

function hivenest_crm_orders_json(?string $value): array
{
    if ($value === null || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    hivenest_crm_orders_out(405, ['error' => 'GET required.']);
}

$db = hivenest_db();
if (!$db) hivenest_crm_orders_out(503, ['error' => 'CRM database is unavailable.']);
if (!hivenest_crm_orders_authed() && !hivenest_crm_orders_verify_admin_jwt($db)) {
    hivenest_crm_orders_out(401, ['error' => 'Admin login required.']);
}

$customerId = (int)($_GET['customer'] ?? $_GET['customer_id'] ?? 0);
$paymentStatus = trim((string)($_GET['payment_status'] ?? ''));
$provisioningStatus = trim((string)($_GET['provisioning_status'] ?? ''));
$query = trim((string)($_GET['q'] ?? ''));

$where = [];
$params = [];
if ($customerId > 0) {
    $where[] = 'o.customer_id = :customer_id';
    $params['customer_id'] = $customerId;
}
if ($paymentStatus !== '') {
    $where[] = 'o.payment_status = :payment_status';
    $params['payment_status'] = $paymentStatus;
}
if ($provisioningStatus !== '') {
    $where[] = 'o.provisioning_status = :provisioning_status';
    $params['provisioning_status'] = $provisioningStatus;
}
if ($query !== '') {
    $where[] = '(o.order_number LIKE :q OR o.payment_reference LIKE :q OR c.email LIKE :q OR c.first_name LIKE :q OR c.last_name LIKE :q OR c.company_name LIKE :q)';
    $params['q'] = '%' . $query . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT
        o.*,
        c.email AS customer_email,
        c.first_name,
        c.last_name,
        c.company_name,
        c.myorderbox_customer_id,
        COUNT(oi.id) AS item_count
    FROM orders o
    INNER JOIN customers c ON c.id = o.customer_id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    {$whereSql}
    GROUP BY o.id
    ORDER BY o.id DESC
    LIMIT 150
");
$stmt->execute($params);
$orders = $stmt->fetchAll() ?: [];

$itemsByOrder = [];
$promotionsByOrder = [];
$paymentsByOrder = [];
$refundsByOrder = [];
if ($orders) {
    $ids = array_map(static fn($order) => (int)$order['id'], $orders);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $itemStmt = $db->prepare("
        SELECT *
        FROM order_items
        WHERE order_id IN ({$placeholders})
        ORDER BY order_id DESC, id ASC
    ");
    $itemStmt->execute($ids);
    foreach ($itemStmt->fetchAll() ?: [] as $item) {
        $config = hivenest_crm_orders_json($item['product_config'] ?? null);
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
    $paymentStmt = $db->prepare("
        SELECT order_id,id,gateway_capture_id,amount,currency,gateway_status
        FROM payment_gateway_transactions
        WHERE gateway='paypal' AND order_id IN ({$placeholders})
        ORDER BY id DESC
    ");
    $paymentStmt->execute($ids);
    foreach ($paymentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $payment) {
        $orderId = (int)$payment['order_id'];
        if (!isset($paymentsByOrder[$orderId])) {
            $paymentsByOrder[$orderId] = [
                'transaction_id' => (int)$payment['id'],
                'capture_id' => (string)$payment['gateway_capture_id'],
                'captured_amount' => (float)$payment['amount'],
                'currency' => (string)$payment['currency'],
                'gateway_status' => (string)$payment['gateway_status'],
            ];
        }
    }
    try {
        $refundTable = $db->query("
            SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_refunds'
        ");
        if ((int)$refundTable->fetchColumn() === 1) {
            $refundStmt = $db->prepare("
                SELECT pr.order_id,pr.id,pr.provider_refund_id,pr.amount,pr.currency,pr.reason,
                       pr.status,pr.error_message,pr.completed_at,pr.created_at,
                       au.username AS admin_username
                FROM payment_refunds pr
                INNER JOIN admin_users au ON au.id=pr.admin_user_id
                WHERE pr.order_id IN ({$placeholders})
                ORDER BY pr.id DESC
            ");
            $refundStmt->execute($ids);
            foreach ($refundStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $refund) {
                $refundsByOrder[(int)$refund['order_id']][] = [
                    'id' => (int)$refund['id'],
                    'provider_refund_id' => $refund['provider_refund_id'] ?: null,
                    'amount' => (float)$refund['amount'],
                    'currency' => (string)$refund['currency'],
                    'reason' => (string)$refund['reason'],
                    'status' => (string)$refund['status'],
                    'error' => $refund['error_message'] ?: null,
                    'admin' => (string)$refund['admin_username'],
                    'completed_at' => $refund['completed_at'] ?: null,
                    'created_at' => $refund['created_at'] ?: null,
                ];
            }
        }
    } catch (Throwable $e) {
        error_log('CRM refund history unavailable: ' . $e->getMessage());
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
        // Keep CRM order access compatible with installations awaiting the migration.
    }
}

$payload = [];
foreach ($orders as $order) {
    $customerName = trim((string)($order['first_name'] ?? '') . ' ' . (string)($order['last_name'] ?? ''));
    $promotion = $promotionsByOrder[(int)$order['id']] ?? ['code' => '', 'discount_amount' => 0.0];
    $totalDiscount = (float)($order['discount_amount'] ?? 0);
    $promotionDiscount = max(0.0, min($totalDiscount, (float)$promotion['discount_amount']));
    $payment = $paymentsByOrder[(int)$order['id']] ?? null;
    $refundHistory = $refundsByOrder[(int)$order['id']] ?? [];
    $reservedRefund = array_reduce(
        $refundHistory,
        static fn(float $sum, array $refund): float => in_array($refund['status'], ['requested','pending','completed'], true)
            ? $sum + (float)$refund['amount']
            : $sum,
        0.0
    );
    $completedRefund = array_reduce(
        $refundHistory,
        static fn(float $sum, array $refund): float => $refund['status'] === 'completed'
            ? $sum + (float)$refund['amount']
            : $sum,
        0.0
    );
    $refundable = $payment ? max(0.0, round((float)$payment['captured_amount'] - $reservedRefund, 2)) : 0.0;
    $payload[] = [
        'id' => (int)$order['id'],
        'uuid' => (string)$order['uuid'],
        'customer_id' => (int)$order['customer_id'],
        'order_number' => (string)$order['order_number'],
        'order_status' => (string)$order['order_status'],
        'payment_status' => (string)$order['payment_status'],
        'provisioning_status' => (string)($order['provisioning_status'] ?? 'pending'),
        'subtotal' => (float)$order['subtotal'],
        'tax_amount' => (float)($order['tax_amount'] ?? 0),
        'discount_amount' => $totalDiscount,
        'loyalty_discount_amount' => max(0.0, round($totalDiscount - $promotionDiscount, 2)),
        'promotion_code' => (string)$promotion['code'],
        'promotion_discount_amount' => $promotionDiscount,
        'total_amount' => (float)$order['total_amount'],
        'currency' => (string)($order['currency'] ?: 'USD'),
        'payment_method' => (string)$order['payment_method'],
        'payment_reference' => $order['payment_reference'] ?? null,
        'myorderbox_transaction_id' => $order['myorderbox_transaction_id'] ?? null,
        'processed_at' => $order['processed_at'] ?? null,
        'created_at' => $order['created_at'] ?? null,
        'updated_at' => $order['updated_at'] ?? null,
        'customer' => [
            'email' => (string)$order['customer_email'],
            'name' => $customerName,
            'company_name' => $order['company_name'] ?? null,
            'myorderbox_customer_id' => $order['myorderbox_customer_id'] ?? null,
        ],
        'items' => $itemsByOrder[(int)$order['id']] ?? [],
        'refund' => [
            'capture_id' => $payment['capture_id'] ?? null,
            'captured_amount' => $payment['captured_amount'] ?? 0,
            'refunded_amount' => round($completedRefund, 2),
            'refundable_amount' => $refundable,
            'currency' => $payment['currency'] ?? (string)($order['currency'] ?: 'USD'),
            'can_refund' => $payment !== null
                && $refundable > 0
                && in_array((string)$order['payment_status'], ['paid','partially_refunded'], true),
            'history' => $refundHistory,
        ],
    ];
}

hivenest_crm_orders_out(200, ['orders' => $payload]);
