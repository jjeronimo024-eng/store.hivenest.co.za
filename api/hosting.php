<?php
// Hosting Plans API Handler
require_once __DIR__ . '/../utilities/db_helper.php';

function getHostingPlans() {
    global $db_helper;
    
    // Get hosting products from database
    $db_products = $db_helper->getProductsByType('hosting');
    
    // Transform to API format
    $hosting_plans = [];
    foreach ($db_products as $product) {
        $hosting_plans[] = [
            'id' => $product['id'],
            'name' => strtoupper($product['name']),
            'price' => floatval($product['base_price']),
            'currency' => 'USD',
            'period' => strtolower($product['billing_cycle']),
            'description' => $product['short_description'] ?? 'Premium hosting solution',
            'features' => $product['features'] ?? [],
            'popular' => $product['is_featured'] == 1,
            'setup_fee' => floatval($product['setup_fee'] ?? 0)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $hosting_plans,
        'count' => count($hosting_plans)
    ]);
}

function getHostingPlan($plan_id) {
    global $db_helper;
    
    // Get product from database
    $product = $db_helper->getProduct($plan_id);
    
    if ($product && $product['product_type'] === 'hosting') {
        $plan = [
            'id' => $product['id'],
            'name' => strtoupper($product['name']),
            'price' => floatval($product['base_price']),
            'currency' => 'USD',
            'period' => strtolower($product['billing_cycle']),
            'description' => $product['short_description'] ?? 'Premium hosting solution',
            'features' => $product['features'] ?? [],
            'popular' => $product['is_featured'] == 1,
            'setup_fee' => floatval($product['setup_fee'] ?? 0)
        ];
        
        echo json_encode([
            'success' => true,
            'data' => $plan
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Hosting plan not found']);
    }
}
?>