<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/crm_permissions.php';
require_once __DIR__ . '/../utilities/email_templates.php';

function hivenest_crm_templates_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function hivenest_crm_templates_env(string $key, string $default = ''): string
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

function hivenest_crm_templates_decode(string $value): string|false
{
    $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function hivenest_crm_templates_admin(PDO $db): array
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    $token = preg_match('/Bearer\s+(.+)/i', $header, $match) ? trim($match[1]) : '';
    $parts = explode('.', $token);
    if ($token === '' || count($parts) !== 3) return [];
    [$head, $body, $signature] = $parts;
    $headJson = hivenest_crm_templates_decode($head);
    $bodyJson = hivenest_crm_templates_decode($body);
    $jwtHead = $headJson === false ? null : json_decode($headJson, true);
    $payload = $bodyJson === false ? null : json_decode($bodyJson, true);
    $secret = hivenest_crm_templates_env('JWT_SECRET_KEY');
    $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $head . '.' . $body, $secret, true)), '+/', '-_'), '=');
    if (!is_array($jwtHead) || !is_array($payload) || $secret === ''
        || ($jwtHead['alg'] ?? '') !== hivenest_crm_templates_env('JWT_ALGORITHM', 'HS256')
        || ($payload['user_type'] ?? '') !== 'admin'
        || (!empty($payload['exp']) && (int)$payload['exp'] < time())
        || !hash_equals($expected, $signature)
    ) return [];
    return hivenest_crm_admin_record($db, (int)($payload['sub'] ?? 0));
}

function hivenest_crm_template_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$db = hivenest_db();
if (!$db) hivenest_crm_templates_out(503, ['ok' => false, 'error' => 'CRM database is unavailable.']);
$admin = hivenest_crm_templates_admin($db);
if (!$admin) hivenest_crm_templates_out(401, ['ok' => false, 'error' => 'Bearer administrator authentication required.']);
if (!hivenest_email_templates_ready($db)) {
    hivenest_crm_templates_out(503, ['ok' => false, 'error' => 'Email template schema is not installed.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $key = strtolower(trim((string)($_GET['template_key'] ?? '')));
    $sql = "
        SELECT et.id,et.uuid,et.template_key,et.version,et.display_name,
               et.subject_template,et.body_template,et.content_type,
               et.allowed_variables,et.is_active,et.created_at,
               COALESCE(a.username,'system') AS created_by
        FROM email_templates et
        LEFT JOIN admin_users a ON a.id=et.created_by_admin_id
    ";
    $params = [];
    if ($key !== '') {
        if (!preg_match('/^[a-z][a-z0-9_]{2,99}$/', $key)) {
            hivenest_crm_templates_out(422, ['ok' => false, 'error' => 'Invalid template_key.']);
        }
        $sql .= ' WHERE et.template_key=:template_key';
        $params['template_key'] = $key;
    }
    $sql .= ' ORDER BY et.template_key ASC,et.version DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['version'] = (int)$row['version'];
        $row['is_active'] = (bool)$row['is_active'];
        $decoded = json_decode((string)($row['allowed_variables'] ?? '[]'), true);
        $row['allowed_variables'] = is_array($decoded) ? array_values($decoded) : [];
    }
    unset($row);
    hivenest_crm_templates_out(200, [
        'ok' => true,
        'templates' => $rows,
        'can_manage' => hivenest_crm_role_allows($admin, 'mail.template.manage'),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hivenest_crm_templates_out(405, ['ok' => false, 'error' => 'GET or POST required.']);
}
if (!hivenest_crm_role_allows($admin, 'mail.template.manage')) {
    hivenest_crm_templates_out(403, ['ok' => false, 'error' => 'Only administrators may publish email templates.']);
}
$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input) || strtolower(trim((string)($input['action'] ?? ''))) !== 'publish') {
    hivenest_crm_templates_out(422, ['ok' => false, 'error' => 'Supported action: publish.']);
}

$key = strtolower(trim((string)($input['template_key'] ?? '')));
$name = trim((string)($input['display_name'] ?? ''));
$subject = trim((string)($input['subject_template'] ?? ''));
$body = (string)($input['body_template'] ?? '');
$contentType = strtolower(trim((string)($input['content_type'] ?? 'text/plain')));
$variablesInput = $input['allowed_variables'] ?? [];
if (!preg_match('/^[a-z][a-z0-9_]{2,99}$/', $key)) {
    hivenest_crm_templates_out(422, ['ok' => false, 'error' => 'template_key must use lowercase letters, numbers and underscores.']);
}
if ($name === '' || strlen($name) > 150 || $subject === '' || strlen($subject) > 255) {
    hivenest_crm_templates_out(422, ['ok' => false, 'error' => 'A valid display name and subject are required.']);
}
if ($body === '' || strlen($body) > 200000 || !in_array($contentType, ['text/plain', 'text/html'], true)) {
    hivenest_crm_templates_out(422, ['ok' => false, 'error' => 'A valid body and content type are required.']);
}
if (!is_array($variablesInput)) {
    hivenest_crm_templates_out(422, ['ok' => false, 'error' => 'allowed_variables must be an array.']);
}
$allowed = [];
foreach ($variablesInput as $variable) {
    $variable = strtolower(trim((string)$variable));
    if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/', $variable)) {
        hivenest_crm_templates_out(422, ['ok' => false, 'error' => 'Each allowed variable must be a safe token name.']);
    }
    $allowed[] = $variable;
}
$allowed = array_values(array_unique($allowed));
$used = array_values(array_unique(array_merge(
    hivenest_email_template_tokens($subject),
    hivenest_email_template_tokens($body)
)));
$unknown = array_values(array_diff($used, $allowed));
if ($unknown) {
    hivenest_crm_templates_out(422, ['ok' => false, 'error' => 'Template uses variables that are not allowed: ' . implode(', ', $unknown)]);
}
if ($contentType === 'text/html'
    && preg_match('/<(script|iframe|object|embed|form|base|meta)\b|(?:on[a-z]+\s*=)|javascript\s*:/i', $body)
) {
    hivenest_crm_templates_out(422, ['ok' => false, 'error' => 'The HTML template contains an unsafe element or attribute.']);
}

try {
    $db->beginTransaction();
    $lock = $db->prepare('SELECT version FROM email_templates WHERE template_key=:template_key ORDER BY version DESC LIMIT 1 FOR UPDATE');
    $lock->execute(['template_key' => $key]);
    $latestVersion = $lock->fetchColumn();
    $version = ($latestVersion === false ? 0 : (int)$latestVersion) + 1;
    $db->prepare('UPDATE email_templates SET is_active=0 WHERE template_key=:template_key AND is_active=1')
        ->execute(['template_key' => $key]);
    $insert = $db->prepare("
        INSERT INTO email_templates
          (uuid,template_key,version,display_name,subject_template,body_template,content_type,allowed_variables,is_active,created_by_admin_id)
        VALUES
          (:uuid,:template_key,:version,:display_name,:subject_template,:body_template,:content_type,:allowed_variables,1,:admin_id)
    ");
    $insert->execute([
        'uuid' => hivenest_crm_template_uuid(),
        'template_key' => $key,
        'version' => $version,
        'display_name' => $name,
        'subject_template' => $subject,
        'body_template' => $body,
        'content_type' => $contentType,
        'allowed_variables' => json_encode($allowed, JSON_UNESCAPED_SLASHES),
        'admin_id' => (int)$admin['id'],
    ]);
    $db->commit();
    hivenest_crm_templates_out(201, [
        'ok' => true,
        'message' => 'Email template version published.',
        'template_key' => $key,
        'version' => $version,
    ]);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('CRM email template publish failed: ' . $e->getMessage());
    hivenest_crm_templates_out(503, ['ok' => false, 'error' => 'The email template could not be published.']);
}
