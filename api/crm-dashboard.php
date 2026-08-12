<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
    session_name('HIVENEST_ADMIN');
    session_start();
}

require_once __DIR__ . '/../access/dbconfig.php';

function hivenest_crm_dashboard_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_crm_dashboard_env(string $key, string $default = ''): string
{
    $path = __DIR__ . '/../Backend/.env';
    if (!is_readable($path)) return $default;
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
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
    return $default;
}

function hivenest_crm_dashboard_b64url_decode(string $value): string|false
{
    $remainder = strlen($value) % 4;
    if ($remainder) $value .= str_repeat('=', 4 - $remainder);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function hivenest_crm_dashboard_b64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function hivenest_crm_dashboard_bearer_token(): string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(.+)/i', $header, $match)) return trim($match[1]);
    return trim((string)($_COOKIE['hivenest_admin_access_token'] ?? ''));
}

function hivenest_crm_dashboard_authed(): bool
{
    return !empty($_SESSION['admin_user']) && !empty($_SESSION['admin_login_time']);
}

function hivenest_crm_dashboard_verify_admin_jwt(PDO $db): bool
{
    $token = hivenest_crm_dashboard_bearer_token();
    if ($token === '') return false;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    [$header64, $payload64, $signature64] = $parts;
    $headerJson = hivenest_crm_dashboard_b64url_decode($header64);
    $payloadJson = hivenest_crm_dashboard_b64url_decode($payload64);
    if ($headerJson === false || $payloadJson === false) return false;
    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);
    if (!is_array($header) || !is_array($payload)) return false;
    if (($header['alg'] ?? '') !== hivenest_crm_dashboard_env('JWT_ALGORITHM', 'HS256')) return false;
    if (($payload['user_type'] ?? '') !== 'admin') return false;
    if (!empty($payload['exp']) && (int)$payload['exp'] < time()) return false;

    $secret = hivenest_crm_dashboard_env('JWT_SECRET_KEY');
    if ($secret === '') return false;
    $expected = hivenest_crm_dashboard_b64url_encode(hash_hmac('sha256', $header64 . '.' . $payload64, $secret, true));
    if (!hash_equals($expected, $signature64)) return false;

    $adminId = (int)($payload['sub'] ?? 0);
    if ($adminId <= 0) return false;
    $stmt = $db->prepare('SELECT id, username, email, role, is_active FROM admin_users WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => $adminId]);
    $admin = $stmt->fetch();
    if (!$admin) return false;

    $_SESSION['admin_user'] = [
        'id' => (int)$admin['id'],
        'username' => (string)$admin['username'],
        'email' => (string)$admin['email'],
        'role' => (string)($admin['role'] ?? 'admin'),
    ];
    $_SESSION['admin_login_time'] = $_SESSION['admin_login_time'] ?? time();
    $_SESSION['admin_last_seen'] = time();
    return true;
}

function hivenest_crm_dashboard_table_exists(PDO $db, string $table): bool
{
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
        ");
        $stmt->execute(['table_name' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        error_log('CRM dashboard table check failed: ' . $e->getMessage());
        return false;
    }
}

function hivenest_crm_dashboard_scalar(PDO $db, string $sql, array $params = []): int|float
{
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return is_numeric($value) ? $value + 0 : 0;
    } catch (Throwable $e) {
        error_log('CRM dashboard scalar failed: ' . $e->getMessage());
        return 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    hivenest_crm_dashboard_out(405, ['error' => 'GET required.']);
}

$db = hivenest_db();
if (!$db) hivenest_crm_dashboard_out(503, ['error' => 'CRM database is unavailable.']);
if (!hivenest_crm_dashboard_authed() && !hivenest_crm_dashboard_verify_admin_jwt($db)) {
    hivenest_crm_dashboard_out(401, ['error' => 'Admin login required.']);
}

$customerCount = hivenest_crm_dashboard_table_exists($db, 'customers')
    ? (int)hivenest_crm_dashboard_scalar($db, 'SELECT COUNT(*) FROM customers')
    : 0;
$newCustomersWeek = hivenest_crm_dashboard_table_exists($db, 'customers')
    ? (int)hivenest_crm_dashboard_scalar($db, "SELECT COUNT(*) FROM customers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")
    : 0;

$monthlyRevenue = hivenest_crm_dashboard_table_exists($db, 'orders')
    ? (float)hivenest_crm_dashboard_scalar($db, "
        SELECT COALESCE(SUM(total_amount), 0)
        FROM orders
        WHERE payment_status = 'paid'
          AND created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')
    ")
    : 0.0;
$paidOrdersMonth = hivenest_crm_dashboard_table_exists($db, 'orders')
    ? (int)hivenest_crm_dashboard_scalar($db, "
        SELECT COUNT(*)
        FROM orders
        WHERE payment_status = 'paid'
          AND created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')
    ")
    : 0;

$activeServices = hivenest_crm_dashboard_table_exists($db, 'services')
    ? (int)hivenest_crm_dashboard_scalar($db, "SELECT COUNT(*) FROM services WHERE service_status = 'active'")
    : 0;
$expiringServices = hivenest_crm_dashboard_table_exists($db, 'services')
    ? (int)hivenest_crm_dashboard_scalar($db, "
        SELECT COUNT(*)
        FROM services
        WHERE next_due_date IS NOT NULL
          AND next_due_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY)
          AND service_status IN ('active','pending')
    ")
    : 0;

$openTickets = hivenest_crm_dashboard_table_exists($db, 'support_tickets')
    ? (int)hivenest_crm_dashboard_scalar($db, "SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','pending')")
    : 0;
$urgentTickets = hivenest_crm_dashboard_table_exists($db, 'support_tickets')
    ? (int)hivenest_crm_dashboard_scalar($db, "SELECT COUNT(*) FROM support_tickets WHERE priority = 'urgent' AND status NOT IN ('resolved','closed')")
    : 0;
$resolvedToday = hivenest_crm_dashboard_table_exists($db, 'support_tickets')
    ? (int)hivenest_crm_dashboard_scalar($db, "SELECT COUNT(*) FROM support_tickets WHERE status IN ('resolved','closed') AND updated_at >= CURRENT_DATE()")
    : 0;

$provisioningQueue = hivenest_crm_dashboard_table_exists($db, 'provisioning_jobs')
    ? (int)hivenest_crm_dashboard_scalar($db, "SELECT COUNT(*) FROM provisioning_jobs WHERE status IN ('pending','retry','manual_review')")
    : 0;
$onboardingQueue = hivenest_crm_dashboard_table_exists($db, 'customer_service_onboarding')
    ? (int)hivenest_crm_dashboard_scalar($db, "SELECT COUNT(*) FROM customer_service_onboarding WHERE status IN ('submitted','reviewing','pending')")
    : 0;
$serviceRequestQueue = hivenest_crm_dashboard_table_exists($db, 'service_requests')
    ? (int)hivenest_crm_dashboard_scalar($db, "SELECT COUNT(*) FROM service_requests WHERE status IN ('pending','in_review','approved')")
    : 0;
$waitingChats = hivenest_crm_dashboard_table_exists($db, 'live_chat_sessions')
    ? (int)hivenest_crm_dashboard_scalar($db, "SELECT COUNT(*) FROM live_chat_sessions WHERE status='waiting'")
    : 0;
$workQueue = hivenest_crm_dashboard_table_exists($db, 'crm_work_items')
    ? (int)hivenest_crm_dashboard_scalar($db, "SELECT COUNT(*) FROM crm_work_items WHERE work_status NOT IN ('completed','cancelled')")
    : 0;
$workQueueOverdue = hivenest_crm_dashboard_table_exists($db, 'crm_work_items')
    ? (int)hivenest_crm_dashboard_scalar($db, "
        SELECT COUNT(*)
        FROM crm_work_items
        WHERE due_at < NOW()
          AND work_status NOT IN ('completed','cancelled')
    ")
    : 0;
$workQueueDueToday = hivenest_crm_dashboard_table_exists($db, 'crm_work_items')
    ? (int)hivenest_crm_dashboard_scalar($db, "
        SELECT COUNT(*)
        FROM crm_work_items
        WHERE DATE(due_at) = CURRENT_DATE()
          AND work_status NOT IN ('completed','cancelled')
    ")
    : 0;
$workQueueUnassigned = hivenest_crm_dashboard_table_exists($db, 'crm_work_items')
    ? (int)hivenest_crm_dashboard_scalar($db, "
        SELECT COUNT(*)
        FROM crm_work_items
        WHERE assigned_to IS NULL
          AND work_status NOT IN ('completed','cancelled')
    ")
    : 0;
$workQueueTeam = (hivenest_crm_dashboard_table_exists($db, 'crm_work_items') && hivenest_crm_dashboard_table_exists($db, 'provisioning_jobs'))
    ? (int)hivenest_crm_dashboard_scalar($db, "
        SELECT COUNT(*)
        FROM crm_work_items wi
        INNER JOIN provisioning_jobs pj ON pj.id = wi.provisioning_job_id
        WHERE wi.work_status NOT IN ('completed','cancelled')
          AND pj.provider = 'hivenest_team'
    ")
    : 0;
$workQueueMyorderbox = (hivenest_crm_dashboard_table_exists($db, 'crm_work_items') && hivenest_crm_dashboard_table_exists($db, 'provisioning_jobs'))
    ? (int)hivenest_crm_dashboard_scalar($db, "
        SELECT COUNT(*)
        FROM crm_work_items wi
        INNER JOIN provisioning_jobs pj ON pj.id = wi.provisioning_job_id
        WHERE wi.work_status NOT IN ('completed','cancelled')
          AND pj.provider = 'myorderbox'
    ")
    : 0;

$recentOrders = [];
if (hivenest_crm_dashboard_table_exists($db, 'orders')) {
    $stmt = $db->query("
        SELECT o.id, o.order_number, o.total_amount, o.currency, o.payment_status, o.provisioning_status, o.created_at,
               c.id AS customer_id, c.email, c.first_name, c.last_name, c.company_name
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        ORDER BY o.id DESC
        LIMIT 5
    ");
    $recentOrders = $stmt ? ($stmt->fetchAll() ?: []) : [];
}

$tickets = [];
if (hivenest_crm_dashboard_table_exists($db, 'support_tickets')) {
    $stmt = $db->query("
        SELECT t.id, t.ticket_number, t.subject, t.priority, t.category, t.status, t.created_at,
               c.id AS customer_id, c.email, c.first_name, c.last_name, c.company_name
        FROM support_tickets t
        LEFT JOIN customers c ON c.id = t.customer_id
        WHERE t.status NOT IN ('resolved','closed')
        ORDER BY
            CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END,
            t.id DESC
        LIMIT 5
    ");
    $tickets = $stmt ? ($stmt->fetchAll() ?: []) : [];
}

$infrastructure = [
    'configured' => false,
    'stale_minutes' => max(1, min(1440, (int)hivenest_crm_dashboard_env('MONITORING_STALE_MINUTES', '5'))),
    'nodes_total' => 0,
    'nodes_up' => 0,
    'nodes_degraded' => 0,
    'nodes_down' => 0,
    'nodes_stale' => 0,
    'open_alerts' => 0,
    'critical_alerts' => 0,
    'nodes' => [],
    'alerts' => [],
];
if (
    hivenest_crm_dashboard_table_exists($db, 'monitoring_nodes')
    && hivenest_crm_dashboard_table_exists($db, 'monitoring_alerts')
) {
    $infrastructure['configured'] = true;
    $staleCutoff = time() - ($infrastructure['stale_minutes'] * 60);
    $nodeStmt = $db->query(
        'SELECT id,node_key,display_name,provider,status,last_seen_at,cpu_percent,memory_percent,
                disk_percent,network_rx_bps,network_tx_bps,latency_ms,uptime_seconds
         FROM monitoring_nodes ORDER BY display_name,id LIMIT 50'
    );
    foreach ($nodeStmt ? ($nodeStmt->fetchAll() ?: []) : [] as $node) {
        $lastSeen = !empty($node['last_seen_at']) ? strtotime((string)$node['last_seen_at']) : false;
        $stale = $lastSeen === false || $lastSeen < $staleCutoff;
        $status = $stale ? 'stale' : (string)$node['status'];
        $infrastructure['nodes_total']++;
        if ($stale) $infrastructure['nodes_stale']++;
        elseif ($status === 'up') $infrastructure['nodes_up']++;
        elseif ($status === 'degraded') $infrastructure['nodes_degraded']++;
        elseif ($status === 'down') $infrastructure['nodes_down']++;
        $infrastructure['nodes'][] = [
            'id' => (int)$node['id'],
            'node_key' => (string)$node['node_key'],
            'display_name' => (string)$node['display_name'],
            'provider' => (string)$node['provider'],
            'status' => $status,
            'last_seen_at' => $node['last_seen_at'],
            'cpu_percent' => $node['cpu_percent'] !== null ? (float)$node['cpu_percent'] : null,
            'memory_percent' => $node['memory_percent'] !== null ? (float)$node['memory_percent'] : null,
            'disk_percent' => $node['disk_percent'] !== null ? (float)$node['disk_percent'] : null,
            'network_rx_bps' => $node['network_rx_bps'] !== null ? (float)$node['network_rx_bps'] : null,
            'network_tx_bps' => $node['network_tx_bps'] !== null ? (float)$node['network_tx_bps'] : null,
            'latency_ms' => $node['latency_ms'] !== null ? (float)$node['latency_ms'] : null,
            'uptime_seconds' => $node['uptime_seconds'] !== null ? (int)$node['uptime_seconds'] : null,
        ];
    }
    $alertStmt = $db->query(
        'SELECT a.id,a.node_id,a.alert_type,a.severity,a.message,a.opened_at,a.last_observed_at,
                n.display_name
         FROM monitoring_alerts a
         INNER JOIN monitoring_nodes n ON n.id=a.node_id
         WHERE a.status="open"
         ORDER BY CASE a.severity WHEN "critical" THEN 1 ELSE 2 END,a.last_observed_at DESC
         LIMIT 25'
    );
    foreach ($alertStmt ? ($alertStmt->fetchAll() ?: []) : [] as $alert) {
        $infrastructure['open_alerts']++;
        if ((string)$alert['severity'] === 'critical') $infrastructure['critical_alerts']++;
        $infrastructure['alerts'][] = [
            'id' => (int)$alert['id'],
            'node_id' => (int)$alert['node_id'],
            'display_name' => (string)$alert['display_name'],
            'type' => (string)$alert['alert_type'],
            'severity' => (string)$alert['severity'],
            'message' => (string)$alert['message'],
            'opened_at' => $alert['opened_at'],
            'last_observed_at' => $alert['last_observed_at'],
        ];
    }
}

hivenest_crm_dashboard_out(200, [
    'metrics' => [
        'customers' => ['total' => $customerCount, 'new_this_week' => $newCustomersWeek],
        'revenue' => ['monthly_total' => $monthlyRevenue, 'paid_orders_month' => $paidOrdersMonth, 'currency' => 'USD'],
        'services' => ['active' => $activeServices, 'expiring_30_days' => $expiringServices],
        'support' => ['open' => $openTickets, 'urgent' => $urgentTickets, 'resolved_today' => $resolvedToday],
        'queues' => [
            'provisioning' => $provisioningQueue,
            'onboarding' => $onboardingQueue,
            'service_requests' => $serviceRequestQueue,
            'waiting_chats' => $waitingChats,
            'work_queue' => $workQueue,
            'work_queue_overdue' => $workQueueOverdue,
            'work_queue_due_today' => $workQueueDueToday,
            'work_queue_unassigned' => $workQueueUnassigned,
            'work_queue_team' => $workQueueTeam,
            'work_queue_myorderbox' => $workQueueMyorderbox,
        ],
        'infrastructure' => $infrastructure,
    ],
    'recent_orders' => $recentOrders,
    'support_tickets' => $tickets,
]);
