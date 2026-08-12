<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/crm_notifications.php';
require_once __DIR__ . '/../utilities/upload_security.php';

function hivenest_onboarding_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_onboarding_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function hivenest_onboarding_table_exists(PDO $db, string $table): bool
{
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
    ");
    $stmt->execute(['table_name' => $table]);
    return (int) $stmt->fetchColumn() > 0;
}

function hivenest_onboarding_column_exists(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
          AND COLUMN_NAME = :column_name
    ");
    $stmt->execute(['table_name' => $table, 'column_name' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function hivenest_onboarding_ensure_schema(PDO $db): void
{
    if (hivenest_onboarding_table_exists($db, 'customer_service_onboarding')) return;
    $db->exec("
        CREATE TABLE IF NOT EXISTS customer_service_onboarding (
            id INT AUTO_INCREMENT PRIMARY KEY,
            uuid VARCHAR(36) NOT NULL UNIQUE,
            customer_id INT NOT NULL,
            service_id INT NULL,
            order_id INT NULL,
            order_item_id INT NULL,
            onboarding_type VARCHAR(80) NOT NULL DEFAULT 'general',
            status ENUM('submitted','in_review','needs_more_info','accepted','completed') NOT NULL DEFAULT 'submitted',
            payload LONGTEXT NULL,
            uploaded_files LONGTEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_customer_status (customer_id, status),
            INDEX idx_service_status (service_id, status),
            INDEX idx_order_item_status (order_item_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function hivenest_onboarding_ensure_work_queue_schema(PDO $db): void
{
    if (!hivenest_onboarding_table_exists($db, 'provisioning_jobs')) return;
    if (hivenest_onboarding_table_exists($db, 'crm_work_items')) return;
    $db->exec("
        CREATE TABLE IF NOT EXISTS crm_work_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            uuid VARCHAR(36) NOT NULL UNIQUE,
            provisioning_job_id INT NOT NULL UNIQUE,
            assigned_to INT NULL,
            priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
            work_status ENUM('todo','in_progress','waiting_client','waiting_provider','completed','cancelled') NOT NULL DEFAULT 'todo',
            staff_notes TEXT NULL,
            due_at TIMESTAMP NULL,
            completed_at TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_crm_work_status (work_status),
            INDEX idx_crm_work_assigned (assigned_to),
            CONSTRAINT fk_crm_work_items_job FOREIGN KEY (provisioning_job_id) REFERENCES provisioning_jobs(id) ON DELETE CASCADE,
            CONSTRAINT fk_crm_work_items_admin FOREIGN KEY (assigned_to) REFERENCES admin_users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function hivenest_onboarding_clean_text(string $value, int $max = 5000): string
{
    $value = trim(str_replace(["\0"], '', $value));
    if (function_exists('mb_substr')) return mb_substr($value, 0, $max);
    return substr($value, 0, $max);
}

function hivenest_onboarding_files(int $customerId): array
{
    if (empty($_FILES['files']) || !is_array($_FILES['files']['name'] ?? null)) return [];

    $uploadRoot = realpath(__DIR__ . '/../uploads/onboarding');
    if ($uploadRoot === false) {
        @mkdir(__DIR__ . '/../uploads/onboarding', 0755, true);
        $uploadRoot = realpath(__DIR__ . '/../uploads/onboarding');
    }
    if ($uploadRoot === false || !is_dir($uploadRoot)) return [];

    $customerDir = $uploadRoot . DIRECTORY_SEPARATOR . 'customer_' . $customerId;
    if (!is_dir($customerDir)) @mkdir($customerDir, 0755, true);

    $allowedExtensions = ['jpg','jpeg','png','gif','webp','svg','pdf','doc','docx','txt','zip'];
    $saved = [];
    $count = min(count($_FILES['files']['name']), 8);
    for ($i = 0; $i < $count; $i++) {
        $error = (int) ($_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        if ($error !== UPLOAD_ERR_OK) {
            $saved[] = ['original_name' => (string) $_FILES['files']['name'][$i], 'error' => 'Upload failed.'];
            continue;
        }
        $saved[] = hivenest_secure_upload([
            'name' => $_FILES['files']['name'][$i] ?? '',
            'type' => $_FILES['files']['type'][$i] ?? '',
            'tmp_name' => $_FILES['files']['tmp_name'][$i] ?? '',
            'error' => $error,
            'size' => $_FILES['files']['size'][$i] ?? 0,
        ], $customerDir, 'uploads/onboarding/customer_' . $customerId, $allowedExtensions, 10 * 1024 * 1024);
    }
    return $saved;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hivenest_onboarding_out(405, ['error' => 'POST required']);
}

$customerId = (int) ($_SESSION['customer_id'] ?? 0);
if ($customerId <= 0) {
    hivenest_onboarding_out(401, ['error' => 'Customer login required.']);
}

$db = hivenest_db();
if (!$db) {
    hivenest_onboarding_out(503, ['error' => 'Customer database is unavailable.']);
}

hivenest_onboarding_ensure_schema($db);
hivenest_onboarding_ensure_work_queue_schema($db);

$serviceId = (int) ($_POST['service_id'] ?? 0);
if ($serviceId <= 0) {
    hivenest_onboarding_out(422, ['error' => 'Select a service to onboard.']);
}

$serviceStmt = $db->prepare("
    SELECT
        s.id,
        s.customer_id,
        s.order_id,
        s.service_name,
        s.service_type,
        s.domain_name,
        s.service_config,
        oi.id AS order_item_id
    FROM services s
    LEFT JOIN order_items oi ON oi.service_id = s.id
    WHERE s.id = :service_id
      AND s.customer_id = :customer_id
    LIMIT 1
");
$serviceStmt->execute(['service_id' => $serviceId, 'customer_id' => $customerId]);
$service = $serviceStmt->fetch();
if (!$service) {
    hivenest_onboarding_out(404, ['error' => 'Service not found for this customer.']);
}

$payload = [
    'service_name' => (string) $service['service_name'],
    'service_type' => (string) $service['service_type'],
    'domain_name' => $service['domain_name'] ?: null,
    'business_name' => hivenest_onboarding_clean_text((string) ($_POST['business_name'] ?? ''), 200),
    'project_type' => hivenest_onboarding_clean_text((string) ($_POST['project_type'] ?? 'general'), 80),
    'goals' => hivenest_onboarding_clean_text((string) ($_POST['goals'] ?? ''), 5000),
    'target_audience' => hivenest_onboarding_clean_text((string) ($_POST['target_audience'] ?? ''), 3000),
    'brand_notes' => hivenest_onboarding_clean_text((string) ($_POST['brand_notes'] ?? ''), 3000),
    'required_pages' => hivenest_onboarding_clean_text((string) ($_POST['required_pages'] ?? ''), 3000),
    'competitors' => hivenest_onboarding_clean_text((string) ($_POST['competitors'] ?? ''), 3000),
    'social_links' => hivenest_onboarding_clean_text((string) ($_POST['social_links'] ?? ''), 3000),
    'deadline' => hivenest_onboarding_clean_text((string) ($_POST['deadline'] ?? ''), 80),
    'extra_notes' => hivenest_onboarding_clean_text((string) ($_POST['extra_notes'] ?? ''), 5000),
    'submitted_at' => gmdate('c'),
];

if ($payload['business_name'] === '' || $payload['goals'] === '') {
    hivenest_onboarding_out(422, ['error' => 'Business name and project goals are required.']);
}

$files = hivenest_onboarding_files($customerId);

$db->beginTransaction();
try {
    $uuid = hivenest_onboarding_uuid();
    $insert = $db->prepare("
        INSERT INTO customer_service_onboarding
            (uuid, customer_id, service_id, order_id, order_item_id, onboarding_type, status, payload, uploaded_files)
        VALUES
            (:uuid, :customer_id, :service_id, :order_id, :order_item_id, :onboarding_type, 'submitted', :payload, :uploaded_files)
    ");
    $insert->execute([
        'uuid' => $uuid,
        'customer_id' => $customerId,
        'service_id' => $serviceId,
        'order_id' => $service['order_id'] ? (int) $service['order_id'] : null,
        'order_item_id' => $service['order_item_id'] ? (int) $service['order_item_id'] : null,
        'onboarding_type' => $payload['project_type'] !== '' ? $payload['project_type'] : 'general',
        'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        'uploaded_files' => json_encode($files, JSON_UNESCAPED_SLASHES),
    ]);
    $onboardingId = (int) $db->lastInsertId();

    if (hivenest_onboarding_column_exists($db, 'services', 'service_config')) {
        $config = json_decode((string) ($service['service_config'] ?? ''), true);
        if (!is_array($config)) $config = [];
        $config['onboarding_status'] = 'submitted';
        $config['latest_onboarding_id'] = $onboardingId;
        $config['onboarding_submitted_at'] = gmdate('c');
        $update = $db->prepare("UPDATE services SET service_config = :config WHERE id = :service_id AND customer_id = :customer_id");
        $update->execute([
            'config' => json_encode($config, JSON_UNESCAPED_SLASHES),
            'service_id' => $serviceId,
            'customer_id' => $customerId,
        ]);
    }

    if (hivenest_onboarding_table_exists($db, 'provisioning_jobs') && (int) ($service['order_id'] ?? 0) > 0) {
        $teamJobType = strtolower((string)($service['service_type'] ?? '')) === 'marketing' ? 'marketing_queue' : 'design_queue';
        $onboardingJobPayload = json_encode([
            'client_onboarding_id' => $onboardingId,
            'client_onboarding_uuid' => $uuid,
            'client_onboarding_status' => 'submitted',
            'client_onboarding_submitted_at' => gmdate('c'),
            'service_id' => $serviceId,
            'service_name' => (string)($service['service_name'] ?? ''),
            'service_type' => (string)($service['service_type'] ?? ''),
            'domain_name' => $service['domain_name'] ?: null,
            'business_name' => $payload['business_name'],
            'project_type' => $payload['project_type'],
        ], JSON_UNESCAPED_SLASHES);

        $job = $db->prepare("
            UPDATE provisioning_jobs
            SET response_payload = :payload,
                status = 'manual_review',
                error_message = 'Client onboarding submitted and ready for CRM review.',
                updated_at = NOW()
            WHERE order_item_id = :order_item_id
              AND job_type IN ('design_queue','marketing_queue','manual_queue')
            ORDER BY id DESC
            LIMIT 1
        ");
        $job->execute([
            'payload' => $onboardingJobPayload,
            'order_item_id' => (int) $service['order_item_id'],
        ]);

        $jobId = 0;
        $findJob = $db->prepare("
            SELECT id
            FROM provisioning_jobs
            WHERE order_item_id = :order_item_id
              AND job_type IN ('design_queue','marketing_queue','manual_queue')
            ORDER BY id DESC
            LIMIT 1
        ");
        $findJob->execute(['order_item_id' => (int)($service['order_item_id'] ?? 0)]);
        $jobId = (int)($findJob->fetchColumn() ?: 0);

        if ($jobId <= 0 && (int)($service['order_item_id'] ?? 0) > 0) {
            $insertJob = $db->prepare("
                INSERT INTO provisioning_jobs
                    (uuid, order_id, order_item_id, service_id, customer_id, job_type, provider, status, request_payload, response_payload, error_message)
                VALUES
                    (:uuid, :order_id, :order_item_id, :service_id, :customer_id, :job_type, 'hivenest_team', 'manual_review', :request_payload, :response_payload, 'Client onboarding submitted and ready for CRM review.')
            ");
            $insertJob->execute([
                'uuid' => hivenest_onboarding_uuid(),
                'order_id' => (int)$service['order_id'],
                'order_item_id' => (int)$service['order_item_id'],
                'service_id' => $serviceId,
                'customer_id' => $customerId,
                'job_type' => $teamJobType,
                'request_payload' => $onboardingJobPayload,
                'response_payload' => $onboardingJobPayload,
            ]);
            $jobId = (int)$db->lastInsertId();
        }

        if ($jobId > 0 && hivenest_onboarding_table_exists($db, 'crm_work_items')) {
            $previousQueueStmt = $db->prepare('SELECT id, work_status, priority FROM crm_work_items WHERE provisioning_job_id = :job_id LIMIT 1');
            $previousQueueStmt->execute(['job_id' => $jobId]);
            $previousQueue = $previousQueueStmt->fetch() ?: [];
            $queue = $db->prepare("
                INSERT INTO crm_work_items (uuid, provisioning_job_id, priority, work_status, staff_notes)
                VALUES (:uuid, :job_id, 'high', 'todo', 'Client onboarding submitted. Review brief and uploads.')
                ON DUPLICATE KEY UPDATE
                    priority = 'high',
                    work_status = 'todo',
                    staff_notes = 'Client onboarding submitted. Review brief and uploads.',
                    completed_at = NULL
            ");
            $queue->execute([
                'uuid' => hivenest_onboarding_uuid(),
                'job_id' => $jobId,
            ]);
            if (hivenest_onboarding_table_exists($db, 'crm_work_item_history')) {
                $currentQueueStmt = $db->prepare('SELECT id FROM crm_work_items WHERE provisioning_job_id = :job_id LIMIT 1');
                $currentQueueStmt->execute(['job_id' => $jobId]);
                $workItemId = (int)($currentQueueStmt->fetchColumn() ?: 0);
                if ($workItemId > 0) {
                    $historyStmt = $db->prepare("
                        INSERT INTO crm_work_item_history
                            (work_item_id, provisioning_job_id, admin_user_id, action, previous_values, new_values, note)
                        VALUES
                            (:work_item_id, :job_id, NULL, 'client_onboarding_submitted', :previous_values, :new_values, :note)
                    ");
                    $historyStmt->execute([
                        'work_item_id' => $workItemId,
                        'job_id' => $jobId,
                        'previous_values' => json_encode([
                            'work_status' => $previousQueue['work_status'] ?? null,
                            'priority' => $previousQueue['priority'] ?? null,
                        ], JSON_UNESCAPED_SLASHES),
                        'new_values' => json_encode([
                            'work_status' => 'todo',
                            'priority' => 'high',
                            'onboarding_status' => 'submitted',
                            'onboarding_id' => $onboardingId,
                            'customer_id' => $customerId,
                            'uploaded_file_count' => count($files),
                        ], JSON_UNESCAPED_SLASHES),
                        'note' => 'Client onboarding submitted. Review brief and uploads.',
                    ]);
                }
            }
        }
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    error_log('Client onboarding submission failed: ' . $e->getMessage());
    hivenest_onboarding_out(500, ['error' => 'Onboarding could not be saved. Please try again.']);
}

try {
    $serviceLabel = (string)($service['service_name'] ?? ('Service #' . $serviceId));
    hivenest_crm_notify_all_admins(
        $db,
        'urgent',
        'Client onboarding submitted',
        $serviceLabel . ' is ready for brief and upload review.',
        '/work-queue/?q=' . rawurlencode($serviceLabel),
        'client_onboarding',
        $onboardingId
    );
} catch (Throwable $e) {
    error_log('CRM in-app onboarding notification failed: ' . $e->getMessage());
}

hivenest_onboarding_out(201, [
    'success' => true,
    'message' => 'Onboarding submitted. The HiveNest team can now review your requirements.',
    'onboarding_id' => $onboardingId,
    'uploaded_files' => $files,
]);
