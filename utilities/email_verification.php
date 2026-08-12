<?php
declare(strict_types=1);
require_once __DIR__ . '/email_templates.php';

/**
 * HiveNest customer email verification helpers.
 *
 * Tokens are sent to the customer but only a SHA-256 hash is stored in the DB.
 * This keeps the raw verification secret out of the database and logs.
 */

function hivenest_verification_env(string $key, string $default = ''): string
{
    $process = getenv($key);
    if ($process !== false && $process !== '') return (string) $process;

    $envPath = defined('HIVENEST_ENV_PATH') ? HIVENEST_ENV_PATH : __DIR__ . '/../Backend/.env';
    if (!is_readable($envPath)) return $default;

    $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$name, $value] = explode('=', $line, 2);
        if (trim($name) !== $key) continue;
        return trim(trim($value), "\"'");
    }

    return $default;
}

function hivenest_verification_base_url(): string
{
    $configured = hivenest_verification_env('APP_URL', '') ?: hivenest_verification_env('HIVENEST_SITE_URL', '');
    if ($configured !== '') {
        return rtrim((string) $configured, '/');
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'hivenest.co.za');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    return ($https ? 'https://' : 'http://') . $host;
}

function hivenest_create_email_verification(PDO $db, int $customerId, string $email, int $ttlHours = 24): bool
{
    try {
        $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $tokenHash = hash('sha256', $rawToken);

        $invalidate = $db->prepare(
            "UPDATE customer_email_verifications
             SET consumed_at = COALESCE(consumed_at, NOW())
             WHERE customer_id = :customer_id AND consumed_at IS NULL"
        );
        $invalidate->execute(['customer_id' => $customerId]);

        $insert = $db->prepare(
            "INSERT INTO customer_email_verifications
                (customer_id, email, token_hash, expires_at, request_ip, user_agent)
             VALUES
                (:customer_id, :email, :token_hash, :expires_at, :request_ip, :user_agent)"
        );
        $insert->execute([
            'customer_id' => $customerId,
            'email' => strtolower(trim($email)),
            'token_hash' => $tokenHash,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + ($ttlHours * 3600)),
            'request_ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
        ]);

        $link = hivenest_verification_base_url() . '/verify-email.php?token=' . rawurlencode($rawToken);
        return hivenest_send_verification_email($email, $link, $ttlHours);
    } catch (Throwable $e) {
        error_log('Email verification token creation failed: ' . $e->getMessage());
        return false;
    }
}

function hivenest_send_verification_email(string $email, string $link, int $ttlHours): bool
{
    $safeHost = parse_url(hivenest_verification_base_url(), PHP_URL_HOST) ?: 'hivenest.co.za';
    $subject = 'Verify your HiveNest account';
    $body = "Welcome to HiveNest.\n\n"
        . "Before checkout can continue, please verify your email address:\n\n"
        . "{{verification_link}}\n\n"
        . "This link expires in {{expiry_hours}} hours. If you did not create this account, you can ignore this email.\n\n"
        . "HiveNest";

    $headers = [
        'From: HiveNest <no-reply@' . $safeHost . '>',
        'Reply-To: support@hivenest.co.za',
        'X-Mailer: PHP/' . phpversion(),
    ];

    $sent = hivenest_mail_send_template(
        $email,
        'account_verification',
        ['verification_link' => $link, 'expiry_hours' => $ttlHours],
        $subject,
        $body,
        $headers,
        'account-verification:' . hash('sha256', strtolower(trim($email)) . '|' . $link)
    );
    if (!$sent) {
        error_log('Verification email could not be sent to customer ID/email hash: ' . substr(hash('sha256', strtolower($email)), 0, 12));
    } else {
        error_log('Verification email handed to mail transport for email hash: ' . substr(hash('sha256', strtolower($email)), 0, 12));
    }
    return $sent;
}

function hivenest_customer_email_verified(PDO $db, int $customerId): bool
{
    $stmt = $db->prepare('SELECT email_verified FROM customers WHERE id = :id AND status = "active" LIMIT 1');
    $stmt->execute(['id' => $customerId]);
    return (int) $stmt->fetchColumn() === 1;
}
