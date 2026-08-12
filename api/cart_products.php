<?php
/**
 * Cart API - Fetch product prices from database
 * Provides product information for cart items
 */

require_once __DIR__ . '/../utilities/cors.php';

header('Content-Type: application/json; charset=utf-8');
hivenest_apply_cors(['GET'], ['Content-Type', 'Accept']);
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'GET required.']);
    exit;
}

require_once '../utilities/db_helper.php';

try {
    $db_helper = new DatabaseHelper();
    
    $action = $_GET['action'] ?? 'get_product';
    
    switch ($action) {
        case 'get_product':
            $productId = $_GET['id'] ?? null;
            $productSlug = $_GET['slug'] ?? null;
            
            if ($productSlug) {
                $product = $db_helper->getProductBySlug($productSlug);
            } elseif ($productId) {
                // Get product by ID (need to add this method to db_helper if needed)
                $product = null; // Placeholder
            } else {
                throw new Exception('Product ID or slug required');
            }
            
            if (!$product) {
                throw new Exception('Product not found');
            }
            
            echo json_encode([
                'success' => true,
                'product' => $product
            ]);
            break;
            
        case 'get_all_products':
            $type = $_GET['type'] ?? null;
            
            if ($type) {
                $products = $db_helper->getProductsByType($type);
            } else {
                $products = $db_helper->getAllProducts();
            }
            
            echo json_encode([
                'success' => true,
                'products' => $products
            ]);
            break;
            
        case 'format_price':
            $price = $_GET['price'] ?? 0;
            $billingCycle = $_GET['billing_cycle'] ?? 'monthly';
            
            $formattedPrice = $db_helper->formatPrice($price, $billingCycle);
            
            echo json_encode([
                'success' => true,
                'formatted_price' => $formattedPrice
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
