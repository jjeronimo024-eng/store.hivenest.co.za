<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../utilities/myorderbox_bridge.php';

function worker_out(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

$token = hivenest_bridge_env('PROVISIONING_WORKER_TOKEN', '');
$provided = (string)($_GET['token'] ?? ($_SERVER['HTTP_X_PROVISIONING_TOKEN'] ?? ''));

if (PHP_SAPI !== 'cli') {
    if ($token === '') {
        worker_out(503, ['ok' => false, 'error' => 'PROVISIONING_WORKER_TOKEN is not configured.']);
    }
    if (!hash_equals($token, $provided)) {
        worker_out(403, ['ok' => false, 'error' => 'Invalid provisioning worker token.']);
    }
}

$limit = PHP_SAPI === 'cli'
    ? (int)($argv[1] ?? 10)
    : (int)($_GET['limit'] ?? 10);

$result = hivenest_process_provisioning_jobs($limit);
hivenest_log_worker_run(PHP_SAPI === 'cli' ? 'cli' : 'cron', $result);
worker_out(200, $result);
