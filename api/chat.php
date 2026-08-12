<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/customer_session.php';
hivenest_customer_session_configure();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function hivenest_chat_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function hivenest_chat_env(string $key, string $default = ''): string
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
function hivenest_chat_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
function hivenest_chat_token(): string
{
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}
function hivenest_chat_input(): array
{
    $input = json_decode((string)file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}
function hivenest_chat_customer(): array
{
    try {
        $status = hivenest_customer_session_status(false);
        return !empty($status['authenticated']) ? $status : [];
    } catch (Throwable) {
        return [];
    }
}
function hivenest_chat_authorized(PDO $db, string $uuid, string $token): array
{
    if ($uuid === '' || $token === '') return [];
    $stmt = $db->prepare('SELECT * FROM live_chat_sessions WHERE uuid=:uuid LIMIT 1');
    $stmt->execute(['uuid' => $uuid]);
    $chat = $stmt->fetch(PDO::FETCH_ASSOC);
    return $chat && hash_equals((string)$chat['access_token_hash'], hash('sha256', $token)) ? $chat : [];
}

$db = hivenest_db();
if (!$db) hivenest_chat_out(503, ['ok' => false, 'error' => 'Chat service is unavailable.']);
$table = $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='live_chat_sessions'");
if ((int)$table->fetchColumn() !== 1) hivenest_chat_out(503, ['ok' => false, 'error' => 'Chat service is not installed.']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $uuid = trim((string)($_GET['session'] ?? ''));
    $token = trim((string)($_SERVER['HTTP_X_CHAT_TOKEN'] ?? ''));
    $chat = hivenest_chat_authorized($db, $uuid, $token);
    if (!$chat) hivenest_chat_out(401, ['ok' => false, 'error' => 'Chat authorization failed.']);
    $after = max(0, (int)($_GET['after'] ?? 0));
    $messages = $db->prepare("
        SELECT m.id,m.actor_type,m.message,m.created_at,a.username AS agent_name
        FROM live_chat_messages m
        LEFT JOIN admin_users a ON a.id=m.admin_user_id
        WHERE m.chat_session_id=:chat_id AND m.id>:after_id
        ORDER BY m.id ASC LIMIT 200
    ");
    $messages->execute(['chat_id' => (int)$chat['id'], 'after_id' => $after]);
    hivenest_chat_out(200, [
        'ok' => true,
        'chat' => [
            'session' => (string)$chat['uuid'],
            'status' => (string)$chat['status'],
            'waiting_since' => $chat['waiting_since'],
            'accepted_at' => $chat['accepted_at'],
            'assigned' => $chat['assigned_admin_id'] !== null,
        ],
        'messages' => $messages->fetchAll(PDO::FETCH_ASSOC) ?: [],
    ]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') hivenest_chat_out(405, ['ok' => false, 'error' => 'GET or POST required.']);
$input = hivenest_chat_input();
$action = strtolower(trim((string)($input['action'] ?? '')));

if ($action === 'start') {
    if (trim((string)($input['website_url'] ?? '')) !== '') hivenest_chat_out(200, ['ok' => true]);
    $customer = hivenest_chat_customer();
    $name = trim((string)($input['name'] ?? ''));
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $subject = strtolower(trim((string)($input['subject'] ?? 'general')));
    $message = trim((string)($input['message'] ?? ''));
    if ($name === '' || strlen($name) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || !in_array($subject, ['general','sales','support','billing'], true)
        || $message === '' || strlen($message) > 4000
    ) hivenest_chat_out(422, ['ok' => false, 'error' => 'Enter your name, email, subject and a message.']);
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $hashKey = hivenest_chat_env('RATE_LIMIT_SECRET', hivenest_chat_env('JWT_SECRET_KEY', 'hivenest-chat'));
    $ipHash = hash_hmac('sha256', $ip, $hashKey);
    $rate = $db->prepare("SELECT COUNT(*) FROM live_chat_sessions WHERE ip_hash=:ip_hash AND created_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR)");
    $rate->execute(['ip_hash' => $ipHash]);
    if ((int)$rate->fetchColumn() >= 5) hivenest_chat_out(429, ['ok' => false, 'error' => 'Too many chat requests. Please try again later.']);
    $uuid = hivenest_chat_uuid();
    $token = hivenest_chat_token();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            INSERT INTO live_chat_sessions
                (uuid,access_token_hash,customer_id,visitor_name,visitor_email,subject,ip_hash,page_url)
            VALUES (:uuid,:token_hash,:customer_id,:name,:email,:subject,:ip_hash,:page_url)
        ");
        $stmt->execute([
            'uuid' => $uuid,
            'token_hash' => hash('sha256', $token),
            'customer_id' => !empty($customer['customer_id']) ? (int)$customer['customer_id'] : null,
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'ip_hash' => $ipHash,
            'page_url' => substr(trim((string)($input['page_url'] ?? '')), 0, 1000) ?: null,
        ]);
        $chatId = (int)$db->lastInsertId();
        $actor = !empty($customer['customer_id']) ? 'customer' : 'visitor';
        $msg = $db->prepare("
            INSERT INTO live_chat_messages (chat_session_id,actor_type,customer_id,message)
            VALUES (:chat_id,:actor,:customer_id,:message)
        ");
        $msg->execute([
            'chat_id' => $chatId,
            'actor' => $actor,
            'customer_id' => !empty($customer['customer_id']) ? (int)$customer['customer_id'] : null,
            'message' => $message,
        ]);
        $db->commit();
        hivenest_chat_out(201, ['ok' => true, 'session' => $uuid, 'token' => $token, 'status' => 'waiting']);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

$uuid = trim((string)($input['session'] ?? ''));
$token = trim((string)($_SERVER['HTTP_X_CHAT_TOKEN'] ?? ''));
$chat = hivenest_chat_authorized($db, $uuid, $token);
if (!$chat) hivenest_chat_out(401, ['ok' => false, 'error' => 'Chat authorization failed.']);
if ($action === 'message') {
    $message = trim((string)($input['message'] ?? ''));
    if (!in_array((string)$chat['status'], ['waiting','active'], true)) hivenest_chat_out(409, ['ok' => false, 'error' => 'This chat is closed.']);
    if ($message === '' || strlen($message) > 4000) hivenest_chat_out(422, ['ok' => false, 'error' => 'Message must be between 1 and 4000 characters.']);
    $customer = hivenest_chat_customer();
    $actor = !empty($chat['customer_id']) ? 'customer' : 'visitor';
    $stmt = $db->prepare("
        INSERT INTO live_chat_messages (chat_session_id,actor_type,customer_id,message)
        VALUES (:chat_id,:actor,:customer_id,:message)
    ");
    $stmt->execute([
        'chat_id' => (int)$chat['id'],
        'actor' => $actor,
        'customer_id' => $chat['customer_id'],
        'message' => $message,
    ]);
    $db->prepare('UPDATE live_chat_sessions SET last_message_at=NOW() WHERE id=:id')->execute(['id' => (int)$chat['id']]);
    hivenest_chat_out(200, ['ok' => true, 'message_id' => (int)$db->lastInsertId()]);
}
if ($action === 'close') {
    $db->prepare("
        UPDATE live_chat_sessions SET status='closed',closed_at=NOW()
        WHERE id=:id AND status IN ('waiting','active')
    ")->execute(['id' => (int)$chat['id']]);
    hivenest_chat_out(200, ['ok' => true, 'status' => 'closed']);
}
hivenest_chat_out(422, ['ok' => false, 'error' => 'Unsupported chat action.']);
