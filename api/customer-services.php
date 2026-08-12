<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/service_credentials.php';

function hivenest_services_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_services_json(?string $value): array
{
    if ($value === null || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function hivenest_services_table_exists(PDO $db, string $table): bool
{
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
    ");
    $stmt->execute(['table_name' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    hivenest_services_out(405, ['error' => 'GET required.']);
}

$customerId = (int)($_SESSION['customer_id'] ?? 0);
if ($customerId <= 0) {
    hivenest_services_out(401, ['error' => 'Customer login required.']);
}

$db = hivenest_db();
if (!$db) {
    hivenest_services_out(503, ['error' => 'Customer database is unavailable.']);
}

$serviceId = (int)($_GET['service_id'] ?? $_GET['service'] ?? 0);
$where = 's.customer_id = :customer_id';
$params = ['customer_id' => $customerId];
if ($serviceId > 0) {
    $where .= ' AND s.id = :service_id';
    $params['service_id'] = $serviceId;
}

$stmt = $db->prepare("
    SELECT
        s.id,
        s.uuid,
        s.customer_id,
        s.product_id,
        s.order_id,
        s.service_name,
        s.domain_name,
        s.service_type,
        s.service_status,
        s.billing_cycle,
        s.setup_date,
        s.expiry_date,
        s.next_due_date,
        s.auto_renew,
        s.service_config,
        s.usage_stats,
        s.suspension_reason,
        s.created_at,
        s.updated_at,
        p.name AS product_name,
        p.slug AS product_slug,
        p.product_type,
        o.order_number,
        o.order_status,
        o.payment_status,
        o.provisioning_status AS order_provisioning_status,
        o.total_amount AS order_total,
        o.currency AS order_currency,
        o.payment_reference,
        o.myorderbox_transaction_id
    FROM services s
    LEFT JOIN products p ON p.id = s.product_id
    LEFT JOIN orders o ON o.id = s.order_id
    WHERE {$where}
    ORDER BY
        CASE s.service_status
            WHEN 'active' THEN 1
            WHEN 'pending' THEN 2
            ELSE 3
        END,
        COALESCE(s.next_due_date, '9999-12-31') ASC,
        s.id DESC
    LIMIT 100
");
$stmt->execute($params);
$rows = $stmt->fetchAll() ?: [];

$itemsByService = [];
$notesByService = [];
$requestsByService = [];
$orderIds = [];
$serviceIds = [];
foreach ($rows as $row) {
    $serviceIds[] = (int)$row['id'];
    if (!empty($row['order_id'])) $orderIds[] = (int)$row['order_id'];
}

if ($serviceIds) {
    $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
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
        WHERE service_id IN ({$placeholders})
        ORDER BY id ASC
    ");
    $itemStmt->execute($serviceIds);
    foreach ($itemStmt->fetchAll() ?: [] as $item) {
        $config = hivenest_services_json($item['product_config'] ?? null);
        $itemsByService[(int)$item['service_id']][] = [
            'id' => (int)$item['id'],
            'order_id' => (int)$item['order_id'],
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
}

if ($serviceIds && hivenest_services_table_exists($db, 'service_notes')) {
    $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
    $notesStmt = $db->prepare("
        SELECT id, service_id, note_type, note_text, created_at
        FROM service_notes
        WHERE service_id IN ({$placeholders})
          AND customer_id = ?
          AND visibility = 'client'
        ORDER BY id DESC
        LIMIT 100
    ");
    $notesStmt->execute([...$serviceIds, $customerId]);
    foreach ($notesStmt->fetchAll() ?: [] as $note) {
        $notesByService[(int)$note['service_id']][] = [
            'id' => (int)$note['id'],
            'note_type' => (string)$note['note_type'],
            'note_text' => (string)$note['note_text'],
            'created_at' => $note['created_at'] ?? null,
        ];
    }
}

if ($serviceIds && hivenest_services_table_exists($db, 'service_requests')) {
    $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
    $requestStmt = $db->prepare("
        SELECT id, service_id, request_type, requested_value, status, message, admin_response, created_at, updated_at
        FROM service_requests
        WHERE service_id IN ({$placeholders})
          AND customer_id = ?
        ORDER BY id DESC
        LIMIT 100
    ");
    $requestStmt->execute([...$serviceIds, $customerId]);
    foreach ($requestStmt->fetchAll() ?: [] as $request) {
        $requestsByService[(int)$request['service_id']][] = [
            'id' => (int)$request['id'],
            'request_type' => (string)$request['request_type'],
            'requested_value' => $request['requested_value'] ?? null,
            'status' => (string)$request['status'],
            'message' => $request['message'] ?? null,
            'admin_response' => $request['admin_response'] ?? null,
            'created_at' => $request['created_at'] ?? null,
            'updated_at' => $request['updated_at'] ?? null,
        ];
    }
}

$services = [];
foreach ($rows as $row) {
    $config = hivenest_service_credentials_redact_config(
        hivenest_services_json($row['service_config'] ?? null)
    );
    $usage = hivenest_services_json($row['usage_stats'] ?? null);
    $workflowStatus = (string)($config['workflow_status'] ?? '');
    $workflowCompletedAt = $config['workflow_completed_at'] ?? null;
    $services[] = [
        'id' => (int)$row['id'],
        'uuid' => (string)$row['uuid'],
        'product_id' => (int)$row['product_id'],
        'order_id' => (int)$row['order_id'],
        'service_name' => (string)$row['service_name'],
        'domain_name' => $row['domain_name'] !== null ? (string)$row['domain_name'] : null,
        'service_type' => (string)$row['service_type'],
        'service_status' => (string)$row['service_status'],
        'billing_cycle' => (string)$row['billing_cycle'],
        'setup_date' => $row['setup_date'] ?? null,
        'expiry_date' => $row['expiry_date'] ?? null,
        'next_due_date' => $row['next_due_date'] ?? null,
        'auto_renew' => (int)($row['auto_renew'] ?? 1) === 1,
        'suspension_reason' => $row['suspension_reason'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
        'product' => [
            'name' => $row['product_name'] ?? null,
            'slug' => $row['product_slug'] ?? null,
            'type' => $row['product_type'] ?? null,
        ],
        'order' => [
            'order_number' => $row['order_number'] ?? null,
            'order_status' => $row['order_status'] ?? null,
            'payment_status' => $row['payment_status'] ?? null,
            'provisioning_status' => $row['order_provisioning_status'] ?? null,
            'total_amount' => $row['order_total'] !== null ? (float)$row['order_total'] : null,
            'currency' => $row['order_currency'] ?? 'USD',
            'payment_reference' => $row['payment_reference'] ?? null,
            'myorderbox_transaction_id' => $row['myorderbox_transaction_id'] ?? null,
        ],
        'service_config' => $config,
        'workflow_status' => $workflowStatus !== '' ? $workflowStatus : null,
        'workflow_completed_at' => is_string($workflowCompletedAt) && $workflowCompletedAt !== '' ? $workflowCompletedAt : null,
        'usage_stats' => $usage,
        'items' => $itemsByService[(int)$row['id']] ?? [],
        'public_notes' => $notesByService[(int)$row['id']] ?? [],
        'service_requests' => $requestsByService[(int)$row['id']] ?? [],
        'needs_onboarding' => in_array((string)($config['job_type'] ?? ''), ['design_queue', 'marketing_queue'], true)
            || in_array((string)($row['service_type'] ?? ''), ['design', 'marketing'], true),
    ];
}

hivenest_services_out(200, [
    'services' => $services,
    'selected_service_id' => $serviceId > 0 ? $serviceId : null,
]);
