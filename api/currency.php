<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/currency.php';

function hivenest_currency_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

$db = hivenest_db();
$customerId = (int)($_SESSION['customer_id'] ?? 0);
$allowed = hivenest_currency_codes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode((string)file_get_contents('php://input'), true);
    $currency = strtoupper(trim((string)($input['currency'] ?? '')));
    if (!in_array($currency, $allowed, true)) {
        hivenest_currency_out(422, ['error' => 'Currency must be USD, ZAR, EUR, or SGD.']);
    }

    $_SESSION['display_currency'] = $currency;
    if ($db && $customerId > 0) {
        try {
            $stmt = $db->prepare(
                'UPDATE customers
                 SET preferred_currency = :currency, updated_at = NOW()
                 WHERE id = :customer_id'
            );
            $stmt->execute(['currency' => $currency, 'customer_id' => $customerId]);
        } catch (Throwable $e) {
            error_log('Currency customer preference update failed: ' . $e->getMessage());
            hivenest_currency_out(500, ['error' => 'Currency preference could not be saved.']);
        }
    }
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    hivenest_currency_out(405, ['error' => 'GET or POST required.']);
}

hivenest_currency_out(200, [
    'base_currency' => 'USD',
    'display_currency' => hivenest_currency_preference($db, $customerId),
    'supported' => $allowed,
    'rates' => hivenest_currency_rates($db),
    'charge_notice' => 'Display conversion is indicative. Checkout and PayPal are charged in USD.',
]);

