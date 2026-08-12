<?php
// Form Validation - Server-side form validation and sanitization
// Usage: Include this file and use validation functions

class FormValidator {
    private $errors = [];
    private $data = [];
    
    public function __construct($data = []) {
        $this->data = $data;
    }
    
    // Validate required field
    public function required($field, $message = null) {
        if (!isset($this->data[$field]) || empty(trim($this->data[$field]))) {
            $this->errors[$field] = $message ?: ucfirst($field) . ' is required';
        }
        return $this;
    }
    
    // Validate email
    public function email($field, $message = null) {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message ?: 'Please enter a valid email address';
        }
        return $this;
    }
    
    // Validate minimum length
    public function minLength($field, $length, $message = null) {
        if (isset($this->data[$field]) && strlen($this->data[$field]) < $length) {
            $this->errors[$field] = $message ?: ucfirst($field) . " must be at least {$length} characters long";
        }
        return $this;
    }
    
    // Validate maximum length
    public function maxLength($field, $length, $message = null) {
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $length) {
            $this->errors[$field] = $message ?: ucfirst($field) . " must not exceed {$length} characters";
        }
        return $this;
    }
    
    // Validate phone number
    public function phone($field, $message = null) {
        if (isset($this->data[$field])) {
            $phone = preg_replace('/[^0-9+]/', '', $this->data[$field]);
            if (!preg_match('/^[\+]?[1-9][\d]{7,14}$/', $phone)) {
                $this->errors[$field] = $message ?: 'Please enter a valid phone number';
            }
        }
        return $this;
    }
    
    // Validate URL
    public function url($field, $message = null) {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
            $this->errors[$field] = $message ?: 'Please enter a valid URL';
        }
        return $this;
    }
    
    // Validate numeric
    public function numeric($field, $message = null) {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field] = $message ?: ucfirst($field) . ' must be a number';
        }
        return $this;
    }
    
    // Validate in array
    public function in($field, $allowed, $message = null) {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowed)) {
            $this->errors[$field] = $message ?: ucfirst($field) . ' contains an invalid value';
        }
        return $this;
    }
    
    // Validate domain name
    public function domain($field, $message = null) {
        if (isset($this->data[$field])) {
            $domain = strtolower(trim($this->data[$field]));
            if (!preg_match('/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)*$/i', $domain)) {
                $this->errors[$field] = $message ?: 'Please enter a valid domain name';
            }
        }
        return $this;
    }
    
    // Check if validation passed
    public function passes() {
        return empty($this->errors);
    }
    
    // Check if validation failed
    public function fails() {
        return !empty($this->errors);
    }
    
    // Get all errors
    public function getErrors() {
        return $this->errors;
    }
    
    // Get error for specific field
    public function getError($field) {
        return isset($this->errors[$field]) ? $this->errors[$field] : null;
    }
    
    // Get sanitized data
    public function getData() {
        return $this->sanitizeData($this->data);
    }
    
    // Sanitize input data
    private function sanitizeData($data) {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
}

// Generate CSRF token
function generateCSRFToken() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validateCSRFToken($token) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Generate CSRF hidden input
function csrfInput() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

// Rate limiting
function checkRateLimit($identifier, $limit = 5, $window = 300) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $key = 'rate_limit_' . md5($identifier);
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    
    // Clean old entries
    $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($now, $window) {
        return ($now - $timestamp) < $window;
    });
    
    // Check if limit exceeded
    if (count($_SESSION[$key]) >= $limit) {
        return false;
    }
    
    // Add current timestamp
    $_SESSION[$key][] = $now;
    
    return true;
}

// Honeypot field (spam protection)
function honeypotField($name = 'website_url') {
    return '<input type="text" name="' . htmlspecialchars($name) . '" style="display: none !important;" tabindex="-1" autocomplete="off">';
}

// Check honeypot
function checkHoneypot($data, $field = 'website_url') {
    return empty($data[$field]);
}
?>