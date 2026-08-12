<?php
declare(strict_types=1);

function hivenest_2fa_base32_encode(string $bytes): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bytes) as $byte) {
        $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    }
    $encoded = '';
    foreach (str_split($bits, 5) as $chunk) {
        $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
    }
    return $encoded;
}

function hivenest_2fa_base32_decode(string $value): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $value = strtoupper((string)preg_replace('/[^A-Z2-7]/', '', $value));
    $bits = '';
    foreach (str_split($value) as $character) {
        $position = strpos($alphabet, $character);
        if ($position === false) {
            throw new InvalidArgumentException('Invalid authenticator secret.');
        }
        $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
    }
    $decoded = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) {
            $decoded .= chr(bindec($chunk));
        }
    }
    return $decoded;
}

function hivenest_2fa_secret(): string
{
    return hivenest_2fa_base32_encode(random_bytes(20));
}

function hivenest_2fa_code(string $secret, int $counter): string
{
    $key = hivenest_2fa_base32_decode($secret);
    $binaryCounter = pack('N2', ($counter >> 32) & 0xffffffff, $counter & 0xffffffff);
    $hash = hash_hmac('sha1', $binaryCounter, $key, true);
    $offset = ord($hash[19]) & 0x0f;
    $number = ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff);
    return str_pad((string)($number % 1000000), 6, '0', STR_PAD_LEFT);
}

function hivenest_2fa_verify_totp(string $secret, string $code, int $window = 1): bool
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($code) !== 6) return false;
    $counter = intdiv(time(), 30);
    for ($offset = -$window; $offset <= $window; $offset++) {
        if (hash_equals(hivenest_2fa_code($secret, $counter + $offset), $code)) return true;
    }
    return false;
}

function hivenest_2fa_encryption_key(): string
{
    $configured = trim((string)(getenv('TWO_FACTOR_ENCRYPTION_KEY') ?: ''));
    $decoded = $configured !== '' ? base64_decode($configured, true) : false;
    if (!is_string($decoded) || strlen($decoded) !== 32) {
        throw new RuntimeException('TWO_FACTOR_ENCRYPTION_KEY must be a base64-encoded 32-byte key.');
    }
    return $decoded;
}

function hivenest_2fa_encrypt(string $plainText): string
{
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('OpenSSL is required for two-factor authentication.');
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipherText = openssl_encrypt(
        $plainText,
        'aes-256-gcm',
        hivenest_2fa_encryption_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        'hivenest-2fa-v1'
    );
    if (!is_string($cipherText)) throw new RuntimeException('Could not protect authenticator secret.');
    return 'v1.' . base64_encode($iv . $tag . $cipherText);
}

function hivenest_2fa_decrypt(string $payload): string
{
    if (!str_starts_with($payload, 'v1.')) throw new RuntimeException('Unsupported authenticator secret format.');
    $raw = base64_decode(substr($payload, 3), true);
    if (!is_string($raw) || strlen($raw) < 29) throw new RuntimeException('Invalid authenticator secret.');
    $plainText = openssl_decrypt(
        substr($raw, 28),
        'aes-256-gcm',
        hivenest_2fa_encryption_key(),
        OPENSSL_RAW_DATA,
        substr($raw, 0, 12),
        substr($raw, 12, 16),
        'hivenest-2fa-v1'
    );
    if (!is_string($plainText) || $plainText === '') throw new RuntimeException('Could not unlock authenticator secret.');
    return $plainText;
}

function hivenest_2fa_recovery_codes(int $count = 10): array
{
    $codes = [];
    for ($index = 0; $index < $count; $index++) {
        $raw = strtoupper(bin2hex(random_bytes(5)));
        $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5);
    }
    return $codes;
}

function hivenest_2fa_normalise_recovery_code(string $code): string
{
    return strtoupper((string)preg_replace('/[^A-Z0-9]/', '', $code));
}

function hivenest_2fa_store_recovery_codes(PDO $db, string $accountType, int $accountId, array $codes): void
{
    $delete = $db->prepare('DELETE FROM two_factor_recovery_codes WHERE account_type=:type AND account_id=:id');
    $delete->execute(['type' => $accountType, 'id' => $accountId]);
    $insert = $db->prepare('INSERT INTO two_factor_recovery_codes (account_type,account_id,code_hash) VALUES (:type,:id,:hash)');
    foreach ($codes as $code) {
        $insert->execute([
            'type' => $accountType,
            'id' => $accountId,
            'hash' => hash('sha256', hivenest_2fa_normalise_recovery_code((string)$code)),
        ]);
    }
}

function hivenest_2fa_use_recovery_code(PDO $db, string $accountType, int $accountId, string $code): bool
{
    $hash = hash('sha256', hivenest_2fa_normalise_recovery_code($code));
    $update = $db->prepare(
        'UPDATE two_factor_recovery_codes SET used_at=NOW()
         WHERE account_type=:type AND account_id=:id AND code_hash=:hash AND used_at IS NULL'
    );
    $update->execute(['type' => $accountType, 'id' => $accountId, 'hash' => $hash]);
    return $update->rowCount() === 1;
}

function hivenest_2fa_create_challenge(PDO $db, string $accountType, int $accountId): string
{
    $token = bin2hex(random_bytes(32));
    $cleanup = $db->prepare(
        'UPDATE two_factor_challenges SET consumed_at=NOW()
         WHERE account_type=:type AND account_id=:id AND consumed_at IS NULL'
    );
    $cleanup->execute(['type' => $accountType, 'id' => $accountId]);
    $insert = $db->prepare(
        'INSERT INTO two_factor_challenges (account_type,account_id,token_hash,expires_at)
         VALUES (:type,:id,:hash,DATE_ADD(NOW(), INTERVAL 5 MINUTE))'
    );
    $insert->execute(['type' => $accountType, 'id' => $accountId, 'hash' => hash('sha256', $token)]);
    return $token;
}

function hivenest_2fa_find_challenge(PDO $db, string $accountType, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $select = $db->prepare(
        'SELECT id,account_id,attempts FROM two_factor_challenges
         WHERE account_type=:type AND token_hash=:hash AND consumed_at IS NULL
           AND expires_at > NOW() AND attempts < 5 LIMIT 1'
    );
    $select->execute(['type' => $accountType, 'hash' => hash('sha256', $token)]);
    $challenge = $select->fetch(PDO::FETCH_ASSOC);
    return is_array($challenge) ? $challenge : null;
}

