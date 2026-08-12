<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/infrastructure_monitoring.php';
require_once __DIR__ . '/../utilities/rate_limiter.php';

function hivenest_monitoring_ingest_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    hivenest_monitoring_ingest_out(405, ['error' => 'POST required.']);
}
$limit = hivenest_rate_limit('monitoring-ingest', 120, 60);
if (!$limit['allowed']) {
    header('Retry-After: ' . $limit['retry_after']);
    hivenest_monitoring_ingest_out(429, ['error' => 'Monitoring ingestion rate exceeded.']);
}
$body = (string)file_get_contents('php://input');
if ($body === '' || strlen($body) > 65536) hivenest_monitoring_ingest_out(413, ['error' => 'Invalid monitoring payload size.']);
$timestamp = trim((string)($_SERVER['HTTP_X_MONITOR_TIMESTAMP'] ?? ''));
$signature = trim((string)($_SERVER['HTTP_X_MONITOR_SIGNATURE'] ?? ''));
if (!hivenest_monitoring_verify_signature($body, $timestamp, $signature)) {
    hivenest_monitoring_ingest_out(401, ['error' => 'Monitoring signature is invalid or expired.']);
}
$input = json_decode($body, true);
if (!is_array($input)) hivenest_monitoring_ingest_out(422, ['error' => 'JSON object required.']);

$eventId = trim((string)($input['event_id'] ?? ''));
$nodeKey = strtolower(trim((string)($input['node_key'] ?? '')));
$displayName = trim((string)($input['display_name'] ?? $nodeKey));
$provider = trim((string)($input['provider'] ?? 'trusted-agent'));
$status = strtolower(trim((string)($input['status'] ?? 'unknown')));
if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,99}$/', $nodeKey)) hivenest_monitoring_ingest_out(422, ['error' => 'Invalid node key.']);
if (!preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $eventId)) hivenest_monitoring_ingest_out(422, ['error' => 'Invalid event ID.']);
if (!in_array($status, ['up','degraded','down','unknown'], true)) hivenest_monitoring_ingest_out(422, ['error' => 'Invalid node status.']);
$displayName = function_exists('mb_substr') ? mb_substr($displayName, 0, 150) : substr($displayName, 0, 150);
$provider = function_exists('mb_substr') ? mb_substr($provider, 0, 100) : substr($provider, 0, 100);

try {
    $cpu = hivenest_monitoring_number($input['cpu_percent'] ?? null, 0, 100);
    $memory = hivenest_monitoring_number($input['memory_percent'] ?? null, 0, 100);
    $disk = hivenest_monitoring_number($input['disk_percent'] ?? null, 0, 100);
    $rx = hivenest_monitoring_number($input['network_rx_bps'] ?? null, 0, 1.0e15);
    $tx = hivenest_monitoring_number($input['network_tx_bps'] ?? null, 0, 1.0e15);
    $latency = hivenest_monitoring_number($input['latency_ms'] ?? null, 0, 86400000);
    $uptime = $input['uptime_seconds'] ?? null;
    if ($uptime !== null && (!is_numeric($uptime) || (int)$uptime < 0)) throw new InvalidArgumentException('Invalid uptime.');
    $uptime = $uptime !== null ? (int)$uptime : null;
} catch (InvalidArgumentException $e) {
    hivenest_monitoring_ingest_out(422, ['error' => $e->getMessage()]);
}

$observedUnix = isset($input['observed_at']) && is_numeric($input['observed_at'])
    ? (int)$input['observed_at']
    : (int)$timestamp;
if (abs(time() - $observedUnix) > 600) hivenest_monitoring_ingest_out(422, ['error' => 'Observation time is outside the accepted window.']);
$observedAt = gmdate('Y-m-d H:i:s', $observedUnix);
$db = hivenest_db();
if (!$db) hivenest_monitoring_ingest_out(503, ['error' => 'Monitoring database is unavailable.']);

try {
    $db->beginTransaction();
    $nodeStmt = $db->prepare(
        'INSERT INTO monitoring_nodes
            (node_key,display_name,provider,status,last_event_id,last_seen_at,cpu_percent,memory_percent,
             disk_percent,network_rx_bps,network_tx_bps,latency_ms,uptime_seconds)
         VALUES (:node_key,:display_name,:provider,:status,:event_id,:observed_at,:cpu,:memory,:disk,:rx,:tx,:latency,:uptime)
         ON DUPLICATE KEY UPDATE
            display_name=VALUES(display_name),provider=VALUES(provider),status=VALUES(status),
            last_event_id=VALUES(last_event_id),last_seen_at=VALUES(last_seen_at),
            cpu_percent=VALUES(cpu_percent),memory_percent=VALUES(memory_percent),disk_percent=VALUES(disk_percent),
            network_rx_bps=VALUES(network_rx_bps),network_tx_bps=VALUES(network_tx_bps),
            latency_ms=VALUES(latency_ms),uptime_seconds=VALUES(uptime_seconds)'
    );
    $params = [
        'node_key' => $nodeKey, 'display_name' => $displayName, 'provider' => $provider,
        'status' => $status, 'event_id' => $eventId, 'observed_at' => $observedAt,
        'cpu' => $cpu, 'memory' => $memory, 'disk' => $disk, 'rx' => $rx, 'tx' => $tx,
        'latency' => $latency, 'uptime' => $uptime,
    ];
    $nodeStmt->execute($params);
    $idStmt = $db->prepare('SELECT id FROM monitoring_nodes WHERE node_key=:node_key LIMIT 1');
    $idStmt->execute(['node_key' => $nodeKey]);
    $nodeId = (int)$idStmt->fetchColumn();
    $sample = $db->prepare(
        'INSERT INTO monitoring_samples
            (node_id,event_id,observed_at,status,cpu_percent,memory_percent,disk_percent,
             network_rx_bps,network_tx_bps,latency_ms,uptime_seconds)
         VALUES (:node_id,:event_id,:observed_at,:status,:cpu,:memory,:disk,:rx,:tx,:latency,:uptime)'
    );
    $sample->execute([
        'node_id' => $nodeId,
        'event_id' => $eventId,
        'observed_at' => $observedAt,
        'status' => $status,
        'cpu' => $cpu,
        'memory' => $memory,
        'disk' => $disk,
        'rx' => $rx,
        'tx' => $tx,
        'latency' => $latency,
        'uptime' => $uptime,
    ]);

    $threshold = hivenest_monitoring_thresholds();
    hivenest_monitoring_sync_alert(
        $db,
        $nodeId,
        'availability',
        in_array($status, ['down', 'degraded'], true),
        $status === 'down' ? 'critical' : 'warning',
        "{$displayName} is reporting " . strtoupper($status) . '.',
        $observedAt
    );
    hivenest_monitoring_sync_alert($db, $nodeId, 'cpu', $cpu !== null && $cpu >= $threshold['cpu'], 'warning', "{$displayName} CPU is {$cpu}%.", $observedAt);
    hivenest_monitoring_sync_alert($db, $nodeId, 'memory', $memory !== null && $memory >= $threshold['memory'], 'warning', "{$displayName} memory is {$memory}%.", $observedAt);
    hivenest_monitoring_sync_alert($db, $nodeId, 'disk', $disk !== null && $disk >= $threshold['disk'], $disk !== null && $disk >= 95 ? 'critical' : 'warning', "{$displayName} disk is {$disk}%.", $observedAt);
    hivenest_monitoring_sync_alert($db, $nodeId, 'latency', $latency !== null && $latency >= $threshold['latency'], 'warning', "{$displayName} latency is {$latency} ms.", $observedAt);
    $db->commit();

    if (random_int(1, 100) === 1) {
        $days = max(7, min(3650, (int)hivenest_monitoring_env('MONITORING_RETENTION_DAYS', '90')));
        $db->exec('DELETE FROM monitoring_samples WHERE observed_at < DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)');
    }
    hivenest_monitoring_ingest_out(202, ['ok' => true, 'event_id' => $eventId]);
} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    if ((string)$e->getCode() === '23000') hivenest_monitoring_ingest_out(200, ['ok' => true, 'duplicate' => true, 'event_id' => $eventId]);
    error_log('Monitoring ingest database failure: ' . $e->getMessage());
    hivenest_monitoring_ingest_out(503, ['error' => 'Monitoring sample could not be stored.']);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('Monitoring ingest failure: ' . $e->getMessage());
    hivenest_monitoring_ingest_out(503, ['error' => 'Monitoring sample could not be stored.']);
}
