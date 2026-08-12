<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../utilities/customer_session.php';
hivenest_customer_session_configure();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hivenest_customer_csrf_require_json();
}

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/support_notifications.php';
require_once __DIR__ . '/../utilities/crm_notifications.php';

function hivenest_customer_service_requests_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_customer_service_requests_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function hivenest_customer_service_requests_clean(string $value, int $max): string
{
    $value = trim(str_replace("\0", '', $value));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function hivenest_customer_service_requests_table_exists(PDO $db, string $table): bool
{
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
        ");
        $stmt->execute(['table_name' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        error_log('Customer service request table check failed: ' . $e->getMessage());
        return false;
    }
}

function hivenest_customer_service_requests_ensure(PDO $db): void
{
    if (hivenest_customer_service_requests_table_exists($db, 'service_requests')) return;
    $db->exec("
        CREATE TABLE IF NOT EXISTS service_requests (
            id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            uuid varchar(36) NOT NULL UNIQUE,
            service_id int(11) NOT NULL,
            customer_id int(11) NOT NULL,
            order_id int(11) DEFAULT NULL,
            request_type enum('renewal','cancel','toggle_auto_renew','upgrade','downgrade','general') NOT NULL DEFAULT 'general',
            requested_value varchar(100) DEFAULT NULL,
            status enum('pending','in_review','approved','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
            message text DEFAULT NULL,
            admin_response text DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            KEY idx_service_request_service (service_id,status),
            KEY idx_service_request_customer (customer_id,status),
            KEY idx_service_request_order (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function hivenest_customer_service_requests_queue_crm(PDO $db, array $service, int $requestId, string $requestType, ?string $requestedValue, string $message): bool
{
    if (
        !hivenest_customer_service_requests_table_exists($db, 'provisioning_jobs')
        || !hivenest_customer_service_requests_table_exists($db, 'crm_work_items')
    ) {
        return false;
    }

    $serviceId = (int)($service['id'] ?? 0);
    $customerId = (int)($service['customer_id'] ?? 0);
    if ($serviceId <= 0 || $customerId <= 0 || $requestId <= 0) return false;

    $payload = [
        'source' => 'customer_service_request',
        'service_request_id' => $requestId,
        'request_type' => $requestType,
        'requested_value' => $requestedValue,
        'message' => $message,
        'product_name' => $service['service_name'] ?? 'Service request',
        'domain_name' => $service['domain_name'] ?? null,
    ];

    $job = $db->prepare("
        INSERT INTO provisioning_jobs
            (uuid, order_id, service_id, customer_id, job_type, provider, status, request_payload, error_message)
        VALUES
            (:uuid, :order_id, :service_id, :customer_id, 'manual_queue', 'hivenest_team', 'manual_review', :payload, :error_message)
    ");
    $job->execute([
        'uuid' => hivenest_customer_service_requests_uuid(),
        'order_id' => !empty($service['order_id']) ? (int)$service['order_id'] : null,
        'service_id' => $serviceId,
        'customer_id' => $customerId,
        'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        'error_message' => 'Customer service request needs CRM review: ' . str_replace('_', ' ', $requestType),
    ]);
    $jobId = (int)$db->lastInsertId();
    if ($jobId <= 0) return false;

    $priority = in_array($requestType, ['cancel','downgrade'], true) ? 'high' : 'normal';
    $staffNote = 'Client submitted service request #' . $requestId . ': ' . $message;
    $work = $db->prepare("
        INSERT INTO crm_work_items
            (uuid, provisioning_job_id, priority, work_status, staff_notes)
        VALUES
            (:uuid, :job_id, :priority, 'todo', :staff_notes)
    ");
    $work->execute([
        'uuid' => hivenest_customer_service_requests_uuid(),
        'job_id' => $jobId,
        'priority' => $priority,
        'staff_notes' => $staffNote,
    ]);
    $workItemId = (int)$db->lastInsertId();
    if ($workItemId <= 0) return false;

    if (hivenest_customer_service_requests_table_exists($db, 'crm_work_item_history')) {
        $history = $db->prepare("
            INSERT INTO crm_work_item_history
                (work_item_id, provisioning_job_id, admin_user_id, action, previous_values, new_values, note)
            VALUES
                (:work_item_id, :job_id, NULL, 'client_service_request_submitted', NULL, :new_values, :note)
        ");
        $history->execute([
            'work_item_id' => $workItemId,
            'job_id' => $jobId,
            'new_values' => json_encode([
                'work_status' => 'todo',
                'priority' => $priority,
                'service_request_id' => $requestId,
                'request_type' => $requestType,
                'requested_value' => $requestedValue,
                'customer_id' => $customerId,
            ], JSON_UNESCAPED_SLASHES),
            'note' => $staffNote,
        ]);
    }
    return true;
}

$customerId = (int)($_SESSION['customer_id'] ?? 0);
if ($customerId <= 0) {
    hivenest_customer_service_requests_out(401, ['error' => 'Customer login required.']);
}

$db = hivenest_db();
if (!$db) {
    hivenest_customer_service_requests_out(503, ['error' => 'Customer database is unavailable.']);
}
hivenest_customer_service_requests_ensure($db);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $serviceId = (int)($_GET['service_id'] ?? $_GET['service'] ?? 0);
    $where = 'sr.customer_id = :customer_id';
    $params = ['customer_id' => $customerId];
    if ($serviceId > 0) {
        $where .= ' AND sr.service_id = :service_id';
        $params['service_id'] = $serviceId;
    }
    $stmt = $db->prepare("
        SELECT sr.*, s.service_name, s.domain_name, s.service_type
        FROM service_requests sr
        INNER JOIN services s ON s.id = sr.service_id
        WHERE {$where}
        ORDER BY sr.id DESC
        LIMIT 50
    ");
    $stmt->execute($params);
    hivenest_customer_service_requests_out(200, ['requests' => $stmt->fetchAll() ?: []]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hivenest_customer_service_requests_out(405, ['error' => 'Method not allowed.']);
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$serviceId = (int)($input['service_id'] ?? 0);
$requestType = hivenest_customer_service_requests_clean((string)($input['request_type'] ?? 'general'), 40);
$requestedValue = hivenest_customer_service_requests_clean((string)($input['requested_value'] ?? ''), 100);
$message = hivenest_customer_service_requests_clean((string)($input['message'] ?? ''), 5000);
$allowedTypes = ['renewal','cancel','toggle_auto_renew','upgrade','downgrade','general'];
if ($serviceId <= 0) hivenest_customer_service_requests_out(422, ['error' => 'Service ID is required.']);
if (!in_array($requestType, $allowedTypes, true)) $requestType = 'general';
if ($requestType === 'toggle_auto_renew' && !in_array($requestedValue, ['enabled','disabled'], true)) {
    hivenest_customer_service_requests_out(422, ['error' => 'Auto-renew request must be enabled or disabled.']);
}
if ($message === '') {
    $message = match ($requestType) {
        'renewal' => 'Customer requested a renewal review.',
        'cancel' => 'Customer requested cancellation review.',
        'toggle_auto_renew' => 'Customer requested auto-renew to be ' . $requestedValue . '.',
        'upgrade' => 'Customer requested an upgrade review.',
        'downgrade' => 'Customer requested a downgrade review.',
        default => 'Customer requested service assistance.',
    };
}

$serviceStmt = $db->prepare('SELECT id, customer_id, order_id, service_name, domain_name, service_type, auto_renew FROM services WHERE id = :id AND customer_id = :customer_id LIMIT 1');
$serviceStmt->execute(['id' => $serviceId, 'customer_id' => $customerId]);
$service = $serviceStmt->fetch();
if (!$service) hivenest_customer_service_requests_out(404, ['error' => 'Selected service was not found.']);

$directAutoRenew = $requestType === 'toggle_auto_renew';
if ($directAutoRenew) {
    $enabled = $requestedValue === 'enabled';
    try {
        $db->beginTransaction();
        $db->prepare("
            UPDATE services
            SET auto_renew=:auto_renew, updated_at=CURRENT_TIMESTAMP
            WHERE id=:service_id
              AND customer_id=:customer_id
        ")->execute([
            'auto_renew' => $enabled ? 1 : 0,
            'service_id' => $serviceId,
            'customer_id' => $customerId,
        ]);
        if ((string)$service['service_type'] === 'domain') {
            $db->prepare("
                UPDATE domain_registrations
                SET auto_renew=:auto_renew, updated_at=CURRENT_TIMESTAMP
                WHERE service_id=:service_id
                  AND customer_id=:customer_id
            ")->execute([
                'auto_renew' => $enabled ? 1 : 0,
                'service_id' => $serviceId,
                'customer_id' => $customerId,
            ]);
        }
        $stmt = $db->prepare("
            INSERT INTO service_requests
                (uuid, service_id, customer_id, order_id, request_type, requested_value, status, message, admin_response)
            VALUES
                (:uuid, :service_id, :customer_id, :order_id, 'toggle_auto_renew',
                 :requested_value, 'completed', :message, :admin_response)
        ");
        $stmt->execute([
            'uuid' => hivenest_customer_service_requests_uuid(),
            'service_id' => $serviceId,
            'customer_id' => $customerId,
            'order_id' => !empty($service['order_id']) ? (int)$service['order_id'] : null,
            'requested_value' => $requestedValue,
            'message' => $message,
            'admin_response' => 'HiveNest automatic renewal invoicing was updated immediately.',
        ]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('Customer auto-renew update failed: ' . $e->getMessage());
        hivenest_customer_service_requests_out(500, ['error' => 'Auto-renew could not be updated.']);
    }
    hivenest_customer_service_requests_out(200, [
        'message' => 'Auto-renew ' . ($enabled ? 'enabled.' : 'disabled.'),
        'service_id' => $serviceId,
        'auto_renew' => $enabled,
        'note' => $enabled
            ? 'HiveNest will generate a renewal invoice before the next due date. Payment is never captured without checkout approval.'
            : 'No new automatic renewal invoice will be generated. Existing unpaid invoices are not cancelled automatically.',
    ]);
}

$duplicate = $db->prepare("
    SELECT id
    FROM service_requests
    WHERE service_id = :service_id
      AND customer_id = :customer_id
      AND request_type = :request_type
      AND status IN ('pending','in_review','approved')
    ORDER BY id DESC
    LIMIT 1
");
$duplicate->execute([
    'service_id' => $serviceId,
    'customer_id' => $customerId,
    'request_type' => $requestType,
]);
if ($duplicate->fetchColumn()) {
    hivenest_customer_service_requests_out(409, ['error' => 'A similar request is already open for this service.']);
}

try {
    $db->beginTransaction();
    $stmt = $db->prepare("
        INSERT INTO service_requests
            (uuid, service_id, customer_id, order_id, request_type, requested_value, status, message)
        VALUES
            (:uuid, :service_id, :customer_id, :order_id, :request_type, :requested_value, 'pending', :message)
    ");
    $stmt->execute([
        'uuid' => hivenest_customer_service_requests_uuid(),
        'service_id' => $serviceId,
        'customer_id' => $customerId,
        'order_id' => !empty($service['order_id']) ? (int)$service['order_id'] : null,
        'request_type' => $requestType,
        'requested_value' => $requestedValue !== '' ? $requestedValue : null,
        'message' => $message,
    ]);
    $requestId = (int)$db->lastInsertId();
    $queued = hivenest_customer_service_requests_queue_crm(
        $db,
        $service,
        $requestId,
        $requestType,
        $requestedValue !== '' ? $requestedValue : null,
        $message
    );
    if (!$queued) {
        throw new RuntimeException('CRM work queue is unavailable for the new service request.');
    }
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('Customer service request failed: ' . $e->getMessage());
    hivenest_customer_service_requests_out(500, ['error' => 'Service request could not be submitted.']);
}

$notification = hivenest_service_request_notify_submission($db, $service, [
    'id' => $requestId,
    'request_type' => $requestType,
    'requested_value' => $requestedValue !== '' ? $requestedValue : null,
    'message' => $message,
]);
if (!$notification['client']) {
    error_log('Service request acknowledgement email was not sent for request #' . $requestId);
}
if (!$notification['team']) {
    error_log('Service request team alert email was not sent for request #' . $requestId);
}
try {
    $serviceLabel = (string)($service['service_name'] ?? $service['product_name'] ?? ('Service #' . $serviceId));
    hivenest_crm_notify_all_admins(
        $db,
        'urgent',
        'New client service request',
        $serviceLabel . ': ' . str_replace('_', ' ', $requestType),
        '/work-queue/?q=' . rawurlencode($serviceLabel),
        'service_request',
        $requestId
    );
} catch (Throwable $e) {
    error_log('CRM in-app service request notification failed: ' . $e->getMessage());
}

hivenest_customer_service_requests_out(201, [
    'success' => true,
    'message' => 'Service request submitted for CRM review.',
    'request_id' => $requestId,
    'client_email_sent' => (bool)$notification['client'],
    'team_email_sent' => (bool)$notification['team'],
]);
