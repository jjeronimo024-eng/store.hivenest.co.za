<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/customer_loyalty.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'GET required']);
    exit;
}

$customerId = (int)($_SESSION['customer_id'] ?? 0);
if ($customerId <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Customer login required.']);
    exit;
}

$db = hivenest_db();
if (!$db) {
    http_response_code(503);
    echo json_encode(['error' => 'Customer database is unavailable.']);
    exit;
}

try {
    echo json_encode([
        'loyalty' => hivenest_customer_loyalty($db, $customerId, false),
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Customer loyalty lookup failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Loyalty status is temporarily unavailable.']);
}
