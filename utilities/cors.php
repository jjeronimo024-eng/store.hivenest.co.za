<?php
declare(strict_types=1);

/**
 * Apply HiveNest's explicit cross-origin policy and stop disallowed requests.
 *
 * Same-origin/non-browser requests without an Origin header remain valid.
 * Cross-origin callers must be listed in CORS_ALLOWED_ORIGINS.
 */
function hivenest_apply_cors(
    array $methods,
    array $headers = ['Content-Type', 'Accept'],
    bool $credentials = false
): void {
    $defaultOrigins = [
        'https://hivenest.co.za',
        'https://cp.hivenest.co.za',
        'https://crm.hivenest.co.za',
        'https://hivenest.holohive.co.za',
    ];
    $envPath = defined('HIVENEST_ENV_PATH')
        ? HIVENEST_ENV_PATH
        : __DIR__ . '/../Backend/.env';
    $configuredOrigins = '';

    if (is_readable($envPath)) {
        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            if (trim($key) !== 'CORS_ALLOWED_ORIGINS') continue;
            $configuredOrigins = trim($value, " \t\n\r\0\x0B\"'");
            break;
        }
    }

    $allowedOrigins = $configuredOrigins !== ''
        ? array_values(array_filter(array_map('trim', explode(',', $configuredOrigins))))
        : $defaultOrigins;
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $allowedMethods = array_values(array_unique(array_map('strtoupper', $methods)));

    header('Access-Control-Allow-Methods: ' . implode(', ', array_unique([...$allowedMethods, 'OPTIONS'])));
    header('Access-Control-Allow-Headers: ' . implode(', ', $headers));

    if ($origin !== '') {
        if (!in_array($origin, $allowedOrigins, true)) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['error' => 'Origin is not allowed.']);
            exit;
        }
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        if ($credentials) header('Access-Control-Allow-Credentials: true');
    }

    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

