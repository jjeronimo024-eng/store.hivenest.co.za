<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
    session_name('HIVENEST_ADMIN');
    session_start();
}

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/crm_permissions.php';
require_once __DIR__ . '/../utilities/customer_notifications.php';
require_once __DIR__ . '/../utilities/upload_security.php';

function hivenest_crm_workflow_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_crm_workflow_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function hivenest_crm_workflow_clean(string $value, int $max = 3000): string
{
    $value = trim(str_replace(["\0"], '', $value));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function hivenest_crm_workflow_env(string $key, string $default = ''): string
{
    $path = __DIR__ . '/../Backend/.env';
    if (!is_readable($path)) return $default;
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$name, $value] = explode('=', $line, 2);
        if (trim($name) !== $key) continue;
        $value = trim($value);
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) $value = substr($value, 1, -1);
        }
        return $value;
    }
    return $default;
}

function hivenest_crm_workflow_b64url_decode(string $value): string|false
{
    $remainder = strlen($value) % 4;
    if ($remainder) $value .= str_repeat('=', 4 - $remainder);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function hivenest_crm_workflow_b64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function hivenest_crm_workflow_bearer_token(): string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(.+)/i', $header, $match)) return trim($match[1]);
    return trim((string)($_COOKIE['hivenest_admin_access_token'] ?? ''));
}

function hivenest_crm_workflow_admin_id(PDO $db): int
{
    if (!empty($_SESSION['admin_user']['id']) && !empty($_SESSION['admin_login_time'])) return (int)$_SESSION['admin_user']['id'];

    $token = hivenest_crm_workflow_bearer_token();
    if ($token === '') return 0;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return 0;
    [$header64, $payload64, $signature64] = $parts;
    $headerJson = hivenest_crm_workflow_b64url_decode($header64);
    $payloadJson = hivenest_crm_workflow_b64url_decode($payload64);
    if ($headerJson === false || $payloadJson === false) return 0;
    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);
    if (!is_array($header) || !is_array($payload)) return 0;
    if (($header['alg'] ?? '') !== hivenest_crm_workflow_env('JWT_ALGORITHM', 'HS256')) return 0;
    if (($payload['user_type'] ?? '') !== 'admin') return 0;
    if (!empty($payload['exp']) && (int)$payload['exp'] < time()) return 0;
    $secret = hivenest_crm_workflow_env('JWT_SECRET_KEY');
    if ($secret === '') return 0;
    $expected = hivenest_crm_workflow_b64url_encode(hash_hmac('sha256', $header64 . '.' . $payload64, $secret, true));
    if (!hash_equals($expected, $signature64)) return 0;
    $adminId = (int)($payload['sub'] ?? 0);
    if ($adminId <= 0) return 0;
    $stmt = $db->prepare('SELECT id, username, email, role FROM admin_users WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => $adminId]);
    $admin = $stmt->fetch();
    if (!$admin) return 0;
    $_SESSION['admin_user'] = ['id' => (int)$admin['id'], 'username' => (string)$admin['username'], 'email' => (string)$admin['email'], 'role' => (string)($admin['role'] ?? 'admin')];
    $_SESSION['admin_login_time'] = $_SESSION['admin_login_time'] ?? time();
    return (int)$admin['id'];
}

function hivenest_crm_workflow_table_exists(PDO $db, string $table): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table");
    $stmt->execute(['table' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function hivenest_crm_workflow_wait_for_client(
    PDO $db,
    int $serviceId,
    int $adminId,
    string $action,
    string $note,
    array $context = []
): void {
    if (
        !hivenest_crm_workflow_table_exists($db, 'crm_work_items')
        || !hivenest_crm_workflow_table_exists($db, 'provisioning_jobs')
    ) {
        return;
    }
    $itemsStmt = $db->prepare("
        SELECT wi.id, wi.provisioning_job_id, wi.work_status
        FROM crm_work_items wi
        INNER JOIN provisioning_jobs pj ON pj.id = wi.provisioning_job_id
        WHERE pj.service_id = :service_id
          AND pj.job_type IN ('design_queue','marketing_queue','manual_queue')
          AND wi.work_status NOT IN ('completed','cancelled')
    ");
    $itemsStmt->execute(['service_id' => $serviceId]);
    $items = $itemsStmt->fetchAll() ?: [];

    $db->prepare("
        UPDATE crm_work_items wi
        INNER JOIN provisioning_jobs pj ON pj.id = wi.provisioning_job_id
        SET wi.work_status = 'waiting_client',
            wi.staff_notes = :staff_notes,
            wi.completed_at = NULL
        WHERE pj.service_id = :service_id
          AND pj.job_type IN ('design_queue','marketing_queue','manual_queue')
          AND wi.work_status NOT IN ('completed','cancelled')
    ")->execute(['staff_notes' => $note, 'service_id' => $serviceId]);

    if (!$items || !hivenest_crm_workflow_table_exists($db, 'crm_work_item_history')) return;
    $historyStmt = $db->prepare("
        INSERT INTO crm_work_item_history
            (work_item_id, provisioning_job_id, admin_user_id, action, previous_values, new_values, note)
        VALUES
            (:work_item_id, :job_id, :admin_id, :action, :previous_values, :new_values, :note)
    ");
    foreach ($items as $item) {
        $historyStmt->execute([
            'work_item_id' => (int)$item['id'],
            'job_id' => (int)$item['provisioning_job_id'],
            'admin_id' => $adminId > 0 ? $adminId : null,
            'action' => $action,
            'previous_values' => json_encode(['work_status' => (string)$item['work_status']], JSON_UNESCAPED_SLASHES),
            'new_values' => json_encode(array_merge(['work_status' => 'waiting_client'], $context), JSON_UNESCAPED_SLASHES),
            'note' => $note,
        ]);
    }
}

function hivenest_crm_workflow_client_note(PDO $db, array $service, int $adminId, string $message): void
{
    if (!hivenest_crm_workflow_table_exists($db, 'customer_notes')) return;
    $message = hivenest_crm_workflow_clean($message, 5000);
    if ($message === '') return;
    try {
        $stmt = $db->prepare("
            INSERT INTO customer_notes
                (uuid, customer_id, author_type, author_admin_id, visibility, note_type, note_text)
            VALUES
                (:uuid, :customer_id, 'admin', :admin_id, 'client', 'account_update', :note_text)
        ");
        $stmt->execute([
            'uuid' => hivenest_crm_workflow_uuid(),
            'customer_id' => (int)$service['customer_id'],
            'admin_id' => $adminId,
            'note_text' => $message,
        ]);
    } catch (Throwable $e) {
        error_log('CRM workflow client note failed: ' . $e->getMessage());
    }
}

function hivenest_crm_workflow_upload(int $customerId): ?array
{
    if (empty($_FILES['file']) || (int)($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    $allowed = ['jpg','jpeg','png','gif','webp','svg','pdf','doc','docx','txt','zip','psd','ai'];
    $rootPath = __DIR__ . '/../uploads/workflow';
    $customerDir = $rootPath . DIRECTORY_SEPARATOR . 'customer_' . $customerId;
    $result = hivenest_secure_upload($_FILES['file'], $customerDir, 'uploads/workflow/customer_' . $customerId, $allowed, 25 * 1024 * 1024);
    if (!empty($result['error'])) hivenest_crm_workflow_out(422, ['error' => $result['error']]);
    return [
        'original' => $result['original_name'],
        'stored' => $result['stored_name'],
        'relative' => $result['relative_path'],
        'size' => $result['size'],
        'mime' => $result['mime'],
        'scan_status' => $result['scan_status'],
    ];
}

function hivenest_crm_workflow_default_stages(PDO $db, array $service): void
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
            'uuid' => hivenest_crm_workflow_uuid(),
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

$db = hivenest_db();
if (!$db) hivenest_crm_workflow_out(503, ['error' => 'CRM database is unavailable.']);
$adminId = hivenest_crm_workflow_admin_id($db);
if ($adminId <= 0) hivenest_crm_workflow_out(401, ['error' => 'Admin login required.']);
if (!hivenest_crm_workflow_table_exists($db, 'service_workflow_stages')) {
    hivenest_crm_workflow_out(503, ['error' => 'Service workflow schema is missing. Run Database/service_workflow.sql.']);
}

$serviceId = (int)($_GET['service_id'] ?? $_POST['service_id'] ?? $_GET['service'] ?? $_POST['service'] ?? 0);
if ($serviceId <= 0) hivenest_crm_workflow_out(422, ['error' => 'Service is required.']);

$serviceStmt = $db->prepare("
    SELECT s.*, c.email AS customer_email, c.first_name, c.last_name, c.company_name, p.name AS product_name, o.order_number
    FROM services s
    INNER JOIN customers c ON c.id = s.customer_id
    LEFT JOIN products p ON p.id = s.product_id
    LEFT JOIN orders o ON o.id = s.order_id
    WHERE s.id = :service_id
    LIMIT 1
");
$serviceStmt->execute(['service_id' => $serviceId]);
$service = $serviceStmt->fetch();
if (!$service) hivenest_crm_workflow_out(404, ['error' => 'Service not found.']);
hivenest_crm_workflow_default_stages($db, $service);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hivenest_crm_role_allows(hivenest_crm_admin_record($db, $adminId), 'workflow.manage')) {
        hivenest_crm_workflow_out(403, ['error' => 'Your staff role cannot change service workflows.']);
    }
    $action = hivenest_crm_workflow_clean((string)($_POST['action'] ?? ''), 40);
    $stageId = (int)($_POST['stage_id'] ?? 0);
    if ($stageId <= 0) hivenest_crm_workflow_out(422, ['error' => 'Stage is required.']);
    $stageStmt = $db->prepare('SELECT * FROM service_workflow_stages WHERE id = :id AND service_id = :service_id LIMIT 1');
    $stageStmt->execute(['id' => $stageId, 'service_id' => $serviceId]);
    $stage = $stageStmt->fetch();
    if (!$stage) hivenest_crm_workflow_out(404, ['error' => 'Stage not found.']);

    if ($action === 'update_stage') {
        $status = hivenest_crm_workflow_clean((string)($_POST['status'] ?? 'pending'), 40);
        $allowed = ['pending','in_progress','ready_for_review','changes_requested','approved','completed'];
        if (!in_array($status, $allowed, true)) hivenest_crm_workflow_out(422, ['error' => 'Invalid stage status.']);
        $title = hivenest_crm_workflow_clean((string)($_POST['title'] ?? $stage['title']), 180);
        $description = hivenest_crm_workflow_clean((string)($_POST['description'] ?? $stage['description']), 3000);
        $visible = !empty($_POST['visible_to_customer']) ? 1 : 0;
        $update = $db->prepare('UPDATE service_workflow_stages SET title = :title, description = :description, status = :status, visible_to_customer = :visible WHERE id = :id');
        $update->execute(['title' => $title, 'description' => $description, 'status' => $status, 'visible' => $visible, 'id' => $stageId]);
        if ($visible === 1 && $status === 'ready_for_review') {
            hivenest_crm_workflow_client_note(
                $db,
                $service,
                $adminId,
                sprintf(
                    '%s is ready for review on %s. Open your client portal workflow to view it and leave feedback.',
                    $title,
                    (string)($service['service_name'] ?? 'your service')
                )
            );
            hivenest_crm_workflow_wait_for_client(
                $db,
                $serviceId,
                $adminId,
                'workflow_stage_ready_for_review',
                'Workflow stage sent to client for review.',
                ['stage_id' => $stageId, 'stage_title' => $title]
            );
            try {
                hivenest_notify_customer(
                    $db,
                    (int)$service['customer_id'],
                    'urgent',
                    'Workflow stage ready for review',
                    $title . ' is ready on ' . (string)($service['service_name'] ?? 'your service') . '.',
                    '/services/workflow.html?service=' . $serviceId,
                    'service_workflow_stage',
                    $stageId
                );
            } catch (Throwable $e) {
                error_log('Client workflow stage notification failed: ' . $e->getMessage());
            }
        }
        hivenest_crm_workflow_out(200, ['ok' => true, 'message' => 'Stage updated.']);
    }

    if ($action === 'comment' || $action === 'deliverable') {
        $comment = hivenest_crm_workflow_clean((string)($_POST['comment'] ?? $_POST['notes'] ?? ''), 5000);
        if ($action === 'comment' && $comment === '') hivenest_crm_workflow_out(422, ['error' => 'Comment is required.']);
        if ($comment !== '') {
            $insertComment = $db->prepare("
                INSERT INTO service_workflow_comments (uuid, stage_id, service_id, customer_id, author_type, author_admin_id, comment_text)
                VALUES (:uuid, :stage_id, :service_id, :customer_id, 'admin', :author_admin_id, :comment_text)
            ");
            $insertComment->execute([
                'uuid' => hivenest_crm_workflow_uuid(),
                'stage_id' => $stageId,
                'service_id' => $serviceId,
                'customer_id' => (int)$service['customer_id'],
                'author_admin_id' => $adminId,
                'comment_text' => $comment,
            ]);
            if ($action === 'comment') {
                try {
                    hivenest_notify_customer(
                        $db,
                        (int)$service['customer_id'],
                        'info',
                        'New message from the HiveNest team',
                        (string)($service['service_name'] ?? 'Service') . ' — ' . (string)($stage['title'] ?? 'Workflow update'),
                        '/services/workflow.html?service=' . $serviceId,
                        'service_workflow_stage',
                        $stageId
                    );
                } catch (Throwable $e) {
                    error_log('Client workflow comment notification failed: ' . $e->getMessage());
                }
            }
        }
        if ($action === 'deliverable') {
            $upload = hivenest_crm_workflow_upload((int)$service['customer_id']);
            $title = hivenest_crm_workflow_clean((string)($_POST['title'] ?? 'Deliverable'), 180);
            $visible = !empty($_POST['visible_to_customer']) ? 1 : 0;
            $isFinal = !empty($_POST['is_final']) ? 1 : 0;
            $insert = $db->prepare("
                INSERT INTO service_workflow_deliverables
                    (uuid, stage_id, service_id, customer_id, uploaded_by_type, uploaded_by_admin_id, title, notes, file_original_name, file_stored_name, file_relative_path, file_size, mime_type, is_final, visible_to_customer)
                VALUES
                    (:uuid, :stage_id, :service_id, :customer_id, 'admin', :admin_id, :title, :notes, :original, :stored, :relative, :size, :mime, :is_final, :visible)
            ");
            $insert->execute([
                'uuid' => hivenest_crm_workflow_uuid(),
                'stage_id' => $stageId,
                'service_id' => $serviceId,
                'customer_id' => (int)$service['customer_id'],
                'admin_id' => $adminId,
                'title' => $title,
                'notes' => $comment !== '' ? $comment : null,
                'original' => $upload['original'] ?? null,
                'stored' => $upload['stored'] ?? null,
                'relative' => $upload['relative'] ?? null,
                'size' => $upload['size'] ?? null,
                'mime' => $upload['mime'] ?? null,
                'is_final' => $isFinal,
                'visible' => $visible,
            ]);
            $deliverableId = (int)$db->lastInsertId();
            $db->prepare("UPDATE service_workflow_stages SET status = 'ready_for_review' WHERE id = :id")->execute(['id' => $stageId]);
            hivenest_crm_workflow_wait_for_client(
                $db,
                $serviceId,
                $adminId,
                'workflow_deliverable_uploaded',
                'Deliverable uploaded and waiting for client review.',
                ['stage_id' => $stageId, 'stage_title' => $title, 'is_final' => $isFinal]
            );
            if ($visible === 1) {
                hivenest_crm_workflow_client_note(
                    $db,
                    $service,
                    $adminId,
                    sprintf(
                        'New deliverable uploaded for %s: %s. Open your client portal workflow to review it.',
                        (string)($service['service_name'] ?? 'your service'),
                        $title !== '' ? $title : (string)($stage['title'] ?? 'Deliverable')
                    )
                );
                try {
                    hivenest_notify_customer(
                        $db,
                        (int)$service['customer_id'],
                        'urgent',
                        $isFinal === 1 ? 'Final deliverable uploaded' : 'New deliverable uploaded',
                        (string)($service['service_name'] ?? 'Service') . ' — ' . ($title !== '' ? $title : 'Deliverable'),
                        '/services/workflow.html?service=' . $serviceId,
                        'service_workflow_deliverable',
                        $deliverableId
                    );
                } catch (Throwable $e) {
                    error_log('Client deliverable notification failed: ' . $e->getMessage());
                }
            }
        }
        hivenest_crm_workflow_out(200, ['ok' => true, 'message' => 'Workflow updated.']);
    }

    hivenest_crm_workflow_out(422, ['error' => 'Unknown workflow action.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') hivenest_crm_workflow_out(405, ['error' => 'Method not allowed.']);

$stageStmt = $db->prepare('SELECT * FROM service_workflow_stages WHERE service_id = :service_id ORDER BY display_order ASC, id ASC');
$stageStmt->execute(['service_id' => $serviceId]);
$stages = $stageStmt->fetchAll() ?: [];
$ids = array_map(static fn($row) => (int)$row['id'], $stages);
$deliverables = [];
$comments = [];
if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $d = $db->prepare("SELECT * FROM service_workflow_deliverables WHERE stage_id IN ({$placeholders}) ORDER BY id ASC");
    $d->execute($ids);
    foreach ($d->fetchAll() ?: [] as $row) $deliverables[(int)$row['stage_id']][] = $row;
    $c = $db->prepare("SELECT * FROM service_workflow_comments WHERE stage_id IN ({$placeholders}) ORDER BY id ASC");
    $c->execute($ids);
    foreach ($c->fetchAll() ?: [] as $row) $comments[(int)$row['stage_id']][] = $row;
}

$payloadStages = [];
foreach ($stages as $stage) {
    $payloadStages[] = [
        'id' => (int)$stage['id'],
        'title' => (string)$stage['title'],
        'description' => $stage['description'] ?? null,
        'status' => (string)$stage['status'],
        'display_order' => (int)$stage['display_order'],
        'visible_to_customer' => (int)$stage['visible_to_customer'] === 1,
        'deliverables' => array_values($deliverables[(int)$stage['id']] ?? []),
        'comments' => array_values($comments[(int)$stage['id']] ?? []),
    ];
}

$name = trim((string)($service['first_name'] ?? '') . ' ' . (string)($service['last_name'] ?? ''));
hivenest_crm_workflow_out(200, [
    'service' => [
        'id' => (int)$service['id'],
        'service_name' => (string)$service['service_name'],
        'service_type' => (string)$service['service_type'],
        'domain_name' => $service['domain_name'] ?? null,
        'service_status' => (string)$service['service_status'],
        'product_name' => $service['product_name'] ?? null,
        'order_number' => $service['order_number'] ?? null,
        'customer' => [
            'id' => (int)$service['customer_id'],
            'name' => $service['company_name'] ?: ($name !== '' ? $name : (string)$service['customer_email']),
            'email' => (string)$service['customer_email'],
        ],
    ],
    'stages' => $payloadStages,
]);
