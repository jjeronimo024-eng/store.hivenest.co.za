<?php
/**
 * Live Domain Services API Handler
 * Direct integration with MyOrderBox Reseller API
 */

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/rate_limiter.php';

// MyOrderBox API Configuration. Credentials must remain in Backend/.env,
// never in this public endpoint or its logs.
function hivenestDomainApiEnv(string $key, string $default = ''): string {
    static $values = null;
    if ($values === null) {
        $values = [];
        $env_file = __DIR__ . '/../Backend/.env';
        $lines = is_readable($env_file)
            ? (@file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [])
            : [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            [$env_key, $env_value] = explode('=', $line, 2);
            $env_key = trim($env_key);
            $env_value = trim($env_value);
            if (strlen($env_value) >= 2) {
                $first = $env_value[0];
                $last = $env_value[strlen($env_value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $env_value = substr($env_value, 1, -1);
                }
            }
            $values[$env_key] = $env_value;
        }
    }
    $process_value = getenv($key);
    if ($process_value !== false && $process_value !== '') return (string)$process_value;
    return isset($values[$key]) && $values[$key] !== '' ? (string)$values[$key] : $default;
}

define('MYORDERBOX_RESELLER_ID', hivenestDomainApiEnv('MYORDERBOX_RESELLER_ID'));
define('MYORDERBOX_API_KEY', hivenestDomainApiEnv('MYORDERBOX_API_KEY'));
define('MYORDERBOX_ENV', strtolower(hivenestDomainApiEnv('MYORDERBOX_ENV', 'production')));
define('MYORDERBOX_BASE_URL', rtrim(
    MYORDERBOX_ENV === 'test'
        ? hivenestDomainApiEnv('MYORDERBOX_TEST_URL', 'https://test.httpapi.com')
        : hivenestDomainApiEnv('MYORDERBOX_BASE_URL', 'https://httpapi.com'),
    '/'
));
// MyOrderBox publishes domaincheck.httpapi.com as the availability endpoint.
define('MYORDERBOX_DOMAIN_CHECK_URL', rtrim(hivenestDomainApiEnv('MYORDERBOX_DOMAIN_CHECK_URL', 'https://domaincheck.httpapi.com'), '/'));

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set headers
header('Content-Type: application/json');
$request_origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = array_filter(array_map('trim', explode(',', hivenestDomainApiEnv(
    'CORS_ALLOWED_ORIGINS',
    'https://hivenest.co.za,https://cp.hivenest.co.za,https://crm.hivenest.co.za,https://hivenest.holohive.co.za'
))));
if ($request_origin !== '' && in_array($request_origin, $allowed_origins, true)) {
    header('Access-Control-Allow-Origin: ' . $request_origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Cross-check commercial South African domains against the ZARC WHOIS server.
 * Returns true when registered, false when explicitly not found, and null when
 * the registry cannot be reached. An uncertain result must never be sold.
 */
function zarcDomainRegistered(string $domain, string $tld): ?bool {
    $servers = [
        'co.za' => 'coza-whois.registry.net.za',
        'org.za' => 'org-whois.registry.net.za',
        'net.za' => 'net-whois.registry.net.za',
        'web.za' => 'web-whois.registry.net.za',
    ];
    if (!isset($servers[$tld])) return null;
    $socket = @fsockopen($servers[$tld], 43, $errno, $error, 10);
    if (!$socket) return null;
    stream_set_timeout($socket, 10);
    fwrite($socket, $domain . "\r\n");
    $raw = '';
    while (!feof($socket) && strlen($raw) < 200000) $raw .= fgets($socket, 4096);
    fclose($socket);
    $raw = trim($raw);
    if ($raw === '') return null;
    if (preg_match('/(?:no match|not found|no entries found|available for registration)/i', $raw)) return false;
    if (preg_match('/^(?:Domain Name|Domain)\s*:\s*' . preg_quote($domain, '/') . '\s*$/mi', $raw)) return true;
    // A substantive registry record without an explicit not-found response is
    // considered registered; ambiguous service messages remain unknown.
    return preg_match('/^(?:Registrar|Registration Date|Creation Date|Name Server|Nameserver)\s*:/mi', $raw) ? true : null;
}

/**
 * Cross-check gTLD results against the authoritative RDAP service published
 * by IANA. This catches cases where the reseller availability endpoint returns
 * regthroughothers but the registry RDAP says the domain is actually not found.
 *
 * Returns true when RDAP confirms a registration, false when RDAP returns a
 * not-found response, and null when RDAP cannot be checked safely.
 */
function rdapDomainRegistered(string $domain, string $tld): ?bool {
    static $rdap_bootstrap = null;

    $tld = strtolower(ltrim(trim($tld), '.'));
    if ($tld === '' || strpos($tld, '.') !== false) return null;

    if ($rdap_bootstrap === null) {
        $ch = curl_init('https://data.iana.org/rdap/dns.json');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        $raw = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error || $http_code !== 200 || !$raw) {
            error_log('IANA RDAP bootstrap failed: ' . ($curl_error ?: 'HTTP ' . $http_code));
            $rdap_bootstrap = [];
        } else {
            $data = json_decode($raw, true);
            $rdap_bootstrap = is_array($data['services'] ?? null) ? $data['services'] : [];
        }
    }

    $rdap_base_url = null;
    foreach ($rdap_bootstrap as $service) {
        $tlds = $service[0] ?? [];
        $urls = $service[1] ?? [];
        if (is_array($tlds) && in_array($tld, array_map('strtolower', $tlds), true) && !empty($urls[0])) {
            $rdap_base_url = rtrim((string)$urls[0], '/');
            break;
        }
    }

    if (!$rdap_base_url) return null;

    $rdap_url = $rdap_base_url . '/domain/' . rawurlencode(strtolower($domain));
    $ch = curl_init($rdap_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/rdap+json, application/json']);
    $raw = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        error_log('RDAP check failed for ' . $domain . ': ' . $curl_error);
        return null;
    }

    if ($http_code === 404) return false;
    if ($http_code !== 200 || !$raw) return null;

    $data = json_decode($raw, true);
    if (!is_array($data)) return null;

    return !empty($data['ldhName']) || !empty($data['handle']) || !empty($data['events']);
}

/**
 * Make a request to MyOrderBox API
 */
function callMyOrderBoxAPI($endpoint, $params = [], $use_domain_check_url = false) {
    if (MYORDERBOX_RESELLER_ID === '' || MYORDERBOX_API_KEY === '') {
        return ['success' => false, 'error' => 'Domain API credentials are not configured'];
    }
    $base_url = $use_domain_check_url ? MYORDERBOX_DOMAIN_CHECK_URL : MYORDERBOX_BASE_URL;
    
    // Add authentication
    $params['auth-userid'] = MYORDERBOX_RESELLER_ID;
    $params['api-key'] = MYORDERBOX_API_KEY;
    
    // Build query string - handle array parameters specially for MyOrderBox API
    $query_parts = [];
    foreach ($params as $key => $value) {
        if (is_array($value)) {
            // For array values, add each as a separate parameter (e.g., tlds=com&tlds=net)
            foreach ($value as $val) {
                $query_parts[] = urlencode($key) . '=' . urlencode($val);
            }
        } else {
            $query_parts[] = urlencode($key) . '=' . urlencode($value);
        }
    }
    $query_string = implode('&', $query_parts);
    
    // Build URL
    $url = $base_url . $endpoint . '?' . $query_string;
    
    // Log the request for debugging (mask API key for security)
    $masked_url = str_replace(MYORDERBOX_API_KEY, '****', $url);
    error_log("MyOrderBox API Request: " . $masked_url);
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    // Execute request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Handle errors
    if ($curl_error) {
        error_log("MyOrderBox API Error: " . $curl_error);
        return [
            'success' => false,
            'error' => 'API request failed: ' . $curl_error
        ];
    }
    
    // Parse response
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("MyOrderBox JSON Parse Error: " . json_last_error_msg());
        return [
            'success' => false,
            'error' => 'Invalid API response - JSON parsing failed',
            'json_error' => json_last_error_msg()
        ];
    }
    
    // Check if API returned an error
    if ($http_code !== 200) {
        error_log("MyOrderBox API returned HTTP $http_code: " . json_encode($data));
        return [
            'success' => false,
            'error' => "API returned HTTP $http_code",
            'data' => $data,
            'http_code' => $http_code
        ];
    }
    
    // Check if API returned an error in the response
    if (isset($data['errorvalue'])) {
        error_log("MyOrderBox API Error: " . json_encode($data['errorvalue']));
        return [
            'success' => false,
            'error' => 'MyOrderBox API Error: ' . ($data['errorvalue']['error'] ?? 'Unknown error'),
            'data' => $data,
            'http_code' => $http_code
        ];
    }
    
    return [
        'success' => true,
        'data' => $data,
        'http_code' => $http_code
    ];
}

/**
 * Check domain availability using MyOrderBox API
 */
function checkDomainAvailability() {
    // Get JSON input
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    if (!$input || empty($input['domain'])) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Domain name is required'
        ]);
        return;
    }
    
    $domain = strtolower(trim($input['domain']));
    $tlds = isset($input['tlds']) ? $input['tlds'] : ['com'];
    
    // Validate domain name format
    if (!preg_match('/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?$/', $domain)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid domain name format']);
        return;
    }
    
    // Build domain list for checking
    $domain_names = [];
    $clean_tlds = [];
    foreach ($tlds as $tld) {
        $tld = ltrim($tld, '.');
        $clean_tlds[] = $tld;
        $domain_names[] = $domain . '.' . $tld;
    }
    
    // Call MyOrderBox domain availability API
    // API expects: domain-name=basedomain&tlds[]=com&tlds[]=net
    $params = [
        'domain-name' => [$domain], // API documents this parameter as an array
        'tlds' => $clean_tlds      // Array of TLDs to check
    ];
    
    $result = callMyOrderBoxAPI('/api/domains/available.json', $params, true);
    
    if (!$result['success']) {
        // If API fails, return error with details
        http_response_code(502);
        echo json_encode([
            'success' => false,
            'error' => 'Domain availability check failed',
            'details' => $result['error'],
            'http_code' => isset($result['http_code']) ? $result['http_code'] : null
        ]);
        return;
    }
    
    // Transform response for frontend
    $api_data = $result['data'];
    $formatted_results = [];
    
    // Check if API returned valid data
    if (!is_array($api_data) || empty($api_data)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid API response format',
            'details' => 'API did not return expected data structure'
        ]);
        return;
    }
    
    foreach ($domain_names as $full_domain) {
        $tld = substr($full_domain, strpos($full_domain, '.') + 1);
        
        // Check if domain info exists in response
        if (isset($api_data[$full_domain])) {
            $domain_info = $api_data[$full_domain];
            $status = strtolower((string)($domain_info['status'] ?? 'unknown'));
            $premium_statuses = ['premium', 'premium_domain', 'premium_quote_required'];
            $registered_statuses = ['registered', 'regthroughus', 'regthroughothers', 'active', 'taken'];
            $is_premium = isset($domain_info['costHash'])
                || !empty($domain_info['isPremium'])
                || !empty($domain_info['premium'])
                || in_array($status, $premium_statuses, true);
            $is_available = ($status === 'available');

            if ($is_available && isset(['co.za'=>1,'org.za'=>1,'net.za'=>1,'web.za'=>1][$tld])) {
                $registered = zarcDomainRegistered($full_domain, $tld);
                if ($registered === true) {
                    $status = 'regthroughothers';
                    $is_available = false;
                } elseif ($registered === null) {
                    $status = 'unknown';
                    $is_available = false;
                }
            }

            if (!$is_available && in_array($status, $registered_statuses, true) && strpos($tld, '.') === false) {
                $rdap_registered = rdapDomainRegistered($full_domain, $tld);
                if ($rdap_registered === false) {
                    error_log('RDAP override: ' . $full_domain . ' returned ' . $status . ' from MyOrderBox but not found in registry RDAP.');
                    $status = 'available';
                    $is_available = true;
                } elseif ($rdap_registered === true) {
                    $domain_info['registry_verified'] = true;
                } else {
                    $domain_info['registry_verified'] = false;
                    $status = 'registry_confirmation_required';
                    $is_available = false;
                }
            }

            // Premium names require a live selling-price calculation. Never
            // allow checkout using the ordinary cached TLD price.
            if ($is_available && $is_premium) {
                $status = 'premium_quote_required';
                $is_available = false;
            }
            
            $formatted_results[] = [
                'domain' => $full_domain,
                'tld' => $tld,
                'available' => $is_available,
                'status' => $status,
                'registrar' => $domain_info['registrar'] ?? $domain_info['registrarName'] ?? $domain_info['provider'] ?? null,
                'registry_verified' => $domain_info['registry_verified'] ?? null,
                'is_premium' => $is_premium,
                'requires_quote' => $is_premium,
                'price' => getDomainPriceForTLD($tld),
                'currency' => 'USD',
                'period' => 'yearly'
            ];
        } else {
            // Domain not in response - mark as unavailable
            $formatted_results[] = [
                'domain' => $full_domain,
                'tld' => $tld,
                'available' => false,
                'status' => 'unknown',
                'is_premium' => false,
                'price' => getDomainPriceForTLD($tld),
                'currency' => 'USD',
                'period' => 'yearly'
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'domain' => $domain,
        'results' => $formatted_results,
        'total' => count($formatted_results),
        'timestamp' => date('Y-m-d H:i:s'),
        'source' => 'myorderbox_live',
        'environment' => MYORDERBOX_ENV
    ]);
}

/**
 * Get cached pricing for a TLD
 */
function getDomainPriceForTLD($tld) {
    $normalized_tld = '.' . ltrim(strtolower(trim((string)$tld)), '.');
    $bare_tld = ltrim($normalized_tld, '.');

    try {
        if (function_exists('hivenest_db')) {
            $db = hivenest_db();
            if ($db instanceof PDO) {
                $stmt = $db->prepare("
                    SELECT register_price
                    FROM domain_extensions
                    WHERE extension = :extension
                      AND is_active = 1
                    LIMIT 1
                ");
                $stmt->execute([':extension' => $normalized_tld]);
                $price = $stmt->fetchColumn();
                if ($price !== false && is_numeric($price) && (float)$price > 0) {
                    return round((float)$price, 2);
                }
            }
        }
    } catch (Throwable $e) {
        error_log('Domain price DB lookup failed for ' . $normalized_tld . ': ' . $e->getMessage());
    }

    $pricing = [
        'com' => 12.99,
        'net' => 13.99,
        'org' => 13.99,
        'co.za' => 8.99,
        'info' => 11.99,
        'biz' => 12.99,
        'io' => 49.99,
        'tech' => 39.99,
        'dev' => 15.99,
        'app' => 18.99
    ];
    
    return $pricing[$bare_tld] ?? 15.99;
}

/**
 * Get domain pricing
 */
function getDomainPricing() {
    $domain_pricing = [
        ['tld' => '.com', 'price' => 12.99, 'renewal_price' => 14.99, 'currency' => 'USD', 'period' => 'yearly', 'popular' => true],
        ['tld' => '.net', 'price' => 13.99, 'renewal_price' => 15.99, 'currency' => 'USD', 'period' => 'yearly', 'popular' => false],
        ['tld' => '.org', 'price' => 13.99, 'renewal_price' => 15.99, 'currency' => 'USD', 'period' => 'yearly', 'popular' => false],
        ['tld' => '.co.za', 'price' => 8.99, 'renewal_price' => 8.99, 'currency' => 'USD', 'period' => 'yearly', 'popular' => true],
        ['tld' => '.io', 'price' => 49.99, 'renewal_price' => 49.99, 'currency' => 'USD', 'period' => 'yearly', 'popular' => true]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $domain_pricing,
        'count' => count($domain_pricing)
    ]);
}

// Route handling
$request_method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'check-availability';

switch ($action) {
    case 'test':
        http_response_code(404);
        echo json_encode(['error' => 'Unknown action']);
        break;
    
    case 'pricing':
        getDomainPricing();
        break;
    
    case 'check-availability':
        if ($request_method === 'POST') {
            $limit = hivenest_rate_limit(
                'domain-availability',
                40,
                300,
                trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'))
            );
            header('X-RateLimit-Remaining: ' . $limit['remaining']);
            if (!$limit['allowed']) {
                header('Retry-After: ' . $limit['retry_after']);
                http_response_code(429);
                echo json_encode([
                    'error' => 'Too many domain checks. Please wait before searching again.',
                    'retry_after' => $limit['retry_after'],
                ]);
                break;
            }
            checkDomainAvailability();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed. Use POST.']);
        }
        break;
    
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Unknown action']);
}
?>
