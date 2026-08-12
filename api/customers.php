<?php
declare(strict_types=1);

// This legacy JSON-file customer API is intentionally retired. Keeping a
// second password store would bypass database sessions, email verification,
// rate limits and two-factor authentication.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
http_response_code(410);
echo json_encode([
    'error' => 'This legacy customer endpoint is retired.',
    'login_endpoint' => '/api/customer-auth.php?action=login',
    'registration_endpoint' => '/api/customer-auth.php?action=register',
], JSON_UNESCAPED_SLASHES);
exit;

// Customer Management API Handler

function handleCustomerRegistration() {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        return;
    }
    
    // Required fields
    $required_fields = ['email', 'password', 'first_name', 'last_name'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }
    
    // Sanitize and validate
    $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
    $first_name = filter_var($input['first_name'], FILTER_SANITIZE_STRING);
    $last_name = filter_var($input['last_name'], FILTER_SANITIZE_STRING);
    $password = $input['password'];
    $phone = isset($input['phone']) ? filter_var($input['phone'], FILTER_SANITIZE_STRING) : '';
    
    if (!$email) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email address']);
        return;
    }
    
    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 8 characters long']);
        return;
    }
    
    // Create customers file if it doesn't exist
    $customers_file = '../data/customers.json';
    if (!file_exists('../data')) {
        mkdir('../data', 0755, true);
    }
    
    // Load existing customers
    $customers = [];
    if (file_exists($customers_file)) {
        $customers_json = file_get_contents($customers_file);
        $customers = json_decode($customers_json, true) ?: [];
    }
    
    // Check if email already exists
    foreach ($customers as $customer) {
        if ($customer['email'] === $email) {
            http_response_code(409);
            echo json_encode(['error' => 'Email address already registered']);
            return;
        }
    }
    
    // Create new customer
    $customer_id = 'cust_' . uniqid();
    $new_customer = [
        'id' => $customer_id,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_BCRYPT),
        'first_name' => $first_name,
        'last_name' => $last_name,
        'phone' => $phone,
        'created_at' => date('Y-m-d H:i:s'),
        'status' => 'active'
    ];
    
    // Add to customers array
    $customers[] = $new_customer;
    
    // Save to file
    $result = file_put_contents($customers_file, json_encode($customers, JSON_PRETTY_PRINT));
    
    // Log registration
    $log_entry = date('Y-m-d H:i:s') . " - Customer registration: $email ($customer_id)\n";
    file_put_contents('../logs/customer_registrations.log', $log_entry, FILE_APPEND | LOCK_EX);
    
    if ($result !== false) {
        // Remove password from response
        unset($new_customer['password']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Customer registered successfully',
            'data' => $new_customer
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save customer data']);
    }
}

function handleCustomerLogin() {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (!$input || empty($input['email']) || empty($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password are required']);
        return;
    }
    
    $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
    $password = $input['password'];
    
    if (!$email) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email address']);
        return;
    }
    
    // Load customers
    $customers_file = '../data/customers.json';
    if (!file_exists($customers_file)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        return;
    }
    
    $customers_json = file_get_contents($customers_file);
    $customers = json_decode($customers_json, true) ?: [];
    
    // Find customer and verify password
    foreach ($customers as $customer) {
        if ($customer['email'] === $email) {
            if (password_verify($password, $customer['password'])) {
                // Login successful
                session_start();
                $_SESSION['customer_id'] = $customer['id'];
                $_SESSION['customer_email'] = $customer['email'];
                
                // Remove password from response
                unset($customer['password']);
                
                // Log login
                $log_entry = date('Y-m-d H:i:s') . " - Customer login: $email ({$customer['id']})\n";
                file_put_contents('../logs/customer_logins.log', $log_entry, FILE_APPEND | LOCK_EX);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'data' => $customer
                ]);
                return;
            }
            break;
        }
    }
    
    // Login failed
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
}
?>
