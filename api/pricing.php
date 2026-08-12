<?php
/**
 * Pricing API
 * Serves all pricing data from database
 */

require_once __DIR__ . '/../utilities/cors.php';

header('Content-Type: application/json; charset=utf-8');
hivenest_apply_cors(['GET'], ['Content-Type', 'Accept']);
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'GET required.']);
    exit;
}

require_once '../utilities/product_pricing.php';

$action = $_GET['action'] ?? 'get-all-pricing';

switch ($action) {
    case 'get-domain-pricing':
        getDomainExtensionsPricing();
        break;
    
    case 'get-product-pricing':
        $slug = $_GET['slug'] ?? '';
        getProductPricingBySlugAPI($slug);
        break;
    
    case 'get-addons':
        $product_type = $_GET['product_type'] ?? '';
        getAddonsByProductType($product_type);
        break;
    
    case 'get-all-pricing':
        getAllPricing();
        break;
    
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Unknown action']);
}

/**
 * Get domain extensions pricing from database
 */
function getDomainExtensionsPricing() {
    $conn = getPricingDBConnection();
    
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        return;
    }
    
    try {
        $stmt = $conn->query("
            SELECT 
                extension,
                register_price,
                renew_price,
                transfer_price,
                restore_price,
                is_popular,
                is_new,
                category,
                description
            FROM domain_extensions
            WHERE is_active = 1
            ORDER BY 
                is_popular DESC,
                register_price ASC
        ");
        
        $extensions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $extensions,
            'count' => count($extensions)
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database query failed: ' . $e->getMessage()]);
    }
}

/**
 * Get product pricing by slug
 */
function getProductPricingBySlugAPI($slug) {
    if (empty($slug)) {
        http_response_code(400);
        echo json_encode(['error' => 'Product slug is required']);
        return;
    }
    
    $pricing = getProductPricingBySlug($slug);
    
    echo json_encode([
        'success' => true,
        'data' => $pricing,
        'count' => count($pricing)
    ]);
}

/**
 * Get addons by product type
 */
function getAddonsByProductType($product_type) {
    $conn = getPricingDBConnection();
    
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        return;
    }
    
    try {
        $stmt = $conn->query("
            SELECT 
                addon_name,
                addon_slug,
                addon_type,
                description,
                price,
                billing_cycle,
                applies_to_product_types
            FROM product_addons
            WHERE is_active = 1
            ORDER BY addon_name ASC
        ");
        
        $addons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Filter by product type if specified
        if ($product_type) {
            $addons = array_filter($addons, function($addon) use ($product_type) {
                $applies_to = json_decode($addon['applies_to_product_types'], true);
                return in_array($product_type, $applies_to);
            });
        }
        
        echo json_encode([
            'success' => true,
            'data' => array_values($addons),
            'count' => count($addons)
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database query failed: ' . $e->getMessage()]);
    }
}

/**
 * Get all pricing (domains + products + addons)
 */
function getAllPricing() {
    $conn = getPricingDBConnection();
    
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        return;
    }
    
    try {
        // Get domain extensions
        $stmt = $conn->query("SELECT * FROM domain_extensions WHERE is_active = 1 ORDER BY is_popular DESC, extension ASC");
        $domains = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get products with pricing
        $stmt = $conn->query("
            SELECT 
                p.*,
                (SELECT JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'id', pp.id,
                        'tier_name', pp.tier_name,
                        'price', pp.price,
                        'billing_cycle', pp.billing_cycle,
                        'is_featured', pp.is_featured
                    )
                ) FROM product_pricing pp WHERE pp.product_id = p.id AND pp.is_active = 1) as pricing_tiers
            FROM products p
            WHERE p.is_active = 1
            ORDER BY p.product_type, p.id
        ");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get addons
        $stmt = $conn->query("SELECT * FROM product_addons WHERE is_active = 1 ORDER BY addon_name ASC");
        $addons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'domains' => $domains,
                'products' => $products,
                'addons' => $addons
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database query failed: ' . $e->getMessage()]);
    }
}
?>
