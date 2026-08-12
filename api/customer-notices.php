<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../utilities/customer_session.php';
hivenest_customer_session_configure();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function hivenest_customer_notices_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    hivenest_customer_notices_out(405, ['ok' => false, 'error' => 'GET required.']);
}
$customerSession = hivenest_customer_session_status(true);
if (!$customerSession['authenticated']) {
    hivenest_customer_notices_out(401, ['ok' => false, 'error' => 'Customer login required.']);
}

require_once __DIR__ . '/../access/dbconfig.php';
$db = hivenest_db();
if (!$db) hivenest_customer_notices_out(503, ['ok' => false, 'error' => 'Customer database unavailable.']);
$customerId = (int)$customerSession['customer_id'];

try {
    $table = $db->query("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='service_notices'
    ");
    if ((int)$table->fetchColumn() === 0) {
        hivenest_customer_notices_out(200, ['ok' => true, 'notices' => []]);
    }
    $stmt = $db->prepare("
        SELECT n.id, n.uuid, n.notice_type, n.severity, n.title, n.message,
               n.status, n.audience_type, n.affected_service_type,
               n.starts_at, n.ends_at, n.published_at, n.resolved_at,
               CASE WHEN n.audience_type='service' THEN n.service_id ELSE NULL END AS service_id
        FROM service_notices n
        WHERE n.status IN ('published','resolved')
          AND (n.starts_at IS NULL OR n.starts_at <= NOW())
          AND (
                n.status='published'
                AND (n.ends_at IS NULL OR n.ends_at >= NOW())
                OR n.status='resolved'
                AND n.resolved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          )
          AND (
                n.audience_type='all'
                OR n.audience_type='customer' AND n.customer_id=:customer_id
                OR n.audience_type='service' AND EXISTS (
                    SELECT 1 FROM services s
                    WHERE s.id=n.service_id AND s.customer_id=:customer_id_service
                )
                OR n.audience_type='service_type' AND EXISTS (
                    SELECT 1 FROM services s
                    WHERE s.customer_id=:customer_id_type
                      AND LOWER(s.service_type)=LOWER(n.affected_service_type)
                      AND s.service_status IN ('active','pending','suspended')
                )
          )
        ORDER BY
          FIELD(n.severity, 'critical','warning','info'),
          COALESCE(n.published_at,n.created_at) DESC
        LIMIT 30
    ");
    $stmt->execute([
        'customer_id' => $customerId,
        'customer_id_service' => $customerId,
        'customer_id_type' => $customerId,
    ]);
    hivenest_customer_notices_out(200, ['ok' => true, 'notices' => $stmt->fetchAll() ?: []]);
} catch (Throwable $e) {
    error_log('Customer notices failed: ' . $e->getMessage());
    hivenest_customer_notices_out(500, ['ok' => false, 'error' => 'Customer notices could not be loaded.']);
}
