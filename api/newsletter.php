<?php
declare(strict_types=1);

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/rate_limiter.php';
require_once __DIR__ . '/../utilities/email_verification.php';
require_once __DIR__ . '/../utilities/mail_delivery.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');

function newsletter_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function newsletter_generic_success(): never
{
    newsletter_response(200, [
        'success' => true,
        'message' => 'Check your inbox to confirm your newsletter subscription.',
    ]);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    newsletter_response(405, ['success' => false, 'message' => 'POST required.']);
}

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$input = [];
if (str_contains($contentType, 'application/json')) {
    $decoded = json_decode((string)file_get_contents('php://input'), true);
    if (is_array($decoded)) $input = $decoded;
} else {
    $input = $_POST;
}

// Silently accept honeypot submissions so bots receive no useful feedback.
if (trim((string)($input['website_url'] ?? '')) !== '') {
    newsletter_generic_success();
}

$email = strtolower(trim((string)($input['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    newsletter_response(422, ['success' => false, 'message' => 'Enter a valid email address.']);
}

$ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
$ipLimit = hivenest_rate_limit('newsletter-ip-hour', 5, 3600, $ip);
$addressLimit = hivenest_rate_limit('newsletter-address-day', 3, 86400, $ip . '|' . $email);
if (!$ipLimit['allowed'] || !$addressLimit['allowed']) {
    $retryAfter = max((int)$ipLimit['retry_after'], (int)$addressLimit['retry_after']);
    header('Retry-After: ' . max(1, $retryAfter));
    newsletter_response(429, [
        'success' => false,
        'message' => 'Too many subscription attempts. Please try again later.',
    ]);
}

$db = hivenest_db();
if (!$db) {
    newsletter_response(503, [
        'success' => false,
        'message' => 'Newsletter service is temporarily unavailable.',
    ]);
}

try {
    $existing = $db->prepare('SELECT status FROM newsletter_subscribers WHERE email = :email LIMIT 1');
    $existing->execute(['email' => $email]);
    $status = (string)($existing->fetchColumn() ?: '');

    // Keep the response identical so this public endpoint cannot enumerate subscribers.
    if ($status === 'active') {
        newsletter_generic_success();
    }

    $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = gmdate('Y-m-d H:i:s', time() + 86400);
    $ipHash = hash_hmac('sha256', $ip, hivenest_rate_limit_secret());

    $upsert = $db->prepare(
        "INSERT INTO newsletter_subscribers
            (email, status, confirmation_token_hash, confirmation_expires_at, request_ip_hash)
         VALUES
            (:email, 'pending', :token_hash, :expires_at, :ip_hash)
         ON DUPLICATE KEY UPDATE
            status = 'pending',
            confirmation_token_hash = VALUES(confirmation_token_hash),
            confirmation_expires_at = VALUES(confirmation_expires_at),
            request_ip_hash = VALUES(request_ip_hash),
            unsubscribed_at = NULL"
    );
    $upsert->execute([
        'email' => $email,
        'token_hash' => $tokenHash,
        'expires_at' => $expiresAt,
        'ip_hash' => $ipHash,
    ]);

    $link = hivenest_verification_base_url()
        . '/newsletter-confirm.php?token=' . rawurlencode($rawToken);
    $host = parse_url(hivenest_verification_base_url(), PHP_URL_HOST) ?: 'hivenest.co.za';
    $subject = 'Confirm your HiveNest newsletter subscription';
    $body = "Confirm your HiveNest newsletter subscription:\n\n"
        . $link . "\n\n"
        . "This confirmation link expires in 24 hours. If you did not request this, ignore this email.\n\n"
        . "HiveNest";
    $headers = [
        'From: HiveNest <no-reply@' . $host . '>',
        'Reply-To: support@hivenest.co.za',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion(),
    ];

    if (!hivenest_mail_send($email, $subject, $body, implode("\r\n", $headers))) {
        error_log('Newsletter confirmation mail failed for email hash: ' . substr(hash('sha256', $email), 0, 12));
        newsletter_response(503, [
            'success' => false,
            'message' => 'Confirmation email could not be sent. Please try again shortly.',
        ]);
    }

    newsletter_generic_success();
} catch (Throwable $e) {
    error_log('Newsletter subscription failed: ' . $e->getMessage());
    newsletter_response(503, [
        'success' => false,
        'message' => 'Newsletter service is temporarily unavailable.',
    ]);
}
