<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
    session_name('HIVENEST_ADMIN');
    session_start();
}

require_once __DIR__ . '/../access/dbconfig.php';

function hivenest_crm_customers_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_crm_customers_env(string $key, string $default = ''): string
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

function hivenest_crm_customers_b64url_decode(string $value): string|false
{
    $remainder = strlen($value) % 4;
    if ($remainder) $value .= str_repeat('=', 4 - $remainder);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function hivenest_crm_customers_b64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function hivenest_crm_customers_bearer_token(): string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(.+)/i', $header, $match)) return trim($match[1]);
    return trim((string)($_COOKIE['hivenest_admin_access_token'] ?? ''));
}

function hivenest_crm_customers_authed(): bool
{
    return !empty($_SESSION['admin_user']) && !empty($_SESSION['admin_login_time']);
}

function hivenest_crm_customers_verify_admin_jwt(PDO $db): bool
{
    $token = hivenest_crm_customers_bearer_token();
    if ($token === '') return false;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    [$header64, $payload64, $signature64] = $parts;
    $headerJson = hivenest_crm_customers_b64url_decode($header64);
    $payloadJson = hivenest_crm_customers_b64url_decode($payload64);
    if ($headerJson === false || $payloadJson === false) return false;
    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);
    if (!is_array($header) || !is_array($payload)) return false;
    if (($header['alg'] ?? '') !== hivenest_crm_customers_env('JWT_ALGORITHM', 'HS256')) return false;
    if (($payload['user_type'] ?? '') !== 'admin') return false;
    if (!empty($payload['exp']) && (int)$payload['exp'] < time()) return false;

    $secret = hivenest_crm_customers_env('JWT_SECRET_KEY');
    if ($secret === '') return false;
    $expected = hivenest_crm_customers_b64url_encode(hash_hmac('sha256', $header64 . '.' . $payload64, $secret, true));
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

function hivenest_crm_customers_table_exists(PDO $db, string $table): bool
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
    hivenest_crm_customers_out(405, ['error' => 'GET required.']);
}

$db = hivenest_db();
if (!$db) hivenest_crm_customers_out(503, ['error' => 'CRM database is unavailable.']);
if (!hivenest_crm_customers_authed() && !hivenest_crm_customers_verify_admin_jwt($db)) {
    hivenest_crm_customers_out(401, ['error' => 'Admin login required.']);
}

$query = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$params = [];
$where = [];
if ($query !== '') {
    $where[] = "(c.email LIKE :q OR c.first_name LIKE :q OR c.last_name LIKE :q OR c.company_name LIKE :q OR c.myorderbox_customer_id LIKE :q)";
    $params['q'] = '%' . $query . '%';
}
if ($status !== '') {
    $where[] = 'c.status = :status';
    $params['status'] = $status;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT
        c.id, c.uuid, c.customer_type, c.email, c.first_name, c.last_name, c.company_name, c.phone,
        c.country_code, c.address_line1, c.address_line2, c.city, c.state, c.postal_code, c.country,
        c.status, c.email_verified, c.preferred_currency, c.credit_balance,
        c.myorderbox_customer_id, c.myorderbox_sync_status, c.myorderbox_last_sync_at, c.myorderbox_sync_error,
        c.last_login, c.created_at, c.updated_at,
        COUNT(DISTINCT o.id) AS order_count,
        COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END), 0) AS lifetime_paid,
        COUNT(DISTINCT s.id) AS service_count,
        COUNT(DISTINCT CASE WHEN st.status NOT IN ('closed','resolved') THEN st.id END) AS open_ticket_count
    FROM customers c
    LEFT JOIN orders o ON o.customer_id = c.id
    LEFT JOIN services s ON s.customer_id = c.id
    LEFT JOIN support_tickets st ON st.customer_id = c.id
    {$whereSql}
    GROUP BY c.id
    ORDER BY c.id DESC
    LIMIT 150
");
$stmt->execute($params);
$rows = $stmt->fetchAll() ?: [];
$notesByCustomer = [];
if ($rows && hivenest_crm_customers_table_exists($db, 'customer_notes')) {
    $customerIds = array_map(static fn($row) => (int)$row['id'], $rows);
    $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
    $notesStmt = $db->prepare("
        SELECT n.*, a.username AS admin_username
        FROM customer_notes n
        LEFT JOIN admin_users a ON a.id = n.author_admin_id
        WHERE n.customer_id IN ({$placeholders})
        ORDER BY n.id DESC
    ");
    $notesStmt->execute($customerIds);
    foreach ($notesStmt->fetchAll() ?: [] as $note) {
        $customerKey = (int)$note['customer_id'];
        if (count($notesByCustomer[$customerKey] ?? []) >= 5) continue;
        $notesByCustomer[$customerKey][] = [
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
$customers = [];
foreach ($rows as $row) {
    $customers[] = [
        'id' => (int)$row['id'],
        'uuid' => (string)$row['uuid'],
        'customer_type' => (string)($row['customer_type'] ?? 'individual'),
        'email' => (string)$row['email'],
        'name' => trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? '')),
        'first_name' => $row['first_name'] ?? null,
        'last_name' => $row['last_name'] ?? null,
        'company_name' => $row['company_name'] ?? null,
        'phone' => $row['phone'] ?? null,
        'address' => [
            'line1' => $row['address_line1'] ?? null,
            'line2' => $row['address_line2'] ?? null,
            'city' => $row['city'] ?? null,
            'state' => $row['state'] ?? null,
            'postal_code' => $row['postal_code'] ?? null,
            'country' => $row['country'] ?? null,
            'country_code' => $row['country_code'] ?? null,
        ],
        'status' => (string)($row['status'] ?? 'active'),
        'email_verified' => (int)($row['email_verified'] ?? 0) === 1,
        'preferred_currency' => (string)($row['preferred_currency'] ?? 'USD'),
        'credit_balance' => (float)($row['credit_balance'] ?? 0),
        'myorderbox_customer_id' => $row['myorderbox_customer_id'] ?? null,
        'myorderbox_sync_status' => (string)($row['myorderbox_sync_status'] ?? 'not_synced'),
        'myorderbox_last_sync_at' => $row['myorderbox_last_sync_at'] ?? null,
        'myorderbox_sync_error' => $row['myorderbox_sync_error'] ?? null,
        'last_login' => $row['last_login'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
        'stats' => [
            'orders' => (int)$row['order_count'],
            'services' => (int)$row['service_count'],
            'open_tickets' => (int)$row['open_ticket_count'],
            'lifetime_paid' => (float)$row['lifetime_paid'],
        ],
        'notes' => $notesByCustomer[(int)$row['id']] ?? [],
    ];
}

hivenest_crm_customers_out(200, ['customers' => $customers]);
