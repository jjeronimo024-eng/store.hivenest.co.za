<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../utilities/customer_session.php';
hivenest_customer_session_configure();
session_start();
require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/order_notifications.php';
require_once __DIR__ . '/../utilities/myorderbox_bridge.php';
require_once __DIR__ . '/../utilities/customer_loyalty.php';
require_once __DIR__ . '/../utilities/promotions.php';
require_once __DIR__ . '/../utilities/rate_limiter.php';
require_once __DIR__ . '/../utilities/currency.php';

function pp_out(int $status, array $data): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_SLASHES); exit; }

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        if ($needle === '') return true;
        $length = strlen($needle);
        return $length <= strlen($haystack) && substr($haystack, -$length) === $needle;
    }
}

function pp_env(string $key, string $default = ''): string {
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

function pp_base(): string { return strtolower(pp_env('PAYPAL_MODE', 'sandbox')) === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com'; }

function pp_token(): string {
    static $token = null;
    if ($token !== null) return $token;
    if (!function_exists('curl_init')) pp_out(503, ['error' => 'PayPal requires the PHP cURL extension.']);
    $client = pp_env('PAYPAL_CLIENT_ID'); $secret = pp_env('PAYPAL_CLIENT_SECRET');
    if ($client === '' || $secret === '') pp_out(503, ['error' => 'PayPal credentials are not configured.']);
    $ch = curl_init(pp_base() . '/v1/oauth2/token');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => 'grant_type=client_credentials', CURLOPT_USERPWD => $client . ':' . $secret, CURLOPT_HTTPHEADER => ['Accept: application/json','Content-Type: application/x-www-form-urlencoded'], CURLOPT_TIMEOUT => 30]);
    $body = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $data = json_decode((string)$body, true);
    if ($status !== 200 || empty($data['access_token'])) pp_out(502, ['error' => 'Unable to authenticate with PayPal.']);
    return $token = (string)$data['access_token'];
}

function pp_request(string $method, string $path, ?array $payload = null, array $extra = []): array {
    $headers = array_merge(['Accept: application/json','Content-Type: application/json','Authorization: Bearer ' . pp_token()], $extra);
    $ch = curl_init(pp_base() . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $body = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
    if ($body === false || $error !== '') pp_out(502, ['error' => 'PayPal connection failed.']);
    $data = json_decode((string)$body, true);
    return ['status' => $status, 'data' => is_array($data) ? $data : ['error' => 'Invalid PayPal response']];
}

function pp_uuid(): string {
    $data = random_bytes(16); $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function pp_slugify(string $value): string {
    $value = strtolower(trim($value));
    $value = (string) preg_replace('/[^a-z0-9]+/i', '-', $value);
    return trim($value, '-');
}

function pp_product_hint(string $id, string $name): string {
    $id = strtolower($id);
    $name = strtolower($name);
    $prefixes = [
        'wordpress-hosting--' => 'wordpress-hosting',
        'wordpress-' => 'wordpress-hosting',
        'windows-hosting-' => 'multi-domain-windows-hosting',
        'multi-domain-windows-hosting--' => 'multi-domain-windows-hosting',
        'linux-hosting-' => 'multi-domain-linux-hosting',
        'multi-domain-linux-hosting--' => 'multi-domain-linux-hosting',
        'cloud-hosting-' => 'cloud-hosting',
        'cloud-hosting--' => 'cloud-hosting',
        'ssl-' => 'ssl-certificates',
        'ssl-certificates--' => 'ssl-certificates',
        'xcitium-' => 'xcitium-backup',
        'xcitium-backup--' => 'xcitium-backup',
        'sitelock-' => 'sitelock-security',
        'sitelock-security--' => 'sitelock-security',
        'seo-' => 'seo-services',
        'seo-services--' => 'seo-services',
        'social-media-' => 'social-media-marketing',
        'social-media-marketing--' => 'social-media-marketing',
        'google-marketing--' => 'google-marketing',
        'server-windows-' => 'windows-vps',
        'server-linux-' => 'dedicated-server-linux',
        'windows-vps--' => 'windows-vps',
        'dedicated-server-linux--' => 'dedicated-server-linux',
        'dedicated-server-windows--' => 'dedicated-server-windows',
        'business-email--' => 'business-email',
        'enterprise-' => 'enterprise-email',
        'enterprise-email--' => 'enterprise-email',
        'workspace-' => 'google-workspace',
        'google-workspace--' => 'google-workspace',
        'cloud-' => 'cloud-mail',
        'cloud-mail--' => 'cloud-mail',
        'website-builder-design-only-' => 'website-builder-design-only',
        'website-builder--' => 'website-builder',
        'website-builder-' => 'website-builder',
        'website-builder-design-only--' => 'website-builder-design-only',
        'logo-' => 'logo-design',
        'logo-design--' => 'logo-design',
        'card-' => 'business-cards',
        'business-cards--' => 'business-cards',
        'letterhead-' => 'letterheads',
        'letterheads--' => 'letterheads',
        'signature-' => 'email-signatures',
        'email-signatures--' => 'email-signatures',
        'special-ops--' => 'special-ops',
    ];
    foreach ($prefixes as $prefix => $slug) {
        if (str_starts_with($id, $prefix)) return $slug;
    }
    $namePrefixes = [
        'wordpress hosting:' => 'wordpress-hosting',
        'windows hosting:' => 'multi-domain-windows-hosting',
        'linux hosting:' => 'multi-domain-linux-hosting',
        'cloud hosting:' => 'cloud-hosting',
        'ssl certificate:' => 'ssl-certificates',
        'xcitium backup:' => 'xcitium-backup',
        'sitelock security:' => 'sitelock-security',
        'seo services:' => 'seo-services',
        'social media marketing:' => 'social-media-marketing',
        'google marketing:' => 'google-marketing',
        'windows server:' => 'windows-vps',
        'linux dedicated server:' => 'dedicated-server-linux',
        'business email:' => 'business-email',
        'enterprise email:' => 'enterprise-email',
        'enterprise email -' => 'enterprise-email',
        'google workspace:' => 'google-workspace',
        'google workspace -' => 'google-workspace',
        'cloud mail:' => 'cloud-mail',
        'cloud mail -' => 'cloud-mail',
        'logo design -' => 'logo-design',
        'business cards -' => 'business-cards',
        'letterhead design -' => 'letterheads',
        'email signature -' => 'email-signatures',
        'website builder:' => 'website-builder',
    ];
    foreach ($namePrefixes as $prefix => $slug) {
        if (str_starts_with($name, $prefix)) return $slug;
    }
    if (str_contains($id, '--')) return substr($id, 0, strpos($id, '--'));
    return '';
}

function pp_tier_candidate(string $id, string $name): string {
    if (str_contains($id, '--')) return substr($id, strrpos($id, '--') + 2);
    foreach ([
        'wordpress-hosting:', 'windows hosting:', 'linux hosting:', 'cloud hosting:',
        'ssl certificate:', 'xcitium backup:', 'sitelock security:', 'seo services:',
        'social media marketing:', 'google marketing:', 'windows server:',
        'business email:', 'enterprise email:', 'google workspace:', 'cloud mail:',
        'enterprise email -', 'google workspace -', 'cloud mail -',
        'logo design -', 'business cards -', 'letterhead design -', 'email signature -',
        'website builder:', 'linux dedicated server:'
    ] as $prefix) {
        if (stripos($name, $prefix) === 0) {
            return pp_slugify(substr($name, strlen($prefix)));
        }
    }
    foreach ([
        'wordpress-', 'windows-hosting-', 'linux-hosting-', 'cloud-hosting-',
        'ssl-', 'xcitium-', 'sitelock-', 'seo-', 'social-media-', 'server-windows-',
        'hosting-plan-', 'server-linux-', 'website-builder-design-only-', 'website-builder-',
        'enterprise-', 'workspace-', 'cloud-', 'logo-', 'card-', 'letterhead-', 'signature-'
    ] as $prefix) {
        if (str_starts_with(strtolower($id), $prefix)) return substr($id, strlen($prefix));
    }
    return pp_slugify($id);
}

function pp_catalog_slug_match(string $id, array $productSlugs): array {
    $id = strtolower(trim($id));
    if ($id === '') return ['', ''];

    foreach ($productSlugs as $slug) {
        $slug = strtolower((string)$slug);
        if ($slug === '') continue;

        if ($id === $slug) {
            return [$slug, 'base'];
        }
        if (str_starts_with($id, $slug . '--')) {
            return [$slug, substr($id, strlen($slug) + 2)];
        }
        if (str_starts_with($id, $slug . '-')) {
            return [$slug, substr($id, strlen($slug) + 1)];
        }
    }

    return ['', ''];
}

function pp_tier_alias(string $hint, string $candidate): string {
    $hint = strtolower(trim($hint));
    $candidate = strtolower(trim($candidate));

    while ($hint !== '' && str_starts_with($candidate, $hint . '--')) {
        $candidate = substr($candidate, strlen($hint) + 2);
    }
    while ($hint !== '' && str_starts_with($candidate, $hint . '-')) {
        $candidate = substr($candidate, strlen($hint) + 1);
    }

    $aliases = [
        'website-builder' => [
            'starter' => 'starter-neural',
            'professional' => 'professional-matrix',
            'professional-matrix' => 'professional-matrix',
            'enterprise' => 'enterprise-quantum',
        ],
        'logo-design' => [
            'basic' => 'basic-neural',
            'professional' => 'professional-matrix-logo',
            'enterprise' => 'enterprise-quantum-logo',
        ],
        'business-cards' => [
            'standard' => 'standard-neural',
            'premium' => 'premium-matrix',
            'luxury' => 'luxury-quantum',
        ],
        'letterheads' => [
            'basic' => 'basic-neural-lh',
            'professional' => 'professional-matrix-lh',
            'enterprise' => 'enterprise-quantum-lh',
        ],
        'email-signatures' => [
            'individual' => 'individual-neural',
            'team' => 'team-matrix',
            'enterprise' => 'enterprise-quantum-sig',
        ],
        'google-workspace' => [
            'starter' => 'business-starter',
            'standard' => 'business-standard',
            'plus' => 'business-plus',
        ],
        'enterprise-email' => [
            'basic' => '1-5-accounts',
            'pro' => '6-25-accounts',
            'ultimate' => '50-plus-accounts',
        ],
    ];

    return $aliases[$hint][$candidate] ?? $candidate;
}

function pp_normalize_tld(string $tld): string {
    $tld = strtolower(trim($tld));
    if ($tld === '') return '';
    return $tld[0] === '.' ? $tld : '.' . $tld;
}

function pp_domain_years(array $item, array $extension): int {
    $requested = (int)($item['period'] ?? $item['years'] ?? 1);
    $minimum = max(1, (int)($extension['min_years'] ?? 1));
    $maximum = max($minimum, (int)($extension['max_years'] ?? 10));
    return max($minimum, min($maximum, $requested > 0 ? $requested : 1));
}

function pp_find_extension(string $domain, string $tld, array $extensions): ?array {
    $domain = strtolower(trim($domain));
    $tld = pp_normalize_tld($tld);

    if ($tld !== '') {
        foreach ($extensions as $extension) {
            if (strtolower((string)$extension['extension']) === $tld) return $extension;
        }
    }

    foreach ($extensions as $extension) {
        $candidate = strtolower((string)$extension['extension']);
        if ($candidate !== '' && str_ends_with($domain, $candidate)) return $extension;
    }

    return null;
}

function pp_requires_live_domain_availability(string $type): bool {
    return in_array($type, ['domain', 'domain_registration', 'idn_domain'], true);
}

function pp_cycle_from_term_months(int $months): ?string {
    return match ($months) {
        1 => 'monthly',
        3 => 'quarterly',
        6 => 'semi_annually',
        12 => 'annually',
        24 => 'biennially',
        36 => 'triennially',
        default => null,
    };
}

function pp_bundle_requires_domain(array $bundleItems): bool {
    foreach ($bundleItems as $bundleItem) {
        if (!is_array($bundleItem)) continue;
        $jobType = strtolower((string)($bundleItem['job_type'] ?? ''));
        $skuText = strtolower((string)($bundleItem['sku'] ?? '') . ' ' . (string)($bundleItem['name'] ?? ''));
        if (!empty($bundleItem['requires_domain'])
            || in_array($jobType, ['domain_registration','hosting_setup','email_setup','ssl_setup','backup_setup','security_setup'], true)
            || str_contains($skuText, 'domain')
            || str_contains($skuText, 'hosting')
            || str_contains($skuText, 'ssl')
        ) {
            return true;
        }
    }
    return false;
}

function pp_bundle_items_from_cart($value): ?array {
    if (is_string($value) && trim($value) !== '') {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($value)) return null;
    $items = [];
    foreach ($value as $index => $bundleItem) {
        if (!is_array($bundleItem)) continue;
        $sku = trim((string)($bundleItem['sku'] ?? ''));
        $name = trim((string)($bundleItem['name'] ?? $sku));
        if ($sku === '' && $name === '') continue;
        $jobType = trim((string)($bundleItem['job_type'] ?? 'manual_queue'));
        $provider = trim((string)($bundleItem['provider'] ?? 'hivenest_team'));
        $clean = [
            'sku' => $sku,
            'name' => $name !== '' ? $name : $sku,
            'job_type' => $jobType !== '' ? $jobType : 'manual_queue',
            'provider' => $provider !== '' ? $provider : 'hivenest_team',
            'quantity' => max(1, (int)($bundleItem['quantity'] ?? 1)),
            'bundle_index' => (int)($bundleItem['bundle_index'] ?? ($index + 1)),
        ];
        if (!empty($bundleItem['requires_domain'])) $clean['requires_domain'] = true;
        if (!empty($bundleItem['domain']) || !empty($bundleItem['domain_name']) || !empty($bundleItem['primary_domain'])) {
            $clean['domain_name'] = trim((string)($bundleItem['domain_name'] ?? ($bundleItem['domain'] ?? $bundleItem['primary_domain'])));
        }
        if (!empty($bundleItem['term_months'])) $clean['term_months'] = max(1, (int)$bundleItem['term_months']);
        $items[] = $clean;
    }
    return $items ?: null;
}

function pp_cart(array $cart): array {
    $db = hivenest_db();
    if (!$db) pp_out(503, ['error' => 'Product catalogue is unavailable.']);
    $bundleSelect = hivenest_bridge_column_exists($db, 'product_pricing', 'bundle_items')
        ? 'pp.bundle_items'
        : 'NULL AS bundle_items';
    $cart = array_values(array_filter($cart, 'pp_is_payable_cart_item'));
    if (!$cart) pp_out(400, ['error' => 'Cart is empty. Add payable cart items before checkout.']);
    if (count($cart) > 250) pp_out(400, ['error' => 'Cart has too many checkout items. Please split this into multiple orders or contact support.']);
    $tier = $db->prepare("
        SELECT
            p.id product_id,
            p.name product_name,
            p.slug product_slug,
            pp.tier_name,
            pp.tier_slug,
            pp.price,
            pp.setup_fee,
            pp.billing_cycle,
            {$bundleSelect}
        FROM products p
        INNER JOIN product_pricing pp
            ON pp.product_id = p.id
           AND pp.is_active = 1
        WHERE p.is_active = 1
          AND (:hint_filter = '' OR p.slug = :hint_match)
          AND (
                pp.tier_slug = :tier_match
             OR CONCAT(p.slug, '--', pp.tier_slug) = :composite_match
             OR p.slug = :product_match
             OR LOWER(pp.tier_name) = LOWER(:full_name_match)
             OR LOWER(pp.tier_name) = LOWER(:tier_name_match)
          )
        ORDER BY
            CASE
                WHEN p.slug = :hint_order AND pp.tier_slug = :tier_order THEN 0
                WHEN CONCAT(p.slug, '--', pp.tier_slug) = :composite_order THEN 1
                WHEN pp.tier_slug = :tier_order_2 THEN 2
                ELSE 3
            END
        LIMIT 1
    ");
    $tierFallback = $db->prepare("
        SELECT
            p.id product_id,
            p.name product_name,
            p.slug product_slug,
            pp.tier_name,
            pp.tier_slug,
            pp.price,
            pp.setup_fee,
            pp.billing_cycle,
            {$bundleSelect}
        FROM products p
        INNER JOIN product_pricing pp
            ON pp.product_id = p.id
           AND pp.is_active = 1
        WHERE p.is_active = 1
          AND (
                pp.tier_slug = :tier_match
             OR LOWER(pp.tier_name) = LOWER(:tier_name_match)
             OR LOWER(pp.tier_name) = LOWER(:full_name_match)
          )
        ORDER BY
            CASE
                WHEN pp.tier_slug = :tier_order THEN 0
                WHEN LOWER(pp.tier_name) = LOWER(:tier_name_order) THEN 1
                ELSE 2
            END,
            p.sort_order ASC,
            p.id ASC
        LIMIT 1
    ");
    $product_slugs = $db->query("
        SELECT slug
        FROM products
        WHERE is_active = 1
        ORDER BY CHAR_LENGTH(slug) DESC, slug ASC
    ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $extensions = $db->query("
        SELECT extension, register_price, renew_price, transfer_price, min_years, max_years
        FROM domain_extensions
        WHERE is_active = 1
        ORDER BY CHAR_LENGTH(extension) DESC, extension ASC
    ")->fetchAll() ?: [];
    $domain_product_id = (int)$db->query("SELECT id FROM products WHERE is_active=1 AND (product_type='domain' OR slug='domain-registration') ORDER BY CASE WHEN slug='domain-registration' THEN 0 ELSE 1 END, id LIMIT 1")->fetchColumn();
    $items = [];
    foreach ($cart as $item) {
        $quantity = max(1, min(100, (int)($item['quantity'] ?? 1)));
        $id = trim((string)($item['id'] ?? '')); $name = trim((string)($item['name'] ?? ''));
        $type = strtolower((string)($item['type'] ?? ''));
        $itemConfig = is_array($item['product_config'] ?? null) ? $item['product_config'] : [];
        $domain = trim((string)($item['domain'] ?? ($item['domain_name'] ?? ($item['primary_domain'] ?? ''))));
        if ($domain === '') {
            $domain = trim((string)($itemConfig['domain'] ?? ($itemConfig['domain_name'] ?? ($itemConfig['primary_domain'] ?? ''))));
        }
        $domainOption = trim((string)($item['domain_option'] ?? ''));
        $tld = strtolower((string)($item['tld'] ?? ''));
        $termMonths = max(0, (int)($item['term_months'] ?? ($itemConfig['term_months'] ?? 0)));
        $monthlyPriceInput = $item['monthly_price'] ?? ($itemConfig['monthly_price'] ?? null);
        if ($type === 'domain_privacy') {
            if ($domain === '') $domain = $id;
            if ($domain_product_id <= 0) pp_out(400, ['error' => 'Domain privacy product is unavailable.']);
            $items[] = ['product_id' => $domain_product_id,'sku' => 'domain-privacy','name' => 'Neural Privacy Protection: ' . $domain,'domain' => $domain,'quantity' => $quantity,'unit_price' => 9.99,'setup_fee' => 0.0,'billing_cycle' => 'annually'];
            continue;
        }
        if (in_array($type, ['domain', 'domain_registration', 'idn_domain', 'domain_transfer', 'domain_extend'], true)) {
            if ($domain === '') $domain = $id;
            $row = pp_find_extension($domain, $tld, $extensions);
            if (!$row) pp_out(400, ['error' => 'Domain extension is unavailable for checkout: ' . $domain]);
            if ($domain_product_id <= 0) pp_out(400, ['error' => 'Domain registration product is unavailable.']);
            $extensionName = (string)$row['extension'];
            $years = pp_domain_years($item, $row);
            $unitPrice = (float)$row['register_price'] * $years;
            $lineName = $domain . ($years > 1 ? ' (' . $years . ' years)' : '');
            if (pp_requires_live_domain_availability($type)) {
                $availability = hivenest_mob_check_domain_available($db, $domain);
                if (empty($availability['ok'])) {
                    pp_out(503, [
                        'error' => 'Could not verify live domain availability for checkout: ' . $domain . '. Please try again or send it to support for feedback.',
                        'domain' => $domain,
                    ]);
                }
                if (empty($availability['available'])) {
                    pp_out(409, [
                        'error' => 'Domain is not available for registration: ' . $domain . ' (' . ($availability['status'] ?? 'unknown') . ').',
                        'domain' => $domain,
                        'status' => $availability['status'] ?? 'unknown',
                    ]);
                }
            }
            if ($type === 'domain_transfer') {
                $unitPrice = (float)$row['transfer_price'];
                $lineName = 'Domain Transfer: ' . $domain;
            } elseif ($type === 'domain_extend') {
                $unitPrice = (float)$row['renew_price'];
                $lineName = 'Extended Registration: ' . $domain;
            }
            $items[] = ['product_id' => $domain_product_id,'sku' => 'domain-' . ltrim($extensionName, '.'),'name' => $lineName,'domain' => $domain,'quantity' => $quantity,'unit_price' => $unitPrice,'setup_fee' => 0.0,'billing_cycle' => 'annually','years' => $years,'domain_action' => $type];
            continue;
        }
        $hint = pp_product_hint($id, $name);
        $candidate = pp_tier_candidate($id, $name);
        [$catalogHint, $catalogCandidate] = pp_catalog_slug_match($id, $product_slugs);
        if ($catalogHint !== '') {
            $hint = $catalogHint;
            $candidate = $catalogCandidate;
        }
        if ($hint !== '') {
            $candidate = pp_tier_alias($hint, $candidate);
        }
        $composite = $hint !== '' ? $hint . '--' . $candidate : $id;
        $tierName = str_contains($name, ':') ? trim(substr($name, strrpos($name, ':') + 1)) : $name;
        try {
            $tier->execute([
                'hint_filter' => $hint,
                'hint_match' => $hint,
                'tier_match' => $candidate,
                'composite_match' => $composite,
                'product_match' => $id,
                'full_name_match' => $name,
                'tier_name_match' => $tierName,
                'hint_order' => $hint,
                'tier_order' => $candidate,
                'composite_order' => $composite,
                'tier_order_2' => $candidate,
            ]);
            $row = $tier->fetch();
            if (!$row) {
                $tierFallback->execute([
                    'tier_match' => $candidate,
                    'tier_name_match' => $tierName,
                    'full_name_match' => $name,
                    'tier_order' => $candidate,
                    'tier_name_order' => $tierName,
                ]);
                $row = $tierFallback->fetch();
            }
        } catch (Throwable $e) {
            error_log('PayPal product validation query failed: ' . $e->getMessage());
            pp_out(500, ['error' => 'Product validation failed. Please refresh the page and try again.']);
        }
        if (!$row) pp_out(400, ['error' => 'Product is unavailable or could not be verified: ' . ($name ?: $id)]);
        $billingCycle = $row['billing_cycle'] ?: 'monthly';
        $unitPrice = (float)($row['price'] ?? 0);
        $termCycle = pp_cycle_from_term_months($termMonths);
        if ($termCycle !== null) {
            $cartPrice = (float)($item['price'] ?? 0);
            $monthlyPrice = (float)($monthlyPriceInput ?? 0);
            $expectedTermTotal = $monthlyPrice > 0 ? round($monthlyPrice * $termMonths, 2) : round($unitPrice * $termMonths, 2);
            if ($cartPrice > 0 && abs($cartPrice - $expectedTermTotal) <= 0.05) {
                $unitPrice = $cartPrice;
            } else {
                $unitPrice = $expectedTermTotal;
            }
            $billingCycle = $termCycle;
        }
        $bundleItems = json_decode((string)($row['bundle_items'] ?? ''), true);
        $bundleItems = is_array($bundleItems) ? $bundleItems : pp_bundle_items_from_cart($item['bundle_items'] ?? ($itemConfig['bundle_items'] ?? null));
        if ($bundleItems && pp_bundle_requires_domain($bundleItems) && $domain === '') {
            pp_out(400, [
                'error' => 'This Special Ops bundle requires a domain before checkout: ' . ($row['tier_name'] ?: $row['product_name']),
            ]);
        }
        $items[] = [
            'product_id' => (int)$row['product_id'],
            'sku' => $row['product_slug'] . '--' . ($row['tier_slug'] ?: 'base'),
            'name' => $row['tier_name'] ?: $row['product_name'],
            'domain' => $domain !== '' ? $domain : null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'setup_fee' => (float)($row['setup_fee'] ?? 0),
            'billing_cycle' => $billingCycle,
            'domain_option' => $domainOption !== '' ? $domainOption : null,
            'term_months' => $termMonths > 0 ? $termMonths : null,
            'monthly_price' => $monthlyPriceInput !== null && $monthlyPriceInput !== '' ? (float)$monthlyPriceInput : null,
            'bundle_items' => $bundleItems,
        ];
    }
    return $items;
}

function pp_is_payable_cart_item($item): bool {
    if (!is_array($item)) return false;
    foreach (['wishlist', 'is_wishlist', 'saved_for_later', 'hidden'] as $flag) {
        if (!empty($item[$flag])) return false;
    }
    $marker_parts = [];
    foreach (['status', 'cart_section', 'section', 'list', 'type', 'category'] as $field) {
        $marker_parts[] = strtolower((string)($item[$field] ?? ''));
    }
    $marker = implode('|', $marker_parts);
    foreach (['wishlist', 'wish_list', 'saved_for_later', 'hidden'] as $blocked) {
        if (str_contains($marker, $blocked)) return false;
    }
    return trim((string)($item['id'] ?? '')) !== '' || trim((string)($item['name'] ?? '')) !== '';
}

function pp_total(array $items): float { return round(array_reduce($items, static fn(float $sum, array $i): float => $sum + (($i['unit_price'] + $i['setup_fee']) * $i['quantity']), 0.0), 2); }

function pp_invoice_snapshot(PDO $db, int $customerId, string $orderNumber): array {
    $orderNumber = trim($orderNumber);
    if ($orderNumber === '') pp_out(400, ['error' => 'Invoice number is required.']);
    $stmt = $db->prepare("
        SELECT id, order_number, order_status, payment_status, subtotal,
               tax_amount, discount_amount, total_amount, currency,
               display_currency, display_exchange_rate, display_subtotal,
               display_tax_amount, display_discount_amount, display_total_amount,
               display_rate_source, display_rate_captured_at
        FROM orders
        WHERE customer_id = :customer_id
          AND order_number = :order_number
        LIMIT 1
    ");
    $stmt->execute(['customer_id' => $customerId, 'order_number' => $orderNumber]);
    $order = $stmt->fetch();
    if (!$order) pp_out(404, ['error' => 'Invoice was not found for this account.']);
    if (!in_array((string)$order['payment_status'], ['pending', 'failed'], true)) {
        pp_out(409, ['error' => 'This invoice is not payable in its current payment state.']);
    }
    if (in_array((string)$order['order_status'], ['cancelled', 'refunded'], true)) {
        pp_out(409, ['error' => 'This invoice has been cancelled or refunded.']);
    }
    if (strtoupper((string)$order['currency']) !== 'USD') {
        pp_out(409, ['error' => 'Only USD invoices can currently be paid through PayPal.']);
    }
    $total = round((float)$order['total_amount'], 2);
    if ($total <= 0) pp_out(409, ['error' => 'Invoice total must be greater than zero.']);
    $itemsStmt = $db->prepare("
        SELECT product_id, product_name, domain_name, quantity, unit_price,
               setup_fee, billing_cycle, line_total, product_config
        FROM order_items
        WHERE order_id = :order_id
        ORDER BY id ASC
    ");
    $itemsStmt->execute(['order_id' => (int)$order['id']]);
    $items = [];
    foreach ($itemsStmt->fetchAll() ?: [] as $item) {
        $config = json_decode((string)($item['product_config'] ?? ''), true);
        $config = is_array($config) ? $config : [];
        $items[] = [
            'product_id' => (int)$item['product_id'],
            'sku' => (string)($config['sku'] ?? 'invoice-item'),
            'name' => (string)$item['product_name'],
            'domain' => $item['domain_name'] !== null ? (string)$item['domain_name'] : null,
            'quantity' => max(1, (int)$item['quantity']),
            'unit_price' => (float)$item['unit_price'],
            'setup_fee' => (float)$item['setup_fee'],
            'billing_cycle' => (string)$item['billing_cycle'],
        ];
    }
    if (!$items) pp_out(409, ['error' => 'Invoice has no payable line items.']);
    return [
        'invoice_order_id' => (int)$order['id'],
        'invoice_order_number' => (string)$order['order_number'],
        'items' => $items,
        'subtotal' => (float)$order['subtotal'],
        'discount_amount' => (float)$order['discount_amount'],
        'total' => $total,
        'currency_snapshot' => [
            'display_currency' => $order['display_currency'] ?? null,
            'display_exchange_rate' => $order['display_exchange_rate'] ?? null,
            'display_subtotal' => $order['display_subtotal'] ?? null,
            'display_tax_amount' => $order['display_tax_amount'] ?? null,
            'display_discount_amount' => $order['display_discount_amount'] ?? null,
            'display_total_amount' => $order['display_total_amount'] ?? null,
            'display_rate_source' => $order['display_rate_source'] ?? null,
            'display_rate_captured_at' => $order['display_rate_captured_at'] ?? null,
        ],
        'captured' => false,
        'created_at' => time(),
    ];
}

function pp_pricing_quote(PDO $db, int $customerId, array $items, string $promotionCode): array {
    $subtotal = pp_total($items);
    if ($subtotal <= 0) pp_out(400, ['error' => 'Order total must be greater than zero.']);
    try {
        $loyalty = hivenest_customer_loyalty($db, $customerId, false);
    } catch (Throwable $e) {
        error_log('PayPal loyalty quote failed: ' . $e->getMessage());
        pp_out(503, ['error' => 'Your loyalty discount could not be verified. Please try again.']);
    }
    $loyaltyPercent = max(0.0, min(18.0, (float)($loyalty['discount_percent'] ?? 0)));
    $loyaltyDiscount = round($subtotal * $loyaltyPercent / 100, 2);
    $promotion = hivenest_promotion_quote($db, $customerId, $items, $subtotal, $promotionCode);
    if (!$promotion['valid']) pp_out(422, ['error' => $promotion['error']]);
    $maximumPromotionDiscount = max(0.0, round($subtotal - 0.01 - $loyaltyDiscount, 2));
    $promotionDiscount = min(round((float)$promotion['discount_amount'], 2), $maximumPromotionDiscount);
    $discount = round($loyaltyDiscount + $promotionDiscount, 2);
    $total = round($subtotal - $discount, 2);
    if ($total <= 0) pp_out(400, ['error' => 'Order total after discount must be greater than zero.']);
    return [
        'currency' => 'USD',
        'subtotal' => $subtotal,
        'discount_percent' => $loyaltyPercent,
        'loyalty_discount_amount' => $loyaltyDiscount,
        'loyalty_tier' => (int)($loyalty['tier'] ?? 1),
        'promotion_discount_amount' => $promotionDiscount,
        'promotion' => $promotion['promotion'],
        'discount_amount' => $discount,
        'total' => $total,
    ];
}

function pp_store_checkout_session(string $paypalOrderId, array $snapshot): void {
    $db = hivenest_db();
    if (!$db || !hivenest_bridge_table_exists($db, 'paypal_checkout_sessions')) return;
    $customer = (int)($_SESSION['customer_id'] ?? 0);
    if ($customer <= 0) return;
    try {
        $stmt = $db->prepare("
            INSERT INTO paypal_checkout_sessions
                (uuid, customer_id, paypal_order_id, status, amount, currency, cart_snapshot)
            VALUES
                (:uuid, :customer_id, :paypal_order_id, 'created', :amount, 'USD', :cart_snapshot)
            ON DUPLICATE KEY UPDATE
                customer_id=VALUES(customer_id),
                status='created',
                amount=VALUES(amount),
                cart_snapshot=VALUES(cart_snapshot),
                updated_at=NOW()
        ");
        $stmt->execute([
            'uuid' => pp_uuid(),
            'customer_id' => $customer,
            'paypal_order_id' => $paypalOrderId,
            'amount' => (float)$snapshot['total'],
            'cart_snapshot' => json_encode($snapshot, JSON_UNESCAPED_SLASHES),
        ]);
    } catch (Throwable $e) {
        error_log('PayPal checkout session persistence failed: ' . $e->getMessage());
    }
}

function pp_save(array $snapshot, array $capture): ?string {
    $db = hivenest_db(); if (!$db) return null;
    $customer = (int)($_SESSION['customer_id'] ?? 0);
    if ($customer <= 0 && !empty($_SESSION['customer_email'])) { $s=$db->prepare('SELECT id FROM customers WHERE email=:email LIMIT 1'); $s->execute(['email'=>$_SESSION['customer_email']]); $customer=(int)$s->fetchColumn(); }
    if ($customer <= 0) return null;
    $captureId = (string)($capture['id'] ?? '');
    $paypalOrderId = (string)($snapshot['paypal_order_id'] ?? '');
    try {
        if ($captureId !== '' && hivenest_bridge_table_exists($db, 'payment_gateway_transactions')) {
            $existing = $db->prepare("
                SELECT o.order_number
                FROM payment_gateway_transactions pgt
                INNER JOIN orders o ON o.id=pgt.order_id
                WHERE pgt.gateway='paypal'
                  AND pgt.gateway_capture_id=:capture_id
                LIMIT 1
            ");
            $existing->execute(['capture_id' => $captureId]);
            $existingNumber = (string)$existing->fetchColumn();
            if ($existingNumber !== '') return $existingNumber;
        }
        if ($paypalOrderId !== '' && hivenest_bridge_table_exists($db, 'paypal_checkout_sessions')) {
            $existing = $db->prepare("
                SELECT hivenest_order_number
                FROM paypal_checkout_sessions
                WHERE paypal_order_id=:paypal_order_id
                  AND hivenest_order_number IS NOT NULL
                  AND hivenest_order_number <> ''
                LIMIT 1
            ");
            $existing->execute(['paypal_order_id' => $paypalOrderId]);
            $existingNumber = (string)$existing->fetchColumn();
            if ($existingNumber !== '') return $existingNumber;
        }
    } catch (Throwable $e) {
        error_log('PayPal idempotency lookup failed: ' . $e->getMessage());
    }
    $number='HN-'.gmdate('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(5)),0,8));
    try {
        $db->beginTransaction();
        $subtotal = round((float)($snapshot['subtotal'] ?? $snapshot['total']), 2);
        $discount = round((float)($snapshot['discount_amount'] ?? 0), 2);
        $total = round((float)$snapshot['total'], 2);
        $invoiceOrderId = (int)($snapshot['invoice_order_id'] ?? 0);
        if ($invoiceOrderId > 0) {
            $locked = $db->prepare("
                SELECT id, order_number, payment_status, order_status, total_amount, currency
                FROM orders
                WHERE id = :order_id AND customer_id = :customer_id
                FOR UPDATE
            ");
            $locked->execute(['order_id' => $invoiceOrderId, 'customer_id' => $customer]);
            $existingInvoice = $locked->fetch();
            if (!$existingInvoice) throw new RuntimeException('Invoice ownership verification failed.');
            if (strtoupper((string)$existingInvoice['currency']) !== 'USD'
                || abs((float)$existingInvoice['total_amount'] - $total) > 0.001) {
                throw new RuntimeException('Invoice amount verification failed.');
            }
            if (!in_array((string)$existingInvoice['payment_status'], ['pending', 'failed'], true)) {
                throw new RuntimeException('Invoice is no longer payable.');
            }
            if (in_array((string)$existingInvoice['order_status'], ['cancelled', 'refunded'], true)) {
                throw new RuntimeException('Invoice is cancelled or refunded.');
            }
            $order = (int)$existingInvoice['id'];
            $number = (string)$existingInvoice['order_number'];
            $db->prepare("
                UPDATE orders
                SET order_status='processing', payment_status='paid',
                    payment_method='paypal', payment_reference=:reference,
                    processed_at=NOW(), updated_at=NOW()
                WHERE id=:order_id
            ")->execute([
                'reference' => $capture['id'] ?? $snapshot['paypal_order_id'],
                'order_id' => $order,
            ]);
            if (hivenest_bridge_table_exists($db, 'service_renewals')) {
                $db->prepare("
                    UPDATE service_renewals
                    SET status='paid', paid_at=NOW(), error_message=NULL
                    WHERE renewal_order_id=:order_id
                      AND status='invoice_created'
                ")->execute(['order_id' => $order]);
            }
        } else {
            $currencySnapshot = is_array($snapshot['currency_snapshot'] ?? null)
                ? $snapshot['currency_snapshot']
                : hivenest_currency_order_snapshot($db, $customer, [
                    'subtotal' => $subtotal,
                    'tax_amount' => 0,
                    'discount_amount' => $discount,
                    'total_amount' => $total,
                ]);
            $s=$db->prepare("INSERT INTO orders
                (uuid,customer_id,order_number,order_status,payment_status,
                 subtotal,tax_amount,discount_amount,total_amount,currency,
                 display_currency,display_exchange_rate,display_subtotal,
                 display_tax_amount,display_discount_amount,display_total_amount,
                 display_rate_source,display_rate_captured_at,
                 payment_method,payment_reference,processed_at)
                VALUES
                (:uuid,:customer,:number,'processing','paid',
                 :subtotal,0,:discount,:total,'USD',
                 :display_currency,:display_exchange_rate,:display_subtotal,
                 :display_tax_amount,:display_discount_amount,:display_total_amount,
                 :display_rate_source,:display_rate_captured_at,
                 'paypal',:reference,NOW())");
            $s->execute([
                'uuid'=>pp_uuid(),'customer'=>$customer,'number'=>$number,
                'subtotal'=>$subtotal,'discount'=>$discount,'total'=>$total,
                'display_currency'=>$currencySnapshot['display_currency'] ?? 'USD',
                'display_exchange_rate'=>$currencySnapshot['display_exchange_rate'] ?? 1,
                'display_subtotal'=>$currencySnapshot['display_subtotal'] ?? $subtotal,
                'display_tax_amount'=>$currencySnapshot['display_tax_amount'] ?? 0,
                'display_discount_amount'=>$currencySnapshot['display_discount_amount'] ?? $discount,
                'display_total_amount'=>$currencySnapshot['display_total_amount'] ?? $total,
                'display_rate_source'=>$currencySnapshot['display_rate_source'] ?? 'usd_base',
                'display_rate_captured_at'=>$currencySnapshot['display_rate_captured_at'] ?? gmdate('Y-m-d H:i:s'),
                'reference'=>$capture['id']??$snapshot['paypal_order_id']
            ]); $order=(int)$db->lastInsertId();
            $line=$db->prepare('INSERT INTO order_items (uuid,order_id,product_id,product_name,domain_name,quantity,unit_price,setup_fee,billing_cycle,line_total,product_config) VALUES (:uuid,:order,:product,:name,:domain,:quantity,:price,:setup,:cycle,:line_total,:config)');
            foreach($snapshot['items'] as $i){$cycle=in_array($i['billing_cycle'],['monthly','quarterly','semi_annually','annually','biennially','triennially'],true)?$i['billing_cycle']:'annually';$line->execute(['uuid'=>pp_uuid(),'order'=>$order,'product'=>$i['product_id'],'name'=>$i['name'],'domain'=>$i['domain'],'quantity'=>$i['quantity'],'price'=>$i['unit_price'],'setup'=>$i['setup_fee'],'cycle'=>$cycle,'line_total'=>($i['unit_price']+$i['setup_fee'])*$i['quantity'],'config'=>json_encode(array_filter(['sku'=>$i['sku'],'paypal_order_id'=>$snapshot['paypal_order_id'],'years'=>$i['years']??null,'domain_action'=>$i['domain_action']??null,'domain_option'=>$i['domain_option']??null,'term_months'=>$i['term_months']??null,'monthly_price'=>$i['monthly_price']??null,'bundle_items'=>$i['bundle_items']??null], static fn($v)=>$v!==null&&$v!==''&&$v!==[]), JSON_UNESCAPED_SLASHES)]);}
        }
        $promotion = $invoiceOrderId > 0 ? null : ($snapshot['promotion'] ?? null);
        if (is_array($promotion) && !empty($promotion['id']) && !empty($promotion['code'])) {
            if (!hivenest_promotion_table_exists($db, 'promotion_redemptions')) {
                throw new RuntimeException('Promotion redemption storage is unavailable.');
            }
            $redemption = $db->prepare("
                INSERT INTO promotion_redemptions
                    (uuid, promotion_code_id, customer_id, order_id, code, discount_amount, currency)
                VALUES
                    (:uuid, :promotion_id, :customer_id, :order_id, :code, :discount_amount, 'USD')
            ");
            $redemption->execute([
                'uuid' => pp_uuid(),
                'promotion_id' => (int)$promotion['id'],
                'customer_id' => $customer,
                'order_id' => $order,
                'code' => (string)$promotion['code'],
                'discount_amount' => (float)($snapshot['promotion_discount_amount'] ?? 0),
            ]);
            $db->prepare('UPDATE promotion_codes SET usage_count = usage_count + 1, updated_at = NOW() WHERE id = :id')
                ->execute(['id' => (int)$promotion['id']]);
        }
        $db->commit();
        if (hivenest_bridge_table_exists($db, 'paypal_checkout_sessions')) {
            $db->prepare("
                UPDATE paypal_checkout_sessions
                SET status='captured',
                    hivenest_order_id=:order_id,
                    hivenest_order_number=:order_number,
                    paypal_capture_id=:capture_id,
                    captured_at=NOW()
                WHERE paypal_order_id=:paypal_order_id
            ")->execute([
                'order_id' => $order,
                'order_number' => $number,
                'capture_id' => (string)($capture['id'] ?? ''),
                'paypal_order_id' => (string)($snapshot['paypal_order_id'] ?? ''),
            ]);
        }
        hivenest_start_order_provisioning($order, (string)($capture['id'] ?? ''), (string)($snapshot['paypal_order_id'] ?? ''));
        hivenest_log_worker_run('checkout', hivenest_process_provisioning_jobs_if_enabled(10));
        try {
            hivenest_customer_loyalty($db, $customer, true);
        } catch (Throwable $loyaltyError) {
            error_log('Customer loyalty recalculation failed: ' . $loyaltyError->getMessage());
        }
        hivenest_send_paid_order_emails($number);
        return $number;
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('PayPal order persistence failed: '.$e->getMessage());return null;}
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') pp_out(405, ['error'=>'POST required']);
$input=json_decode((string)file_get_contents('php://input'),true)?:[]; $action=$_GET['action']??'';
$customerSession = hivenest_customer_session_status(true);
if (!$customerSession['authenticated']) {
    pp_out(401, ['error'=>'Create or sign in to your HiveNest account before starting payment.']);
}
hivenest_customer_csrf_require_json();
$sessionCustomerId = (int)$customerSession['customer_id'];
$verificationDb = hivenest_db();
if (!$verificationDb) {
    pp_out(503, ['error'=>'Customer database is unavailable.']);
}
$verificationStmt = $verificationDb->prepare('SELECT email_verified FROM customers WHERE id = :id AND status = "active" LIMIT 1');
$verificationStmt->execute(['id' => $sessionCustomerId]);
$emailVerified = (int) $verificationStmt->fetchColumn() === 1;
$_SESSION['customer_email_verified'] = $emailVerified ? 1 : 0;
if (!$emailVerified) {
    pp_out(403, ['error'=>'Verify your email address before starting payment.']);
}
$paymentRateIdentifier = $sessionCustomerId . '|' . trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
if($action==='quote'){
    $limit = hivenest_rate_limit('paypal-quote', 60, 600, $paymentRateIdentifier);
    if (!$limit['allowed']) {
        header('Retry-After: ' . $limit['retry_after']);
        pp_out(429, ['error'=>'Too many pricing requests. Please wait and try again.', 'retry_after'=>$limit['retry_after']]);
    }
    $items=pp_cart($input['cart']??[]);
    $pricing=pp_pricing_quote($verificationDb,$sessionCustomerId,$items,(string)($input['promo_code']??''));
    pp_out(200,['pricing'=>$pricing]);
}
if($action==='create-order'){
    $limit = hivenest_rate_limit('paypal-create-order', 10, 3600, $paymentRateIdentifier);
    if (!$limit['allowed']) {
        header('Retry-After: ' . $limit['retry_after']);
        pp_out(429, ['error'=>'Too many payment attempts. Please wait before starting another checkout.', 'retry_after'=>$limit['retry_after']]);
    }
    $invoiceNumber = trim((string)($input['invoice_number'] ?? ''));
    $invoiceSnapshot = $invoiceNumber !== ''
        ? pp_invoice_snapshot($verificationDb, $sessionCustomerId, $invoiceNumber)
        : null;
    $items=$invoiceSnapshot ? $invoiceSnapshot['items'] : pp_cart($input['cart']??[]);
    $pricing=$invoiceSnapshot ? [
        'currency'=>'USD',
        'subtotal'=>(float)$invoiceSnapshot['subtotal'],
        'discount_percent'=>0,
        'loyalty_discount_amount'=>(float)$invoiceSnapshot['discount_amount'],
        'loyalty_tier'=>1,
        'promotion_discount_amount'=>0,
        'promotion'=>null,
        'discount_amount'=>(float)$invoiceSnapshot['discount_amount'],
        'total'=>(float)$invoiceSnapshot['total'],
    ] : pp_pricing_quote($verificationDb,$sessionCustomerId,$items,(string)($input['promo_code']??''));
    $subtotal=(float)$pricing['subtotal'];
    $discountAmount=(float)$pricing['discount_amount'];
    $total=(float)$pricing['total'];
    $currencySnapshot = $invoiceSnapshot
        ? (array)($invoiceSnapshot['currency_snapshot'] ?? [])
        : hivenest_currency_order_snapshot($verificationDb, $sessionCustomerId, [
            'subtotal' => $subtotal,
            'tax_amount' => 0,
            'discount_amount' => $discountAmount,
            'total_amount' => $total,
        ]);
    $paypal_items=$invoiceSnapshot
        ? [[
            'name'=>'HiveNest invoice '.$invoiceNumber,
            'sku'=>'hivenest-invoice',
            'quantity'=>'1',
            'unit_amount'=>['currency_code'=>'USD','value'=>number_format($total,2,'.','')]
        ]]
        : (count($items)>90
        ? [[
            'name'=>'HiveNest order - '.count($items).' items',
            'sku'=>'hivenest-order-summary',
            'quantity'=>'1',
            'unit_amount'=>['currency_code'=>'USD','value'=>number_format($subtotal,2,'.','')]
        ]]
        : array_map(static fn(array $i):array=>['name'=>substr($i['name'],0,127),'sku'=>substr($i['sku'],0,127),'quantity'=>(string)$i['quantity'],'unit_amount'=>['currency_code'=>'USD','value'=>number_format($i['unit_price']+$i['setup_fee'],2,'.','')]],$items));
    $breakdown=['item_total'=>['currency_code'=>'USD','value'=>number_format($invoiceSnapshot ? $total : $subtotal,2,'.','')]];
    if(!$invoiceSnapshot && $discountAmount>0)$breakdown['discount']=['currency_code'=>'USD','value'=>number_format($discountAmount,2,'.','')];
    $payload=['intent'=>'CAPTURE','purchase_units'=>[['amount'=>['currency_code'=>'USD','value'=>number_format($total,2,'.',''),'breakdown'=>$breakdown],'items'=>$paypal_items]],'application_context'=>['shipping_preference'=>'NO_SHIPPING','user_action'=>'PAY_NOW']];
    $response=pp_request('POST','/v2/checkout/orders',$payload,['PayPal-Request-Id: '.pp_uuid()]);if($response['status']<200||$response['status']>=300||empty($response['data']['id']))pp_out($response['status']?:502,$response['data']);
    $id=(string)$response['data']['id'];
    $_SESSION['paypal_orders'][$id]=[
        'paypal_order_id'=>$id,
        'items'=>$items,
        'subtotal'=>$subtotal,
        'discount_percent'=>$pricing['discount_percent'],
        'loyalty_discount_amount'=>$pricing['loyalty_discount_amount'],
        'promotion_discount_amount'=>$pricing['promotion_discount_amount'],
        'promotion'=>$pricing['promotion'],
        'discount_amount'=>$discountAmount,
        'loyalty_tier'=>$pricing['loyalty_tier'],
        'total'=>$total,
        'currency_snapshot'=>$currencySnapshot,
        'captured'=>false,
        'created_at'=>time()
    ];
    if ($invoiceSnapshot) {
        $_SESSION['paypal_orders'][$id]['invoice_order_id'] = (int)$invoiceSnapshot['invoice_order_id'];
        $_SESSION['paypal_orders'][$id]['invoice_order_number'] = (string)$invoiceSnapshot['invoice_order_number'];
    }
    pp_store_checkout_session($id, $_SESSION['paypal_orders'][$id]);
    pp_out(201,[
        'id'=>$id,
        'pricing'=>$pricing
    ]);
}
if($action==='capture'){
    $limit = hivenest_rate_limit('paypal-capture', 12, 3600, $paymentRateIdentifier);
    if (!$limit['allowed']) {
        header('Retry-After: ' . $limit['retry_after']);
        pp_out(429, ['error'=>'Too many payment capture attempts. Please wait or contact support.', 'retry_after'=>$limit['retry_after']]);
    }
    $id=preg_replace('/[^A-Z0-9-]/i','',(string)($input['order_id']??''));$snapshot=$_SESSION['paypal_orders'][$id]??null;if(!$snapshot)pp_out(409,['error'=>'Checkout session expired or order was not created by this session.']);if(!empty($snapshot['captured']))pp_out(409,['error'=>'This PayPal order has already been captured.']);
    $response=pp_request('POST','/v2/checkout/orders/'.rawurlencode($id).'/capture',null);$data=$response['data'];$capture=$data['purchase_units'][0]['payments']['captures'][0]??[];$value=(float)($capture['amount']['value']??-1);
    if($response['status']<200||$response['status']>=300||($capture['status']??'')!=='COMPLETED'||abs($value-(float)$snapshot['total'])>0.001||($capture['amount']['currency_code']??'')!=='USD')pp_out($response['status']?:502,$data);
    $_SESSION['paypal_orders'][$id]['captured']=true;$data['hivenest_order_number']=pp_save($snapshot,$capture);pp_out(200,$data);
}
pp_out(400,['error'=>'Unknown PayPal action']);
