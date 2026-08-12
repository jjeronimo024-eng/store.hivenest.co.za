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

function hivenest_crm_onboarding_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_crm_onboarding_authed(): bool
{
    return !empty($_SESSION['admin_user']) && !empty($_SESSION['admin_login_time']);
}

function hivenest_crm_onboarding_env(string $key, string $default = ''): string
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
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        return $value;
    }
    return $default;
}

function hivenest_crm_onboarding_b64url_decode(string $value): string|false
{
    $remainder = strlen($value) % 4;
    if ($remainder) $value .= str_repeat('=', 4 - $remainder);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function hivenest_crm_onboarding_b64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function hivenest_crm_onboarding_bearer_token(): string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(.+)/i', $header, $match)) return trim($match[1]);
    return trim((string)($_COOKIE['hivenest_admin_access_token'] ?? ''));
}

function hivenest_crm_onboarding_verify_admin_jwt(PDO $db): bool
{
    $token = hivenest_crm_onboarding_bearer_token();
    if ($token === '') return false;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    [$header64, $payload64, $signature64] = $parts;
    $headerJson = hivenest_crm_onboarding_b64url_decode($header64);
    $payloadJson = hivenest_crm_onboarding_b64url_decode($payload64);
    if ($headerJson === false || $payloadJson === false) return false;
    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);
    if (!is_array($header) || !is_array($payload)) return false;
    if (($header['alg'] ?? '') !== hivenest_crm_onboarding_env('JWT_ALGORITHM', 'HS256')) return false;
    if (($payload['user_type'] ?? '') !== 'admin') return false;
    if (!empty($payload['exp']) && (int)$payload['exp'] < time()) return false;

    $secret = hivenest_crm_onboarding_env('JWT_SECRET_KEY');
    if ($secret === '') return false;
    $expected = hivenest_crm_onboarding_b64url_encode(hash_hmac('sha256', $header64 . '.' . $payload64, $secret, true));
    if (!hash_equals($expected, $signature64)) return false;

    $adminId = (int)($payload['sub'] ?? 0);
    if ($adminId <= 0) return false;
    $stmt = $db->prepare('SELECT id, username, email, role, is_active FROM admin_users WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => $adminId]);
    $admin = $stmt->fetch();
    if (!$admin) return false;

    $_SESSION['admin_user'] = [
        'id' => (int)$admin['id'],
        'username' => (string)$admin['username'],
        'email' => (string)$admin['email'],
        'role' => (string)($admin['role'] ?? 'admin'),
    ];
    $_SESSION['admin_login_time'] = $_SESSION['admin_login_time'] ?? time();
    $_SESSION['admin_last_seen'] = time();
    return true;
}

function hivenest_crm_onboarding_table_exists(PDO $db, string $table): bool
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

function hivenest_crm_onboarding_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function hivenest_crm_onboarding_prepare_workflow(PDO $db, int $onboardingId): bool
{
    if (
        !hivenest_crm_onboarding_table_exists($db, 'service_workflow_stages')
        || !hivenest_crm_onboarding_table_exists($db, 'services')
    ) {
        return false;
    }

    $stmt = $db->prepare("
        SELECT
            cso.id,
            cso.service_id,
            cso.customer_id,
            cso.order_id,
            cso.payload,
            s.service_type,
            s.service_name
        FROM customer_service_onboarding cso
        INNER JOIN services s ON s.id = cso.service_id
        WHERE cso.id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $onboardingId]);
    $row = $stmt->fetch();
    if (!$row || (int)($row['service_id'] ?? 0) <= 0) return false;

    $count = $db->prepare('SELECT COUNT(*) FROM service_workflow_stages WHERE service_id = :service_id');
    $count->execute(['service_id' => (int)$row['service_id']]);
    if ((int)$count->fetchColumn() > 0) return true;

    $payload = json_decode((string)($row['payload'] ?? ''), true);
    if (!is_array($payload)) $payload = [];
    $type = strtolower((string)($row['service_type'] ?? $payload['project_type'] ?? ''));
    $isMarketing = str_contains($type, 'marketing') || str_contains($type, 'seo') || str_contains($type, 'campaign');

    $stages = $isMarketing
        ? [
            ['campaign_brief', 'Campaign Brief', 'CRM reviews goals, audience, channels, content, and campaign assets.'],
            ['strategy_draft', 'Strategy Draft', 'First campaign strategy, copy direction, or SEO/marketing plan is shared for review.'],
            ['asset_review', 'Asset Review', 'Campaign graphics, copy, keywords, ads, or content assets are shared for feedback.'],
            ['revision_window', 'Revision Window', 'Client comments and requested campaign changes are processed here.'],
            ['final_launch', 'Final Launch Pack', 'Final campaign assets, launch notes, reports, and next steps are delivered.'],
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
            (uuid, service_id, customer_id, order_id, stage_key, title, description, status, display_order, visible_to_customer)
        VALUES
            (:uuid, :service_id, :customer_id, :order_id, :stage_key, :title, :description, :status, :display_order, 1)
    ");
    foreach ($stages as $index => $stage) {
        $insert->execute([
            'uuid' => hivenest_crm_onboarding_uuid(),
            'service_id' => (int)$row['service_id'],
            'customer_id' => (int)$row['customer_id'],
            'order_id' => $row['order_id'] ? (int)$row['order_id'] : null,
            'stage_key' => $stage[0],
            'title' => $stage[1],
            'description' => $stage[2],
            'status' => $index === 0 ? 'in_progress' : 'pending',
            'display_order' => $index + 1,
        ]);
    }

    return true;
}

$db = hivenest_db();
if (!$db) {
    hivenest_crm_onboarding_out(503, ['error' => 'CRM database is unavailable.']);
}
if (!hivenest_crm_onboarding_authed() && !hivenest_crm_onboarding_verify_admin_jwt($db)) {
    hivenest_crm_onboarding_out(401, ['error' => 'Admin login required.']);
}
if (!hivenest_crm_onboarding_table_exists($db, 'customer_service_onboarding')) {
    hivenest_crm_onboarding_out(200, ['submissions' => [], 'message' => 'Onboarding table has not been created yet.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = trim((string) ($_GET['status'] ?? ''));
    $where = $status !== '' ? 'WHERE cso.status = :status' : '';
    $stmt = $db->prepare("
        SELECT
            cso.*,
            c.email AS customer_email,
            c.first_name,
            c.last_name,
            c.company_name,
            s.service_name,
            s.service_type,
            s.domain_name,
            o.order_number
        FROM customer_service_onboarding cso
        INNER JOIN customers c ON c.id = cso.customer_id
        LEFT JOIN services s ON s.id = cso.service_id
        LEFT JOIN orders o ON o.id = cso.order_id
        {$where}
        ORDER BY
            CASE cso.status
                WHEN 'submitted' THEN 1
                WHEN 'in_review' THEN 2
                WHEN 'needs_more_info' THEN 3
                WHEN 'accepted' THEN 4
                ELSE 5
            END,
            cso.id DESC
        LIMIT 100
    ");
    $stmt->execute($status !== '' ? ['status' => $status] : []);
    $rows = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        $files = json_decode((string) ($row['uploaded_files'] ?? ''), true);
        $rows[] = [
            'id' => (int) $row['id'],
            'uuid' => (string) $row['uuid'],
            'status' => (string) $row['status'],
            'onboarding_type' => (string) $row['onboarding_type'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
            'customer' => [
                'email' => (string) $row['customer_email'],
                'name' => trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')),
                'company_name' => $row['company_name'] ?? null,
            ],
            'service' => [
                'id' => $row['service_id'] !== null ? (int)$row['service_id'] : null,
                'name' => $row['service_name'] ?? null,
                'type' => $row['service_type'] ?? null,
                'domain' => $row['domain_name'] ?? null,
            ],
            'order_number' => $row['order_number'] ?? null,
            'payload' => is_array($payload) ? $payload : [],
            'files' => is_array($files) ? $files : [],
        ];
    }
    hivenest_crm_onboarding_out(200, ['submissions' => $rows]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin = hivenest_crm_admin_record($db, (int)($_SESSION['admin_user']['id'] ?? 0));
    if (!hivenest_crm_role_allows($admin, 'workflow.manage')) {
        hivenest_crm_onboarding_out(403, ['error' => 'Your staff role cannot change onboarding submissions.']);
    }
    $input = json_decode((string) file_get_contents('php://input'), true) ?: [];
    $id = (int) ($input['id'] ?? 0);
    $status = (string) ($input['status'] ?? '');
    $allowed = ['submitted','in_review','needs_more_info','accepted','completed'];
    if ($id <= 0 || !in_array($status, $allowed, true)) {
        hivenest_crm_onboarding_out(422, ['error' => 'Valid onboarding id and status are required.']);
    }
    $currentStmt = $db->prepare('SELECT status FROM customer_service_onboarding WHERE id = :id LIMIT 1');
    $currentStmt->execute(['id' => $id]);
    $previousOnboardingStatus = $currentStmt->fetchColumn();
    if ($previousOnboardingStatus === false) {
        hivenest_crm_onboarding_out(404, ['error' => 'Onboarding submission not found.']);
    }
    $previousQueueItems = [];
    if (hivenest_crm_onboarding_table_exists($db, 'crm_work_items') && hivenest_crm_onboarding_table_exists($db, 'provisioning_jobs')) {
        $previousQueueStmt = $db->prepare("
            SELECT wi.id, wi.provisioning_job_id, wi.work_status
            FROM crm_work_items wi
            INNER JOIN provisioning_jobs pj ON pj.id = wi.provisioning_job_id
            INNER JOIN customer_service_onboarding cso ON cso.order_item_id = pj.order_item_id
            WHERE cso.id = :id
              AND pj.job_type IN ('design_queue','marketing_queue','manual_queue')
              AND wi.work_status NOT IN ('completed','cancelled')
        ");
        $previousQueueStmt->execute(['id' => $id]);
        $previousQueueItems = $previousQueueStmt->fetchAll() ?: [];
    }
    $stmt = $db->prepare("UPDATE customer_service_onboarding SET status = :status WHERE id = :id");
    $stmt->execute(['status' => $status, 'id' => $id]);

    if (hivenest_crm_onboarding_table_exists($db, 'crm_work_items') && hivenest_crm_onboarding_table_exists($db, 'provisioning_jobs')) {
        $workStatus = match ($status) {
            'needs_more_info' => 'waiting_client',
            'submitted' => 'todo',
            default => 'in_progress',
        };
        $note = match ($status) {
            'needs_more_info' => 'Onboarding reviewed: more client information is required.',
            'accepted', 'completed' => 'Onboarding accepted. Proceed with service workflow/deliverables.',
            'in_review' => 'Onboarding is under CRM review.',
            default => 'Client onboarding submitted. Review brief and uploads.',
        };
        $sync = $db->prepare("
            UPDATE crm_work_items wi
            INNER JOIN provisioning_jobs pj ON pj.id = wi.provisioning_job_id
            INNER JOIN customer_service_onboarding cso ON cso.order_item_id = pj.order_item_id
            SET wi.work_status = :work_status,
                wi.staff_notes = :staff_notes,
                wi.completed_at = NULL
            WHERE cso.id = :id
              AND pj.job_type IN ('design_queue','marketing_queue','manual_queue')
              AND wi.work_status NOT IN ('completed','cancelled')
        ");
        $sync->execute([
            'work_status' => $workStatus,
            'staff_notes' => $note,
            'id' => $id,
        ]);
        if (
            (string)$previousOnboardingStatus !== $status
            && $previousQueueItems
            && hivenest_crm_onboarding_table_exists($db, 'crm_work_item_history')
        ) {
            $historyStmt = $db->prepare("
                INSERT INTO crm_work_item_history
                    (work_item_id, provisioning_job_id, admin_user_id, action, previous_values, new_values, note)
                VALUES
                    (:work_item_id, :job_id, :admin_id, 'onboarding_status_updated', :previous_values, :new_values, :note)
            ");
            foreach ($previousQueueItems as $queueItem) {
                $historyStmt->execute([
                    'work_item_id' => (int)$queueItem['id'],
                    'job_id' => (int)$queueItem['provisioning_job_id'],
                    'admin_id' => (int)($_SESSION['admin_user']['id'] ?? 0) ?: null,
                    'previous_values' => json_encode([
                        'onboarding_status' => (string)$previousOnboardingStatus,
                        'work_status' => (string)$queueItem['work_status'],
                    ], JSON_UNESCAPED_SLASHES),
                    'new_values' => json_encode([
                        'onboarding_status' => $status,
                        'work_status' => $workStatus,
                    ], JSON_UNESCAPED_SLASHES),
                    'note' => $note,
                ]);
            }
        }
    }

    $workflowPrepared = false;
    if (in_array($status, ['accepted', 'completed'], true)) {
        $workflowPrepared = hivenest_crm_onboarding_prepare_workflow($db, $id);
    }

    if ((string)$previousOnboardingStatus !== $status) {
        try {
            $notificationStmt = $db->prepare("
                SELECT cso.customer_id, cso.service_id, s.service_name
                FROM customer_service_onboarding cso
                LEFT JOIN services s ON s.id = cso.service_id
                WHERE cso.id = :id
                LIMIT 1
            ");
            $notificationStmt->execute(['id' => $id]);
            $notificationRow = $notificationStmt->fetch() ?: [];
            $customerId = (int)($notificationRow['customer_id'] ?? 0);
            $serviceId = (int)($notificationRow['service_id'] ?? 0);
            $serviceName = (string)($notificationRow['service_name'] ?? 'Your service');
            if ($customerId > 0) {
                $message = match ($status) {
                    'needs_more_info' => $serviceName . ' needs more onboarding information from you.',
                    'accepted' => $serviceName . ' onboarding has been accepted and production can begin.',
                    'completed' => $serviceName . ' onboarding is complete.',
                    'in_review' => $serviceName . ' onboarding is being reviewed.',
                    default => $serviceName . ' onboarding status is now ' . str_replace('_', ' ', $status) . '.',
                };
                $link = in_array($status, ['accepted', 'completed'], true)
                    ? '/services/workflow.html?service=' . $serviceId
                    : '/services/onboarding.html?service=' . $serviceId;
                hivenest_notify_customer(
                    $db,
                    $customerId,
                    $status === 'needs_more_info' ? 'urgent' : 'info',
                    'Onboarding status updated',
                    $message,
                    $link,
                    'client_onboarding',
                    $id
                );
            }
        } catch (Throwable $e) {
            error_log('Client onboarding status notification failed: ' . $e->getMessage());
        }
    }

    hivenest_crm_onboarding_out(200, [
        'success' => true,
        'message' => $workflowPrepared
            ? 'Onboarding status updated and workflow stages prepared.'
            : 'Onboarding status updated.',
        'workflow_prepared' => $workflowPrepared,
    ]);
}

hivenest_crm_onboarding_out(405, ['error' => 'Method not allowed.']);
