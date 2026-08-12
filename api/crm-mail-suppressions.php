<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/crm_permissions.php';
require_once __DIR__ . '/../utilities/mail_delivery.php';
require_once __DIR__ . '/../utilities/mail_suppression.php';

function hivenest_crm_suppressions_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function hivenest_crm_suppressions_decode(string $value): string|false
{
    $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function hivenest_crm_suppressions_admin(PDO $db): array
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    $token = preg_match('/Bearer\s+(.+)/i', $header, $match) ? trim($match[1]) : '';
    $parts = explode('.', $token);
    if ($token === '' || count($parts) !== 3) return [];
    [$head, $body, $signature] = $parts;
    $headJson = hivenest_crm_suppressions_decode($head);
    $bodyJson = hivenest_crm_suppressions_decode($body);
    $jwtHead = $headJson === false ? null : json_decode($headJson, true);
    $payload = $bodyJson === false ? null : json_decode($bodyJson, true);
    $secret = hivenest_mail_env('JWT_SECRET_KEY');
    $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $head . '.' . $body, $secret, true)), '+/', '-_'), '=');
    if (!is_array($jwtHead) || !is_array($payload) || $secret === ''
        || ($jwtHead['alg'] ?? '') !== hivenest_mail_env('JWT_ALGORITHM', 'HS256')
        || ($payload['user_type'] ?? '') !== 'admin'
        || (!empty($payload['exp']) && (int)$payload['exp'] < time())
        || !hash_equals($expected, $signature)
    ) return [];
    return hivenest_crm_admin_record($db, (int)($payload['sub'] ?? 0));
}

$db = hivenest_db();
if (!$db) hivenest_crm_suppressions_out(503, ['ok' => false, 'error' => 'CRM database is unavailable.']);
$admin = hivenest_crm_suppressions_admin($db);
if (!$admin) hivenest_crm_suppressions_out(401, ['ok' => false, 'error' => 'Bearer administrator authentication required.']);
if (!hivenest_mail_suppression_ready($db)) {
    hivenest_crm_suppressions_out(503, ['ok' => false, 'error' => 'Mail suppression schema is not installed.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $active = strtolower(trim((string)($_GET['active'] ?? 'all')));
    $search = strtolower(trim((string)($_GET['q'] ?? '')));
    $where = [];
    $params = [];
    if ($active === 'true' || $active === '1') $where[] = 'ms.is_active=1';
    if ($active === 'false' || $active === '0') $where[] = 'ms.is_active=0';
    if ($search !== '') {
        $where[] = 'ms.recipient_email LIKE :search';
        $params['search'] = '%' . substr($search, 0, 200) . '%';
    }
    $sql = "
        SELECT ms.id,ms.recipient_email,ms.suppression_type,ms.source,ms.reason,
               ms.source_event_id,ms.is_active,ms.first_suppressed_at,
               ms.last_suppressed_at,ms.released_at,ms.release_reason,
               a.username AS released_by
        FROM mail_suppressions ms
        LEFT JOIN admin_users a ON a.id=ms.released_by_admin_id
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY ms.is_active DESC,ms.last_suppressed_at DESC
        LIMIT 500
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['is_active'] = (bool)$row['is_active'];
    }
    unset($row);
    hivenest_crm_suppressions_out(200, [
        'ok' => true,
        'suppressions' => $rows,
        'can_manage' => hivenest_crm_role_allows($admin, 'mail.suppression.manage'),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hivenest_crm_suppressions_out(405, ['ok' => false, 'error' => 'GET or POST required.']);
}
if (!hivenest_crm_role_allows($admin, 'mail.suppression.manage')) {
    hivenest_crm_suppressions_out(403, ['ok' => false, 'error' => 'Only administrators may change mail suppressions.']);
}
$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) hivenest_crm_suppressions_out(400, ['ok' => false, 'error' => 'Valid JSON required.']);
$action = strtolower(trim((string)($input['action'] ?? '')));
$reason = trim((string)($input['reason'] ?? ''));
if (strlen($reason) < 10 || strlen($reason) > 500) {
    hivenest_crm_suppressions_out(422, ['ok' => false, 'error' => 'A reason between 10 and 500 characters is required.']);
}

if ($action === 'suppress') {
    $email = hivenest_mail_normalize_recipient((string)($input['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        hivenest_crm_suppressions_out(422, ['ok' => false, 'error' => 'A valid recipient email is required.']);
    }
    $stmt = $db->prepare("
        INSERT INTO mail_suppressions
          (recipient_email,recipient_hash,suppression_type,source,reason,is_active)
        VALUES
          (:email,:email_hash,'manual','crm',:reason,1)
        ON DUPLICATE KEY UPDATE
          suppression_type='manual',source='crm',reason=VALUES(reason),is_active=1,
          last_suppressed_at=NOW(),released_at=NULL,released_by_admin_id=NULL,release_reason=NULL
    ");
    $stmt->execute(['email' => $email, 'email_hash' => hash('sha256', $email), 'reason' => $reason]);
    $cancel = $db->prepare("
        UPDATE outbound_mail_queue
        SET status='suppressed',locked_at=NULL,last_error='Recipient manually suppressed by CRM administrator.'
        WHERE recipient_email=:email AND status IN ('pending','retry')
    ");
    $cancel->execute(['email' => $email]);
    hivenest_crm_suppressions_out(200, ['ok' => true, 'message' => 'Recipient suppressed.']);
}

if ($action === 'release') {
    $id = (int)($input['suppression_id'] ?? 0);
    $confirmation = hivenest_mail_normalize_recipient((string)($input['confirm_email'] ?? ''));
    $lock = $db->prepare('SELECT recipient_email,is_active FROM mail_suppressions WHERE id=:id LIMIT 1');
    $lock->execute(['id' => $id]);
    $row = $lock->fetch(PDO::FETCH_ASSOC);
    if (!$row || !(bool)$row['is_active']) {
        hivenest_crm_suppressions_out(409, ['ok' => false, 'error' => 'Only an active suppression can be released.']);
    }
    if (!hash_equals((string)$row['recipient_email'], $confirmation)) {
        hivenest_crm_suppressions_out(422, ['ok' => false, 'error' => 'The confirmation email does not match the suppressed recipient.']);
    }
    $stmt = $db->prepare("
        UPDATE mail_suppressions
        SET is_active=0,released_at=NOW(),released_by_admin_id=:admin_id,release_reason=:reason
        WHERE id=:id AND is_active=1
    ");
    $stmt->execute(['admin_id' => (int)$admin['id'], 'reason' => $reason, 'id' => $id]);
    hivenest_crm_suppressions_out(200, ['ok' => true, 'message' => 'Recipient suppression released. New mail may now be queued.']);
}

hivenest_crm_suppressions_out(422, ['ok' => false, 'error' => 'Supported actions: suppress or release.']);
