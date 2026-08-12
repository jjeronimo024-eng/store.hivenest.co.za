<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
    session_name('HIVENEST_ADMIN');
    session_start();
}

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/crm_permissions.php';
require_once __DIR__ . '/../utilities/support_notifications.php';
require_once __DIR__ . '/../utilities/customer_notifications.php';

function hivenest_crm_support_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_crm_support_env(string $key, string $default = ''): string
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

function hivenest_crm_support_b64url_decode(string $value): string|false
{
    $remainder = strlen($value) % 4;
    if ($remainder) $value .= str_repeat('=', 4 - $remainder);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function hivenest_crm_support_b64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function hivenest_crm_support_bearer_token(): string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(.+)/i', $header, $match)) return trim($match[1]);
    return trim((string)($_COOKIE['hivenest_admin_access_token'] ?? ''));
}

function hivenest_crm_support_authed(): bool
{
    return !empty($_SESSION['admin_user']) && !empty($_SESSION['admin_login_time']);
}

function hivenest_crm_support_verify_admin_jwt(PDO $db): bool
{
    $token = hivenest_crm_support_bearer_token();
    if ($token === '') return false;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    [$header64, $payload64, $signature64] = $parts;
    $headerJson = hivenest_crm_support_b64url_decode($header64);
    $payloadJson = hivenest_crm_support_b64url_decode($payload64);
    if ($headerJson === false || $payloadJson === false) return false;
    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);
    if (!is_array($header) || !is_array($payload)) return false;
    if (($header['alg'] ?? '') !== hivenest_crm_support_env('JWT_ALGORITHM', 'HS256')) return false;
    if (($payload['user_type'] ?? '') !== 'admin') return false;
    if (!empty($payload['exp']) && (int)$payload['exp'] < time()) return false;

    $secret = hivenest_crm_support_env('JWT_SECRET_KEY');
    if ($secret === '') return false;
    $expected = hivenest_crm_support_b64url_encode(hash_hmac('sha256', $header64 . '.' . $payload64, $secret, true));
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

function hivenest_crm_support_table_exists(PDO $db, string $table): bool
{
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
    ");
    $stmt->execute(['table_name' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function hivenest_crm_support_column_exists(PDO $db, string $table, string $column): bool
{
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
        ");
        $stmt->execute(['table_name' => $table, 'column_name' => $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        error_log('CRM support column check failed: ' . $e->getMessage());
        return false;
    }
}

function hivenest_crm_support_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$db = hivenest_db();
if (!$db) hivenest_crm_support_out(503, ['error' => 'CRM database is unavailable.']);
if (!hivenest_crm_support_authed() && !hivenest_crm_support_verify_admin_jwt($db)) {
    hivenest_crm_support_out(401, ['error' => 'Admin login required.']);
}
if (!hivenest_crm_support_table_exists($db, 'support_tickets') || !hivenest_crm_support_table_exists($db, 'support_ticket_replies')) {
    hivenest_crm_support_out(200, ['tickets' => [], 'message' => 'Support ticket tables have not been created yet.']);
}
$hasTicketOrderId = hivenest_crm_support_column_exists($db, 'support_tickets', 'order_id');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = trim((string)($_GET['status'] ?? ''));
    $customerId = (int)($_GET['customer'] ?? 0);
    $conditions = [];
    $params = [];
    if ($status !== '') {
        $conditions[] = 't.status = :status';
        $params['status'] = $status;
    }
    if ($customerId > 0) {
        $conditions[] = 't.customer_id = :customer_id';
        $params['customer_id'] = $customerId;
    }
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $orderSelect = $hasTicketOrderId ? ', t.order_id, o.order_number, o.total_amount AS order_total, o.currency AS order_currency' : '';
    $orderJoin = $hasTicketOrderId ? 'LEFT JOIN orders o ON o.id = t.order_id' : '';
    $stmt = $db->prepare("
        SELECT
            t.id, t.uuid, t.ticket_number, t.subject, t.priority, t.category, t.status, t.service_id,
            t.assigned_to, t.created_at, t.updated_at,
            c.email AS customer_email, c.first_name, c.last_name, c.company_name,
            s.service_name, s.service_type, s.domain_name,
            au.username AS assigned_username
            {$orderSelect}
        FROM support_tickets t
        INNER JOIN customers c ON c.id = t.customer_id
        LEFT JOIN services s ON s.id = t.service_id
        {$orderJoin}
        LEFT JOIN admin_users au ON au.id = t.assigned_to
        {$where}
        ORDER BY
            CASE t.priority
                WHEN 'urgent' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                ELSE 4
            END,
            CASE t.status
                WHEN 'open' THEN 1
                WHEN 'pending' THEN 2
                WHEN 'replied' THEN 3
                WHEN 'resolved' THEN 4
                ELSE 5
            END,
            t.id DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $tickets = $stmt->fetchAll() ?: [];
    if ($tickets) {
        $ids = array_map(static fn($row) => (int)$row['id'], $tickets);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $replyStmt = $db->prepare("
            SELECT r.*, au.username AS staff_username
            FROM support_ticket_replies r
            LEFT JOIN admin_users au ON au.id = r.author_id AND r.reply_type = 'staff'
            WHERE r.ticket_id IN ({$placeholders})
            ORDER BY r.ticket_id ASC, r.id ASC
        ");
        $replyStmt->execute($ids);
        $repliesByTicket = [];
        foreach ($replyStmt->fetchAll() ?: [] as $reply) {
            $reply['attachments'] = json_decode((string)($reply['attachments'] ?? ''), true) ?: [];
            $repliesByTicket[(int)$reply['ticket_id']][] = $reply;
        }
        foreach ($tickets as &$ticket) {
            $ticket['replies'] = $repliesByTicket[(int)$ticket['id']] ?? [];
        }
        unset($ticket);
    }
    hivenest_crm_support_out(200, ['tickets' => $tickets]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin = hivenest_crm_admin_record($db, (int)($_SESSION['admin_user']['id'] ?? 0));
    if (!hivenest_crm_role_allows($admin, 'support.manage')) {
        hivenest_crm_support_out(403, ['error' => 'Your staff role cannot change support tickets.']);
    }
    $input = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $ticketId = (int)($input['ticket_id'] ?? 0);
    $status = trim((string)($input['status'] ?? ''));
    $message = trim((string)($input['message'] ?? ''));
    $allowedStatuses = ['open','pending','replied','resolved','closed'];
    if ($ticketId <= 0) hivenest_crm_support_out(422, ['error' => 'Ticket ID is required.']);
    if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
        hivenest_crm_support_out(422, ['error' => 'Invalid ticket status.']);
    }
    if ($status === '' && $message === '') {
        hivenest_crm_support_out(422, ['error' => 'A reply message or status change is required.']);
    }

    $check = $db->prepare("
        SELECT t.id, t.customer_id, t.ticket_number, t.subject, t.status, c.email AS customer_email
        FROM support_tickets t
        INNER JOIN customers c ON c.id = t.customer_id
        WHERE t.id = :id
        LIMIT 1
    ");
    $check->execute(['id' => $ticketId]);
    $ticketRow = $check->fetch();
    if (!$ticketRow) hivenest_crm_support_out(404, ['error' => 'Ticket not found.']);

    $admin = $_SESSION['admin_user'] ?? [];
    $adminId = (int)($admin['id'] ?? 0);
    $db->beginTransaction();
    try {
        if ($message !== '') {
            $reply = $db->prepare("
                INSERT INTO support_ticket_replies
                    (uuid, ticket_id, reply_type, author_id, message, attachments, is_internal)
                VALUES
                    (:uuid, :ticket_id, 'staff', :author_id, :message, '[]', 0)
            ");
            $reply->execute([
                'uuid' => hivenest_crm_support_uuid(),
                'ticket_id' => $ticketId,
                'author_id' => $adminId,
                'message' => $message,
            ]);
            if ($status === '') $status = 'replied';
        }
        if ($status !== '') {
            $update = $db->prepare('UPDATE support_tickets SET status = :status, assigned_to = COALESCE(assigned_to, :admin_id) WHERE id = :id');
            $update->execute(['status' => $status, 'admin_id' => $adminId ?: null, 'id' => $ticketId]);
        }
        $db->commit();

        try {
            hivenest_support_notify_client_reply($ticketRow, (string)$ticketRow['customer_email'], $message, $status);
        } catch (Throwable $mailError) {
            error_log('Support client reply notification failed: ' . $mailError->getMessage());
        }
        try {
            hivenest_notify_customer(
                $db,
                (int)$ticketRow['customer_id'],
                'info',
                $message !== '' ? 'New support reply' : 'Support ticket updated',
                '#' . (string)$ticketRow['ticket_number'] . ' — ' . (string)$ticketRow['subject'],
                '/support/index.html?ticket=' . $ticketId,
                'support_ticket',
                $ticketId
            );
        } catch (Throwable $notificationError) {
            error_log('Support client in-app notification failed: ' . $notificationError->getMessage());
        }
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('CRM support update failed: ' . $e->getMessage());
        hivenest_crm_support_out(500, ['error' => 'Ticket could not be updated.']);
    }
    hivenest_crm_support_out(200, ['success' => true, 'message' => 'Ticket updated.']);
}

hivenest_crm_support_out(405, ['error' => 'Method not allowed.']);
