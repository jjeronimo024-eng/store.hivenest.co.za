<?php
declare(strict_types=1);
require_once __DIR__ . '/mail_delivery.php';

function hivenest_support_mail_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hivenest_support_mail_send(string $to, string $subject, string $html): bool
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: HiveNest Support <support@hivenest.co.za>',
        'Reply-To: HiveNest Support <support@hivenest.co.za>',
    ];

    return hivenest_mail_send($to, $subject, $html, implode("\r\n", $headers));
}

function hivenest_support_email_shell(string $title, string $bodyHtml): string
{
    return '<!doctype html><html><body style="margin:0;background:#05070d;color:#d7e7ec;font-family:Arial,sans-serif;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#05070d;padding:28px 0;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="max-width:640px;width:94%;background:#0b101a;border:1px solid #00ffff;border-radius:18px;overflow:hidden;">'
        . '<tr><td style="padding:26px 30px;background:linear-gradient(135deg,#101b2e,#21103a);border-bottom:1px solid rgba(0,255,255,.35);">'
        . '<h1 style="margin:0;color:#00ffff;font-size:28px;letter-spacing:2px;">HIVENEST SUPPORT</h1>'
        . '<p style="margin:8px 0 0;color:#ff00ff;font-weight:bold;">' . hivenest_support_mail_escape($title) . '</p>'
        . '</td></tr>'
        . '<tr><td style="padding:28px 30px;line-height:1.65;color:#d7e7ec;">' . $bodyHtml . '</td></tr>'
        . '<tr><td style="padding:18px 30px;border-top:1px solid rgba(0,255,255,.25);color:#9fb2bb;font-size:13px;">'
        . 'HiveNest Support · <a href="mailto:support@hivenest.co.za" style="color:#00ffff;">support@hivenest.co.za</a>'
        . '</td></tr></table></td></tr></table></body></html>';
}

function hivenest_support_notify_team_new_ticket(array $ticket, array $customer, string $message): bool
{
    $number = hivenest_support_mail_escape((string)($ticket['ticket_number'] ?? ''));
    $subject = hivenest_support_mail_escape((string)($ticket['subject'] ?? 'Support ticket'));
    $customerName = trim((string)($customer['first_name'] ?? '') . ' ' . (string)($customer['last_name'] ?? ''));
    if ($customerName === '') $customerName = (string)($customer['company_name'] ?? 'Customer');
    $email = hivenest_support_mail_escape((string)($customer['email'] ?? ''));

    $body = '<h2 style="color:#00ffff;margin-top:0;">New support ticket created</h2>'
        . '<p><strong>Ticket:</strong> ' . $number . '</p>'
        . '<p><strong>Subject:</strong> ' . $subject . '</p>'
        . '<p><strong>Customer:</strong> ' . hivenest_support_mail_escape($customerName) . ' &lt;' . $email . '&gt;</p>'
        . '<p><strong>Priority:</strong> ' . hivenest_support_mail_escape((string)($ticket['priority'] ?? 'medium')) . '</p>'
        . '<p><strong>Category:</strong> ' . hivenest_support_mail_escape((string)($ticket['category'] ?? 'general')) . '</p>'
        . '<div style="margin-top:18px;padding:16px;border:1px solid rgba(0,255,255,.35);border-radius:12px;background:rgba(0,255,255,.06);white-space:pre-wrap;">'
        . hivenest_support_mail_escape($message)
        . '</div>'
        . '<p style="margin-top:22px;"><a href="https://crm.hivenest.co.za/support/index.html" style="display:inline-block;background:#00ffff;color:#080b12;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:bold;">OPEN CRM SUPPORT QUEUE</a></p>';

    return hivenest_support_mail_send('support@hivenest.co.za', 'New HiveNest Support Ticket - ' . (string)($ticket['ticket_number'] ?? ''), hivenest_support_email_shell('New Ticket', $body));
}

function hivenest_support_notify_client_reply(array $ticket, string $customerEmail, string $message, string $status = ''): bool
{
    $body = '<h2 style="color:#00ffff;margin-top:0;">Your support ticket has been updated</h2>'
        . '<p><strong>Ticket:</strong> ' . hivenest_support_mail_escape((string)($ticket['ticket_number'] ?? '')) . '</p>'
        . '<p><strong>Subject:</strong> ' . hivenest_support_mail_escape((string)($ticket['subject'] ?? 'Support ticket')) . '</p>';

    if ($status !== '') {
        $body .= '<p><strong>Status:</strong> ' . hivenest_support_mail_escape($status) . '</p>';
    }
    if ($message !== '') {
        $body .= '<div style="margin-top:18px;padding:16px;border:1px solid rgba(255,0,255,.35);border-radius:12px;background:rgba(255,0,255,.08);white-space:pre-wrap;">'
            . hivenest_support_mail_escape($message)
            . '</div>';
    }

    $body .= '<p style="margin-top:22px;"><a href="https://cp.hivenest.co.za/support/create-ticket.html" style="display:inline-block;background:#00ffff;color:#080b12;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:bold;">VIEW TICKET IN CLIENT PORTAL</a></p>';

    return hivenest_support_mail_send($customerEmail, 'HiveNest Support Ticket Updated - ' . (string)($ticket['ticket_number'] ?? ''), hivenest_support_email_shell('Ticket Updated', $body));
}

function hivenest_service_request_notify_client(
    PDO $db,
    array $service,
    array $request,
    string $status,
    string $response = ''
): bool {
    $customerId = (int)($service['customer_id'] ?? 0);
    $serviceId = (int)($service['id'] ?? 0);
    if ($customerId <= 0 || $serviceId <= 0) return false;

    $customerStmt = $db->prepare("
        SELECT email, first_name, last_name, company_name
        FROM customers
        WHERE id = :id
        LIMIT 1
    ");
    $customerStmt->execute(['id' => $customerId]);
    $customer = $customerStmt->fetch();
    if (!$customer || empty($customer['email'])) return false;

    $customerName = trim((string)($customer['first_name'] ?? '') . ' ' . (string)($customer['last_name'] ?? ''));
    if ($customerName === '') $customerName = (string)($customer['company_name'] ?? 'Customer');
    $requestId = (int)($request['id'] ?? 0);
    $requestType = str_replace('_', ' ', (string)($request['request_type'] ?? 'service request'));
    $serviceName = (string)($service['service_name'] ?? 'HiveNest Service');
    $statusLabel = str_replace('_', ' ', $status);
    $portalUrl = 'https://cp.hivenest.co.za/services/manage.html?service=' . rawurlencode((string)$serviceId);

    $body = '<h2 style="color:#00ffff;margin-top:0;">Your service request has been updated</h2>'
        . '<p>Hello ' . hivenest_support_mail_escape($customerName) . ',</p>'
        . '<p><strong>Request:</strong> #' . hivenest_support_mail_escape((string)$requestId) . ' · ' . hivenest_support_mail_escape(ucwords($requestType)) . '</p>'
        . '<p><strong>Service:</strong> ' . hivenest_support_mail_escape($serviceName) . '</p>'
        . (!empty($service['domain_name']) ? '<p><strong>Domain:</strong> ' . hivenest_support_mail_escape((string)$service['domain_name']) . '</p>' : '')
        . '<p><strong>New status:</strong> ' . hivenest_support_mail_escape(ucwords($statusLabel)) . '</p>';

    if ($response !== '') {
        $body .= '<div style="margin-top:18px;padding:16px;border:1px solid rgba(255,0,255,.35);border-radius:12px;background:rgba(255,0,255,.08);white-space:pre-wrap;">'
            . '<strong>HiveNest response</strong><br>'
            . hivenest_support_mail_escape($response)
            . '</div>';
    }

    $body .= '<p style="margin-top:22px;"><a href="' . hivenest_support_mail_escape($portalUrl) . '" style="display:inline-block;background:#00ffff;color:#080b12;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:bold;">VIEW REQUEST IN CLIENT PORTAL</a></p>';

    return hivenest_support_mail_send(
        (string)$customer['email'],
        'HiveNest Service Request #' . $requestId . ' Updated',
        hivenest_support_email_shell('Service Request Updated', $body)
    );
}

function hivenest_service_request_notify_submission(
    PDO $db,
    array $service,
    array $request
): array {
    $customerId = (int)($service['customer_id'] ?? 0);
    $serviceId = (int)($service['id'] ?? 0);
    $requestId = (int)($request['id'] ?? 0);
    if ($customerId <= 0 || $serviceId <= 0 || $requestId <= 0) {
        return ['client' => false, 'team' => false];
    }

    $customerStmt = $db->prepare("
        SELECT email, first_name, last_name, company_name
        FROM customers
        WHERE id = :id
        LIMIT 1
    ");
    $customerStmt->execute(['id' => $customerId]);
    $customer = $customerStmt->fetch();
    if (!$customer || empty($customer['email'])) {
        return ['client' => false, 'team' => false];
    }

    $customerName = trim((string)($customer['first_name'] ?? '') . ' ' . (string)($customer['last_name'] ?? ''));
    if ($customerName === '') $customerName = (string)($customer['company_name'] ?? 'Customer');
    $requestType = str_replace('_', ' ', (string)($request['request_type'] ?? 'service request'));
    $serviceName = (string)($service['service_name'] ?? 'HiveNest Service');
    $message = (string)($request['message'] ?? '');
    $requestedValue = (string)($request['requested_value'] ?? '');
    $clientUrl = 'https://cp.hivenest.co.za/services/manage.html?service=' . rawurlencode((string)$serviceId);
    $crmUrl = 'https://crm.hivenest.co.za/services/index.html?service='
        . rawurlencode((string)$serviceId)
        . '&request='
        . rawurlencode((string)$requestId);

    $summary = '<p><strong>Request:</strong> #' . hivenest_support_mail_escape((string)$requestId) . ' · ' . hivenest_support_mail_escape(ucwords($requestType)) . '</p>'
        . '<p><strong>Service:</strong> ' . hivenest_support_mail_escape($serviceName) . '</p>'
        . (!empty($service['domain_name']) ? '<p><strong>Domain:</strong> ' . hivenest_support_mail_escape((string)$service['domain_name']) . '</p>' : '')
        . ($requestedValue !== '' ? '<p><strong>Requested value:</strong> ' . hivenest_support_mail_escape($requestedValue) . '</p>' : '')
        . ($message !== '' ? '<div style="margin-top:18px;padding:16px;border:1px solid rgba(0,255,255,.35);border-radius:12px;background:rgba(0,255,255,.06);white-space:pre-wrap;">' . hivenest_support_mail_escape($message) . '</div>' : '');

    $clientBody = '<h2 style="color:#00ffff;margin-top:0;">We received your service request</h2>'
        . '<p>Hello ' . hivenest_support_mail_escape($customerName) . ',</p>'
        . '<p>Your request is now in the HiveNest CRM work queue. You can follow its status and see the team response in your client portal.</p>'
        . $summary
        . '<p style="margin-top:22px;"><a href="' . hivenest_support_mail_escape($clientUrl) . '" style="display:inline-block;background:#00ffff;color:#080b12;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:bold;">VIEW REQUEST IN CLIENT PORTAL</a></p>';

    $teamBody = '<h2 style="color:#00ffff;margin-top:0;">New client service request</h2>'
        . '<p><strong>Customer:</strong> ' . hivenest_support_mail_escape($customerName) . ' &lt;' . hivenest_support_mail_escape((string)$customer['email']) . '&gt;</p>'
        . $summary
        . '<p style="margin-top:22px;"><a href="' . hivenest_support_mail_escape($crmUrl) . '" style="display:inline-block;background:#00ffff;color:#080b12;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:bold;">MANAGE REQUEST IN CRM</a></p>';

    return [
        'client' => hivenest_support_mail_send(
            (string)$customer['email'],
            'HiveNest Service Request #' . $requestId . ' Received',
            hivenest_support_email_shell('Request Received', $clientBody)
        ),
        'team' => hivenest_support_mail_send(
            'support@hivenest.co.za',
            'New HiveNest Service Request #' . $requestId . ' - ' . $serviceName,
            hivenest_support_email_shell('New Service Request', $teamBody)
        ),
    ];
}

function hivenest_work_queue_notify_assignment(
    PDO $db,
    array $job,
    array $payload,
    int $assigneeId,
    string $assignedBy = 'HiveNest CRM'
): bool {
    if ($assigneeId <= 0) return false;

    $adminStmt = $db->prepare("
        SELECT username, email, first_name, last_name
        FROM admin_users
        WHERE id = :id
          AND is_active = 1
        LIMIT 1
    ");
    $adminStmt->execute(['id' => $assigneeId]);
    $assignee = $adminStmt->fetch();
    if (!$assignee || empty($assignee['email'])) return false;

    $customerStmt = $db->prepare("
        SELECT email, first_name, last_name, company_name
        FROM customers
        WHERE id = :id
        LIMIT 1
    ");
    $customerStmt->execute(['id' => (int)($job['customer_id'] ?? 0)]);
    $customer = $customerStmt->fetch() ?: [];

    $orderNumber = '';
    if (!empty($job['order_id'])) {
        $orderStmt = $db->prepare('SELECT order_number FROM orders WHERE id = :id LIMIT 1');
        $orderStmt->execute(['id' => (int)$job['order_id']]);
        $orderNumber = (string)($orderStmt->fetchColumn() ?: '');
    }

    $assigneeName = trim((string)($assignee['first_name'] ?? '') . ' ' . (string)($assignee['last_name'] ?? ''));
    if ($assigneeName === '') $assigneeName = (string)($assignee['username'] ?? 'Team member');
    $customerName = trim((string)($customer['first_name'] ?? '') . ' ' . (string)($customer['last_name'] ?? ''));
    if ($customerName === '') $customerName = (string)($customer['company_name'] ?? $customer['email'] ?? 'Customer');
    $productName = (string)($payload['product_name'] ?? $payload['service_name'] ?? 'HiveNest work item');
    $jobType = str_replace('_', ' ', (string)($job['job_type'] ?? 'manual queue'));
    $queueUrl = 'https://crm.hivenest.co.za/work-queue/index.html'
        . ($orderNumber !== '' ? '?q=' . rawurlencode($orderNumber) : '');

    $body = '<h2 style="color:#00ffff;margin-top:0;">A CRM work item was assigned to you</h2>'
        . '<p>Hello ' . hivenest_support_mail_escape($assigneeName) . ',</p>'
        . '<p><strong>Job:</strong> #' . hivenest_support_mail_escape((string)($job['id'] ?? '')) . ' · ' . hivenest_support_mail_escape(ucwords($jobType)) . '</p>'
        . '<p><strong>Product / service:</strong> ' . hivenest_support_mail_escape($productName) . '</p>'
        . ($orderNumber !== '' ? '<p><strong>Order:</strong> ' . hivenest_support_mail_escape($orderNumber) . '</p>' : '')
        . '<p><strong>Customer:</strong> ' . hivenest_support_mail_escape($customerName) . '</p>'
        . (!empty($customer['email']) ? '<p><strong>Customer email:</strong> ' . hivenest_support_mail_escape((string)$customer['email']) . '</p>' : '')
        . (!empty($payload['domain_name']) ? '<p><strong>Domain:</strong> ' . hivenest_support_mail_escape((string)$payload['domain_name']) . '</p>' : '')
        . '<p><strong>Assigned by:</strong> ' . hivenest_support_mail_escape($assignedBy) . '</p>'
        . '<p style="margin-top:22px;"><a href="' . hivenest_support_mail_escape($queueUrl) . '" style="display:inline-block;background:#00ffff;color:#080b12;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:bold;">OPEN CRM WORK QUEUE</a></p>';

    return hivenest_support_mail_send(
        (string)$assignee['email'],
        'HiveNest CRM Work Item Assigned - Job #' . (string)($job['id'] ?? ''),
        hivenest_support_email_shell('Work Item Assigned', $body)
    );
}
