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
require_once __DIR__ . '/../utilities/support_notifications.php';
require_once __DIR__ . '/../utilities/customer_notifications.php';

function hivenest_crm_service_actions_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_crm_service_actions_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function hivenest_crm_service_actions_clean(string $value, int $max = 5000): string
{
    $value = trim(str_replace(["\0"], '', $value));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function hivenest_crm_service_actions_env(string $key, string $default = ''): string
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

function hivenest_crm_service_actions_b64url_decode(string $value): string|false
{
    $remainder = strlen($value) % 4;
    if ($remainder) $value .= str_repeat('=', 4 - $remainder);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function hivenest_crm_service_actions_b64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function hivenest_crm_service_actions_bearer_token(): string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(.+)/i', $header, $match)) return trim($match[1]);
    return trim((string)($_COOKIE['hivenest_admin_access_token'] ?? ''));
}

function hivenest_crm_service_actions_admin_id(PDO $db): int
{
    if (!empty($_SESSION['admin_user']['id']) && !empty($_SESSION['admin_login_time'])) return (int)$_SESSION['admin_user']['id'];
    $token = hivenest_crm_service_actions_bearer_token();
    if ($token === '') return 0;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return 0;
    [$header64, $payload64, $signature64] = $parts;
    $headerJson = hivenest_crm_service_actions_b64url_decode($header64);
    $payloadJson = hivenest_crm_service_actions_b64url_decode($payload64);
    if ($headerJson === false || $payloadJson === false) return 0;
    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);
    if (!is_array($header) || !is_array($payload)) return 0;
    if (($header['alg'] ?? '') !== hivenest_crm_service_actions_env('JWT_ALGORITHM', 'HS256')) return 0;
    if (($payload['user_type'] ?? '') !== 'admin') return 0;
    if (!empty($payload['exp']) && (int)$payload['exp'] < time()) return 0;
    $secret = hivenest_crm_service_actions_env('JWT_SECRET_KEY');
    if ($secret === '') return 0;
    $expected = hivenest_crm_service_actions_b64url_encode(hash_hmac('sha256', $header64 . '.' . $payload64, $secret, true));
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

function hivenest_crm_service_actions_table_exists(PDO $db, string $table): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table");
    $stmt->execute(['table' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function hivenest_crm_service_actions_sync_request_queue(
    PDO $db,
    int $serviceId,
    int $requestId,
    string $requestType,
    string $status,
    string $response,
    int $adminId,
    string $previousStatus,
    string $previousResponse
): void {
    if (
        !hivenest_crm_service_actions_table_exists($db, 'provisioning_jobs')
        || !hivenest_crm_service_actions_table_exists($db, 'crm_work_items')
    ) {
        return;
    }

    $jobStmt = $db->prepare("
        SELECT id
        FROM provisioning_jobs
        WHERE service_id = :service_id
          AND job_type = 'manual_queue'
          AND provider = 'hivenest_team'
          AND request_payload LIKE :request_marker
        ORDER BY id DESC
        LIMIT 1
    ");
    $jobStmt->execute([
        'service_id' => $serviceId,
        'request_marker' => '%"service_request_id":' . $requestId . '%',
    ]);
    $jobId = (int)($jobStmt->fetchColumn() ?: 0);
    if ($jobId <= 0) return;

    $workItemStmt = $db->prepare("
        SELECT id, work_status, staff_notes
        FROM crm_work_items
        WHERE provisioning_job_id = :job_id
        LIMIT 1
    ");
    $workItemStmt->execute(['job_id' => $jobId]);
    $previousWorkItem = $workItemStmt->fetch() ?: [];

    $isClosed = in_array($status, ['completed', 'rejected', 'cancelled'], true);
    $jobStatus = $isClosed ? 'completed' : 'manual_review';
    $workStatus = $isClosed
        ? 'completed'
        : (in_array($status, ['in_review', 'approved'], true) ? 'in_progress' : 'todo');
    $statusLabel = str_replace('_', ' ', $status);
    $responsePayload = json_encode([
        'source' => 'crm_service_request',
        'service_request_id' => $requestId,
        'request_type' => $requestType,
        'request_status' => $status,
        'admin_response' => $response !== '' ? $response : null,
        'updated_at' => date(DATE_ATOM),
    ], JSON_UNESCAPED_SLASHES);

    $jobUpdate = $db->prepare("
        UPDATE provisioning_jobs
        SET status = :status,
            response_payload = :response_payload,
            error_message = NULL,
            next_attempt_at = NULL,
            locked_at = NULL,
            locked_by = NULL
        WHERE id = :id
    ");
    $jobUpdate->execute([
        'status' => $jobStatus,
        'response_payload' => $responsePayload,
        'id' => $jobId,
    ]);

    $note = 'Service request #' . $requestId . ' marked ' . $statusLabel . ' by CRM.';
    if ($response !== '') $note .= ' Response: ' . $response;

    if ($isClosed) {
        $workUpdate = $db->prepare("
            UPDATE crm_work_items
            SET work_status = 'completed',
                completed_at = COALESCE(completed_at, NOW()),
                staff_notes = CASE
                    WHEN staff_notes IS NULL OR staff_notes = '' THEN :staff_note
                    ELSE CONCAT(staff_notes, CHAR(10), :staff_note_copy)
                END
            WHERE provisioning_job_id = :job_id
        ");
    } else {
        $workUpdate = $db->prepare("
            UPDATE crm_work_items
            SET work_status = :work_status,
                completed_at = NULL,
                staff_notes = CASE
                    WHEN staff_notes IS NULL OR staff_notes = '' THEN :staff_note
                    ELSE CONCAT(staff_notes, CHAR(10), :staff_note_copy)
                END
            WHERE provisioning_job_id = :job_id
        ");
    }
    $workParams = [
        'staff_note' => $note,
        'staff_note_copy' => $note,
        'job_id' => $jobId,
    ];
    if (!$isClosed) $workParams['work_status'] = $workStatus;
    $workUpdate->execute($workParams);

    $requestChanged = $previousStatus !== $status || trim($previousResponse) !== trim($response);
    if (
        $requestChanged
        && !empty($previousWorkItem['id'])
        && hivenest_crm_service_actions_table_exists($db, 'crm_work_item_history')
    ) {
        $historyStmt = $db->prepare("
            INSERT INTO crm_work_item_history
                (work_item_id, provisioning_job_id, admin_user_id, action, previous_values, new_values, note)
            VALUES
                (:work_item_id, :job_id, :admin_id, 'service_request_updated', :previous_values, :new_values, :note)
        ");
        $historyStmt->execute([
            'work_item_id' => (int)$previousWorkItem['id'],
            'job_id' => $jobId,
            'admin_id' => $adminId > 0 ? $adminId : null,
            'previous_values' => json_encode([
                'request_status' => $previousStatus,
                'admin_response' => $previousResponse !== '' ? $previousResponse : null,
                'work_status' => $previousWorkItem['work_status'] ?? null,
            ], JSON_UNESCAPED_SLASHES),
            'new_values' => json_encode([
                'request_status' => $status,
                'admin_response' => $response !== '' ? $response : null,
                'work_status' => $workStatus,
            ], JSON_UNESCAPED_SLASHES),
            'note' => $note,
        ]);
    }
}

function hivenest_crm_service_actions_ensure_schema(PDO $db): void
{
    if (hivenest_crm_service_actions_table_exists($db, 'service_notes')) return;
    $db->exec("
        CREATE TABLE IF NOT EXISTS service_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            uuid VARCHAR(36) NOT NULL UNIQUE,
            service_id INT NOT NULL,
            customer_id INT NOT NULL,
            order_id INT NULL,
            author_type ENUM('admin','customer','system') NOT NULL DEFAULT 'admin',
            author_admin_id INT NULL,
            author_customer_id INT NULL,
            visibility ENUM('internal','client') NOT NULL DEFAULT 'internal',
            note_type ENUM('note','status_update','handover','renewal','issue') NOT NULL DEFAULT 'note',
            note_text TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_service_created (service_id, created_at),
            INDEX idx_customer_created (customer_id, created_at),
            INDEX idx_visibility (visibility)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS service_status_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            uuid VARCHAR(36) NOT NULL UNIQUE,
            service_id INT NOT NULL,
            customer_id INT NOT NULL,
            order_id INT NULL,
            changed_by_admin_id INT NULL,
            old_status VARCHAR(40) NULL,
            new_status VARCHAR(40) NOT NULL,
            reason TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_service_created (service_id, created_at),
            INDEX idx_customer_created (customer_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
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

$db = hivenest_db();
if (!$db) hivenest_crm_service_actions_out(503, ['error' => 'CRM database is unavailable.']);
$adminId = hivenest_crm_service_actions_admin_id($db);
if ($adminId <= 0) hivenest_crm_service_actions_out(401, ['error' => 'Admin login required.']);
hivenest_crm_service_actions_ensure_schema($db);

$serviceId = (int)($_GET['service_id'] ?? $_POST['service_id'] ?? $_GET['service'] ?? $_POST['service'] ?? 0);
if ($serviceId <= 0) hivenest_crm_service_actions_out(422, ['error' => 'Service is required.']);

$serviceStmt = $db->prepare('SELECT * FROM services WHERE id = :id LIMIT 1');
$serviceStmt->execute(['id' => $serviceId]);
$service = $serviceStmt->fetch();
if (!$service) hivenest_crm_service_actions_out(404, ['error' => 'Service not found.']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hivenest_crm_role_allows(hivenest_crm_admin_record($db, $adminId), 'service.manage')) {
        hivenest_crm_service_actions_out(403, ['error' => 'Your staff role cannot change services.']);
    }
    $action = hivenest_crm_service_actions_clean((string)($_POST['action'] ?? ''), 40);

    if ($action === 'add_note') {
        $note = hivenest_crm_service_actions_clean((string)($_POST['note_text'] ?? ''), 5000);
        if ($note === '') hivenest_crm_service_actions_out(422, ['error' => 'Note text is required.']);
        $visibility = (string)($_POST['visibility'] ?? 'internal') === 'client' ? 'client' : 'internal';
        $noteType = hivenest_crm_service_actions_clean((string)($_POST['note_type'] ?? 'note'), 40);
        $allowedTypes = ['note','status_update','handover','renewal','issue'];
        if (!in_array($noteType, $allowedTypes, true)) $noteType = 'note';
        $insert = $db->prepare("
            INSERT INTO service_notes
                (uuid, service_id, customer_id, order_id, author_type, author_admin_id, visibility, note_type, note_text)
            VALUES
                (:uuid, :service_id, :customer_id, :order_id, 'admin', :admin_id, :visibility, :note_type, :note_text)
        ");
        $insert->execute([
            'uuid' => hivenest_crm_service_actions_uuid(),
            'service_id' => $serviceId,
            'customer_id' => (int)$service['customer_id'],
            'order_id' => $service['order_id'] ? (int)$service['order_id'] : null,
            'admin_id' => $adminId,
            'visibility' => $visibility,
            'note_type' => $noteType,
            'note_text' => $note,
        ]);
        hivenest_crm_service_actions_out(200, ['ok' => true, 'message' => 'Service note added.']);
    }

    if ($action === 'update_status') {
        $status = hivenest_crm_service_actions_clean((string)($_POST['service_status'] ?? ''), 40);
        $allowedStatuses = ['pending','active','suspended','cancelled','expired','terminated'];
        if (!in_array($status, $allowedStatuses, true)) hivenest_crm_service_actions_out(422, ['error' => 'Invalid service status.']);
        $reason = hivenest_crm_service_actions_clean((string)($_POST['reason'] ?? ''), 5000);
        $oldStatus = (string)$service['service_status'];
        $db->beginTransaction();
        try {
            $update = $db->prepare('UPDATE services SET service_status = :status, suspension_reason = :reason WHERE id = :id');
            $update->execute(['status' => $status, 'reason' => $reason !== '' ? $reason : null, 'id' => $serviceId]);
            $history = $db->prepare("
                INSERT INTO service_status_history
                    (uuid, service_id, customer_id, order_id, changed_by_admin_id, old_status, new_status, reason)
                VALUES
                    (:uuid, :service_id, :customer_id, :order_id, :admin_id, :old_status, :new_status, :reason)
            ");
            $history->execute([
                'uuid' => hivenest_crm_service_actions_uuid(),
                'service_id' => $serviceId,
                'customer_id' => (int)$service['customer_id'],
                'order_id' => $service['order_id'] ? (int)$service['order_id'] : null,
                'admin_id' => $adminId,
                'old_status' => $oldStatus,
                'new_status' => $status,
                'reason' => $reason !== '' ? $reason : null,
            ]);
            $note = $reason !== '' ? $reason : 'Service status changed from ' . $oldStatus . ' to ' . $status . '.';
            $noteStmt = $db->prepare("
                INSERT INTO service_notes
                    (uuid, service_id, customer_id, order_id, author_type, author_admin_id, visibility, note_type, note_text)
                VALUES
                    (:uuid, :service_id, :customer_id, :order_id, 'admin', :admin_id, 'internal', 'status_update', :note_text)
            ");
            $noteStmt->execute([
                'uuid' => hivenest_crm_service_actions_uuid(),
                'service_id' => $serviceId,
                'customer_id' => (int)$service['customer_id'],
                'order_id' => $service['order_id'] ? (int)$service['order_id'] : null,
                'admin_id' => $adminId,
                'note_text' => $note,
            ]);
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            hivenest_crm_service_actions_out(500, ['error' => 'Service status could not be updated.']);
        }
        hivenest_crm_service_actions_out(200, ['ok' => true, 'message' => 'Service status updated.']);
    }

    if ($action === 'update_request') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $status = hivenest_crm_service_actions_clean((string)($_POST['request_status'] ?? ''), 40);
        $response = hivenest_crm_service_actions_clean((string)($_POST['admin_response'] ?? ''), 5000);
        $allowedStatuses = ['pending','in_review','approved','completed','rejected','cancelled'];
        if ($requestId <= 0) hivenest_crm_service_actions_out(422, ['error' => 'Request ID is required.']);
        if (!in_array($status, $allowedStatuses, true)) hivenest_crm_service_actions_out(422, ['error' => 'Invalid request status.']);
        $check = $db->prepare('SELECT * FROM service_requests WHERE id = :id AND service_id = :service_id LIMIT 1');
        $check->execute(['id' => $requestId, 'service_id' => $serviceId]);
        $request = $check->fetch();
        if (!$request) hivenest_crm_service_actions_out(404, ['error' => 'Service request not found.']);
        $requestChanged = (string)($request['status'] ?? '') !== $status
            || trim((string)($request['admin_response'] ?? '')) !== $response;
        $db->beginTransaction();
        try {
            $update = $db->prepare('UPDATE service_requests SET status = :status, admin_response = :admin_response WHERE id = :id');
            $update->execute([
                'status' => $status,
                'admin_response' => $response !== '' ? $response : null,
                'id' => $requestId,
            ]);
            $noteText = 'Service request #' . $requestId . ' (' . $request['request_type'] . ') marked ' . $status . '.';
            if ($response !== '') $noteText .= ' Response: ' . $response;
            $noteStmt = $db->prepare("
                INSERT INTO service_notes
                    (uuid, service_id, customer_id, order_id, author_type, author_admin_id, visibility, note_type, note_text)
                VALUES
                    (:uuid, :service_id, :customer_id, :order_id, 'admin', :admin_id, 'client', 'status_update', :note_text)
            ");
            $noteStmt->execute([
                'uuid' => hivenest_crm_service_actions_uuid(),
                'service_id' => $serviceId,
                'customer_id' => (int)$service['customer_id'],
                'order_id' => $service['order_id'] ? (int)$service['order_id'] : null,
                'admin_id' => $adminId,
                'note_text' => $noteText,
            ]);
            hivenest_crm_service_actions_sync_request_queue(
                $db,
                $serviceId,
                $requestId,
                (string)$request['request_type'],
                $status,
                $response,
                $adminId,
                (string)($request['status'] ?? ''),
                trim((string)($request['admin_response'] ?? ''))
            );
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            hivenest_crm_service_actions_out(500, ['error' => 'Service request could not be updated.']);
        }
        $emailSent = null;
        if ($requestChanged) {
            $emailSent = hivenest_service_request_notify_client($db, $service, $request, $status, $response);
            if (!$emailSent) {
                error_log('Service request update email was not sent for request #' . $requestId);
            }
            try {
                hivenest_notify_customer(
                    $db,
                    (int)$service['customer_id'],
                    in_array($status, ['rejected', 'cancelled'], true) ? 'warning' : 'info',
                    'Service request updated',
                    (string)($service['service_name'] ?? 'Service') . ': ' . str_replace('_', ' ', (string)$request['request_type']) . ' is now ' . str_replace('_', ' ', $status) . '.',
                    '/services/manage.html?service=' . $serviceId,
                    'service_request',
                    $requestId
                );
            } catch (Throwable $e) {
                error_log('Client service request in-app notification failed: ' . $e->getMessage());
            }
        }
        hivenest_crm_service_actions_out(200, [
            'ok' => true,
            'message' => 'Service request updated.',
            'email_sent' => $emailSent,
        ]);
    }

    hivenest_crm_service_actions_out(422, ['error' => 'Unknown service action.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') hivenest_crm_service_actions_out(405, ['error' => 'Method not allowed.']);

$notesStmt = $db->prepare("
    SELECT n.*, a.username AS admin_username, a.email AS admin_email
    FROM service_notes n
    LEFT JOIN admin_users a ON a.id = n.author_admin_id
    WHERE n.service_id = :service_id
    ORDER BY n.id DESC
    LIMIT 100
");
$notesStmt->execute(['service_id' => $serviceId]);
$notes = [];
foreach ($notesStmt->fetchAll() ?: [] as $row) {
    $notes[] = [
        'id' => (int)$row['id'],
        'visibility' => (string)$row['visibility'],
        'note_type' => (string)$row['note_type'],
        'note_text' => (string)$row['note_text'],
        'author_type' => (string)$row['author_type'],
        'author' => $row['admin_username'] ?? $row['admin_email'] ?? 'HiveNest',
        'created_at' => $row['created_at'] ?? null,
    ];
}

$historyStmt = $db->prepare("
    SELECT h.*, a.username AS admin_username
    FROM service_status_history h
    LEFT JOIN admin_users a ON a.id = h.changed_by_admin_id
    WHERE h.service_id = :service_id
    ORDER BY h.id DESC
    LIMIT 50
");
$historyStmt->execute(['service_id' => $serviceId]);
$history = [];
foreach ($historyStmt->fetchAll() ?: [] as $row) {
    $history[] = [
        'id' => (int)$row['id'],
        'old_status' => $row['old_status'] ?? null,
        'new_status' => (string)$row['new_status'],
        'reason' => $row['reason'] ?? null,
        'changed_by' => $row['admin_username'] ?? 'HiveNest',
        'created_at' => $row['created_at'] ?? null,
    ];
}

hivenest_crm_service_actions_out(200, [
    'service' => [
        'id' => (int)$service['id'],
        'service_name' => (string)$service['service_name'],
        'service_status' => (string)$service['service_status'],
        'customer_id' => (int)$service['customer_id'],
    ],
    'notes' => $notes,
    'history' => $history,
]);
