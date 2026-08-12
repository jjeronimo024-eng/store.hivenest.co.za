<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../utilities/customer_session.php';
hivenest_customer_session_configure();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$customerSession = hivenest_customer_session_status(true);
if (!$customerSession['authenticated']) {
    http_response_code(401);
    echo json_encode(['error' => 'Customer login required.'], JSON_UNESCAPED_SLASHES);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hivenest_customer_csrf_require_json();
}

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/upload_security.php';

function hivenest_service_files_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function hivenest_service_files_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function hivenest_service_files_table_exists(PDO $db, string $table): bool
{
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
    ");
    $stmt->execute(['table_name' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function hivenest_service_files_clean(string $value, int $maximum): string
{
    $value = trim(str_replace("\0", '', $value));
    return function_exists('mb_substr')
        ? mb_substr($value, 0, $maximum)
        : substr($value, 0, $maximum);
}

function hivenest_service_files_payload(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'service_id' => (int)$row['service_id'],
        'file_category' => (string)$row['file_category'],
        'description' => $row['description'] ?? null,
        'original_name' => (string)$row['original_name'],
        'mime_type' => (string)$row['mime_type'],
        'extension' => (string)$row['extension'],
        'file_size' => (int)$row['file_size'],
        'scan_status' => (string)$row['scan_status'],
        'uploaded_by_type' => (string)$row['uploaded_by_type'],
        'download_count' => (int)$row['download_count'],
        'created_at' => $row['created_at'] ?? null,
        'download_url' => '/api/service-files/download?id=' . (int)$row['id'],
    ];
}

$customerId = (int)$customerSession['customer_id'];
$db = hivenest_db();
if (!$db) hivenest_service_files_out(503, ['error' => 'Customer database is unavailable.']);
if (!hivenest_service_files_table_exists($db, 'service_files')) {
    hivenest_service_files_out(503, [
        'error' => 'Service file storage is not installed. Import Database/service_file_library.sql.',
    ]);
}

$serviceId = (int)($_GET['service_id'] ?? $_POST['service_id'] ?? 0);
if ($serviceId <= 0) hivenest_service_files_out(422, ['error' => 'Service ID is required.']);

$serviceStmt = $db->prepare("
    SELECT id, order_id, service_name
    FROM services
    WHERE id = :service_id
      AND customer_id = :customer_id
    LIMIT 1
");
$serviceStmt->execute([
    'service_id' => $serviceId,
    'customer_id' => $customerId,
]);
$service = $serviceStmt->fetch();
if (!$service) hivenest_service_files_out(404, ['error' => 'Service was not found for this account.']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("
        SELECT *
        FROM service_files
        WHERE service_id = :service_id
          AND customer_id = :customer_id
          AND visibility = 'client'
          AND deleted_at IS NULL
          AND (retention_until IS NULL OR retention_until > CURRENT_TIMESTAMP)
        ORDER BY created_at DESC, id DESC
        LIMIT 200
    ");
    $stmt->execute([
        'service_id' => $serviceId,
        'customer_id' => $customerId,
    ]);
    hivenest_service_files_out(200, [
        'service_id' => $serviceId,
        'files' => array_map('hivenest_service_files_payload', $stmt->fetchAll() ?: []),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hivenest_service_files_out(405, ['error' => 'GET or POST required.']);
}
if (empty($_FILES['files']) || !is_array($_FILES['files']['name'] ?? null)) {
    hivenest_service_files_out(422, ['error' => 'Select at least one file.']);
}

$category = strtolower(hivenest_service_files_clean((string)($_POST['file_category'] ?? 'general'), 60));
if (!preg_match('/^[a-z0-9_-]{1,60}$/', $category)) $category = 'general';
$description = hivenest_service_files_clean((string)($_POST['description'] ?? ''), 500);
$storageRoot = __DIR__ . '/../uploads/service-files';
if (!is_dir($storageRoot)) @mkdir($storageRoot, 0750, true);
$storageRoot = realpath($storageRoot);
if ($storageRoot === false) hivenest_service_files_out(503, ['error' => 'Service file storage is unavailable.']);
$destination = $storageRoot . DIRECTORY_SEPARATOR . 'customer_' . $customerId . DIRECTORY_SEPARATOR . 'service_' . $serviceId;
$relative = 'uploads/service-files/customer_' . $customerId . '/service_' . $serviceId;
$allowed = ['jpg','jpeg','png','gif','webp','svg','pdf','doc','docx','txt','zip','psd','ai'];
$saved = [];
$errors = [];
$count = min(count($_FILES['files']['name']), 5);

for ($index = 0; $index < $count; $index++) {
    $upload = hivenest_secure_upload([
        'name' => $_FILES['files']['name'][$index] ?? '',
        'type' => $_FILES['files']['type'][$index] ?? '',
        'tmp_name' => $_FILES['files']['tmp_name'][$index] ?? '',
        'error' => $_FILES['files']['error'][$index] ?? UPLOAD_ERR_NO_FILE,
        'size' => $_FILES['files']['size'][$index] ?? 0,
    ], $destination, $relative, $allowed, 25 * 1024 * 1024);

    if (!empty($upload['error'])) {
        $errors[] = [
            'original_name' => $upload['original_name'] ?? 'File',
            'error' => $upload['error'],
        ];
        continue;
    }

    try {
        $insert = $db->prepare("
            INSERT INTO service_files
                (uuid, customer_id, service_id, order_id, uploaded_by_type,
                 uploaded_by_customer_id, file_category, description,
                 original_name, stored_name, relative_path, mime_type,
                 extension, file_size, scan_status, visibility)
            VALUES
                (:uuid, :customer_id, :service_id, :order_id, 'customer',
                 :uploaded_by_customer_id, :file_category, :description,
                 :original_name, :stored_name, :relative_path, :mime_type,
                 :extension, :file_size, :scan_status, 'client')
        ");
        $insert->execute([
            'uuid' => hivenest_service_files_uuid(),
            'customer_id' => $customerId,
            'service_id' => $serviceId,
            'order_id' => !empty($service['order_id']) ? (int)$service['order_id'] : null,
            'uploaded_by_customer_id' => $customerId,
            'file_category' => $category,
            'description' => $description !== '' ? $description : null,
            'original_name' => (string)$upload['original_name'],
            'stored_name' => (string)$upload['stored_name'],
            'relative_path' => (string)$upload['relative_path'],
            'mime_type' => (string)$upload['mime'],
            'extension' => (string)$upload['extension'],
            'file_size' => (int)$upload['size'],
            'scan_status' => (string)$upload['scan_status'],
        ]);
        $upload['id'] = (int)$db->lastInsertId();
        unset($upload['relative_path'], $upload['stored_name']);
        $saved[] = $upload;
    } catch (Throwable $e) {
        @unlink($destination . DIRECTORY_SEPARATOR . (string)$upload['stored_name']);
        error_log('Service file metadata insert failed: ' . $e->getMessage());
        $errors[] = [
            'original_name' => $upload['original_name'] ?? 'File',
            'error' => 'File metadata could not be saved.',
        ];
    }
}

if (!$saved) {
    hivenest_service_files_out(422, [
        'error' => 'No files were accepted.',
        'file_errors' => $errors,
    ]);
}
hivenest_service_files_out(201, [
    'message' => count($saved) . ' service file(s) uploaded securely.',
    'files' => $saved,
    'file_errors' => $errors,
]);
