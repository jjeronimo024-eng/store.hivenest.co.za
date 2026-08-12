<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../access/dbconfig.php';

function hivenest_onboarding_file_json(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_onboarding_file_env(string $key, string $default = ''): string
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
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) $value = substr($value, 1, -1);
        }
        return $value;
    }
    return $default;
}

function hivenest_onboarding_file_b64url_decode(string $value): string|false
{
    $remainder = strlen($value) % 4;
    if ($remainder) $value .= str_repeat('=', 4 - $remainder);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function hivenest_onboarding_file_b64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function hivenest_onboarding_file_bearer_token(): string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(.+)/i', $header, $match)) return trim($match[1]);
    return trim((string)($_COOKIE['hivenest_admin_access_token'] ?? ''));
}

function hivenest_onboarding_file_is_admin(PDO $db): bool
{
    if (!empty($_SESSION['admin_user']) && !empty($_SESSION['admin_login_time'])) return true;
    $token = hivenest_onboarding_file_bearer_token();
    if ($token === '') return false;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    [$header64, $payload64, $signature64] = $parts;
    $headerJson = hivenest_onboarding_file_b64url_decode($header64);
    $payloadJson = hivenest_onboarding_file_b64url_decode($payload64);
    if ($headerJson === false || $payloadJson === false) return false;
    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);
    if (!is_array($header) || !is_array($payload)) return false;
    if (($payload['user_type'] ?? '') !== 'admin') return false;
    if (!empty($payload['exp']) && (int)$payload['exp'] < time()) return false;

    $secret = hivenest_onboarding_file_env('JWT_SECRET_KEY');
    if ($secret === '') return false;
    $expected = hivenest_onboarding_file_b64url_encode(hash_hmac('sha256', $header64 . '.' . $payload64, $secret, true));
    if (!hash_equals($expected, $signature64)) return false;

    $adminId = (int)($payload['sub'] ?? 0);
    if ($adminId <= 0) return false;
    $stmt = $db->prepare('SELECT id FROM admin_users WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => $adminId]);
    return (bool)$stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    hivenest_onboarding_file_json(405, ['error' => 'GET required.']);
}

$onboardingId = (int)($_GET['onboarding_id'] ?? $_GET['id'] ?? 0);
$storedName = basename((string)($_GET['file'] ?? ''));
if ($onboardingId <= 0 || $storedName === '') {
    hivenest_onboarding_file_json(422, ['error' => 'Onboarding file reference is required.']);
}

$db = hivenest_db();
if (!$db) hivenest_onboarding_file_json(503, ['error' => 'Database is unavailable.']);

$customerId = (int)($_SESSION['customer_id'] ?? 0);
$isAdmin = hivenest_onboarding_file_is_admin($db);
if ($customerId <= 0 && !$isAdmin) hivenest_onboarding_file_json(401, ['error' => 'Login required.']);

$stmt = $db->prepare('
    SELECT id, customer_id, uploaded_files
    FROM customer_service_onboarding
    WHERE id = :id
    LIMIT 1
');
$stmt->execute(['id' => $onboardingId]);
$onboarding = $stmt->fetch();
if (!$onboarding) hivenest_onboarding_file_json(404, ['error' => 'Onboarding submission not found.']);
if (!$isAdmin && (int)$onboarding['customer_id'] !== $customerId) {
    hivenest_onboarding_file_json(403, ['error' => 'Onboarding file access denied.']);
}

$files = json_decode((string)($onboarding['uploaded_files'] ?? ''), true);
if (!is_array($files)) $files = [];

$fileMeta = null;
foreach ($files as $file) {
    if (!is_array($file) || !empty($file['error'])) continue;
    if ((string)($file['stored_name'] ?? '') === $storedName) {
        $fileMeta = $file;
        break;
    }
}
if (!$fileMeta) hivenest_onboarding_file_json(404, ['error' => 'Onboarding file not found.']);

$root = realpath(__DIR__ . '/../uploads/onboarding');
if ($root === false) hivenest_onboarding_file_json(404, ['error' => 'Onboarding file storage not found.']);
$path = realpath($root . DIRECTORY_SEPARATOR . 'customer_' . (int)$onboarding['customer_id'] . DIRECTORY_SEPARATOR . $storedName);
if ($path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
    hivenest_onboarding_file_json(404, ['error' => 'Onboarding file missing from storage.']);
}

$original = basename((string)($fileMeta['original_name'] ?? $storedName));
$mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $original) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
