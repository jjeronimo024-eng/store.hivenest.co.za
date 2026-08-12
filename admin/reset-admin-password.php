<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

session_name('HIVENEST_ADMIN_RESET');
session_start();

require_once __DIR__ . '/../access/dbconfig.php';

function hivenest_admin_reset_env(string $key): string
{
    if (!is_readable(HIVENEST_ENV_PATH)) return '';
    $lines = @file(HIVENEST_ENV_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$name, $value] = explode('=', $line, 2);
        if (trim($name) !== $key) continue;
        $value = trim($value);
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        return $value;
    }
    return '';
}

function hivenest_admin_reset_not_found(): never
{
    http_response_code(404);
    exit('Not found');
}

function hivenest_admin_reset_csrf(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function hivenest_admin_reset_token_consumed(PDO $pdo, string $tokenHash): bool
{
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'admin_reset_last_consumed_hash' LIMIT 1");
        $stmt->execute();
        $stored = (string)($stmt->fetchColumn() ?: '');
        return $stored !== '' && hash_equals($stored, $tokenHash);
    } catch (Throwable $e) {
        error_log('Admin reset consumption check failed: ' . $e->getMessage());
        return true;
    }
}

$enabled = filter_var(hivenest_admin_reset_env('ADMIN_RESET_ENABLED'), FILTER_VALIDATE_BOOLEAN);
$configuredToken = hivenest_admin_reset_env('ADMIN_RESET_TOKEN');
$expiresAt = hivenest_admin_reset_env('ADMIN_RESET_EXPIRES_AT');
$expiryTimestamp = $expiresAt !== '' ? strtotime($expiresAt) : false;

if (
    !$enabled
    || strlen($configuredToken) < 32
    || ($expiresAt !== '' && ($expiryTimestamp === false || $expiryTimestamp < time()))
) {
    hivenest_admin_reset_not_found();
}

$message = '';
$success = false;
$authorizedAt = (int)($_SESSION['reset_authorized_at'] ?? 0);
$authorized = $authorizedAt > 0 && (time() - $authorizedAt) <= 900;
$pdo = hivenest_db();
if (!$pdo) {
    http_response_code(503);
    exit('Recovery service unavailable.');
}

$tokenHash = hash('sha256', $configuredToken);
if (hivenest_admin_reset_token_consumed($pdo, $tokenHash)) {
    unset($_SESSION['reset_authorized_at']);
    hivenest_admin_reset_not_found();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals(hivenest_admin_reset_csrf(), $csrf)) {
        http_response_code(403);
        $message = 'The recovery form expired. Refresh and try again.';
    } elseif (($_POST['action'] ?? '') === 'authorize') {
        $submittedToken = (string)($_POST['recovery_token'] ?? '');
        if (!hash_equals($configuredToken, $submittedToken)) {
            usleep(350000);
            $message = 'Invalid recovery token.';
        } else {
            session_regenerate_id(true);
            $_SESSION['reset_authorized_at'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: reset-admin-password.php', true, 303);
            exit;
        }
    } elseif (($_POST['action'] ?? '') === 'reset' && $authorized) {
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $targetEmail = strtolower(trim((string)($_POST['admin_email'] ?? '')));
        $allowedEmails = [
            'admin@hivenest.co.za',
            'support@hivenest.co.za',
            'manager@hivenest.co.za',
        ];

        if (!in_array($targetEmail, $allowedEmails, true)) {
            $message = 'Select a permitted administrator account.';
        } elseif (
            strlen($password) < 14
            || !preg_match('/[A-Z]/', $password)
            || !preg_match('/[a-z]/', $password)
            || !preg_match('/\d/', $password)
            || !preg_match('/[^A-Za-z0-9]/', $password)
        ) {
            $message = 'Use at least 14 characters with uppercase, lowercase, a number, and a symbol.';
        } elseif (!hash_equals($password, $confirm)) {
            $message = 'The password confirmation does not match.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                if (!is_string($hash) || !password_verify($password, $hash)) {
                    throw new RuntimeException('The hosting server could not generate a valid bcrypt hash.');
                }

                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    UPDATE admin_users
                    SET password_hash = :password_hash,
                        is_active = 1,
                        updated_at = NOW()
                    WHERE email = :email
                    LIMIT 1
                ");
                $stmt->execute([
                    'password_hash' => $hash,
                    'email' => $targetEmail,
                ]);
                $success = $stmt->rowCount() > 0;
                if (!$success) {
                    throw new RuntimeException('The selected administrator account was not found or updated.');
                }

                $consumeStmt = $pdo->prepare("
                    INSERT INTO system_settings
                        (setting_key, setting_value, setting_type, description, is_editable)
                    VALUES
                        ('admin_reset_last_consumed_hash', :token_hash, 'string', 'Hash of the last consumed one-time admin recovery token.', 0)
                    ON DUPLICATE KEY UPDATE
                        setting_value = VALUES(setting_value),
                        is_editable = 0,
                        updated_at = NOW()
                ");
                $consumeStmt->execute(['token_hash' => $tokenHash]);
                $pdo->commit();

                unset($_SESSION['reset_authorized_at']);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $attemptFile = __DIR__ . '/../Backend/logs/admin_login_attempts.json';
                if (is_file($attemptFile)) @unlink($attemptFile);
                $message = 'Password reset completed for ' . $targetEmail . '. This recovery token is now consumed.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Admin password reset failed: ' . $e->getMessage());
                $message = 'The reset could not be completed. Check the PHP error log.';
            }
        }

        $password = $confirm = '';
    } elseif (($_POST['action'] ?? '') === 'reset') {
        unset($_SESSION['reset_authorized_at']);
        $authorized = false;
        $message = 'Recovery authorization expired. Enter the one-time token again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HiveNest Admin Password Reset</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 20px; background: #060a12; color: #e8ffff; font: 16px Arial, sans-serif; }
        main { width: min(460px, 100%); padding: 30px; background: #101624; border: 1px solid #00dfe6; border-radius: 14px; }
        h1 { color: #00ffff; font-size: 24px; }
        label { display: block; margin: 18px 0 7px; }
        input, select { width: 100%; padding: 13px; color: white; background: #070b12; border: 1px solid #34717a; border-radius: 7px; }
        button { width: 100%; margin-top: 22px; padding: 14px; border: 0; border-radius: 7px; background: #00d5c3; color: #041014; font-weight: bold; cursor: pointer; }
        .message { margin: 15px 0; padding: 12px; border-radius: 7px; background: <?php echo $success ? '#123c2d' : '#43142a'; ?>; }
        .warning { color: #ffd166; font-size: 13px; margin-top: 18px; }
    </style>
</head>
<body>
<main>
    <h1>Admin Password Reset</h1>
    <p>One-time administrator account recovery.</p>
    <?php if ($message !== ''): ?>
        <div class="message"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if (!$success && !$authorized): ?>
        <form method="post" autocomplete="off">
            <input type="hidden" name="action" value="authorize">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(hivenest_admin_reset_csrf(), ENT_QUOTES, 'UTF-8'); ?>">
            <label for="recovery_token">One-time recovery token</label>
            <input id="recovery_token" name="recovery_token" type="password" minlength="32" required autocomplete="off">
            <button type="submit">Authorize Recovery</button>
        </form>
    <?php elseif (!$success): ?>
        <form method="post" autocomplete="off">
            <input type="hidden" name="action" value="reset">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(hivenest_admin_reset_csrf(), ENT_QUOTES, 'UTF-8'); ?>">
            <label for="admin_email">Administrator account</label>
            <select id="admin_email" name="admin_email" required>
                <option value="admin@hivenest.co.za">admin@hivenest.co.za</option>
                <option value="support@hivenest.co.za">support@hivenest.co.za</option>
                <option value="manager@hivenest.co.za">manager@hivenest.co.za</option>
            </select>
            <label for="password">New temporary password</label>
            <input id="password" name="password" type="password" minlength="14" required autocomplete="new-password">
            <label for="confirm_password">Confirm password</label>
            <input id="confirm_password" name="confirm_password" type="password" minlength="14" required autocomplete="new-password">
            <button type="submit">Reset Selected Account</button>
        </form>
    <?php endif; ?>
    <div class="warning">After recovery, set ADMIN_RESET_ENABLED=false and remove the token from the server environment.</div>
</main>
</body>
</html>
