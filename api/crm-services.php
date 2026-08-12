<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
    session_name('HIVENEST_ADMIN');
    session_start();
}

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/service_credentials.php';

function hivenest_crm_services_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_crm_services_env(string $key, string $default = ''): string
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

function hivenest_crm_services_b64url_decode(string $value): string|false
{
    $remainder = strlen($value) % 4;
    if ($remainder) $value .= str_repeat('=', 4 - $remainder);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function hivenest_crm_services_b64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function hivenest_crm_services_bearer_token(): string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(.+)/i', $header, $match)) return trim($match[1]);
    return trim((string)($_COOKIE['hivenest_admin_access_token'] ?? ''));
}

function hivenest_crm_services_authed(): bool
{
    return !empty($_SESSION['admin_user']) && !empty($_SESSION['admin_login_time']);
}

function hivenest_crm_services_verify_admin_jwt(PDO $db): bool
{
    $token = hivenest_crm_services_bearer_token();
    if ($token === '') return false;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    [$header64, $payload64, $signature64] = $parts;
    $headerJson = hivenest_crm_services_b64url_decode($header64);
    $payloadJson = hivenest_crm_services_b64url_decode($payload64);
    if ($headerJson === false || $payloadJson === false) return false;
    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);
    if (!is_array($header) || !is_array($payload)) return false;
    if (($header['alg'] ?? '') !== hivenest_crm_services_env('JWT_ALGORITHM', 'HS256')) return false;
    if (($payload['user_type'] ?? '') !== 'admin') return false;
    if (!empty($payload['exp']) && (int)$payload['exp'] < time()) return false;

    $secret = hivenest_crm_services_env('JWT_SECRET_KEY');
    if ($secret === '') return false;
    $expected = hivenest_crm_services_b64url_encode(hash_hmac('sha256', $header64 . '.' . $payload64, $secret, true));
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

function hivenest_crm_services_json(?string $value): array
{
    if ($value === null || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function hivenest_crm_services_table_exists(PDO $db, string $table): bool
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
    hivenest_crm_services_out(405, ['error' => 'GET required.']);
}

$db = hivenest_db();
if (!$db) hivenest_crm_services_out(503, ['error' => 'CRM database is unavailable.']);
if (!hivenest_crm_services_authed() && !hivenest_crm_services_verify_admin_jwt($db)) {
    hivenest_crm_services_out(401, ['error' => 'Admin login required.']);
}

$customerId = (int)($_GET['customer'] ?? $_GET['customer_id'] ?? 0);
$serviceId = (int)($_GET['service'] ?? $_GET['service_id'] ?? 0);
$status = trim((string)($_GET['status'] ?? ''));
$type = trim((string)($_GET['type'] ?? ''));
$workflow = trim((string)($_GET['workflow'] ?? ''));
$requestStatus = trim((string)($_GET['request_status'] ?? ''));
$query = trim((string)($_GET['q'] ?? ''));

$where = [];
$params = [];
if ($customerId > 0) {
    $where[] = 's.customer_id = :customer_id';
    $params['customer_id'] = $customerId;
}
if ($serviceId > 0) {
    $where[] = 's.id = :service_id';
    $params['service_id'] = $serviceId;
}
if ($status !== '') {
    $where[] = 's.service_status = :status';
    $params['status'] = $status;
}
if ($type !== '') {
    $where[] = 's.service_type = :type';
    $params['type'] = $type;
}
if ($workflow === 'completed') {
    $where[] = "(JSON_VALID(s.service_config) AND JSON_UNQUOTE(JSON_EXTRACT(s.service_config, '$.workflow_status')) = 'completed')";
} elseif ($workflow === 'not_completed') {
    $where[] = "(s.service_config IS NULL OR NOT JSON_VALID(s.service_config) OR JSON_UNQUOTE(JSON_EXTRACT(s.service_config, '$.workflow_status')) IS NULL OR JSON_UNQUOTE(JSON_EXTRACT(s.service_config, '$.workflow_status')) <> 'completed')";
}
if ($requestStatus !== '') {
    if (!hivenest_crm_services_table_exists($db, 'service_requests')) {
        $where[] = '1 = 0';
    } elseif ($requestStatus === 'open') {
        $where[] = "EXISTS (
            SELECT 1 FROM service_requests sr_filter
            WHERE sr_filter.service_id = s.id
              AND sr_filter.status IN ('pending','in_review','approved')
        )";
    } elseif ($requestStatus === 'closed') {
        $where[] = "EXISTS (
            SELECT 1 FROM service_requests sr_filter
            WHERE sr_filter.service_id = s.id
              AND sr_filter.status IN ('completed','rejected','cancelled')
        )";
    } elseif (in_array($requestStatus, ['pending','in_review','approved','completed','rejected','cancelled'], true)) {
        $where[] = "EXISTS (
            SELECT 1 FROM service_requests sr_filter
            WHERE sr_filter.service_id = s.id
              AND sr_filter.status = :request_status
        )";
        $params['request_status'] = $requestStatus;
    }
}
if ($query !== '') {
    $where[] = '(s.service_name LIKE :q OR s.domain_name LIKE :q OR o.order_number LIKE :q OR c.email LIKE :q OR c.first_name LIKE :q OR c.last_name LIKE :q OR c.company_name LIKE :q)';
    $params['q'] = '%' . $query . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT
        s.*,
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
        o.myorderbox_transaction_id,
        c.email AS customer_email,
        c.first_name,
        c.last_name,
        c.company_name,
        c.myorderbox_customer_id
    FROM services s
    INNER JOIN customers c ON c.id = s.customer_id
    LEFT JOIN products p ON p.id = s.product_id
    LEFT JOIN orders o ON o.id = s.order_id
    {$whereSql}
    ORDER BY
        CASE s.service_status
            WHEN 'pending' THEN 1
            WHEN 'active' THEN 2
            WHEN 'suspended' THEN 3
            ELSE 4
        END,
        COALESCE(s.next_due_date, '9999-12-31') ASC,
        s.id DESC
    LIMIT 150
");
$stmt->execute($params);
$rows = $stmt->fetchAll() ?: [];

$itemsByService = [];
$notesByService = [];
$historyByService = [];
$requestsByService = [];
if ($rows) {
    $serviceIds = array_map(static fn($row) => (int)$row['id'], $rows);
    $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
    $itemStmt = $db->prepare("
        SELECT *
        FROM order_items
        WHERE service_id IN ({$placeholders})
        ORDER BY service_id DESC, id ASC
    ");
    $itemStmt->execute($serviceIds);
    foreach ($itemStmt->fetchAll() ?: [] as $item) {
        $config = hivenest_crm_services_json($item['product_config'] ?? null);
        $itemsByService[(int)$item['service_id']][] = [
            'id' => (int)$item['id'],
            'order_id' => (int)$item['order_id'],
            'product_name' => (string)$item['product_name'],
            'domain_name' => $item['domain_name'] !== null ? (string)$item['domain_name'] : null,
            'quantity' => (int)$item['quantity'],
            'unit_price' => (float)$item['unit_price'],
            'billing_cycle' => (string)$item['billing_cycle'],
            'line_total' => (float)$item['line_total'],
            'sku' => $config['sku'] ?? null,
            'domain_option' => $config['domain_option'] ?? null,
            'term_months' => isset($config['term_months']) ? (int)$config['term_months'] : null,
            'job_type' => $config['job_type'] ?? null,
            'provisioning_status' => (string)$item['provisioning_status'],
            'provider_order_id' => $item['provider_order_id'] ?? null,
            'provider_action_id' => $item['provider_action_id'] ?? null,
            'provider_entity_id' => $item['provider_entity_id'] ?? null,
            'provisioning_error' => $item['provisioning_error'] ?? null,
            'service_ready_notified_at' => $item['service_ready_notified_at'] ?? null,
        ];
    }

    if (hivenest_crm_services_table_exists($db, 'service_notes')) {
        $notesStmt = $db->prepare("
            SELECT n.*, a.username AS admin_username
            FROM service_notes n
            LEFT JOIN admin_users a ON a.id = n.author_admin_id
            WHERE n.service_id IN ({$placeholders})
            ORDER BY n.id DESC
        ");
        $notesStmt->execute($serviceIds);
        foreach ($notesStmt->fetchAll() ?: [] as $note) {
            $serviceKey = (int)$note['service_id'];
            if (count($notesByService[$serviceKey] ?? []) >= 8) continue;
            $notesByService[$serviceKey][] = [
                'id' => (int)$note['id'],
                'visibility' => (string)$note['visibility'],
                'note_type' => (string)$note['note_type'],
                'note_text' => (string)$note['note_text'],
                'author_type' => (string)$note['author_type'],
                'author' => $note['admin_username'] ?? 'HiveNest',
                'created_at' => $note['created_at'] ?? null,
            ];
        }
    }

    if (hivenest_crm_services_table_exists($db, 'service_status_history')) {
        $historyStmt = $db->prepare("
            SELECT h.*, a.username AS admin_username
            FROM service_status_history h
            LEFT JOIN admin_users a ON a.id = h.changed_by_admin_id
            WHERE h.service_id IN ({$placeholders})
            ORDER BY h.id DESC
        ");
        $historyStmt->execute($serviceIds);
        foreach ($historyStmt->fetchAll() ?: [] as $history) {
            $serviceKey = (int)$history['service_id'];
            if (count($historyByService[$serviceKey] ?? []) >= 5) continue;
            $historyByService[$serviceKey][] = [
                'id' => (int)$history['id'],
                'old_status' => $history['old_status'] ?? null,
                'new_status' => (string)$history['new_status'],
                'reason' => $history['reason'] ?? null,
                'changed_by' => $history['admin_username'] ?? 'HiveNest',
                'created_at' => $history['created_at'] ?? null,
            ];
        }
    }

    if (hivenest_crm_services_table_exists($db, 'service_requests')) {
        $requestStmt = $db->prepare("
            SELECT *
            FROM service_requests
            WHERE service_id IN ({$placeholders})
            ORDER BY id DESC
        ");
        $requestStmt->execute($serviceIds);
        foreach ($requestStmt->fetchAll() ?: [] as $request) {
            $serviceKey = (int)$request['service_id'];
            if (count($requestsByService[$serviceKey] ?? []) >= 5) continue;
            $requestsByService[$serviceKey][] = [
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
}

$services = [];
foreach ($rows as $row) {
    $config = hivenest_service_credentials_redact_config(
        hivenest_crm_services_json($row['service_config'] ?? null)
    );
    $usage = hivenest_crm_services_json($row['usage_stats'] ?? null);
    $workflowStatus = (string)($config['workflow_status'] ?? '');
    $workflowCompletedAt = $config['workflow_completed_at'] ?? null;
    $customerName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
    $services[] = [
        'id' => (int)$row['id'],
        'uuid' => (string)$row['uuid'],
        'customer_id' => (int)$row['customer_id'],
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
        'customer' => [
            'id' => (int)$row['customer_id'],
            'email' => (string)$row['customer_email'],
            'name' => $customerName !== '' ? $customerName : (string)$row['customer_email'],
            'company_name' => $row['company_name'] ?? null,
            'myorderbox_customer_id' => $row['myorderbox_customer_id'] ?? null,
        ],
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
        'notes' => $notesByService[(int)$row['id']] ?? [],
        'status_history' => $historyByService[(int)$row['id']] ?? [],
        'service_requests' => $requestsByService[(int)$row['id']] ?? [],
        'needs_onboarding' => in_array((string)($config['job_type'] ?? ''), ['design_queue', 'marketing_queue'], true)
            || in_array((string)($row['service_type'] ?? ''), ['design', 'marketing'], true),
    ];
}

hivenest_crm_services_out(200, [
    'services' => $services,
    'filters' => [
        'customer_id' => $customerId > 0 ? $customerId : null,
        'service_id' => $serviceId > 0 ? $serviceId : null,
        'status' => $status !== '' ? $status : null,
        'type' => $type !== '' ? $type : null,
        'workflow' => $workflow !== '' ? $workflow : null,
        'request_status' => $requestStatus !== '' ? $requestStatus : null,
        'q' => $query !== '' ? $query : null,
    ],
]);
