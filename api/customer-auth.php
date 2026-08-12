<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../utilities/customer_session.php';
hivenest_customer_session_configure();
session_start();
require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/email_verification.php';
require_once __DIR__ . '/../utilities/rate_limiter.php';
require_once __DIR__ . '/../utilities/mail_delivery.php';
require_once __DIR__ . '/../utilities/two_factor.php';

function auth_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function auth_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function auth_customer_session(array $customer): void
{
    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int)$customer['id'];
    $_SESSION['customer_uuid'] = (string)$customer['uuid'];
    $_SESSION['customer_email'] = (string)$customer['email'];
    $_SESSION['customer_email_verified'] = (int)$customer['email_verified'];
    $_SESSION['customer_auth_version'] = (int)$customer['auth_version'];
    $_SESSION['customer_authenticated_at'] = time();
    $_SESSION['customer_last_activity_at'] = time();
    unset($_SESSION['customer_csrf_token']);
}

$action = strtolower(trim((string) ($_GET['action'] ?? '')));
if ($_SERVER['REQUEST_METHOD'] !== 'POST') auth_out(405, ['error' => 'POST required']);

if ($action === 'logout') {
    hivenest_customer_csrf_require_json();
    hivenest_customer_session_destroy();
    auth_out(200, ['authenticated' => false, 'logged_out' => true]);
}

$db = hivenest_db();
if (!$db) auth_out(503, ['error' => 'Customer database is unavailable.']);
$input = json_decode((string) file_get_contents('php://input'), true) ?: [];
$clientIp = trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

if ($action === 'request-password-reset') {
    $email = strtolower(trim((string)($input['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        auth_out(422, ['error' => 'Enter a valid email address.']);
    }

    $ipLimit = hivenest_rate_limit('customer-password-reset-ip', 5, 3600, $clientIp);
    $accountLimit = hivenest_rate_limit('customer-password-reset-account', 3, 3600, $clientIp . '|' . $email);
    if (!$ipLimit['allowed'] || !$accountLimit['allowed']) {
        $retryAfter = max($ipLimit['retry_after'], $accountLimit['retry_after']);
        header('Retry-After: ' . max(1, $retryAfter));
        auth_out(429, [
            'error' => 'Too many password recovery attempts. Please try again later.',
            'retry_after' => $retryAfter,
        ]);
    }

    $generic = [
        'success' => true,
        'message' => 'If an active account matches that email, a password reset link will be sent.',
    ];
    $find = $db->prepare("SELECT id, email FROM customers WHERE email = :email AND status = 'active' LIMIT 1");
    $find->execute(['email' => $email]);
    $customer = $find->fetch();
    if (!$customer) auth_out(200, $generic);

    try {
        $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $tokenHash = hash('sha256', $rawToken);
        $ipHash = hash_hmac('sha256', $clientIp, hivenest_rate_limit_secret());
        $db->beginTransaction();

        $invalidate = $db->prepare(
            'UPDATE customer_password_resets
             SET consumed_at = UTC_TIMESTAMP()
             WHERE customer_id = :customer_id AND consumed_at IS NULL'
        );
        $invalidate->execute(['customer_id' => (int)$customer['id']]);

        $insert = $db->prepare(
            'INSERT INTO customer_password_resets
                (customer_id, token_hash, expires_at, request_ip_hash)
             VALUES
                (:customer_id, :token_hash, :expires_at, :request_ip_hash)'
        );
        $insert->execute([
            'customer_id' => (int)$customer['id'],
            'token_hash' => $tokenHash,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
            'request_ip_hash' => $ipHash,
        ]);
        $db->commit();

        $baseUrl = hivenest_verification_base_url();
        $host = parse_url($baseUrl, PHP_URL_HOST) ?: 'hivenest.co.za';
        $link = $baseUrl . '/reset-password.php?token=' . rawurlencode($rawToken);
        $subject = 'Reset your HiveNest password';
        $body = "A password reset was requested for your HiveNest account.\n\n"
            . "Set a new password using this secure link:\n\n"
            . "{{reset_link}}\n\n"
            . "The link expires in one hour and can be used once. If you did not request this, ignore this email.\n\n"
            . "HiveNest";
        $headers = [
            'From: HiveNest <no-reply@' . $host . '>',
            'Reply-To: support@hivenest.co.za',
            'X-Mailer: PHP/' . phpversion(),
        ];
        if (!hivenest_mail_send_template(
            (string)$customer['email'],
            'password_reset',
            ['reset_link' => $link, 'expiry_minutes' => 60],
            $subject,
            $body,
            $headers,
            'password-reset:' . (int)$customer['id'] . ':' . $tokenHash
        )) {
            error_log('Password reset mail failed for customer ID: ' . (int)$customer['id']);
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('Password reset request failed: ' . $e->getMessage());
    }
    auth_out(200, $generic);
}

if ($action === 'reset-password') {
    $token = trim((string)($input['token'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $ipLimit = hivenest_rate_limit('customer-password-update-ip', 10, 3600, $clientIp);
    if (!$ipLimit['allowed']) {
        header('Retry-After: ' . max(1, $ipLimit['retry_after']));
        auth_out(429, ['error' => 'Too many reset attempts. Please try again later.']);
    }
    if (!preg_match('/^[A-Za-z0-9_-]{32,}$/', $token)) {
        auth_out(422, ['error' => 'This password reset link is invalid or expired.']);
    }
    if (
        strlen($password) < 12
        || !preg_match('/[A-Z]/', $password)
        || !preg_match('/[a-z]/', $password)
        || !preg_match('/\d/', $password)
        || !preg_match('/[^A-Za-z0-9]/', $password)
    ) {
        auth_out(422, ['error' => 'Password must be at least 12 characters and include uppercase, lowercase, a number, and a symbol.']);
    }

    try {
        $tokenHash = hash('sha256', $token);
        $db->beginTransaction();
        $find = $db->prepare(
            "SELECT r.id, r.customer_id
             FROM customer_password_resets r
             JOIN customers c ON c.id = r.customer_id
             WHERE r.token_hash = :token_hash
               AND r.consumed_at IS NULL
               AND r.expires_at > UTC_TIMESTAMP()
               AND c.status = 'active'
             LIMIT 1
             FOR UPDATE"
        );
        $find->execute(['token_hash' => $tokenHash]);
        $reset = $find->fetch();
        if (!$reset) {
            $db->rollBack();
            auth_out(422, ['error' => 'This password reset link is invalid or expired.']);
        }

        $update = $db->prepare(
            'UPDATE customers
             SET password_hash = :password_hash,
                 auth_version = auth_version + 1,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = :customer_id'
        );
        $update->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'customer_id' => (int)$reset['customer_id'],
        ]);
        $consume = $db->prepare(
            'UPDATE customer_password_resets
             SET consumed_at = UTC_TIMESTAMP()
             WHERE customer_id = :customer_id AND consumed_at IS NULL'
        );
        $consume->execute(['customer_id' => (int)$reset['customer_id']]);
        $db->commit();

        hivenest_customer_session_destroy();
        auth_out(200, [
            'success' => true,
            'message' => 'Your password has been reset. Sign in with your new password.',
        ]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('Password update failed: ' . $e->getMessage());
        auth_out(503, ['error' => 'Password could not be reset. Please try again shortly.']);
    }
}

if ($action === 'register') {
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $password = (string) ($input['password'] ?? '');
    $firstName = trim((string) ($input['first_name'] ?? ''));
    $lastName = trim((string) ($input['last_name'] ?? ''));
    $companyName = trim((string) ($input['company_name'] ?? ''));
    $phone = trim((string) ($input['phone'] ?? ''));
    $countryCode = strtoupper(trim((string) ($input['country_code'] ?? 'ZA')));
    $addressLine1 = trim((string) ($input['address_line1'] ?? ''));
    $addressLine2 = trim((string) ($input['address_line2'] ?? ''));
    $city = trim((string) ($input['city'] ?? ''));
    $state = trim((string) ($input['state'] ?? ''));
    $postalCode = trim((string) ($input['postal_code'] ?? ''));
    $country = trim((string) ($input['country'] ?? 'South Africa'));
    $ipLimit = hivenest_rate_limit('customer-register-ip', 5, 3600, $clientIp);
    $emailLimit = hivenest_rate_limit('customer-register-email', 3, 3600, $clientIp . '|' . $email);
    if (!$ipLimit['allowed'] || !$emailLimit['allowed']) {
        $retryAfter = max($ipLimit['retry_after'], $emailLimit['retry_after']);
        header('Retry-After: ' . $retryAfter);
        auth_out(429, [
            'error' => 'Too many account creation attempts. Please wait before trying again.',
            'retry_after' => $retryAfter,
        ]);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) auth_out(422, ['error' => 'Enter a valid email address.']);
    if (
        strlen($password) < 12
        || !preg_match('/[A-Z]/', $password)
        || !preg_match('/[a-z]/', $password)
        || !preg_match('/\d/', $password)
        || !preg_match('/[^A-Za-z0-9]/', $password)
    ) {
        auth_out(422, ['error' => 'Password must be at least 12 characters and include uppercase, lowercase, a number, and a symbol.']);
    }
    if ($firstName === '' || $lastName === '') auth_out(422, ['error' => 'Enter your first and last name.']);
    if ($phone === '' || $addressLine1 === '' || $city === '' || $state === '' || $postalCode === '' || $country === '') auth_out(422, ['error' => 'Complete all required registrant contact and address fields.']);
    if (!preg_match('/^[A-Z]{2,3}$/', $countryCode)) auth_out(422, ['error' => 'Enter a valid country code.']);

    $exists = $db->prepare('SELECT id FROM customers WHERE email = :email LIMIT 1');
    $exists->execute(['email' => $email]);
    if ($exists->fetchColumn()) auth_out(409, ['error' => 'An account already exists for this email address.']);

    try {
        $customerUuid = auth_uuid();
        $stmt = $db->prepare("INSERT INTO customers (uuid,customer_type,email,password_hash,first_name,last_name,company_name,phone,country_code,address_line1,address_line2,city,state,postal_code,country,status,email_verified,preferred_currency) VALUES (:uuid,'individual',:email,:password_hash,:first_name,:last_name,:company_name,:phone,:country_code,:address_line1,:address_line2,:city,:state,:postal_code,:country,'active',0,'USD')");
        $stmt->execute([
            'uuid' => $customerUuid,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company_name' => $companyName !== '' ? $companyName : null,
            'phone' => $phone,
            'country_code' => $countryCode,
            'address_line1' => $addressLine1,
            'address_line2' => $addressLine2 !== '' ? $addressLine2 : null,
            'city' => $city,
            'state' => $state,
            'postal_code' => $postalCode,
            'country' => $country,
        ]);
        $customerId = (int) $db->lastInsertId();
    } catch (PDOException $e) {
        error_log('Customer registration failed: ' . $e->getMessage());
        auth_out(500, ['error' => 'Account creation failed. Please try again.']);
    }

    session_regenerate_id(true);
    $_SESSION['customer_id'] = $customerId;
    $_SESSION['customer_uuid'] = $customerUuid;
    $_SESSION['customer_email'] = $email;
    $_SESSION['customer_email_verified'] = 0;
    $_SESSION['customer_auth_version'] = 1;
    $_SESSION['customer_authenticated_at'] = time();
    $_SESSION['customer_last_activity_at'] = time();
    unset($_SESSION['customer_csrf_token']);
    $verificationSent = hivenest_create_email_verification($db, $customerId, $email);
    auth_out(201, [
        'authenticated' => true,
        'customer_id' => $customerId,
        'csrf_token' => hivenest_customer_csrf_token(),
        'email_verified' => false,
        'verification_sent' => $verificationSent,
        'message' => $verificationSent
            ? 'Account created. Please verify your email before checkout.'
            : 'Account created. Verification email could not be sent; use resend on checkout.',
    ]);
}

if ($action === 'login') {
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $password = (string) ($input['password'] ?? '');
    $ipLimit = hivenest_rate_limit('customer-login-ip', 20, 600, $clientIp);
    $accountLimit = hivenest_rate_limit('customer-login-account', 8, 600, $clientIp . '|' . $email);
    if (!$ipLimit['allowed'] || !$accountLimit['allowed']) {
        $retryAfter = max($ipLimit['retry_after'], $accountLimit['retry_after']);
        header('Retry-After: ' . $retryAfter);
        auth_out(429, [
            'error' => 'Too many login attempts. Please wait before trying again.',
            'retry_after' => $retryAfter,
        ]);
    }
    $stmt = $db->prepare('SELECT id,uuid,email,password_hash,auth_version,status,email_verified,two_factor_enabled FROM customers WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $customer = $stmt->fetch();
    if (!$customer || !password_verify($password, (string) $customer['password_hash'])) {
        auth_out(401, ['error' => 'Invalid email or password.']);
    }
    if (($customer['status'] ?? '') !== 'active') auth_out(403, ['error' => 'This account is not active.']);

    if ((int)($customer['two_factor_enabled'] ?? 0) === 1) {
        try {
            $challenge = hivenest_2fa_create_challenge($db, 'customer', (int)$customer['id']);
        } catch (Throwable $e) {
            error_log('Customer 2FA challenge creation failed: ' . $e->getMessage());
            auth_out(503, ['error' => 'Two-factor authentication is temporarily unavailable.']);
        }
        auth_out(200, [
            'authenticated' => false,
            'two_factor_required' => true,
            'challenge_token' => $challenge,
            'message' => 'Enter the code from your authenticator app or a recovery code.',
        ]);
    }

    auth_customer_session($customer);
    $update = $db->prepare('UPDATE customers SET last_login = NOW() WHERE id = :id');
    $update->execute(['id' => (int) $customer['id']]);
    auth_out(200, [
        'authenticated' => true,
        'customer_id' => (int) $customer['id'],
        'csrf_token' => hivenest_customer_csrf_token(),
        'email_verified' => (int) $customer['email_verified'] === 1,
    ]);
}

if ($action === 'verify-2fa') {
    $challengeToken = strtolower(trim((string)($input['challenge_token'] ?? '')));
    $code = trim((string)($input['code'] ?? ''));
    $ipLimit = hivenest_rate_limit('customer-2fa-ip', 15, 600, $clientIp);
    if (!$ipLimit['allowed']) {
        header('Retry-After: ' . max(1, (int)$ipLimit['retry_after']));
        auth_out(429, ['error' => 'Too many verification attempts. Please wait and sign in again.']);
    }
    $challenge = hivenest_2fa_find_challenge($db, 'customer', $challengeToken);
    if (!$challenge) auth_out(401, ['error' => 'Verification session expired. Sign in again.']);

    $stmt = $db->prepare(
        'SELECT id,uuid,email,auth_version,status,email_verified,two_factor_enabled,two_factor_secret
         FROM customers WHERE id=:id LIMIT 1'
    );
    $stmt->execute(['id' => (int)$challenge['account_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$customer || $customer['status'] !== 'active' || (int)$customer['two_factor_enabled'] !== 1) {
        auth_out(401, ['error' => 'Verification session is invalid.']);
    }
    try {
        $secret = hivenest_2fa_decrypt((string)$customer['two_factor_secret']);
        $valid = hivenest_2fa_verify_totp($secret, $code)
            || hivenest_2fa_use_recovery_code($db, 'customer', (int)$customer['id'], $code);
    } catch (Throwable $e) {
        error_log('Customer 2FA verification failed: ' . $e->getMessage());
        auth_out(503, ['error' => 'Two-factor authentication is temporarily unavailable.']);
    }
    if (!$valid) {
        $failed = $db->prepare('UPDATE two_factor_challenges SET attempts=attempts+1 WHERE id=:id');
        $failed->execute(['id' => (int)$challenge['id']]);
        auth_out(401, ['error' => 'Authenticator or recovery code is invalid.']);
    }
    $consume = $db->prepare('UPDATE two_factor_challenges SET consumed_at=NOW() WHERE id=:id AND consumed_at IS NULL');
    $consume->execute(['id' => (int)$challenge['id']]);
    if ($consume->rowCount() !== 1) auth_out(409, ['error' => 'Verification code has already been used.']);

    auth_customer_session($customer);
    $update = $db->prepare('UPDATE customers SET last_login=NOW() WHERE id=:id');
    $update->execute(['id' => (int)$customer['id']]);
    auth_out(200, [
        'authenticated' => true,
        'customer_id' => (int)$customer['id'],
        'csrf_token' => hivenest_customer_csrf_token(),
        'email_verified' => (int)$customer['email_verified'] === 1,
    ]);
}

auth_out(400, ['error' => 'Unknown authentication action.']);
