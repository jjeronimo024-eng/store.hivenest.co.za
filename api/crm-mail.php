<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/crm_permissions.php';

function hivenest_crm_mail_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function hivenest_crm_mail_env(string $key, string $default = ''): string
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
function hivenest_crm_mail_decode(string $value): string|false
{
    $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
    return base64_decode(strtr($value, '-_', '+/'), true);
}
function hivenest_crm_mail_admin(PDO $db): array
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    $token = preg_match('/Bearer\s+(.+)/i', $header, $match) ? trim($match[1]) : '';
    $parts = explode('.', $token);
    if ($token === '' || count($parts) !== 3) return [];
    [$head, $body, $signature] = $parts;
    $headJson = hivenest_crm_mail_decode($head);
    $bodyJson = hivenest_crm_mail_decode($body);
    $jwtHead = $headJson === false ? null : json_decode($headJson, true);
    $payload = $bodyJson === false ? null : json_decode($bodyJson, true);
    $secret = hivenest_crm_mail_env('JWT_SECRET_KEY');
    $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $head . '.' . $body, $secret, true)), '+/', '-_'), '=');
    if (!is_array($jwtHead) || !is_array($payload) || $secret === ''
        || ($jwtHead['alg'] ?? '') !== hivenest_crm_mail_env('JWT_ALGORITHM', 'HS256')
        || ($payload['user_type'] ?? '') !== 'admin'
        || (!empty($payload['exp']) && (int)$payload['exp'] < time())
        || !hash_equals($expected, $signature)
    ) return [];
    return hivenest_crm_admin_record($db, (int)($payload['sub'] ?? 0));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') hivenest_crm_mail_out(405, ['ok' => false, 'error' => 'POST required.']);
$db = hivenest_db();
if (!$db) hivenest_crm_mail_out(503, ['ok' => false, 'error' => 'CRM database is unavailable.']);
$admin = hivenest_crm_mail_admin($db);
if (!$admin) hivenest_crm_mail_out(401, ['ok' => false, 'error' => 'Bearer administrator authentication required.']);
if (!hivenest_crm_role_allows($admin, 'mail.retry')) {
    hivenest_crm_mail_out(403, ['ok' => false, 'error' => 'Only administrators may retry failed email.']);
}
$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input) || strtolower(trim((string)($input['action'] ?? ''))) !== 'retry') {
    hivenest_crm_mail_out(422, ['ok' => false, 'error' => 'Supported action: retry.']);
}
$mailId = (int)($input['mail_id'] ?? 0);
if ($mailId <= 0) hivenest_crm_mail_out(422, ['ok' => false, 'error' => 'Valid mail_id required.']);
$stmt = $db->prepare("
    UPDATE outbound_mail_queue
    SET status='pending',attempts=0,next_attempt_at=NOW(),locked_at=NULL,last_error=NULL,
        manual_retry_count=manual_retry_count+1,last_retried_by=:admin_id,last_retried_at=NOW()
    WHERE id=:id AND status='failed'
");
$stmt->execute(['admin_id' => (int)$admin['id'], 'id' => $mailId]);
if ($stmt->rowCount() !== 1) {
    hivenest_crm_mail_out(409, ['ok' => false, 'error' => 'Only a terminal failed email can be manually retried.']);
}
hivenest_crm_mail_out(200, ['ok' => true, 'message' => 'Email queued for another delivery attempt.']);
