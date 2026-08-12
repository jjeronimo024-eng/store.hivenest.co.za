<?php
declare(strict_types=1);

function hivenest_monitoring_env(string $key, string $default = ''): string
{
    $runtime = getenv($key);
    if (is_string($runtime) && trim($runtime) !== '') return trim($runtime);
    $path = defined('HIVENEST_ENV_PATH') ? HIVENEST_ENV_PATH : __DIR__ . '/../Backend/.env';
    if (!is_readable($path)) return $default;
    foreach (@file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$name, $value] = explode('=', $line, 2);
        if (trim($name) === $key) return trim(trim($value), "\"'");
    }
    return $default;
}

function hivenest_monitoring_number(mixed $value, float $min, float $max): ?float
{
    if ($value === null || $value === '') return null;
    if (!is_numeric($value)) throw new InvalidArgumentException('Metric must be numeric.');
    $number = (float)$value;
    if (!is_finite($number) || $number < $min || $number > $max) {
        throw new InvalidArgumentException('Metric is outside the permitted range.');
    }
    return round($number, 2);
}

function hivenest_monitoring_verify_signature(string $body, string $timestamp, string $signature): bool
{
    $secret = hivenest_monitoring_env('MONITORING_INGEST_SECRET');
    if (strlen($secret) < 32 || !preg_match('/^\d{10}$/', $timestamp)) return false;
    if (abs(time() - (int)$timestamp) > 300) return false;
    $signature = strtolower(trim(preg_replace('/^sha256=/i', '', $signature) ?? ''));
    if (!preg_match('/^[a-f0-9]{64}$/', $signature)) return false;
    return hash_equals(hash_hmac('sha256', $timestamp . '.' . $body, $secret), $signature);
}

function hivenest_monitoring_thresholds(): array
{
    return [
        'cpu' => min(100.0, max(1.0, (float)hivenest_monitoring_env('MONITORING_CPU_WARNING_PERCENT', '90'))),
        'memory' => min(100.0, max(1.0, (float)hivenest_monitoring_env('MONITORING_MEMORY_WARNING_PERCENT', '90'))),
        'disk' => min(100.0, max(1.0, (float)hivenest_monitoring_env('MONITORING_DISK_WARNING_PERCENT', '85'))),
        'latency' => max(1.0, (float)hivenest_monitoring_env('MONITORING_LATENCY_WARNING_MS', '1000')),
    ];
}

function hivenest_monitoring_sync_alert(
    PDO $db,
    int $nodeId,
    string $type,
    bool $active,
    string $severity,
    string $message,
    string $observedAt
): void {
    $select = $db->prepare(
        'SELECT id FROM monitoring_alerts
         WHERE node_id=:node_id AND alert_type=:alert_type AND status="open"
         ORDER BY id DESC LIMIT 1'
    );
    $select->execute(['node_id' => $nodeId, 'alert_type' => $type]);
    $alertId = (int)($select->fetchColumn() ?: 0);
    if ($active && $alertId > 0) {
        $update = $db->prepare(
            'UPDATE monitoring_alerts
             SET severity=:severity,message=:message,last_observed_at=:observed_at
             WHERE id=:id'
        );
        $update->execute(['severity' => $severity, 'message' => $message, 'observed_at' => $observedAt, 'id' => $alertId]);
    } elseif ($active) {
        $insert = $db->prepare(
            'INSERT INTO monitoring_alerts
                (node_id,alert_type,severity,status,message,opened_at,last_observed_at)
             VALUES (:node_id,:alert_type,:severity,"open",:message,:observed_at,:observed_at)'
        );
        $insert->execute([
            'node_id' => $nodeId,
            'alert_type' => $type,
            'severity' => $severity,
            'message' => $message,
            'observed_at' => $observedAt,
        ]);
    } elseif ($alertId > 0) {
        $resolve = $db->prepare(
            'UPDATE monitoring_alerts SET status="resolved",resolved_at=:observed_at,last_observed_at=:observed_at
             WHERE id=:id'
        );
        $resolve->execute(['observed_at' => $observedAt, 'id' => $alertId]);
    }
}
