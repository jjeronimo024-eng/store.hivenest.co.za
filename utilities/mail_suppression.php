<?php
declare(strict_types=1);

require_once __DIR__ . '/../access/dbconfig.php';

function hivenest_mail_suppression_ready(PDO $db): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $stmt = $db->query("
            SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA=DATABASE()
              AND TABLE_NAME IN ('mail_delivery_events','mail_suppressions')
        ");
        return $ready = (int)$stmt->fetchColumn() === 2;
    } catch (Throwable $e) {
        return $ready = false;
    }
}

function hivenest_mail_normalize_recipient(string $email): string
{
    return strtolower(trim($email));
}

function hivenest_mail_is_suppressed(PDO $db, string $email): bool
{
    if (!hivenest_mail_suppression_ready($db)) return false;
    $stmt = $db->prepare('SELECT 1 FROM mail_suppressions WHERE recipient_email=:email AND is_active=1 LIMIT 1');
    $stmt->execute(['email' => hivenest_mail_normalize_recipient($email)]);
    return (bool)$stmt->fetchColumn();
}

function hivenest_mail_canonical_event_type(string $type, bool $permanent = false): string
{
    $type = strtolower(trim(str_replace(['.', '-', ' '], '_', $type)));
    if (in_array($type, ['delivered', 'delivery', 'sent'], true)) return 'delivered';
    if (in_array($type, ['complaint', 'spam', 'spam_report', 'spamreport'], true)) return 'complaint';
    if (in_array($type, ['unsubscribe', 'unsubscribed'], true)) return 'unsubscribe';
    if ($permanent || in_array($type, ['hard_bounce', 'permanent_bounce', 'dropped', 'invalid_recipient'], true)) {
        return 'hard_bounce';
    }
    if (in_array($type, ['bounce', 'bounced', 'soft_bounce', 'deferred', 'temporary_failure'], true)) {
        return 'soft_bounce';
    }
    return 'other';
}

function hivenest_mail_event_suppresses(string $type): bool
{
    return in_array($type, ['hard_bounce', 'complaint', 'unsubscribe'], true);
}

function hivenest_mail_record_event(PDO $db, array $event, string $payloadHash): array
{
    $provider = strtolower(trim((string)($event['provider'] ?? 'provider-adapter')));
    $eventId = trim((string)($event['event_id'] ?? ''));
    $email = hivenest_mail_normalize_recipient((string)($event['recipient'] ?? $event['email'] ?? ''));
    $type = hivenest_mail_canonical_event_type(
        (string)($event['type'] ?? $event['event_type'] ?? ''),
        (bool)($event['permanent'] ?? false)
    );
    $reason = trim((string)($event['reason'] ?? $event['diagnostic'] ?? ''));
    $occurredAt = trim((string)($event['occurred_at'] ?? ''));
    if ($eventId === '' || strlen($eventId) > 191 || !preg_match('/^[a-z0-9._:@-]+$/i', $eventId)) {
        throw new InvalidArgumentException('Each mail event requires a safe event_id.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Each mail event requires a valid recipient email.');
    }
    if ($provider === '' || strlen($provider) > 100 || !preg_match('/^[a-z0-9._-]+$/', $provider)) {
        throw new InvalidArgumentException('Each mail event requires a safe provider name.');
    }
    $timestamp = null;
    if ($occurredAt !== '') {
        $parsed = strtotime($occurredAt);
        if ($parsed === false) throw new InvalidArgumentException('Invalid occurred_at value.');
        $timestamp = gmdate('Y-m-d H:i:s', $parsed);
    }
    $recipientHash = hash('sha256', $email);
    try {
        $insert = $db->prepare("
            INSERT INTO mail_delivery_events
              (provider,event_id,event_type,recipient_email,recipient_hash,reason,payload_hash,occurred_at)
            VALUES
              (:provider,:event_id,:event_type,:recipient_email,:recipient_hash,:reason,:payload_hash,:occurred_at)
        ");
        $insert->execute([
            'provider' => $provider,
            'event_id' => $eventId,
            'event_type' => $type,
            'recipient_email' => $email,
            'recipient_hash' => $recipientHash,
            'reason' => $reason !== '' ? substr($reason, 0, 1000) : null,
            'payload_hash' => $payloadHash,
            'occurred_at' => $timestamp,
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') return ['duplicate' => true, 'suppressed' => false, 'type' => $type];
        throw $e;
    }

    $suppressed = hivenest_mail_event_suppresses($type);
    if ($suppressed) {
        $upsert = $db->prepare("
            INSERT INTO mail_suppressions
              (recipient_email,recipient_hash,suppression_type,source,reason,source_event_id,is_active)
            VALUES
              (:recipient_email,:recipient_hash,:suppression_type,:source,:reason,:source_event_id,1)
            ON DUPLICATE KEY UPDATE
              recipient_hash=VALUES(recipient_hash),
              suppression_type=VALUES(suppression_type),
              source=VALUES(source),
              reason=VALUES(reason),
              source_event_id=VALUES(source_event_id),
              is_active=1,
              last_suppressed_at=NOW(),
              released_at=NULL,
              released_by_admin_id=NULL,
              release_reason=NULL
        ");
        $upsert->execute([
            'recipient_email' => $email,
            'recipient_hash' => $recipientHash,
            'suppression_type' => $type,
            'source' => $provider,
            'reason' => $reason !== '' ? substr($reason, 0, 1000) : null,
            'source_event_id' => $eventId,
        ]);
        $cancel = $db->prepare("
            UPDATE outbound_mail_queue
            SET status='suppressed',locked_at=NULL,
                last_error='Recipient suppressed after provider delivery event.'
            WHERE recipient_email=:recipient_email AND status IN ('pending','retry')
        ");
        $cancel->execute(['recipient_email' => $email]);
        if ($type === 'unsubscribe' || $type === 'hard_bounce') {
            try {
                $newsletter = $db->prepare("
                    UPDATE newsletter_subscribers
                    SET status=:new_status,
                        unsubscribed_at=CASE WHEN :status_check='unsubscribed' THEN NOW() ELSE unsubscribed_at END
                    WHERE email=:email
                ");
                $newsletter->execute([
                    'new_status' => $type === 'unsubscribe' ? 'unsubscribed' : 'bounced',
                    'status_check' => $type === 'unsubscribe' ? 'unsubscribed' : 'bounced',
                    'email' => $email,
                ]);
            } catch (Throwable $ignored) {
                // Newsletter storage is optional to transactional delivery.
            }
        }
    }
    return ['duplicate' => false, 'suppressed' => $suppressed, 'type' => $type];
}
