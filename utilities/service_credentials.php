<?php
declare(strict_types=1);

function hivenest_service_credentials_env(string $name): string
{
    $runtime = getenv($name);
    if (is_string($runtime) && trim($runtime) !== '') return trim($runtime);
    $path = defined('HIVENEST_ENV_PATH') ? HIVENEST_ENV_PATH : __DIR__ . '/../Backend/.env';
    if (!is_readable($path)) return '';
    foreach (@file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        if (trim($key) !== $name) continue;
        return trim(trim($value), "\"'");
    }
    return '';
}

function hivenest_service_credentials_key(): string
{
    $decoded = base64_decode(hivenest_service_credentials_env('SERVICE_CREDENTIAL_ENCRYPTION_KEY'), true);
    if (!is_string($decoded) || strlen($decoded) !== 32) {
        throw new RuntimeException('SERVICE_CREDENTIAL_ENCRYPTION_KEY must be a base64-encoded 32-byte key.');
    }
    return $decoded;
}

function hivenest_service_credentials_encrypt(string $plainText): string
{
    if ($plainText === '') throw new InvalidArgumentException('Credential secret cannot be empty.');
    if (!function_exists('openssl_encrypt')) throw new RuntimeException('OpenSSL is required for credential encryption.');
    $iv = random_bytes(12);
    $tag = '';
    $cipherText = openssl_encrypt(
        $plainText,
        'aes-256-gcm',
        hivenest_service_credentials_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        'hivenest-service-credential-v1'
    );
    if (!is_string($cipherText)) throw new RuntimeException('Credential encryption failed.');
    return 'v1.' . base64_encode($iv . $tag . $cipherText);
}

function hivenest_service_credentials_decrypt(string $payload): string
{
    if (!function_exists('openssl_decrypt')) throw new RuntimeException('OpenSSL is required for credential decryption.');
    if (!str_starts_with($payload, 'v1.')) throw new RuntimeException('Unsupported credential format.');
    $raw = base64_decode(substr($payload, 3), true);
    if (!is_string($raw) || strlen($raw) < 29) throw new RuntimeException('Invalid encrypted credential.');
    $plainText = openssl_decrypt(
        substr($raw, 28),
        'aes-256-gcm',
        hivenest_service_credentials_key(),
        OPENSSL_RAW_DATA,
        substr($raw, 0, 12),
        substr($raw, 12, 16),
        'hivenest-service-credential-v1'
    );
    if (!is_string($plainText)) throw new RuntimeException('Credential decryption failed.');
    return $plainText;
}

function hivenest_service_credentials_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function hivenest_service_credentials_request_hash(string $value): ?string
{
    $value = trim($value);
    if ($value === '') return null;
    $key = hivenest_service_credentials_env('RATE_LIMIT_SECRET');
    if ($key === '') $key = hivenest_service_credentials_env('JWT_SECRET_KEY');
    if ($key === '') throw new RuntimeException('A rate-limit or JWT secret is required for audit hashing.');
    return hash_hmac('sha256', $value, $key);
}

function hivenest_service_credentials_audit(
    PDO $db,
    int $credentialId,
    int $serviceId,
    int $customerId,
    string $actorType,
    ?int $actorId,
    string $eventType
): void {
    $stmt = $db->prepare(
        'INSERT INTO service_credential_access_audit
            (credential_id,service_id,customer_id,actor_type,actor_id,event_type,request_ip_hash,user_agent_hash)
         VALUES (:credential_id,:service_id,:customer_id,:actor_type,:actor_id,:event_type,:ip_hash,:agent_hash)'
    );
    $stmt->execute([
        'credential_id' => $credentialId,
        'service_id' => $serviceId,
        'customer_id' => $customerId,
        'actor_type' => $actorType,
        'actor_id' => $actorId,
        'event_type' => $eventType,
        'ip_hash' => hivenest_service_credentials_request_hash((string)($_SERVER['REMOTE_ADDR'] ?? '')),
        'agent_hash' => hivenest_service_credentials_request_hash((string)($_SERVER['HTTP_USER_AGENT'] ?? '')),
    ]);
}

function hivenest_service_credentials_redact_config(array $config): array
{
    foreach ($config as $key => $value) {
        $normalised = strtolower((string)$key);
        if (preg_match('/password|passwd|secret|token|api[_-]?key|private[_-]?key|auth[_-]?code/', $normalised)) {
            $config[$key] = '[vault-protected]';
            continue;
        }
        if (is_array($value)) $config[$key] = hivenest_service_credentials_redact_config($value);
    }
    return $config;
}

function hivenest_service_credentials_metadata(array $row): array
{
    $metadata = json_decode((string)($row['metadata_json'] ?? ''), true);
    return [
        'id' => (int)$row['id'],
        'uuid' => (string)$row['uuid'],
        'service_id' => (int)$row['service_id'],
        'type' => (string)$row['credential_type'],
        'label' => (string)$row['label'],
        'username' => $row['username'] !== null ? (string)$row['username'] : null,
        'login_url' => $row['login_url'] !== null ? (string)$row['login_url'] : null,
        'metadata' => is_array($metadata) ? $metadata : [],
        'status' => (string)$row['status'],
        'expires_at' => $row['expires_at'] ?? null,
        'rotated_at' => $row['rotated_at'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}
