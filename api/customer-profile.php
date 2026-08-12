<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../access/dbconfig.php';

function hivenest_customer_profile_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_customer_profile_clean(?string $value, int $max = 255): ?string
{
    $clean = trim(str_replace("\0", '', (string)($value ?? '')));
    if ($clean === '') return null;
    if (function_exists('mb_substr')) {
        return mb_substr($clean, 0, $max);
    }
    return substr($clean, 0, $max);
}

function hivenest_customer_profile_payload(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'uuid' => (string)$row['uuid'],
        'email' => (string)$row['email'],
        'first_name' => (string)($row['first_name'] ?? ''),
        'last_name' => (string)($row['last_name'] ?? ''),
        'company_name' => $row['company_name'] ?? null,
        'phone' => $row['phone'] ?? null,
        'country_code' => (string)($row['country_code'] ?? 'ZA'),
        'address_line1' => $row['address_line1'] ?? null,
        'address_line2' => $row['address_line2'] ?? null,
        'city' => $row['city'] ?? null,
        'state' => $row['state'] ?? null,
        'postal_code' => $row['postal_code'] ?? null,
        'country' => $row['country'] ?? null,
        'preferred_currency' => (string)($row['preferred_currency'] ?: 'USD'),
        'email_verified' => (int)($row['email_verified'] ?? 0) === 1,
        'myorderbox_customer_id' => $row['myorderbox_customer_id'] ?? null,
        'myorderbox_sync_status' => (string)($row['myorderbox_sync_status'] ?? 'not_synced'),
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

$customerId = (int)($_SESSION['customer_id'] ?? 0);
if ($customerId <= 0) {
    hivenest_customer_profile_out(401, ['error' => 'Customer login required.']);
}

$db = hivenest_db();
if (!$db) {
    hivenest_customer_profile_out(503, ['error' => 'Customer database is unavailable.']);
}

$select = $db->prepare("
    SELECT
        id, uuid, email, first_name, last_name, company_name, phone, country_code,
        address_line1, address_line2, city, state, postal_code, country,
        preferred_currency, email_verified, myorderbox_customer_id,
        myorderbox_sync_status, updated_at
    FROM customers
    WHERE id = :customer_id
      AND status = 'active'
    LIMIT 1
");
$select->execute(['customer_id' => $customerId]);
$profile = $select->fetch();
if (!$profile) {
    hivenest_customer_profile_out(404, ['error' => 'Customer profile not found.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    hivenest_customer_profile_out(200, ['profile' => hivenest_customer_profile_payload($profile)]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hivenest_customer_profile_out(405, ['error' => 'GET or POST required.']);
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    hivenest_customer_profile_out(400, ['error' => 'Invalid JSON input.']);
}

$allowedCurrencies = ['USD', 'ZAR', 'EUR', 'SGD'];
$firstName = hivenest_customer_profile_clean($input['first_name'] ?? null, 100);
$lastName = hivenest_customer_profile_clean($input['last_name'] ?? null, 100);
$phone = hivenest_customer_profile_clean($input['phone'] ?? null, 50);
$countryCode = strtoupper((string)(hivenest_customer_profile_clean($input['country_code'] ?? null, 3) ?: 'ZA'));
$country = hivenest_customer_profile_clean($input['country'] ?? null, 100);
$preferredCurrency = strtoupper((string)(hivenest_customer_profile_clean($input['preferred_currency'] ?? null, 3) ?: 'USD'));

if ($firstName === null || $lastName === null) {
    hivenest_customer_profile_out(422, ['error' => 'First name and last name are required.']);
}
if ($phone === null) {
    hivenest_customer_profile_out(422, ['error' => 'Phone number is required for registrations and provider orders.']);
}
if (!preg_match('/^[A-Z]{2,3}$/', $countryCode)) {
    hivenest_customer_profile_out(422, ['error' => 'Country code must be 2 or 3 letters, for example ZA.']);
}
if ($country === null) {
    hivenest_customer_profile_out(422, ['error' => 'Country is required.']);
}
if (!in_array($preferredCurrency, $allowedCurrencies, true)) {
    hivenest_customer_profile_out(422, ['error' => 'Preferred currency must be USD, ZAR, EUR, or SGD.']);
}

$fields = [
    'first_name' => $firstName,
    'last_name' => $lastName,
    'company_name' => hivenest_customer_profile_clean($input['company_name'] ?? null, 255),
    'phone' => $phone,
    'country_code' => $countryCode,
    'address_line1' => hivenest_customer_profile_clean($input['address_line1'] ?? null, 255),
    'address_line2' => hivenest_customer_profile_clean($input['address_line2'] ?? null, 255),
    'city' => hivenest_customer_profile_clean($input['city'] ?? null, 100),
    'state' => hivenest_customer_profile_clean($input['state'] ?? null, 100),
    'postal_code' => hivenest_customer_profile_clean($input['postal_code'] ?? null, 20),
    'country' => $country,
    'preferred_currency' => $preferredCurrency,
];

foreach (['address_line1', 'city', 'state', 'postal_code'] as $requiredField) {
    if ($fields[$requiredField] === null) {
        hivenest_customer_profile_out(422, ['error' => 'Address, city, state/province, and postal code are required for domains and invoices.']);
    }
}

$sets = [];
$params = ['customer_id' => $customerId];
foreach ($fields as $field => $value) {
    $sets[] = "{$field} = :{$field}";
    $params[$field] = $value;
}

try {
    $update = $db->prepare('UPDATE customers SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :customer_id');
    $update->execute($params);
} catch (Throwable $e) {
    error_log('Customer profile update failed: ' . $e->getMessage());
    hivenest_customer_profile_out(500, ['error' => 'Profile update failed. Please try again.']);
}

$select->execute(['customer_id' => $customerId]);
$updated = $select->fetch();

hivenest_customer_profile_out(200, [
    'success' => true,
    'message' => 'Profile updated.',
    'profile' => hivenest_customer_profile_payload($updated ?: $profile),
]);
