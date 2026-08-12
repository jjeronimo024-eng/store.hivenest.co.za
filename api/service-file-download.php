<?php
declare(strict_types=1);

require_once __DIR__ . '/../utilities/customer_session.php';
hivenest_customer_session_configure();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function hivenest_service_file_download_json(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    hivenest_service_file_download_json(405, ['error' => 'GET required.']);
}

$session = hivenest_customer_session_status(true);
if (!$session['authenticated']) {
    hivenest_service_file_download_json(401, ['error' => 'Customer login required.']);
}

require_once __DIR__ . '/../access/dbconfig.php';
$db = hivenest_db();
if (!$db) hivenest_service_file_download_json(503, ['error' => 'Database is unavailable.']);

$fileId = (int)($_GET['id'] ?? 0);
if ($fileId <= 0) hivenest_service_file_download_json(422, ['error' => 'File reference is required.']);
$customerId = (int)$session['customer_id'];

$stmt = $db->prepare("
    SELECT f.*
    FROM service_files f
    INNER JOIN services s
        ON s.id = f.service_id
       AND s.customer_id = f.customer_id
    WHERE f.id = :file_id
      AND f.customer_id = :customer_id
      AND f.visibility = 'client'
      AND f.deleted_at IS NULL
      AND (f.retention_until IS NULL OR f.retention_until > CURRENT_TIMESTAMP)
    LIMIT 1
");
$stmt->execute([
    'file_id' => $fileId,
    'customer_id' => $customerId,
]);
$file = $stmt->fetch();
if (!$file) hivenest_service_file_download_json(404, ['error' => 'Service file was not found for this account.']);

$root = realpath(__DIR__ . '/../uploads/service-files');
if ($root === false) hivenest_service_file_download_json(404, ['error' => 'Service file storage was not found.']);
$path = realpath(
    $root
    . DIRECTORY_SEPARATOR . 'customer_' . $customerId
    . DIRECTORY_SEPARATOR . 'service_' . (int)$file['service_id']
    . DIRECTORY_SEPARATOR . basename((string)$file['stored_name'])
);
if ($path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
    hivenest_service_file_download_json(404, ['error' => 'Service file is missing from protected storage.']);
}

try {
    $db->beginTransaction();
    $db->prepare("
        UPDATE service_files
        SET download_count = download_count + 1,
            last_downloaded_at = CURRENT_TIMESTAMP
        WHERE id = :file_id
          AND customer_id = :customer_id
    ")->execute([
        'file_id' => $fileId,
        'customer_id' => $customerId,
    ]);
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $ipHash = $ip !== '' ? hash('sha256', $ip . '|' . session_id()) : null;
    $db->prepare("
        INSERT INTO service_file_downloads
            (service_file_id, customer_id, actor_type, ip_hash, user_agent)
        VALUES
            (:service_file_id, :customer_id, 'customer', :ip_hash, :user_agent)
    ")->execute([
        'service_file_id' => $fileId,
        'customer_id' => $customerId,
        'ip_hash' => $ipHash,
        'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500) ?: null,
    ]);
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('Service file download audit failed: ' . $e->getMessage());
    hivenest_service_file_download_json(503, ['error' => 'Download audit could not be recorded. Try again.']);
}

$original = basename((string)$file['original_name']);
$mime = trim((string)$file['mime_type']) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $original) . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
