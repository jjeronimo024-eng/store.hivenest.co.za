<?php
// HiveNest API Handler
// Main API router for handling frontend requests

header('Content-Type: application/json');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = [
    'https://hivenest.co.za',
    'https://cp.hivenest.co.za',
    'https://crm.hivenest.co.za',
    'https://hivenest.holohive.co.za',
];
if ($origin !== '' && in_array($origin, $allowed_origins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Request-With, X-CSRF-Token');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get the request URI and method
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Remove query string and decode URL
$path = parse_url($request_uri, PHP_URL_PATH);
$path = urldecode($path);

// Remove /api prefix if present
$path = preg_replace('/^\/api/', '', $path);

// Enforce the same inactivity timeout on every private customer route, not
// only in the client portal JavaScript.
$privateCustomerPaths = [
    '/customers/dashboard',
    '/customers/loyalty',
    '/customers/profile',
    '/customers/change-password',
    '/customers/two-factor',
    '/customers/billing',
    '/customers/services',
    '/customers/service-credentials',
    '/customers/domains',
    '/customers/domain-dns',
    '/customers/mailboxes',
    '/customers/service-files',
    '/customers/service-requests',
    '/customers/service-workflow',
    '/customers/onboarding',
    '/customers/support',
    '/customer-notifications',
    '/customer-notifications.php',
    '/customers/notices',
];
if (in_array($path, $privateCustomerPaths, true)) {
    require_once __DIR__ . '/../utilities/customer_session.php';
    hivenest_customer_session_configure();
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $customerSession = hivenest_customer_session_status(true);
    if (!$customerSession['authenticated']) {
        http_response_code(401);
        echo json_encode([
            'authenticated' => false,
            'expired' => (bool)$customerSession['expired'],
            'error' => $customerSession['expired'] ? 'Customer session expired.' : 'Customer login required.',
        ]);
        exit;
    }
    if (in_array($request_method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        hivenest_customer_csrf_require_json();
    }
}

// Route the request
try {
    switch ($path) {
        case '/auth/me':
            if ($request_method === 'GET') {
                require_once 'customer-auth-me.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/customer-auth':
        case '/customer-auth.php':
            require_once 'customer-auth.php';
            break;

        case '/monitoring/ingest':
            if ($request_method === 'POST') {
                require_once 'monitoring-ingest.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/mail/events':
            if ($request_method === 'POST') {
                require_once 'mail-events.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/customer-notifications':
        case '/customer-notifications.php':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'customer-notifications.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/contact':
            if ($request_method === 'POST') {
                require_once 'contact.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        case '/newsletter/subscribe':
            if ($request_method === 'POST') {
                require_once 'newsletter.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        case '/customers/register':
            http_response_code(410);
            echo json_encode([
                'error' => 'This legacy registration endpoint is retired.',
                'endpoint' => '/api/customer-auth.php?action=register',
            ]);
            break;
            
        case '/customers/login':
            http_response_code(410);
            echo json_encode([
                'error' => 'This legacy login endpoint is retired.',
                'endpoint' => '/api/customer-auth.php?action=login',
            ]);
            break;

        case '/customers/dashboard':
            if ($request_method === 'GET') {
                require_once 'customer-dashboard.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/customers/notices':
            if ($request_method === 'GET') {
                require_once 'customer-notices.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/customers/loyalty':
            if ($request_method === 'GET') {
                require_once 'customer-loyalty.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/customers/profile':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'customer-profile.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/currency':
        case '/currency.php':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'currency.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/customers/change-password':
            if ($request_method === 'POST') {
                require_once 'customer-change-password.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/customers/two-factor':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'customer-two-factor.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/customers/billing':
            if ($request_method === 'GET') {
                require_once 'customer-billing.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/customers/services':
            if ($request_method === 'GET') {
                require_once 'customer-services.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/customers/service-credentials':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'customer-service-credentials.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/customers/domains':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'customer-domains.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/customers/domain-dns':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'customer-domain-dns.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/customers/mailboxes':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'customer-mailboxes.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/customers/service-files':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'customer-service-files.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/customers/service-requests':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'customer-service-requests.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/customers/service-workflow':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'customer-service-workflow.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/customers/onboarding':
            if ($request_method === 'POST') {
                require_once 'customer-onboarding.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/customers/support':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'customer-support.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/support/attachment':
            if ($request_method === 'GET') {
                require_once 'support-attachment.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/workflow/file':
            if ($request_method === 'GET') {
                require_once 'workflow-file.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/service-files/download':
            if ($request_method === 'GET') {
                require_once 'service-file-download.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/onboarding/file':
            if ($request_method === 'GET') {
                require_once 'onboarding-file.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/crm/onboarding':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'crm-onboarding.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/crm/support':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'crm-support.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/crm/dashboard':
            if ($request_method === 'GET') {
                require_once 'crm-dashboard.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/crm/capabilities':
            if ($request_method === 'GET') {
                require_once 'crm-capabilities.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/crm/notices':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'crm-notices.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/crm/refunds':
            if ($request_method === 'POST') {
                require_once 'crm-refunds.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/chat':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'chat.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/crm/chat':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'crm-chat.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/crm/reports':
            if ($request_method === 'GET') {
                require_once 'crm-reports.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/crm/mail':
            if ($request_method === 'POST') {
                require_once 'crm-mail.php';
            } else {
                send_error('Method not allowed', 405);
            }
            break;

        case '/crm/email-templates':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'crm-email-templates.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/crm/mail-suppressions':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'crm-mail-suppressions.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/crm/customers':
            if ($request_method === 'GET') {
                require_once 'crm-customers.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/crm/customer-profile':
            if ($request_method === 'GET') {
                require_once 'crm-customer-profile.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/crm/customer-actions':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'crm-customer-actions.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/crm/orders':
            if ($request_method === 'GET') {
                require_once 'crm-orders.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/crm/services':
            if ($request_method === 'GET') {
                require_once 'crm-services.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/crm/service-credentials':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'crm-service-credentials.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/crm/work-queue':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'crm-work-queue.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/crm/service-workflow':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'crm-service-workflow.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/crm/service-actions':
            if (in_array($request_method, ['GET', 'POST'], true)) {
                require_once 'crm-service-actions.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        case '/hosting/plans':
            if ($request_method === 'GET') {
                require_once 'hosting.php';
                getHostingPlans();
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        case '/domains/pricing':
            if ($request_method === 'GET') {
                require_once 'domains.php';
                getDomainPricing();
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        case '/domains/check':
            if ($request_method === 'POST') {
                // Route all availability decisions through the hardened live
                // MyOrderBox endpoint. No random/demo fallback is permitted.
                require 'domains_live.php';
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        case '/ssl/certificates':
            if ($request_method === 'GET') {
                require_once 'ssl.php';
                getSSLCertificates();
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        case '/orders':
            if ($request_method === 'POST') {
                require_once 'orders.php';
                submitOrder();
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found', 'path' => $path]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}
?>
