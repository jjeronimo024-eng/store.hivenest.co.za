<?php
declare(strict_types=1);

header('Cache-Control: no-store');
if (session_status() === PHP_SESSION_NONE) {
    session_name('HIVENEST_ADMIN');
    session_start();
}
require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/crm_permissions.php';

function hivenest_crm_reports_out(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function hivenest_crm_reports_env(string $key, string $default = ''): string
{
    $path = __DIR__ . '/../Backend/.env';
    foreach (is_readable($path) ? (@file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$name, $value] = explode('=', $line, 2);
        if (trim($name) === $key) return trim(trim($value), "\"'");
    }
    return $default;
}
function hivenest_crm_reports_decode(string $value): string|false
{
    $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
    return base64_decode(strtr($value, '-_', '+/'), true);
}
function hivenest_crm_reports_admin(PDO $db): array
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    $token = preg_match('/Bearer\s+(.+)/i', $header, $match) ? trim($match[1]) : '';
    if ($token === '') return [];
    $parts = explode('.', $token);
    if (count($parts) !== 3) return [];
    [$head, $body, $signature] = $parts;
    $headJson = hivenest_crm_reports_decode($head);
    $bodyJson = hivenest_crm_reports_decode($body);
    $jwtHead = $headJson === false ? null : json_decode($headJson, true);
    $payload = $bodyJson === false ? null : json_decode($bodyJson, true);
    $secret = hivenest_crm_reports_env('JWT_SECRET_KEY');
    $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $head . '.' . $body, $secret, true)), '+/', '-_'), '=');
    if (!is_array($jwtHead) || !is_array($payload) || $secret === ''
        || ($jwtHead['alg'] ?? '') !== hivenest_crm_reports_env('JWT_ALGORITHM', 'HS256')
        || ($payload['user_type'] ?? '') !== 'admin'
        || (!empty($payload['exp']) && (int)$payload['exp'] < time())
        || !hash_equals($expected, $signature)
    ) return [];
    $stmt = $db->prepare('SELECT id,username,email,role,permissions FROM admin_users WHERE id=:id AND is_active=1 LIMIT 1');
    $stmt->execute(['id' => (int)($payload['sub'] ?? 0)]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}
function hivenest_crm_reports_table(PDO $db, string $table): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name');
    $stmt->execute(['table_name' => $table]);
    return (int)$stmt->fetchColumn() === 1;
}
function hivenest_crm_reports_rows(PDO $db, string $sql, array $params): array
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function hivenest_crm_reports_csv_cell(mixed $value): string
{
    $text = is_scalar($value) || $value === null ? (string)$value : json_encode($value, JSON_UNESCAPED_SLASHES);
    if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'], true)) $text = "'" . $text;
    return $text;
}
function hivenest_crm_reports_csv(string $report, array $rows): never
{
    $filename = 'hivenest-' . preg_replace('/[^a-z0-9-]/', '', strtolower($report)) . '-' . gmdate('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    $stream = fopen('php://output', 'wb');
    fwrite($stream, "\xEF\xBB\xBF");
    if ($rows) {
        fputcsv($stream, array_keys($rows[0]));
        foreach ($rows as $row) fputcsv($stream, array_map('hivenest_crm_reports_csv_cell', $row));
    }
    fclose($stream);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') hivenest_crm_reports_out(405, ['ok' => false, 'error' => 'GET required.']);
$db = hivenest_db();
if (!$db) hivenest_crm_reports_out(503, ['ok' => false, 'error' => 'CRM database is unavailable.']);
$admin = hivenest_crm_reports_admin($db);
if (!$admin) hivenest_crm_reports_out(401, ['ok' => false, 'error' => 'Bearer administrator authentication required.']);

$report = strtolower(trim((string)($_GET['report'] ?? 'orders')));
$allowedReports = ['orders','refunds','provisioning','support','chat','mail','audit'];
if (!in_array($report, $allowedReports, true)) hivenest_crm_reports_out(422, ['ok' => false, 'error' => 'Unsupported report.']);
$fromInput = trim((string)($_GET['from'] ?? gmdate('Y-m-d', strtotime('-29 days'))));
$toInput = trim((string)($_GET['to'] ?? gmdate('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromInput) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toInput)) {
    hivenest_crm_reports_out(422, ['ok' => false, 'error' => 'Dates must use YYYY-MM-DD.']);
}
$from = DateTimeImmutable::createFromFormat('!Y-m-d', $fromInput);
$to = DateTimeImmutable::createFromFormat('!Y-m-d', $toInput);
if (!$from || !$to || $to < $from || (int)$from->diff($to)->days > 366) {
    hivenest_crm_reports_out(422, ['ok' => false, 'error' => 'Choose a valid date range of no more than 366 days.']);
}
$params = ['from_date' => $from->format('Y-m-d 00:00:00'), 'to_date' => $to->modify('+1 day')->format('Y-m-d 00:00:00')];
$format = strtolower(trim((string)($_GET['format'] ?? 'json')));
$limit = $format === 'csv' ? 5000 : 1000;

$summary = [
    'orders' => 0, 'paid_revenue' => 0.0, 'refunds' => 0.0,
    'net_revenue' => 0.0, 'provisioning_attention' => 0, 'open_support' => 0, 'chats' => 0,
    'mail_attention' => 0,
];
$overview = $db->prepare("
    SELECT COUNT(*) AS orders,
           COALESCE(SUM(CASE WHEN payment_status IN ('paid','partially_refunded') THEN total_amount ELSE 0 END),0) AS paid_revenue
    FROM orders WHERE created_at>=:from_date AND created_at<:to_date
");
$overview->execute($params);
$orderSummary = $overview->fetch(PDO::FETCH_ASSOC) ?: [];
$summary['orders'] = (int)($orderSummary['orders'] ?? 0);
$summary['paid_revenue'] = round((float)($orderSummary['paid_revenue'] ?? 0), 2);
if (hivenest_crm_reports_table($db, 'payment_refunds')) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payment_refunds WHERE status='completed' AND created_at>=:from_date AND created_at<:to_date");
    $stmt->execute($params);
    $summary['refunds'] = round((float)$stmt->fetchColumn(), 2);
}
if (hivenest_crm_reports_table($db, 'provisioning_jobs')) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM provisioning_jobs WHERE status IN ('retry','failed','manual_review') AND created_at>=:from_date AND created_at<:to_date");
    $stmt->execute($params);
    $summary['provisioning_attention'] = (int)$stmt->fetchColumn();
}
if (hivenest_crm_reports_table($db, 'support_tickets')) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','pending') AND created_at>=:from_date AND created_at<:to_date");
    $stmt->execute($params);
    $summary['open_support'] = (int)$stmt->fetchColumn();
}
if (hivenest_crm_reports_table($db, 'live_chat_sessions')) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM live_chat_sessions WHERE created_at>=:from_date AND created_at<:to_date");
    $stmt->execute($params);
    $summary['chats'] = (int)$stmt->fetchColumn();
}
if (hivenest_crm_reports_table($db, 'outbound_mail_queue')) {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM outbound_mail_queue
        WHERE status IN ('retry','failed')
          AND created_at>=:from_date AND created_at<:to_date
    ");
    $stmt->execute($params);
    $summary['mail_attention'] = (int)$stmt->fetchColumn();
}
$summary['net_revenue'] = round($summary['paid_revenue'] - $summary['refunds'], 2);

$rows = [];
if ($report === 'orders') {
    $rows = hivenest_crm_reports_rows($db, "
        SELECT o.order_number,c.email AS customer_email,o.order_status,o.payment_status,o.provisioning_status,
               o.total_amount,o.currency,o.payment_method,o.payment_reference,o.created_at
        FROM orders o INNER JOIN customers c ON c.id=o.customer_id
        WHERE o.created_at>=:from_date AND o.created_at<:to_date
        ORDER BY o.id DESC LIMIT {$limit}
    ", $params);
} elseif ($report === 'refunds' && hivenest_crm_reports_table($db, 'payment_refunds')) {
    $rows = hivenest_crm_reports_rows($db, "
        SELECT o.order_number,c.email AS customer_email,pr.amount,pr.currency,pr.status,pr.reason,
               pr.provider_refund_id,a.username AS administrator,pr.created_at,pr.completed_at
        FROM payment_refunds pr
        INNER JOIN orders o ON o.id=pr.order_id
        INNER JOIN customers c ON c.id=pr.customer_id
        INNER JOIN admin_users a ON a.id=pr.admin_user_id
        WHERE pr.created_at>=:from_date AND pr.created_at<:to_date
        ORDER BY pr.id DESC LIMIT {$limit}
    ", $params);
} elseif ($report === 'provisioning' && hivenest_crm_reports_table($db, 'provisioning_jobs')) {
    $rows = hivenest_crm_reports_rows($db, "
        SELECT o.order_number,c.email AS customer_email,pj.job_type,pj.provider,pj.status,pj.attempts,
               pj.provider_order_id,pj.error_message,pj.created_at,pj.completed_at
        FROM provisioning_jobs pj
        INNER JOIN orders o ON o.id=pj.order_id
        INNER JOIN customers c ON c.id=pj.customer_id
        WHERE pj.created_at>=:from_date AND pj.created_at<:to_date
        ORDER BY pj.id DESC LIMIT {$limit}
    ", $params);
} elseif ($report === 'support' && hivenest_crm_reports_table($db, 'support_tickets')) {
    $rows = hivenest_crm_reports_rows($db, "
        SELECT t.ticket_number,c.email AS customer_email,t.subject,t.category,t.priority,t.status,
               a.username AS assigned_agent,t.created_at,t.updated_at
        FROM support_tickets t
        INNER JOIN customers c ON c.id=t.customer_id
        LEFT JOIN admin_users a ON a.id=t.assigned_to
        WHERE t.created_at>=:from_date AND t.created_at<:to_date
        ORDER BY t.id DESC LIMIT {$limit}
    ", $params);
} elseif ($report === 'chat' && hivenest_crm_reports_table($db, 'live_chat_sessions')) {
    $rows = hivenest_crm_reports_rows($db, "
        SELECT lc.uuid AS chat_reference,lc.visitor_name,lc.visitor_email,lc.subject,lc.status,
               a.username AS assigned_agent,lc.waiting_since,lc.accepted_at,lc.closed_at,lc.last_message_at
        FROM live_chat_sessions lc
        LEFT JOIN admin_users a ON a.id=lc.assigned_admin_id
        WHERE lc.created_at>=:from_date AND lc.created_at<:to_date
        ORDER BY lc.id DESC LIMIT {$limit}
    ", $params);
} elseif ($report === 'mail' && hivenest_crm_reports_table($db, 'outbound_mail_queue')) {
    $rows = hivenest_crm_reports_rows($db, "
        SELECT mq.id,mq.recipient_email,mq.subject,mq.status,mq.attempts,mq.max_attempts,
               mq.manual_retry_count,a.username AS last_retried_by,mq.next_attempt_at,
               mq.sent_at,mq.last_error,mq.created_at
        FROM outbound_mail_queue mq
        LEFT JOIN admin_users a ON a.id=mq.last_retried_by
        WHERE mq.created_at>=:from_date AND mq.created_at<:to_date
        ORDER BY mq.id DESC LIMIT {$limit}
    ", $params);
} elseif ($report === 'audit') {
    $audit = [];
    if (hivenest_crm_reports_table($db, 'crm_work_item_history')) {
        $items = hivenest_crm_reports_rows($db, "
            SELECT h.created_at AS occurred_at,'work_queue' AS source,h.action,
                   COALESCE(a.username,'system') AS actor,CONCAT('work_item:',h.work_item_id) AS entity,
                   COALESCE(h.note,'') AS detail
            FROM crm_work_item_history h LEFT JOIN admin_users a ON a.id=h.admin_user_id
            WHERE h.created_at>=:from_date AND h.created_at<:to_date
            ORDER BY h.id DESC LIMIT {$limit}
        ", $params);
        $audit = array_merge($audit, $items);
    }
    if (hivenest_crm_reports_table($db, 'service_file_downloads')) {
        $items = hivenest_crm_reports_rows($db, "
            SELECT d.downloaded_at AS occurred_at,'file_download' AS source,'download' AS action,
                   COALESCE(a.username,c.email,d.actor_type) AS actor,CONCAT('service_file:',d.service_file_id) AS entity,
                   d.actor_type AS detail
            FROM service_file_downloads d
            LEFT JOIN admin_users a ON a.id=d.admin_id LEFT JOIN customers c ON c.id=d.customer_id
            WHERE d.downloaded_at>=:from_date AND d.downloaded_at<:to_date
            ORDER BY d.id DESC LIMIT {$limit}
        ", $params);
        $audit = array_merge($audit, $items);
    }
    if (hivenest_crm_reports_table($db, 'payment_refunds')) {
        $items = hivenest_crm_reports_rows($db, "
            SELECT pr.created_at AS occurred_at,'refund' AS source,pr.status AS action,a.username AS actor,
                   CONCAT('order:',pr.order_id) AS entity,CONCAT(pr.currency,' ',pr.amount,' — ',pr.reason) AS detail
            FROM payment_refunds pr INNER JOIN admin_users a ON a.id=pr.admin_user_id
            WHERE pr.created_at>=:from_date AND pr.created_at<:to_date
            ORDER BY pr.id DESC LIMIT {$limit}
        ", $params);
        $audit = array_merge($audit, $items);
    }
    if (hivenest_crm_reports_table($db, 'live_chat_messages')) {
        $items = hivenest_crm_reports_rows($db, "
            SELECT m.created_at AS occurred_at,'live_chat' AS source,'agent_message' AS action,a.username AS actor,
                   CONCAT('chat:',m.chat_session_id) AS entity,'Agent message stored; content omitted.' AS detail
            FROM live_chat_messages m INNER JOIN admin_users a ON a.id=m.admin_user_id
            WHERE m.actor_type='admin' AND m.created_at>=:from_date AND m.created_at<:to_date
            ORDER BY m.id DESC LIMIT {$limit}
        ", $params);
        $audit = array_merge($audit, $items);
    }
    usort($audit, static fn(array $a, array $b): int => strcmp((string)$b['occurred_at'], (string)$a['occurred_at']));
    $rows = array_slice($audit, 0, $limit);
}

if ($format === 'csv') {
    if (!hivenest_crm_role_allows($admin, 'report.export')) {
        hivenest_crm_reports_out(403, ['ok' => false, 'error' => 'Only administrators may export reports.']);
    }
    hivenest_crm_reports_csv($report, $rows);
}
hivenest_crm_reports_out(200, [
    'ok' => true,
    'report' => $report,
    'from' => $fromInput,
    'to' => $toInput,
    'summary' => $summary,
    'rows' => $rows,
    'row_limit' => $limit,
    'can_export' => hivenest_crm_role_allows($admin, 'report.export'),
    'can_manage_mail' => hivenest_crm_role_allows($admin, 'mail.retry'),
]);
