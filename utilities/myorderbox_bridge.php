<?php
declare(strict_types=1);

/**
 * HiveNest PayPal -> MyOrderBox provisioning bridge.
 *
 * This file intentionally separates provider provisioning from PayPal capture.
 * Payment capture should record the paid order first, then create durable
 * provisioning/service records. Provider/API failures are stored for CRM review
 * and must not make a successful PayPal capture look unpaid to the customer.
 */

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/order_notifications.php';

function hivenest_bridge_uuid(): string {
    if (function_exists('pp_uuid')) return pp_uuid();
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function hivenest_bridge_env(string $key, string $default = ''): string {
    static $env = null;
    if ($env === null) {
        $env = [];
        $lines = is_readable(HIVENEST_ENV_PATH) ? (@file(HIVENEST_ENV_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            [$name, $value] = explode('=', $line, 2);
            $env[trim($name)] = trim(trim($value), "\"'");
        }
    }
    $process = getenv($key);
    return $process !== false && $process !== '' ? (string)$process : (string)($env[$key] ?? $default);
}

function hivenest_bridge_env_bool(string $key, bool $default = false): bool {
    $value = strtolower(trim(hivenest_bridge_env($key, $default ? 'true' : 'false')));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function hivenest_bridge_table_exists(PDO $db, string $table): bool {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
        ");
        $stmt->execute(['table_name' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        error_log('HiveNest bridge table check failed: ' . $e->getMessage());
        return false;
    }
}

function hivenest_bridge_column_exists(PDO $db, string $table, string $column): bool {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
        ");
        $stmt->execute(['table_name' => $table, 'column_name' => $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        error_log('HiveNest bridge column check failed: ' . $e->getMessage());
        return false;
    }
}

function hivenest_bridge_schema_ready(PDO $db): bool {
    return hivenest_bridge_table_exists($db, 'provisioning_jobs')
        && hivenest_bridge_table_exists($db, 'payment_gateway_transactions')
        && hivenest_bridge_table_exists($db, 'myorderbox_contacts')
        && hivenest_bridge_table_exists($db, 'product_provider_mappings')
        && hivenest_bridge_column_exists($db, 'order_items', 'service_id')
        && hivenest_bridge_column_exists($db, 'customers', 'myorderbox_customer_id');
}

function hivenest_bridge_service_type(string $productType, string $productSlug, string $sku, string $name): string {
    $text = strtolower($productType . ' ' . $productSlug . ' ' . $sku . ' ' . $name);
    // Hosting/server product names often contain "domain" because the service
    // is linked to an existing primary domain. That must not turn the package
    // into a domain-registration job. Provider provisioning should follow the
    // actual product family first, then fall back to domain registration.
    if (str_contains($text, 'server') || str_contains($text, 'vps') || str_contains($text, 'dedicated')) return 'server';
    if (str_contains($text, 'hosting') || str_contains($text, 'wordpress')) return 'hosting';
    if (str_contains($text, 'ssl')) return 'ssl';
    if (str_contains($text, 'backup') || str_contains($text, 'xcitium')) return 'backup';
    if (str_contains($text, 'sitelock') || str_contains($text, 'security')) return 'security';
    if (str_contains($text, 'email') || str_contains($text, 'workspace') || str_contains($text, 'mail')) return 'email';
    if (str_contains($text, 'domain')) return 'domain';
    return 'design';
}

function hivenest_bridge_job_type(string $serviceType, string $sku, string $name): string {
    $text = strtolower($sku . ' ' . $name);
    if ($serviceType === 'domain') {
        return str_contains($text, 'privacy') ? 'manual_queue' : 'domain_registration';
    }
    if ($serviceType === 'hosting' || $serviceType === 'server') return 'hosting_setup';
    if ($serviceType === 'email') return 'email_setup';
    if ($serviceType === 'ssl') return 'ssl_setup';
    if ($serviceType === 'backup') return 'backup_setup';
    if ($serviceType === 'security') return 'security_setup';
    if (str_contains($text, 'seo') || str_contains($text, 'marketing') || str_contains($text, 'social')) return 'marketing_queue';
    return 'design_queue';
}

function hivenest_bridge_is_domain_privacy_addon(string $serviceType, string $sku, string $name): bool {
    $text = strtolower($sku . ' ' . $name);
    return $serviceType === 'domain'
        && (str_contains($text, 'privacy') || $sku === 'domain-privacy');
}

function hivenest_bridge_next_due_date(string $billingCycle): string {
    $map = [
        'monthly' => '+1 month',
        'quarterly' => '+3 months',
        'semi_annually' => '+6 months',
        'annually' => '+1 year',
        'biennially' => '+2 years',
        'triennially' => '+3 years',
    ];
    return gmdate('Y-m-d H:i:s', strtotime($map[$billingCycle] ?? '+1 year'));
}

function hivenest_bridge_api_log(PDO $db, string $endpoint, array $request, array $response, string $status): void {
    if (!hivenest_bridge_table_exists($db, 'api_integration_logs')) return;
    try {
        $stmt = $db->prepare("
            INSERT INTO api_integration_logs
                (uuid, integration_name, request_type, request_data, response_data, status, error_message, created_at)
            VALUES
                (:uuid, :integration_name, :request_type, :request_data, :response_data, :status, :error_message, NOW())
        ");
        $stmt->execute([
            'uuid' => hivenest_bridge_uuid(),
            'integration_name' => 'myorderbox:' . $endpoint,
            'request_type' => str_contains($endpoint, 'domains') ? 'domain_register' : 'hosting_create',
            'request_data' => json_encode($request, JSON_UNESCAPED_SLASHES),
            'response_data' => json_encode($response, JSON_UNESCAPED_SLASHES),
            'status' => $status === 'success' ? 'success' : 'error',
            'error_message' => $status === 'success' ? null : (is_array($response) ? ($response['error'] ?? null) : null),
        ]);
    } catch (Throwable $e) {
        error_log('HiveNest bridge API log failed: ' . $e->getMessage());
    }
}

function hivenest_mob_base_url(?string $environment = null): string {
    $env = strtolower(trim((string)($environment ?? hivenest_bridge_env('MYORDERBOX_ENV', 'test'))));
    $default = $env === 'production' || $env === 'live' ? 'https://httpapi.com' : 'https://test.httpapi.com';
    return rtrim(hivenest_bridge_env($env === 'production' || $env === 'live' ? 'MYORDERBOX_BASE_URL' : 'MYORDERBOX_TEST_URL', $default), '/');
}

function hivenest_mob_query(array $payload): string {
    $parts = [];
    foreach ($payload as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $item) {
                $parts[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$item);
            }
            continue;
        }
        $parts[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
    }
    return implode('&', $parts);
}

function hivenest_mob_response_error(array $decoded, string $fallback = 'MyOrderBox API returned an error.'): string {
    foreach (['message', 'error', 'error-message', 'error_description', 'description', 'statusmsg'] as $key) {
        if (!array_key_exists($key, $decoded)) continue;
        $value = $decoded[$key];
        if (is_scalar($value) && trim((string)$value) !== '') {
            return trim((string)$value);
        }
        if (is_array($value)) {
            $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($json) && $json !== '[]' && $json !== '{}') return $json;
        }
    }
    $json = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($json) && $json !== '[]' && $json !== '{}' ? $json : $fallback;
}

function hivenest_mob_mask_sensitive(array $payload): array {
    $masked = $payload;
    foreach ($masked as $key => $value) {
        $normalized = strtolower(str_replace(['_', ' '], '-', (string)$key));
        if ($normalized === 'api-key'
            || str_contains($normalized, 'password')
            || str_contains($normalized, 'passwd')
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'token')
            || str_contains($normalized, 'auth-code')
        ) {
            $masked[$key] = '***';
        }
    }
    return $masked;
}

function hivenest_mob_request(PDO $db, string $endpoint, array $params): array {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'PHP cURL extension is not enabled.'];
    }
    $overrideResellerId = trim((string)($params['__auth_userid'] ?? ''));
    $overrideApiKey = trim((string)($params['__api_key'] ?? ''));
    $overrideEnv = trim((string)($params['__mob_env'] ?? ''));
    $httpMethod = strtoupper(trim((string)($params['__http_method'] ?? 'POST')));
    $redactResponse = !empty($params['__redact_response']);
    if (!in_array($httpMethod, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) $httpMethod = 'POST';
    unset(
        $params['__auth_userid'],
        $params['__api_key'],
        $params['__mob_env'],
        $params['__http_method'],
        $params['__redact_response']
    );

    $resellerId = $overrideResellerId !== '' ? $overrideResellerId : hivenest_bridge_env('MYORDERBOX_RESELLER_ID');
    $apiKey = $overrideApiKey !== '' ? $overrideApiKey : hivenest_bridge_env('MYORDERBOX_API_KEY');
    if ($resellerId === '' || $apiKey === '') {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'MyOrderBox credentials are not configured.'];
    }

    $sendParamsInQuery = !empty($params['__send_params_in_query']);
    unset($params['__send_params_in_query']);

    $payload = array_merge([
        'auth-userid' => $resellerId,
        'api-key' => $apiKey,
    ], $params);
    $url = hivenest_mob_base_url($overrideEnv !== '' ? $overrideEnv : null) . $endpoint;
    if ($sendParamsInQuery) {
        $url .= '?' . hivenest_mob_query($payload);
    }
    $masked = hivenest_mob_mask_sensitive($payload);

    $ch = curl_init($url);
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $httpMethod,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ];
    if (!$sendParamsInQuery) {
        $curlOptions[CURLOPT_POSTFIELDS] = hivenest_mob_query($payload);
    }
    curl_setopt_array($ch, $curlOptions);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $decoded = json_decode((string)$body, true);
    $jsonOk = json_last_error() === JSON_ERROR_NONE;
    $apiError = is_array($decoded) && in_array(strtoupper((string)($decoded['status'] ?? '')), ['ERROR', 'FAILED', 'FAILURE'], true);
    $ok = $error === '' && $http >= 200 && $http < 300 && $jsonOk && !$apiError;
    $responseError = null;
    if ($error !== '') {
        $responseError = $error;
    } elseif (!$jsonOk) {
        $responseError = 'Invalid JSON response from MyOrderBox. HTTP ' . $http . ': ' . substr(trim((string)$body), 0, 500);
    } elseif ($apiError) {
        $responseError = hivenest_mob_response_error($decoded, 'MyOrderBox API returned ERROR.');
    } elseif (!$ok) {
        $responseError = 'MyOrderBox HTTP ' . $http . ': ' . hivenest_mob_response_error($decoded, 'Provider request was rejected.');
    }
    $result = [
        'ok' => $ok,
        'status' => $http,
        'data' => $jsonOk ? $decoded : null,
        'error' => $responseError,
        'raw_body' => !$jsonOk ? substr(trim((string)$body), 0, 1000) : null,
    ];
    $loggedResult = $result;
    if ($redactResponse) {
        $loggedResult['data'] = $result['data'] === null ? null : '[REDACTED SENSITIVE PROVIDER RESPONSE]';
        $loggedResult['raw_body'] = $result['raw_body'] === null ? null : '[REDACTED SENSITIVE PROVIDER RESPONSE]';
    }
    hivenest_bridge_api_log($db, $endpoint, $masked, $loggedResult, $result['ok'] ? 'success' : 'failed');
    return $result;
}

function hivenest_mob_get(PDO $db, string $endpoint, array $params): array {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'PHP cURL extension is not enabled.'];
    }
    $overrideResellerId = trim((string)($params['__auth_userid'] ?? ''));
    $overrideApiKey = trim((string)($params['__api_key'] ?? ''));
    $overrideEnv = trim((string)($params['__mob_env'] ?? ''));
    unset($params['__auth_userid'], $params['__api_key'], $params['__mob_env']);

    $resellerId = $overrideResellerId !== '' ? $overrideResellerId : hivenest_bridge_env('MYORDERBOX_RESELLER_ID');
    $apiKey = $overrideApiKey !== '' ? $overrideApiKey : hivenest_bridge_env('MYORDERBOX_API_KEY');
    if ($resellerId === '' || $apiKey === '') {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'MyOrderBox credentials are not configured.'];
    }

    $payload = array_merge(['auth-userid' => $resellerId, 'api-key' => $apiKey], $params);
    $masked = hivenest_mob_mask_sensitive($payload);
    $url = hivenest_mob_base_url($overrideEnv !== '' ? $overrideEnv : null) . $endpoint . '?' . hivenest_mob_query($payload);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $decoded = json_decode((string)$body, true);
    $jsonOk = json_last_error() === JSON_ERROR_NONE;
    $apiError = is_array($decoded) && strtoupper((string)($decoded['status'] ?? '')) === 'ERROR';
    $result = [
        'ok' => $error === '' && $http >= 200 && $http < 300 && $jsonOk && !$apiError,
        'status' => $http,
        'data' => $jsonOk ? $decoded : null,
        'error' => $error !== '' ? $error : (!$jsonOk ? 'Invalid JSON response from MyOrderBox.' : ($apiError ? (string)($decoded['message'] ?? $decoded['error'] ?? 'MyOrderBox API returned ERROR.') : null)),
    ];
    hivenest_bridge_api_log($db, $endpoint, $masked, $result, $result['ok'] ? 'success' : 'failed');
    return $result;
}

function hivenest_mob_plan_details(PDO $db, string $productKey = '', string $authUserId = '', string $apiKey = '', string $environment = ''): array {
    $params = [];
    $productKey = trim($productKey);
    if ($productKey !== '') {
        $params['product-key'] = $productKey;
    }
    $authUserId = trim($authUserId);
    $apiKey = trim($apiKey);
    if ($authUserId !== '') {
        $params['__auth_userid'] = $authUserId;
    }
    if ($apiKey !== '') {
        $params['__api_key'] = $apiKey;
    }
    $environment = trim($environment);
    if ($environment !== '') {
        $params['__mob_env'] = $environment;
    }
    return hivenest_mob_get($db, '/api/products/plan-details.json', $params);
}

function hivenest_mob_reseller_balance(PDO $db): array {
    $resellerId = trim(hivenest_bridge_env(
        'MYORDERBOX_BALANCE_RESELLER_ID',
        hivenest_bridge_env('MYORDERBOX_RESELLER_ID')
    ));
    if ($resellerId === '' || !ctype_digit($resellerId)) {
        return ['ok' => false, 'error' => 'A numeric MyOrderBox balance reseller ID is required.'];
    }

    $result = hivenest_mob_get($db, '/api/billing/reseller-balance.json', [
        'reseller-id' => $resellerId,
    ]);
    if (!$result['ok']) return $result;

    $data = is_array($result['data']) ? $result['data'] : [];
    $available = $data['sellingcurrencybalance'] ?? null;
    $locked = $data['sellingcurrencylockedbalance'] ?? 0;
    if (!is_numeric($available)) {
        return [
            'ok' => false,
            'error' => 'MyOrderBox balance response did not contain sellingcurrencybalance.',
            'data' => $data,
        ];
    }

    return [
        'ok' => true,
        'reseller_id' => $resellerId,
        'currency' => trim((string)($data['sellingcurrencysymbol'] ?? '')),
        'available' => (float)$available,
        'locked' => is_numeric($locked) ? (float)$locked : 0.0,
        'data' => $data,
    ];
}

function hivenest_mob_rest_product_order_lookup(PDO $db, string $productKey, string $orderId = '', string $domainName = ''): array {
    $productKey = strtolower(trim($productKey));
    $orderId = trim($orderId);
    $domainName = strtolower(trim($domainName));

    if ($productKey === '' || !preg_match('/^[a-z0-9]+$/', $productKey)) {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Enter a valid MyOrderBox product key, for example wordpresshostingusa.'];
    }
    if ($orderId === '' && $domainName === '') {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Enter an existing MyOrderBox Order ID or domain name to inspect.'];
    }

    $params = [];
    if ($orderId !== '') {
        $params['order-id'] = $orderId;
    }
    if ($domainName !== '') {
        $params['domain-name'] = $domainName;
    }

    return hivenest_mob_get($db, '/restapi/product/' . $productKey, $params);
}

function hivenest_bridge_country_code(string $country): string {
    $country = trim($country);
    if (preg_match('/^[A-Z]{2}$/', $country)) return strtoupper($country);
    $map = [
        'south africa' => 'ZA',
        'united states' => 'US',
        'usa' => 'US',
        'united kingdom' => 'GB',
        'england' => 'GB',
        'singapore' => 'SG',
        'europe' => 'DE',
    ];
    return $map[strtolower($country)] ?? 'ZA';
}

function hivenest_bridge_phone_parts(string $phone, string $countryCode): array {
    $digits = preg_replace('/\D+/', '', $phone) ?: '';
    $cc = [
        'ZA' => '27',
        'US' => '1',
        'GB' => '44',
        'SG' => '65',
        'DE' => '49',
    ][$countryCode] ?? '27';

    if ($digits === '') return [$cc, '000000000'];
    if (str_starts_with($digits, $cc) && strlen($digits) > strlen($cc) + 4) {
        $digits = substr($digits, strlen($cc));
    }
    if (str_starts_with($digits, '0') && strlen($digits) > 6) {
        $digits = substr($digits, 1);
    }
    return [$cc, $digits];
}

function hivenest_mob_customer_from_search(array $data, string $email): ?string {
    $email = strtolower($email);
    $candidates = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            if (isset($value['username']) || isset($value['customerid'])) {
                $candidates[] = $value;
            } else {
                foreach ($value as $nested) {
                    if (is_array($nested)) $candidates[] = $nested;
                }
            }
        }
    }
    foreach ($candidates as $customer) {
        $username = strtolower((string)($customer['username'] ?? $customer['customer.username'] ?? ''));
        if ($username === $email && !empty($customer['customerid'])) return (string)$customer['customerid'];
        if ($username === $email && !empty($customer['customer.customerid'])) return (string)$customer['customer.customerid'];
    }
    return null;
}

function hivenest_mob_temp_password(): string {
    // MyOrderBox requires 9-16 chars with lowercase, uppercase, number and an allowed special char.
    $lower = 'abcdefghijkmnopqrstuvwxyz';
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $digits = '23456789';
    $special = '~*!@$#%_+.?:,{}';
    $all = $lower . $upper . $digits;

    $password = [
        $upper[random_int(0, strlen($upper) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $digits[random_int(0, strlen($digits) - 1)],
        $special[random_int(0, strlen($special) - 1)],
    ];
    for ($i = 0; $i < 8; $i++) {
        $password[] = $all[random_int(0, strlen($all) - 1)];
    }
    shuffle($password);
    return implode('', $password);
}

function hivenest_mob_sync_customer(PDO $db, int $customerId): array {
    $stmt = $db->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $customerId]);
    $customer = $stmt->fetch();
    if (!$customer) return ['ok' => false, 'customer_id' => null, 'error' => 'Local customer not found.'];

    if (!empty($customer['myorderbox_customer_id'])) {
        return ['ok' => true, 'customer_id' => (string)$customer['myorderbox_customer_id'], 'source' => 'local'];
    }

    $email = strtolower(trim((string)$customer['email']));
    $search = hivenest_mob_get($db, '/api/customers/search.json', [
        'username' => $email,
        'no-of-records' => 10,
        'page-no' => 1,
    ]);
    if ($search['ok']) {
        $existingId = hivenest_mob_customer_from_search($search['data'] ?? [], $email);
        if ($existingId) {
            $db->prepare("UPDATE customers SET myorderbox_customer_id=:mob_id, myorderbox_sync_status='synced', myorderbox_last_sync_at=NOW(), myorderbox_sync_error=NULL WHERE id=:id")
                ->execute(['mob_id' => $existingId, 'id' => $customerId]);
            return ['ok' => true, 'customer_id' => $existingId, 'source' => 'search'];
        }
    }

    $countryCode = hivenest_bridge_country_code((string)($customer['country_code'] ?: $customer['country'] ?: 'ZA'));
    [$phoneCc, $phone] = hivenest_bridge_phone_parts((string)($customer['phone'] ?? ''), $countryCode);
    $name = trim((string)($customer['first_name'] ?? '') . ' ' . (string)($customer['last_name'] ?? ''));
    if ($name === '') $name = $email;
    $company = trim((string)($customer['company_name'] ?? ''));
    if ($company === '') $company = $name;
    $state = trim((string)($customer['state'] ?? ''));
    if ($state === '') $state = 'Not Applicable';
    $address = trim((string)($customer['address_line1'] ?? ''));
    if ($address === '') $address = 'Not Provided';
    $city = trim((string)($customer['city'] ?? ''));
    if ($city === '') $city = 'Not Provided';
    $zipcode = trim((string)($customer['postal_code'] ?? ''));
    if ($zipcode === '') $zipcode = '0000';

    $signup = hivenest_mob_request($db, '/api/customers/v2/signup.json', [
        'username' => $email,
        'passwd' => hivenest_mob_temp_password(),
        'name' => $name,
        'company' => $company,
        'address-line-1' => $address,
        'address-line-2' => trim((string)($customer['address_line2'] ?? '')),
        'city' => $city,
        'state' => $state,
        'country' => $countryCode,
        'zipcode' => $zipcode,
        'phone-cc' => $phoneCc,
        'phone' => $phone,
        'lang-pref' => 'en',
        'accept-policy' => 'true',
        'marketing-email-consent' => 'false',
    ]);

    if (!$signup['ok']) {
        $error = $signup['error'] ?: 'MyOrderBox customer signup failed.';
        $db->prepare("UPDATE customers SET myorderbox_sync_status='failed', myorderbox_sync_error=:error WHERE id=:id")
            ->execute(['error' => $error, 'id' => $customerId]);
        return ['ok' => false, 'customer_id' => null, 'error' => $error, 'response' => $signup];
    }

    $data = $signup['data'];
    $mobId = null;
    if (is_scalar($data)) {
        $mobId = (string)$data;
    } elseif (is_array($data)) {
        $mobId = (string)($data['customerid'] ?? $data['customer-id'] ?? $data['id'] ?? $data[0] ?? '');
    }
    if ($mobId === '') {
        $error = 'MyOrderBox customer signup did not return a customer ID.';
        $db->prepare("UPDATE customers SET myorderbox_sync_status='failed', myorderbox_sync_error=:error WHERE id=:id")
            ->execute(['error' => $error, 'id' => $customerId]);
        return ['ok' => false, 'customer_id' => null, 'error' => $error, 'response' => $signup];
    }

    $db->prepare("UPDATE customers SET myorderbox_customer_id=:mob_id, myorderbox_sync_status='synced', myorderbox_last_sync_at=NOW(), myorderbox_sync_error=NULL WHERE id=:id")
        ->execute(['mob_id' => $mobId, 'id' => $customerId]);
    return ['ok' => true, 'customer_id' => $mobId, 'source' => 'signup'];
}

function hivenest_mob_get_or_create_contact_set(PDO $db, int $customerId, string $mobCustomerId): array {
    $existing = $db->prepare('SELECT * FROM myorderbox_contacts WHERE customer_id=:customer_id AND contact_type="Contact" LIMIT 1');
    $existing->execute(['customer_id' => $customerId]);
    $row = $existing->fetch();
    if ($row) {
        return [
            'ok' => true,
            'reg_contact_id' => (string)$row['registrant_contact_id'],
            'admin_contact_id' => (string)$row['admin_contact_id'],
            'tech_contact_id' => (string)$row['tech_contact_id'],
            'billing_contact_id' => (string)$row['billing_contact_id'],
            'source' => 'local',
        ];
    }

    $stmt = $db->prepare('SELECT * FROM customers WHERE id=:id LIMIT 1');
    $stmt->execute(['id' => $customerId]);
    $customer = $stmt->fetch();
    if (!$customer) return ['ok' => false, 'error' => 'Local customer not found for contact creation.'];

    $countryCode = hivenest_bridge_country_code((string)($customer['country_code'] ?: $customer['country'] ?: 'ZA'));
    [$phoneCc, $phone] = hivenest_bridge_phone_parts((string)($customer['phone'] ?? ''), $countryCode);
    $name = trim((string)($customer['first_name'] ?? '') . ' ' . (string)($customer['last_name'] ?? ''));
    if ($name === '') $name = (string)$customer['email'];
    $company = trim((string)($customer['company_name'] ?? ''));
    if ($company === '') $company = 'N/A';
    $state = trim((string)($customer['state'] ?? ''));
    if ($state === '') $state = 'Not Applicable';
    $address = trim((string)($customer['address_line1'] ?? ''));
    if ($address === '') $address = 'Not Provided';
    $city = trim((string)($customer['city'] ?? ''));
    if ($city === '') $city = 'Not Provided';
    $zipcode = trim((string)($customer['postal_code'] ?? ''));
    if ($zipcode === '') $zipcode = '0000';

    $payload = [
        'name' => $name,
        'company' => $company,
        'email' => (string)$customer['email'],
        'address-line-1' => $address,
        'address-line-2' => trim((string)($customer['address_line2'] ?? '')),
        'city' => $city,
        'state' => $state,
        'country' => $countryCode,
        'zipcode' => $zipcode,
        'phone-cc' => $phoneCc,
        'phone' => $phone,
        'customer-id' => $mobCustomerId,
        'type' => 'Contact',
    ];

    $created = hivenest_mob_request($db, '/api/contacts/add.json', $payload);
    if (!$created['ok']) {
        return ['ok' => false, 'error' => $created['error'] ?: 'MyOrderBox contact creation failed.', 'response' => $created];
    }

    $data = $created['data'];
    $contactId = is_scalar($data) ? (string)$data : (string)($data['entityid'] ?? $data['contactid'] ?? $data['contact-id'] ?? $data['id'] ?? $data[0] ?? '');
    if ($contactId === '') {
        return ['ok' => false, 'error' => 'MyOrderBox contact creation did not return a contact ID.', 'response' => $created];
    }

    $insert = $db->prepare("
        INSERT INTO myorderbox_contacts
            (uuid, customer_id, myorderbox_customer_id, contact_type, registrant_contact_id, admin_contact_id, tech_contact_id, billing_contact_id, contact_payload, provider_response)
        VALUES
            (:uuid, :customer_id, :mob_customer_id, 'Contact', :contact_id, :contact_id2, :contact_id3, :contact_id4, :payload, :response)
    ");
    $insert->execute([
        'uuid' => hivenest_bridge_uuid(),
        'customer_id' => $customerId,
        'mob_customer_id' => $mobCustomerId,
        'contact_id' => $contactId,
        'contact_id2' => $contactId,
        'contact_id3' => $contactId,
        'contact_id4' => $contactId,
        'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        'response' => json_encode($created['data'], JSON_UNESCAPED_SLASHES),
    ]);

    return [
        'ok' => true,
        'reg_contact_id' => $contactId,
        'admin_contact_id' => $contactId,
        'tech_contact_id' => $contactId,
        'billing_contact_id' => $contactId,
        'source' => 'created',
    ];
}

function hivenest_bridge_bundle_items(array $config): array {
    $items = $config['bundle_items'] ?? [];
    if (is_string($items)) {
        $decoded = json_decode($items, true);
        $items = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($items)) return [];

    $normalized = [];
    foreach ($items as $index => $item) {
        if (!is_array($item)) continue;
        $sku = trim((string)($item['sku'] ?? ''));
        $name = trim((string)($item['name'] ?? $sku));
        if ($sku === '' && $name === '') continue;
        $provider = strtolower(trim((string)($item['provider'] ?? '')));
        if ($provider === '') {
            $provider = in_array((string)($item['job_type'] ?? ''), ['design_queue','marketing_queue','manual_queue'], true)
                ? 'hivenest_team'
                : 'myorderbox';
        }
        $jobType = trim((string)($item['job_type'] ?? ''));
        if ($jobType === '') {
            $serviceType = hivenest_bridge_service_type((string)($item['product_type'] ?? ''), '', $sku, $name);
            $jobType = hivenest_bridge_job_type($serviceType, $sku, $name);
        }
        $normalized[] = array_merge($item, [
            'bundle_index' => $index,
            'sku' => $sku,
            'name' => $name !== '' ? $name : $sku,
            'job_type' => $jobType,
            'provider' => $provider,
        ]);
    }
    return $normalized;
}

function hivenest_bridge_bundle_item_domain(array $bundleItem, array $orderItem, array $config): string {
    $domain = strtolower(trim((string)($bundleItem['domain_name'] ?? $bundleItem['domain'] ?? $bundleItem['primary_domain'] ?? '')));
    if ($domain !== '') return $domain;
    $domainSource = strtolower(trim((string)($bundleItem['domain_source'] ?? '')));
    if ($domainSource === 'none') return '';

    foreach (['domain_name', 'domain', 'primary_domain'] as $key) {
        if (!empty($config[$key])) return strtolower(trim((string)$config[$key]));
    }
    return strtolower(trim((string)($orderItem['domain_name'] ?? '')));
}

function hivenest_mob_default_nameservers(): array {
    $raw = trim(hivenest_bridge_env('MYORDERBOX_DEFAULT_NAMESERVERS', 'ns1.hivenest.co.za,ns2.hivenest.co.za'));
    $nameservers = array_values(array_filter(array_map('trim', explode(',', $raw))));
    if (count($nameservers) < 2) return ['ns1.hivenest.co.za', 'ns2.hivenest.co.za'];
    return array_slice($nameservers, 0, 13);
}

function hivenest_mob_domain_tld(string $domain): string {
    $parts = explode('.', strtolower($domain));
    if (count($parts) >= 3 && in_array(implode('.', array_slice($parts, -2)), ['co.za','org.za','net.za','web.za'], true)) {
        return implode('.', array_slice($parts, -2));
    }
    return (string)end($parts);
}

function hivenest_mob_check_domain_available(PDO $db, string $domain): array {
    $domain = strtolower(trim($domain));
    $tld = hivenest_mob_domain_tld($domain);
    $name = substr($domain, 0, -(strlen($tld) + 1));
    if ($name === '' || $tld === '') return ['ok' => false, 'available' => false, 'error' => 'Invalid domain name for availability check.'];

    $base = rtrim(hivenest_bridge_env('MYORDERBOX_DOMAIN_CHECK_URL', 'https://domaincheck.httpapi.com'), '/');
    $resellerId = hivenest_bridge_env('MYORDERBOX_RESELLER_ID');
    $apiKey = hivenest_bridge_env('MYORDERBOX_API_KEY');
    if ($resellerId === '' || $apiKey === '') return ['ok' => false, 'available' => false, 'error' => 'MyOrderBox credentials are not configured.'];
    if (!function_exists('curl_init')) return ['ok' => false, 'available' => false, 'error' => 'PHP cURL extension is not enabled.'];

    $payload = [
        'auth-userid' => $resellerId,
        'api-key' => $apiKey,
        'domain-name' => $name,
        'tlds' => [$tld],
    ];
    $url = $base . '/api/domains/available.json?' . hivenest_mob_query($payload);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    $data = json_decode((string)$body, true);
    $ok = $error === '' && $http >= 200 && $http < 300 && json_last_error() === JSON_ERROR_NONE && is_array($data);
    hivenest_bridge_api_log($db, '/api/domains/available.json', ['domain-name' => $name, 'tlds' => [$tld], 'api-key' => '***'], ['status' => $http, 'data' => $data, 'error' => $error], $ok ? 'success' : 'failed');
    if (!$ok) return ['ok' => false, 'available' => false, 'error' => $error ?: 'Invalid availability response from MyOrderBox.'];

    $info = $data[$domain] ?? null;
    $status = strtolower((string)($info['status'] ?? ''));
    return ['ok' => true, 'available' => $status === 'available', 'status' => $status, 'raw' => $info ?: $data];
}

function hivenest_mob_register_domain(PDO $db, array $job): array {
    $phase = 'loading domain job';
    try {
        $stmt = $db->prepare("
            SELECT
                j.*,
                o.order_number,
                c.myorderbox_customer_id,
                oi.product_name,
                oi.domain_name,
                oi.product_config,
                oi.billing_cycle,
                oi.service_id
            FROM provisioning_jobs j
            INNER JOIN orders o ON o.id=j.order_id
            INNER JOIN customers c ON c.id=j.customer_id
            INNER JOIN order_items oi ON oi.id=j.order_item_id
            WHERE j.id=:job_id
            LIMIT 1
        ");
        $stmt->execute(['job_id' => (int)$job['id']]);
        $row = $stmt->fetch();
        if (!$row) return ['ok' => false, 'status' => 'failed', 'error' => 'Domain registration job details not found.'];

        $domain = strtolower(trim((string)$row['domain_name']));
        if ($domain === '') return ['ok' => false, 'status' => 'manual_review', 'error' => 'Domain registration job has no domain name.'];
        $mobCustomerId = trim((string)$row['myorderbox_customer_id']);
        if ($mobCustomerId === '') return ['ok' => false, 'status' => 'manual_review', 'error' => 'Customer has no MyOrderBox customer ID.'];

        $phase = 'checking domain availability';
        $available = hivenest_mob_check_domain_available($db, $domain);
        if (!$available['ok']) return ['ok' => false, 'status' => 'retry', 'error' => $available['error'] ?? 'Could not verify domain availability.', 'response' => $available];
        if (!$available['available']) return ['ok' => false, 'status' => 'manual_review', 'error' => 'Domain is no longer available: ' . ($available['status'] ?? 'unknown'), 'response' => $available];

        $phase = 'creating/finding domain contacts';
        $contacts = hivenest_mob_get_or_create_contact_set($db, (int)$row['customer_id'], $mobCustomerId);
        if (!$contacts['ok']) return ['ok' => false, 'status' => 'retry', 'error' => $contacts['error'] ?? 'Could not create domain contacts.', 'response' => $contacts];

        $config = json_decode((string)($row['product_config'] ?? ''), true);
        if (!is_array($config)) $config = [];
        $years = max(1, min(10, (int)($config['years'] ?? 1)));
        $nameservers = hivenest_mob_default_nameservers();
        $phase = 'checking domain privacy add-on';
        $privacyStmt = $db->prepare("
            SELECT COUNT(*)
            FROM order_items
            WHERE order_id = :order_id
              AND domain_name = :domain_name
              AND (
                    LOWER(product_name) LIKE '%privacy%'
                 OR JSON_EXTRACT(product_config, '$.sku') = 'domain-privacy'
              )
        ");
        $privacyStmt->execute(['order_id' => (int)$row['order_id'], 'domain_name' => $domain]);
        $purchasePrivacy = (int)$privacyStmt->fetchColumn() > 0;
        $payload = [
            'domain-name' => $domain,
            'years' => $years,
            'ns' => $nameservers,
            'customer-id' => $mobCustomerId,
            'reg-contact-id' => $contacts['reg_contact_id'],
            'admin-contact-id' => $contacts['admin_contact_id'],
            'tech-contact-id' => $contacts['tech_contact_id'],
            'billing-contact-id' => $contacts['billing_contact_id'],
            'invoice-option' => 'PayInvoice',
            'protect-privacy' => $purchasePrivacy ? 'true' : 'false',
            // HiveNest creates and collects its own renewal invoices. Native
            // provider auto-renew must stay off to prevent duplicate renewals.
            'auto-renew' => 'false',
        ];
        if ($purchasePrivacy) {
            $payload['purchase-privacy'] = 'true';
        }

        $phase = 'submitting domain registration to MyOrderBox';
        $register = hivenest_mob_request($db, '/api/domains/register.json', $payload);
        if (!$register['ok']) {
            return ['ok' => false, 'status' => 'retry', 'error' => $register['error'] ?: 'Domain registration API failed.', 'request' => $payload, 'response' => $register];
        }

        $data = is_array($register['data']) ? $register['data'] : ['value' => $register['data']];
        $providerOrderId = (string)($data['entityid'] ?? $data['orderid'] ?? $data['order-id'] ?? '');
        $providerActionId = (string)($data['eaqid'] ?? $data['actionid'] ?? $data['action-id'] ?? '');
        $providerInvoiceId = (string)($data['invoiceid'] ?? $data['invoice-id'] ?? '');
        $providerStatus = (string)($data['actionstatus'] ?? $data['actionstatusdesc'] ?? 'submitted');

        $serviceId = (int)$row['service_id'];
        if ($serviceId > 0) {
            $expiry = gmdate('Y-m-d H:i:s', strtotime('+' . $years . ' years'));
            $phase = 'saving local domain registration';
            $db->prepare("
                INSERT INTO domain_registrations
                    (uuid, service_id, customer_id, domain_name, extension, expiry_date, nameservers, privacy_protection, registrar_status, provider_order_id, provider_action_id, provider_invoice_id, provider_status, provider_response)
                VALUES
                    (:uuid, :service_id, :customer_id, :domain_name, :extension, :expiry_date, :nameservers, :privacy_protection, 'active', :provider_order_id, :provider_action_id, :provider_invoice_id, :provider_status, :provider_response)
            ")->execute([
                'uuid' => hivenest_bridge_uuid(),
                'service_id' => $serviceId,
                'customer_id' => (int)$row['customer_id'],
                'domain_name' => $domain,
                'extension' => '.' . hivenest_mob_domain_tld($domain),
                'expiry_date' => $expiry,
                'nameservers' => json_encode($nameservers, JSON_UNESCAPED_SLASHES),
                'privacy_protection' => $purchasePrivacy ? 1 : 0,
                'provider_order_id' => $providerOrderId ?: null,
                'provider_action_id' => $providerActionId ?: null,
                'provider_invoice_id' => $providerInvoiceId ?: null,
                'provider_status' => $providerStatus,
                'provider_response' => json_encode($data, JSON_UNESCAPED_SLASHES),
            ]);

            $phase = 'updating local domain service';
            $serviceConfigStmt = $db->prepare('SELECT service_config FROM services WHERE id=:service_id LIMIT 1');
            $serviceConfigStmt->execute(['service_id' => $serviceId]);
            $serviceConfig = json_decode((string)$serviceConfigStmt->fetchColumn(), true);
            if (!is_array($serviceConfig)) $serviceConfig = [];
            $serviceConfig['provider_order_id'] = $providerOrderId;
            $serviceConfig['provider_action_id'] = $providerActionId;
            $serviceConfig['provider_invoice_id'] = $providerInvoiceId;
            $serviceConfig['provider_status'] = $providerStatus;

            $db->prepare("
                UPDATE services
                SET service_status='active',
                    setup_date=NOW(),
                    expiry_date=:expiry_date,
                    next_due_date=:next_due_date,
                    service_config=:service_config
                WHERE id=:service_id
            ")->execute([
                'expiry_date' => $expiry,
                'next_due_date' => $expiry,
                'service_config' => json_encode($serviceConfig, JSON_UNESCAPED_SLASHES),
                'service_id' => $serviceId,
            ]);
        }

        return [
            'ok' => true,
            'status' => 'completed',
            'provider_order_id' => $providerOrderId,
            'provider_action_id' => $providerActionId,
            'provider_invoice_id' => $providerInvoiceId,
            'response' => $data,
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'status' => 'retry',
            'error' => 'Domain registration failed while ' . $phase . ': ' . $e->getMessage(),
        ];
    }
}

function hivenest_mob_months_from_cycle(string $cycle, int $defaultMonths): int {
    $map = [
        'monthly' => 1,
        'quarterly' => 3,
        'semi_annually' => 6,
        'annually' => 12,
        'biennially' => 24,
        'triennially' => 36,
    ];
    return max(1, (int)($map[$cycle] ?? $defaultMonths));
}

function hivenest_mob_response_scalars(array $data, int $depth = 0): array {
    if ($depth > 8) return [];

    $scalars = [];
    foreach ($data as $key => $value) {
        $normalizedKey = strtolower((string)preg_replace('/[^a-z0-9]+/i', '', (string)$key));
        if (is_array($value)) {
            foreach (hivenest_mob_response_scalars($value, $depth + 1) as $nestedKey => $nestedValues) {
                if (!isset($scalars[$nestedKey])) $scalars[$nestedKey] = [];
                $scalars[$nestedKey] = array_merge($scalars[$nestedKey], $nestedValues);
            }
            continue;
        }
        if (!is_scalar($value) || $normalizedKey === '') continue;
        $scalar = trim((string)$value);
        if ($scalar === '') continue;
        $scalars[$normalizedKey][] = $scalar;
    }
    return $scalars;
}

function hivenest_mob_first_response_scalar(array $scalars, array $keys): string {
    foreach ($keys as $key) {
        $normalizedKey = strtolower((string)preg_replace('/[^a-z0-9]+/i', '', (string)$key));
        foreach (($scalars[$normalizedKey] ?? []) as $value) {
            $value = trim((string)$value);
            if ($value !== '') return $value;
        }
    }
    return '';
}

function hivenest_mob_validate_provider_order_response(array $data, string $jobType): array {
    $scalars = hivenest_mob_response_scalars($data);

    $entityId = hivenest_mob_first_response_scalar($scalars, [
        'entityid', 'entity-id', 'entity_id', 'serviceid', 'service-id', 'service_id',
    ]);
    $orderId = hivenest_mob_first_response_scalar($scalars, [
        'orderid', 'order-id', 'order_id',
    ]);
    $actionId = hivenest_mob_first_response_scalar($scalars, [
        'eaqid', 'actionid', 'action-id', 'action_id',
    ]);
    $invoiceId = hivenest_mob_first_response_scalar($scalars, [
        'invoiceid', 'invoice-id', 'invoice_id',
    ]);
    $providerStatus = hivenest_mob_first_response_scalar($scalars, [
        'actionstatusdesc', 'actionstatus', 'currentstatus', 'orderstatus', 'status',
    ]);

    // Some MyOrderBox order endpoints return only the new numeric order ID.
    if ($orderId === '' && $entityId === '' && isset($data['value']) && is_scalar($data['value'])) {
        $value = trim((string)$data['value']);
        if ($value !== '' && ctype_digit($value) && (int)$value > 0) {
            $orderId = $value;
        }
    }

    $explicitError = hivenest_mob_first_response_scalar($scalars, [
        'errormessage', 'error-message', 'error_description', 'errordescription', 'error',
    ]);
    $errorCode = hivenest_mob_first_response_scalar($scalars, ['errorcode', 'error-code', 'error_code']);
    $normalizedError = strtolower(trim($explicitError));
    $hasExplicitError = $normalizedError !== ''
        && !in_array($normalizedError, ['0', 'false', 'none', 'null', 'no error'], true);
    if ($errorCode !== '' && !in_array(strtolower($errorCode), ['0', 'false', 'none', 'null'], true)) {
        $hasExplicitError = true;
        if ($explicitError === '') $explicitError = 'Provider error code ' . $errorCode;
    }

    $normalizedStatus = strtolower(trim($providerStatus));
    $failedState = $normalizedStatus !== '' && (
        preg_match('/(^|[\s_-])(error|failed|failure|rejected|cancelled|canceled|denied|invalid)([\s_-]|$)/', $normalizedStatus) === 1
    );

    $providerOrderId = $orderId !== '' ? $orderId : $entityId;
    $providerEntityId = $entityId !== '' ? $entityId : $orderId;
    $hasProviderReference = $providerOrderId !== '' || $providerEntityId !== '' || $actionId !== '';

    if ($hasExplicitError || $failedState) {
        $reason = $explicitError !== ''
            ? $explicitError
            : 'MyOrderBox returned provider status "' . $providerStatus . '".';
        return [
            'ok' => false,
            'status' => 'manual_review',
            'error' => 'Provider rejected or failed the ' . $jobType . ' order: ' . $reason,
            'provider_order_id' => $providerOrderId,
            'provider_action_id' => $actionId,
            'provider_entity_id' => $providerEntityId,
            'provider_invoice_id' => $invoiceId,
            'provider_status' => $providerStatus,
        ];
    }

    if (!$hasProviderReference) {
        return [
            'ok' => false,
            'status' => 'manual_review',
            'error' => 'MyOrderBox returned HTTP/API success for ' . $jobType
                . ' but no provider order, entity, or action ID. Verify the provider before completing or retrying this job.',
            'provider_order_id' => '',
            'provider_action_id' => '',
            'provider_entity_id' => '',
            'provider_invoice_id' => $invoiceId,
            'provider_status' => $providerStatus,
        ];
    }

    return [
        'ok' => true,
        'status' => 'completed',
        'provider_order_id' => $providerOrderId,
        'provider_action_id' => $actionId,
        'provider_entity_id' => $providerEntityId,
        'provider_invoice_id' => $invoiceId,
        'provider_status' => $providerStatus !== '' ? $providerStatus : 'submitted',
    ];
}

function hivenest_mob_extract_provider_ids(array $data): array {
    $validated = hivenest_mob_validate_provider_order_response($data, 'provider');
    return [
        'provider_order_id' => (string)($validated['provider_order_id'] ?? ''),
        'provider_action_id' => (string)($validated['provider_action_id'] ?? ''),
        'provider_entity_id' => (string)($validated['provider_entity_id'] ?? ''),
        'provider_invoice_id' => (string)($validated['provider_invoice_id'] ?? ''),
        'provider_status' => (string)($validated['provider_status'] ?? 'submitted'),
    ];
}

function hivenest_mob_process_mapped_provider_order(PDO $db, array $job): array {
    $stmt = $db->prepare("
        SELECT
            j.*,
            o.order_number,
            c.myorderbox_customer_id,
            oi.product_id,
            oi.product_name,
            oi.domain_name,
            oi.product_config,
            oi.billing_cycle,
            oi.service_id
        FROM provisioning_jobs j
        INNER JOIN orders o ON o.id=j.order_id
        INNER JOIN customers c ON c.id=j.customer_id
        INNER JOIN order_items oi ON oi.id=j.order_item_id
        WHERE j.id=:job_id
        LIMIT 1
    ");
    $stmt->execute(['job_id' => (int)$job['id']]);
    $row = $stmt->fetch();
    if (!$row) return ['ok' => false, 'status' => 'failed', 'error' => 'Provider job details not found.'];

    $mobCustomerId = trim((string)$row['myorderbox_customer_id']);
    if ($mobCustomerId === '') return ['ok' => false, 'status' => 'manual_review', 'error' => 'Customer has no MyOrderBox customer ID.'];

    $config = json_decode((string)($row['product_config'] ?? ''), true);
    if (!is_array($config)) $config = [];
    $jobPayload = json_decode((string)($row['request_payload'] ?? ''), true);
    if (!is_array($jobPayload)) $jobPayload = [];
    $sku = (string)($jobPayload['sku'] ?? $config['sku'] ?? '');
    if ($sku === '') return ['ok' => false, 'status' => 'manual_review', 'error' => 'Order item has no provider SKU.'];

    $mappingStmt = $db->prepare("
        SELECT *
        FROM product_provider_mappings
        WHERE is_active=1
          AND provider='myorderbox'
          AND job_type=:job_type
          AND (product_sku=:sku OR product_sku=:product_sku_base)
        ORDER BY CASE WHEN product_sku=:sku_order THEN 0 ELSE 1 END
        LIMIT 1
    ");
    $baseSku = str_contains($sku, '--') ? substr($sku, 0, strpos($sku, '--')) : $sku;
    $mappingStmt->execute([
        'job_type' => $job['job_type'],
        'sku' => $sku,
        'product_sku_base' => $baseSku,
        'sku_order' => $sku,
    ]);
    $mapping = $mappingStmt->fetch();
    if (!$mapping) {
        return [
            'ok' => false,
            'status' => 'manual_review',
            'error' => 'No active MyOrderBox provider mapping exists for SKU ' . $sku . '. Add it to product_provider_mappings before auto-provisioning this product.',
        ];
    }

    $extra = json_decode((string)($mapping['extra_params'] ?? ''), true);
    if (!is_array($extra)) $extra = [];

    $domain = strtolower(trim((string)($jobPayload['domain_name'] ?? $row['domain_name'] ?? '')));
    foreach (['domain', 'domain_name', 'primary_domain', 'hostname'] as $domainKey) {
        if ($domain === '' && !empty($jobPayload[$domainKey])) {
            $domain = strtolower(trim((string)$jobPayload[$domainKey]));
        }
        if ($domain === '' && !empty($config[$domainKey])) {
            $domain = strtolower(trim((string)$config[$domainKey]));
        }
    }
    if ($domain === '' && !empty($extra['__domain_name'])) {
        $domain = strtolower(trim((string)$extra['__domain_name']));
    }
    if ($domain === '') {
        $domainStmt = $db->prepare("
            SELECT DISTINCT domain_name
            FROM order_items
            WHERE order_id=:order_id
              AND id<>:order_item_id
              AND domain_name IS NOT NULL
              AND domain_name<>''
            LIMIT 2
        ");
        $domainStmt->execute(['order_id' => (int)$row['order_id'], 'order_item_id' => (int)$row['order_item_id']]);
        $domains = array_values(array_filter(array_map('strval', $domainStmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
        if (count($domains) === 1) {
            $domain = strtolower(trim($domains[0]));
        }
    }
    if ((int)$mapping['requires_domain'] === 1 && $domain === '') {
        return [
            'ok' => false,
            'status' => 'manual_review',
            'error' => 'Provider mapping requires a domain name, but this order item has none. Add a domain to the hosting order item or collect a primary domain before provisioning.',
        ];
    }

    $endpoint = (string)$mapping['provider_endpoint'];
    $months = hivenest_mob_months_from_cycle((string)$row['billing_cycle'], (int)$mapping['default_months']);
    foreach (['__months', '__tenure_months'] as $overrideKey) {
        if (isset($extra[$overrideKey]) && is_numeric($extra[$overrideKey])) {
            $months = max(1, min(120, (int)$extra[$overrideKey]));
            break;
        }
    }
    $payloadTermMonths = $jobPayload['term_months'] ?? $config['term_months'] ?? null;
    if (is_numeric($payloadTermMonths)) {
        $orderMonths = (int)$payloadTermMonths;
        if (in_array($orderMonths, [1, 3, 6, 12, 24, 36], true)) {
            $months = $orderMonths;
        }
    }
    $monthsParam = trim((string)($extra['__months_param'] ?? 'months'));
    if ($monthsParam === '') $monthsParam = 'months';
    if ($monthsParam === 'months' && str_contains(strtolower($endpoint), '/restapi/product/wordpresshostingusa/order')) {
        $monthsParam = 'noOfMonths';
    }
    $payload = [
        'customer-id' => $mobCustomerId,
        $monthsParam => $months,
        'plan-id' => (string)$mapping['provider_plan_id'],
        'invoice-option' => 'PayInvoice',
        'discount-amount' => '0.00',
        'auto-renew' => 'false',
    ];
    if ($domain !== '') $payload['domain-name'] = $domain;

    foreach ($extra as $key => $value) {
        $key = (string)$key;
        if ($key !== '' && !str_starts_with($key, '__') && strtolower($key) !== 'auto-renew') {
            $payload[$key] = $value;
        }
    }

    if (str_starts_with(strtolower($endpoint), '/restapi/product/')) {
        $payload['__send_params_in_query'] = true;
    }

    $result = hivenest_mob_request($db, $endpoint, $payload);
    if (!$result['ok']) {
        $providerError = (string)($result['error'] ?: 'MyOrderBox provider order failed.');
        $debugParts = [
            'endpoint=' . $endpoint,
            'plan-id=' . (string)$mapping['provider_plan_id'],
            $monthsParam . '=' . (string)$months,
        ];
        if ($domain !== '') {
            $debugParts[] = 'domain=' . $domain;
        }
        $providerErrorWithContext = $providerError . ' [' . implode(', ', $debugParts) . ']';
        $manualProviderErrors = ['invalid tenure', 'invalid plan', 'invalid plan-id'];
        $manualStatus = false;
        foreach ($manualProviderErrors as $manualProviderError) {
            if (str_contains(strtolower($providerError), $manualProviderError)) {
                $manualStatus = true;
                break;
            }
        }
        return [
            'ok' => false,
            'status' => $manualStatus ? 'manual_review' : 'retry',
            'error' => $providerErrorWithContext,
            'request' => $payload,
            'response' => $result,
        ];
    }

    $data = is_array($result['data']) ? $result['data'] : ['value' => $result['data']];
    $validation = hivenest_mob_validate_provider_order_response($data, (string)$job['job_type']);
    if (!$validation['ok']) {
        return [
            'ok' => false,
            'status' => 'manual_review',
            'error' => $validation['error'],
            'request' => $payload,
            'response' => $data,
            'provider_order_id' => $validation['provider_order_id'] ?? '',
            'provider_action_id' => $validation['provider_action_id'] ?? '',
            'provider_entity_id' => $validation['provider_entity_id'] ?? '',
            'provider_invoice_id' => $validation['provider_invoice_id'] ?? '',
            'provider_status' => $validation['provider_status'] ?? '',
        ];
    }
    $ids = $validation;
    $serviceId = (int)$row['service_id'];
    if ($serviceId > 0) {
        $nextDue = gmdate('Y-m-d H:i:s', strtotime('+' . $months . ' months'));
        $db->prepare("
            UPDATE services
            SET service_status='active',
                setup_date=NOW(),
                next_due_date=:next_due_date,
                service_config=JSON_SET(
                    COALESCE(service_config, '{}'),
                    '$.provider_order_id', :provider_order_id,
                    '$.provider_action_id', :provider_action_id,
                    '$.provider_entity_id', :provider_entity_id,
                    '$.provider_invoice_id', :provider_invoice_id,
                    '$.provider_status', :provider_status,
                    '$.provider_endpoint', :provider_endpoint
                )
            WHERE id=:service_id
        ")->execute([
            'next_due_date' => $nextDue,
            'provider_order_id' => $ids['provider_order_id'],
            'provider_action_id' => $ids['provider_action_id'],
            'provider_entity_id' => $ids['provider_entity_id'],
            'provider_invoice_id' => $ids['provider_invoice_id'],
            'provider_status' => $ids['provider_status'],
            'provider_endpoint' => $endpoint,
            'service_id' => $serviceId,
        ]);
    }

    return [
        'ok' => true,
        'status' => 'completed',
        'provider_order_id' => $ids['provider_order_id'],
        'provider_action_id' => $ids['provider_action_id'],
        'provider_entity_id' => $ids['provider_entity_id'],
        'provider_invoice_id' => $ids['provider_invoice_id'],
        'provider_status' => $ids['provider_status'],
        'response' => $data,
    ];
}

function hivenest_mob_process_service_renewal(PDO $db, array $job): array {
    $stmt = $db->prepare("
        SELECT
            j.id AS job_id,
            j.order_id,
            j.order_item_id,
            j.service_id,
            oi.product_id,
            oi.product_config,
            oi.domain_name,
            s.customer_id,
            s.service_type,
            s.next_due_date,
            s.auto_renew,
            s.service_config,
            sr.id AS renewal_id,
            sr.period_months,
            dr.id AS domain_registration_id,
            dr.provider_order_id AS domain_provider_order_id,
            dr.expiry_date AS domain_expiry_date,
            dr.privacy_protection
        FROM provisioning_jobs j
        INNER JOIN order_items oi ON oi.id=j.order_item_id
        INNER JOIN services s ON s.id=j.service_id
        INNER JOIN service_renewals sr ON sr.renewal_order_id=j.order_id AND sr.service_id=s.id
        LEFT JOIN domain_registrations dr ON dr.service_id=s.id
        WHERE j.id=:job_id
        LIMIT 1
    ");
    $stmt->execute(['job_id' => (int)$job['id']]);
    $row = $stmt->fetch();
    if (!$row) return ['ok' => false, 'status' => 'failed', 'error' => 'Renewal job details were not found.'];
    $db->prepare("
        UPDATE service_renewals
        SET status='provisioning', error_message=NULL
        WHERE id=:id
          AND status IN ('paid','failed','manual_review','provisioning')
    ")->execute(['id' => (int)$row['renewal_id']]);

    $itemConfig = json_decode((string)($row['product_config'] ?? ''), true);
    $itemConfig = is_array($itemConfig) ? $itemConfig : [];
    $serviceConfig = json_decode((string)($row['service_config'] ?? ''), true);
    $serviceConfig = is_array($serviceConfig) ? $serviceConfig : [];
    $sku = trim((string)($itemConfig['sku'] ?? $serviceConfig['sku'] ?? ''));
    $months = max(1, min(36, (int)($row['period_months'] ?? $itemConfig['renewal_period_months'] ?? 1)));
    $providerOrderId = trim((string)(
        $row['domain_provider_order_id']
        ?? $serviceConfig['provider_order_id']
        ?? $serviceConfig['provider_entity_id']
        ?? ''
    ));

    if ((string)$row['service_type'] === 'domain') {
        if ($providerOrderId === '' || !ctype_digit($providerOrderId)) {
            return ['ok' => false, 'status' => 'manual_review', 'error' => 'Domain renewal requires a numeric MyOrderBox provider order ID.'];
        }
        $expiry = (string)($row['domain_expiry_date'] ?: $row['next_due_date']);
        $expiryEpoch = strtotime($expiry);
        if ($expiryEpoch === false || $expiryEpoch <= 0) {
            return ['ok' => false, 'status' => 'manual_review', 'error' => 'Domain renewal requires the current provider expiry date.'];
        }
        $years = max(1, (int)ceil($months / 12));
        $result = hivenest_mob_request($db, '/api/domains/renew.json', [
            'order-id' => $providerOrderId,
            'years' => $years,
            'exp-date' => $expiryEpoch,
            'purchase-privacy' => (int)($row['privacy_protection'] ?? 0) === 1 ? 'true' : 'false',
            'auto-renew' => 'false',
            'invoice-option' => 'PayInvoice',
            'discount-amount' => '0.00',
        ]);
        $advance = '+' . $years . ' years';
    } else {
        $requiresProvider = !empty($serviceConfig['requires_provider_provisioning']);
        if (!$requiresProvider) {
            $newDue = gmdate('Y-m-d H:i:s', strtotime((string)$row['next_due_date'] . ' +' . $months . ' months'));
            $db->prepare("UPDATE services SET next_due_date=:next_due_date, expiry_date=COALESCE(expiry_date, :expiry_date) WHERE id=:service_id")
                ->execute(['next_due_date' => $newDue, 'expiry_date' => $newDue, 'service_id' => (int)$row['service_id']]);
            $db->prepare("UPDATE service_renewals SET status='completed', completed_at=NOW(), error_message=NULL WHERE id=:id")
                ->execute(['id' => (int)$row['renewal_id']]);
            return [
                'ok' => true,
                'status' => 'completed',
                'provider_order_id' => '',
                'provider_action_id' => '',
                'provider_entity_id' => '',
                'provider_invoice_id' => '',
                'provider_status' => 'hivenest_team_renewed',
                'response' => ['next_due_date' => $newDue],
            ];
        }
        if ($providerOrderId === '' || !ctype_digit($providerOrderId)) {
            return ['ok' => false, 'status' => 'manual_review', 'error' => 'Provider renewal requires a numeric MyOrderBox order ID on the existing service.'];
        }
        $mapping = $db->prepare("
            SELECT *
            FROM product_provider_mappings
            WHERE is_active=1 AND provider='myorderbox'
              AND (product_sku=:sku OR product_sku=:base_sku)
            ORDER BY CASE WHEN product_sku=:sku_order THEN 0 ELSE 1 END
            LIMIT 1
        ");
        $baseSku = str_contains($sku, '--') ? substr($sku, 0, strpos($sku, '--')) : $sku;
        $mapping->execute(['sku' => $sku, 'base_sku' => $baseSku, 'sku_order' => $sku]);
        $map = $mapping->fetch();
        if (!$map) return ['ok' => false, 'status' => 'manual_review', 'error' => 'No active provider mapping exists for renewal SKU ' . $sku . '.'];
        $endpoint = (string)$map['provider_endpoint'];
        $payload = [
            'order-id' => $providerOrderId,
            'months' => $months,
            'auto-renew' => 'false',
            'invoice-option' => 'PayInvoice',
            'discount-amount' => '0.00',
        ];
        if (preg_match('#^/restapi/product/([a-z0-9]+)/order/?$#i', $endpoint, $matches)) {
            $endpoint = '/restapi/product/' . strtolower($matches[1]) . '/order/' . rawurlencode($providerOrderId) . '/tenure/' . $months;
            unset($payload['order-id'], $payload['months']);
            $payload['__send_params_in_query'] = true;
            $payload['__http_method'] = 'PATCH';
        } elseif (str_ends_with($endpoint, '/add.json')) {
            $endpoint = substr($endpoint, 0, -strlen('/add.json')) . '/renew.json';
        } else {
            return ['ok' => false, 'status' => 'manual_review', 'error' => 'Provider mapping does not expose a recognized renewal endpoint for SKU ' . $sku . '.'];
        }
        $result = hivenest_mob_request($db, $endpoint, $payload);
        $advance = '+' . $months . ' months';
    }

    if (!$result['ok']) {
        $error = (string)($result['error'] ?: 'MyOrderBox renewal failed.');
        $db->prepare("UPDATE service_renewals SET status='failed', error_message=:error, provider_response=:response WHERE id=:id")
            ->execute(['error' => $error, 'response' => json_encode($result, JSON_UNESCAPED_SLASHES), 'id' => (int)$row['renewal_id']]);
        return ['ok' => false, 'status' => 'retry', 'error' => $error, 'response' => $result];
    }
    $data = is_array($result['data']) ? $result['data'] : ['value' => $result['data']];
    $ids = hivenest_mob_validate_provider_order_response($data, 'service_renewal');
    if (!$ids['ok']) {
        return ['ok' => false, 'status' => 'manual_review', 'error' => $ids['error'], 'response' => $data];
    }
    $newDue = gmdate('Y-m-d H:i:s', strtotime((string)$row['next_due_date'] . ' ' . $advance));
    $db->prepare("
        UPDATE services
        SET service_status='active', next_due_date=:next_due_date,
            expiry_date=CASE WHEN expiry_date IS NULL OR expiry_date < :expiry_date THEN :expiry_date2 ELSE expiry_date END
        WHERE id=:service_id
    ")->execute([
        'next_due_date' => $newDue,
        'expiry_date' => $newDue,
        'expiry_date2' => $newDue,
        'service_id' => (int)$row['service_id'],
    ]);
    if ((int)($row['domain_registration_id'] ?? 0) > 0) {
        $db->prepare("
            UPDATE domain_registrations
            SET expiry_date=:expiry_date, provider_action_id=:action_id,
                provider_invoice_id=:invoice_id, provider_status=:provider_status,
                provider_response=:provider_response
            WHERE id=:id
        ")->execute([
            'expiry_date' => $newDue,
            'action_id' => $ids['provider_action_id'] ?: null,
            'invoice_id' => $ids['provider_invoice_id'] ?: null,
            'provider_status' => $ids['provider_status'],
            'provider_response' => json_encode($data, JSON_UNESCAPED_SLASHES),
            'id' => (int)$row['domain_registration_id'],
        ]);
    }
    $db->prepare("
        UPDATE service_renewals
        SET status='completed', provider_order_id=:provider_order_id,
            provider_action_id=:provider_action_id, provider_invoice_id=:provider_invoice_id,
            provider_response=:provider_response, error_message=NULL, completed_at=NOW()
        WHERE id=:id
    ")->execute([
        'provider_order_id' => $ids['provider_order_id'] ?: $providerOrderId,
        'provider_action_id' => $ids['provider_action_id'] ?: null,
        'provider_invoice_id' => $ids['provider_invoice_id'] ?: null,
        'provider_response' => json_encode($data, JSON_UNESCAPED_SLASHES),
        'id' => (int)$row['renewal_id'],
    ]);
    return array_merge($ids, ['ok' => true, 'status' => 'completed', 'response' => $data]);
}

function hivenest_refresh_order_provisioning_status(PDO $db, int $orderId): void {
    if ($orderId <= 0 || !hivenest_bridge_column_exists($db, 'orders', 'provisioning_status')) return;
    try {
        $orderStmt = $db->prepare('SELECT payment_status, order_status FROM orders WHERE id=:order_id LIMIT 1');
        $orderStmt->execute(['order_id' => $orderId]);
        $order = $orderStmt->fetch() ?: [];
        $paymentStatus = (string)($order['payment_status'] ?? 'pending');

        $stmt = $db->prepare("
            SELECT
                SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) completed_count,
                SUM(CASE WHEN status IN ('pending','processing') THEN 1 ELSE 0 END) open_count,
                SUM(CASE WHEN status='retry' THEN 1 ELSE 0 END) retry_count,
                SUM(CASE WHEN status IN ('failed','manual_review') THEN 1 ELSE 0 END) review_count,
                COUNT(*) total_count
            FROM provisioning_jobs
            WHERE order_id=:order_id
              AND NOT (
                  job_type='manual_queue'
                  AND (
                      LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(request_payload, '$.product_name')), '')) LIKE '%privacy%'
                      OR LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(request_payload, '$.sku')), '')) = 'domain-privacy'
                  )
              )
        ");
        $stmt->execute(['order_id' => $orderId]);
        $row = $stmt->fetch() ?: [];
        $total = (int)($row['total_count'] ?? 0);
        $completed = (int)($row['completed_count'] ?? 0);
        $open = (int)($row['open_count'] ?? 0);
        $retry = (int)($row['retry_count'] ?? 0);
        $review = (int)($row['review_count'] ?? 0);

        if ($total <= 0) {
            $status = 'pending';
            $orderStatus = 'processing';
        } elseif ($review > 0) {
            $status = 'manual_review';
            $orderStatus = 'processing';
        } elseif ($retry > 0) {
            $status = 'retry';
            $orderStatus = 'processing';
        } elseif ($open > 0) {
            $status = 'queued';
            $orderStatus = 'processing';
        } elseif ($completed === $total) {
            $status = 'completed';
            $orderStatus = match ($paymentStatus) {
                'failed' => 'failed',
                'refunded' => 'refunded',
                'partially_refunded' => 'processing',
                default => 'completed',
            };
        } else {
            $status = 'queued';
            $orderStatus = 'processing';
        }

        if ($paymentStatus === 'failed') {
            $orderStatus = 'failed';
        } elseif ($paymentStatus === 'refunded') {
            $orderStatus = 'refunded';
        }

        $db->prepare("UPDATE orders SET provisioning_status=:status, order_status=:order_status WHERE id=:order_id")
            ->execute(['status' => $status, 'order_status' => $orderStatus, 'order_id' => $orderId]);
    } catch (Throwable $e) {
        error_log('HiveNest order provisioning status refresh failed: ' . $e->getMessage());
    }
}

function hivenest_refresh_order_item_from_jobs(PDO $db, int $orderItemId): void {
    if ($orderItemId <= 0 || !hivenest_bridge_column_exists($db, 'order_items', 'provisioning_status')) return;
    try {
        $stmt = $db->prepare("
            SELECT
                COUNT(*) total_count,
                SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) completed_count,
                SUM(CASE WHEN status IN ('pending','processing') THEN 1 ELSE 0 END) open_count,
                SUM(CASE WHEN status='retry' THEN 1 ELSE 0 END) retry_count,
                SUM(CASE WHEN status IN ('failed','manual_review') THEN 1 ELSE 0 END) review_count,
                MAX(CASE WHEN status IN ('failed','manual_review','retry') THEN error_message ELSE NULL END) latest_error
            FROM provisioning_jobs
            WHERE order_item_id=:order_item_id
        ");
        $stmt->execute(['order_item_id' => $orderItemId]);
        $row = $stmt->fetch() ?: [];
        $total = (int)($row['total_count'] ?? 0);
        if ($total <= 1) return;

        $completed = (int)($row['completed_count'] ?? 0);
        $open = (int)($row['open_count'] ?? 0);
        $retry = (int)($row['retry_count'] ?? 0);
        $review = (int)($row['review_count'] ?? 0);
        $error = trim((string)($row['latest_error'] ?? ''));

        if ($review > 0) {
            $status = 'manual_review';
        } elseif ($retry > 0) {
            $status = 'retry';
        } elseif ($open > 0) {
            $status = 'pending';
        } elseif ($completed === $total) {
            $status = 'completed';
            $error = '';
        } else {
            $status = 'pending';
        }

        $db->prepare("
            UPDATE order_items
            SET provisioning_status=:status,
                provisioning_error=:error
            WHERE id=:order_item_id
        ")->execute([
            'status' => $status,
            'error' => $error !== '' ? $error : null,
            'order_item_id' => $orderItemId,
        ]);
    } catch (Throwable $e) {
        error_log('HiveNest order item provisioning status refresh failed: ' . $e->getMessage());
    }
}

function hivenest_order_item_ready_to_notify(PDO $db, int $orderItemId): bool {
    if ($orderItemId <= 0) return false;
    try {
        $stmt = $db->prepare("
            SELECT
                COUNT(*) total_count,
                SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) completed_count
            FROM provisioning_jobs
            WHERE order_item_id=:order_item_id
        ");
        $stmt->execute(['order_item_id' => $orderItemId]);
        $row = $stmt->fetch() ?: [];
        $total = (int)($row['total_count'] ?? 0);
        $completed = (int)($row['completed_count'] ?? 0);
        return $total <= 1 || ($total > 0 && $completed === $total);
    } catch (Throwable $e) {
        error_log('HiveNest order item ready-notify check failed: ' . $e->getMessage());
        return true;
    }
}

function hivenest_mob_credit_customer(PDO $db, array $order, string $captureId): array {
    $customerId = trim((string)($order['myorderbox_customer_id'] ?? ''));
    if ($customerId === '') {
        return [
            'status' => 'manual_review',
            'provider_transaction_id' => null,
            'response' => null,
            'error' => 'Customer has no MyOrderBox customer ID yet. Create/sync customer before provider credit.',
        ];
    }

    $amount = number_format((float)$order['total_amount'], 2, '.', '');
    $result = hivenest_mob_request($db, '/api/billing/add-customer-fund.json', [
        'customer-id' => $customerId,
        'amount' => $amount,
        'transaction-type' => 'receipt',
        'transaction-key' => $captureId,
        'description' => 'HiveNest PayPal payment for order ' . $order['order_number'],
        'update-total-receipt' => 'true',
    ]);

    if (!$result['ok']) {
        return [
            'status' => 'failed',
            'provider_transaction_id' => null,
            'response' => $result,
            'error' => $result['error'] ?: 'MyOrderBox fund credit failed.',
        ];
    }

    $data = $result['data'] ?? [];
    $providerId = (string)($data['transactionid'] ?? $data['transaction-id'] ?? $data['eaqid'] ?? $data['id'] ?? '');
    return [
        'status' => 'credited',
        'provider_transaction_id' => $providerId !== '' ? $providerId : null,
        'response' => $data,
        'error' => null,
    ];
}

function hivenest_bridge_job_idempotency_key(
    int $orderId,
    ?int $orderItemId,
    string $jobType,
    array $payload = []
): string {
    $parts = ['order', (string)$orderId];
    if ($orderItemId !== null && $orderItemId > 0) {
        $parts[] = 'item';
        $parts[] = (string)$orderItemId;
    } else {
        $parts[] = 'order-job';
    }
    $parts[] = strtolower(trim($jobType));

    if (array_key_exists('bundle_index', $payload)) {
        $parts[] = 'bundle';
        $parts[] = (string)max(0, (int)$payload['bundle_index']);
        $bundleSku = strtolower(trim((string)($payload['sku'] ?? '')));
        if ($bundleSku !== '') $parts[] = $bundleSku;
    }

    return substr(implode(':', $parts), 0, 191);
}

function hivenest_start_order_provisioning(int $orderId, string $paypalCaptureId, string $paypalOrderId = ''): void {
    $db = hivenest_db();
    if (!$db) return;

    if (!hivenest_bridge_schema_ready($db)) {
        error_log('HiveNest provisioning bridge skipped: run Database/paypal_myorderbox_bridge.sql first.');
        return;
    }

    try {
        $orderStmt = $db->prepare("
            SELECT
                o.*,
                c.email,
                c.first_name,
                c.last_name,
                c.company_name,
                c.myorderbox_customer_id
            FROM orders o
            INNER JOIN customers c ON c.id = o.customer_id
            WHERE o.id = :order_id
            LIMIT 1
        ");
        $orderStmt->execute(['order_id' => $orderId]);
        $order = $orderStmt->fetch();
        if (!$order) return;

        $paymentStatus = 'pending';
        $providerTransactionId = null;
        $providerResponse = null;
        $providerError = null;

        $existingPaymentStmt = $db->prepare("
            SELECT provider_credit_status, provider_transaction_id, provider_response, error_message
            FROM payment_gateway_transactions
            WHERE gateway='paypal'
              AND gateway_capture_id=:gateway_capture_id
            LIMIT 1
        ");
        $existingPaymentStmt->execute(['gateway_capture_id' => $paypalCaptureId]);
        $existingPayment = $existingPaymentStmt->fetch();
        if ($existingPayment) {
            $paymentStatus = (string)$existingPayment['provider_credit_status'];
            $providerTransactionId = $existingPayment['provider_transaction_id'] ?: null;
            $providerResponse = json_decode((string)($existingPayment['provider_response'] ?? ''), true);
            if (!is_array($providerResponse)) $providerResponse = null;
            $providerError = $existingPayment['error_message'] ?: null;
        } else {
            $credit = hivenest_mob_credit_customer($db, $order, $paypalCaptureId);
            $paymentStatus = $credit['status'];
            $providerTransactionId = $credit['provider_transaction_id'];
            $providerResponse = $credit['response'];
            $providerError = $credit['error'];
        }

        $paymentStmt = $db->prepare("
            INSERT INTO payment_gateway_transactions
                (uuid, order_id, customer_id, gateway, gateway_order_id, gateway_capture_id, amount, currency, gateway_status, provider_credit_status, provider_transaction_id, provider_response, error_message)
            VALUES
                (:uuid, :order_id, :customer_id, 'paypal', :gateway_order_id, :gateway_capture_id, :amount, :currency, 'captured', :provider_credit_status, :provider_transaction_id, :provider_response, :error_message)
            ON DUPLICATE KEY UPDATE
                provider_credit_status = VALUES(provider_credit_status),
                provider_transaction_id = VALUES(provider_transaction_id),
                provider_response = VALUES(provider_response),
                error_message = VALUES(error_message),
                updated_at = NOW()
        ");
        $paymentStmt->execute([
            'uuid' => hivenest_bridge_uuid(),
            'order_id' => $orderId,
            'customer_id' => (int)$order['customer_id'],
            'gateway_order_id' => $paypalOrderId !== '' ? $paypalOrderId : null,
            'gateway_capture_id' => $paypalCaptureId,
            'amount' => (float)$order['total_amount'],
            'currency' => $order['currency'] ?: 'USD',
            'provider_credit_status' => $paymentStatus,
            'provider_transaction_id' => $providerTransactionId,
            'provider_response' => $providerResponse ? json_encode($providerResponse, JSON_UNESCAPED_SLASHES) : null,
            'error_message' => $providerError,
        ]);

        $db->prepare("
            UPDATE orders
            SET provisioning_status = :status,
                myorderbox_transaction_id = COALESCE(:provider_transaction_id, myorderbox_transaction_id),
                provider_payload = :provider_payload
            WHERE id = :order_id
        ")->execute([
            'status' => $paymentStatus === 'credited' ? 'queued' : 'manual_review',
            'provider_transaction_id' => $providerTransactionId,
            'provider_payload' => $providerResponse ? json_encode($providerResponse, JSON_UNESCAPED_SLASHES) : null,
            'order_id' => $orderId,
        ]);

        $hasJobIdempotency = hivenest_bridge_column_exists($db, 'provisioning_jobs', 'idempotency_key');
        $jobInsertOrder = $hasJobIdempotency
            ? $db->prepare("
                INSERT INTO provisioning_jobs
                    (uuid, idempotency_key, order_id, order_item_id, service_id, customer_id, job_type, provider, status, next_attempt_at, request_payload, error_message)
                VALUES
                    (:uuid, :idempotency_key, :order_id, NULL, NULL, :customer_id, :job_type, 'myorderbox', :status, NOW(), :request_payload, :error_message)
                ON DUPLICATE KEY UPDATE
                    status = CASE
                        WHEN provisioning_jobs.status IN ('completed','processing') THEN provisioning_jobs.status
                        ELSE VALUES(status)
                    END,
                    request_payload = CASE
                        WHEN provisioning_jobs.status='completed' THEN provisioning_jobs.request_payload
                        ELSE VALUES(request_payload)
                    END,
                    error_message = CASE
                        WHEN provisioning_jobs.status='completed' THEN NULL
                        ELSE VALUES(error_message)
                    END,
                    updated_at = NOW()
            ")
            : $db->prepare("
                INSERT INTO provisioning_jobs
                    (uuid, order_id, order_item_id, service_id, customer_id, job_type, provider, status, next_attempt_at, request_payload, error_message)
                SELECT
                    :uuid, :order_id, NULL, NULL, :customer_id, :job_type, 'myorderbox', :status, NOW(), :request_payload, :error_message
                WHERE NOT EXISTS (
                    SELECT 1 FROM provisioning_jobs
                    WHERE order_id=:existing_order_id
                      AND order_item_id IS NULL
                      AND job_type=:existing_job_type
                )
            ");
        if (empty($order['myorderbox_customer_id'])) {
            try {
                $params = [
                    'uuid' => hivenest_bridge_uuid(),
                    'order_id' => $orderId,
                    'customer_id' => (int)$order['customer_id'],
                    'job_type' => 'customer_sync',
                    'status' => 'pending',
                    'request_payload' => json_encode(['order_number' => $order['order_number'], 'email' => $order['email']], JSON_UNESCAPED_SLASHES),
                    'error_message' => null,
                ];
                if ($hasJobIdempotency) {
                    $params['idempotency_key'] = hivenest_bridge_job_idempotency_key($orderId, null, 'customer_sync');
                } else {
                    $params['existing_order_id'] = $orderId;
                    $params['existing_job_type'] = 'customer_sync';
                }
                $jobInsertOrder->execute($params);
            } catch (Throwable $ignored) {}
        }
        if ($paymentStatus !== 'credited') {
            try {
                $paymentPayload = ['order_number' => $order['order_number'], 'paypal_capture_id' => $paypalCaptureId, 'paypal_order_id' => $paypalOrderId];
                $params = [
                    'uuid' => hivenest_bridge_uuid(),
                    'order_id' => $orderId,
                    'customer_id' => (int)$order['customer_id'],
                    'job_type' => 'payment_credit',
                    'status' => 'pending',
                    'request_payload' => json_encode($paymentPayload, JSON_UNESCAPED_SLASHES),
                    'error_message' => $providerError,
                ];
                if ($hasJobIdempotency) {
                    $params['idempotency_key'] = hivenest_bridge_job_idempotency_key($orderId, null, 'payment_credit', $paymentPayload);
                } else {
                    $params['existing_order_id'] = $orderId;
                    $params['existing_job_type'] = 'payment_credit';
                }
                $jobInsertOrder->execute($params);
            } catch (Throwable $ignored) {}
        }

        $itemsStmt = $db->prepare("
            SELECT
                oi.*,
                p.product_type,
                p.slug AS product_slug
            FROM order_items oi
            INNER JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = :order_id
            ORDER BY oi.id ASC
        ");
        $itemsStmt->execute(['order_id' => $orderId]);
        $items = $itemsStmt->fetchAll() ?: [];

        $serviceInsert = $db->prepare("
            INSERT INTO services
                (uuid, customer_id, product_id, order_id, service_name, domain_name, service_type, service_status, billing_cycle, next_due_date, service_config)
            VALUES
                (:uuid, :customer_id, :product_id, :order_id, :service_name, :domain_name, :service_type, 'pending', :billing_cycle, :next_due_date, :service_config)
        ");
        $serviceFind = $db->prepare("
            SELECT id
            FROM services
            WHERE order_id = :order_id
              AND product_id = :product_id
              AND service_name = :service_name
              AND ((domain_name IS NULL AND :domain_name IS NULL) OR domain_name = :domain_name_match)
            ORDER BY id ASC
            LIMIT 1
        ");
        $renewalServiceFind = $db->prepare("
            SELECT id
            FROM services
            WHERE id = :service_id
              AND customer_id = :customer_id
            LIMIT 1
        ");
        $itemUpdate = $db->prepare("
            UPDATE order_items
            SET service_id = :service_id,
                provisioning_status = :status,
                provisioning_error = :error
            WHERE id = :order_item_id
        ");
        $jobInsert = $hasJobIdempotency
            ? $db->prepare("
                INSERT INTO provisioning_jobs
                    (uuid, idempotency_key, order_id, order_item_id, service_id, customer_id, job_type, provider, status, next_attempt_at, request_payload, error_message)
                VALUES
                    (:uuid, :idempotency_key, :order_id, :order_item_id, :service_id, :customer_id, :job_type, :provider, :status, NOW(), :request_payload, :error_message)
                ON DUPLICATE KEY UPDATE
                    service_id = VALUES(service_id),
                    status = CASE
                        WHEN provisioning_jobs.status IN ('completed','processing') THEN provisioning_jobs.status
                        ELSE VALUES(status)
                    END,
                    request_payload = CASE
                        WHEN provisioning_jobs.status='completed' THEN provisioning_jobs.request_payload
                        ELSE VALUES(request_payload)
                    END,
                    error_message = CASE
                        WHEN provisioning_jobs.status='completed' THEN NULL
                        ELSE VALUES(error_message)
                    END,
                    updated_at = NOW()
            ")
            : $db->prepare("
                INSERT INTO provisioning_jobs
                    (uuid, order_id, order_item_id, service_id, customer_id, job_type, provider, status, next_attempt_at, request_payload, error_message)
                SELECT
                    :uuid, :order_id, :order_item_id, :service_id, :customer_id, :job_type, :provider, :status, NOW(), :request_payload, :error_message
                WHERE NOT EXISTS (
                    SELECT 1 FROM provisioning_jobs
                    WHERE order_id=:existing_order_id
                      AND order_item_id=:existing_order_item_id
                      AND job_type=:existing_job_type
                      AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(request_payload, '$.bundle_index')), '-1')
                          = :existing_bundle_index
                )
            ");

        foreach ($items as $item) {
            $config = json_decode((string)($item['product_config'] ?? ''), true);
            if (!is_array($config)) $config = [];
            $sku = (string)($config['sku'] ?? '');
            $serviceType = hivenest_bridge_service_type((string)$item['product_type'], (string)$item['product_slug'], $sku, (string)$item['product_name']);
            $jobType = hivenest_bridge_job_type($serviceType, $sku, (string)$item['product_name']);
            $privacyAddon = hivenest_bridge_is_domain_privacy_addon($serviceType, $sku, (string)$item['product_name']);
            $nextDue = hivenest_bridge_next_due_date((string)$item['billing_cycle']);

            $renewalServiceId = max(0, (int)($config['renewal_service_id'] ?? 0));
            $renewalChildAddon = !empty($config['renewal_child_addon']);
            if ($renewalServiceId > 0) {
                $renewalServiceFind->execute([
                    'service_id' => $renewalServiceId,
                    'customer_id' => (int)$order['customer_id'],
                ]);
                $serviceId = (int)$renewalServiceFind->fetchColumn();
                if ($serviceId <= 0) {
                    throw new RuntimeException(
                        'Renewal service ownership verification failed for order item ' . (int)$item['id'] . '.'
                    );
                }
                if ($renewalChildAddon) {
                    $itemUpdate->execute([
                        'service_id' => $serviceId,
                        'status' => 'completed',
                        'error' => null,
                        'order_item_id' => (int)$item['id'],
                    ]);
                    continue;
                }
                $jobType = 'service_renewal';
                $privacyAddon = false;
            } else {
                $serviceFind->execute([
                    'order_id' => $orderId,
                    'product_id' => (int)$item['product_id'],
                    'service_name' => $item['product_name'],
                    'domain_name' => $item['domain_name'],
                    'domain_name_match' => $item['domain_name'],
                ]);
                $serviceId = (int)$serviceFind->fetchColumn();
                if ($serviceId <= 0) {
                    $serviceInsert->execute([
                        'uuid' => hivenest_bridge_uuid(),
                        'customer_id' => (int)$order['customer_id'],
                        'product_id' => (int)$item['product_id'],
                        'order_id' => $orderId,
                        'service_name' => $item['product_name'],
                        'domain_name' => $item['domain_name'],
                        'service_type' => $serviceType,
                        'billing_cycle' => $item['billing_cycle'],
                        'next_due_date' => $nextDue,
                        'service_config' => json_encode([
                            'order_item_id' => (int)$item['id'],
                            'sku' => $sku,
                            'provider' => 'myorderbox',
                            'paypal_capture_id' => $paypalCaptureId,
                            'addon_parent_domain' => $privacyAddon ? (string)$item['domain_name'] : null,
                            'requires_provider_provisioning' => in_array($jobType, ['domain_registration','hosting_setup','email_setup','ssl_setup','backup_setup','security_setup'], true),
                        ], JSON_UNESCAPED_SLASHES),
                    ]);
                    $serviceId = (int)$db->lastInsertId();
                }
            }

            $bundleItems = hivenest_bridge_bundle_items($config);
            if (!empty($bundleItems)) {
                $itemUpdate->execute([
                    'service_id' => $serviceId,
                    'status' => 'queued',
                    'error' => null,
                    'order_item_id' => (int)$item['id'],
                ]);

                foreach ($bundleItems as $bundleItem) {
                    $bundleSku = (string)$bundleItem['sku'];
                    $bundleName = (string)$bundleItem['name'];
                    $bundleJobType = (string)$bundleItem['job_type'];
                    $bundleProvider = (string)$bundleItem['provider'];
                    $bundleDomain = hivenest_bridge_bundle_item_domain($bundleItem, $item, $config);
                    $teamBundleJob = in_array($bundleJobType, ['design_queue','marketing_queue','manual_queue'], true) || $bundleProvider === 'hivenest_team';
                    $bundleCanProvision = $paymentStatus === 'credited';

                    if ($teamBundleJob) {
                        $bundleStatus = 'manual_review';
                        $bundleError = 'Team action required for this bundled service. Complete it in CRM/provisioning when ready.';
                        $bundleProvider = 'hivenest_team';
                    } else {
                        $bundleStatus = $bundleCanProvision ? 'pending' : 'manual_review';
                        $bundleError = $bundleCanProvision ? null : ($providerError ?: 'Payment captured locally, but MyOrderBox credit/customer sync is not complete.');
                        $bundleProvider = 'myorderbox';
                    }

                    $bundlePayload = [
                        'order_number' => $order['order_number'],
                        'bundle_parent_name' => $item['product_name'],
                        'bundle_parent_sku' => $sku,
                        'bundle_index' => (int)($bundleItem['bundle_index'] ?? 0),
                        'product_name' => $bundleName,
                        'sku' => $bundleSku,
                        'domain_name' => $bundleDomain !== '' ? $bundleDomain : null,
                        'billing_cycle' => $item['billing_cycle'],
                        'term_months' => $bundleItem['term_months'] ?? $config['term_months'] ?? null,
                        'quantity' => (int)($bundleItem['quantity'] ?? 1),
                        'line_total' => 0,
                        'bundle_item' => $bundleItem,
                    ];
                    $params = [
                        'uuid' => hivenest_bridge_uuid(),
                        'order_id' => $orderId,
                        'order_item_id' => (int)$item['id'],
                        'service_id' => $serviceId,
                        'customer_id' => (int)$order['customer_id'],
                        'job_type' => $bundleJobType,
                        'provider' => $bundleProvider,
                        'status' => $bundleStatus,
                        'request_payload' => json_encode($bundlePayload, JSON_UNESCAPED_SLASHES),
                        'error_message' => $bundleError,
                    ];
                    if ($hasJobIdempotency) {
                        $params['idempotency_key'] = hivenest_bridge_job_idempotency_key(
                            $orderId,
                            (int)$item['id'],
                            $bundleJobType,
                            $bundlePayload
                        );
                    } else {
                        $params['existing_order_id'] = $orderId;
                        $params['existing_order_item_id'] = (int)$item['id'];
                        $params['existing_job_type'] = $bundleJobType;
                        $params['existing_bundle_index'] = (string)$bundlePayload['bundle_index'];
                    }
                    $jobInsert->execute($params);
                }

                continue;
            }

            if ($privacyAddon) {
                $itemUpdate->execute([
                    'service_id' => $serviceId,
                    'status' => 'completed',
                    'error' => null,
                    'order_item_id' => (int)$item['id'],
                ]);
                continue;
            }

            $teamJob = in_array($jobType, ['design_queue','marketing_queue','manual_queue'], true);
            $canProvision = $paymentStatus === 'credited';
            if ($teamJob) {
                $status = 'manual_review';
                $error = 'Team action required for this service. Complete it manually in the provisioning monitor when ready.';
            } else {
                $status = $canProvision ? 'pending' : 'manual_review';
                $error = $canProvision ? null : ($providerError ?: 'Payment captured locally, but MyOrderBox credit/customer sync is not complete.');
            }

            $itemUpdate->execute([
                'service_id' => $serviceId,
                'status' => $status,
                'error' => $error,
                'order_item_id' => (int)$item['id'],
            ]);

            $itemPayload = [
                'order_number' => $order['order_number'],
                'product_name' => $item['product_name'],
                'sku' => $sku,
                'domain_name' => $item['domain_name'],
                'billing_cycle' => $item['billing_cycle'],
                'quantity' => (int)$item['quantity'],
                'line_total' => (float)$item['line_total'],
                'renewal_service_id' => $renewalServiceId > 0 ? $renewalServiceId : null,
                'renewal_due_date' => $config['renewal_due_date'] ?? null,
                'renewal_period_months' => $config['renewal_period_months'] ?? null,
                'renewal_type' => $config['renewal_type'] ?? null,
            ];
            $params = [
                'uuid' => hivenest_bridge_uuid(),
                'order_id' => $orderId,
                'order_item_id' => (int)$item['id'],
                'service_id' => $serviceId,
                'customer_id' => (int)$order['customer_id'],
                'job_type' => $jobType,
                'provider' => $teamJob ? 'hivenest_team' : 'myorderbox',
                'status' => $status,
                'request_payload' => json_encode($itemPayload, JSON_UNESCAPED_SLASHES),
                'error_message' => $error,
            ];
            if ($hasJobIdempotency) {
                $params['idempotency_key'] = hivenest_bridge_job_idempotency_key(
                    $orderId,
                    (int)$item['id'],
                    $jobType,
                    $itemPayload
                );
            } else {
                $params['existing_order_id'] = $orderId;
                $params['existing_order_item_id'] = (int)$item['id'];
                $params['existing_job_type'] = $jobType;
                $params['existing_bundle_index'] = '-1';
            }
            $jobInsert->execute($params);
        }

        hivenest_refresh_order_provisioning_status($db, $orderId);
    } catch (Throwable $e) {
        error_log('HiveNest provisioning bridge failed for order ' . $orderId . ': ' . $e->getMessage());
        try {
            $db->prepare("UPDATE orders SET provisioning_status='manual_review', admin_notes=CONCAT(COALESCE(admin_notes,''), '\nProvisioning bridge error: ', :error) WHERE id=:order_id")
                ->execute(['error' => $e->getMessage(), 'order_id' => $orderId]);
        } catch (Throwable $ignored) {
        }
    }
}

function hivenest_process_provisioning_jobs(int $limit = 10): array {
    $db = hivenest_db();
    if (!$db) return ['ok' => false, 'processed' => 0, 'results' => [], 'error' => 'Database unavailable.'];
    if (!hivenest_bridge_schema_ready($db)) {
        return ['ok' => false, 'processed' => 0, 'results' => [], 'error' => 'Provisioning schema is not ready. Run Database/paypal_myorderbox_bridge.sql.'];
    }

    $limit = max(1, min(50, $limit));
    $claim = $db->prepare("
        SELECT *
        FROM provisioning_jobs
        WHERE status IN ('pending','retry')
          AND attempts < max_attempts
          AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
          AND job_type IN ('customer_sync','payment_credit','domain_registration','service_renewal','hosting_setup','email_setup','ssl_setup','backup_setup','security_setup')
        ORDER BY
          CASE job_type WHEN 'customer_sync' THEN 0 WHEN 'payment_credit' THEN 1 WHEN 'domain_registration' THEN 2 WHEN 'service_renewal' THEN 3 ELSE 4 END,
          id ASC
        LIMIT {$limit}
    ");
    $claim->execute();
    $jobs = $claim->fetchAll() ?: [];
    $results = [];

    foreach ($jobs as $job) {
        $jobId = (int)$job['id'];
        try {
            $claimed = $db->prepare("
                UPDATE provisioning_jobs
                SET status='processing',
                    attempts=attempts+1,
                    locked_at=NOW(),
                    locked_by='php-worker'
                WHERE id=:id
                  AND status IN ('pending','retry')
                  AND attempts < max_attempts
                  AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
            ");
            $claimed->execute(['id' => $jobId]);
            if ($claimed->rowCount() !== 1) {
                continue;
            }

            if ($job['job_type'] === 'customer_sync') {
                $sync = hivenest_mob_sync_customer($db, (int)$job['customer_id']);
                if ($sync['ok']) {
                    $db->prepare("UPDATE provisioning_jobs SET status='completed', response_payload=:response, error_message=NULL WHERE id=:id")
                        ->execute(['response' => json_encode($sync, JSON_UNESCAPED_SLASHES), 'id' => $jobId]);
                    $db->prepare("UPDATE provisioning_jobs SET status='pending', next_attempt_at=NOW(), error_message=NULL WHERE order_id=:order_id AND job_type='payment_credit' AND status='manual_review'")
                        ->execute(['order_id' => (int)$job['order_id']]);
                    $results[] = ['job_id' => $jobId, 'job_type' => 'customer_sync', 'status' => 'completed'];
                    hivenest_refresh_order_provisioning_status($db, (int)$job['order_id']);
                } else {
                    $db->prepare("UPDATE provisioning_jobs SET status='retry', next_attempt_at=DATE_ADD(NOW(), INTERVAL 15 MINUTE), response_payload=:response, error_message=:error WHERE id=:id")
                        ->execute(['response' => json_encode($sync, JSON_UNESCAPED_SLASHES), 'error' => $sync['error'] ?? 'Customer sync failed.', 'id' => $jobId]);
                    $results[] = ['job_id' => $jobId, 'job_type' => 'customer_sync', 'status' => 'retry', 'error' => $sync['error'] ?? 'Customer sync failed.'];
                    hivenest_refresh_order_provisioning_status($db, (int)$job['order_id']);
                }
                continue;
            }

            if ($job['job_type'] === 'payment_credit') {
                $payload = json_decode((string)($job['request_payload'] ?? ''), true);
                if (!is_array($payload)) $payload = [];
                $captureId = (string)($payload['paypal_capture_id'] ?? '');
                if ($captureId === '') {
                    $orderStmt = $db->prepare('SELECT payment_reference FROM orders WHERE id=:order_id LIMIT 1');
                    $orderStmt->execute(['order_id' => (int)$job['order_id']]);
                    $captureId = (string)$orderStmt->fetchColumn();
                }
                $orderStmt = $db->prepare("
                    SELECT o.*, c.myorderbox_customer_id
                    FROM orders o
                    INNER JOIN customers c ON c.id=o.customer_id
                    WHERE o.id=:order_id
                    LIMIT 1
                ");
                $orderStmt->execute(['order_id' => (int)$job['order_id']]);
                $order = $orderStmt->fetch();
                if (!$order) throw new RuntimeException('Order not found for payment credit job.');

                $credit = hivenest_mob_credit_customer($db, $order, $captureId);
                if ($credit['status'] === 'credited') {
                    $db->prepare("UPDATE provisioning_jobs SET status='completed', response_payload=:response, error_message=NULL WHERE id=:id")
                        ->execute(['response' => json_encode($credit['response'], JSON_UNESCAPED_SLASHES), 'id' => $jobId]);
                    $db->prepare("UPDATE orders SET provisioning_status='queued', myorderbox_transaction_id=COALESCE(:tx, myorderbox_transaction_id), provider_payload=:payload WHERE id=:order_id")
                        ->execute(['tx' => $credit['provider_transaction_id'], 'payload' => json_encode($credit['response'], JSON_UNESCAPED_SLASHES), 'order_id' => (int)$job['order_id']]);
                    $db->prepare("UPDATE payment_gateway_transactions SET provider_credit_status='credited', provider_transaction_id=:tx, provider_response=:payload, error_message=NULL WHERE order_id=:order_id AND gateway_capture_id=:capture")
                        ->execute(['tx' => $credit['provider_transaction_id'], 'payload' => json_encode($credit['response'], JSON_UNESCAPED_SLASHES), 'order_id' => (int)$job['order_id'], 'capture' => $captureId]);
                    $db->prepare("UPDATE provisioning_jobs SET status='pending', next_attempt_at=NOW(), error_message=NULL WHERE order_id=:order_id AND job_type NOT IN ('customer_sync','payment_credit','design_queue','marketing_queue','manual_queue') AND status='manual_review'")
                        ->execute(['order_id' => (int)$job['order_id']]);
                    $db->prepare("
                        UPDATE order_items oi
                        SET oi.provisioning_status='pending', oi.provisioning_error=NULL
                        WHERE oi.order_id=:order_id
                          AND oi.provisioning_status='manual_review'
                          AND EXISTS (
                              SELECT 1
                              FROM provisioning_jobs pj
                              WHERE pj.order_item_id = oi.id
                                AND pj.job_type NOT IN ('design_queue','marketing_queue','manual_queue')
                          )
                    ")
                        ->execute(['order_id' => (int)$job['order_id']]);
                    $results[] = ['job_id' => $jobId, 'job_type' => 'payment_credit', 'status' => 'completed'];
                    hivenest_refresh_order_provisioning_status($db, (int)$job['order_id']);
                } else {
                    $status = $credit['status'] === 'manual_review' ? 'manual_review' : 'retry';
                    $db->prepare("UPDATE provisioning_jobs SET status=:status, next_attempt_at=DATE_ADD(NOW(), INTERVAL 15 MINUTE), response_payload=:response, error_message=:error WHERE id=:id")
                        ->execute(['status' => $status, 'response' => json_encode($credit['response'], JSON_UNESCAPED_SLASHES), 'error' => $credit['error'] ?? 'Payment credit failed.', 'id' => $jobId]);
                    $results[] = ['job_id' => $jobId, 'job_type' => 'payment_credit', 'status' => $status, 'error' => $credit['error'] ?? 'Payment credit failed.'];
                    hivenest_refresh_order_provisioning_status($db, (int)$job['order_id']);
                }
                continue;
            }

            if ($job['job_type'] === 'domain_registration') {
                $registration = hivenest_mob_register_domain($db, $job);
                if ($registration['ok']) {
                    $db->prepare("UPDATE provisioning_jobs SET status='completed', response_payload=:response, error_message=NULL WHERE id=:id")
                        ->execute(['response' => json_encode($registration['response'] ?? $registration, JSON_UNESCAPED_SLASHES), 'id' => $jobId]);
                    $db->prepare("UPDATE order_items SET provisioning_status='completed', provider_order_id=:order_id, provider_action_id=:action_id, provider_entity_id=:entity_id, provisioning_error=NULL WHERE id=:order_item_id")
                        ->execute([
                            'order_id' => $registration['provider_order_id'] ?: null,
                            'action_id' => $registration['provider_action_id'] ?: null,
                            'entity_id' => $registration['provider_order_id'] ?: null,
                            'order_item_id' => (int)$job['order_item_id'],
                        ]);
                    $results[] = ['job_id' => $jobId, 'job_type' => 'domain_registration', 'status' => 'completed'];
                    hivenest_refresh_order_item_from_jobs($db, (int)$job['order_item_id']);
                    if (hivenest_order_item_ready_to_notify($db, (int)$job['order_item_id'])) {
                        hivenest_send_service_ready_email((int)$job['order_item_id'], [
                            'provider_order_id' => $registration['provider_order_id'] ?? '',
                            'provider_action_id' => $registration['provider_action_id'] ?? '',
                            'provider_entity_id' => $registration['provider_order_id'] ?? '',
                        ]);
                    }
                    hivenest_refresh_order_provisioning_status($db, (int)$job['order_id']);
                } else {
                    $status = $registration['status'] ?? 'retry';
                    if (!in_array($status, ['retry','manual_review','failed'], true)) $status = 'retry';
                    $db->prepare("UPDATE provisioning_jobs SET status=:status, next_attempt_at=DATE_ADD(NOW(), INTERVAL 15 MINUTE), response_payload=:response, error_message=:error WHERE id=:id")
                        ->execute(['status' => $status, 'response' => json_encode($registration, JSON_UNESCAPED_SLASHES), 'error' => $registration['error'] ?? 'Domain registration failed.', 'id' => $jobId]);
                    $db->prepare("UPDATE order_items SET provisioning_status=:status, provisioning_error=:error WHERE id=:order_item_id")
                        ->execute(['status' => $status, 'error' => $registration['error'] ?? 'Domain registration failed.', 'order_item_id' => (int)$job['order_item_id']]);
                    $results[] = ['job_id' => $jobId, 'job_type' => 'domain_registration', 'status' => $status, 'error' => $registration['error'] ?? 'Domain registration failed.'];
                    hivenest_refresh_order_item_from_jobs($db, (int)$job['order_item_id']);
                    hivenest_refresh_order_provisioning_status($db, (int)$job['order_id']);
                }
                continue;
            }

            if ($job['job_type'] === 'service_renewal') {
                $renewal = hivenest_mob_process_service_renewal($db, $job);
                if ($renewal['ok']) {
                    $db->prepare("UPDATE provisioning_jobs SET status='completed', response_payload=:response, error_message=NULL WHERE id=:id")
                        ->execute(['response' => json_encode($renewal['response'] ?? $renewal, JSON_UNESCAPED_SLASHES), 'id' => $jobId]);
                    $db->prepare("UPDATE order_items SET provisioning_status='completed', provider_order_id=:order_id, provider_action_id=:action_id, provider_entity_id=:entity_id, provisioning_error=NULL WHERE id=:order_item_id")
                        ->execute([
                            'order_id' => $renewal['provider_order_id'] ?: null,
                            'action_id' => $renewal['provider_action_id'] ?: null,
                            'entity_id' => $renewal['provider_entity_id'] ?: null,
                            'order_item_id' => (int)$job['order_item_id'],
                        ]);
                    $results[] = ['job_id' => $jobId, 'job_type' => 'service_renewal', 'status' => 'completed'];
                } else {
                    $status = in_array((string)($renewal['status'] ?? ''), ['retry','manual_review','failed'], true)
                        ? (string)$renewal['status']
                        : 'retry';
                    $ledgerStatus = $status === 'manual_review' ? 'manual_review' : 'failed';
                    $db->prepare("
                        UPDATE service_renewals
                        SET status=:status, error_message=:error
                        WHERE renewal_order_id=:order_id
                          AND service_id=:service_id
                          AND status <> 'completed'
                    ")->execute([
                        'status' => $ledgerStatus,
                        'error' => $renewal['error'] ?? 'Renewal failed.',
                        'order_id' => (int)$job['order_id'],
                        'service_id' => (int)$job['service_id'],
                    ]);
                    $db->prepare("UPDATE provisioning_jobs SET status=:status, next_attempt_at=DATE_ADD(NOW(), INTERVAL 15 MINUTE), response_payload=:response, error_message=:error WHERE id=:id")
                        ->execute(['status' => $status, 'response' => json_encode($renewal, JSON_UNESCAPED_SLASHES), 'error' => $renewal['error'] ?? 'Renewal failed.', 'id' => $jobId]);
                    $db->prepare("UPDATE order_items SET provisioning_status=:status, provisioning_error=:error WHERE id=:order_item_id")
                        ->execute(['status' => $status, 'error' => $renewal['error'] ?? 'Renewal failed.', 'order_item_id' => (int)$job['order_item_id']]);
                    $results[] = ['job_id' => $jobId, 'job_type' => 'service_renewal', 'status' => $status, 'error' => $renewal['error'] ?? 'Renewal failed.'];
                }
                hivenest_refresh_order_item_from_jobs($db, (int)$job['order_item_id']);
                hivenest_refresh_order_provisioning_status($db, (int)$job['order_id']);
                continue;
            }

            if (in_array($job['job_type'], ['hosting_setup','email_setup','ssl_setup','backup_setup','security_setup'], true)) {
                $providerOrder = hivenest_mob_process_mapped_provider_order($db, $job);
                if ($providerOrder['ok']) {
                    $db->prepare("UPDATE provisioning_jobs SET status='completed', response_payload=:response, error_message=NULL WHERE id=:id")
                        ->execute(['response' => json_encode($providerOrder['response'] ?? $providerOrder, JSON_UNESCAPED_SLASHES), 'id' => $jobId]);
                    $db->prepare("UPDATE order_items SET provisioning_status='completed', provider_order_id=:order_id, provider_action_id=:action_id, provider_entity_id=:entity_id, provisioning_error=NULL WHERE id=:order_item_id")
                        ->execute([
                            'order_id' => $providerOrder['provider_order_id'] ?: null,
                            'action_id' => $providerOrder['provider_action_id'] ?: null,
                            'entity_id' => $providerOrder['provider_entity_id'] ?: null,
                            'order_item_id' => (int)$job['order_item_id'],
                        ]);
                    $results[] = ['job_id' => $jobId, 'job_type' => $job['job_type'], 'status' => 'completed'];
                    hivenest_refresh_order_item_from_jobs($db, (int)$job['order_item_id']);
                    if (hivenest_order_item_ready_to_notify($db, (int)$job['order_item_id'])) {
                        hivenest_send_service_ready_email((int)$job['order_item_id'], [
                            'provider_order_id' => $providerOrder['provider_order_id'] ?? '',
                            'provider_action_id' => $providerOrder['provider_action_id'] ?? '',
                            'provider_entity_id' => $providerOrder['provider_entity_id'] ?? '',
                        ]);
                    }
                    hivenest_refresh_order_provisioning_status($db, (int)$job['order_id']);
                } else {
                    $status = $providerOrder['status'] ?? 'retry';
                    if (!in_array($status, ['retry','manual_review','failed'], true)) $status = 'retry';
                    $db->prepare("UPDATE provisioning_jobs SET status=:status, next_attempt_at=DATE_ADD(NOW(), INTERVAL 15 MINUTE), response_payload=:response, error_message=:error WHERE id=:id")
                        ->execute(['status' => $status, 'response' => json_encode($providerOrder, JSON_UNESCAPED_SLASHES), 'error' => $providerOrder['error'] ?? 'Provider order failed.', 'id' => $jobId]);
                    $db->prepare("UPDATE order_items SET provisioning_status=:status, provisioning_error=:error WHERE id=:order_item_id")
                        ->execute(['status' => $status, 'error' => $providerOrder['error'] ?? 'Provider order failed.', 'order_item_id' => (int)$job['order_item_id']]);
                    $results[] = ['job_id' => $jobId, 'job_type' => $job['job_type'], 'status' => $status, 'error' => $providerOrder['error'] ?? 'Provider order failed.'];
                    hivenest_refresh_order_item_from_jobs($db, (int)$job['order_item_id']);
                    hivenest_refresh_order_provisioning_status($db, (int)$job['order_id']);
                }
                continue;
            }
        } catch (Throwable $e) {
            $db->prepare("UPDATE provisioning_jobs SET status='retry', next_attempt_at=DATE_ADD(NOW(), INTERVAL 15 MINUTE), error_message=:error WHERE id=:id")
                ->execute(['error' => $e->getMessage(), 'id' => $jobId]);
            hivenest_refresh_order_provisioning_status($db, (int)$job['order_id']);
            $results[] = ['job_id' => $jobId, 'job_type' => $job['job_type'], 'status' => 'retry', 'error' => $e->getMessage()];
        }
    }

    return ['ok' => true, 'processed' => count($results), 'results' => $results];
}

function hivenest_process_provisioning_jobs_if_enabled(int $limit = 10): array {
    if (!hivenest_bridge_env_bool('PROVISIONING_PROCESS_IMMEDIATELY', true)) {
        return ['ok' => true, 'processed' => 0, 'skipped' => true, 'reason' => 'Immediate provisioning disabled by environment.'];
    }
    return hivenest_process_provisioning_jobs($limit);
}

function hivenest_log_worker_run(string $source, array $result, ?string $error = null): void {
    $db = hivenest_db();
    if (!$db || !hivenest_bridge_table_exists($db, 'provisioning_worker_runs')) return;
    $allowed = ['checkout','webhook','admin','cron','cli'];
    if (!in_array($source, $allowed, true)) $source = 'cron';
    try {
        $stmt = $db->prepare("
            INSERT INTO provisioning_worker_runs
                (uuid, trigger_source, processed_count, ok, result_payload, error_message, finished_at)
            VALUES
                (:uuid, :trigger_source, :processed_count, :ok, :result_payload, :error_message, NOW())
        ");
        $stmt->execute([
            'uuid' => hivenest_bridge_uuid(),
            'trigger_source' => $source,
            'processed_count' => (int)($result['processed'] ?? 0),
            'ok' => !empty($result['ok']) ? 1 : 0,
            'result_payload' => json_encode($result, JSON_UNESCAPED_SLASHES),
            'error_message' => $error ?: ($result['error'] ?? null),
        ]);
    } catch (Throwable $e) {
        error_log('HiveNest worker run log failed: ' . $e->getMessage());
    }
}
