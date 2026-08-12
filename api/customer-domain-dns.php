<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../utilities/customer_session.php';
hivenest_customer_session_configure();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$session = hivenest_customer_session_status(true);
if (!$session['authenticated']) {
    http_response_code(401);
    echo json_encode(['error' => 'Customer login required.'], JSON_UNESCAPED_SLASHES);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hivenest_customer_csrf_require_json();
}

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/myorderbox_bridge.php';

function hivenest_domain_dns_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function hivenest_domain_dns_owned(PDO $db, int $customerId, int $domainId): array
{
    $stmt = $db->prepare("
        SELECT d.id, d.service_id, d.domain_name, d.provider_order_id,
               d.registrar_status, s.service_status
        FROM domain_registrations d
        INNER JOIN services s
          ON s.id=d.service_id
         AND s.customer_id=d.customer_id
        WHERE d.id=:domain_id
          AND d.customer_id=:customer_id
        LIMIT 1
    ");
    $stmt->execute(['domain_id' => $domainId, 'customer_id' => $customerId]);
    $domain = $stmt->fetch();
    if (!$domain) hivenest_domain_dns_out(404, ['error' => 'Domain registration was not found for this account.']);
    $providerOrderId = trim((string)($domain['provider_order_id'] ?? ''));
    if ($providerOrderId === '' || !ctype_digit($providerOrderId)) {
        hivenest_domain_dns_out(409, ['error' => 'This domain is not linked to a valid provider order.']);
    }
    if ((string)$domain['registrar_status'] !== 'active') {
        hivenest_domain_dns_out(409, ['error' => 'DNS can only be managed for an active domain registration.']);
    }
    return $domain;
}

function hivenest_domain_dns_records(mixed $value, string $expectedType): array
{
    $records = [];
    $walk = static function (mixed $node) use (&$walk, &$records, $expectedType): void {
        if (!is_array($node)) return;
        $type = strtoupper(trim((string)($node['type'] ?? $node['record-type'] ?? '')));
        $host = trim((string)($node['host'] ?? $node['name'] ?? ''));
        $recordValue = $node['value'] ?? $node['record-value'] ?? null;
        if (($type === '' || $type === $expectedType) && $recordValue !== null && (is_scalar($recordValue))) {
            $records[] = [
                'type' => $expectedType,
                'host' => $host !== '' ? $host : '@',
                'value' => (string)$recordValue,
                'ttl' => (int)($node['ttl'] ?? 14400),
                'priority' => isset($node['priority']) ? (int)$node['priority'] : null,
            ];
            return;
        }
        foreach ($node as $child) $walk($child);
    };
    $walk($value);
    return $records;
}

function hivenest_domain_dns_host(mixed $value): string
{
    $host = strtolower(rtrim(trim((string)$value), '.'));
    if ($host === '') return '@';
    if ($host === '@') return $host;
    if (strlen($host) > 253
        || !preg_match('/^(?:_?[a-z0-9](?:[a-z0-9_-]{0,61}[a-z0-9])?)(?:\.(?:_?[a-z0-9](?:[a-z0-9_-]{0,61}[a-z0-9])?))*$/', $host)
    ) {
        hivenest_domain_dns_out(422, ['error' => 'Enter a valid DNS host such as @, www or _dmarc.']);
    }
    return $host;
}

function hivenest_domain_dns_fqdn(mixed $value): string
{
    $hostname = strtolower(rtrim(trim((string)$value), '.'));
    if (strlen($hostname) > 253
        || !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $hostname)
    ) {
        hivenest_domain_dns_out(422, ['error' => 'Enter a valid destination hostname.']);
    }
    return $hostname;
}

$db = hivenest_db();
if (!$db) hivenest_domain_dns_out(503, ['error' => 'Customer database is unavailable.']);

$customerId = (int)$session['customer_id'];
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) hivenest_domain_dns_out(400, ['error' => 'Invalid JSON input.']);
}
$domainId = (int)($input['domain_id'] ?? $_GET['domain_id'] ?? 0);
if ($domainId <= 0) hivenest_domain_dns_out(422, ['error' => 'A valid domain is required.']);
$domain = hivenest_domain_dns_owned($db, $customerId, $domainId);
$domainName = strtolower((string)$domain['domain_name']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $records = [];
    foreach (['A', 'CNAME', 'MX', 'TXT'] as $type) {
        $result = hivenest_mob_get($db, '/api/dns/manage/search-records.json', [
            'domain-name' => $domainName,
            'type' => $type,
            'no-of-records' => 100,
            'page-no' => 1,
        ]);
        if (!$result['ok']) {
            hivenest_domain_dns_out(502, [
                'error' => $result['error'] ?: 'DNS records could not be loaded from the provider.',
                'domain_name' => $domainName,
            ]);
        }
        $records = array_merge($records, hivenest_domain_dns_records($result['data'], $type));
    }
    hivenest_domain_dns_out(200, ['domain_id' => $domainId, 'domain_name' => $domainName, 'records' => $records]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hivenest_domain_dns_out(405, ['error' => 'GET or POST required.']);
}

$action = strtolower(trim((string)($input['action'] ?? '')));
if ($action === 'activate') {
    $result = hivenest_mob_request($db, '/api/dns/activate.json', [
        'order-id' => (string)$domain['provider_order_id'],
    ]);
    if (!$result['ok']) hivenest_domain_dns_out(502, ['error' => $result['error'] ?: 'DNS activation was rejected.']);
    hivenest_domain_dns_out(200, [
        'message' => 'DNS service activation was accepted. Use the provider DNS nameservers before relying on these records.',
    ]);
}

$type = strtoupper(trim((string)($input['type'] ?? '')));
$endpoints = [
    'A' => 'a',
    'CNAME' => 'cname',
    'MX' => 'mx',
    'TXT' => 'txt',
];
if (!isset($endpoints[$type])) {
    hivenest_domain_dns_out(422, ['error' => 'Record type must be A, CNAME, MX or TXT.']);
}
$host = hivenest_domain_dns_host($input['host'] ?? '@');
$value = trim((string)($input['value'] ?? ''));
if ($type === 'A') {
    if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        hivenest_domain_dns_out(422, ['error' => 'A records require a valid IPv4 address.']);
    }
} elseif (in_array($type, ['CNAME', 'MX'], true)) {
    $value = hivenest_domain_dns_fqdn($value);
} elseif ($value === '' || strlen($value) > 255 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
    hivenest_domain_dns_out(422, ['error' => 'TXT records require 1 to 255 printable characters.']);
}
$ttl = max(14400, min(604800, (int)($input['ttl'] ?? 14400)));
$priority = max(0, min(65535, (int)($input['priority'] ?? 10)));
$verb = $action === 'delete_record' ? 'delete' : ($action === 'add_record' ? 'add' : '');
if ($verb === '') hivenest_domain_dns_out(422, ['error' => 'Use add_record or delete_record.']);

$params = [
    'domain-name' => $domainName,
    'host' => $host,
    'value' => $value,
];
if ($verb === 'add') $params['ttl'] = $ttl;
if ($type === 'MX') $params['priority'] = $priority;
$result = hivenest_mob_request(
    $db,
    '/api/dns/manage/' . $verb . '-' . $endpoints[$type] . '-record.json',
    $params
);
if (!$result['ok']) {
    hivenest_domain_dns_out(502, ['error' => $result['error'] ?: 'The DNS provider rejected the record change.']);
}
hivenest_domain_dns_out(200, [
    'message' => $type . ' record ' . ($verb === 'add' ? 'added.' : 'deleted.'),
    'domain_id' => $domainId,
]);
