<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/service_credentials.php';
require_once __DIR__ . '/../utilities/crm_notifications.php';

function hivenest_workflow_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_workflow_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function hivenest_workflow_clean(string $value, int $max = 3000): string
{
    $value = trim(str_replace(["\0"], '', $value));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function hivenest_workflow_json(?string $value): array
{
    if ($value === null || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function hivenest_workflow_table_exists(PDO $db, string $table): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table");
    $stmt->execute(['table' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function hivenest_workflow_add_completion_note(PDO $db, array $service): void
{
    if (!hivenest_workflow_table_exists($db, 'service_notes')) return;

    $serviceId = (int)($service['id'] ?? 0);
    $customerId = (int)($service['customer_id'] ?? 0);
    if ($serviceId <= 0 || $customerId <= 0) return;

    $noteText = 'Workflow completed by client approval. Final files and handover details are available in the service workflow.';
    $exists = $db->prepare("
        SELECT COUNT(*)
        FROM service_notes
        WHERE service_id = :service_id
          AND customer_id = :customer_id
          AND visibility = 'client'
          AND note_type = 'handover'
          AND note_text = :note_text
    ");
    $exists->execute([
        'service_id' => $serviceId,
        'customer_id' => $customerId,
        'note_text' => $noteText,
    ]);
    if ((int)$exists->fetchColumn() > 0) return;

    $insert = $db->prepare("
        INSERT INTO service_notes
            (uuid, service_id, customer_id, order_id, author_type, author_customer_id, visibility, note_type, note_text)
        VALUES
            (:uuid, :service_id, :customer_id, :order_id, 'system', :author_customer_id, 'client', 'handover', :note_text)
    ");
    $insert->execute([
        'uuid' => hivenest_workflow_uuid(),
        'service_id' => $serviceId,
        'customer_id' => $customerId,
        'order_id' => !empty($service['order_id']) ? (int)$service['order_id'] : null,
        'author_customer_id' => $customerId,
        'note_text' => $noteText,
    ]);
}

function hivenest_workflow_ensure_schema(PDO $db): void
{
    if (hivenest_workflow_table_exists($db, 'service_workflow_stages')) return;
    $db->exec("
        CREATE TABLE IF NOT EXISTS service_workflow_stages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            uuid VARCHAR(36) NOT NULL UNIQUE,
            service_id INT NOT NULL,
            customer_id INT NOT NULL,
            order_id INT NULL,
            stage_key VARCHAR(80) NOT NULL,
            title VARCHAR(180) NOT NULL,
            description TEXT NULL,
            status ENUM('pending','in_progress','ready_for_review','changes_requested','approved','completed') NOT NULL DEFAULT 'pending',
            display_order INT NOT NULL DEFAULT 0,
            visible_to_customer TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_service_order (service_id, display_order),
            INDEX idx_customer_status (customer_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS service_workflow_deliverables (
            id INT AUTO_INCREMENT PRIMARY KEY,
            uuid VARCHAR(36) NOT NULL UNIQUE,
            stage_id INT NOT NULL,
            service_id INT NOT NULL,
            customer_id INT NOT NULL,
            uploaded_by_type ENUM('admin','customer','system') NOT NULL DEFAULT 'admin',
            uploaded_by_admin_id INT NULL,
            uploaded_by_customer_id INT NULL,
            title VARCHAR(180) NOT NULL,
            notes TEXT NULL,
            file_original_name VARCHAR(255) NULL,
            file_stored_name VARCHAR(255) NULL,
            file_relative_path VARCHAR(500) NULL,
            file_size INT NULL,
            mime_type VARCHAR(120) NULL,
            is_final TINYINT(1) NOT NULL DEFAULT 0,
            visible_to_customer TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_stage_created (stage_id, created_at),
            INDEX idx_service_created (service_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS service_workflow_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            uuid VARCHAR(36) NOT NULL UNIQUE,
            stage_id INT NOT NULL,
            service_id INT NOT NULL,
            customer_id INT NOT NULL,
            author_type ENUM('admin','customer','system') NOT NULL DEFAULT 'customer',
            author_admin_id INT NULL,
            author_customer_id INT NULL,
            comment_text TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_stage_created (stage_id, created_at),
            INDEX idx_service_created (service_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function hivenest_workflow_default_stages(PDO $db, array $service): void
{
    $count = $db->prepare('SELECT COUNT(*) FROM service_workflow_stages WHERE service_id = :service_id');
    $count->execute(['service_id' => (int)$service['id']]);
    if ((int)$count->fetchColumn() > 0) return;

    $type = strtolower((string)($service['service_type'] ?? ''));
    $isMarketing = str_contains($type, 'marketing');
    $stages = $isMarketing
        ? [
            ['campaign_brief', 'Campaign Brief', 'CRM reviews goals, audience, channels, and campaign assets.'],
            ['strategy_draft', 'Strategy Draft', 'First campaign strategy or creative direction is shared for review.'],
            ['asset_review', 'Asset Review', 'Campaign graphics, copy, or ad assets are shared for feedback.'],
            ['revision_window', 'Revision Window', 'Requested changes are reviewed and applied.'],
            ['final_launch', 'Final Launch Pack', 'Final campaign assets, notes, and next steps are delivered.'],
        ]
        : [
            ['design_1', 'Design Option 1', 'First design/build concept uploaded by the HiveNest team.'],
            ['design_2', 'Design Option 2', 'Second design/build concept or revised direction.'],
            ['design_3', 'Design Option 3', 'Third design/build concept or final comparison option.'],
            ['revision_window', 'Revision Window', 'Client comments and requested changes are processed here.'],
            ['final_delivery', 'Final Delivery', 'Final approved files, links, and handover notes.'],
        ];

    $insert = $db->prepare("
        INSERT INTO service_workflow_stages
            (uuid, service_id, customer_id, order_id, stage_key, title, description, status, display_order)
        VALUES
            (:uuid, :service_id, :customer_id, :order_id, :stage_key, :title, :description, :status, :display_order)
    ");
    foreach ($stages as $index => $stage) {
        $insert->execute([
            'uuid' => hivenest_workflow_uuid(),
            'service_id' => (int)$service['id'],
            'customer_id' => (int)$service['customer_id'],
            'order_id' => $service['order_id'] ? (int)$service['order_id'] : null,
            'stage_key' => $stage[0],
            'title' => $stage[1],
            'description' => $stage[2],
            'status' => $index === 0 ? 'in_progress' : 'pending',
            'display_order' => $index + 1,
        ]);
    }
}

function hivenest_workflow_notify_crm(PDO $db, array $service, array $stage, string $action, string $comment): void
{
    if (!hivenest_workflow_table_exists($db, 'provisioning_jobs') || !hivenest_workflow_table_exists($db, 'crm_work_items')) return;

    $serviceId = (int)$service['id'];
    $orderId = (int)($service['order_id'] ?? 0);
    $orderItemId = 0;
    if (hivenest_workflow_table_exists($db, 'order_items')) {
        $item = $db->prepare('SELECT id FROM order_items WHERE service_id = :service_id ORDER BY id DESC LIMIT 1');
        $item->execute(['service_id' => $serviceId]);
        $orderItemId = (int)($item->fetchColumn() ?: 0);
    }
    if ($orderId <= 0) return;

    $type = strtolower((string)($service['service_type'] ?? ''));
    $jobType = str_contains($type, 'marketing') ? 'marketing_queue' : 'design_queue';
    $message = match ($action) {
        'request_changes' => 'Client requested workflow changes.',
        'approve' => 'Client approved a workflow stage.',
        default => 'Client commented on workflow.',
    };
    $payload = json_encode([
        'workflow_alert' => true,
        'workflow_action' => $action,
        'service_id' => $serviceId,
        'service_name' => (string)($service['service_name'] ?? ''),
        'stage_id' => (int)$stage['id'],
        'stage_title' => (string)($stage['title'] ?? ''),
        'comment' => $comment,
        'submitted_at' => gmdate('c'),
    ], JSON_UNESCAPED_SLASHES);

    $find = $db->prepare("
        SELECT id
        FROM provisioning_jobs
        WHERE service_id = :service_id
          AND job_type IN ('design_queue','marketing_queue','manual_queue')
        ORDER BY id DESC
        LIMIT 1
    ");
    $find->execute(['service_id' => $serviceId]);
    $jobId = (int)($find->fetchColumn() ?: 0);
    $previousQueueItem = [];

    if ($jobId <= 0 && $orderItemId > 0) {
        $insert = $db->prepare("
            INSERT INTO provisioning_jobs
                (uuid, order_id, order_item_id, service_id, customer_id, job_type, provider, status, request_payload, response_payload, error_message)
            VALUES
                (:uuid, :order_id, :order_item_id, :service_id, :customer_id, :job_type, 'hivenest_team', 'manual_review', :payload, :payload, :error_message)
        ");
        $insert->execute([
            'uuid' => hivenest_workflow_uuid(),
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'service_id' => $serviceId,
            'customer_id' => (int)$service['customer_id'],
            'job_type' => $jobType,
            'payload' => $payload,
            'error_message' => $message,
        ]);
        $jobId = (int)$db->lastInsertId();
    } elseif ($jobId > 0) {
        $previousQueueStmt = $db->prepare('SELECT id, work_status, priority FROM crm_work_items WHERE provisioning_job_id = :job_id LIMIT 1');
        $previousQueueStmt->execute(['job_id' => $jobId]);
        $previousQueueItem = $previousQueueStmt->fetch() ?: [];
        $update = $db->prepare("
            UPDATE provisioning_jobs
            SET status = CASE WHEN status = 'completed' THEN 'manual_review' ELSE status END,
                response_payload = :payload,
                error_message = :error_message,
                updated_at = NOW()
            WHERE id = :id
        ");
        $update->execute([
            'payload' => $payload,
            'error_message' => $message,
            'id' => $jobId,
        ]);
    }

    if ($jobId > 0) {
        $queue = $db->prepare("
            INSERT INTO crm_work_items (uuid, provisioning_job_id, priority, work_status, staff_notes)
            VALUES (:uuid, :job_id, 'high', 'todo', :staff_notes)
            ON DUPLICATE KEY UPDATE
                priority = 'high',
                work_status = 'todo',
                staff_notes = :staff_notes,
                completed_at = NULL
        ");
        $queue->execute([
            'uuid' => hivenest_workflow_uuid(),
            'job_id' => $jobId,
            'staff_notes' => $message . ' Stage: ' . (string)($stage['title'] ?? 'Workflow stage'),
        ]);
        if (hivenest_workflow_table_exists($db, 'crm_work_item_history')) {
            $currentQueueStmt = $db->prepare('SELECT id FROM crm_work_items WHERE provisioning_job_id = :job_id LIMIT 1');
            $currentQueueStmt->execute(['job_id' => $jobId]);
            $workItemId = (int)($currentQueueStmt->fetchColumn() ?: 0);
            if ($workItemId > 0) {
                $historyStmt = $db->prepare("
                    INSERT INTO crm_work_item_history
                        (work_item_id, provisioning_job_id, admin_user_id, action, previous_values, new_values, note)
                    VALUES
                        (:work_item_id, :job_id, NULL, :action, :previous_values, :new_values, :note)
                ");
                $historyStmt->execute([
                    'work_item_id' => $workItemId,
                    'job_id' => $jobId,
                    'action' => 'client_workflow_' . $action,
                    'previous_values' => json_encode([
                        'work_status' => $previousQueueItem['work_status'] ?? null,
                        'priority' => $previousQueueItem['priority'] ?? null,
                        'stage_status' => $stage['status'] ?? null,
                    ], JSON_UNESCAPED_SLASHES),
                    'new_values' => json_encode([
                        'work_status' => 'todo',
                        'priority' => 'high',
                        'workflow_action' => $action,
                        'stage_id' => (int)$stage['id'],
                        'stage_title' => (string)($stage['title'] ?? ''),
                        'customer_id' => (int)$service['customer_id'],
                    ], JSON_UNESCAPED_SLASHES),
                    'note' => $comment !== '' ? $comment : $message,
                ]);
            }
        }
    }
    try {
        $serviceLabel = (string)($service['service_name'] ?? ('Service #' . $serviceId));
        $title = match ($action) {
            'request_changes' => 'Client requested changes',
            'approve' => 'Client approved a workflow stage',
            default => 'New client workflow comment',
        };
        hivenest_crm_notify_all_admins(
            $db,
            $action === 'request_changes' ? 'urgent' : 'info',
            $title,
            $serviceLabel . ' — ' . (string)($stage['title'] ?? 'Workflow stage'),
            '/work-queue/?q=' . rawurlencode($serviceLabel),
            'service_workflow_stage',
            (int)$stage['id']
        );
    } catch (Throwable $e) {
        error_log('CRM in-app workflow notification failed: ' . $e->getMessage());
    }
}

function hivenest_workflow_advance_after_approval(PDO $db, array $service, array $stage): array
{
    $serviceId = (int)$service['id'];
    $currentOrder = (int)($stage['display_order'] ?? 0);
    $next = $db->prepare("
        SELECT id, title
        FROM service_workflow_stages
        WHERE service_id = :service_id
          AND customer_id = :customer_id
          AND display_order > :display_order
          AND status = 'pending'
        ORDER BY display_order ASC, id ASC
        LIMIT 1
    ");
    $next->execute([
        'service_id' => $serviceId,
        'customer_id' => (int)$service['customer_id'],
        'display_order' => $currentOrder,
    ]);
    $nextStage = $next->fetch();
    if ($nextStage) {
        $db->prepare("UPDATE service_workflow_stages SET status = 'in_progress' WHERE id = :id")
            ->execute(['id' => (int)$nextStage['id']]);
        return ['workflow_complete' => false, 'next_stage_id' => (int)$nextStage['id'], 'next_stage_title' => (string)$nextStage['title']];
    }

    $remaining = $db->prepare("
        SELECT COUNT(*)
        FROM service_workflow_stages
        WHERE service_id = :service_id
          AND customer_id = :customer_id
          AND status NOT IN ('approved','completed')
    ");
    $remaining->execute(['service_id' => $serviceId, 'customer_id' => (int)$service['customer_id']]);
    if ((int)$remaining->fetchColumn() === 0) {
        $config = hivenest_workflow_json($service['service_config'] ?? null);
        $config['workflow_status'] = 'completed';
        $config['workflow_completed_at'] = gmdate('c');
        $db->prepare("
            UPDATE services
            SET service_status = CASE WHEN service_status IN ('pending','suspended') THEN 'active' ELSE service_status END,
                service_config = :service_config
            WHERE id = :service_id
              AND customer_id = :customer_id
        ")->execute([
            'service_config' => json_encode($config, JSON_UNESCAPED_SLASHES),
            'service_id' => $serviceId,
            'customer_id' => (int)$service['customer_id'],
        ]);
        hivenest_workflow_add_completion_note($db, $service);
        hivenest_workflow_complete_team_jobs($db, $service);
        return ['workflow_complete' => true, 'next_stage_id' => null, 'next_stage_title' => null];
    }

    return ['workflow_complete' => false, 'next_stage_id' => null, 'next_stage_title' => null];
}

function hivenest_workflow_complete_team_jobs(PDO $db, array $service): void
{
    if (!hivenest_workflow_table_exists($db, 'provisioning_jobs')) return;

    $serviceId = (int)$service['id'];
    $response = json_encode([
        'completed_by' => 'client_workflow_approval',
        'workflow_status' => 'completed',
        'completed_at' => gmdate('c'),
    ], JSON_UNESCAPED_SLASHES);
    $previousQueueItems = [];
    if (hivenest_workflow_table_exists($db, 'crm_work_items')) {
        $previousQueueStmt = $db->prepare("
            SELECT wi.id, wi.provisioning_job_id, wi.work_status
            FROM crm_work_items wi
            INNER JOIN provisioning_jobs pj ON pj.id = wi.provisioning_job_id
            WHERE pj.service_id = :service_id
              AND pj.job_type IN ('design_queue','marketing_queue','manual_queue')
        ");
        $previousQueueStmt->execute(['service_id' => $serviceId]);
        $previousQueueItems = $previousQueueStmt->fetchAll() ?: [];
    }

    $db->prepare("
        UPDATE provisioning_jobs
        SET status = 'completed',
            response_payload = :response,
            error_message = NULL,
            updated_at = NOW()
        WHERE service_id = :service_id
          AND job_type IN ('design_queue','marketing_queue','manual_queue')
    ")->execute(['response' => $response, 'service_id' => $serviceId]);

    if (hivenest_workflow_table_exists($db, 'crm_work_items')) {
        $db->prepare("
            UPDATE crm_work_items wi
            INNER JOIN provisioning_jobs pj ON pj.id = wi.provisioning_job_id
            SET wi.work_status = 'completed',
                wi.staff_notes = 'Workflow completed by client approval.',
                wi.completed_at = NOW()
            WHERE pj.service_id = :service_id
              AND pj.job_type IN ('design_queue','marketing_queue','manual_queue')
        ")->execute(['service_id' => $serviceId]);
        if ($previousQueueItems && hivenest_workflow_table_exists($db, 'crm_work_item_history')) {
            $historyStmt = $db->prepare("
                INSERT INTO crm_work_item_history
                    (work_item_id, provisioning_job_id, admin_user_id, action, previous_values, new_values, note)
                VALUES
                    (:work_item_id, :job_id, NULL, 'client_workflow_completed', :previous_values, :new_values, :note)
            ");
            foreach ($previousQueueItems as $queueItem) {
                $historyStmt->execute([
                    'work_item_id' => (int)$queueItem['id'],
                    'job_id' => (int)$queueItem['provisioning_job_id'],
                    'previous_values' => json_encode(['work_status' => (string)$queueItem['work_status']], JSON_UNESCAPED_SLASHES),
                    'new_values' => json_encode([
                        'work_status' => 'completed',
                        'workflow_status' => 'completed',
                        'customer_id' => (int)$service['customer_id'],
                    ], JSON_UNESCAPED_SLASHES),
                    'note' => 'Workflow completed by client approval.',
                ]);
            }
        }
    }

    if (hivenest_workflow_table_exists($db, 'order_items')) {
        $db->prepare("
            UPDATE order_items
            SET provisioning_status = 'completed',
                provisioning_error = NULL
            WHERE service_id = :service_id
              AND provisioning_status IN ('pending','processing','manual_review','queued','retry')
        ")->execute(['service_id' => $serviceId]);
    }

    hivenest_workflow_refresh_order_status($db, (int)($service['order_id'] ?? 0));
}

function hivenest_workflow_refresh_order_status(PDO $db, int $orderId): void
{
    if ($orderId <= 0 || !hivenest_workflow_table_exists($db, 'orders') || !hivenest_workflow_table_exists($db, 'order_items')) return;

    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS total_items,
            SUM(provisioning_status = 'completed') AS completed_items,
            SUM(provisioning_status IN ('failed','manual_review')) AS review_items,
            SUM(provisioning_status IN ('pending','processing','queued','retry')) AS open_items
        FROM order_items
        WHERE order_id = :order_id
    ");
    $stmt->execute(['order_id' => $orderId]);
    $row = $stmt->fetch() ?: [];
    $total = (int)($row['total_items'] ?? 0);
    if ($total <= 0) return;

    $status = 'queued';
    if ((int)($row['completed_items'] ?? 0) >= $total) {
        $status = 'completed';
    } elseif ((int)($row['review_items'] ?? 0) > 0) {
        $status = 'manual_review';
    } elseif ((int)($row['open_items'] ?? 0) > 0) {
        $status = 'processing';
    }

    $db->prepare("UPDATE orders SET provisioning_status = :status WHERE id = :order_id")
        ->execute(['status' => $status, 'order_id' => $orderId]);
}

$customerId = (int)($_SESSION['customer_id'] ?? 0);
if ($customerId <= 0) hivenest_workflow_out(401, ['error' => 'Customer login required.']);

$db = hivenest_db();
if (!$db) hivenest_workflow_out(503, ['error' => 'Customer database is unavailable.']);
hivenest_workflow_ensure_schema($db);

$serviceId = (int)($_GET['service_id'] ?? $_POST['service_id'] ?? $_GET['service'] ?? $_POST['service'] ?? 0);
if ($serviceId <= 0) hivenest_workflow_out(422, ['error' => 'Service is required.']);

$serviceStmt = $db->prepare("
    SELECT s.*, p.name AS product_name, o.order_number
    FROM services s
    LEFT JOIN products p ON p.id = s.product_id
    LEFT JOIN orders o ON o.id = s.order_id
    WHERE s.id = :service_id AND s.customer_id = :customer_id
    LIMIT 1
");
$serviceStmt->execute(['service_id' => $serviceId, 'customer_id' => $customerId]);
$service = $serviceStmt->fetch();
if (!$service) hivenest_workflow_out(404, ['error' => 'Service not found.']);

hivenest_workflow_default_stages($db, $service);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = hivenest_workflow_clean((string)($_POST['action'] ?? ''), 40);
    $stageId = (int)($_POST['stage_id'] ?? 0);
    if ($stageId <= 0) hivenest_workflow_out(422, ['error' => 'Stage is required.']);

    $stageStmt = $db->prepare('SELECT * FROM service_workflow_stages WHERE id = :id AND service_id = :service_id AND customer_id = :customer_id LIMIT 1');
    $stageStmt->execute(['id' => $stageId, 'service_id' => $serviceId, 'customer_id' => $customerId]);
    $stage = $stageStmt->fetch();
    if (!$stage) hivenest_workflow_out(404, ['error' => 'Workflow stage not found.']);

    if ($action === 'comment' || $action === 'request_changes' || $action === 'approve') {
        $comment = hivenest_workflow_clean((string)($_POST['comment'] ?? ''), 5000);
        if ($action !== 'approve' && $comment === '') {
            hivenest_workflow_out(422, ['error' => 'Comment is required.']);
        }
        if ($comment !== '') {
            $insert = $db->prepare("
                INSERT INTO service_workflow_comments
                    (uuid, stage_id, service_id, customer_id, author_type, author_customer_id, comment_text)
                VALUES
                    (:uuid, :stage_id, :service_id, :customer_id, 'customer', :author_customer_id, :comment_text)
            ");
            $insert->execute([
                'uuid' => hivenest_workflow_uuid(),
                'stage_id' => $stageId,
                'service_id' => $serviceId,
                'customer_id' => $customerId,
                'author_customer_id' => $customerId,
                'comment_text' => $comment,
            ]);
        }
        $progress = ['workflow_complete' => false, 'next_stage_id' => null, 'next_stage_title' => null];
        if ($action === 'request_changes' || $action === 'approve') {
            $newStatus = $action === 'approve' ? 'approved' : 'changes_requested';
            $update = $db->prepare('UPDATE service_workflow_stages SET status = :status WHERE id = :id');
            $update->execute(['status' => $newStatus, 'id' => $stageId]);
        }
        if (in_array($action, ['comment', 'request_changes', 'approve'], true)) {
            hivenest_workflow_notify_crm($db, $service, $stage, $action, $comment);
        }
        if ($action === 'approve') {
            $progress = hivenest_workflow_advance_after_approval($db, $service, $stage);
        }
        hivenest_workflow_out(200, [
            'ok' => true,
            'message' => $progress['workflow_complete']
                ? 'Workflow approved and completed.'
                : ($progress['next_stage_title'] ? 'Workflow approved. Next stage is now in progress.' : 'Workflow updated.'),
            'progress' => $progress,
        ]);
    }

    hivenest_workflow_out(422, ['error' => 'Unknown workflow action.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') hivenest_workflow_out(405, ['error' => 'Method not allowed.']);

$stagesStmt = $db->prepare("
    SELECT *
    FROM service_workflow_stages
    WHERE service_id = :service_id
      AND customer_id = :customer_id
      AND visible_to_customer = 1
    ORDER BY display_order ASC, id ASC
");
$stagesStmt->execute(['service_id' => $serviceId, 'customer_id' => $customerId]);
$stages = $stagesStmt->fetchAll() ?: [];

$stageIds = array_map(static fn($stage) => (int)$stage['id'], $stages);
$deliverablesByStage = [];
$commentsByStage = [];
if ($stageIds) {
    $placeholders = implode(',', array_fill(0, count($stageIds), '?'));
    $deliverableStmt = $db->prepare("SELECT * FROM service_workflow_deliverables WHERE stage_id IN ({$placeholders}) AND visible_to_customer = 1 ORDER BY id ASC");
    $deliverableStmt->execute($stageIds);
    foreach ($deliverableStmt->fetchAll() ?: [] as $row) {
        $deliverablesByStage[(int)$row['stage_id']][] = [
            'id' => (int)$row['id'],
            'title' => (string)$row['title'],
            'notes' => $row['notes'] ?? null,
            'file_original_name' => $row['file_original_name'] ?? null,
            'file_stored_name' => $row['file_stored_name'] ?? null,
            'file_size' => $row['file_size'] !== null ? (int)$row['file_size'] : null,
            'is_final' => (int)($row['is_final'] ?? 0) === 1,
            'uploaded_by_type' => (string)$row['uploaded_by_type'],
            'created_at' => $row['created_at'] ?? null,
        ];
    }
    $commentStmt = $db->prepare("SELECT * FROM service_workflow_comments WHERE stage_id IN ({$placeholders}) ORDER BY id ASC");
    $commentStmt->execute($stageIds);
    foreach ($commentStmt->fetchAll() ?: [] as $row) {
        $commentsByStage[(int)$row['stage_id']][] = [
            'id' => (int)$row['id'],
            'author_type' => (string)$row['author_type'],
            'comment_text' => (string)$row['comment_text'],
            'created_at' => $row['created_at'] ?? null,
        ];
    }
}

$payloadStages = [];
foreach ($stages as $stage) {
    $payloadStages[] = [
        'id' => (int)$stage['id'],
        'stage_key' => (string)$stage['stage_key'],
        'title' => (string)$stage['title'],
        'description' => $stage['description'] ?? null,
        'status' => (string)$stage['status'],
        'display_order' => (int)$stage['display_order'],
        'deliverables' => $deliverablesByStage[(int)$stage['id']] ?? [],
        'comments' => $commentsByStage[(int)$stage['id']] ?? [],
    ];
}

hivenest_workflow_out(200, [
    'service' => [
        'id' => (int)$service['id'],
        'service_name' => (string)$service['service_name'],
        'service_type' => (string)$service['service_type'],
        'domain_name' => $service['domain_name'] ?? null,
        'service_status' => (string)$service['service_status'],
        'product_name' => $service['product_name'] ?? null,
        'order_number' => $service['order_number'] ?? null,
        'service_config' => hivenest_service_credentials_redact_config(
            hivenest_workflow_json($service['service_config'] ?? null)
        ),
    ],
    'stages' => $payloadStages,
]);
