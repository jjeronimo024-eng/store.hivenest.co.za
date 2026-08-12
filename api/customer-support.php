<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/support_notifications.php';
require_once __DIR__ . '/../utilities/upload_security.php';

function hivenest_support_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_support_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function hivenest_support_clean(string $value, int $max): string
{
    $value = trim(str_replace("\0", '', $value));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function hivenest_support_files(int $customerId): array
{
    if (empty($_FILES['attachments']) || !is_array($_FILES['attachments']['name'] ?? null)) return [];
    $root = realpath(__DIR__ . '/../uploads/support');
    if ($root === false) {
        @mkdir(__DIR__ . '/../uploads/support', 0755, true);
        $root = realpath(__DIR__ . '/../uploads/support');
    }
    if ($root === false) return [];
    $dir = $root . DIRECTORY_SEPARATOR . 'customer_' . $customerId;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $allowed = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','txt','zip','log'];
    $saved = [];
    $count = min(count($_FILES['attachments']['name']), 5);
    for ($i = 0; $i < $count; $i++) {
        $error = (int)($_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        $original = basename((string)$_FILES['attachments']['name'][$i]);
        if ($error !== UPLOAD_ERR_OK) {
            $saved[] = ['original_name' => $original, 'error' => 'Upload failed.'];
            continue;
        }
        $saved[] = hivenest_secure_upload([
            'name' => $_FILES['attachments']['name'][$i] ?? '',
            'type' => $_FILES['attachments']['type'][$i] ?? '',
            'tmp_name' => $_FILES['attachments']['tmp_name'][$i] ?? '',
            'error' => $error,
            'size' => $_FILES['attachments']['size'][$i] ?? 0,
        ], $dir, 'uploads/support/customer_' . $customerId, $allowed, 10 * 1024 * 1024);
    }
    return $saved;
}

function hivenest_support_column_exists(PDO $db, string $table, string $column): bool
{
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
        ");
        $stmt->execute(['table_name' => $table, 'column_name' => $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        error_log('Support column check failed: ' . $e->getMessage());
        return false;
    }
}

$customerId = (int)($_SESSION['customer_id'] ?? 0);
if ($customerId <= 0) {
    hivenest_support_out(401, ['error' => 'Customer login required.']);
}

$db = hivenest_db();
if (!$db) {
    hivenest_support_out(503, ['error' => 'Customer database is unavailable.']);
}

$hasTicketOrderId = hivenest_support_column_exists($db, 'support_tickets', 'order_id');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $orderSelect = $hasTicketOrderId ? ', t.order_id, o.order_number, o.total_amount AS order_total, o.currency AS order_currency' : '';
    $orderJoin = $hasTicketOrderId ? 'LEFT JOIN orders o ON o.id = t.order_id' : '';
    $stmt = $db->prepare("
        SELECT t.id, t.ticket_number, t.subject, t.priority, t.category, t.status, t.service_id, t.created_at, t.updated_at,
               s.service_name, s.domain_name
               {$orderSelect}
        FROM support_tickets t
        LEFT JOIN services s ON s.id = t.service_id
        {$orderJoin}
        WHERE t.customer_id = :customer_id
        ORDER BY t.id DESC
        LIMIT 50
    ");
    $stmt->execute(['customer_id' => $customerId]);
    $tickets = $stmt->fetchAll() ?: [];
    if ($tickets) {
        $ids = array_map(static fn($ticket) => (int)$ticket['id'], $tickets);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $replies = $db->prepare("
            SELECT id, ticket_id, reply_type, author_id, message, attachments, is_internal, created_at
            FROM support_ticket_replies
            WHERE ticket_id IN ({$placeholders})
              AND is_internal = 0
            ORDER BY ticket_id ASC, id ASC
        ");
        $replies->execute($ids);
        $repliesByTicket = [];
        foreach ($replies->fetchAll() ?: [] as $reply) {
            $reply['attachments'] = json_decode((string)($reply['attachments'] ?? ''), true) ?: [];
            $repliesByTicket[(int)$reply['ticket_id']][] = $reply;
        }
        foreach ($tickets as &$ticket) {
            $ticket['replies'] = $repliesByTicket[(int)$ticket['id']] ?? [];
        }
        unset($ticket);
    }
    hivenest_support_out(200, ['tickets' => $tickets]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hivenest_support_out(405, ['error' => 'Method not allowed.']);
}

$message = hivenest_support_clean((string)($_POST['message'] ?? ''), 10000);
$ticketId = (int)($_POST['ticket_id'] ?? 0);

if ($ticketId > 0) {
    if ($message === '') hivenest_support_out(422, ['error' => 'Reply message is required.']);
    $check = $db->prepare('SELECT id, status FROM support_tickets WHERE id = :ticket_id AND customer_id = :customer_id LIMIT 1');
    $check->execute(['ticket_id' => $ticketId, 'customer_id' => $customerId]);
    $existingTicket = $check->fetch();
    if (!$existingTicket) hivenest_support_out(404, ['error' => 'Ticket was not found.']);
    if (in_array((string)$existingTicket['status'], ['resolved','closed'], true)) {
        hivenest_support_out(409, ['error' => 'This ticket is closed. Please open a new ticket if you still need help.']);
    }

    $attachments = hivenest_support_files($customerId);
    try {
        $reply = $db->prepare("
            INSERT INTO support_ticket_replies
                (uuid, ticket_id, reply_type, author_id, message, attachments, is_internal)
            VALUES
                (:uuid, :ticket_id, 'customer', :author_id, :message, :attachments, 0)
        ");
        $reply->execute([
            'uuid' => hivenest_support_uuid(),
            'ticket_id' => $ticketId,
            'author_id' => $customerId,
            'message' => $message,
            'attachments' => json_encode($attachments, JSON_UNESCAPED_SLASHES),
        ]);
        $update = $db->prepare("UPDATE support_tickets SET status = 'open' WHERE id = :ticket_id");
        $update->execute(['ticket_id' => $ticketId]);
    } catch (Throwable $e) {
        error_log('Customer support reply failed: ' . $e->getMessage());
        hivenest_support_out(500, ['error' => 'Reply could not be sent. Please try again.']);
    }

    hivenest_support_out(200, [
        'success' => true,
        'message' => 'Reply sent.',
        'ticket_id' => $ticketId,
    ]);
}

$subject = hivenest_support_clean((string)($_POST['subject'] ?? ''), 255);
$priority = (string)($_POST['priority'] ?? 'medium');
$category = (string)($_POST['category'] ?? 'general');
$serviceId = (int)($_POST['service_id'] ?? 0);
$orderRef = hivenest_support_clean((string)($_POST['order_id'] ?? $_POST['order'] ?? ''), 100);

if ($subject === '' || $message === '') {
    hivenest_support_out(422, ['error' => 'Subject and message are required.']);
}
if (!in_array($priority, ['low','medium','high','urgent'], true)) $priority = 'medium';
if (!in_array($category, ['technical','billing','general','sales','abuse'], true)) $category = 'general';

if ($serviceId > 0) {
    $check = $db->prepare('SELECT id FROM services WHERE id = :service_id AND customer_id = :customer_id LIMIT 1');
    $check->execute(['service_id' => $serviceId, 'customer_id' => $customerId]);
    if (!$check->fetchColumn()) hivenest_support_out(404, ['error' => 'Selected service was not found.']);
} else {
    $serviceId = null;
}

$orderId = null;
if ($hasTicketOrderId && $orderRef !== '') {
    if (ctype_digit($orderRef)) {
        $check = $db->prepare('SELECT id FROM orders WHERE id = :id AND customer_id = :customer_id LIMIT 1');
        $check->execute(['id' => (int)$orderRef, 'customer_id' => $customerId]);
    } else {
        $check = $db->prepare('SELECT id FROM orders WHERE order_number = :order_number AND customer_id = :customer_id LIMIT 1');
        $check->execute(['order_number' => $orderRef, 'customer_id' => $customerId]);
    }
    $foundOrder = $check->fetchColumn();
    if (!$foundOrder) hivenest_support_out(404, ['error' => 'Selected order was not found.']);
    $orderId = (int)$foundOrder;
}

$attachments = hivenest_support_files($customerId);
$ticketNumber = 'TIC-' . gmdate('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

$db->beginTransaction();
try {
    $orderColumn = $hasTicketOrderId ? ', order_id' : '';
    $orderValue = $hasTicketOrderId ? ', :order_id' : '';
    $ticket = $db->prepare("
        INSERT INTO support_tickets
            (uuid, customer_id, ticket_number, subject, priority, category, status, service_id{$orderColumn})
        VALUES
            (:uuid, :customer_id, :ticket_number, :subject, :priority, :category, 'open', :service_id{$orderValue})
    ");
    $ticketParams = [
        'uuid' => hivenest_support_uuid(),
        'customer_id' => $customerId,
        'ticket_number' => $ticketNumber,
        'subject' => $subject,
        'priority' => $priority,
        'category' => $category,
        'service_id' => $serviceId,
    ];
    if ($hasTicketOrderId) $ticketParams['order_id'] = $orderId;
    $ticket->execute($ticketParams);
    $ticketId = (int)$db->lastInsertId();

    $reply = $db->prepare("
        INSERT INTO support_ticket_replies
            (uuid, ticket_id, reply_type, author_id, message, attachments, is_internal)
        VALUES
            (:uuid, :ticket_id, 'customer', :author_id, :message, :attachments, 0)
    ");
    $reply->execute([
        'uuid' => hivenest_support_uuid(),
        'ticket_id' => $ticketId,
        'author_id' => $customerId,
        'message' => $message,
        'attachments' => json_encode($attachments, JSON_UNESCAPED_SLASHES),
    ]);
    $db->commit();

    try {
        $customerStmt = $db->prepare('SELECT email, first_name, last_name, company_name FROM customers WHERE id = :customer_id LIMIT 1');
        $customerStmt->execute(['customer_id' => $customerId]);
        $customer = $customerStmt->fetch() ?: [];
        hivenest_support_notify_team_new_ticket([
            'ticket_number' => $ticketNumber,
            'subject' => $subject,
            'priority' => $priority,
            'category' => $category,
        ], $customer, $message);
    } catch (Throwable $mailError) {
        error_log('Support team ticket notification failed: ' . $mailError->getMessage());
    }
} catch (Throwable $e) {
    $db->rollBack();
    error_log('Customer support ticket failed: ' . $e->getMessage());
    hivenest_support_out(500, ['error' => 'Ticket could not be created. Please try again.']);
}

hivenest_support_out(201, [
    'success' => true,
    'message' => 'Support ticket created.',
    'ticket_number' => $ticketNumber,
    'ticket_id' => $ticketId,
]);
