<?php
/**
 * HiveNest Admin Authentication Helper
 * -----------------------------------------------------------------
 * Hardened session-based admin auth for all /admin/*.php pages.
 *
 * Features:
 *   - bcrypt password_verify() against admin_users.password_hash
 *   - Username + password login (not just a shared password)
 *   - session_regenerate_id(true) on successful login
 *   - HttpOnly + SameSite=Strict + Secure (when HTTPS) cookies
 *   - Idle session timeout (30 min) + absolute timeout (8 hours)
 *   - CSRF token generate / verify helpers
 *   - Brute-force lockout (5 failed attempts per IP+username = 15 min lock)
 *     persisted in a small JSON file under Backend/logs/ so it survives
 *     PHP-FPM restarts and works on shared hosting w/o extra tables
 *   - Renders a self-contained cyberpunk login page if not authenticated
 *   - Full logout that destroys session + cookie
 *
 * Usage in admin pages (drop-in replacement for the old password gate):
 *
 *   require_once __DIR__ . '/../utilities/admin_auth.php';
 *   requireAdminAuth();                  // halts + renders login page if not logged in
 *   $admin = currentAdmin();             // ['id'=>..,'username'=>..,'role'=>..]
 *
 *   // For POST handlers:
 *   verifyCsrfOrDie($_POST['csrf_token'] ?? '');
 *   $csrf = csrfToken();                 // embed in <input type="hidden" ...>
 */

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/product_pricing.php'; // legacy helper, now thin wrapper over dbconfig
require_once __DIR__ . '/two_factor.php';

// -----------------------------------------------------------------
// Session bootstrap with secure cookie params
// -----------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $is_https = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
        (($_SERVER['SERVER_PORT'] ?? '') == 443)
    );
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_name('HIVENEST_ADMIN');
    session_start();
}

// -----------------------------------------------------------------
// Configuration
// -----------------------------------------------------------------
const ADMIN_IDLE_TIMEOUT     = 1800;   // 30 min of inactivity
const ADMIN_ABSOLUTE_TIMEOUT = 28800;  // 8 hours hard cap
const ADMIN_MAX_FAILED       = 5;      // lock after N failed logins
const ADMIN_LOCK_WINDOW      = 900;    // 15 min lock window (seconds)

function adminLockFile(): string {
    $dir = __DIR__ . '/../Backend/logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir . '/admin_login_attempts.json';
}

function clientIp(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

// -----------------------------------------------------------------
// Brute-force tracking (file-based, atomic)
// -----------------------------------------------------------------
function loadAttempts(): array {
    $f = adminLockFile();
    if (!file_exists($f)) return [];
    $raw = @file_get_contents($f);
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function saveAttempts(array $data): void {
    $f = adminLockFile();
    $fp = @fopen($f, 'c+');
    if (!$fp) return;
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

function attemptKey(string $username): string {
    return strtolower($username) . '|' . clientIp();
}

function isLockedOut(string $username): int {
    $data = loadAttempts();
    $key  = attemptKey($username);
    if (!isset($data[$key])) return 0;
    $rec = $data[$key];
    if (($rec['count'] ?? 0) >= ADMIN_MAX_FAILED) {
        $remaining = (int)($rec['until'] ?? 0) - time();
        if ($remaining > 0) return $remaining;
    }
    return 0;
}

function recordFailedAttempt(string $username): void {
    $data = loadAttempts();
    $key  = attemptKey($username);
    $now  = time();
    $rec  = $data[$key] ?? ['count' => 0, 'until' => 0];

    // Reset counter if last attempt was outside the lock window
    if ($now > ($rec['until'] ?? 0) && ($rec['count'] ?? 0) >= ADMIN_MAX_FAILED) {
        $rec = ['count' => 0, 'until' => 0];
    }
    $rec['count'] = ($rec['count'] ?? 0) + 1;
    if ($rec['count'] >= ADMIN_MAX_FAILED) {
        $rec['until'] = $now + ADMIN_LOCK_WINDOW;
    }
    $data[$key] = $rec;
    saveAttempts($data);
}

function clearFailedAttempts(string $username): void {
    $data = loadAttempts();
    unset($data[attemptKey($username)]);
    saveAttempts($data);
}

// -----------------------------------------------------------------
// CSRF helpers
// -----------------------------------------------------------------
function csrfToken(): string {
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf'];
}

function verifyCsrf(?string $token): bool {
    return is_string($token) && !empty($_SESSION['admin_csrf'])
        && hash_equals($_SESSION['admin_csrf'], $token);
}

function verifyCsrfOrDie(?string $token): void {
    if (!verifyCsrf($token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'CSRF token invalid. Please reload the page.']);
        exit;
    }
}

// -----------------------------------------------------------------
// DB-backed user verification (bcrypt via admin_users)
// -----------------------------------------------------------------
function verifyAdminCredentials(string $username, string $password, ?string &$failure_reason = null): ?array {
    $failure_reason = null;
    $conn = getPricingDBConnection();
    if (!$conn) {
        $failure_reason = 'database_connection';
        return null;
    }
    try {
        $stmt = $conn->prepare("
            SELECT id, username, email, password_hash, is_active,
                   two_factor_enabled, two_factor_secret, auth_version
            FROM admin_users
            WHERE (username = :username OR email = :email) AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([
            'username' => $username,
            'email' => $username,
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) return null;

        // Some shared-hosting PHP builds only recognise PHP's native $2y$
        // bcrypt marker. The underlying $2b$ hash format is compatible, so
        // normalise the marker before verification without changing the DB.
        $stored_hash = (string) $user['password_hash'];
        if (strpos($stored_hash, '$2b$') === 0) {
            $stored_hash = '$2y$' . substr($stored_hash, 4);
        }
        if (!password_verify($password, $stored_hash)) return null;

        // Authentication only depends on the core columns above. Keep the
        // session shape stable even on older databases without profile fields.
        $user['first_name'] = '';
        $user['last_name'] = '';
        $user['role'] = $user['username'] === 'admin' ? 'super_admin' : 'admin';

        // Update last_login (best-effort, ignore failures)
        try {
            $upd = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = :id");
            $upd->execute(['id' => $user['id']]);
        } catch (Throwable $e) { /* non-fatal */ }

        unset($user['password_hash']);
        return $user;
    } catch (Throwable $e) {
        error_log('verifyAdminCredentials: ' . $e->getMessage());
        $driver_code = 0;
        if ($e instanceof PDOException && is_array($e->errorInfo ?? null)) {
            $driver_code = (int)($e->errorInfo[1] ?? 0);
        }
        $GLOBALS['hivenest_admin_query_error'] = [
            'driver_code' => $driver_code,
            'message' => $e->getMessage(),
        ];
        $failure_reason = 'database_query';
        return null;
    }
}

// -----------------------------------------------------------------
// Session state helpers
// -----------------------------------------------------------------
function isAdminAuthenticated(): bool {
    if (empty($_SESSION['admin_user']) || empty($_SESSION['admin_login_time'])) {
        return false;
    }
    $now = time();
    $idle = $now - ($_SESSION['admin_last_seen'] ?? 0);
    $age  = $now - ($_SESSION['admin_login_time'] ?? 0);
    if ($idle > ADMIN_IDLE_TIMEOUT || $age > ADMIN_ABSOLUTE_TIMEOUT) {
        adminLogout(); // expire stale session
        return false;
    }
    $_SESSION['admin_last_seen'] = $now;
    return true;
}

function currentAdmin(): ?array {
    return $_SESSION['admin_user'] ?? null;
}

function adminLogin(array $user): void {
    // Prevent session fixation
    session_regenerate_id(true);
    $_SESSION['admin_user']       = $user;
    $_SESSION['admin_login_time'] = time();
    $_SESSION['admin_last_seen']  = time();
    // Fresh CSRF token tied to the new session
    $_SESSION['admin_csrf']       = bin2hex(random_bytes(32));
}

function adminLogout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// -----------------------------------------------------------------
// Login handler + login page renderer
// -----------------------------------------------------------------
function handleAdminLogin(): ?string {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['admin_login'])) {
        return null;
    }
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $twoFactorCode = trim((string)($_POST['two_factor_code'] ?? ''));

    if ($username === '' || $password === '') {
        return 'Username and password are required.';
    }

    // CSRF on login form too
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        return 'Session expired. Please reload and try again.';
    }

    $lock = isLockedOut($username);
    if ($lock > 0) {
        $mins = (int)ceil($lock / 60);
        return "Too many failed attempts. Account temporarily locked. Try again in {$mins} minute(s).";
    }

    $failure_reason = null;
    $user = verifyAdminCredentials($username, $password, $failure_reason);
    if (!$user) {
        if ($failure_reason === 'database_connection' || $failure_reason === 'database_query') {
            $db_error = $GLOBALS['hivenest_admin_query_error']
                ?? (function_exists('hivenest_db_last_error') ? hivenest_db_last_error() : null);
            $code = (int)($db_error['driver_code'] ?? 0);
            $message = strtolower((string)($db_error['message'] ?? ''));

            if ($code === 1045) {
                return 'Database access denied (MySQL 1045). Check DB_USER, DB_PASSWORD, and the user host permission.';
            }
            if ($code === 1049) {
                return 'Database not found (MySQL 1049). Check DB_NAME and any hosting-account prefix.';
            }
            if ($code === 1044 || $code === 1142) {
                return 'Database permission denied (MySQL ' . $code . '). Grant the configured user access to hivenest_main.';
            }
            if ($code === 1146) {
                return 'Required table admin_users is missing (MySQL 1146). Import hivenest_main.sql into the connected database.';
            }
            if ($code === 1054) {
                return 'The admin_users table schema is outdated (MySQL 1054). Re-import or update hivenest_main.sql.';
            }
            if ($code === 2002) {
                return 'Database server/socket unavailable (MySQL 2002). Check DB_HOST and MySQL service status.';
            }
            if (strpos($message, 'could not find driver') !== false) {
                return 'PHP PDO MySQL driver is not enabled on this hosting account.';
            }

            return 'Admin database query failed. Check the PHP error log for details.';
        }
        recordFailedAttempt($username);
        // Slight delay to slow down brute-force
        usleep(300000);
        return 'Invalid username or password.';
    }

    if ((int)($user['two_factor_enabled'] ?? 0) === 1) {
        $conn = getPricingDBConnection();
        if (!$conn) return 'Two-factor authentication is temporarily unavailable.';
        $pending = $_SESSION['admin_2fa_challenge'] ?? null;
        $pendingMatches = is_array($pending)
            && (int)($pending['admin_id'] ?? 0) === (int)$user['id']
            && is_string($pending['token'] ?? null);

        if (!$pendingMatches || $twoFactorCode === '') {
            try {
                $challengeToken = hivenest_2fa_create_challenge($conn, 'admin', (int)$user['id']);
                $_SESSION['admin_2fa_challenge'] = [
                    'admin_id' => (int)$user['id'],
                    'token' => $challengeToken,
                ];
                return 'Enter the code from your authenticator app or a recovery code.';
            } catch (Throwable $e) {
                error_log('PHP admin 2FA challenge failed: ' . $e->getMessage());
                return 'Two-factor authentication is temporarily unavailable.';
            }
        }

        $challenge = hivenest_2fa_find_challenge($conn, 'admin', (string)$pending['token']);
        if (!$challenge || (int)$challenge['account_id'] !== (int)$user['id']) {
            unset($_SESSION['admin_2fa_challenge']);
            return 'Verification session expired. Sign in again.';
        }
        try {
            $secret = hivenest_2fa_decrypt((string)($user['two_factor_secret'] ?? ''));
            $valid = hivenest_2fa_verify_totp($secret, $twoFactorCode)
                || hivenest_2fa_use_recovery_code($conn, 'admin', (int)$user['id'], $twoFactorCode);
        } catch (Throwable $e) {
            error_log('PHP admin 2FA verification failed: ' . $e->getMessage());
            return 'Two-factor authentication is temporarily unavailable.';
        }
        if (!$valid) {
            $failed = $conn->prepare('UPDATE two_factor_challenges SET attempts=attempts+1 WHERE id=:id');
            $failed->execute(['id' => (int)$challenge['id']]);
            return 'Authenticator or recovery code is invalid.';
        }
        $consume = $conn->prepare('UPDATE two_factor_challenges SET consumed_at=NOW() WHERE id=:id AND consumed_at IS NULL');
        $consume->execute(['id' => (int)$challenge['id']]);
        if ($consume->rowCount() !== 1) return 'Verification code has already been used.';
        unset($_SESSION['admin_2fa_challenge']);
    }

    unset($user['two_factor_secret'], $user['two_factor_enabled']);
    clearFailedAttempts($username);
    adminLogin($user);
    // Re-issue current URL so refresh doesn't repost
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

function requireAdminAuth(): void {
    // Handle logout from any admin page via ?logout
    if (isset($_GET['logout'])) {
        adminLogout();
        $uri = strtok($_SERVER['REQUEST_URI'], '?');
        header('Location: ' . $uri);
        exit;
    }

    $error = handleAdminLogin();

    if (!isAdminAuthenticated()) {
        renderAdminLoginPage($error);
        exit;
    }
}

function renderAdminLoginPage(?string $error = null): void {
    $token = csrfToken();
    $err   = $error ? htmlspecialchars($error, ENT_QUOTES, 'UTF-8') : '';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HiveNest Admin — Neural Access</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
        font-family: 'Rajdhani', 'Segoe UI', sans-serif;
        background: radial-gradient(circle at 20% 20%, #131a2a 0%, #0a0a0a 60%, #000 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #e6f7ff;
        padding: 20px;
    }
    .login-box {
        background: rgba(15, 18, 28, 0.85);
        padding: 48px;
        border-radius: 14px;
        box-shadow: 0 0 60px rgba(0, 255, 255, 0.25);
        border: 1px solid rgba(0, 255, 255, 0.35);
        width: 100%;
        max-width: 460px;
        backdrop-filter: blur(12px);
    }
    h2 {
        font-size: 30px;
        font-weight: 700;
        letter-spacing: 2px;
        color: #00ffff;
        text-align: center;
        margin-bottom: 8px;
        text-shadow: 0 0 18px rgba(0, 255, 255, 0.6);
    }
    .subtitle {
        text-align:center; color:#9ad4e0; margin-bottom:32px; font-size:14px; letter-spacing:1px;
    }
    label {
        display:block; text-transform:uppercase; font-size:12px;
        letter-spacing:1.5px; color:#00ffff; margin-bottom:8px;
    }
    input[type="text"], input[type="password"] {
        width: 100%;
        padding: 14px 16px;
        border: 1px solid rgba(0, 255, 255, 0.35);
        border-radius: 8px;
        font-size: 15px;
        margin-bottom: 20px;
        background: rgba(0, 0, 0, 0.55);
        color: #e6f7ff;
        font-family: 'Rajdhani', sans-serif;
        transition: all .25s ease;
    }
    input:focus {
        outline: none;
        border-color: #00ffff;
        box-shadow: 0 0 14px rgba(0, 255, 255, 0.4);
    }
    button {
        width: 100%;
        padding: 15px;
        background: linear-gradient(135deg, #00ffff 0%, #00b894 100%);
        color: #0a0a0a;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        cursor: pointer;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        transition: all .25s ease;
    }
    button:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(0, 255, 255, 0.45); }
    .error {
        background: rgba(255, 0, 100, 0.12);
        border: 1px solid #ff0064;
        color: #ff6090;
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 22px;
        font-size: 14px;
    }
    .footer-note {
        margin-top: 22px; text-align:center; color:#5a7a86; font-size:12px; letter-spacing:1px;
    }
</style>
</head>
<body>
<div class="login-box">
    <h2><i class="fas fa-bolt"></i> NEURAL ACCESS</h2>
    <div class="subtitle">HiveNest Admin Matrix</div>
    <?php if ($err): ?>
        <div class="error" data-testid="admin-login-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $err; ?></div>
    <?php endif; ?>
    <form method="POST" autocomplete="off" data-testid="admin-login-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="admin_login" value="1">
        <label for="username">Operator</label>
        <input type="text" id="username" name="username" placeholder="username or email" required autofocus data-testid="admin-username-input">
        <label for="password">Neural Key</label>
        <input type="password" id="password" name="password" placeholder="••••••••••" required data-testid="admin-password-input">
        <?php if (!empty($_SESSION['admin_2fa_challenge'])): ?>
            <label for="two_factor_code">Authenticator or Recovery Code</label>
            <input type="text" id="two_factor_code" name="two_factor_code" autocomplete="one-time-code" required autofocus data-testid="admin-two-factor-input">
        <?php endif; ?>
        <button type="submit" data-testid="admin-login-button">Connect to Matrix</button>
    </form>
    <div class="footer-note">Sessions auto-expire after 30 minutes of inactivity.</div>
</div>
</body>
</html>
    <?php
}
