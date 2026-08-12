<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');

if (session_status() === PHP_SESSION_NONE) {
    session_name('HIVENEST_ADMIN');
    session_start();
}

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/crm_permissions.php';
require_once __DIR__ . '/../utilities/service_credentials.php';
require_once __DIR__ . '/../utilities/rate_limiter.php';

function hivenest_crm_credentials_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_crm_credentials_b64url_decode(string $value): string|false
{
    $remainder = strlen($value) % 4;
    if ($remainder) $value .= str_repeat('=', 4 - $remainder);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function hivenest_crm_credentials_bearer(): string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    return preg_match('/Bearer\s+(.+)/i', $header, $match) ? trim($match[1]) : '';
}

function hivenest_crm_credentials_admin(PDO $db): array
{
    $token = hivenest_crm_credentials_bearer();
    if ($token === '') return [];
    $parts = explode('.', $token);
    if (count($parts) !== 3) return [];
    [$header64, $payload64, $signature64] = $parts;
    $header = json_decode((string)hivenest_crm_credentials_b64url_decode($header64), true);
    $payload = json_decode((string)hivenest_crm_credentials_b64url_decode($payload64), true);
    if (!is_array($header) || !is_array($payload) || ($payload['user_type'] ?? '') !== 'admin') return [];
    if (($header['alg'] ?? '') !== (hivenest_service_credentials_env('JWT_ALGORITHM') ?: 'HS256')) return [];
    if (!empty($payload['exp']) && (int)$payload['exp'] < time()) return [];
    $secret = hivenest_service_credentials_env('JWT_SECRET_KEY');
    if ($secret === '') return [];
    $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $header64 . '.' . $payload64, $secret, true)), '+/', '-_'), '=');
    if (!hash_equals($expected, $signature64)) return [];
    return hivenest_crm_admin_record($db, (int)($payload['sub'] ?? 0));
}

function hivenest_crm_credentials_table_exists(PDO $db, string $table): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name'
    );
    $stmt->execute(['table_name' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function hivenest_crm_credentials_clean(?string $value, int $max): ?string
{
    if ($value === null) return null;
    $value = trim(str_replace("\0", '', $value));
    if ($value === '') return null;
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

$db = hivenest_db();
if (!$db) hivenest_crm_credentials_out(503, ['error' => 'CRM database is unavailable.']);
$admin = hivenest_crm_credentials_admin($db);
if (!$admin) hivenest_crm_credentials_out(401, ['error' => 'A valid CRM bearer token is required.']);
if (
    !hivenest_crm_credentials_table_exists($db, 'service_credentials')
    || !hivenest_crm_credentials_table_exists($db, 'service_credential_access_audit')
) {
    hivenest_crm_credentials_out(503, ['error' => 'The encrypted credential vault has not been installed.']);
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$input = $method === 'POST'
    ? (json_decode((string)file_get_contents('php://input'), true) ?: [])
    : [];
$serviceId = (int)($input['service_id'] ?? $_GET['service_id'] ?? 0);
if ($serviceId <= 0) hivenest_crm_credentials_out(422, ['error' => 'Service is required.']);
$serviceStmt = $db->prepare('SELECT id,customer_id FROM services WHERE id=:id LIMIT 1');
$serviceStmt->execute(['id' => $serviceId]);
$service = $serviceStmt->fetch(PDO::FETCH_ASSOC);
if (!$service) hivenest_crm_credentials_out(404, ['error' => 'Service not found.']);
$customerId = (int)$service['customer_id'];

if ($method === 'GET') {
    $stmt = $db->prepare(
        'SELECT id,uuid,service_id,customer_id,credential_type,label,username,login_url,
                metadata_json,status,expires_at,rotated_at,created_at,updated_at
         FROM service_credentials WHERE service_id=:service_id
         ORDER BY status,credential_type,label,id'
    );
    $stmt->execute(['service_id' => $serviceId]);
    hivenest_crm_credentials_out(200, [
        'credentials' => array_map('hivenest_service_credentials_metadata', $stmt->fetchAll() ?: []),
    ]);
}
if ($method !== 'POST') hivenest_crm_credentials_out(405, ['error' => 'Method not allowed.']);

$action = strtolower(trim((string)($input['action'] ?? '')));
$credentialId = (int)($input['credential_id'] ?? 0);
$adminId = (int)$admin['id'];

if ($action === 'reveal') {
    if (!hivenest_crm_role_allows($admin, 'credential.reveal')) {
        hivenest_crm_credentials_out(403, ['error' => 'Your staff role cannot reveal service credentials.']);
    }
    $limit = hivenest_rate_limit('crm-credential-reveal', 20, 600, 'admin:' . $adminId);
    if (!$limit['allowed']) {
        header('Retry-After: ' . $limit['retry_after']);
        hivenest_crm_credentials_out(429, ['error' => 'Too many credential reveal attempts. Try again later.']);
    }
    $stmt = $db->prepare(
        'SELECT * FROM service_credentials
         WHERE id=:id AND service_id=:service_id AND status="active"
           AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1'
    );
    $stmt->execute(['id' => $credentialId, 'service_id' => $serviceId]);
    $credential = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$credential) hivenest_crm_credentials_out(404, ['error' => 'Active credential not found.']);
    try {
        $secret = hivenest_service_credentials_decrypt((string)$credential['secret_ciphertext']);
        hivenest_service_credentials_audit($db, $credentialId, $serviceId, $customerId, 'admin', $adminId, 'revealed');
        hivenest_crm_credentials_out(200, [
            'credential' => hivenest_service_credentials_metadata($credential),
            'secret' => $secret,
        ]);
    } catch (Throwable $e) {
        error_log('CRM credential reveal failed: ' . $e->getMessage());
        hivenest_crm_credentials_out(503, ['error' => 'Credential could not be unlocked.']);
    }
}

if (!hivenest_crm_role_allows($admin, 'credential.manage')) {
    hivenest_crm_credentials_out(403, ['error' => 'Your staff role cannot manage service credentials.']);
}

if ($action === 'revoke') {
    $update = $db->prepare(
        'UPDATE service_credentials SET status="revoked",updated_at=NOW()
         WHERE id=:id AND service_id=:service_id AND status="active"'
    );
    $update->execute(['id' => $credentialId, 'service_id' => $serviceId]);
    if ($update->rowCount() !== 1) hivenest_crm_credentials_out(404, ['error' => 'Active credential not found.']);
    hivenest_service_credentials_audit($db, $credentialId, $serviceId, $customerId, 'admin', $adminId, 'revoked');
    hivenest_crm_credentials_out(200, ['message' => 'Credential revoked.']);
}

$allowedTypes = ['control_panel','ftp','ssh','database','email','api','other'];
$type = strtolower(trim((string)($input['credential_type'] ?? 'other')));
if (!in_array($type, $allowedTypes, true)) hivenest_crm_credentials_out(422, ['error' => 'Invalid credential type.']);
$label = hivenest_crm_credentials_clean((string)($input['label'] ?? ''), 100);
$secret = (string)($input['secret'] ?? '');
if ($label === null || $secret === '') hivenest_crm_credentials_out(422, ['error' => 'Label and secret are required.']);
$username = hivenest_crm_credentials_clean(isset($input['username']) ? (string)$input['username'] : null, 255);
$loginUrl = hivenest_crm_credentials_clean(isset($input['login_url']) ? (string)$input['login_url'] : null, 500);
if ($loginUrl !== null && filter_var($loginUrl, FILTER_VALIDATE_URL) === false) {
    hivenest_crm_credentials_out(422, ['error' => 'Login URL is invalid.']);
}
$metadata = is_array($input['metadata'] ?? null) ? $input['metadata'] : [];

try {
    $cipherText = hivenest_service_credentials_encrypt($secret);
    if ($action === 'create') {
        $insert = $db->prepare(
            'INSERT INTO service_credentials
                (uuid,service_id,customer_id,credential_type,label,username,secret_ciphertext,
                 login_url,metadata_json,created_by_admin_id)
             VALUES (:uuid,:service_id,:customer_id,:type,:label,:username,:ciphertext,
                     :login_url,:metadata,:admin_id)'
        );
        $insert->execute([
            'uuid' => hivenest_service_credentials_uuid(),
            'service_id' => $serviceId,
            'customer_id' => $customerId,
            'type' => $type,
            'label' => $label,
            'username' => $username,
            'ciphertext' => $cipherText,
            'login_url' => $loginUrl,
            'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES) : null,
            'admin_id' => $adminId,
        ]);
        $credentialId = (int)$db->lastInsertId();
        hivenest_service_credentials_audit($db, $credentialId, $serviceId, $customerId, 'admin', $adminId, 'created');
        hivenest_crm_credentials_out(201, ['id' => $credentialId, 'message' => 'Credential encrypted and stored.']);
    }
    if ($action === 'rotate') {
        $update = $db->prepare(
            'UPDATE service_credentials
             SET credential_type=:type,label=:label,username=:username,secret_ciphertext=:ciphertext,
                 login_url=:login_url,metadata_json=:metadata,status="active",rotated_at=NOW()
             WHERE id=:id AND service_id=:service_id'
        );
        $update->execute([
            'type' => $type,
            'label' => $label,
            'username' => $username,
            'ciphertext' => $cipherText,
            'login_url' => $loginUrl,
            'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES) : null,
            'id' => $credentialId,
            'service_id' => $serviceId,
        ]);
        if ($update->rowCount() !== 1) hivenest_crm_credentials_out(404, ['error' => 'Credential not found.']);
        hivenest_service_credentials_audit($db, $credentialId, $serviceId, $customerId, 'admin', $adminId, 'rotated');
        hivenest_crm_credentials_out(200, ['message' => 'Credential rotated.']);
    }
    hivenest_crm_credentials_out(422, ['error' => 'Unknown credential action.']);
} catch (Throwable $e) {
    error_log('CRM credential write failed: ' . $e->getMessage());
    hivenest_crm_credentials_out(503, ['error' => 'Credential vault operation failed.']);
}
