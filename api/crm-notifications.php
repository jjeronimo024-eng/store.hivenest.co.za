<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
    session_name('HIVENEST_ADMIN');
    session_start();
}

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/crm_notifications.php';

function hivenest_crm_notifications_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_crm_notifications_env(string $key, string $default = ''): string
{
    $path = __DIR__ . '/../Backend/.env';
    if (!is_readable($path)) return $default;
    foreach (@file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$name, $value] = explode('=', $line, 2);
        if (trim($name) !== $key) continue;
        $value = trim($value);
        if (strlen($value) >= 2 && (
            ($value[0] === '"' && $value[strlen($value) - 1] === '"') ||
            ($value[0] === "'" && $value[strlen($value) - 1] === "'")
        )) $value = substr($value, 1, -1);
        return $value;
    }
    return $default;
}

function hivenest_crm_notifications_b64decode(string $value): string|false
{
    $remainder = strlen($value) % 4;
    if ($remainder) $value .= str_repeat('=', 4 - $remainder);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function hivenest_crm_notifications_b64encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function hivenest_crm_notifications_admin(PDO $db): array
{
    if (!empty($_SESSION['admin_user']) && !empty($_SESSION['admin_login_time'])) {
        return (array)$_SESSION['admin_user'];
    }
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    $token = preg_match('/Bearer\s+(.+)/i', $header, $match)
        ? trim($match[1])
        : trim((string)($_COOKIE['hivenest_admin_access_token'] ?? ''));
    $parts = explode('.', $token);
    if ($token === '' || count($parts) !== 3) return [];
    [$header64, $payload64, $signature64] = $parts;
    $headerJson = hivenest_crm_notifications_b64decode($header64);
    $payloadJson = hivenest_crm_notifications_b64decode($payload64);
    if ($headerJson === false || $payloadJson === false) return [];
    $jwtHeader = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);
    if (!is_array($jwtHeader) || !is_array($payload)) return [];
    if (($jwtHeader['alg'] ?? '') !== hivenest_crm_notifications_env('JWT_ALGORITHM', 'HS256')) return [];
    if (($payload['user_type'] ?? '') !== 'admin' || (!empty($payload['exp']) && (int)$payload['exp'] < time())) return [];
    $secret = hivenest_crm_notifications_env('JWT_SECRET_KEY');
    if ($secret === '') return [];
    $expected = hivenest_crm_notifications_b64encode(hash_hmac('sha256', $header64 . '.' . $payload64, $secret, true));
    if (!hash_equals($expected, $signature64)) return [];
    $adminId = (int)($payload['sub'] ?? 0);
    if ($adminId <= 0) return [];
    $stmt = $db->prepare('SELECT id, username, email, role FROM admin_users WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => $adminId]);
    $admin = $stmt->fetch() ?: [];
    if (!$admin) return [];
    $_SESSION['admin_user'] = $admin;
    $_SESSION['admin_login_time'] = $_SESSION['admin_login_time'] ?? time();
    return $admin;
}

function hivenest_crm_notifications_add_deadline_alerts(PDO $db, int $adminId): void
{
    $tableStmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME IN ('crm_work_items', 'provisioning_jobs')
    ");
    $tableStmt->execute();
    if ((int)$tableStmt->fetchColumn() < 2) return;

    $dueStmt = $db->prepare("
        SELECT
            wi.id,
            wi.due_at,
            pj.job_type,
            COALESCE(
                NULLIF(oi.product_name, ''),
                NULLIF(s.service_name, ''),
                NULLIF(s.domain_name, ''),
                NULLIF(oi.domain_name, ''),
                CONCAT('Work item #', wi.id)
            ) AS work_label
        FROM crm_work_items wi
        INNER JOIN provisioning_jobs pj ON pj.id = wi.provisioning_job_id
        LEFT JOIN order_items oi ON oi.id = pj.order_item_id
        LEFT JOIN services s ON s.id = pj.service_id
        WHERE wi.assigned_to = :admin_id
          AND wi.due_at IS NOT NULL
          AND wi.due_at < DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY)
          AND wi.work_status NOT IN ('completed', 'cancelled')
        ORDER BY wi.due_at ASC
        LIMIT 25
    ");
    $dueStmt->execute(['admin_id' => $adminId]);
    $existsStmt = $db->prepare("
        SELECT COUNT(*)
        FROM admin_notifications
        WHERE admin_user_id = :admin_id
          AND entity_type = 'crm_work_item'
          AND entity_id = :entity_id
          AND created_at >= CURRENT_DATE()
          AND created_at < DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY)
    ");
    foreach ($dueStmt->fetchAll() ?: [] as $item) {
        $workItemId = (int)$item['id'];
        $existsStmt->execute(['admin_id' => $adminId, 'entity_id' => $workItemId]);
        if ((int)$existsStmt->fetchColumn() > 0) continue;
        $dueAt = (string)$item['due_at'];
        $overdue = strtotime($dueAt) < time();
        $label = trim((string)$item['work_label']) ?: ('Work item #' . $workItemId);
        hivenest_crm_notify_admin(
            $db,
            $adminId,
            'urgent',
            $overdue ? 'Assigned work is overdue' : 'Assigned work is due today',
            $label . ' · Due ' . $dueAt,
            '/work-queue/?assigned=my&due=' . ($overdue ? 'overdue' : 'today') . '&q=' . rawurlencode($label),
            'crm_work_item',
            $workItemId
        );
    }
}

$db = hivenest_db();
if (!$db) hivenest_crm_notifications_out(503, ['ok' => false, 'error' => 'Database unavailable.']);

try {
    $admin = hivenest_crm_notifications_admin($db);
    if (!$admin) hivenest_crm_notifications_out(401, ['ok' => false, 'error' => 'Administrator authentication required.']);
    hivenest_crm_notifications_ensure($db);
    $adminId = (int)$admin['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        hivenest_crm_notifications_add_deadline_alerts($db, $adminId);
        $limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));
        $stmt = $db->prepare("
            SELECT id, uuid, notification_type, title, message, link_url,
                   entity_type, entity_id, is_read, read_at, created_at
            FROM admin_notifications
            WHERE admin_user_id = :admin_id
            ORDER BY created_at DESC, id DESC
            LIMIT {$limit}
        ");
        $stmt->execute(['admin_id' => $adminId]);
        $items = $stmt->fetchAll();
        $countStmt = $db->prepare('SELECT COUNT(*) FROM admin_notifications WHERE admin_user_id = :admin_id AND is_read = 0');
        $countStmt->execute(['admin_id' => $adminId]);
        hivenest_crm_notifications_out(200, [
            'ok' => true,
            'unread_count' => (int)$countStmt->fetchColumn(),
            'notifications' => $items,
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        hivenest_crm_notifications_out(405, ['ok' => false, 'error' => 'GET or POST required.']);
    }
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $action = trim((string)($input['action'] ?? ''));
    if ($action === 'mark_all_read') {
        $stmt = $db->prepare('UPDATE admin_notifications SET is_read = 1, read_at = COALESCE(read_at, CURRENT_TIMESTAMP) WHERE admin_user_id = :admin_id AND is_read = 0');
        $stmt->execute(['admin_id' => $adminId]);
        hivenest_crm_notifications_out(200, ['ok' => true, 'updated' => $stmt->rowCount()]);
    }
    if ($action === 'mark_read') {
        $notificationId = (int)($input['notification_id'] ?? 0);
        if ($notificationId <= 0) hivenest_crm_notifications_out(422, ['ok' => false, 'error' => 'Valid notification_id required.']);
        $stmt = $db->prepare('UPDATE admin_notifications SET is_read = 1, read_at = COALESCE(read_at, CURRENT_TIMESTAMP) WHERE id = :id AND admin_user_id = :admin_id');
        $stmt->execute(['id' => $notificationId, 'admin_id' => $adminId]);
        hivenest_crm_notifications_out(200, ['ok' => true, 'updated' => $stmt->rowCount()]);
    }
    hivenest_crm_notifications_out(422, ['ok' => false, 'error' => 'Unsupported action.']);
} catch (Throwable $e) {
    error_log('CRM notifications failed: ' . $e->getMessage());
    hivenest_crm_notifications_out(500, ['ok' => false, 'error' => 'Notification request failed.']);
}
