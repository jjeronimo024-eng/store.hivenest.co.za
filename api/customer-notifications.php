<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../utilities/customer_session.php';
hivenest_customer_session_configure();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/customer_notifications.php';

function hivenest_customer_notifications_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_customer_notifications_add_paid_invoices(PDO $db, int $customerId): void
{
    $tableStmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders'
    ");
    $tableStmt->execute();
    if ((int)$tableStmt->fetchColumn() === 0) return;
    $orders = $db->prepare("
        SELECT id, order_number, total_amount, currency
        FROM orders
        WHERE customer_id = :customer_id
          AND payment_status IN ('paid', 'completed')
          AND NOT EXISTS (
              SELECT 1
              FROM customer_notifications cn
              WHERE cn.customer_id = orders.customer_id
                AND cn.entity_type = 'paid_order'
                AND cn.entity_id = orders.id
          )
        ORDER BY id DESC
        LIMIT 20
    ");
    $orders->execute(['customer_id' => $customerId]);
    foreach (array_reverse($orders->fetchAll() ?: []) as $order) {
        $currency = strtoupper((string)($order['currency'] ?? 'USD'));
        hivenest_notify_customer(
            $db,
            $customerId,
            'success',
            'Paid invoice available',
            (string)$order['order_number'] . ' · ' . $currency . ' ' . number_format((float)$order['total_amount'], 2),
            '/billing/index.html',
            'paid_order',
            (int)$order['id']
        );
    }
}

function hivenest_customer_notifications_add_due_services(PDO $db, int $customerId): void
{
    $tableStmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'services'
    ");
    $tableStmt->execute();
    if ((int)$tableStmt->fetchColumn() === 0) return;
    $services = $db->prepare("
        SELECT id, service_name, domain_name, next_due_date
        FROM services
        WHERE customer_id = :customer_id
          AND next_due_date IS NOT NULL
          AND next_due_date >= CURRENT_DATE()
          AND next_due_date < DATE_ADD(CURRENT_DATE(), INTERVAL 31 DAY)
          AND service_status IN ('active', 'pending')
        ORDER BY next_due_date ASC
        LIMIT 25
    ");
    $services->execute(['customer_id' => $customerId]);
    foreach ($services->fetchAll() ?: [] as $service) {
        $dueDate = substr((string)$service['next_due_date'], 0, 10);
        if ($dueDate === '') continue;
        $label = trim((string)$service['service_name']) ?: ('Service #' . (int)$service['id']);
        $domain = trim((string)($service['domain_name'] ?? ''));
        hivenest_notify_customer_once(
            $db,
            $customerId,
            'warning',
            'Service renewal approaching',
            $label . ($domain !== '' ? ' · ' . $domain : '') . ' is due on ' . $dueDate . '.',
            '/billing/index.html',
            'service_due_' . str_replace('-', '', $dueDate),
            (int)$service['id']
        );
    }
}

$customerSession = hivenest_customer_session_status(true);
if (!$customerSession['authenticated']) {
    hivenest_customer_notifications_out(401, ['ok' => false, 'error' => 'Customer login required.']);
}
$customerId = (int)$customerSession['customer_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hivenest_customer_csrf_require_json();
}
$db = hivenest_db();
if (!$db) hivenest_customer_notifications_out(503, ['ok' => false, 'error' => 'Customer database unavailable.']);

try {
    hivenest_customer_notifications_ensure($db);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        hivenest_customer_notifications_add_paid_invoices($db, $customerId);
        hivenest_customer_notifications_add_due_services($db, $customerId);
        $limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));
        $stmt = $db->prepare("
            SELECT id, uuid, notification_type, title, message, link_url,
                   entity_type, entity_id, is_read, read_at, created_at
            FROM customer_notifications
            WHERE customer_id = :customer_id
            ORDER BY created_at DESC, id DESC
            LIMIT {$limit}
        ");
        $stmt->execute(['customer_id' => $customerId]);
        $count = $db->prepare('SELECT COUNT(*) FROM customer_notifications WHERE customer_id = :customer_id AND is_read = 0');
        $count->execute(['customer_id' => $customerId]);
        hivenest_customer_notifications_out(200, [
            'ok' => true,
            'unread_count' => (int)$count->fetchColumn(),
            'notifications' => $stmt->fetchAll(),
        ]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        hivenest_customer_notifications_out(405, ['ok' => false, 'error' => 'GET or POST required.']);
    }
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $action = trim((string)($input['action'] ?? ''));
    if ($action === 'mark_all_read') {
        $stmt = $db->prepare('UPDATE customer_notifications SET is_read = 1, read_at = COALESCE(read_at, CURRENT_TIMESTAMP) WHERE customer_id = :customer_id AND is_read = 0');
        $stmt->execute(['customer_id' => $customerId]);
        hivenest_customer_notifications_out(200, ['ok' => true, 'updated' => $stmt->rowCount()]);
    }
    if ($action === 'mark_read') {
        $notificationId = (int)($input['notification_id'] ?? 0);
        if ($notificationId <= 0) hivenest_customer_notifications_out(422, ['ok' => false, 'error' => 'Valid notification_id required.']);
        $stmt = $db->prepare('UPDATE customer_notifications SET is_read = 1, read_at = COALESCE(read_at, CURRENT_TIMESTAMP) WHERE id = :id AND customer_id = :customer_id');
        $stmt->execute(['id' => $notificationId, 'customer_id' => $customerId]);
        hivenest_customer_notifications_out(200, ['ok' => true, 'updated' => $stmt->rowCount()]);
    }
    hivenest_customer_notifications_out(422, ['ok' => false, 'error' => 'Unsupported action.']);
} catch (Throwable $e) {
    error_log('Customer notifications failed: ' . $e->getMessage());
    hivenest_customer_notifications_out(500, ['ok' => false, 'error' => 'Notification request failed.']);
}
