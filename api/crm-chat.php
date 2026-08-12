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

function hivenest_crm_chat_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function hivenest_crm_chat_env(string $key, string $default = ''): string
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
function hivenest_crm_chat_decode(string $value): string|false
{
    $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
    return base64_decode(strtr($value, '-_', '+/'), true);
}
function hivenest_crm_chat_admin(PDO $db): array
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    $token = preg_match('/Bearer\s+(.+)/i', $header, $match) ? trim($match[1]) : '';
    if ($token === '') return [];
    $parts = explode('.', $token);
    if (count($parts) !== 3) return [];
    [$head, $body, $signature] = $parts;
    $headJson = hivenest_crm_chat_decode($head);
    $bodyJson = hivenest_crm_chat_decode($body);
    $jwtHead = $headJson === false ? null : json_decode($headJson, true);
    $payload = $bodyJson === false ? null : json_decode($bodyJson, true);
    $secret = hivenest_crm_chat_env('JWT_SECRET_KEY');
    $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $head . '.' . $body, $secret, true)), '+/', '-_'), '=');
    if (!is_array($jwtHead) || !is_array($payload) || $secret === ''
        || ($jwtHead['alg'] ?? '') !== hivenest_crm_chat_env('JWT_ALGORITHM', 'HS256')
        || ($payload['user_type'] ?? '') !== 'admin'
        || (!empty($payload['exp']) && (int)$payload['exp'] < time())
        || !hash_equals($expected, $signature)
    ) return [];
    $stmt = $db->prepare('SELECT id,username,email,role FROM admin_users WHERE id=:id AND is_active=1 LIMIT 1');
    $stmt->execute(['id' => (int)($payload['sub'] ?? 0)]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

$db = hivenest_db();
if (!$db) hivenest_crm_chat_out(503, ['ok' => false, 'error' => 'CRM database is unavailable.']);
$admin = hivenest_crm_chat_admin($db);
if (!$admin) hivenest_crm_chat_out(401, ['ok' => false, 'error' => 'Bearer administrator authentication required.']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $chatId = max(0, (int)($_GET['chat_id'] ?? 0));
    if ($chatId > 0) {
        $chat = $db->prepare("
            SELECT c.*,a.username AS assigned_agent,cu.email AS customer_account_email
            FROM live_chat_sessions c
            LEFT JOIN admin_users a ON a.id=c.assigned_admin_id
            LEFT JOIN customers cu ON cu.id=c.customer_id
            WHERE c.id=:id LIMIT 1
        ");
        $chat->execute(['id' => $chatId]);
        $row = $chat->fetch(PDO::FETCH_ASSOC);
        if (!$row) hivenest_crm_chat_out(404, ['ok' => false, 'error' => 'Chat not found.']);
        $messages = $db->prepare("
            SELECT m.id,m.actor_type,m.message,m.created_at,a.username AS agent_name
            FROM live_chat_messages m
            LEFT JOIN admin_users a ON a.id=m.admin_user_id
            WHERE m.chat_session_id=:id ORDER BY m.id ASC LIMIT 1000
        ");
        $messages->execute(['id' => $chatId]);
        hivenest_crm_chat_out(200, ['ok' => true, 'chat' => $row, 'messages' => $messages->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }
    $status = trim((string)($_GET['status'] ?? 'open'));
    $where = $status === 'closed' ? "c.status IN ('closed','abandoned')" : "c.status IN ('waiting','active')";
    $rows = $db->query("
        SELECT c.id,c.uuid,c.customer_id,c.visitor_name,c.visitor_email,c.subject,c.status,
               c.assigned_admin_id,c.waiting_since,c.accepted_at,c.closed_at,c.last_message_at,
               a.username AS assigned_agent,
               (SELECT message FROM live_chat_messages m WHERE m.chat_session_id=c.id ORDER BY m.id DESC LIMIT 1) AS latest_message
        FROM live_chat_sessions c
        LEFT JOIN admin_users a ON a.id=c.assigned_admin_id
        WHERE {$where}
        ORDER BY FIELD(c.status,'waiting','active','closed','abandoned'),c.waiting_since ASC
        LIMIT 200
    ");
    hivenest_crm_chat_out(200, ['ok' => true, 'chats' => $rows->fetchAll(PDO::FETCH_ASSOC) ?: [], 'admin_id' => (int)$admin['id']]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') hivenest_crm_chat_out(405, ['ok' => false, 'error' => 'GET or POST required.']);
if (!hivenest_crm_role_allows($admin, 'chat.manage')) {
    hivenest_crm_chat_out(403, ['ok' => false, 'error' => 'Your staff role cannot operate live chat.']);
}
$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) hivenest_crm_chat_out(400, ['ok' => false, 'error' => 'Invalid JSON input.']);
$action = strtolower(trim((string)($input['action'] ?? '')));
$chatId = (int)($input['chat_id'] ?? 0);
if ($chatId <= 0) hivenest_crm_chat_out(422, ['ok' => false, 'error' => 'Valid chat_id required.']);

$db->beginTransaction();
try {
    $stmt = $db->prepare('SELECT * FROM live_chat_sessions WHERE id=:id FOR UPDATE');
    $stmt->execute(['id' => $chatId]);
    $chat = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$chat) throw new DomainException('Chat not found.');
    $assigned = (int)($chat['assigned_admin_id'] ?? 0);
    $adminId = (int)$admin['id'];
    $canOverride = in_array((string)($admin['role'] ?? ''), ['super_admin','admin'], true);
    if ($action === 'accept') {
        if ((string)$chat['status'] !== 'waiting' || $assigned > 0) throw new DomainException('This chat has already been accepted or closed.');
        $db->prepare("
            UPDATE live_chat_sessions
            SET status='active',assigned_admin_id=:admin_id,accepted_at=NOW()
            WHERE id=:id
        ")->execute(['admin_id' => $adminId, 'id' => $chatId]);
        $db->prepare("
            INSERT INTO live_chat_messages (chat_session_id,actor_type,admin_user_id,message)
            VALUES (:chat_id,'system',:admin_id,:message)
        ")->execute(['chat_id' => $chatId, 'admin_id' => $adminId, 'message' => (string)$admin['username'] . ' joined the chat.']);
    } elseif ($action === 'message') {
        if ((string)$chat['status'] !== 'active') throw new DomainException('Chat is not active.');
        if ($assigned !== $adminId && !$canOverride) throw new DomainException('This chat belongs to another agent.');
        $message = trim((string)($input['message'] ?? ''));
        if ($message === '' || strlen($message) > 4000) throw new DomainException('Message must be between 1 and 4000 characters.');
        $db->prepare("
            INSERT INTO live_chat_messages (chat_session_id,actor_type,admin_user_id,message)
            VALUES (:chat_id,'admin',:admin_id,:message)
        ")->execute(['chat_id' => $chatId, 'admin_id' => $adminId, 'message' => $message]);
        $db->prepare('UPDATE live_chat_sessions SET last_message_at=NOW() WHERE id=:id')->execute(['id' => $chatId]);
    } elseif ($action === 'close') {
        if (!in_array((string)$chat['status'], ['waiting','active'], true)) throw new DomainException('Chat is already closed.');
        if ($assigned > 0 && $assigned !== $adminId && !$canOverride) throw new DomainException('This chat belongs to another agent.');
        $db->prepare("UPDATE live_chat_sessions SET status='closed',closed_at=NOW() WHERE id=:id")->execute(['id' => $chatId]);
        $db->prepare("
            INSERT INTO live_chat_messages (chat_session_id,actor_type,admin_user_id,message)
            VALUES (:chat_id,'system',:admin_id,'The support chat was closed.')
        ")->execute(['chat_id' => $chatId, 'admin_id' => $adminId]);
    } else {
        throw new DomainException('Unsupported chat action.');
    }
    $db->commit();
    hivenest_crm_chat_out(200, ['ok' => true, 'message' => 'Chat updated.']);
} catch (DomainException $e) {
    if ($db->inTransaction()) $db->rollBack();
    hivenest_crm_chat_out(409, ['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('CRM chat failed: ' . $e->getMessage());
    hivenest_crm_chat_out(500, ['ok' => false, 'error' => 'Chat request failed.']);
}
