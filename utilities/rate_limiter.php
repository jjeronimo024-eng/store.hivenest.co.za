<?php
declare(strict_types=1);

/**
 * Small file-backed limiter for public endpoints.
 * Client identifiers are HMAC-hashed before storage; raw IP addresses are not
 * written to the rate-limit file.
 *
 * @return array{allowed:bool,remaining:int,retry_after:int}
 */
function hivenest_rate_limit(
    string $bucket,
    int $limit,
    int $windowSeconds,
    ?string $identifier = null
): array {
    $limit = max(1, $limit);
    $windowSeconds = max(1, $windowSeconds);
    $identifier ??= trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $secret = hivenest_rate_limit_secret();
    $key = hash_hmac('sha256', $bucket . '|' . $identifier, $secret);
    $path = __DIR__ . '/../Backend/logs/rate_limits.json';
    $directory = dirname($path);

    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        error_log('Rate limiter could not create its storage directory.');
        return ['allowed' => true, 'remaining' => $limit, 'retry_after' => 0];
    }

    $handle = @fopen($path, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        error_log('Rate limiter could not lock its storage file.');
        return ['allowed' => true, 'remaining' => $limit, 'retry_after' => 0];
    }

    try {
        rewind($handle);
        $raw = stream_get_contents($handle);
        $store = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($store)) $store = [];

        $now = time();
        $globalCutoff = $now - 86400;
        foreach ($store as $storedKey => $timestamps) {
            if (!is_array($timestamps)) {
                unset($store[$storedKey]);
                continue;
            }
            $timestamps = array_values(array_filter(
                array_map('intval', $timestamps),
                static fn(int $timestamp): bool => $timestamp > $globalCutoff
            ));
            if ($timestamps) $store[$storedKey] = $timestamps;
            else unset($store[$storedKey]);
        }

        $cutoff = $now - $windowSeconds;
        $attempts = array_values(array_filter(
            array_map('intval', $store[$key] ?? []),
            static fn(int $timestamp): bool => $timestamp > $cutoff
        ));
        $allowed = count($attempts) < $limit;
        $retryAfter = 0;

        if ($allowed) {
            $attempts[] = $now;
            $store[$key] = $attempts;
        } elseif ($attempts) {
            $retryAfter = max(1, $windowSeconds - ($now - min($attempts)));
        }

        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($store, JSON_UNESCAPED_SLASHES));
        fflush($handle);

        return [
            'allowed' => $allowed,
            'remaining' => max(0, $limit - count($attempts)),
            'retry_after' => $retryAfter,
        ];
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function hivenest_rate_limit_secret(): string
{
    $envPath = defined('HIVENEST_ENV_PATH')
        ? HIVENEST_ENV_PATH
        : __DIR__ . '/../Backend/.env';
    $wanted = ['RATE_LIMIT_SECRET', 'JWT_SECRET_KEY'];
    if (is_readable($envPath)) {
        $values = [];
        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if (!in_array($key, $wanted, true)) continue;
            $values[$key] = trim(trim($value), "\"'");
        }
        foreach ($wanted as $key) {
            if (!empty($values[$key])) return (string)$values[$key];
        }
    }
    return hash('sha256', __FILE__ . '|' . PHP_VERSION);
}

