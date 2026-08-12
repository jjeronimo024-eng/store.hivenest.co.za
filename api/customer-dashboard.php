<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/customer_loyalty.php';

function hivenest_customer_dashboard_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function hivenest_customer_dashboard_table_exists(PDO $db, string $table): bool
{
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
        ");
        $stmt->execute(['table_name' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        error_log('Client dashboard table check failed: ' . $e->getMessage());
        return false;
    }
}

function hivenest_customer_dashboard_json(?string $value): array
{
    if ($value === null || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    hivenest_customer_dashboard_out(405, ['error' => 'GET required']);
}

$customerId = (int) ($_SESSION['customer_id'] ?? 0);
if ($customerId <= 0) {
    hivenest_customer_dashboard_out(401, ['error' => 'Customer login required.']);
}

$db = hivenest_db();
if (!$db) {
    hivenest_customer_dashboard_out(503, ['error' => 'Customer database is unavailable.']);
}

$profileStmt = $db->prepare("
    SELECT id, uuid, email, first_name, last_name, company_name, phone, preferred_currency, email_verified
    FROM customers
    WHERE id = :customer_id
    LIMIT 1
");
$profileStmt->execute(['customer_id' => $customerId]);
$profile = $profileStmt->fetch();
if (!$profile) {
    hivenest_customer_dashboard_out(404, ['error' => 'Customer profile not found.']);
}

$loyalty = hivenest_customer_loyalty($db, $customerId, false);

$services = [];
$serviceIds = [];
$activeServices = 0;
$nextPaymentDate = null;
if (hivenest_customer_dashboard_table_exists($db, 'services')) {
    $serviceStmt = $db->prepare("
        SELECT
            s.id,
            s.service_name,
            s.domain_name,
            s.service_type,
            s.service_status,
            s.billing_cycle,
            s.next_due_date,
            s.service_config,
            p.slug AS product_slug,
            p.product_type
        FROM services s
        LEFT JOIN products p ON p.id = s.product_id
        WHERE s.customer_id = :customer_id
        ORDER BY
            CASE s.service_status
                WHEN 'active' THEN 1
                WHEN 'pending' THEN 2
                WHEN 'manual_review' THEN 3
                ELSE 4
            END,
            COALESCE(s.next_due_date, '9999-12-31') ASC,
            s.id DESC
        LIMIT 12
    ");
    $serviceStmt->execute(['customer_id' => $customerId]);
    foreach ($serviceStmt->fetchAll() ?: [] as $row) {
        $config = hivenest_customer_dashboard_json($row['service_config'] ?? null);
        $serviceId = (int) $row['id'];
        $workflowStatus = (string) ($config['workflow_status'] ?? '');
        $workflowCompletedAt = $config['workflow_completed_at'] ?? null;
        $serviceIds[] = $serviceId;
        $services[] = [
            'id' => (int) $row['id'],
            'service_name' => (string) $row['service_name'],
            'domain_name' => $row['domain_name'] !== null ? (string) $row['domain_name'] : null,
            'service_type' => (string) ($row['service_type'] ?: $row['product_type'] ?: 'service'),
            'service_status' => (string) ($row['service_status'] ?: 'pending'),
            'billing_cycle' => (string) ($row['billing_cycle'] ?: 'monthly'),
            'next_due_date' => $row['next_due_date'] ?: null,
            'product_slug' => $row['product_slug'] ?: null,
            'needs_onboarding' => in_array((string) ($config['job_type'] ?? ''), ['design_queue', 'marketing_queue'], true)
                || in_array((string) ($row['service_type'] ?? ''), ['design', 'marketing'], true),
            'workflow_status' => $workflowStatus !== '' ? $workflowStatus : null,
            'workflow_completed_at' => is_string($workflowCompletedAt) && $workflowCompletedAt !== '' ? $workflowCompletedAt : null,
            'workflow_summary' => null,
        ];
    }

    if ($serviceIds && hivenest_customer_dashboard_table_exists($db, 'service_workflow_stages')) {
        $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
        $workflowStmt = $db->prepare("
            SELECT
                service_id,
                COUNT(*) AS total_stages,
                SUM(status = 'ready_for_review') AS ready_for_review,
                SUM(status = 'changes_requested') AS changes_requested,
                SUM(status IN ('approved','completed')) AS approved_or_completed,
                MIN(CASE WHEN status IN ('ready_for_review','changes_requested','in_progress') THEN display_order ELSE NULL END) AS next_display_order
            FROM service_workflow_stages
            WHERE customer_id = ?
              AND visible_to_customer = 1
              AND service_id IN ({$placeholders})
            GROUP BY service_id
        ");
        $workflowStmt->execute(array_merge([$customerId], $serviceIds));
        $summaries = [];
        foreach ($workflowStmt->fetchAll() ?: [] as $row) {
            $summaries[(int)$row['service_id']] = [
                'total_stages' => (int)$row['total_stages'],
                'ready_for_review' => (int)$row['ready_for_review'],
                'changes_requested' => (int)$row['changes_requested'],
                'approved_or_completed' => (int)$row['approved_or_completed'],
                'next_display_order' => $row['next_display_order'] !== null ? (int)$row['next_display_order'] : null,
            ];
        }

        foreach ($services as &$service) {
            $service['workflow_summary'] = $summaries[(int)$service['id']] ?? null;
        }
        unset($service);
    }

    $activeStmt = $db->prepare("SELECT COUNT(*) FROM services WHERE customer_id = :customer_id AND service_status IN ('active','pending')");
    $activeStmt->execute(['customer_id' => $customerId]);
    $activeServices = (int) $activeStmt->fetchColumn();

    $dueStmt = $db->prepare("
        SELECT MIN(next_due_date)
        FROM services
        WHERE customer_id = :customer_id
          AND next_due_date IS NOT NULL
          AND service_status IN ('active','pending')
    ");
    $dueStmt->execute(['customer_id' => $customerId]);
    $nextPaymentDate = $dueStmt->fetchColumn() ?: null;
}

$recentOrders = [];
if (hivenest_customer_dashboard_table_exists($db, 'orders')) {
    $orderStmt = $db->prepare("
        SELECT id, order_number, order_status, payment_status, provisioning_status, total_amount, currency, created_at
        FROM orders
        WHERE customer_id = :customer_id
        ORDER BY id DESC
        LIMIT 8
    ");
    $orderStmt->execute(['customer_id' => $customerId]);
    foreach ($orderStmt->fetchAll() ?: [] as $row) {
        $recentOrders[] = [
            'id' => (int) $row['id'],
            'order_number' => (string) $row['order_number'],
            'order_status' => (string) $row['order_status'],
            'payment_status' => (string) $row['payment_status'],
            'provisioning_status' => (string) ($row['provisioning_status'] ?? 'pending'),
            'total_amount' => (float) $row['total_amount'],
            'currency' => (string) ($row['currency'] ?: 'USD'),
            'created_at' => $row['created_at'] ?? null,
        ];
    }
}

$openTickets = 0;
if (hivenest_customer_dashboard_table_exists($db, 'support_tickets')) {
    $ticketStmt = $db->prepare("
        SELECT COUNT(*)
        FROM support_tickets
        WHERE customer_id = :customer_id
          AND status NOT IN ('closed','resolved')
    ");
    $ticketStmt->execute(['customer_id' => $customerId]);
    $openTickets = (int) $ticketStmt->fetchColumn();
}

$openServiceRequests = 0;
$recentServiceRequests = [];
if (hivenest_customer_dashboard_table_exists($db, 'service_requests')) {
    $requestCountStmt = $db->prepare("
        SELECT COUNT(*)
        FROM service_requests
        WHERE customer_id = :customer_id
          AND status IN ('pending','in_review','approved')
    ");
    $requestCountStmt->execute(['customer_id' => $customerId]);
    $openServiceRequests = (int)$requestCountStmt->fetchColumn();

    $requestStmt = $db->prepare("
        SELECT
            sr.id,
            sr.service_id,
            sr.request_type,
            sr.requested_value,
            sr.status,
            sr.message,
            sr.admin_response,
            sr.created_at,
            sr.updated_at,
            s.service_name,
            s.domain_name
        FROM service_requests sr
        INNER JOIN services s ON s.id = sr.service_id
        WHERE sr.customer_id = :customer_id
        ORDER BY sr.updated_at DESC, sr.id DESC
        LIMIT 6
    ");
    $requestStmt->execute(['customer_id' => $customerId]);
    foreach ($requestStmt->fetchAll() ?: [] as $row) {
        $recentServiceRequests[] = [
            'id' => (int)$row['id'],
            'service_id' => (int)$row['service_id'],
            'service_name' => (string)$row['service_name'],
            'domain_name' => $row['domain_name'] ?? null,
            'request_type' => (string)$row['request_type'],
            'requested_value' => $row['requested_value'] ?? null,
            'status' => (string)$row['status'],
            'message' => $row['message'] ?? null,
            'admin_response' => $row['admin_response'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}

$accountUpdates = [];
if (hivenest_customer_dashboard_table_exists($db, 'customer_notes')) {
    $notesStmt = $db->prepare("
        SELECT id, note_type, note_text, created_at
        FROM customer_notes
        WHERE customer_id = :customer_id
          AND visibility = 'client'
        ORDER BY id DESC
        LIMIT 6
    ");
    $notesStmt->execute(['customer_id' => $customerId]);
    foreach ($notesStmt->fetchAll() ?: [] as $row) {
        $accountUpdates[] = [
            'id' => (int)$row['id'],
            'note_type' => (string)$row['note_type'],
            'note_text' => (string)$row['note_text'],
            'created_at' => $row['created_at'] ?? null,
        ];
    }
}

hivenest_customer_dashboard_out(200, [
    'profile' => [
        'id' => (int) $profile['id'],
        'uuid' => (string) $profile['uuid'],
        'email' => (string) $profile['email'],
        'first_name' => (string) ($profile['first_name'] ?? ''),
        'last_name' => (string) ($profile['last_name'] ?? ''),
        'company_name' => $profile['company_name'] ?? null,
        'phone' => $profile['phone'] ?? null,
        'preferred_currency' => (string) ($profile['preferred_currency'] ?: 'USD'),
        'email_verified' => (int) ($profile['email_verified'] ?? 0) === 1,
    ],
    'stats' => [
        'active_services' => $activeServices,
        'next_payment_date' => $nextPaymentDate,
        'open_tickets' => $openTickets,
        'recent_orders' => count($recentOrders),
        'open_service_requests' => $openServiceRequests,
    ],
    'loyalty' => $loyalty,
    'recent_services' => $services,
    'workflow_reviews' => array_values(array_filter($services, static function (array $service): bool {
        $summary = $service['workflow_summary'] ?? null;
        return is_array($summary)
            && ((int)($summary['ready_for_review'] ?? 0) > 0 || (int)($summary['changes_requested'] ?? 0) > 0);
    })),
    'recent_orders' => $recentOrders,
    'service_requests' => $recentServiceRequests,
    'account_updates' => $accountUpdates,
]);
