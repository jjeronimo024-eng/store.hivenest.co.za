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

function hivenest_auth_me_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    hivenest_auth_me_out(405, ['error' => 'GET required.']);
}

$session = hivenest_customer_session_status(true);
if (!$session['authenticated']) {
    hivenest_auth_me_out(401, [
        'authenticated' => false,
        'expired' => (bool)$session['expired'],
        'error' => $session['expired'] ? 'Customer session expired.' : 'Customer login required.',
    ]);
}

$db = hivenest_db();
if (!$db) {
    hivenest_auth_me_out(503, ['error' => 'Customer database is unavailable.']);
}

$stmt = $db->prepare("
    SELECT
        id, uuid, email, first_name, last_name, company_name,
        status, email_verified, preferred_currency, last_login
    FROM customers
    WHERE id = :customer_id
    LIMIT 1
");
$stmt->execute(['customer_id' => (int)$session['customer_id']]);
$customer = $stmt->fetch();

if (!$customer || (string)($customer['status'] ?? '') !== 'active') {
    hivenest_customer_session_destroy();
    hivenest_auth_me_out(403, [
        'authenticated' => false,
        'error' => 'Customer account is not active.',
    ]);
}

hivenest_auth_me_out(200, [
    'authenticated' => true,
    'expires_in' => (int)$session['expires_in'],
    'csrf_token' => hivenest_customer_csrf_token(),
    'customer' => [
        'id' => (int)$customer['id'],
        'uuid' => (string)$customer['uuid'],
        'email' => (string)$customer['email'],
        'first_name' => (string)($customer['first_name'] ?? ''),
        'last_name' => (string)($customer['last_name'] ?? ''),
        'company_name' => $customer['company_name'] ?? null,
        'email_verified' => (int)($customer['email_verified'] ?? 0) === 1,
        'preferred_currency' => (string)($customer['preferred_currency'] ?? 'USD'),
        'last_login' => $customer['last_login'] ?? null,
    ],
]);
