<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../utilities/customer_session.php';
hivenest_customer_session_configure();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

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

function hivenest_customer_mailboxes_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function hivenest_customer_mailboxes_config(?string $value): array
{
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

function hivenest_customer_mailboxes_provider_order(array $service): string
{
    $config = hivenest_customer_mailboxes_config($service['service_config'] ?? null);
    $candidates = [
        $config['provider_order_id'] ?? null,
        $service['item_provider_order_id'] ?? null,
        $service['item_provider_entity_id'] ?? null,
    ];
    foreach ($candidates as $candidate) {
        $value = trim((string)$candidate);
        if ($value !== '' && ctype_digit($value)) return $value;
    }
    return '';
}

function hivenest_customer_mailboxes_is_email_service(array $service): bool
{
    $text = strtolower(implode(' ', [
        $service['service_type'] ?? '',
        $service['service_name'] ?? '',
        $service['product_name'] ?? '',
        $service['product_slug'] ?? '',
        $service['product_type'] ?? '',
    ]));
    return (bool)preg_match('/\b(email|mail|workspace|g[\s_-]*suite)\b/', $text);
}

function hivenest_customer_mailboxes_collect_users(mixed $value, array &$users): void
{
    if (!is_array($value)) return;
    $email = trim((string)($value['email'] ?? $value['username'] ?? $value['user-email'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $key = strtolower($email);
        $users[$key] = [
            'email' => $key,
            'first_name' => trim((string)($value['first-name'] ?? $value['firstname'] ?? $value['first_name'] ?? '')),
            'last_name' => trim((string)($value['last-name'] ?? $value['lastname'] ?? $value['last_name'] ?? '')),
            'status' => strtolower(trim((string)($value['status'] ?? $value['userstatus'] ?? 'active'))),
            'account_type' => trim((string)($value['account-type'] ?? $value['account_type'] ?? 'mailbox')),
            'used_space' => $value['used-space'] ?? $value['used_space'] ?? null,
            'quota' => $value['quota'] ?? $value['storage'] ?? null,
        ];
    }
    foreach ($value as $key => $child) {
        if (!is_array($child)) continue;
        if (is_string($key) && filter_var($key, FILTER_VALIDATE_EMAIL) && empty($child['email'])) {
            $child['email'] = $key;
        }
        hivenest_customer_mailboxes_collect_users($child, $users);
    }
}

function hivenest_customer_mailboxes_validate_password(string $password): void
{
    if (strlen($password) < 9 || strlen($password) > 16
        || !preg_match('/[a-z]/', $password)
        || !preg_match('/[A-Z]/', $password)
        || !preg_match('/\d/', $password)
        || !preg_match('/[~*!@$#%_+.?:,{}]/', $password)
    ) {
        hivenest_customer_mailboxes_out(422, [
            'error' => 'Password must be 9–16 characters with lowercase, uppercase, number, and an allowed special character.',
        ]);
    }
}

function hivenest_customer_mailboxes_find_generated_password(mixed $value, int $depth = 0): string
{
    if ($depth > 6 || !is_array($value)) return '';
    foreach ($value as $key => $child) {
        $normalized = strtolower(str_replace(['_', ' '], '-', (string)$key));
        if (in_array($normalized, ['password', 'passwd', 'new-password', 'newpasswd'], true)
            && is_scalar($child)
            && trim((string)$child) !== ''
        ) {
            return trim((string)$child);
        }
    }
    foreach ($value as $child) {
        $found = hivenest_customer_mailboxes_find_generated_password($child, $depth + 1);
        if ($found !== '') return $found;
    }
    return '';
}

$customerId = (int)$customerSession['customer_id'];
$db = hivenest_db();
if (!$db) hivenest_customer_mailboxes_out(503, ['error' => 'Customer database is unavailable.']);

$serviceId = (int)($_GET['service_id'] ?? 0);
$serviceStmt = $db->prepare("
    SELECT
        s.id,
        s.customer_id,
        s.service_name,
        s.domain_name,
        s.service_type,
        s.service_status,
        s.service_config,
        p.name AS product_name,
        p.slug AS product_slug,
        p.product_type,
        (
            SELECT oi.provider_order_id
            FROM order_items oi
            WHERE oi.service_id = s.id
              AND oi.provider_order_id IS NOT NULL
              AND oi.provider_order_id <> ''
            ORDER BY oi.id DESC
            LIMIT 1
        ) AS item_provider_order_id,
        (
            SELECT oi.provider_entity_id
            FROM order_items oi
            WHERE oi.service_id = s.id
              AND oi.provider_entity_id IS NOT NULL
              AND oi.provider_entity_id <> ''
            ORDER BY oi.id DESC
            LIMIT 1
        ) AS item_provider_entity_id
    FROM services s
    LEFT JOIN products p ON p.id = s.product_id
    WHERE s.customer_id = :customer_id
      AND (:service_id = 0 OR s.id = :service_id)
    ORDER BY s.id DESC
    LIMIT 100
");
$serviceStmt->execute([
    'customer_id' => $customerId,
    'service_id' => $serviceId,
]);
$allServices = $serviceStmt->fetchAll() ?: [];
$emailServices = array_values(array_filter($allServices, 'hivenest_customer_mailboxes_is_email_service'));

$servicePayloads = array_map(static function (array $service): array {
    return [
        'id' => (int)$service['id'],
        'service_name' => (string)$service['service_name'],
        'domain_name' => $service['domain_name'] ?? null,
        'service_status' => (string)$service['service_status'],
        'provider_linked' => hivenest_customer_mailboxes_provider_order($service) !== '',
    ];
}, $emailServices);

if ($serviceId <= 0) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        hivenest_customer_mailboxes_out(422, ['error' => 'Select an email service first.']);
    }
    hivenest_customer_mailboxes_out(200, ['services' => $servicePayloads, 'mailboxes' => []]);
}

$service = $emailServices[0] ?? null;
if (!$service || (int)$service['id'] !== $serviceId) {
    hivenest_customer_mailboxes_out(404, ['error' => 'Email service was not found for this account.']);
}
$providerOrderId = hivenest_customer_mailboxes_provider_order($service);
if ($providerOrderId === '') {
    hivenest_customer_mailboxes_out(409, [
        'error' => 'This email service is not linked to a valid provider order. Contact support.',
        'services' => $servicePayloads,
    ]);
}
$serviceDomain = strtolower(trim((string)($service['domain_name'] ?? '')));
if ($serviceDomain === '') {
    hivenest_customer_mailboxes_out(409, [
        'error' => 'This email service has no primary domain. Contact support to link it before managing mailboxes.',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = hivenest_mob_get($db, '/api/mail/users/search.json', [
        'order-id' => $providerOrderId,
        'page-no' => 1,
    ]);
    if (!$result['ok']) {
        hivenest_customer_mailboxes_out(502, [
            'error' => $result['error'] ?: 'Mailbox list could not be fetched from the provider.',
        ]);
    }
    $users = [];
    hivenest_customer_mailboxes_collect_users($result['data'], $users);
    hivenest_customer_mailboxes_out(200, [
        'services' => $servicePayloads,
        'selected_service_id' => $serviceId,
        'mailboxes' => array_values($users),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hivenest_customer_mailboxes_out(405, ['error' => 'GET or POST required.']);
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) hivenest_customer_mailboxes_out(400, ['error' => 'Invalid JSON input.']);
$action = strtolower(trim((string)($input['action'] ?? '')));
$email = strtolower(trim((string)($input['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)
    || !str_ends_with($email, '@' . $serviceDomain)
) {
    hivenest_customer_mailboxes_out(422, [
        'error' => 'Mailbox address must belong to ' . $serviceDomain . '.',
    ]);
}

if ($action === 'create') {
    $password = (string)($input['password'] ?? '');
    hivenest_customer_mailboxes_validate_password($password);
    $customerStmt = $db->prepare("
        SELECT email, first_name, last_name, country_code
        FROM customers
        WHERE id = :customer_id
          AND status = 'active'
        LIMIT 1
    ");
    $customerStmt->execute(['customer_id' => $customerId]);
    $customer = $customerStmt->fetch();
    if (!$customer) hivenest_customer_mailboxes_out(404, ['error' => 'Customer profile was not found.']);

    $result = hivenest_mob_request($db, '/api/mail/user/add.json', [
        'order-id' => $providerOrderId,
        'email' => $email,
        'passwd' => $password,
        'notification-email' => (string)$customer['email'],
        'first-name' => trim((string)$customer['first_name']) ?: 'HiveNest',
        'last-name' => trim((string)$customer['last_name']) ?: 'Customer',
        'country-code' => strtoupper(trim((string)$customer['country_code'])) ?: 'ZA',
        'language-code' => 'en',
    ]);
    // Never retain the mailbox password in API logs or local tables.
    if (!$result['ok']) {
        hivenest_customer_mailboxes_out(502, [
            'error' => $result['error'] ?: 'Mailbox creation was rejected by the provider.',
        ]);
    }
    hivenest_customer_mailboxes_out(201, [
        'message' => $email . ' was created. Store the password securely; HiveNest does not retain it.',
    ]);
}

if ($action === 'reset_password') {
    $result = hivenest_mob_request($db, '/api/mail/user/reset-password.json', [
        'order-id' => $providerOrderId,
        'email' => $email,
        '__redact_response' => true,
    ]);
    if (!$result['ok']) {
        hivenest_customer_mailboxes_out(502, [
            'error' => $result['error'] ?: 'Mailbox password reset was rejected by the provider.',
        ]);
    }
    $generatedPassword = hivenest_customer_mailboxes_find_generated_password($result['data']);
    if ($generatedPassword === '') {
        hivenest_customer_mailboxes_out(502, [
            'error' => 'The provider accepted the reset but did not return a new password. Contact support before resetting it again.',
        ]);
    }
    hivenest_customer_mailboxes_out(200, [
        'message' => 'Password reset completed. Copy the temporary password now; HiveNest does not retain it.',
        'generated_password' => $generatedPassword,
    ]);
}

if ($action === 'delete') {
    $confirmation = strtolower(trim((string)($input['confirmation'] ?? '')));
    $confirmedPermanent = ($input['confirm_permanent'] ?? false) === true;
    if (!$confirmedPermanent || !hash_equals($email, $confirmation)) {
        hivenest_customer_mailboxes_out(422, [
            'error' => 'Permanent deletion requires the exact mailbox address and deletion confirmation.',
        ]);
    }
    $result = hivenest_mob_request($db, '/api/mail/user/delete.json', [
        'order-id' => $providerOrderId,
        'email' => $email,
    ]);
    if (!$result['ok']) {
        hivenest_customer_mailboxes_out(502, [
            'error' => $result['error'] ?: 'Mailbox deletion was rejected by the provider.',
        ]);
    }
    hivenest_customer_mailboxes_out(200, [
        'message' => $email . ' was permanently deleted. Provider deletion does not issue a refund.',
    ]);
}

if (!in_array($action, ['suspend', 'unsuspend'], true)) {
    hivenest_customer_mailboxes_out(422, ['error' => 'Unsupported mailbox action.']);
}
$endpoint = $action === 'suspend'
    ? '/api/mail/users/suspend.json'
    : '/api/mail/users/unsuspend.json';
$result = hivenest_mob_request($db, $endpoint, [
    'order-id' => $providerOrderId,
    'emails' => $email,
]);
if (!$result['ok']) {
    hivenest_customer_mailboxes_out(502, [
        'error' => $result['error'] ?: 'Mailbox status change was rejected by the provider.',
    ]);
}
hivenest_customer_mailboxes_out(200, [
    'message' => $email . ' was ' . ($action === 'suspend' ? 'suspended.' : 'reactivated.'),
]);
