<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../utilities/customer_session.php';
hivenest_customer_session_configure();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/customer_notifications.php';

function hivenest_change_password_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hivenest_change_password_out(405, ['error' => 'POST required.']);
}

$customerId = (int)($_SESSION['customer_id'] ?? 0);
if ($customerId <= 0) {
    hivenest_change_password_out(401, ['error' => 'Customer login required.']);
}

$origin = strtolower(trim((string)($_SERVER['HTTP_ORIGIN'] ?? '')));
$allowedOrigins = [
    'https://hivenest.co.za',
    'https://cp.hivenest.co.za',
    'https://hivenest.holohive.co.za',
];
if ($origin !== '' && !in_array($origin, $allowedOrigins, true)) {
    hivenest_change_password_out(403, ['error' => 'Request origin is not allowed.']);
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    hivenest_change_password_out(400, ['error' => 'Invalid JSON input.']);
}

$currentPassword = (string)($input['current_password'] ?? '');
$newPassword = (string)($input['new_password'] ?? '');
$confirmation = (string)($input['confirm_password'] ?? '');

if ($currentPassword === '' || $newPassword === '' || $confirmation === '') {
    hivenest_change_password_out(422, ['error' => 'Complete all password fields.']);
}
if (!hash_equals($newPassword, $confirmation)) {
    hivenest_change_password_out(422, ['error' => 'New password and confirmation do not match.']);
}
if (strlen($newPassword) < 12
    || !preg_match('/[a-z]/', $newPassword)
    || !preg_match('/[A-Z]/', $newPassword)
    || !preg_match('/\d/', $newPassword)
    || !preg_match('/[^a-zA-Z0-9]/', $newPassword)
) {
    hivenest_change_password_out(422, [
        'error' => 'New password must be at least 12 characters and include uppercase, lowercase, a number, and a symbol.',
    ]);
}

$db = hivenest_db();
if (!$db) {
    hivenest_change_password_out(503, ['error' => 'Customer database is unavailable.']);
}

$stmt = $db->prepare("
    SELECT id, password_hash, auth_version, status
    FROM customers
    WHERE id = :customer_id
    LIMIT 1
");
$stmt->execute(['customer_id' => $customerId]);
$customer = $stmt->fetch();
if (!$customer || (string)($customer['status'] ?? '') !== 'active') {
    hivenest_change_password_out(403, ['error' => 'This customer account is not active.']);
}

$existingHash = (string)($customer['password_hash'] ?? '');
if ($existingHash === '' || !password_verify($currentPassword, $existingHash)) {
    hivenest_change_password_out(422, ['error' => 'Current password is incorrect.']);
}
if (password_verify($newPassword, $existingHash)) {
    hivenest_change_password_out(422, ['error' => 'Choose a new password that is different from the current password.']);
}

try {
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    if (!is_string($newHash) || $newHash === '') {
        throw new RuntimeException('Password hashing failed.');
    }
    $update = $db->prepare("
        UPDATE customers
        SET password_hash = :password_hash,
            auth_version = auth_version + 1,
            updated_at = NOW()
        WHERE id = :customer_id
          AND status = 'active'
    ");
    $update->execute([
        'password_hash' => $newHash,
        'customer_id' => $customerId,
    ]);

    session_regenerate_id(true);
    $_SESSION['customer_auth_version'] = (int)$customer['auth_version'] + 1;
    $_SESSION['customer_authenticated_at'] = time();

    hivenest_notify_customer(
        $db,
        $customerId,
        'success',
        'Password changed',
        'Your HiveNest account password was changed successfully. Contact support immediately if you did not make this change.',
        '/account/profile.html',
        'account_security',
        $customerId
    );
} catch (Throwable $e) {
    error_log('Customer password change failed: ' . $e->getMessage());
    hivenest_change_password_out(500, ['error' => 'Password could not be changed. Please try again.']);
}

hivenest_change_password_out(200, [
    'success' => true,
    'message' => 'Password changed successfully.',
]);
