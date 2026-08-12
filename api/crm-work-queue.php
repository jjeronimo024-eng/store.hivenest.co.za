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
require_once __DIR__ . '/../utilities/myorderbox_bridge.php';
require_once __DIR__ . '/../utilities/support_notifications.php';
require_once __DIR__ . '/../utilities/crm_notifications.php';

function hivenest_crm_work_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_crm_work_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function hivenest_crm_work_clean(string $value, int $max = 5000): string
{
    $value = trim(str_replace("\0", '', $value));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function hivenest_crm_work_env(string $key, string $default = ''): string
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

function hivenest_crm_work_b64url_decode(string $value): string|false
{
    $remainder = strlen($value) % 4;
    if ($remainder) $value .= str_repeat('=', 4 - $remainder);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function hivenest_crm_work_b64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function hivenest_crm_work_bearer_token(): string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(.+)/i', $header, $match)) return trim($match[1]);
    return trim((string)($_COOKIE['hivenest_admin_access_token'] ?? ''));
}

function hivenest_crm_work_admin(PDO $db): array
{
    if (!empty($_SESSION['admin_user']) && !empty($_SESSION['admin_login_time'])) return $_SESSION['admin_user'];
    $token = hivenest_crm_work_bearer_token();
    if ($token === '') return [];
    $parts = explode('.', $token);
    if (count($parts) !== 3) return [];
    [$header64, $payload64, $signature64] = $parts;
    $headerJson = hivenest_crm_work_b64url_decode($header64);
    $payloadJson = hivenest_crm_work_b64url_decode($payload64);
    if ($headerJson === false || $payloadJson === false) return [];
    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);
    if (!is_array($header) || !is_array($payload)) return [];
    if (($header['alg'] ?? '') !== hivenest_crm_work_env('JWT_ALGORITHM', 'HS256')) return [];
    if (($payload['user_type'] ?? '') !== 'admin') return [];
    if (!empty($payload['exp']) && (int)$payload['exp'] < time()) return [];
    $secret = hivenest_crm_work_env('JWT_SECRET_KEY');
    if ($secret === '') return [];
    $expected = hivenest_crm_work_b64url_encode(hash_hmac('sha256', $header64 . '.' . $payload64, $secret, true));
    if (!hash_equals($expected, $signature64)) return [];
    $adminId = (int)($payload['sub'] ?? 0);
    if ($adminId <= 0) return [];
    $stmt = $db->prepare('SELECT id, username, email, role FROM admin_users WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => $adminId]);
    $admin = $stmt->fetch() ?: [];
    if (!$admin) return [];
    $_SESSION['admin_user'] = ['id' => (int)$admin['id'], 'username' => (string)$admin['username'], 'email' => (string)$admin['email'], 'role' => (string)($admin['role'] ?? 'admin')];
    $_SESSION['admin_login_time'] = $_SESSION['admin_login_time'] ?? time();
    return $_SESSION['admin_user'];
}

function hivenest_crm_work_table_exists(PDO $db, string $table): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
    $stmt->execute(['table' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function hivenest_crm_work_ensure(PDO $db): void
{
    if (!hivenest_crm_work_table_exists($db, 'crm_work_items')) {
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
    $db->exec("
        CREATE TABLE IF NOT EXISTS crm_work_item_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            work_item_id INT NOT NULL,
            provisioning_job_id INT NOT NULL,
            admin_user_id INT NULL,
            action VARCHAR(40) NOT NULL,
            previous_values LONGTEXT NULL,
            new_values LONGTEXT NULL,
            note TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_crm_work_history_item (work_item_id, created_at),
            INDEX idx_crm_work_history_job (provisioning_job_id),
            INDEX idx_crm_work_history_admin (admin_user_id),
            CONSTRAINT fk_crm_work_history_item FOREIGN KEY (work_item_id) REFERENCES crm_work_items(id) ON DELETE CASCADE,
            CONSTRAINT fk_crm_work_history_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function hivenest_crm_work_payload(?string $value): array
{
    if ($value === null || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function hivenest_crm_work_log(
    PDO $db,
    int $workItemId,
    int $jobId,
    int $adminId,
    string $action,
    array $previousValues = [],
    array $newValues = [],
    ?string $note = null
): void {
    $stmt = $db->prepare("
        INSERT INTO crm_work_item_history
            (work_item_id, provisioning_job_id, admin_user_id, action, previous_values, new_values, note)
        VALUES
            (:work_item_id, :job_id, :admin_id, :action, :previous_values, :new_values, :note)
    ");
    $stmt->execute([
        'work_item_id' => $workItemId,
        'job_id' => $jobId,
        'admin_id' => $adminId > 0 ? $adminId : null,
        'action' => $action,
        'previous_values' => $previousValues ? json_encode($previousValues, JSON_UNESCAPED_SLASHES) : null,
        'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_SLASHES) : null,
        'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
    ]);
}

function hivenest_crm_work_backfill_history(PDO $db): void
{
    $db->exec("
        INSERT INTO crm_work_item_history
            (work_item_id, provisioning_job_id, admin_user_id, action, previous_values, new_values, note)
        SELECT
            wi.id,
            wi.provisioning_job_id,
            NULL,
            'queue_tracking_started',
            NULL,
            JSON_OBJECT(
                'work_status', wi.work_status,
                'priority', wi.priority,
                'assigned_to', wi.assigned_to,
                'due_at', wi.due_at
            ),
            'Audit tracking baseline created for an existing work item.'
        FROM crm_work_items wi
        WHERE NOT EXISTS (
            SELECT 1
            FROM crm_work_item_history h
            WHERE h.work_item_id = wi.id
        )
    ");
}

$db = hivenest_db();
if (!$db) hivenest_crm_work_out(503, ['error' => 'CRM database is unavailable.']);
$admin = hivenest_crm_work_admin($db);
if (!$admin) hivenest_crm_work_out(401, ['error' => 'Admin login required.']);
hivenest_crm_work_ensure($db);
hivenest_crm_work_backfill_history($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hivenest_crm_role_allows($admin, 'work_queue.manage')) {
        hivenest_crm_work_out(403, ['error' => 'Your staff role cannot change work-queue items.']);
    }
    $action = hivenest_crm_work_clean((string)($_POST['action'] ?? ''), 40);
    $jobId = (int)($_POST['job_id'] ?? 0);
    if ($jobId <= 0) hivenest_crm_work_out(422, ['error' => 'Job ID is required.']);

    $jobStmt = $db->prepare('SELECT * FROM provisioning_jobs WHERE id = :id LIMIT 1');
    $jobStmt->execute(['id' => $jobId]);
    $job = $jobStmt->fetch();
    if (!$job) hivenest_crm_work_out(404, ['error' => 'Provisioning job not found.']);
    $jobPayload = hivenest_crm_work_payload($job['request_payload'] ?? null);
    $isServiceRequestJob = ($jobPayload['source'] ?? '') === 'customer_service_request';

    $db->prepare("INSERT IGNORE INTO crm_work_items (uuid, provisioning_job_id) VALUES (:uuid, :job_id)")
        ->execute(['uuid' => hivenest_crm_work_uuid(), 'job_id' => $jobId]);

    if ($action === 'assign_to_me') {
        $claimStmt = $db->prepare("
            UPDATE crm_work_items
            SET assigned_to = :admin_id
            WHERE provisioning_job_id = :job_id
              AND assigned_to IS NULL
        ");
        $claimStmt->execute([
            'admin_id' => (int)$admin['id'],
            'job_id' => $jobId,
        ]);
        if ($claimStmt->rowCount() === 0) {
            $ownerStmt = $db->prepare("
                SELECT wi.assigned_to, au.username
                FROM crm_work_items wi
                LEFT JOIN admin_users au ON au.id = wi.assigned_to
                WHERE wi.provisioning_job_id = :job_id
                LIMIT 1
            ");
            $ownerStmt->execute(['job_id' => $jobId]);
            $owner = $ownerStmt->fetch() ?: [];
            if ((int)($owner['assigned_to'] ?? 0) === (int)$admin['id']) {
                hivenest_crm_work_out(200, ['ok' => true, 'message' => 'This work item is already assigned to you.']);
            }
            hivenest_crm_work_out(409, [
                'error' => 'This work item has already been assigned to ' . ((string)($owner['username'] ?? '') ?: 'another staff member') . '.',
            ]);
        }
        $claimedItemStmt = $db->prepare('SELECT id FROM crm_work_items WHERE provisioning_job_id = :job_id LIMIT 1');
        $claimedItemStmt->execute(['job_id' => $jobId]);
        $claimedItemId = (int)$claimedItemStmt->fetchColumn();
        if ($claimedItemId > 0) {
            hivenest_crm_work_log(
                $db,
                $claimedItemId,
                $jobId,
                (int)$admin['id'],
                'assigned_to_self',
                ['assigned_to' => null],
                ['assigned_to' => (int)$admin['id']]
            );
        }
        hivenest_crm_work_out(200, [
            'ok' => true,
            'message' => 'Work item assigned to you.',
        ]);
    }

    if ($action === 'update_work') {
        $previousStmt = $db->prepare('SELECT * FROM crm_work_items WHERE provisioning_job_id = :job_id LIMIT 1');
        $previousStmt->execute(['job_id' => $jobId]);
        $previousWork = $previousStmt->fetch() ?: [];
        $previousAssignedTo = (int)($previousWork['assigned_to'] ?? 0);
        $priority = hivenest_crm_work_clean((string)($_POST['priority'] ?? 'normal'), 20);
        $workStatus = hivenest_crm_work_clean((string)($_POST['work_status'] ?? 'todo'), 30);
        $assignedTo = (int)($_POST['assigned_to'] ?? 0);
        $staffNotes = hivenest_crm_work_clean((string)($_POST['staff_notes'] ?? ''), 5000);
        $dueAt = hivenest_crm_work_clean((string)($_POST['due_at'] ?? ''), 30);
        if (!in_array($priority, ['low','normal','high','urgent'], true)) $priority = 'normal';
        if (!in_array($workStatus, ['todo','in_progress','waiting_client','waiting_provider','completed','cancelled'], true)) $workStatus = 'todo';
        if ($isServiceRequestJob && in_array($workStatus, ['completed', 'cancelled'], true)) {
            hivenest_crm_work_out(422, ['error' => 'Use Manage Request to complete, reject, or cancel a customer service request.']);
        }
        $dueValue = $dueAt !== '' ? str_replace('T', ' ', $dueAt) : null;
        if ($dueValue !== null && strlen($dueValue) === 16) $dueValue .= ':00';
        $newWorkValues = [
            'assigned_to' => $assignedTo > 0 ? $assignedTo : null,
            'priority' => $priority,
            'work_status' => $workStatus,
            'staff_notes' => $staffNotes !== '' ? $staffNotes : null,
            'due_at' => $dueValue,
        ];
        $previousWorkValues = [
            'assigned_to' => !empty($previousWork['assigned_to']) ? (int)$previousWork['assigned_to'] : null,
            'priority' => (string)($previousWork['priority'] ?? 'normal'),
            'work_status' => (string)($previousWork['work_status'] ?? 'todo'),
            'staff_notes' => $previousWork['staff_notes'] ?? null,
            'due_at' => $previousWork['due_at'] ?? null,
        ];
        $db->prepare("
            UPDATE crm_work_items
            SET assigned_to = :assigned_to,
                priority = :priority,
                work_status = :work_status,
                staff_notes = :staff_notes,
                due_at = :due_at,
                completed_at = CASE WHEN :work_status_done = 'completed' THEN NOW() ELSE completed_at END
            WHERE provisioning_job_id = :job_id
        ")->execute([
            'assigned_to' => $assignedTo > 0 ? $assignedTo : null,
            'priority' => $priority,
            'work_status' => $workStatus,
            'staff_notes' => $staffNotes !== '' ? $staffNotes : null,
            'due_at' => $dueValue,
            'work_status_done' => $workStatus,
            'job_id' => $jobId,
        ]);
        if ($newWorkValues !== $previousWorkValues && !empty($previousWork['id'])) {
            hivenest_crm_work_log(
                $db,
                (int)$previousWork['id'],
                $jobId,
                (int)$admin['id'],
                'work_item_updated',
                $previousWorkValues,
                $newWorkValues,
                $staffNotes
            );
        }
        $assignmentEmailSent = null;
        $assignmentNotificationCreated = null;
        if ($assignedTo > 0 && $assignedTo !== $previousAssignedTo) {
            $assignmentEmailSent = hivenest_work_queue_notify_assignment(
                $db,
                $job,
                $jobPayload,
                $assignedTo,
                (string)($admin['username'] ?? 'HiveNest CRM')
            );
            if (!$assignmentEmailSent) {
                error_log('CRM assignment email was not sent for provisioning job #' . $jobId);
            }
            try {
                $workLabel = (string)(
                    $jobPayload['product_name']
                    ?? $jobPayload['service_name']
                    ?? $jobPayload['domain_name']
                    ?? ucwords(str_replace('_', ' ', (string)($job['job_type'] ?? 'work item')))
                );
                $notificationId = hivenest_crm_notify_admin(
                    $db,
                    $assignedTo,
                    $priority === 'urgent' ? 'urgent' : 'info',
                    'Work assigned to you',
                    $workLabel . ($dueValue ? ' · Due ' . $dueValue : ''),
                    '/work-queue/?assigned=my&q=' . rawurlencode($workLabel),
                    'provisioning_job',
                    $jobId
                );
                $assignmentNotificationCreated = $notificationId > 0;
            } catch (Throwable $e) {
                $assignmentNotificationCreated = false;
                error_log('CRM assignment in-app notification failed for provisioning job #' . $jobId . ': ' . $e->getMessage());
            }
        }
        hivenest_crm_work_out(200, [
            'ok' => true,
            'message' => 'Work item updated.',
            'assignment_email_sent' => $assignmentEmailSent,
            'assignment_notification_created' => $assignmentNotificationCreated,
        ]);
    }

    if ($action === 'complete_team_job') {
        if ($isServiceRequestJob) {
            hivenest_crm_work_out(422, ['error' => 'Use Manage Request to resolve this customer service request so the client portal is updated too.']);
        }
        if (!in_array((string)$job['job_type'], ['design_queue','marketing_queue','manual_queue'], true) && (string)$job['provider'] !== 'hivenest_team') {
            hivenest_crm_work_out(422, ['error' => 'Only HiveNest team jobs can be completed from the CRM queue. Provider jobs must be completed from Provisioning Monitor.']);
        }
        $note = hivenest_crm_work_clean((string)($_POST['staff_notes'] ?? ''), 5000);
        $db->beginTransaction();
        try {
            $workItemStmt = $db->prepare('SELECT id, work_status FROM crm_work_items WHERE provisioning_job_id = :job_id LIMIT 1');
            $workItemStmt->execute(['job_id' => $jobId]);
            $workItem = $workItemStmt->fetch() ?: [];
            $response = [
                'completed_from_crm_queue' => true,
                'completed_by' => $admin['username'] ?? 'admin',
                'staff_notes' => $note,
                'completed_at' => gmdate('c'),
            ];
            $db->prepare("UPDATE provisioning_jobs SET status='completed', response_payload=:response, error_message=NULL WHERE id=:id")
                ->execute(['response' => json_encode($response, JSON_UNESCAPED_SLASHES), 'id' => $jobId]);
            $db->prepare("UPDATE crm_work_items SET work_status='completed', staff_notes=:notes, completed_at=NOW() WHERE provisioning_job_id=:job_id")
                ->execute(['notes' => $note !== '' ? $note : null, 'job_id' => $jobId]);
            if (!empty($workItem['id'])) {
                hivenest_crm_work_log(
                    $db,
                    (int)$workItem['id'],
                    $jobId,
                    (int)$admin['id'],
                    'team_job_completed',
                    ['work_status' => (string)($workItem['work_status'] ?? '')],
                    ['work_status' => 'completed'],
                    $note
                );
            }
            if (!empty($job['order_item_id'])) {
                $db->prepare("UPDATE order_items SET provisioning_status='completed', provisioning_error=NULL WHERE id=:id")
                    ->execute(['id' => (int)$job['order_item_id']]);
            }
            if (!empty($job['service_id'])) {
                $db->prepare("UPDATE services SET service_status='active', setup_date=COALESCE(setup_date, NOW()) WHERE id=:id")
                    ->execute(['id' => (int)$job['service_id']]);
            }
            if (!empty($job['order_item_id'])) hivenest_refresh_order_item_from_jobs($db, (int)$job['order_item_id']);
            hivenest_refresh_order_provisioning_status($db, (int)$job['order_id']);
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            error_log('CRM work queue team completion failed: ' . $error->getMessage());
            hivenest_crm_work_out(500, ['error' => 'Team job could not be completed.']);
        }
        if (!empty($job['order_item_id']) && hivenest_order_item_ready_to_notify($db, (int)$job['order_item_id'])) {
            hivenest_send_service_ready_email((int)$job['order_item_id'], [
                'completed_manually' => true,
            ]);
        }
        hivenest_crm_work_out(200, ['ok' => true, 'message' => 'Team job completed.']);
    }

    hivenest_crm_work_out(422, ['error' => 'Unknown work queue action.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') hivenest_crm_work_out(405, ['error' => 'GET or POST required.']);

$status = hivenest_crm_work_clean((string)($_GET['status'] ?? ''), 30);
$type = hivenest_crm_work_clean((string)($_GET['type'] ?? ''), 40);
$provider = hivenest_crm_work_clean((string)($_GET['provider'] ?? ''), 40);
$assigned = hivenest_crm_work_clean((string)($_GET['assigned'] ?? ''), 30);
$due = hivenest_crm_work_clean((string)($_GET['due'] ?? ''), 20);
$q = hivenest_crm_work_clean((string)($_GET['q'] ?? ''), 120);

$seedWhere = "pj.status IN ('pending','retry','failed','manual_review') OR pj.job_type IN ('design_queue','marketing_queue','manual_queue')";
$db->exec("
    INSERT IGNORE INTO crm_work_items (uuid, provisioning_job_id, priority, work_status)
    SELECT UUID(), pj.id,
           CASE WHEN pj.status IN ('failed','manual_review') THEN 'high' ELSE 'normal' END,
           CASE WHEN pj.status = 'completed' THEN 'completed' ELSE 'todo' END
    FROM provisioning_jobs pj
    WHERE {$seedWhere}
");
hivenest_crm_work_backfill_history($db);

$where = [];
$params = [];
if ($status !== '') {
    $where[] = 'wi.work_status = :status';
    $params['status'] = $status;
}
if ($type !== '') {
    $where[] = 'pj.job_type = :type';
    $params['type'] = $type;
}
if ($provider === 'hivenest_team') {
    $where[] = "pj.provider = 'hivenest_team'";
} elseif ($provider === 'myorderbox') {
    $where[] = "pj.provider = 'myorderbox'";
} elseif ($provider === 'other') {
    $where[] = "pj.provider NOT IN ('hivenest_team','myorderbox')";
}
if ($assigned === 'my') {
    $where[] = 'wi.assigned_to = :assigned_to';
    $params['assigned_to'] = (int)$admin['id'];
} elseif ($assigned === 'unassigned') {
    $where[] = 'wi.assigned_to IS NULL';
}
if ($due === 'overdue') {
    $where[] = "wi.due_at < NOW() AND wi.work_status NOT IN ('completed','cancelled')";
} elseif ($due === 'today') {
    $where[] = "DATE(wi.due_at) = CURRENT_DATE() AND wi.work_status NOT IN ('completed','cancelled')";
} elseif ($due === 'upcoming') {
    $where[] = "wi.due_at > NOW() AND wi.work_status NOT IN ('completed','cancelled')";
} elseif ($due === 'none') {
    $where[] = 'wi.due_at IS NULL';
}
if ($q !== '') {
    $where[] = '(o.order_number LIKE :q OR c.email LIKE :q OR c.first_name LIKE :q OR c.last_name LIKE :q OR c.company_name LIKE :q OR oi.product_name LIKE :q OR s.service_name LIKE :q OR s.domain_name LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT
        wi.*,
        CASE WHEN wi.due_at < NOW() AND wi.work_status NOT IN ('completed','cancelled') THEN 1 ELSE 0 END AS is_overdue,
        pj.job_type, pj.provider, pj.status AS job_status, pj.error_message, pj.request_payload, pj.response_payload, pj.order_id, pj.order_item_id, pj.service_id,
        o.order_number, o.payment_status, o.provisioning_status AS order_provisioning_status,
        oi.product_name AS item_product_name, oi.domain_name AS item_domain_name, oi.provisioning_status AS item_status,
        s.service_name, s.domain_name AS service_domain_name, s.service_status,
        c.id AS customer_id, c.email AS customer_email, c.first_name, c.last_name, c.company_name, c.myorderbox_customer_id,
        au.username AS assigned_username
    FROM crm_work_items wi
    INNER JOIN provisioning_jobs pj ON pj.id = wi.provisioning_job_id
    INNER JOIN customers c ON c.id = pj.customer_id
    LEFT JOIN orders o ON o.id = pj.order_id
    LEFT JOIN order_items oi ON oi.id = pj.order_item_id
    LEFT JOIN services s ON s.id = pj.service_id
    LEFT JOIN admin_users au ON au.id = wi.assigned_to
    {$whereSql}
    ORDER BY
        CASE wi.work_status WHEN 'in_progress' THEN 1 WHEN 'todo' THEN 2 WHEN 'waiting_client' THEN 3 WHEN 'waiting_provider' THEN 4 ELSE 5 END,
        CASE wi.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END,
        wi.id DESC
    LIMIT 150
");
$stmt->execute($params);

$items = [];
$itemPositions = [];
foreach ($stmt->fetchAll() ?: [] as $row) {
    $payload = hivenest_crm_work_payload($row['request_payload'] ?? null);
    $responsePayload = hivenest_crm_work_payload($row['response_payload'] ?? null);
    $onboardingId = (int)($responsePayload['client_onboarding_id'] ?? $payload['client_onboarding_id'] ?? 0);
    $name = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
    $itemPositions[(int)$row['id']] = count($items);
    $items[] = [
        'id' => (int)$row['id'],
        'job_id' => (int)$row['provisioning_job_id'],
        'job_type' => (string)$row['job_type'],
        'provider' => (string)$row['provider'],
        'job_status' => (string)$row['job_status'],
        'priority' => (string)$row['priority'],
        'work_status' => (string)$row['work_status'],
        'assigned_to' => $row['assigned_to'] !== null ? (int)$row['assigned_to'] : null,
        'assigned_username' => $row['assigned_username'] ?? null,
        'staff_notes' => $row['staff_notes'] ?? null,
        'due_at' => $row['due_at'] ?? null,
        'is_overdue' => (bool)($row['is_overdue'] ?? false),
        'completed_at' => $row['completed_at'] ?? null,
        'error_message' => $row['error_message'] ?? null,
        'product_name' => $payload['product_name'] ?? $row['item_product_name'] ?? $row['service_name'] ?? 'Order-level job',
        'sku' => $payload['sku'] ?? null,
        'domain_name' => $payload['domain_name'] ?? $row['item_domain_name'] ?? $row['service_domain_name'] ?? null,
        'onboarding_id' => $onboardingId > 0 ? $onboardingId : null,
        'workflow_alert' => [
            'active' => !empty($responsePayload['workflow_alert']) || !empty($payload['workflow_alert']),
            'action' => $responsePayload['workflow_action'] ?? $payload['workflow_action'] ?? null,
            'stage_id' => $responsePayload['stage_id'] ?? $payload['stage_id'] ?? null,
            'stage_title' => $responsePayload['stage_title'] ?? $payload['stage_title'] ?? null,
            'comment' => $responsePayload['comment'] ?? $payload['comment'] ?? null,
        ],
        'service_request' => [
            'active' => ($payload['source'] ?? '') === 'customer_service_request',
            'id' => isset($payload['service_request_id']) ? (int)$payload['service_request_id'] : null,
            'type' => $payload['request_type'] ?? null,
            'requested_value' => $payload['requested_value'] ?? null,
            'message' => $payload['message'] ?? null,
        ],
        'order' => [
            'id' => (int)$row['order_id'],
            'order_number' => $row['order_number'] ?? null,
            'payment_status' => $row['payment_status'] ?? null,
            'provisioning_status' => $row['order_provisioning_status'] ?? null,
        ],
        'service' => [
            'id' => $row['service_id'] !== null ? (int)$row['service_id'] : null,
            'name' => $row['service_name'] ?? null,
            'status' => $row['service_status'] ?? null,
        ],
        'customer' => [
            'id' => (int)$row['customer_id'],
            'email' => (string)$row['customer_email'],
            'name' => $row['company_name'] ?: ($name !== '' ? $name : (string)$row['customer_email']),
            'myorderbox_customer_id' => $row['myorderbox_customer_id'] ?? null,
        ],
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
        'history' => [],
    ];
}

if ($itemPositions) {
    $workItemIds = implode(',', array_map('intval', array_keys($itemPositions)));
    $historyStmt = $db->query("
        SELECT h.id, h.work_item_id, h.action, h.previous_values, h.new_values, h.note, h.created_at,
               au.username AS admin_username
        FROM crm_work_item_history h
        LEFT JOIN admin_users au ON au.id = h.admin_user_id
        WHERE h.work_item_id IN ({$workItemIds})
        ORDER BY h.id DESC
    ");
    foreach ($historyStmt->fetchAll() ?: [] as $historyRow) {
        $workItemId = (int)$historyRow['work_item_id'];
        if (!isset($itemPositions[$workItemId])) continue;
        $position = $itemPositions[$workItemId];
        if (count($items[$position]['history']) >= 10) continue;
        $items[$position]['history'][] = [
            'id' => (int)$historyRow['id'],
            'action' => (string)$historyRow['action'],
            'previous_values' => hivenest_crm_work_payload($historyRow['previous_values'] ?? null),
            'new_values' => hivenest_crm_work_payload($historyRow['new_values'] ?? null),
            'note' => $historyRow['note'] ?? null,
            'admin_username' => $historyRow['admin_username'] ?? 'System',
            'created_at' => $historyRow['created_at'] ?? null,
        ];
    }
}

$admins = [];
$adminStmt = $db->query("SELECT id, username, email, role FROM admin_users WHERE is_active = 1 ORDER BY username ASC");
foreach ($adminStmt->fetchAll() ?: [] as $row) {
    $admins[] = ['id' => (int)$row['id'], 'username' => (string)$row['username'], 'email' => (string)$row['email'], 'role' => (string)($row['role'] ?? 'staff')];
}

hivenest_crm_work_out(200, [
    'items' => $items,
    'admins' => $admins,
    'current_admin' => [
        'id' => (int)$admin['id'],
        'username' => (string)($admin['username'] ?? ''),
    ],
    'filters' => [
        'status' => $status !== '' ? $status : null,
        'type' => $type !== '' ? $type : null,
        'provider' => $provider !== '' ? $provider : null,
        'assigned' => $assigned !== '' ? $assigned : null,
        'due' => $due !== '' ? $due : null,
        'q' => $q !== '' ? $q : null,
    ],
]);
