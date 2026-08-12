<?php
// Contact Form API Handler

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../utilities/cors.php';
require_once __DIR__ . '/../utilities/rate_limiter.php';
require_once __DIR__ . '/../utilities/mail_delivery.php';

header('Content-Type: application/json; charset=utf-8');
hivenest_apply_cors(['POST'], ['Content-Type', 'Accept']);

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Only POST is accepted.']);
    exit();
}

$rateLimit = hivenest_rate_limit('public-contact', 6, 600);
header('X-RateLimit-Remaining: ' . $rateLimit['remaining']);
if (!$rateLimit['allowed']) {
    header('Retry-After: ' . $rateLimit['retry_after']);
    http_response_code(429);
    echo json_encode([
        'error' => 'Too many contact requests. Please wait before trying again.',
        'retry_after' => $rateLimit['retry_after'],
    ]);
    exit;
}

function handleContactForm() {
    // Get JSON input
    $raw_input = file_get_contents('php://input');

    $input = json_decode($raw_input, true);
    
    // Check for JSON decode errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid JSON input',
            'details' => json_last_error_msg()
        ]);
        return;
    }
    
    // Validate input
    if (!$input || !is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input format']);
        return;
    }

    if (!empty($input['website_url'])) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Thank you! Your message has been received successfully.',
        ]);
        return;
    }
    
    // Required fields
    $required_fields = ['name', 'email', 'message'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Missing required fields',
            'missing_fields' => $missing_fields
        ]);
        return;
    }
    
    // Normalize and validate input. Subject labels are server-owned so request
    // data can never inject additional mail headers.
    $name = trim(strip_tags((string)$input['name']));
    $email_raw = trim((string)$input['email']);
    $email = filter_var($email_raw, FILTER_VALIDATE_EMAIL);
    $subjectKey = strtolower(trim((string)($input['subject'] ?? 'general')));
    $subjectOptions = [
        'general' => 'General Inquiry',
        'sales' => 'Sales Request',
        'support' => 'Technical Support',
        'billing' => 'Billing Question',
        'partnership' => 'Partnership Proposal',
    ];
    $subject = $subjectOptions[$subjectKey] ?? $subjectOptions['general'];
    $message = trim(strip_tags((string)$input['message']));
    
    // Validate email
    if (!$email) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid email address',
            'provided_email' => $email_raw
        ]);
        return;
    }
    
    // Validate name length
    if (strlen($name) < 2 || strlen($name) > 120) {
        http_response_code(400);
        echo json_encode(['error' => 'Name must contain between 2 and 120 characters.']);
        return;
    }
    
    // Validate message length
    if (strlen($message) < 10 || strlen($message) > 5000) {
        http_response_code(400);
        echo json_encode(['error' => 'Message must contain between 10 and 5000 characters.']);
        return;
    }
    
    // Prepare email data
    $to_email = 'info@hivenest.co.za'; // Change this to your actual email
    $email_subject = "HiveNest Contact Form: $subject";
    $email_body = "
New contact form submission from HiveNest website:

Name: $name
Email: $email
Subject: $subject

Message:
$message

---
Sent from HiveNest Contact Form
Time: " . date('Y-m-d H:i:s') . "
    ";
    
    $headers = [
        'From: noreply@hivenest.co.za',
        'Reply-To: ' . $email,
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: HiveNest Contact Form'
    ];

    // Try to send email
    $email_sent = hivenest_mail_send($to_email, $email_subject, $email_body, implode("\r\n", $headers));

    if (!$email_sent) {
        error_log('Contact form email delivery failed.');
        http_response_code(503);
        echo json_encode([
            'error' => 'Your message could not be delivered right now. Please try again shortly or email support@hivenest.co.za.',
        ]);
        return;
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your message has been received successfully.',
    ]);
}

// Call the function
handleContactForm();
?>
