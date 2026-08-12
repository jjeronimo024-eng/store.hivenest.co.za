<?php
declare(strict_types=1);

/**
 * Configure the customer session cookie for the storefront and API subdomain.
 * Call this before session_start().
 */
function hivenest_customer_session_env(): array
{
    static $values = null;
    if (is_array($values)) return $values;

    $values = [];
    $path = defined('HIVENEST_ENV_PATH')
        ? HIVENEST_ENV_PATH
        : dirname(__DIR__) . '/Backend/.env';
    if (!is_readable($path)) return $values;

    foreach (@file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim((string)$line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $value = trim($value);
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        $values[trim($key)] = $value;
    }
    return $values;
}

function hivenest_customer_session_configure(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $env = hivenest_customer_session_env();
    $secureValue = strtolower(trim((string)($env['COOKIE_SECURE'] ?? 'true')));
    $domain = trim((string)($env['COOKIE_DOMAIN'] ?? ''));
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?: '';
    $domainHost = ltrim(strtolower($domain), '.');
    if ($domainHost !== '' && $host !== $domainHost && !str_ends_with($host, '.' . $domainHost)) {
        // A production cookie domain is invalid on a staging hostname.
        $domain = '';
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $domain,
        'secure' => in_array($secureValue, ['1', 'true', 'yes', 'on'], true),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function hivenest_customer_session_destroy(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) return;

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool)($params['secure'] ?? false),
            'httponly' => (bool)($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

function hivenest_customer_session_idle_seconds(): int
{
    $env = hivenest_customer_session_env();
    $minutes = (int)($env['CUSTOMER_SESSION_IDLE_MINUTES'] ?? 120);
    $minutes = max(15, min($minutes, 1440));
    return $minutes * 60;
}

/**
 * Return the CSRF token bound to the current customer session.
 *
 * The token is deliberately kept in the server-side session and returned only
 * by authenticated JSON responses. JavaScript must send it back in the
 * X-CSRF-Token header for state-changing customer requests.
 */
function hivenest_customer_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        hivenest_customer_session_configure();
        session_start();
    }

    $token = (string)($_SESSION['customer_csrf_token'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['customer_csrf_token'] = $token;
    }
    return $token;
}

function hivenest_customer_csrf_is_valid(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        hivenest_customer_session_configure();
        session_start();
    }

    $expected = (string)($_SESSION['customer_csrf_token'] ?? '');
    $provided = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    return $expected !== ''
        && $provided !== ''
        && hash_equals($expected, $provided);
}

function hivenest_customer_csrf_require_json(): void
{
    if (hivenest_customer_csrf_is_valid()) return;

    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'error' => 'Security token is missing or expired. Refresh the page and try again.',
        'code' => 'CSRF_TOKEN_INVALID',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Validate and touch an authenticated customer session.
 *
 * @return array{authenticated:bool,expired:bool,customer_id:int,expires_in:int}
 */
function hivenest_customer_session_status(bool $touch = true): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        hivenest_customer_session_configure();
        session_start();
    }

    $customerId = (int)($_SESSION['customer_id'] ?? 0);
    if ($customerId <= 0) {
        return ['authenticated' => false, 'expired' => false, 'customer_id' => 0, 'expires_in' => 0];
    }

    $sessionAuthVersion = (int)($_SESSION['customer_auth_version'] ?? 0);
    if ($sessionAuthVersion <= 0) {
        hivenest_customer_session_destroy();
        return ['authenticated' => false, 'expired' => false, 'customer_id' => 0, 'expires_in' => 0];
    }

    require_once __DIR__ . '/../access/dbconfig.php';
    $db = hivenest_db();
    if (!$db) {
        hivenest_customer_session_destroy();
        return ['authenticated' => false, 'expired' => false, 'customer_id' => 0, 'expires_in' => 0];
    }
    try {
        $authCheck = $db->prepare(
            "SELECT auth_version
             FROM customers
             WHERE id = :customer_id AND status = 'active'
             LIMIT 1"
        );
        $authCheck->execute(['customer_id' => $customerId]);
        $databaseAuthVersion = (int)($authCheck->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        error_log('Customer session revision validation failed: ' . $e->getMessage());
        hivenest_customer_session_destroy();
        return ['authenticated' => false, 'expired' => false, 'customer_id' => 0, 'expires_in' => 0];
    }
    if ($databaseAuthVersion <= 0 || $databaseAuthVersion !== $sessionAuthVersion) {
        hivenest_customer_session_destroy();
        return ['authenticated' => false, 'expired' => false, 'customer_id' => 0, 'expires_in' => 0];
    }

    $now = time();
    $idleSeconds = hivenest_customer_session_idle_seconds();
    $lastActivity = (int)($_SESSION['customer_last_activity_at']
        ?? $_SESSION['customer_authenticated_at']
        ?? $now);

    if ($lastActivity > 0 && ($now - $lastActivity) >= $idleSeconds) {
        hivenest_customer_session_destroy();
        return ['authenticated' => false, 'expired' => true, 'customer_id' => 0, 'expires_in' => 0];
    }

    if ($touch) {
        $_SESSION['customer_last_activity_at'] = $now;
        $lastActivity = $now;
    }

    return [
        'authenticated' => true,
        'expired' => false,
        'customer_id' => $customerId,
        'expires_in' => max(0, $idleSeconds - ($now - $lastActivity)),
    ];
}
