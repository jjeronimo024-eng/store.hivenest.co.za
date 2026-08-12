<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../utilities/customer_session.php';
hivenest_customer_session_configure();
session_start();
require_once __DIR__ . '/../access/dbconfig.php';

function session_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    session_out(405, ['error' => 'POST required']);
}

$input = json_decode((string) file_get_contents('php://input'), true) ?: [];
$token = trim((string) ($input['access_token'] ?? ''));
if ($token === '' || strlen($token) > 4096) {
    session_out(400, ['error' => 'A valid access token is required.']);
}
if (!function_exists('curl_init')) {
    session_out(503, ['error' => 'The PHP cURL extension is required.']);
}

$ch = curl_init('https://api.hivenest.co.za/api/auth/me');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer ' . $token],
    CURLOPT_TIMEOUT => 20,
]);
$body = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($body === false || $error !== '') {
    session_out(502, ['error' => 'Customer authentication service is unavailable.']);
}
$data = json_decode((string) $body, true);
$user = is_array($data) ? ($data['user'] ?? null) : null;
if ($status !== 200 || !is_array($user) || ($data['user_type'] ?? '') !== 'customer' || (int) ($user['id'] ?? 0) <= 0) {
    session_out(401, ['error' => 'Customer authentication could not be verified.']);
}

$db = hivenest_db();
if (!$db) {
    session_out(503, ['error' => 'Customer database is unavailable.']);
}
$customerStmt = $db->prepare(
    "SELECT id, email, auth_version
     FROM customers
     WHERE id = :customer_id AND status = 'active'
     LIMIT 1"
);
$customerStmt->execute(['customer_id' => (int)$user['id']]);
$customer = $customerStmt->fetch();
if (!$customer) {
    session_out(401, ['error' => 'Customer account is not active.']);
}

session_regenerate_id(true);
$_SESSION['customer_id'] = (int)$customer['id'];
$_SESSION['customer_email'] = (string)$customer['email'];
$_SESSION['customer_auth_version'] = (int)$customer['auth_version'];
$_SESSION['customer_authenticated_at'] = time();
$_SESSION['customer_last_activity_at'] = time();

session_out(200, ['authenticated' => true, 'customer_id' => $_SESSION['customer_id']]);
