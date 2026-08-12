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

function hivenest_crm_notices_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function hivenest_crm_notices_env(string $key, string $default = ''): string
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
function hivenest_crm_notices_b64(string $value): string|false
{
    $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
    return base64_decode(strtr($value, '-_', '+/'), true);
}
function hivenest_crm_notices_admin(PDO $db): array
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    $token = preg_match('/Bearer\s+(.+)/i', $header, $match) ? trim($match[1]) : '';
    if ($token !== '') {
        $parts = explode('.', $token);
        if (count($parts) === 3) {
            [$head, $body, $signature] = $parts;
            $headJson = hivenest_crm_notices_b64($head);
            $bodyJson = hivenest_crm_notices_b64($body);
            $jwtHead = $headJson === false ? null : json_decode($headJson, true);
            $payload = $bodyJson === false ? null : json_decode($bodyJson, true);
            $secret = hivenest_crm_notices_env('JWT_SECRET_KEY');
            $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $head . '.' . $body, $secret, true)), '+/', '-_'), '=');
            if (is_array($jwtHead) && is_array($payload) && $secret !== ''
                && ($jwtHead['alg'] ?? '') === hivenest_crm_notices_env('JWT_ALGORITHM', 'HS256')
                && ($payload['user_type'] ?? '') === 'admin'
                && (empty($payload['exp']) || (int)$payload['exp'] >= time())
                && hash_equals($expected, $signature)
            ) {
                $stmt = $db->prepare('SELECT id,username,email,role FROM admin_users WHERE id=:id AND is_active=1 LIMIT 1');
                $stmt->execute(['id' => (int)($payload['sub'] ?? 0)]);
                return $stmt->fetch() ?: [];
            }
        }
    }
    return !empty($_SESSION['admin_user']) && !empty($_SESSION['admin_login_time'])
        ? (array)$_SESSION['admin_user']
        : [];
}
function hivenest_crm_notices_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
function hivenest_crm_notices_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

$db = hivenest_db();
if (!$db) hivenest_crm_notices_out(503, ['ok' => false, 'error' => 'Database unavailable.']);
$admin = hivenest_crm_notices_admin($db);
if (!$admin) hivenest_crm_notices_out(401, ['ok' => false, 'error' => 'Administrator authentication required.']);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->query("
            SELECT n.*, CONCAT(COALESCE(a.first_name,''),' ',COALESCE(a.last_name,'')) AS created_by_name,
                   c.email AS customer_email, s.service_name
            FROM service_notices n
            LEFT JOIN admin_users a ON a.id=n.created_by
            LEFT JOIN customers c ON c.id=n.customer_id
            LEFT JOIN services s ON s.id=n.service_id
            ORDER BY n.created_at DESC, n.id DESC
            LIMIT 200
        ");
        hivenest_crm_notices_out(200, ['ok' => true, 'notices' => $stmt->fetchAll() ?: []]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        hivenest_crm_notices_out(405, ['ok' => false, 'error' => 'GET or POST required.']);
    }
    $authorization = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/Bearer\s+.+/i', $authorization)) {
        hivenest_crm_notices_out(403, ['ok' => false, 'error' => 'Bearer authentication is required for notice changes.']);
    }
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) hivenest_crm_notices_out(400, ['ok' => false, 'error' => 'Invalid JSON input.']);
    $action = strtolower(trim((string)($input['action'] ?? 'save')));
    $noticeId = (int)($input['notice_id'] ?? 0);
    if (!hivenest_crm_role_allows($admin, 'notice.draft')) {
        hivenest_crm_notices_out(403, ['ok' => false, 'error' => 'Your staff role cannot change notices.']);
    }

    if (in_array($action, ['publish','resolve','archive'], true)) {
        if (!hivenest_crm_role_allows($admin, 'notice.publish')) {
            hivenest_crm_notices_out(403, ['ok' => false, 'error' => 'Only administrators may publish, resolve or archive notices.']);
        }
        if ($noticeId <= 0) hivenest_crm_notices_out(422, ['ok' => false, 'error' => 'Valid notice_id required.']);
        $status = $action === 'publish' ? 'published' : ($action === 'resolve' ? 'resolved' : 'archived');
        $allowedCurrent = $action === 'publish'
            ? ['draft']
            : ($action === 'resolve' ? ['published'] : ['published', 'resolved']);
        $timeColumn = $action === 'publish' ? ', published_at=COALESCE(published_at,NOW())'
            : ($action === 'resolve' ? ', resolved_at=NOW()' : '');
        $currentPlaceholders = implode(',', array_fill(0, count($allowedCurrent), '?'));
        $stmt = $db->prepare(
            "UPDATE service_notices SET status=?, updated_by=? {$timeColumn}
             WHERE id=? AND status IN ({$currentPlaceholders})"
        );
        $stmt->execute([$status, (int)$admin['id'], $noticeId, ...$allowedCurrent]);
        if ($stmt->rowCount() !== 1) {
            hivenest_crm_notices_out(409, ['ok' => false, 'error' => 'Notice does not exist or is not in a valid state for this action.']);
        }
        hivenest_crm_notices_out(200, ['ok' => true, 'message' => 'Notice marked ' . $status . '.', 'updated' => 1]);
    }
    if (!in_array($action, ['save','create','update'], true)) {
        hivenest_crm_notices_out(422, ['ok' => false, 'error' => 'Unsupported action.']);
    }

    $type = strtolower(trim((string)($input['notice_type'] ?? 'announcement')));
    $severity = strtolower(trim((string)($input['severity'] ?? 'info')));
    $audience = strtolower(trim((string)($input['audience_type'] ?? 'all')));
    $title = trim((string)($input['title'] ?? ''));
    $message = trim((string)($input['message'] ?? ''));
    if (!in_array($type, ['announcement','maintenance','incident'], true)
        || !in_array($severity, ['info','warning','critical'], true)
        || !in_array($audience, ['all','customer','service','service_type'], true)
        || $title === '' || hivenest_crm_notices_length($title) > 180
        || $message === '' || hivenest_crm_notices_length($message) > 10000
    ) hivenest_crm_notices_out(422, ['ok' => false, 'error' => 'Complete the notice with valid type, severity, audience, title and message.']);

    $customerId = $audience === 'customer' ? (int)($input['customer_id'] ?? 0) : null;
    $serviceId = $audience === 'service' ? (int)($input['service_id'] ?? 0) : null;
    $serviceType = $audience === 'service_type' ? strtolower(trim((string)($input['affected_service_type'] ?? ''))) : null;
    if (($audience === 'customer' && !$customerId)
        || ($audience === 'service' && !$serviceId)
        || ($audience === 'service_type' && !in_array($serviceType, ['domain','hosting','email','ssl','design','marketing','security','backup'], true))
    ) hivenest_crm_notices_out(422, ['ok' => false, 'error' => 'The selected audience requires a valid target.']);
    if ($customerId) {
        $target = $db->prepare('SELECT COUNT(*) FROM customers WHERE id=:id');
        $target->execute(['id' => $customerId]);
        if ((int)$target->fetchColumn() !== 1) {
            hivenest_crm_notices_out(422, ['ok' => false, 'error' => 'The selected customer does not exist.']);
        }
    }
    if ($serviceId) {
        $target = $db->prepare('SELECT COUNT(*) FROM services WHERE id=:id');
        $target->execute(['id' => $serviceId]);
        if ((int)$target->fetchColumn() !== 1) {
            hivenest_crm_notices_out(422, ['ok' => false, 'error' => 'The selected service does not exist.']);
        }
    }

    $startsAt = trim((string)($input['starts_at'] ?? '')) ?: null;
    $endsAt = trim((string)($input['ends_at'] ?? '')) ?: null;
    if ($startsAt && $endsAt && strtotime($endsAt) <= strtotime($startsAt)) {
        hivenest_crm_notices_out(422, ['ok' => false, 'error' => 'End time must be after the start time.']);
    }
    $params = [
        'type' => $type, 'severity' => $severity, 'title' => $title, 'message' => $message,
        'audience' => $audience, 'customer_id' => $customerId, 'service_id' => $serviceId,
        'service_type' => $serviceType, 'starts_at' => $startsAt, 'ends_at' => $endsAt,
        'admin_id' => (int)$admin['id'],
    ];
    if ($noticeId > 0) {
        $stmt = $db->prepare("
            UPDATE service_notices SET notice_type=:type,severity=:severity,title=:title,message=:message,
                audience_type=:audience,customer_id=:customer_id,service_id=:service_id,
                affected_service_type=:service_type,starts_at=:starts_at,ends_at=:ends_at,updated_by=:admin_id
            WHERE id=:id AND status='draft'
        ");
        $stmt->execute($params + ['id' => $noticeId]);
        if ($stmt->rowCount() === 0) hivenest_crm_notices_out(409, ['ok' => false, 'error' => 'Only draft notices can be edited.']);
    } else {
        $stmt = $db->prepare("
            INSERT INTO service_notices
                (uuid,notice_type,severity,title,message,status,audience_type,customer_id,service_id,
                 affected_service_type,starts_at,ends_at,created_by,updated_by)
            VALUES (:uuid,:type,:severity,:title,:message,'draft',:audience,:customer_id,:service_id,
                    :service_type,:starts_at,:ends_at,:admin_id,:admin_id)
        ");
        $stmt->execute(['uuid' => hivenest_crm_notices_uuid()] + $params);
        $noticeId = (int)$db->lastInsertId();
    }
    hivenest_crm_notices_out(200, ['ok' => true, 'notice_id' => $noticeId, 'message' => 'Draft notice saved.']);
} catch (Throwable $e) {
    error_log('CRM notices failed: ' . $e->getMessage());
    hivenest_crm_notices_out(500, ['ok' => false, 'error' => 'Notice request failed.']);
}
