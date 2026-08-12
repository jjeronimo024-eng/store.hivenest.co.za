<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../utilities/customer_session.php';
hivenest_customer_session_configure();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/email_verification.php';

function ev_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ev_out(405, ['error' => 'POST required']);
}

$customerSession = hivenest_customer_session_status(true);
if (!$customerSession['authenticated']) {
    ev_out(401, ['error' => 'Sign in before requesting a verification email.']);
}
hivenest_customer_csrf_require_json();
$customerId = (int)$customerSession['customer_id'];

$db = hivenest_db();
if (!$db) {
    ev_out(503, ['error' => 'Customer database is unavailable.']);
}

$action = strtolower(trim((string) ($_GET['action'] ?? 'resend')));
if (!in_array($action, ['resend', 'status'], true)) {
    ev_out(400, ['error' => 'Unknown email verification action.']);
}

$stmt = $db->prepare('SELECT email, email_verified FROM customers WHERE id = :id AND status = "active" LIMIT 1');
$stmt->execute(['id' => $customerId]);
$customer = $stmt->fetch();
if (!$customer) {
    ev_out(404, ['error' => 'Customer account was not found.']);
}
if ((int) $customer['email_verified'] === 1) {
    $_SESSION['customer_email_verified'] = 1;
    ev_out(200, ['sent' => false, 'verified' => true, 'message' => 'Your email is already verified.']);
}

if ($action === 'status') {
    $_SESSION['customer_email_verified'] = 0;
    ev_out(200, [
        'sent' => false,
        'verified' => false,
        'message' => 'Email is not verified yet. If you already clicked the verification link, wait a few seconds and check again.',
    ]);
}

$recent = $db->prepare(
    'SELECT COUNT(*) FROM customer_email_verifications
     WHERE customer_id = :customer_id AND created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)'
);
$recent->execute(['customer_id' => $customerId]);
if ((int) $recent->fetchColumn() > 0) {
    ev_out(429, ['error' => 'Please wait a moment before requesting another verification email.']);
}

$sent = hivenest_create_email_verification($db, $customerId, (string) $customer['email']);
ev_out(200, [
    'sent' => $sent,
    'verified' => false,
    'message' => $sent
        ? 'Verification email was sent.'
        : 'Verification email could not be delivered. Please try again or contact support.',
]);
