<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../utilities/customer_session.php';
hivenest_customer_session_configure();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$customerSession = hivenest_customer_session_status(true);
if (!$customerSession['authenticated']) {
    http_response_code(401);
    echo json_encode(['error' => 'Customer login required.'], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hivenest_customer_csrf_require_json();
}

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/myorderbox_bridge.php';

function hivenest_customer_domains_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function hivenest_customer_domains_nameservers(?string $value): array
{
    $value = trim((string)$value);
    if ($value === '') return [];

    $decoded = json_decode($value, true);
    $items = is_array($decoded)
        ? $decoded
        : preg_split('/[\s,;]+/', $value, -1, PREG_SPLIT_NO_EMPTY);

    $nameservers = [];
    foreach ($items ?: [] as $item) {
        if (!is_scalar($item)) continue;
        $hostname = strtolower(rtrim(trim((string)$item), '.'));
        if ($hostname !== '') $nameservers[$hostname] = $hostname;
    }
    return array_values($nameservers);
}

function hivenest_customer_domains_validate_nameservers(mixed $value): array
{
    $items = is_array($value)
        ? $value
        : preg_split('/[\r\n,;\s]+/', trim((string)$value), -1, PREG_SPLIT_NO_EMPTY);
    $nameservers = [];
    foreach ($items ?: [] as $item) {
        if (!is_scalar($item)) {
            hivenest_customer_domains_out(422, ['error' => 'Every nameserver must be a hostname.']);
        }
        $hostname = strtolower(rtrim(trim((string)$item), '.'));
        if ($hostname === '') continue;
        if (strlen($hostname) > 253
            || !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $hostname)
        ) {
            hivenest_customer_domains_out(422, [
                'error' => $hostname . ' is not a valid nameserver hostname.',
            ]);
        }
        $nameservers[$hostname] = $hostname;
    }

    $nameservers = array_values($nameservers);
    if (count($nameservers) < 2 || count($nameservers) > 13) {
        hivenest_customer_domains_out(422, [
            'error' => 'Enter between 2 and 13 unique nameserver hostnames.',
        ]);
    }
    return $nameservers;
}

function hivenest_customer_domains_payload(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'service_id' => (int)$row['service_id'],
        'domain_name' => (string)$row['domain_name'],
        'extension' => (string)$row['extension'],
        'registration_date' => $row['registration_date'] ?? null,
        'expiry_date' => $row['expiry_date'] ?? null,
        'auto_renew' => (int)($row['auto_renew'] ?? 0) === 1,
        'nameservers' => hivenest_customer_domains_nameservers($row['nameservers'] ?? null),
        'privacy_protection' => (int)($row['privacy_protection'] ?? 0) === 1,
        'lock_status' => (int)($row['lock_status'] ?? 0) === 1,
        'registrar_status' => (string)($row['registrar_status'] ?? 'active'),
        'provider_status' => $row['provider_status'] ?? null,
        'provider_order_linked' => trim((string)($row['provider_order_id'] ?? '')) !== '',
        'service_status' => $row['service_status'] ?? null,
        'service_name' => $row['service_name'] ?? null,
        'order_number' => $row['order_number'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

$customerId = (int)$customerSession['customer_id'];
$db = hivenest_db();
if (!$db) {
    hivenest_customer_domains_out(503, ['error' => 'Customer database is unavailable.']);
}

$domainId = (int)($_GET['domain_id'] ?? 0);
$where = 'd.customer_id = :customer_id';
$params = ['customer_id' => $customerId];
if ($domainId > 0) {
    $where .= ' AND d.id = :domain_id';
    $params['domain_id'] = $domainId;
}

$selectSql = "
    SELECT
        d.*,
        s.service_name,
        s.service_status,
        o.order_number
    FROM domain_registrations d
    INNER JOIN services s
        ON s.id = d.service_id
       AND s.customer_id = d.customer_id
    LEFT JOIN orders o
        ON o.id = s.order_id
       AND o.customer_id = d.customer_id
    WHERE {$where}
    ORDER BY
        CASE d.registrar_status WHEN 'active' THEN 1 ELSE 2 END,
        d.expiry_date ASC,
        d.id DESC
    LIMIT 100
";
$select = $db->prepare($selectSql);
$select->execute($params);
$rows = $select->fetchAll() ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    hivenest_customer_domains_out(200, [
        'domains' => array_map('hivenest_customer_domains_payload', $rows),
        'selected_domain_id' => $domainId > 0 ? $domainId : null,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hivenest_customer_domains_out(405, ['error' => 'GET or POST required.']);
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    hivenest_customer_domains_out(400, ['error' => 'Invalid JSON input.']);
}

$action = strtolower(trim((string)($input['action'] ?? '')));
$domainId = (int)($input['domain_id'] ?? 0);
if ($action !== 'update_nameservers' || $domainId <= 0) {
    hivenest_customer_domains_out(422, ['error' => 'A valid domain and nameserver update action are required.']);
}

// Re-select after reading the request so ownership can never be supplied by
// the browser or inferred from a service owned by a different customer.
$owned = $db->prepare("
    SELECT d.*
    FROM domain_registrations d
    INNER JOIN services s
        ON s.id = d.service_id
       AND s.customer_id = d.customer_id
    WHERE d.id = :domain_id
      AND d.customer_id = :customer_id
    LIMIT 1
");
$owned->execute([
    'domain_id' => $domainId,
    'customer_id' => $customerId,
]);
$domain = $owned->fetch();
if (!$domain) {
    hivenest_customer_domains_out(404, ['error' => 'Domain registration was not found for this account.']);
}

$providerOrderId = trim((string)($domain['provider_order_id'] ?? ''));
if ($providerOrderId === '' || !ctype_digit($providerOrderId)) {
    hivenest_customer_domains_out(409, [
        'error' => 'This domain is not linked to a valid provider order. Contact support for assistance.',
    ]);
}
if ((string)($domain['registrar_status'] ?? '') !== 'active') {
    hivenest_customer_domains_out(409, [
        'error' => 'Nameservers can only be changed while the domain registration is active.',
    ]);
}

$nameservers = hivenest_customer_domains_validate_nameservers($input['nameservers'] ?? []);
$provider = hivenest_mob_request($db, '/api/domains/modify-ns.json', [
    'order-id' => $providerOrderId,
    'ns' => $nameservers,
]);
if (!$provider['ok']) {
    hivenest_customer_domains_out(502, [
        'error' => $provider['error'] ?: 'The domain provider rejected the nameserver update.',
    ]);
}

$providerData = is_array($provider['data']) ? $provider['data'] : [];
$providerStatus = trim((string)($providerData['actionstatusdesc']
    ?? $providerData['actionstatus']
    ?? $providerData['status']
    ?? 'submitted'));
$providerActionId = trim((string)($providerData['eaqid']
    ?? $providerData['actionid']
    ?? $providerData['action-id']
    ?? ''));

$update = $db->prepare("
    UPDATE domain_registrations
    SET nameservers = :nameservers,
        provider_action_id = COALESCE(NULLIF(:provider_action_id, ''), provider_action_id),
        provider_status = :provider_status,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = :domain_id
      AND customer_id = :customer_id
");
$update->execute([
    'nameservers' => json_encode($nameservers, JSON_UNESCAPED_SLASHES),
    'provider_action_id' => $providerActionId,
    'provider_status' => $providerStatus,
    'domain_id' => $domainId,
    'customer_id' => $customerId,
]);

hivenest_customer_domains_out(200, [
    'message' => 'Nameserver update accepted. DNS propagation can take 24 to 48 hours.',
    'domain' => [
        'id' => $domainId,
        'domain_name' => (string)$domain['domain_name'],
        'nameservers' => $nameservers,
        'provider_status' => $providerStatus,
    ],
]);
