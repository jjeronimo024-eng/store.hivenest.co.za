<?php
declare(strict_types=1);

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/mail_suppression.php';

function hivenest_mail_env(string $key, string $default = ''): string
{
    $process = getenv($key);
    if ($process !== false && $process !== '') return (string)$process;
    $path = defined('HIVENEST_ENV_PATH') ? HIVENEST_ENV_PATH : __DIR__ . '/../Backend/.env';
    foreach (is_readable($path) ? (@file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$name, $value] = explode('=', $line, 2);
        if (trim($name) === $key) return trim(trim($value), "\"'");
    }
    return $default;
}

function hivenest_mail_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function hivenest_mail_table_ready(PDO $db): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='outbound_mail_queue'");
        $stmt->execute();
        return $ready = (int)$stmt->fetchColumn() === 1;
    } catch (Throwable $e) {
        return $ready = false;
    }
}

function hivenest_mail_template_columns_ready(PDO $db): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $stmt = $db->query("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE()
              AND TABLE_NAME='outbound_mail_queue'
              AND COLUMN_NAME IN ('template_key','template_version')
        ");
        return $ready = (int)$stmt->fetchColumn() === 2;
    } catch (Throwable $e) {
        return $ready = false;
    }
}

function hivenest_mail_valid(string $to, string $subject): bool
{
    return filter_var($to, FILTER_VALIDATE_EMAIL) !== false
        && $subject !== ''
        && !preg_match('/[\r\n]/', $to . $subject);
}

function hivenest_mail_send(
    string $to,
    string $subject,
    string $body,
    string $headers = '',
    ?string $dedupeKey = null,
    ?string $templateKey = null,
    ?int $templateVersion = null
): bool {
    $to = strtolower(trim($to));
    $subject = trim($subject);
    if (!hivenest_mail_valid($to, $subject)) return false;
    $db = hivenest_db();
    if ($db && hivenest_mail_is_suppressed($db, $to)) {
        error_log('HiveNest mail suppressed for recipient hash: ' . substr(hash('sha256', $to), 0, 12));
        return false;
    }
    if (!$db || !hivenest_mail_table_ready($db)) {
        return @mail($to, $subject, $body, $headers);
    }
    try {
        $params = [
            'uuid' => hivenest_mail_uuid(),
            'dedupe_key' => $dedupeKey !== null && trim($dedupeKey) !== '' ? substr(trim($dedupeKey), 0, 191) : null,
            'recipient' => $to,
            'subject' => substr($subject, 0, 255),
            'body' => $body,
            'headers' => $headers,
        ];
        if (hivenest_mail_template_columns_ready($db)) {
            $stmt = $db->prepare("
                INSERT INTO outbound_mail_queue
                    (uuid,dedupe_key,recipient_email,subject,body,headers,template_key,template_version,status,next_attempt_at)
                VALUES
                    (:uuid,:dedupe_key,:recipient,:subject,:body,:headers,:template_key,:template_version,'pending',NOW())
            ");
            $params['template_key'] = $templateKey !== null ? substr(strtolower(trim($templateKey)), 0, 100) : null;
            $params['template_version'] = $templateVersion;
        } else {
            $stmt = $db->prepare("
                INSERT INTO outbound_mail_queue
                    (uuid,dedupe_key,recipient_email,subject,body,headers,status,next_attempt_at)
                VALUES
                    (:uuid,:dedupe_key,:recipient,:subject,:body,:headers,'pending',NOW())
            ");
        }
        $stmt->execute($params);
        if (strtolower(hivenest_mail_env('MAIL_PROCESS_IMMEDIATELY', 'false')) === 'true') {
            hivenest_mail_process_queue(1);
        }
        return true;
    } catch (PDOException $e) {
        if ($e->getCode() === '23000' && $dedupeKey !== null) return true;
        error_log('HiveNest mail queue insert failed: ' . $e->getMessage());
        return false;
    }
}

function hivenest_mail_smtp_read($socket): string
{
    $response = '';
    while (($line = fgets($socket, 8192)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    return $response;
}

function hivenest_mail_smtp_command($socket, string $command, array $codes): void
{
    fwrite($socket, $command . "\r\n");
    $response = hivenest_mail_smtp_read($socket);
    if (!in_array((int)substr($response, 0, 3), $codes, true)) {
        throw new RuntimeException('SMTP command rejected with code ' . (int)substr($response, 0, 3) . '.');
    }
}

function hivenest_mail_header_address(string $headers, string $name, string $fallback): string
{
    if (preg_match('/^' . preg_quote($name, '/') . ':\s*(?:[^<\r\n]*<)?([^>\s\r\n]+@[^>\s\r\n]+)>?/mi', $headers, $match)
        && filter_var($match[1], FILTER_VALIDATE_EMAIL)
    ) return strtolower($match[1]);
    return $fallback;
}

function hivenest_mail_deliver_smtp(array $mail): bool
{
    $host = hivenest_mail_env('SMTP_HOST');
    $port = max(1, (int)hivenest_mail_env('SMTP_PORT', '587'));
    $security = strtolower(hivenest_mail_env('SMTP_SECURITY', 'tls'));
    $username = hivenest_mail_env('SMTP_USERNAME', hivenest_mail_env('SMTP_USER'));
    $password = hivenest_mail_env('SMTP_PASSWORD');
    $defaultFrom = hivenest_mail_env(
        'MAIL_FROM_ADDRESS',
        hivenest_mail_env('SMTP_FROM_EMAIL', 'no-reply@hivenest.co.za')
    );
    if ($host === '' || $username === '' || $password === '') {
        throw new RuntimeException('Authenticated SMTP is selected but SMTP_HOST, SMTP_USERNAME or SMTP_PASSWORD is missing.');
    }
    $target = ($security === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $socket = @stream_socket_client($target, $errno, $error, 20, STREAM_CLIENT_CONNECT);
    if (!$socket) throw new RuntimeException('SMTP connection failed (' . $errno . ').');
    stream_set_timeout($socket, 20);
    try {
        $greeting = hivenest_mail_smtp_read($socket);
        if ((int)substr($greeting, 0, 3) !== 220) throw new RuntimeException('SMTP server did not accept the connection.');
        $hostname = preg_replace('/[^a-z0-9.-]/i', '', (string)($_SERVER['SERVER_NAME'] ?? 'hivenest.co.za')) ?: 'hivenest.co.za';
        hivenest_mail_smtp_command($socket, 'EHLO ' . $hostname, [250]);
        if ($security === 'tls') {
            hivenest_mail_smtp_command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP TLS negotiation failed.');
            }
            hivenest_mail_smtp_command($socket, 'EHLO ' . $hostname, [250]);
        }
        hivenest_mail_smtp_command($socket, 'AUTH LOGIN', [334]);
        hivenest_mail_smtp_command($socket, base64_encode($username), [334]);
        hivenest_mail_smtp_command($socket, base64_encode($password), [235]);
        $from = hivenest_mail_header_address((string)$mail['headers'], 'From', $defaultFrom);
        hivenest_mail_smtp_command($socket, 'MAIL FROM:<' . $from . '>', [250]);
        hivenest_mail_smtp_command($socket, 'RCPT TO:<' . $mail['recipient_email'] . '>', [250, 251]);
        hivenest_mail_smtp_command($socket, 'DATA', [354]);
        $headers = trim((string)$mail['headers']);
        $message = 'To: ' . $mail['recipient_email'] . "\r\n"
            . 'Subject: ' . $mail['subject'] . "\r\n"
            . 'Date: ' . gmdate('D, d M Y H:i:s O') . "\r\n"
            . 'Message-ID: <' . $mail['uuid'] . '@hivenest.co.za>' . "\r\n"
            . ($headers !== '' ? $headers . "\r\n" : '')
            . "\r\n" . (string)$mail['body'];
        $message = preg_replace('/(?m)^\./', '..', str_replace(["\r\n", "\r", "\n"], "\r\n", $message)) ?? $message;
        fwrite($socket, $message . "\r\n.\r\n");
        $response = hivenest_mail_smtp_read($socket);
        if ((int)substr($response, 0, 3) !== 250) throw new RuntimeException('SMTP server rejected the message.');
        hivenest_mail_smtp_command($socket, 'QUIT', [221]);
        return true;
    } finally {
        fclose($socket);
    }
}

function hivenest_mail_deliver(array $mail): bool
{
    if (strtolower(hivenest_mail_env('MAIL_TRANSPORT', 'php_mail')) === 'smtp') {
        return hivenest_mail_deliver_smtp($mail);
    }
    return @mail(
        (string)$mail['recipient_email'],
        (string)$mail['subject'],
        (string)$mail['body'],
        (string)$mail['headers']
    );
}

function hivenest_mail_process_queue(int $limit = 25): array
{
    $db = hivenest_db();
    if (!$db || !hivenest_mail_table_ready($db)) return ['processed' => 0, 'sent' => 0, 'failed' => 0];
    $limit = max(1, min(100, $limit));
    $result = ['processed' => 0, 'sent' => 0, 'failed' => 0];
    $db->exec("
        UPDATE outbound_mail_queue
        SET status='retry',locked_at=NULL,next_attempt_at=NOW(),
            last_error='Recovered after a mail worker stopped before recording delivery.'
        WHERE status='processing' AND locked_at < DATE_SUB(NOW(),INTERVAL 15 MINUTE)
    ");
    for ($i = 0; $i < $limit; $i++) {
        $db->beginTransaction();
        try {
            $row = $db->query("
                SELECT * FROM outbound_mail_queue
                WHERE status IN ('pending','retry') AND next_attempt_at<=NOW()
                ORDER BY id ASC LIMIT 1 FOR UPDATE
            ")->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $db->commit();
                break;
            }
            $claim = $db->prepare("
                UPDATE outbound_mail_queue
                SET status='processing',locked_at=NOW(),attempts=attempts+1,last_error=NULL
                WHERE id=:id AND status IN ('pending','retry')
            ");
            $claim->execute(['id' => (int)$row['id']]);
            $db->commit();
            if ($claim->rowCount() !== 1) continue;
            $row['attempts'] = (int)$row['attempts'] + 1;
            $result['processed']++;
            try {
                if (!hivenest_mail_deliver($row)) throw new RuntimeException('Mail transport did not accept the message.');
                $db->prepare("UPDATE outbound_mail_queue SET status='sent',sent_at=NOW(),locked_at=NULL,last_error=NULL WHERE id=:id")
                    ->execute(['id' => (int)$row['id']]);
                $result['sent']++;
            } catch (Throwable $deliveryError) {
                $terminal = (int)$row['attempts'] >= (int)$row['max_attempts'];
                $delay = min(3600, 60 * (2 ** min(6, (int)$row['attempts'])));
                $stmt = $db->prepare("
                    UPDATE outbound_mail_queue
                    SET status=:status,locked_at=NULL,last_error=:error,
                        next_attempt_at=DATE_ADD(NOW(),INTERVAL {$delay} SECOND)
                    WHERE id=:id
                ");
                $stmt->execute([
                    'status' => $terminal ? 'failed' : 'retry',
                    'error' => substr($deliveryError->getMessage(), 0, 1000),
                    'id' => (int)$row['id'],
                ]);
                $result['failed']++;
            }
        } catch (Throwable $workerError) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('HiveNest mail worker failed: ' . $workerError->getMessage());
            break;
        }
    }
    return $result;
}
