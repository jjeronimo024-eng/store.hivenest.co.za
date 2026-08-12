<?php
/**
 * Domain Services API Handler
 * Integrated with MyOrderBox Reseller API via Backend
 */

// Backend API base URL
define('BACKEND_API_URL', 'http://localhost:8005/api/reseller');

/**
 * Make a request to the backend API
 */
function callBackendAPI($endpoint, $method = 'GET', $data = null) {
    $url = BACKEND_API_URL . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($data))
            ]);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'error' => 'API request failed: ' . $error
        ];
    }
    
    $result = json_decode($response, true);
    
    if (!$result) {
        return [
            'success' => false,
            'error' => 'Invalid API response',
            'raw_response' => $response
        ];
    }
    
    return $result;
}

/**
 * Get domain pricing from MyOrderBox API
 */
function getDomainPricing() {
    // Call reseller pricing endpoint
    $result = callBackendAPI('/pricing/reseller');
    
    if (!$result['success']) {
        // Fallback to cached pricing if API fails
        return getFallbackDomainPricing();
    }
    
    // Transform pricing data for frontend
    $pricing_data = $result['data'] ?? [];
    
    // TODO: Parse and format pricing data based on MyOrderBox response structure
    // For now, return the raw data
    echo json_encode([
        'success' => true,
        'data' => $pricing_data,
        'source' => 'myorderbox_api'
    ]);
}

/**
 * Fallback pricing (cached) when API is unavailable
 */
function getFallbackDomainPricing() {
    $domain_pricing = [
        [
            'tld' => '.com',
            'price' => 12.99,
            'renewal_price' => 14.99,
            'currency' => 'USD',
            'period' => 'yearly',
            'popular' => true
        ],
        [
            'tld' => '.net',
            'price' => 13.99,
            'renewal_price' => 15.99,
            'currency' => 'USD',
            'period' => 'yearly',
            'popular' => false
        ],
        [
            'tld' => '.org',
            'price' => 13.99,
            'renewal_price' => 15.99,
            'currency' => 'USD',
            'period' => 'yearly',
            'popular' => false
        ],
        [
            'tld' => '.co.za',
            'price' => 89.00,
            'renewal_price' => 89.00,
            'currency' => 'ZAR',
            'period' => 'yearly',
            'popular' => true
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $domain_pricing,
        'count' => count($domain_pricing),
        'source' => 'fallback_cache'
    ]);
}

/**
 * Check domain availability using MyOrderBox API
 */
function checkDomainAvailability() {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['domain'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Domain name is required']);
        return;
    }
    
    $domain = strtolower(trim($input['domain']));
    $tlds = isset($input['tlds']) ? $input['tlds'] : ['com', 'net', 'org', 'co.za'];
    
    // Validate domain name format
    if (!preg_match('/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?$/', $domain)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid domain name format']);
        return;
    }
    
    // Build query string for TLDs
    $tld_query = implode(',', array_map('trim', $tlds));
    
    // Call backend API
    $result = callBackendAPI("/domains/check-availability?domain={$domain}&tlds={$tld_query}");
    
    if (!$result['success']) {
        http_response_code(502);
        echo json_encode([
            'success' => false,
            'error' => 'Live domain availability is temporarily unavailable. No availability assumption was made.'
        ]);
        return;
    }
    
    // Transform response for frontend
    $formatted_results = [];
    foreach ($result['results'] as $domain_result) {
        $formatted_results[] = [
            'domain' => $domain_result['domain'],
            'tld' => $domain_result['tld'],
            'available' => $domain_result['available'],
            'status' => $domain_result['status'],
            'is_premium' => $domain_result['is_premium'] ?? false,
            'price' => $domain_result['pricing']['create'] ?? 'N/A',
            'currency' => 'USD',
            'period' => 'yearly'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'domain' => $domain,
        'results' => $formatted_results,
        'total' => count($formatted_results),
        'timestamp' => date('Y-m-d H:i:s'),
        'source' => 'myorderbox_api'
    ]);
}

/**
 * Legacy cached price lookup. Availability is never simulated.
 */
/** Get cached pricing for display only; never for availability decisions. */
function getDomainPriceForTLD($tld) {
    $pricing = [
        'com' => ['price' => 12.99, 'currency' => 'USD'],
        'net' => ['price' => 13.99, 'currency' => 'USD'],
        'org' => ['price' => 13.99, 'currency' => 'USD'],
        'co.za' => ['price' => 89.00, 'currency' => 'ZAR'],
        'info' => ['price' => 11.99, 'currency' => 'USD'],
        'biz' => ['price' => 12.99, 'currency' => 'USD'],
        'io' => ['price' => 49.99, 'currency' => 'USD'],
        'tech' => ['price' => 39.99, 'currency' => 'USD']
    ];
    
    return isset($pricing[$tld]) ? $pricing[$tld] : ['price' => 15.99, 'currency' => 'USD'];
}

/**
 * Register a domain via MyOrderBox API
 */
function registerDomain() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['domain_name']) || empty($input['customer_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required parameters']);
        return;
    }
    
    // Prepare registration data
    $registration_data = [
        'domain_name' => $input['domain_name'],
        'years' => $input['years'] ?? 1,
        'customer_id' => $input['customer_id'],
        'contacts' => $input['contacts'] ?? [],
        'nameservers' => $input['nameservers'] ?? null
    ];
    
    // Call backend API
    $result = callBackendAPI('/domains/register', 'POST', $registration_data);
    
    echo json_encode($result);
}

/**
 * Search domains
 */
function searchDomains() {
    $customer_id = $_GET['customer_id'] ?? null;
    $status = $_GET['status'] ?? null;
    $page_no = $_GET['page_no'] ?? 1;
    $no_of_records = $_GET['no_of_records'] ?? 50;
    
    $query_params = "?page_no={$page_no}&no_of_records={$no_of_records}";
    if ($customer_id) $query_params .= "&customer_id={$customer_id}";
    if ($status) $query_params .= "&status={$status}";
    
    $result = callBackendAPI("/domains/search{$query_params}");
    
    echo json_encode($result);
}

// Route handling
$request_method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'check-availability';

header('Content-Type: application/json');

switch ($action) {
    case 'pricing':
        getDomainPricing();
        break;
    
    case 'check-availability':
        if ($request_method === 'POST') {
            checkDomainAvailability();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    
    case 'register':
        if ($request_method === 'POST') {
            registerDomain();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    
    case 'search':
        searchDomains();
        break;
    
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Unknown action']);
}
?>
