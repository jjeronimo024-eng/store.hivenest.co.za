<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
    session_name('HIVENEST_ADMIN');
    session_start();
}

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/crm_permissions.php';

function hivenest_crm_customer_actions_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_crm_customer_actions_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function hivenest_crm_customer_actions_clean(string $value, int $max = 5000): string
{
    $value = trim(str_replace(["\0"], '', $value));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function hivenest_crm_customer_actions_nullable(string $value, int $max = 255): ?string
{
    $value = hivenest_crm_customer_actions_clean($value, $max);
    return $value === '' ? null : $value;
}

function hivenest_crm_customer_actions_env(string $key, string $default = ''): string
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

function hivenest_crm_customer_actions_b64url_decode(string $value): string|false
{
    $remainder = strlen($value) % 4;
    if ($remainder) $value .= str_repeat('=', 4 - $remainder);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function hivenest_crm_customer_actions_b64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function hivenest_crm_customer_actions_bearer_token(): string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(.+)/i', $header, $match)) return trim($match[1]);
    return trim((string)($_COOKIE['hivenest_admin_access_token'] ?? ''));
}

function hivenest_crm_customer_actions_admin_id(PDO $db): int
{
    if (!empty($_SESSION['admin_user']['id']) && !empty($_SESSION['admin_login_time'])) return (int)$_SESSION['admin_user']['id'];
    $token = hivenest_crm_customer_actions_bearer_token();
    if ($token === '') return 0;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return 0;
    [$header64, $payload64, $signature64] = $parts;
    $headerJson = hivenest_crm_customer_actions_b64url_decode($header64);
    $payloadJson = hivenest_crm_customer_actions_b64url_decode($payload64);
    if ($headerJson === false || $payloadJson === false) return 0;
    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);
    if (!is_array($header) || !is_array($payload)) return 0;
    if (($header['alg'] ?? '') !== hivenest_crm_customer_actions_env('JWT_ALGORITHM', 'HS256')) return 0;
    if (($payload['user_type'] ?? '') !== 'admin') return 0;
    if (!empty($payload['exp']) && (int)$payload['exp'] < time()) return 0;
    $secret = hivenest_crm_customer_actions_env('JWT_SECRET_KEY');
    if ($secret === '') return 0;
    $expected = hivenest_crm_customer_actions_b64url_encode(hash_hmac('sha256', $header64 . '.' . $payload64, $secret, true));
    if (!hash_equals($expected, $signature64)) return 0;
    $adminId = (int)($payload['sub'] ?? 0);
    if ($adminId <= 0) return 0;
    $stmt = $db->prepare('SELECT id, username, email, role FROM admin_users WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => $adminId]);
    $admin = $stmt->fetch();
    if (!$admin) return 0;
    $_SESSION['admin_user'] = ['id' => (int)$admin['id'], 'username' => (string)$admin['username'], 'email' => (string)$admin['email'], 'role' => (string)($admin['role'] ?? 'admin')];
    $_SESSION['admin_login_time'] = $_SESSION['admin_login_time'] ?? time();
    return (int)$admin['id'];
}

function hivenest_crm_customer_actions_table_exists(PDO $db, string $table): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table");
    $stmt->execute(['table' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function hivenest_crm_customer_actions_ensure_schema(PDO $db): void
{
    if (hivenest_crm_customer_actions_table_exists($db, 'customer_notes')) return;
    $db->exec("
        CREATE TABLE IF NOT EXISTS customer_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            uuid VARCHAR(36) NOT NULL UNIQUE,
            customer_id INT NOT NULL,
            author_type ENUM('admin','customer','system') NOT NULL DEFAULT 'admin',
            author_admin_id INT NULL,
            author_customer_id INT NULL,
            visibility ENUM('internal','client') NOT NULL DEFAULT 'internal',
            note_type ENUM('note','account_update','billing','support','risk','sales') NOT NULL DEFAULT 'note',
            note_text TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer_created (customer_id, created_at),
            INDEX idx_visibility (visibility)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

$db = hivenest_db();
if (!$db) hivenest_crm_customer_actions_out(503, ['error' => 'CRM database is unavailable.']);
$adminId = hivenest_crm_customer_actions_admin_id($db);
if ($adminId <= 0) hivenest_crm_customer_actions_out(401, ['error' => 'Admin login required.']);
hivenest_crm_customer_actions_ensure_schema($db);

$customerId = (int)($_GET['customer_id'] ?? $_GET['customer'] ?? $_POST['customer_id'] ?? $_POST['customer'] ?? 0);
if ($customerId <= 0) hivenest_crm_customer_actions_out(422, ['error' => 'Customer is required.']);

$customerStmt = $db->prepare('SELECT id, email, status FROM customers WHERE id = :id LIMIT 1');
$customerStmt->execute(['id' => $customerId]);
$customer = $customerStmt->fetch();
if (!$customer) hivenest_crm_customer_actions_out(404, ['error' => 'Customer not found.']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hivenest_crm_role_allows(hivenest_crm_admin_record($db, $adminId), 'customer.manage')) {
        hivenest_crm_customer_actions_out(403, ['error' => 'Your staff role cannot change customer records.']);
    }
    $action = hivenest_crm_customer_actions_clean((string)($_POST['action'] ?? ''), 40);

    if ($action === 'add_note') {
        $note = hivenest_crm_customer_actions_clean((string)($_POST['note_text'] ?? ''), 5000);
        if ($note === '') hivenest_crm_customer_actions_out(422, ['error' => 'Note text is required.']);
        $visibility = (string)($_POST['visibility'] ?? 'internal') === 'client' ? 'client' : 'internal';
        $noteType = hivenest_crm_customer_actions_clean((string)($_POST['note_type'] ?? 'note'), 40);
        $allowedTypes = ['note','account_update','billing','support','risk','sales'];
        if (!in_array($noteType, $allowedTypes, true)) $noteType = 'note';
        $insert = $db->prepare("
            INSERT INTO customer_notes
                (uuid, customer_id, author_type, author_admin_id, visibility, note_type, note_text)
            VALUES
                (:uuid, :customer_id, 'admin', :admin_id, :visibility, :note_type, :note_text)
        ");
        $insert->execute([
            'uuid' => hivenest_crm_customer_actions_uuid(),
            'customer_id' => $customerId,
            'admin_id' => $adminId,
            'visibility' => $visibility,
            'note_type' => $noteType,
            'note_text' => $note,
        ]);
        hivenest_crm_customer_actions_out(200, ['ok' => true, 'message' => 'Customer note added.']);
    }

    if ($action === 'update_status') {
        $status = hivenest_crm_customer_actions_clean((string)($_POST['status'] ?? ''), 40);
        $allowedStatuses = ['active','pending','suspended','disabled'];
        if (!in_array($status, $allowedStatuses, true)) hivenest_crm_customer_actions_out(422, ['error' => 'Invalid customer status.']);
        $reason = hivenest_crm_customer_actions_clean((string)($_POST['reason'] ?? ''), 5000);
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE customers SET status = :status WHERE id = :id')->execute(['status' => $status, 'id' => $customerId]);
            $noteText = $reason !== '' ? $reason : 'Customer status changed from ' . (string)$customer['status'] . ' to ' . $status . '.';
            $insert = $db->prepare("
                INSERT INTO customer_notes
                    (uuid, customer_id, author_type, author_admin_id, visibility, note_type, note_text)
                VALUES
                    (:uuid, :customer_id, 'admin', :admin_id, 'internal', 'account_update', :note_text)
            ");
            $insert->execute([
                'uuid' => hivenest_crm_customer_actions_uuid(),
                'customer_id' => $customerId,
                'admin_id' => $adminId,
                'note_text' => $noteText,
            ]);
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            hivenest_crm_customer_actions_out(500, ['error' => 'Customer status could not be updated.']);
        }
        hivenest_crm_customer_actions_out(200, ['ok' => true, 'message' => 'Customer status updated.']);
    }

    if ($action === 'update_profile') {
        $firstName = hivenest_crm_customer_actions_nullable((string)($_POST['first_name'] ?? ''), 100);
        $lastName = hivenest_crm_customer_actions_nullable((string)($_POST['last_name'] ?? ''), 100);
        $phone = hivenest_crm_customer_actions_nullable((string)($_POST['phone'] ?? ''), 50);
        $countryCode = strtoupper((string)(hivenest_crm_customer_actions_nullable((string)($_POST['country_code'] ?? 'ZA'), 3) ?: 'ZA'));
        $country = hivenest_crm_customer_actions_nullable((string)($_POST['country'] ?? ''), 100);
        $currency = strtoupper((string)(hivenest_crm_customer_actions_nullable((string)($_POST['preferred_currency'] ?? 'USD'), 3) ?: 'USD'));
        if ($firstName === null || $lastName === null) hivenest_crm_customer_actions_out(422, ['error' => 'First and last name are required.']);
        if ($phone === null) hivenest_crm_customer_actions_out(422, ['error' => 'Phone number is required.']);
        if (!preg_match('/^[A-Z]{2,3}$/', $countryCode)) hivenest_crm_customer_actions_out(422, ['error' => 'Country code must be 2 or 3 letters.']);
        if ($country === null) hivenest_crm_customer_actions_out(422, ['error' => 'Country is required.']);
        if (!in_array($currency, ['USD','ZAR','EUR','SGD'], true)) hivenest_crm_customer_actions_out(422, ['error' => 'Preferred currency must be USD, ZAR, EUR, or SGD.']);

        $fields = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company_name' => hivenest_crm_customer_actions_nullable((string)($_POST['company_name'] ?? ''), 255),
            'phone' => $phone,
            'country_code' => $countryCode,
            'address_line1' => hivenest_crm_customer_actions_nullable((string)($_POST['address_line1'] ?? ''), 255),
            'address_line2' => hivenest_crm_customer_actions_nullable((string)($_POST['address_line2'] ?? ''), 255),
            'city' => hivenest_crm_customer_actions_nullable((string)($_POST['city'] ?? ''), 100),
            'state' => hivenest_crm_customer_actions_nullable((string)($_POST['state'] ?? ''), 100),
            'postal_code' => hivenest_crm_customer_actions_nullable((string)($_POST['postal_code'] ?? ''), 20),
            'country' => $country,
            'preferred_currency' => $currency,
        ];
        foreach (['address_line1','city','state','postal_code'] as $required) {
            if ($fields[$required] === null) hivenest_crm_customer_actions_out(422, ['error' => 'Address, city, state/province, and postal code are required.']);
        }

        $db->beginTransaction();
        try {
            $sets = [];
            $params = ['id' => $customerId];
            foreach ($fields as $field => $value) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
            $db->prepare('UPDATE customers SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :id')->execute($params);
            $insert = $db->prepare("
                INSERT INTO customer_notes
                    (uuid, customer_id, author_type, author_admin_id, visibility, note_type, note_text)
                VALUES
                    (:uuid, :customer_id, 'admin', :admin_id, 'internal', 'account_update', :note_text)
            ");
            $insert->execute([
                'uuid' => hivenest_crm_customer_actions_uuid(),
                'customer_id' => $customerId,
                'admin_id' => $adminId,
                'note_text' => 'Customer profile/contact details updated from CRM.',
            ]);
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            error_log('CRM customer profile update failed: ' . $error->getMessage());
            hivenest_crm_customer_actions_out(500, ['error' => 'Customer profile could not be updated.']);
        }
        hivenest_crm_customer_actions_out(200, ['ok' => true, 'message' => 'Customer profile updated.']);
    }

    hivenest_crm_customer_actions_out(422, ['error' => 'Unknown customer action.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') hivenest_crm_customer_actions_out(405, ['error' => 'Method not allowed.']);

$notesStmt = $db->prepare("
    SELECT n.*, a.username AS admin_username, a.email AS admin_email
    FROM customer_notes n
    LEFT JOIN admin_users a ON a.id = n.author_admin_id
    WHERE n.customer_id = :customer_id
    ORDER BY n.id DESC
    LIMIT 100
");
$notesStmt->execute(['customer_id' => $customerId]);
$notes = [];
foreach ($notesStmt->fetchAll() ?: [] as $row) {
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

hivenest_crm_customer_actions_out(200, [
    'customer' => [
        'id' => (int)$customer['id'],
        'email' => (string)$customer['email'],
        'status' => (string)$customer['status'],
    ],
    'notes' => $notes,
]);
