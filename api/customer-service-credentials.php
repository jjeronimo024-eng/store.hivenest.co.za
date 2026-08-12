<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');

require_once __DIR__ . '/../utilities/customer_session.php';
hivenest_customer_session_configure();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/service_credentials.php';
require_once __DIR__ . '/../utilities/two_factor.php';
require_once __DIR__ . '/../utilities/rate_limiter.php';

function hivenest_customer_credentials_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_customer_credentials_table_exists(PDO $db, string $table): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name'
    );
    $stmt->execute(['table_name' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

$sessionState = hivenest_customer_session_status(true);
if (!$sessionState['authenticated']) {
    hivenest_customer_credentials_out(401, [
        'error' => $sessionState['expired'] ? 'Customer session expired.' : 'Customer login required.',
    ]);
}
$customerId = (int)($_SESSION['customer_id'] ?? 0);
if ($customerId <= 0) hivenest_customer_credentials_out(401, ['error' => 'Customer login required.']);
$db = hivenest_db();
if (!$db) hivenest_customer_credentials_out(503, ['error' => 'Customer database is unavailable.']);
if (
    !hivenest_customer_credentials_table_exists($db, 'service_credentials')
    || !hivenest_customer_credentials_table_exists($db, 'service_credential_access_audit')
) {
    hivenest_customer_credentials_out(503, ['error' => 'The encrypted credential vault has not been installed.']);
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'POST') hivenest_customer_csrf_require_json();
$input = $method === 'POST'
    ? (json_decode((string)file_get_contents('php://input'), true) ?: [])
    : [];
$serviceId = (int)($input['service_id'] ?? $_GET['service_id'] ?? 0);
if ($serviceId <= 0) hivenest_customer_credentials_out(422, ['error' => 'Service is required.']);

$serviceStmt = $db->prepare(
    'SELECT id,customer_id FROM services WHERE id=:service_id AND customer_id=:customer_id LIMIT 1'
);
$serviceStmt->execute(['service_id' => $serviceId, 'customer_id' => $customerId]);
if (!$serviceStmt->fetch()) hivenest_customer_credentials_out(404, ['error' => 'Service not found.']);

if ($method === 'GET') {
    $stmt = $db->prepare(
        'SELECT id,uuid,service_id,customer_id,credential_type,label,username,login_url,
                metadata_json,status,expires_at,rotated_at,created_at,updated_at
         FROM service_credentials
         WHERE service_id=:service_id AND customer_id=:customer_id AND status="active"
         ORDER BY credential_type,label,id'
    );
    $stmt->execute(['service_id' => $serviceId, 'customer_id' => $customerId]);
    hivenest_customer_credentials_out(200, [
        'credentials' => array_map('hivenest_service_credentials_metadata', $stmt->fetchAll() ?: []),
    ]);
}
if ($method !== 'POST') hivenest_customer_credentials_out(405, ['error' => 'Method not allowed.']);
if (strtolower(trim((string)($input['action'] ?? ''))) !== 'reveal') {
    hivenest_customer_credentials_out(422, ['error' => 'Only the reveal action is available to customers.']);
}

$limit = hivenest_rate_limit('customer-credential-reveal', 5, 600, 'customer:' . $customerId);
if (!$limit['allowed']) {
    header('Retry-After: ' . $limit['retry_after']);
    hivenest_customer_credentials_out(429, ['error' => 'Too many credential reveal attempts. Try again later.']);
}

$accountStmt = $db->prepare(
    'SELECT password_hash,two_factor_enabled,two_factor_secret
     FROM customers WHERE id=:id AND status="active" LIMIT 1'
);
$accountStmt->execute(['id' => $customerId]);
$account = $accountStmt->fetch(PDO::FETCH_ASSOC);
if (!$account || !password_verify((string)($input['password'] ?? ''), (string)$account['password_hash'])) {
    hivenest_customer_credentials_out(422, ['error' => 'Current password is incorrect.']);
}

if ((int)($account['two_factor_enabled'] ?? 0) === 1) {
    $code = (string)($input['code'] ?? '');
    try {
        $secret = hivenest_2fa_decrypt((string)$account['two_factor_secret']);
        $valid = hivenest_2fa_verify_totp($secret, $code)
            || hivenest_2fa_use_recovery_code($db, 'customer', $customerId, $code);
        if (!$valid) hivenest_customer_credentials_out(422, ['error' => 'Authenticator or recovery code is invalid.']);
    } catch (Throwable $e) {
        error_log('Customer credential 2FA check failed: ' . $e->getMessage());
        hivenest_customer_credentials_out(503, ['error' => 'Credential verification is temporarily unavailable.']);
    }
}

$credentialId = (int)($input['credential_id'] ?? 0);
$stmt = $db->prepare(
    'SELECT * FROM service_credentials
     WHERE id=:id AND service_id=:service_id AND customer_id=:customer_id
       AND status="active" AND (expires_at IS NULL OR expires_at > NOW())
     LIMIT 1'
);
$stmt->execute(['id' => $credentialId, 'service_id' => $serviceId, 'customer_id' => $customerId]);
$credential = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$credential) hivenest_customer_credentials_out(404, ['error' => 'Active credential not found.']);

try {
    $plainText = hivenest_service_credentials_decrypt((string)$credential['secret_ciphertext']);
    hivenest_service_credentials_audit(
        $db,
        (int)$credential['id'],
        $serviceId,
        $customerId,
        'customer',
        $customerId,
        'revealed'
    );
    hivenest_customer_credentials_out(200, [
        'credential' => hivenest_service_credentials_metadata($credential),
        'secret' => $plainText,
        'warning' => 'This secret is sensitive. Do not share it or store it in an unsecured location.',
    ]);
} catch (Throwable $e) {
    error_log('Customer credential reveal failed: ' . $e->getMessage());
    hivenest_customer_credentials_out(503, ['error' => 'Credential could not be unlocked.']);
}
