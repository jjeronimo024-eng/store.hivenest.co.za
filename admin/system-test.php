<?php
/**
 * HiveNest – System Health / Diagnostic Page
 * -----------------------------------------------------------------
 * One-page overview of every critical subsystem. Run after a deploy
 * (or any time the site feels off) to instantly see what's broken.
 *
 * Checks performed:
 *   ▸ DB: PDO + mysqli connectivity through /access/dbconfig.php
 *   ▸ DB: required tables exist + non-empty
 *   ▸ Auth: admin_users seeded with bcrypt hashes
 *   ▸ Products: every one of the 12 product pages maps to a DB row
 *   ▸ Pricing cache: file exists, fresh, writable
 *   ▸ File-system: log dir writable, .env readable
 *   ▸ Cart: required tables + sample row
 *   ▸ Endpoints: contact, pricing, cart APIs respond
 *
 * Access: protected by admin_auth.php (same login as products-admin).
 */

require_once __DIR__ . '/../utilities/admin_auth.php';
requireAdminAuth();
$admin = currentAdmin();
require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/deployment_readiness.php';

// -----------------------------------------------------------------
// Tiny check registry
// -----------------------------------------------------------------
$checks = []; // [ ['group'=>..,'name'=>..,'status'=>'pass|fail|warn','message'=>..,'detail'=>..], ... ]

function addCheck(string $group, string $name, string $status, string $message, string $detail = ''): void {
    global $checks;
    $checks[] = compact('group', 'name', 'status', 'message', 'detail');
}

function tryCheck(string $group, string $name, callable $fn): void {
    try {
        $result = $fn();
        if (is_array($result)) {
            addCheck($group, $name, $result[0], $result[1], $result[2] ?? '');
        } else {
            addCheck($group, $name, 'pass', (string)$result);
        }
    } catch (Throwable $e) {
        addCheck($group, $name, 'fail', $e->getMessage());
    }
}

// =================================================================
// 1) DB CONNECTION
// =================================================================
$pdo = null;
$mysqli = null;

tryCheck('Database', 'Backend/.env readable', function() {
    $p = __DIR__ . '/../Backend/.env';
    if (!file_exists($p))    return ['fail', 'Missing: ' . $p];
    if (!is_readable($p))    return ['fail', 'Not readable: ' . $p];
    return ['pass', basename($p) . ' OK', $p];
});

tryCheck('Database', 'dbconfig.php loaded', function() {
    if (!function_exists('hivenest_db'))            return ['fail', 'hivenest_db() not defined'];
    if (!function_exists('hivenest_db_mysqli'))     return ['fail', 'hivenest_db_mysqli() not defined'];
    if (!function_exists('hivenest_db_credentials')) return ['fail', 'hivenest_db_credentials() not defined'];
    return ['pass', 'All 3 helpers exposed'];
});

tryCheck('Database', 'Credentials parsed', function() {
    $c = hivenest_db_credentials();
    if (empty($c['host']) || empty($c['dbname']) || empty($c['username'])) {
        return ['fail', 'Missing host/dbname/username in env'];
    }
    return ['pass', "{$c['username']}@{$c['host']}:{$c['port']}/{$c['dbname']}"];
});

tryCheck('Database', 'PDO connection', function() use (&$pdo) {
    $pdo = hivenest_db();
    if (!$pdo instanceof PDO) {
        return ['fail', 'hivenest_db() returned null — see PHP error log for SQLSTATE'];
    }
    $version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    return ['pass', 'Connected · MariaDB/MySQL ' . $version];
});

tryCheck('Database', 'mysqli connection', function() use (&$mysqli) {
    $mysqli = hivenest_db_mysqli();
    if (!$mysqli instanceof mysqli) {
        return ['warn', 'mysqli unavailable (PDO is the primary path; only legacy code uses mysqli)'];
    }
    return ['pass', 'Connected · ' . $mysqli->server_info];
});

// =================================================================
// 2) DATABASE SCHEMA + SEED DATA
// =================================================================
$required_tables = [
    'admin_users', 'products', 'product_categories', 'product_pricing',
    'customers', 'orders', 'order_items',
];

foreach ($required_tables as $t) {
    tryCheck('Schema', "Table `$t`", function() use ($pdo, $t) {
        if (!$pdo) return ['fail', 'No DB connection'];
        $stmt = $pdo->prepare("SHOW TABLES LIKE :t");
        $stmt->execute(['t' => $t]);
        if (!$stmt->fetch()) return ['fail', "Table `$t` does NOT exist"];
        $count = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        $status = $count > 0 ? 'pass' : 'warn';
        $msg = $count > 0 ? "{$count} row(s)" : 'Table exists but is EMPTY';
        return [$status, $msg];
    });
}

foreach (hivenest_readiness_table_groups() as $requirementGroup => $tables) {
    tryCheck('Migration Readiness', $requirementGroup, function() use ($pdo, $tables) {
        if (!$pdo) return ['fail', 'No DB connection'];
        $placeholders = implode(',', array_fill(0, count($tables), '?'));
        $stmt = $pdo->prepare("
            SELECT TABLE_NAME
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ({$placeholders})
        ");
        $stmt->execute($tables);
        $found = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $missing = array_values(array_diff($tables, $found));
        if ($missing) {
            return ['fail', count($missing) . ' required table(s) missing', implode(', ', $missing)];
        }
        return ['pass', count($tables) . ' of ' . count($tables) . ' required tables ready'];
    });
}

foreach (hivenest_readiness_column_groups() as $requirementGroup => $tableColumns) {
    tryCheck('Upgrade Columns', $requirementGroup, function() use ($pdo, $tableColumns) {
        if (!$pdo) return ['fail', 'No DB connection'];
        $missing = [];
        $expected = 0;
        foreach ($tableColumns as $table => $columns) {
            foreach ($columns as $column) {
                $expected++;
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name AND COLUMN_NAME=:column_name
                ");
                $stmt->execute(['table_name' => $table, 'column_name' => $column]);
                if ((int)$stmt->fetchColumn() !== 1) $missing[] = $table . '.' . $column;
            }
        }
        if ($missing) return ['fail', count($missing) . ' upgrade column(s) missing', implode(', ', $missing)];
        return ['pass', $expected . ' of ' . $expected . ' upgrade columns ready'];
    });
}

foreach (hivenest_readiness_environment_requirements() as $requirementGroup => $keys) {
    tryCheck('Environment Readiness', $requirementGroup, function() use ($keys) {
        $values = hivenest_readiness_env_values();
        $missing = array_values(array_filter(
            $keys,
            static fn(string $key): bool => !hivenest_readiness_value_configured($key, $values)
        ));
        if ($missing) {
            return ['warn', count($missing) . ' environment value(s) missing or still placeholders', implode(', ', $missing)];
        }
        return ['pass', count($keys) . ' of ' . count($keys) . ' values configured'];
    });
}

foreach (hivenest_readiness_required_extensions() as $extension) {
    tryCheck('Runtime Readiness', 'PHP extension: ' . $extension, function() use ($extension) {
        return extension_loaded($extension)
            ? ['pass', 'Loaded']
            : ['fail', 'Required PHP extension is not loaded'];
    });
}

tryCheck('Runtime Readiness', 'PHP version', function() {
    if (version_compare(PHP_VERSION, '8.1.0', '<')) {
        return ['fail', 'PHP 8.1 or newer is required', PHP_VERSION];
    }
    return ['pass', PHP_VERSION];
});

tryCheck('Deployment Files', 'API router contains current private routes', function() {
    $source = (string)@file_get_contents(__DIR__ . '/../api/index.php');
    $required = [
        "case '/auth/me':",
        "case '/customers/service-credentials':",
        "case '/crm/capabilities':",
        "case '/crm/email-templates':",
        "case '/crm/mail-suppressions':",
        "case '/monitoring/ingest':",
        "case '/mail/events':",
    ];
    $missing = array_values(array_filter($required, static fn(string $route): bool => !str_contains($source, $route)));
    return $missing
        ? ['fail', 'API router is missing current routes', implode(', ', $missing)]
        : ['pass', count($required) . ' current route contracts found'];
});

tryCheck('Deployment Files', 'Root CSP header configured', function() {
    $source = (string)@file_get_contents(__DIR__ . '/../.htaccess');
    if ($source === '') return ['fail', 'Root .htaccess is missing or unreadable'];
    if (!stripos($source, 'Content-Security-Policy')) return ['warn', 'Content-Security-Policy header is not configured'];
    return ['pass', 'Content-Security-Policy directive found'];
});

// =================================================================
// 3) ADMIN AUTH HEALTH
// =================================================================
tryCheck('Authentication', 'Admin users seeded', function() use ($pdo) {
    if (!$pdo) return ['fail', 'No DB connection'];
    $stmt = $pdo->query("SELECT username, role, password_hash, is_active FROM admin_users");
    $rows = $stmt->fetchAll();
    if (empty($rows)) return ['fail', 'No admin users in DB — login impossible'];

    $bcrypt_ok = 0; $active = 0; $names = [];
    foreach ($rows as $r) {
        if (preg_match('/^\$2[aby]\$/', $r['password_hash'])) $bcrypt_ok++;
        if ((int)$r['is_active'] === 1) {
            $active++;
            $names[] = $r['username'] . " (" . $r['role'] . ")";
        }
    }
    if ($bcrypt_ok === 0) return ['fail', 'Hashes are NOT bcrypt — password_verify() will fail'];
    if ($active === 0)    return ['fail', 'All admin users are inactive'];
    return ['pass', "$active active · " . count($rows) . " total · " . $bcrypt_ok . " bcrypt", implode(', ', $names)];
});

tryCheck('Authentication', 'Logged-in admin session', function() use ($admin) {
    if (!$admin) return ['fail', 'No current admin in session'];
    return ['pass', "Signed in as {$admin['username']} ({$admin['role']})"];
});

tryCheck('Authentication', 'Brute-force tracker writable', function() {
    $dir = __DIR__ . '/../Backend/logs';
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0775, true)) return ['fail', "Cannot create $dir"];
    }
    if (!is_writable($dir)) return ['fail', "Not writable: $dir"];
    return ['pass', basename($dir) . '/ writable'];
});

// =================================================================
// 4) PRODUCT PAGE → DB MAPPING
// =================================================================
$product_pages = [
    'index.php',
    'domains/register.php',
    'hosting/wordpress.php',
    'hosting/windows.php',
    'hosting/linux-shared.php',
    'hosting/cloud-hosting.php',
    'servers/linux-dedicated.php',
    'email/cloud-mail.php',
    'email/enterprise.php',
    'email/google-workspace.php',
    'tools/sitelock.php',
    'tools/sslcert.php',
    'tools/xcitium.php',
    'branding/business-cards.php',
    'branding/letterheads.php',
    'branding/logo.php',
    'branding/signatures.php',
    'branding/website-builder.php',
    'marketing/seo.php',
    'marketing/social-media.php',
    'marketing/offers.php',
];

foreach ($product_pages as $page) {
    tryCheck('Product Pages', $page, function() use ($pdo, $page) {
        if (!$pdo) return ['fail', 'No DB connection'];

        $disk = __DIR__ . '/../' . $page;
        if (!file_exists($disk)) return ['fail', "Page file missing on disk: $page"];

        $plain = ltrim($page, '/');
        $stmt = $pdo->prepare("
            SELECT
                p.id,
                p.name,
                p.is_active,
                SUM(CASE WHEN pp.is_active = 1 THEN 1 ELSE 0 END) AS active_tiers
            FROM products p
            LEFT JOIN product_pricing pp ON pp.product_id = p.id
            WHERE p.page_url = :plain_path OR p.page_url = :slash_path
            GROUP BY p.id, p.name, p.is_active
            ORDER BY p.sort_order ASC, p.id ASC
        ");
        $stmt->execute([
            'plain_path' => $plain,
            'slash_path' => '/' . $plain,
        ]);
        $products = $stmt->fetchAll();
        if (!$products) {
            return ['warn', 'Page exists, but no product is assigned to this page URL'];
        }

        $activeProducts = 0;
        $activeTiers = 0;
        foreach ($products as $product) {
            if ((int)$product['is_active'] === 1) {
                $activeProducts++;
                $activeTiers += (int)$product['active_tiers'];
            }
        }
        if ($activeProducts === 0) {
            return ['warn', count($products) . ' mapped product(s), all hidden'];
        }
        if ($activeTiers === 0) {
            return ['warn', $activeProducts . ' active product(s), but no visible pricing packages'];
        }

        return ['pass', $activeProducts . ' active product(s) · ' . $activeTiers . ' visible package(s)'];
    });
}

// =================================================================
// 5) PRICING CACHE
// =================================================================
tryCheck('Pricing Cache', 'pricing_cache.json', function() {
    $f = __DIR__ . '/../utilities/pricing_cache.json';
    if (!file_exists($f)) {
        return ['warn', 'Missing — pages still work (DB fallback) but page loads are slower. Click REFRESH FROM DB in admin.'];
    }
    if (!is_writable($f)) return ['fail', 'Not writable — admin saves will fail to refresh cache'];
    $age = time() - filemtime($f);
    $human = $age < 60 ? "{$age}s" : ($age < 3600 ? floor($age/60) . 'm' : floor($age/3600) . 'h');
    $size = round(filesize($f) / 1024, 1);
    $data = json_decode(file_get_contents($f), true);
    $products_cached = is_array($data) ? count($data) : 0;
    return ['pass', "{$products_cached} products cached · age {$human} · {$size}KB"];
});

// =================================================================
// 6) CART / E-COMMERCE
// =================================================================
tryCheck('Cart & Checkout', 'orders + order_items reachable', function() use ($pdo) {
    if (!$pdo) return ['fail', 'No DB connection'];
    $o = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $i = (int)$pdo->query("SELECT COUNT(*) FROM order_items")->fetchColumn();
    return ['pass', "{$o} orders · {$i} line items"];
});

tryCheck('Cart & Checkout', 'cart.php / checkout.php exist', function() {
    $files = ['cart.php', 'checkout.php', 'order-success.php'];
    $missing = [];
    foreach ($files as $f) {
        if (!file_exists(__DIR__ . '/../' . $f)) $missing[] = $f;
    }
    if ($missing) return ['fail', 'Missing: ' . implode(', ', $missing)];
    return ['pass', 'All 3 storefront files present'];
});

// =================================================================
// 7) API ENDPOINT REACHABILITY (curl back to self)
// =================================================================
function selfHost(): string {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function pingEndpoint(string $relPath, int $timeout = 5): array {
    $url = selfHost() . $relPath;
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $verifyTls = !in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_SSL_VERIFYPEER => $verifyTls,
        CURLOPT_SSL_VERIFYHOST => $verifyTls ? 2 : 0,
        CURLOPT_NOBODY => false,
        CURLOPT_HTTPHEADER => ['Accept: application/json, text/html;q=0.9'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return [$code, $body, $err, $url];
}

if (function_exists('curl_init')) {
    foreach ([
        '/api/auth/me'                 => ['API · customer authentication', [200, 401]],
        '/api/customers/profile'       => ['API · customer profile', [401]],
        '/api/crm/dashboard'           => ['API · CRM dashboard', [401]],
        '/api/crm/capabilities'        => ['API · CRM capabilities', [401]],
        '/api/monitoring/ingest'       => ['API · monitoring ingestion', [405]],
        '/api/mail/events'             => ['API · mail delivery events', [405]],
        '/hosting/wordpress.php'       => ['Page · hosting/wordpress.php', [200]],
        '/branding/logo.php'           => ['Page · branding/logo.php', [200]],
        '/login.php'                   => ['Page · login.php', [200]],
    ] as $path => [$label, $expectedCodes]) {
        tryCheck('Endpoints', $label, function() use ($path, $expectedCodes) {
            [$code, $body, $err, $url] = pingEndpoint($path);
            if ($err)                  return ['fail', "curl error: $err", $url];
            if (!in_array($code, $expectedCodes, true)) {
                return ['fail', "HTTP $code; expected " . implode(' or ', $expectedCodes), $url];
            }
            $kb = round(strlen($body) / 1024, 1);
            return ['pass', "HTTP $code · {$kb}KB", $url];
        });
    }
} else {
    addCheck('Endpoints', 'cURL extension', 'warn', 'cURL not installed — skipping live endpoint tests');
}

// =================================================================
// 8) ACCESS FOLDER LOCKDOWN
// =================================================================
tryCheck('Security', '/access/.htaccess present', function() {
    $f = __DIR__ . '/../access/.htaccess';
    if (!file_exists($f)) return ['fail', '/access/.htaccess MISSING — folder is web-exposed'];
    return ['pass', basename($f) . ' present'];
});

tryCheck('Security', '/access/index.php 403 stub', function() {
    $f = __DIR__ . '/../access/index.php';
    if (!file_exists($f)) return ['warn', '/access/index.php missing (belt-and-braces stub)'];
    return ['pass', '403 stub present'];
});

// =================================================================
// 9) OPERATIONAL DATA HEALTH (read-only)
// =================================================================
tryCheck('Operational Health', 'Active catalogue packages', function() use ($pdo) {
    if (!$pdo) return ['fail', 'No DB connection'];
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM products p
        WHERE p.is_active = 1
          AND NOT EXISTS (
              SELECT 1
              FROM product_pricing pp
              WHERE pp.product_id = p.id AND pp.is_active = 1
          )
    ");
    $missing = (int)$stmt->fetchColumn();
    return $missing === 0
        ? ['pass', 'Every active product has at least one visible package']
        : ['warn', $missing . ' active product(s) have no visible package'];
});

tryCheck('Operational Health', 'Unique PayPal captures', function() use ($pdo) {
    if (!$pdo) return ['fail', 'No DB connection'];
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM (
            SELECT gateway_capture_id
            FROM payment_gateway_transactions
            WHERE gateway_capture_id IS NOT NULL AND gateway_capture_id <> ''
            GROUP BY gateway_capture_id
            HAVING COUNT(*) > 1
        ) duplicate_captures
    ");
    $duplicates = (int)$stmt->fetchColumn();
    return $duplicates === 0
        ? ['pass', 'No duplicate gateway capture IDs']
        : ['fail', $duplicates . ' duplicated gateway capture ID(s) require reconciliation'];
});

tryCheck('Operational Health', 'Provisioning exceptions', function() use ($pdo) {
    if (!$pdo) return ['fail', 'No DB connection'];
    $stmt = $pdo->query("
        SELECT status, COUNT(*) AS total
        FROM provisioning_jobs
        WHERE status IN ('retry', 'failed', 'manual_review')
        GROUP BY status
        ORDER BY status
    ");
    $parts = [];
    foreach ($stmt->fetchAll() as $row) {
        $parts[] = $row['status'] . ': ' . (int)$row['total'];
    }
    return !$parts
        ? ['pass', 'No retry, failed or manual-review jobs']
        : ['warn', implode(' · ', $parts)];
});

tryCheck('Operational Health', 'Stale provisioning queue', function() use ($pdo) {
    if (!$pdo) return ['fail', 'No DB connection'];
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM provisioning_jobs
        WHERE status IN ('pending', 'processing')
          AND updated_at < (NOW() - INTERVAL 15 MINUTE)
    ");
    $stale = (int)$stmt->fetchColumn();
    return $stale === 0
        ? ['pass', 'No pending/processing job older than 15 minutes']
        : ['warn', $stale . ' provisioning job(s) have been waiting over 15 minutes'];
});

tryCheck('Operational Health', 'Paid-order provisioning', function() use ($pdo) {
    if (!$pdo) return ['fail', 'No DB connection'];
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM orders
        WHERE payment_status = 'paid'
          AND provisioning_status NOT IN ('completed', 'not_required')
          AND updated_at < (NOW() - INTERVAL 15 MINUTE)
    ");
    $open = (int)$stmt->fetchColumn();
    return $open === 0
        ? ['pass', 'No paid order has unresolved provisioning older than 15 minutes']
        : ['warn', $open . ' paid order(s) require provisioning review'];
});

tryCheck('Operational Health', 'Outbound mail exceptions', function() use ($pdo) {
    if (!$pdo) return ['fail', 'No DB connection'];
    $stmt = $pdo->query("
        SELECT status, COUNT(*) AS total
        FROM outbound_mail_queue
        WHERE status IN ('retry', 'failed')
        GROUP BY status
        ORDER BY status
    ");
    $parts = [];
    foreach ($stmt->fetchAll() as $row) {
        $parts[] = $row['status'] . ': ' . (int)$row['total'];
    }
    return !$parts
        ? ['pass', 'No retry or failed mail']
        : ['warn', implode(' · ', $parts)];
});

tryCheck('Operational Health', 'Infrastructure alerts', function() use ($pdo) {
    if (!$pdo) return ['fail', 'No DB connection'];
    $stmt = $pdo->query("
        SELECT severity, COUNT(*) AS total
        FROM monitoring_alerts
        WHERE status = 'open'
        GROUP BY severity
        ORDER BY severity
    ");
    $parts = [];
    foreach ($stmt->fetchAll() as $row) {
        $parts[] = $row['severity'] . ': ' . (int)$row['total'];
    }
    return !$parts
        ? ['pass', 'No open infrastructure alert']
        : ['warn', implode(' · ', $parts)];
});

// =================================================================
// Tally
// =================================================================
$totals = ['pass' => 0, 'warn' => 0, 'fail' => 0];
foreach ($checks as $c) $totals[$c['status']]++;
$overall = $totals['fail'] > 0 ? 'fail' : ($totals['warn'] > 0 ? 'warn' : 'pass');

// Optional JSON output for monitoring tools: /admin/system-test.php?format=json
if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'overall' => $overall,
        'totals'  => $totals,
        'checks'  => $checks,
        'generated_at' => date(DATE_ATOM),
    ], JSON_PRETTY_PRINT);
    exit;
}

// Group checks for display
$grouped = [];
foreach ($checks as $c) {
    $grouped[$c['group']][] = $c;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HiveNest · System Health</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {
    --bg-0: #060812;
    --bg-1: #0d1320;
    --bg-2: #131a2a;
    --cyan: #00ffff;
    --green: #00ff88;
    --amber: #ffb547;
    --pink: #ff0064;
    --text: #e6f7ff;
    --muted: #6a8a96;
}
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: 'Rajdhani', sans-serif;
    background: radial-gradient(circle at 15% 10%, var(--bg-2) 0%, var(--bg-0) 70%);
    color: var(--text);
    min-height: 100vh;
    padding: 30px 20px 60px;
}
.wrap { max-width: 1100px; margin: 0 auto; }

/* Header */
.hdr {
    border: 1px solid rgba(0,255,255,0.25);
    border-radius: 14px;
    padding: 24px 28px;
    margin-bottom: 24px;
    background: linear-gradient(135deg, rgba(0,255,255,0.06), rgba(255,0,100,0.04));
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 18px;
}
.hdr h1 {
    font-size: 28px; font-weight: 700; letter-spacing: 2px; color: var(--cyan);
    text-shadow: 0 0 18px rgba(0,255,255,0.55);
    display: flex; align-items: center; gap: 12px;
}
.hdr .sub { color: var(--muted); font-size: 14px; margin-top: 4px; letter-spacing: 1px; }
.hdr-actions { display:flex; gap:10px; flex-wrap: wrap; }
.btn {
    background: transparent; border: 1px solid var(--cyan); color: var(--cyan);
    padding: 10px 18px; border-radius: 8px; font-family: inherit; font-weight: 600;
    text-decoration: none; cursor: pointer; font-size: 14px; letter-spacing:1px;
    transition: all .2s ease; display:inline-flex; align-items:center; gap:8px;
    text-transform: uppercase;
}
.btn:hover { background: var(--cyan); color: var(--bg-0); box-shadow: 0 0 16px rgba(0,255,255,0.45); }
.btn-pink { border-color: var(--pink); color: var(--pink); }
.btn-pink:hover { background: var(--pink); color: #fff; box-shadow: 0 0 16px rgba(255,0,100,0.45); }

/* Status banner */
.banner {
    border-radius: 14px;
    padding: 28px 30px;
    margin-bottom: 28px;
    border: 1px solid;
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 24px;
    align-items: center;
}
.banner.pass { border-color: var(--green); background: linear-gradient(135deg, rgba(0,255,136,0.10), rgba(0,255,136,0.02)); }
.banner.warn { border-color: var(--amber); background: linear-gradient(135deg, rgba(255,181,71,0.10), rgba(255,181,71,0.02)); }
.banner.fail { border-color: var(--pink);  background: linear-gradient(135deg, rgba(255,0,100,0.12), rgba(255,0,100,0.02)); }
.banner .icon { font-size: 48px; }
.banner.pass .icon { color: var(--green); text-shadow: 0 0 22px rgba(0,255,136,0.55); }
.banner.warn .icon { color: var(--amber); text-shadow: 0 0 22px rgba(255,181,71,0.55); }
.banner.fail .icon { color: var(--pink);  text-shadow: 0 0 22px rgba(255,0,100,0.55); }
.banner h2 { font-size: 24px; letter-spacing:2px; margin-bottom:6px; }
.banner .meta { color: var(--muted); font-size: 14px; }
.tally { display: flex; gap: 18px; }
.tally .pill {
    padding: 8px 16px; border-radius: 24px; font-weight: 700; font-size: 13px;
    border: 1px solid; letter-spacing: 1px;
}
.tally .pill.pass { color: var(--green); border-color: var(--green); }
.tally .pill.warn { color: var(--amber); border-color: var(--amber); }
.tally .pill.fail { color: var(--pink);  border-color: var(--pink);  }

/* Groups */
.group { margin-bottom: 22px; }
.group h3 {
    font-size: 18px; letter-spacing: 2px; color: var(--cyan); margin-bottom: 12px;
    text-transform: uppercase; display: flex; align-items: center; gap: 10px;
}
.group h3::after {
    content: ''; flex: 1; height: 1px;
    background: linear-gradient(90deg, rgba(0,255,255,0.4), transparent);
}
.row {
    display: grid; grid-template-columns: 28px 1fr auto; gap: 16px; align-items: center;
    padding: 12px 18px; border-radius: 10px; margin-bottom: 6px;
    border: 1px solid rgba(255,255,255,0.05);
    background: rgba(255,255,255,0.02);
    transition: background .2s ease;
}
.row:hover { background: rgba(0,255,255,0.04); border-color: rgba(0,255,255,0.18); }
.row .ico { font-size: 18px; text-align: center; }
.row.pass .ico { color: var(--green); }
.row.warn .ico { color: var(--amber); }
.row.fail .ico { color: var(--pink); }
.row .info .name { font-weight: 600; font-size: 15px; }
.row .info .msg  { color: var(--muted); font-size: 13px; margin-top: 2px; font-family: 'JetBrains Mono', monospace; }
.row .info .detail { color: #4a6470; font-size: 11px; margin-top: 4px; font-family: 'JetBrains Mono', monospace; word-break: break-all; }
.row .status {
    text-transform: uppercase; font-size: 11px; font-weight: 700;
    padding: 4px 10px; border-radius: 4px; letter-spacing: 1.5px;
}
.row.pass .status { background: rgba(0,255,136,0.15); color: var(--green); }
.row.warn .status { background: rgba(255,181,71,0.15); color: var(--amber); }
.row.fail .status { background: rgba(255,0,100,0.18); color: var(--pink); }

.foot {
    margin-top: 30px; color: var(--muted); font-size: 12px; text-align: center; letter-spacing:1px;
}
.foot a { color: var(--cyan); }
</style>
</head>
<body>
<div class="wrap">

    <div class="hdr">
        <div>
            <h1><i class="fas fa-heartbeat"></i> System Health</h1>
            <div class="sub">
                Signed in as <strong style="color:var(--cyan);"><?php echo htmlspecialchars($admin['username']); ?></strong>
                · <?php echo count($checks); ?> checks ·
                <?php echo date('Y-m-d H:i:s'); ?>
            </div>
        </div>
        <div class="hdr-actions">
            <a href="?" class="btn" data-testid="rerun-btn"><i class="fas fa-redo"></i> RE-RUN</a>
            <a href="?format=json" class="btn" target="_blank" data-testid="json-btn"><i class="fas fa-code"></i> JSON</a>
            <a href="products-admin.php" class="btn"><i class="fas fa-microchip"></i> ADMIN</a>
            <a href="?logout" class="btn btn-pink"><i class="fas fa-power-off"></i> LOGOUT</a>
        </div>
    </div>

    <div class="banner <?php echo $overall; ?>" data-testid="overall-status-<?php echo $overall; ?>">
        <div class="icon">
            <i class="fas fa-<?php echo $overall === 'pass' ? 'check-circle' : ($overall === 'warn' ? 'exclamation-triangle' : 'times-circle'); ?>"></i>
        </div>
        <div>
            <h2><?php echo $overall === 'pass' ? 'ALL SYSTEMS NOMINAL'
                       : ($overall === 'warn' ? 'OPERATING WITH WARNINGS' : 'CRITICAL ISSUES DETECTED'); ?></h2>
            <div class="meta">
                <?php if ($overall === 'pass'): ?>
                    Everything is responding correctly. No action required.
                <?php elseif ($overall === 'warn'): ?>
                    Non-fatal issues — site is still serving traffic but you should review the warnings below.
                <?php else: ?>
                    One or more critical checks failed. Fix the red rows below before declaring this deploy healthy.
                <?php endif; ?>
            </div>
        </div>
        <div class="tally">
            <div class="pill pass">✓ <?php echo $totals['pass']; ?> PASS</div>
            <div class="pill warn">! <?php echo $totals['warn']; ?> WARN</div>
            <div class="pill fail">✗ <?php echo $totals['fail']; ?> FAIL</div>
        </div>
    </div>

    <?php foreach ($grouped as $group => $rows): ?>
    <div class="group" data-testid="group-<?php echo strtolower(preg_replace('/[^a-z]+/i','-',$group)); ?>">
        <h3><i class="fas fa-<?php
            echo $group === 'Database'        ? 'database'
               : ($group === 'Schema'         ? 'table'
               : ($group === 'Authentication' ? 'user-shield'
               : ($group === 'Product Pages'  ? 'tags'
               : ($group === 'Pricing Cache'  ? 'memory'
               : ($group === 'Cart & Checkout'? 'shopping-cart'
               : ($group === 'Endpoints'      ? 'satellite-dish'
               : ($group === 'Security'       ? 'lock'
               : 'cog')))))));
        ?>"></i> <?php echo htmlspecialchars($group); ?></h3>
        <?php foreach ($rows as $c):
            $icon = $c['status'] === 'pass' ? 'check-circle' : ($c['status'] === 'warn' ? 'exclamation-triangle' : 'times-circle');
        ?>
        <div class="row <?php echo $c['status']; ?>" data-testid="check-<?php echo $c['status']; ?>">
            <div class="ico"><i class="fas fa-<?php echo $icon; ?>"></i></div>
            <div class="info">
                <div class="name"><?php echo htmlspecialchars($c['name']); ?></div>
                <div class="msg"><?php echo htmlspecialchars($c['message']); ?></div>
                <?php if (!empty($c['detail'])): ?>
                    <div class="detail"><?php echo htmlspecialchars($c['detail']); ?></div>
                <?php endif; ?>
            </div>
            <div class="status"><?php echo strtoupper($c['status']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <div class="foot">
        Tip: bookmark <code>?format=json</code> for an uptime monitor (UptimeRobot, BetterStack, etc.).
        Returns <code>overall: "pass"|"warn"|"fail"</code>.
    </div>

</div>
</body>
</html>
