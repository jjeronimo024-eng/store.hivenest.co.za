<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
    session_name('HIVENEST_ADMIN');
    session_start();
}

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/customer_loyalty.php';

function hivenest_crm_customer_profile_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_crm_customer_profile_env(string $key, string $default = ''): string
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
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) $value = substr($value, 1, -1);
        }
        return $value;
    }
    return $default;
}

function hivenest_crm_customer_profile_b64url_decode(string $value): string|false
{
    $remainder = strlen($value) % 4;
    if ($remainder) $value .= str_repeat('=', 4 - $remainder);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function hivenest_crm_customer_profile_b64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function hivenest_crm_customer_profile_bearer_token(): string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(.+)/i', $header, $match)) return trim($match[1]);
    return trim((string)($_COOKIE['hivenest_admin_access_token'] ?? ''));
}

function hivenest_crm_customer_profile_authed(PDO $db): bool
{
    if (!empty($_SESSION['admin_user']) && !empty($_SESSION['admin_login_time'])) return true;
    $token = hivenest_crm_customer_profile_bearer_token();
    if ($token === '') return false;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    [$header64, $payload64, $signature64] = $parts;
    $headerJson = hivenest_crm_customer_profile_b64url_decode($header64);
    $payloadJson = hivenest_crm_customer_profile_b64url_decode($payload64);
    if ($headerJson === false || $payloadJson === false) return false;
    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);
    if (!is_array($header) || !is_array($payload)) return false;
    if (($header['alg'] ?? '') !== hivenest_crm_customer_profile_env('JWT_ALGORITHM', 'HS256')) return false;
    if (($payload['user_type'] ?? '') !== 'admin') return false;
    if (!empty($payload['exp']) && (int)$payload['exp'] < time()) return false;
    $secret = hivenest_crm_customer_profile_env('JWT_SECRET_KEY');
    if ($secret === '') return false;
    $expected = hivenest_crm_customer_profile_b64url_encode(hash_hmac('sha256', $header64 . '.' . $payload64, $secret, true));
    if (!hash_equals($expected, $signature64)) return false;
    $adminId = (int)($payload['sub'] ?? 0);
    if ($adminId <= 0) return false;
    $stmt = $db->prepare('SELECT id, username, email, role FROM admin_users WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => $adminId]);
    $admin = $stmt->fetch();
    if (!$admin) return false;
    $_SESSION['admin_user'] = ['id' => (int)$admin['id'], 'username' => (string)$admin['username'], 'email' => (string)$admin['email'], 'role' => (string)($admin['role'] ?? 'admin')];
    $_SESSION['admin_login_time'] = $_SESSION['admin_login_time'] ?? time();
    return true;
}

function hivenest_crm_customer_profile_table_exists(PDO $db, string $table): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table");
    $stmt->execute(['table' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function hivenest_crm_customer_profile_json(?string $value): array
{
    if ($value === null || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    hivenest_crm_customer_profile_out(405, ['error' => 'GET required.']);
}

$db = hivenest_db();
if (!$db) hivenest_crm_customer_profile_out(503, ['error' => 'CRM database is unavailable.']);
if (!hivenest_crm_customer_profile_authed($db)) hivenest_crm_customer_profile_out(401, ['error' => 'Admin login required.']);

$customerId = (int)($_GET['customer'] ?? $_GET['customer_id'] ?? $_GET['id'] ?? 0);
if ($customerId <= 0) hivenest_crm_customer_profile_out(422, ['error' => 'Customer is required.']);

$customerStmt = $db->prepare("
    SELECT
        c.*,
        (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS order_count,
        (
            SELECT COALESCE(SUM(o.total_amount), 0)
            FROM orders o
            WHERE o.customer_id = c.id
              AND o.payment_status = 'paid'
              AND UPPER(o.currency) = 'USD'
        ) AS lifetime_paid,
        (SELECT COUNT(*) FROM services s WHERE s.customer_id = c.id) AS service_count,
        (
            SELECT COUNT(*)
            FROM support_tickets st
            WHERE st.customer_id = c.id
              AND st.status NOT IN ('closed','resolved')
        ) AS open_ticket_count
    FROM customers c
    WHERE c.id = :customer_id
    LIMIT 1
");
$customerStmt->execute(['customer_id' => $customerId]);
$customer = $customerStmt->fetch();
if (!$customer) hivenest_crm_customer_profile_out(404, ['error' => 'Customer not found.']);

try {
    $loyalty = hivenest_customer_loyalty($db, $customerId, false);
} catch (Throwable $loyaltyError) {
    error_log('CRM customer loyalty lookup failed: ' . $loyaltyError->getMessage());
    $loyalty = null;
}

$orders = [];
if (hivenest_crm_customer_profile_table_exists($db, 'orders')) {
    $orderStmt = $db->prepare("
        SELECT id, order_number, order_status, payment_status, provisioning_status, total_amount, currency, payment_reference, created_at
        FROM orders
        WHERE customer_id = :customer_id
        ORDER BY id DESC
        LIMIT 12
    ");
    $orderStmt->execute(['customer_id' => $customerId]);
    foreach ($orderStmt->fetchAll() ?: [] as $row) {
        $orders[] = [
            'id' => (int)$row['id'],
            'order_number' => (string)$row['order_number'],
            'order_status' => (string)$row['order_status'],
            'payment_status' => (string)$row['payment_status'],
            'provisioning_status' => (string)($row['provisioning_status'] ?? 'pending'),
            'total_amount' => (float)$row['total_amount'],
            'currency' => (string)($row['currency'] ?: 'USD'),
            'payment_reference' => $row['payment_reference'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }
}

$services = [];
if (hivenest_crm_customer_profile_table_exists($db, 'services')) {
    $serviceStmt = $db->prepare("
        SELECT s.*, p.slug AS product_slug, p.product_type
        FROM services s
        LEFT JOIN products p ON p.id = s.product_id
        WHERE s.customer_id = :customer_id
        ORDER BY s.id DESC
        LIMIT 16
    ");
    $serviceStmt->execute(['customer_id' => $customerId]);
    foreach ($serviceStmt->fetchAll() ?: [] as $row) {
        $config = hivenest_crm_customer_profile_json($row['service_config'] ?? null);
        $services[] = [
            'id' => (int)$row['id'],
            'service_name' => (string)$row['service_name'],
            'domain_name' => $row['domain_name'] ?? null,
            'service_type' => (string)($row['service_type'] ?: $row['product_type'] ?: 'service'),
            'service_status' => (string)$row['service_status'],
            'billing_cycle' => (string)$row['billing_cycle'],
            'next_due_date' => $row['next_due_date'] ?? null,
            'provider_order_id' => $row['provider_order_id'] ?? null,
            'provider_entity_id' => $row['provider_entity_id'] ?? null,
            'product_slug' => $row['product_slug'] ?? null,
            'job_type' => $config['job_type'] ?? null,
        ];
    }
}

$tickets = [];
if (hivenest_crm_customer_profile_table_exists($db, 'support_tickets')) {
    $ticketStmt = $db->prepare("
        SELECT id, ticket_number, subject, status, priority, category, created_at, updated_at
        FROM support_tickets
        WHERE customer_id = :customer_id
        ORDER BY id DESC
        LIMIT 10
    ");
    $ticketStmt->execute(['customer_id' => $customerId]);
    foreach ($ticketStmt->fetchAll() ?: [] as $row) {
        $tickets[] = [
            'id' => (int)$row['id'],
            'ticket_number' => (string)$row['ticket_number'],
            'subject' => (string)$row['subject'],
            'status' => (string)$row['status'],
            'priority' => (string)$row['priority'],
            'category' => (string)$row['category'],
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}

$notes = [];
if (hivenest_crm_customer_profile_table_exists($db, 'customer_notes')) {
    $noteStmt = $db->prepare("
        SELECT n.*, a.username AS admin_username, a.email AS admin_email
        FROM customer_notes n
        LEFT JOIN admin_users a ON a.id = n.author_admin_id
        WHERE n.customer_id = :customer_id
        ORDER BY n.id DESC
        LIMIT 30
    ");
    $noteStmt->execute(['customer_id' => $customerId]);
    foreach ($noteStmt->fetchAll() ?: [] as $row) {
        $notes[] = [
            'id' => (int)$row['id'],
            'visibility' => (string)$row['visibility'],
            'note_type' => (string)$row['note_type'],
            'note_text' => (string)$row['note_text'],
            'author_type' => (string)$row['author_type'],
            'author' => $row['admin_username'] ?? $row['admin_email'] ?? 'HiveNest',
            'created_at' => $row['created_at'] ?? null,
        ];
    }
}

$name = trim((string)($customer['first_name'] ?? '') . ' ' . (string)($customer['last_name'] ?? ''));
hivenest_crm_customer_profile_out(200, [
    'customer' => [
        'id' => (int)$customer['id'],
        'uuid' => (string)$customer['uuid'],
        'customer_type' => (string)($customer['customer_type'] ?? 'individual'),
        'email' => (string)$customer['email'],
        'name' => $name,
        'first_name' => $customer['first_name'] ?? null,
        'last_name' => $customer['last_name'] ?? null,
        'company_name' => $customer['company_name'] ?? null,
        'phone' => $customer['phone'] ?? null,
        'country_code' => $customer['country_code'] ?? null,
        'address_line1' => $customer['address_line1'] ?? null,
        'address_line2' => $customer['address_line2'] ?? null,
        'city' => $customer['city'] ?? null,
        'state' => $customer['state'] ?? null,
        'postal_code' => $customer['postal_code'] ?? null,
        'country' => $customer['country'] ?? null,
        'status' => (string)($customer['status'] ?? 'active'),
        'email_verified' => (int)($customer['email_verified'] ?? 0) === 1,
        'preferred_currency' => (string)($customer['preferred_currency'] ?? 'USD'),
        'credit_balance' => (float)($customer['credit_balance'] ?? 0),
        'myorderbox_customer_id' => $customer['myorderbox_customer_id'] ?? null,
        'myorderbox_sync_status' => (string)($customer['myorderbox_sync_status'] ?? 'not_synced'),
        'myorderbox_last_sync_at' => $customer['myorderbox_last_sync_at'] ?? null,
        'myorderbox_sync_error' => $customer['myorderbox_sync_error'] ?? null,
        'last_login' => $customer['last_login'] ?? null,
        'created_at' => $customer['created_at'] ?? null,
        'updated_at' => $customer['updated_at'] ?? null,
        'stats' => [
            'orders' => (int)$customer['order_count'],
            'services' => (int)$customer['service_count'],
            'open_tickets' => (int)$customer['open_ticket_count'],
            'lifetime_paid' => (float)$customer['lifetime_paid'],
        ],
    ],
    'loyalty' => $loyalty,
    'orders' => $orders,
    'services' => $services,
    'tickets' => $tickets,
    'notes' => $notes,
]);
