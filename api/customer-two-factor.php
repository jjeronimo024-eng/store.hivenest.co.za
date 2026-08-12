<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../utilities/customer_session.php';
hivenest_customer_session_configure();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/two_factor.php';

function customer_2fa_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

$customerId = (int)($_SESSION['customer_id'] ?? 0);
if ($customerId <= 0) customer_2fa_out(401, ['error' => 'Customer login required.']);
$db = hivenest_db();
if (!$db) customer_2fa_out(503, ['error' => 'Customer database is unavailable.']);

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$input = $method === 'POST'
    ? (json_decode((string)file_get_contents('php://input'), true) ?: [])
    : [];
$action = strtolower(trim((string)($input['action'] ?? $_GET['action'] ?? 'status')));

$select = $db->prepare('SELECT email,password_hash,two_factor_enabled,two_factor_secret FROM customers WHERE id=:id AND status="active" LIMIT 1');
$select->execute(['id' => $customerId]);
$customer = $select->fetch(PDO::FETCH_ASSOC);
if (!$customer) customer_2fa_out(403, ['error' => 'Customer account is unavailable.']);

if ($method === 'GET' && $action === 'status') {
    customer_2fa_out(200, ['enabled' => (int)$customer['two_factor_enabled'] === 1]);
}
if ($method !== 'POST') customer_2fa_out(405, ['error' => 'Method not allowed.']);

if ($action === 'start') {
    if ((int)$customer['two_factor_enabled'] === 1) {
        customer_2fa_out(409, ['error' => 'Two-factor authentication is already enabled.']);
    }
    try {
        $secret = hivenest_2fa_secret();
        $_SESSION['customer_2fa_pending_secret'] = hivenest_2fa_encrypt($secret);
        $_SESSION['customer_2fa_pending_expires'] = time() + 600;
        $label = rawurlencode('HiveNest:' . (string)$customer['email']);
        $uri = 'otpauth://totp/' . $label . '?secret=' . $secret
            . '&issuer=HiveNest&algorithm=SHA1&digits=6&period=30';
        customer_2fa_out(200, [
            'secret' => $secret,
            'otpauth_uri' => $uri,
            'expires_in' => 600,
        ]);
    } catch (Throwable $e) {
        error_log('Customer 2FA enrollment start failed: ' . $e->getMessage());
        customer_2fa_out(503, ['error' => 'Two-factor authentication is not configured on this server.']);
    }
}

if ($action === 'confirm') {
    $encrypted = (string)($_SESSION['customer_2fa_pending_secret'] ?? '');
    if ($encrypted === '' || (int)($_SESSION['customer_2fa_pending_expires'] ?? 0) < time()) {
        customer_2fa_out(410, ['error' => 'Enrollment expired. Start again.']);
    }
    try {
        $secret = hivenest_2fa_decrypt($encrypted);
        if (!hivenest_2fa_verify_totp($secret, (string)($input['code'] ?? ''))) {
            customer_2fa_out(422, ['error' => 'Authenticator code is invalid.']);
        }
        $codes = hivenest_2fa_recovery_codes();
        $db->beginTransaction();
        $update = $db->prepare(
            'UPDATE customers SET two_factor_enabled=1,two_factor_secret=:secret,two_factor_confirmed_at=NOW(),auth_version=auth_version+1 WHERE id=:id'
        );
        $update->execute(['secret' => $encrypted, 'id' => $customerId]);
        hivenest_2fa_store_recovery_codes($db, 'customer', $customerId, $codes);
        $db->commit();
        unset($_SESSION['customer_2fa_pending_secret'], $_SESSION['customer_2fa_pending_expires']);
        $_SESSION['customer_auth_version'] = (int)($_SESSION['customer_auth_version'] ?? 1) + 1;
        customer_2fa_out(200, [
            'enabled' => true,
            'recovery_codes' => $codes,
            'message' => 'Two-factor authentication enabled. Store these recovery codes securely; they are shown once.',
        ]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('Customer 2FA confirmation failed: ' . $e->getMessage());
        customer_2fa_out(500, ['error' => 'Two-factor authentication could not be enabled.']);
    }
}

if ($action === 'disable') {
    $password = (string)($input['password'] ?? '');
    $code = (string)($input['code'] ?? '');
    if (!password_verify($password, (string)$customer['password_hash'])) {
        customer_2fa_out(422, ['error' => 'Current password is incorrect.']);
    }
    try {
        $secret = hivenest_2fa_decrypt((string)$customer['two_factor_secret']);
        $valid = hivenest_2fa_verify_totp($secret, $code)
            || hivenest_2fa_use_recovery_code($db, 'customer', $customerId, $code);
        if (!$valid) customer_2fa_out(422, ['error' => 'Authenticator or recovery code is invalid.']);
        $db->beginTransaction();
        $update = $db->prepare(
            'UPDATE customers SET two_factor_enabled=0,two_factor_secret=NULL,two_factor_confirmed_at=NULL,auth_version=auth_version+1 WHERE id=:id'
        );
        $update->execute(['id' => $customerId]);
        $delete = $db->prepare('DELETE FROM two_factor_recovery_codes WHERE account_type="customer" AND account_id=:id');
        $delete->execute(['id' => $customerId]);
        $db->commit();
        $_SESSION['customer_auth_version'] = (int)($_SESSION['customer_auth_version'] ?? 1) + 1;
        customer_2fa_out(200, ['enabled' => false, 'message' => 'Two-factor authentication disabled.']);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('Customer 2FA disable failed: ' . $e->getMessage());
        customer_2fa_out(500, ['error' => 'Two-factor authentication could not be disabled.']);
    }
}

customer_2fa_out(400, ['error' => 'Unknown two-factor action.']);

