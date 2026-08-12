<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/crm_permissions.php';

function hivenest_crm_capabilities_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function hivenest_crm_capabilities_env(string $key, string $default = ''): string
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

function hivenest_crm_capabilities_decode(string $value): string|false
{
    $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function hivenest_crm_capabilities_admin(PDO $db): array
{
    $authorization = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    $token = preg_match('/Bearer\s+(.+)/i', $authorization, $match) ? trim($match[1]) : '';
    $parts = explode('.', $token);
    if ($token === '' || count($parts) !== 3) return [];

    [$encodedHeader, $encodedPayload, $signature] = $parts;
    $headerJson = hivenest_crm_capabilities_decode($encodedHeader);
    $payloadJson = hivenest_crm_capabilities_decode($encodedPayload);
    $jwtHeader = $headerJson === false ? null : json_decode($headerJson, true);
    $payload = $payloadJson === false ? null : json_decode($payloadJson, true);
    $secret = hivenest_crm_capabilities_env('JWT_SECRET_KEY');
    if (!is_array($jwtHeader) || !is_array($payload) || $secret === '') return [];

    $expected = rtrim(strtr(base64_encode(hash_hmac(
        'sha256',
        $encodedHeader . '.' . $encodedPayload,
        $secret,
        true
    )), '+/', '-_'), '=');
    if (($jwtHeader['alg'] ?? '') !== hivenest_crm_capabilities_env('JWT_ALGORITHM', 'HS256')
        || ($payload['user_type'] ?? '') !== 'admin'
        || (!empty($payload['exp']) && (int)$payload['exp'] < time())
        || !hash_equals($expected, $signature)
    ) {
        return [];
    }

    return hivenest_crm_admin_record($db, (int)($payload['sub'] ?? 0));
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    hivenest_crm_capabilities_out(405, ['ok' => false, 'error' => 'GET required.']);
}

$db = hivenest_db();
if (!$db) {
    hivenest_crm_capabilities_out(503, ['ok' => false, 'error' => 'CRM database is unavailable.']);
}
$admin = hivenest_crm_capabilities_admin($db);
if (!$admin) {
    hivenest_crm_capabilities_out(401, ['ok' => false, 'error' => 'Bearer administrator authentication required.']);
}

$capabilities = [];
foreach (array_keys(hivenest_crm_permission_matrix()) as $capability) {
    $capabilities[$capability] = hivenest_crm_role_allows($admin, $capability);
}

hivenest_crm_capabilities_out(200, [
    'ok' => true,
    'admin' => [
        'id' => (int)$admin['id'],
        'username' => (string)$admin['username'],
        'role' => (string)$admin['role'],
    ],
    'capabilities' => $capabilities,
]);
