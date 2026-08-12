<?php
/**
 * HiveNest - Central Database Access Configuration
 * -----------------------------------------------------------------
 * SINGLE source of truth for ALL database connections in the app.
 *
 * Every PHP file that needs DB access goes through this file:
 *   require_once __DIR__ . '/../access/dbconfig.php';
 *   $pdo    = hivenest_db();         // PDO singleton (preferred)
 *   $mysqli = hivenest_db_mysqli();  // mysqli singleton (legacy code)
 *   $creds  = hivenest_db_credentials();  // raw .env values
 *
 * ── Security roadmap ────────────────────────────────────────────────
 * This folder ("access/") is structured so it can be moved OUT of
 * public_html later without touching any consumer file. To relocate:
 *
 *   1. Move /access/  →  e.g.  /home/user/private/access/
 *   2. At the very top of each consumer, replace the relative require:
 *        require_once __DIR__ . '/../access/dbconfig.php';
 *      with the new absolute path:
 *        require_once '/home/user/private/access/dbconfig.php';
 *      OR define HIVENEST_ACCESS_PATH in php.ini's auto_prepend_file
 *      and require that constant here.
 *   3. Move Backend/.env into the private folder too.
 *
 * For now everything still works under public_html, but the .env
 * file is already protected by the .htaccess that sits beside it.
 * ───────────────────────────────────────────────────────────────────
 *
 * NOTE: do NOT put any business logic or output in this file. It
 * MUST be safe to include from any script (CLI, web, AJAX).
 */

// Path to the env file. Override-able by defining HIVENEST_ENV_PATH
// before including this file (e.g. when access/ is moved out of webroot).
if (!defined('HIVENEST_ENV_PATH')) {
    define('HIVENEST_ENV_PATH', __DIR__ . '/../Backend/.env');
}

/**
 * Read DB credentials once from Backend/.env and cache them.
 * Sensible defaults so the file never throws on a misconfigured box —
 * the connection helper below will surface the real DB error instead.
 *
 * @return array{host:string,port:string,dbname:string,username:string,password:string,charset:string}
 */
function hivenest_db_credentials(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $cred = [
        'host'     => 'localhost',
        'port'     => '3306',
        'dbname'   => 'hivenest_main',
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ];

    if (is_readable(HIVENEST_ENV_PATH)) {
        $lines = @file(HIVENEST_ENV_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') === false) continue;
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            // strip surrounding quotes if present
            if (strlen($v) >= 2) {
                $first = $v[0]; $last = $v[strlen($v) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $v = substr($v, 1, -1);
                }
            }
            switch ($k) {
                case 'DB_HOST':     $cred['host']     = $v; break;
                case 'DB_PORT':     $cred['port']     = $v; break;
                case 'DB_NAME':     $cred['dbname']   = $v; break;
                case 'DB_USER':     $cred['username'] = $v; break;
                case 'DB_PASSWORD': $cred['password'] = $v; break;
                case 'DB_CHARSET':  $cred['charset']  = $v; break;
            }
        }
    }
    $cache = $cred;
    return $cache;
}

/**
 * Get the PDO singleton (preferred for all new code).
 *
 * @return PDO|null  null when connection fails (caller should handle gracefully)
 */
function hivenest_db(): ?PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    try {
        $c   = hivenest_db_credentials();
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['dbname']};charset={$c['charset']}";
        $pdo = new PDO($dsn, $c['username'], $c['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ]);
        return $pdo;
    } catch (Throwable $e) {
        $driver_code = 0;
        if ($e instanceof PDOException && is_array($e->errorInfo ?? null)) {
            $driver_code = (int)($e->errorInfo[1] ?? 0);
        }
        $GLOBALS['hivenest_db_last_error'] = [
            'driver_code' => $driver_code,
            'message' => $e->getMessage(),
        ];
        error_log('hivenest_db (PDO) connection failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Return the last connection failure for local error classification.
 * Never includes or exposes the configured password.
 */
function hivenest_db_last_error(): ?array {
    return $GLOBALS['hivenest_db_last_error'] ?? null;
}

/**
 * Get the mysqli singleton (used by legacy DatabaseHelper).
 *
 * @return mysqli|null
 */
function hivenest_db_mysqli(): ?mysqli {
    static $mysqli = null;
    if ($mysqli instanceof mysqli && @$mysqli->ping()) return $mysqli;

    try {
        $c = hivenest_db_credentials();
        $mysqli = @new mysqli($c['host'], $c['username'], $c['password'], $c['dbname'], (int)$c['port']);
        if ($mysqli->connect_error) {
            error_log('hivenest_db_mysqli connect_error: ' . $mysqli->connect_error);
            $mysqli = null;
            return null;
        }
        $mysqli->set_charset($c['charset']);
        return $mysqli;
    } catch (Throwable $e) {
        error_log('hivenest_db_mysqli failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Convenience: close both connections (useful at end of long-running CLI).
 */
function hivenest_db_close(): void {
    // PDO is closed by setting the static to null — but we can't reach it
    // from here without a more complex container. mysqli we can close:
    $m = hivenest_db_mysqli();
    if ($m instanceof mysqli) @$m->close();
}
