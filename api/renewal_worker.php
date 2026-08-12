<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../utilities/myorderbox_bridge.php';
require_once __DIR__ . '/../utilities/service_renewals.php';

function renewal_worker_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

$configuredToken = hivenest_bridge_env('PROVISIONING_WORKER_TOKEN', '');
$providedToken = (string)($_GET['token'] ?? ($_SERVER['HTTP_X_PROVISIONING_TOKEN'] ?? ''));
if (PHP_SAPI !== 'cli') {
    if ($configuredToken === '') {
        renewal_worker_out(503, ['ok' => false, 'error' => 'PROVISIONING_WORKER_TOKEN is not configured.']);
    }
    if (!hash_equals($configuredToken, $providedToken)) {
        renewal_worker_out(403, ['ok' => false, 'error' => 'Invalid renewal worker token.']);
    }
}

$days = PHP_SAPI === 'cli' ? (int)($argv[1] ?? 30) : (int)($_GET['days'] ?? 30);
$limit = PHP_SAPI === 'cli' ? (int)($argv[2] ?? 100) : (int)($_GET['limit'] ?? 100);
$result = hivenest_generate_renewal_invoices($days, $limit);
renewal_worker_out(!empty($result['ok']) ? 200 : 503, $result);
