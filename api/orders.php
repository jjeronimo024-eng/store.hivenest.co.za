<?php
// Orders API Handler
require_once __DIR__ . '/../utilities/mail_delivery.php';

function submitOrder() {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        return;
    }
    
    // Validate required fields
    $required_fields = ['customer_email', 'items', 'billing_info'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }
    
    // Validate email
    $customer_email = filter_var($input['customer_email'], FILTER_VALIDATE_EMAIL);
    if (!$customer_email) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email address']);
        return;
    }
    
    // Validate items
    if (!is_array($input['items']) || empty($input['items'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Order must contain at least one item']);
        return;
    }
    
    // Calculate total
    $total_amount = 0;
    $processed_items = [];
    
    foreach ($input['items'] as $item) {
        if (empty($item['id']) || empty($item['price']) || empty($item['quantity'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid item data']);
            return;
        }
        
        $item_total = floatval($item['price']) * intval($item['quantity']);
        $total_amount += $item_total;
        
        $processed_items[] = [
            'id' => $item['id'],
            'name' => isset($item['name']) ? $item['name'] : 'Unknown Item',
            'price' => floatval($item['price']),
            'quantity' => intval($item['quantity']),
            'total' => $item_total
        ];
    }
    
    // Generate order ID
    $order_id = 'HN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    // Create order data
    $order_data = [
        'order_id' => $order_id,
        'customer_email' => $customer_email,
        'items' => $processed_items,
        'billing_info' => $input['billing_info'],
        'total_amount' => $total_amount,
        'currency' => isset($input['currency']) ? $input['currency'] : 'USD',
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'payment_method' => isset($input['payment_method']) ? $input['payment_method'] : 'pending'
    ];
    
    // Save order to file
    $orders_file = '../data/orders.json';
    if (!file_exists('../data')) {
        mkdir('../data', 0755, true);
    }
    
    // Load existing orders
    $orders = [];
    if (file_exists($orders_file)) {
        $orders_json = file_get_contents($orders_file);
        $orders = json_decode($orders_json, true) ?: [];
    }
    
    // Add new order
    $orders[] = $order_data;
    
    // Save orders
    $result = file_put_contents($orders_file, json_encode($orders, JSON_PRETTY_PRINT));
    
    if ($result !== false) {
        // Log order
        $log_entry = date('Y-m-d H:i:s') . " - New order: $order_id ($customer_email) - Total: {$order_data['currency']} {$total_amount}\n";
        file_put_contents('../logs/orders.log', $log_entry, FILE_APPEND | LOCK_EX);
        
        // Send order confirmation email
        sendOrderConfirmationEmail($order_data);
        
        echo json_encode([
            'success' => true,
            'message' => 'Order submitted successfully',
            'data' => [
                'order_id' => $order_id,
                'total_amount' => $total_amount,
                'currency' => $order_data['currency'],
                'status' => 'pending',
                'estimated_setup_time' => '24-48 hours'
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save order']);
    }
}

function sendOrderConfirmationEmail($order_data) {
    $to = $order_data['customer_email'];
    $subject = "HiveNest Order Confirmation - {$order_data['order_id']}";
    
    $body = "
Thank you for your order with HiveNest Matrix!

Order Details:
Order ID: {$order_data['order_id']}
Date: {$order_data['created_at']}
Status: {$order_data['status']}

Items Ordered:
";
    
    foreach ($order_data['items'] as $item) {
        $body .= "- {$item['name']} x{$item['quantity']} = {$order_data['currency']} {$item['total']}\n";
    }
    
    $body .= "
Total Amount: {$order_data['currency']} {$order_data['total_amount']}

We will process your order within 24-48 hours and send you the setup details.

If you have any questions, please contact us at support@hivenest.co.za

Welcome to the Matrix!

---
HiveNest Team
https://hivenest.co.za
    ";
    
    $headers = [
        'From: orders@hivenest.co.za',
        'Reply-To: support@hivenest.co.za',
        'Content-Type: text/plain; charset=UTF-8'
    ];
    
    hivenest_mail_send($to, $subject, $body, implode("\r\n", $headers));
}
?>
