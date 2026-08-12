<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../utilities/mail_delivery.php';
require_once __DIR__ . '/../utilities/mail_suppression.php';

function hivenest_mail_events_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hivenest_mail_events_out(405, ['ok' => false, 'error' => 'POST required.']);
}
$raw = (string)file_get_contents('php://input');
if ($raw === '' || strlen($raw) > 1048576) {
    hivenest_mail_events_out(413, ['ok' => false, 'error' => 'Mail event payload must be between 1 byte and 1 MiB.']);
}
$timestamp = trim((string)($_SERVER['HTTP_X_HIVENEST_MAIL_TIMESTAMP'] ?? ''));
$requestId = trim((string)($_SERVER['HTTP_X_HIVENEST_MAIL_EVENT_ID'] ?? ''));
$signature = strtolower(trim((string)($_SERVER['HTTP_X_HIVENEST_MAIL_SIGNATURE'] ?? '')));
$secret = hivenest_mail_env('MAIL_EVENT_WEBHOOK_SECRET');
$maxAge = max(60, min(900, (int)hivenest_mail_env('MAIL_EVENT_MAX_AGE_SECONDS', '300')));
if ($secret === '' || strlen($secret) < 32) {
    hivenest_mail_events_out(503, ['ok' => false, 'error' => 'Mail event webhook is not configured.']);
}
if (!ctype_digit($timestamp) || abs(time() - (int)$timestamp) > $maxAge) {
    hivenest_mail_events_out(401, ['ok' => false, 'error' => 'Mail event timestamp is missing or expired.']);
}
if (!preg_match('/^[a-z0-9._:@-]{8,191}$/i', $requestId) || !preg_match('/^[a-f0-9]{64}$/', $signature)) {
    hivenest_mail_events_out(401, ['ok' => false, 'error' => 'Mail event signature headers are invalid.']);
}
$expected = hash_hmac('sha256', $timestamp . "\n" . $requestId . "\n" . $raw, $secret);
if (!hash_equals($expected, $signature)) {
    hivenest_mail_events_out(401, ['ok' => false, 'error' => 'Mail event signature verification failed.']);
}
$payload = json_decode($raw, true);
if (!is_array($payload)) hivenest_mail_events_out(400, ['ok' => false, 'error' => 'Invalid JSON mail event payload.']);
$events = isset($payload['events']) ? $payload['events'] : [$payload];
if (!is_array($events) || $events === [] || count($events) > 100) {
    hivenest_mail_events_out(422, ['ok' => false, 'error' => 'Provide between 1 and 100 mail events.']);
}
$db = hivenest_db();
if (!$db || !hivenest_mail_suppression_ready($db)) {
    hivenest_mail_events_out(503, ['ok' => false, 'error' => 'Mail suppression schema is unavailable.']);
}

$results = [];
$counts = ['processed' => 0, 'duplicates' => 0, 'suppressed' => 0];
try {
    $db->beginTransaction();
    $payloadHash = hash('sha256', $raw);
    foreach ($events as $event) {
        if (!is_array($event)) throw new InvalidArgumentException('Each mail event must be an object.');
        if (empty($event['event_id'])) $event['event_id'] = $requestId;
        $result = hivenest_mail_record_event($db, $event, $payloadHash);
        $counts[$result['duplicate'] ? 'duplicates' : 'processed']++;
        if ($result['suppressed']) $counts['suppressed']++;
        $results[] = [
            'event_id' => (string)$event['event_id'],
            'type' => $result['type'],
            'duplicate' => $result['duplicate'],
            'suppressed' => $result['suppressed'],
        ];
    }
    $db->commit();
    hivenest_mail_events_out(200, ['ok' => true] + $counts + ['results' => $results]);
} catch (InvalidArgumentException $e) {
    if ($db->inTransaction()) $db->rollBack();
    hivenest_mail_events_out(422, ['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('Mail event processing failed: ' . $e->getMessage());
    hivenest_mail_events_out(503, ['ok' => false, 'error' => 'Mail events could not be processed.']);
}
