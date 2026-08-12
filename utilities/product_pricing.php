<?php
/**
 * Product Pricing Utility
 * Fetches product pricing from database dynamically.
 * DB access is centralised through /access/dbconfig.php
 */

require_once __DIR__ . '/../access/dbconfig.php';

// Include database helper if available
if (file_exists(__DIR__ . '/db_helper.php')) {
    require_once __DIR__ . '/db_helper.php';
}

/**
 * @deprecated Kept for backward compatibility — new code should call
 *             hivenest_db_credentials() directly.
 */
function loadDBCredentials() {
    $c = hivenest_db_credentials();
    return [
        'host'     => $c['host'],
        'port'     => $c['port'],
        'dbname'   => $c['dbname'],
        'username' => $c['username'],
        'password' => $c['password'],
    ];
}

/**
 * Get the central PDO connection. Returns null on failure.
 * All callers in the codebase route through this single function.
 */
function getPricingDBConnection() {
    return hivenest_db();
}

function hivenestPricingStyleColumnsAvailable($conn): bool {
    static $available = null;
    if ($available !== null) return $available;
    if (!$conn) return $available = false;
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM product_pricing LIKE 'accent_color'");
        $hasAccent = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $conn->query("SHOW COLUMNS FROM product_pricing LIKE 'glow_color'");
        $hasGlow = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        return $available = ($hasAccent && $hasGlow);
    } catch (Throwable $e) {
        return $available = false;
    }
}

/**
 * Get product pricing tiers by product slug
 * @param string $product_slug
 * @return array
 */
function getProductPricingBySlug($product_slug) {
    $conn = getPricingDBConnection();
    if (!$conn) {
        return [];
    }
    
    try {
        $styleSelect = hivenestPricingStyleColumnsAvailable($conn)
            ? 'pp.accent_color, pp.glow_color,'
            : 'NULL AS accent_color, NULL AS glow_color,';
        $stmt = $conn->prepare("
            SELECT 
                pp.id,
                pp.tier_name,
                pp.tier_slug,
                pp.tier_level,
                pp.price,
                pp.setup_fee,
                pp.billing_cycle,
                pp.features,
                {$styleSelect}
                pp.is_featured,
                pp.sort_order,
                p.name as product_name,
                p.slug as product_slug
            FROM product_pricing pp
            INNER JOIN products p ON pp.product_id = p.id
            WHERE p.slug = :slug AND pp.is_active = 1 AND p.is_active = 1
            ORDER BY pp.sort_order ASC
        ");
        
        $stmt->execute(['slug' => $product_slug]);
        $results = $stmt->fetchAll();
        
        // Decode JSON features
        foreach ($results as &$result) {
            if ($result['features']) {
                $result['features'] = json_decode($result['features'], true);
            }
        }
        
        return $results;
    } catch (PDOException $e) {
        error_log("Error fetching pricing: " . $e->getMessage());
        return [];
    }
}

/**
 * Get product pricing tiers by product ID
 * @param int $product_id
 * @return array
 */
function getProductPricingById($product_id) {
    $conn = getPricingDBConnection();
    if (!$conn) {
        return [];
    }
    
    try {
        $styleSelect = hivenestPricingStyleColumnsAvailable($conn)
            ? 'pp.accent_color, pp.glow_color,'
            : 'NULL AS accent_color, NULL AS glow_color,';
        $stmt = $conn->prepare("
            SELECT 
                pp.id,
                pp.tier_name,
                pp.tier_slug,
                pp.tier_level,
                pp.price,
                pp.setup_fee,
                pp.billing_cycle,
                pp.features,
                {$styleSelect}
                pp.is_featured,
                pp.sort_order,
                p.name as product_name,
                p.slug as product_slug
            FROM product_pricing pp
            INNER JOIN products p ON pp.product_id = p.id
            WHERE pp.product_id = :product_id AND pp.is_active = 1 AND p.is_active = 1
            ORDER BY pp.sort_order ASC
        ");
        
        $stmt->execute(['product_id' => $product_id]);
        $results = $stmt->fetchAll();
        
        // Decode JSON features
        foreach ($results as &$result) {
            if ($result['features']) {
                $result['features'] = json_decode($result['features'], true);
            }
        }
        
        return $results;
    } catch (PDOException $e) {
        error_log("Error fetching pricing: " . $e->getMessage());
        return [];
    }
}

/**
 * Format pricing for display
 * @param float $price
 * @param string $billing_cycle
 * @return array ['price' => '$19', 'period' => '/mo']
 */
function formatPricing($price, $billing_cycle = 'monthly') {
    $formatted_price = '$' . number_format($price, 2);
    
    // Remove .00 for whole numbers
    if (floor($price) == $price) {
        $formatted_price = '$' . number_format($price, 0);
    }
    
    $period_map = [
        'monthly' => '/mo',
        'quarterly' => '/3mo',
        'semi_annually' => '/6mo',
        'annually' => '/yr',
        'one_time' => '',
        'per_user_monthly' => '/user/mo'
    ];
    
    $period = isset($period_map[$billing_cycle]) ? $period_map[$billing_cycle] : '/mo';
    
    return [
        'price' => $formatted_price,
        'period' => $period
    ];
}

/**
 * Convert pricing tiers to pricing cards format
 * @param array $pricing_tiers
 * @param string $onclick_function - JavaScript function name to call on button click
 * @return array
 */
function convertToPricingCards($pricing_tiers, $onclick_function = 'addToCart') {
    $cards = [];
    
    foreach ($pricing_tiers as $tier) {
        $formatted = formatPricing($tier['price'], $tier['billing_cycle']);
        
        $card = [
            'name' => $tier['tier_name'],
            'price' => $formatted['price'],
            'period' => $formatted['period'],
            'features' => is_array($tier['features']) ? $tier['features'] : [],
            'accent_color' => $tier['accent_color'] ?? '',
            'glow_color' => $tier['glow_color'] ?? '',
            'cta_link' => '#',
            'cta_text' => $tier['is_featured'] ? 'MOST POPULAR' : 'ADD TO CART',
            'onclick' => sprintf(
                "%s('%s', %s)",
                $onclick_function,
                (($tier['product_slug'] ?? '') !== '' && strpos((string)$tier['tier_slug'], '--') === false)
                    ? $tier['product_slug'] . '--' . $tier['tier_slug']
                    : $tier['tier_slug'],
                $tier['price']
            ),
            'featured' => $tier['is_featured'] == 1
        ];
        
        $cards[] = $card;
    }
    
    return $cards;
}

/**
 * Get product base info from products table (fallback)
 * @param string $product_slug
 * @return array|null
 */
function getProductBaseInfo($product_slug) {
    $conn = getPricingDBConnection();
    if (!$conn) {
        return null;
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                id,
                name,
                slug,
                base_price,
                setup_fee,
                billing_cycle,
                features
            FROM products
            WHERE slug = :slug AND is_active = 1
        ");
        
        $stmt->execute(['slug' => $product_slug]);
        $result = $stmt->fetch();
        
        if ($result && $result['features']) {
            $result['features'] = json_decode($result['features'], true);
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error fetching product base info: " . $e->getMessage());
        return null;
    }
}

/**
 * Create default pricing tiers from product base info
 * @param array $product
 * @return array
 */
function createDefaultPricingFromProduct($product) {
    if (!$product) {
        return [];
    }
    
    $base_price = floatval($product['base_price']);
    
    // Create three tiers based on the base product
    return [
        [
            'tier_name' => 'CYBER INITIATE',
            'tier_slug' => 'initiate',
            'tier_level' => 'basic',
            'price' => $base_price,
            'setup_fee' => floatval($product['setup_fee']),
            'billing_cycle' => $product['billing_cycle'],
            'features' => is_array($product['features']) ? $product['features'] : [],
            'is_featured' => 0,
            'product_name' => $product['name'],
            'product_slug' => $product['slug']
        ]
    ];
}

/**
 * Get formatted pricing array for a product
 * @param string $product_slug
 * @param string $onclick_function
 * @return array
 */
function getFormattedProductPricing($product_slug, $onclick_function = 'addToCart') {
    $pricing_tiers = getProductPricingBySlug($product_slug);
    
    // If no pricing tiers found, try to get from product base price
    if (empty($pricing_tiers)) {
        $product = getProductBaseInfo($product_slug);
        if ($product) {
            $pricing_tiers = createDefaultPricingFromProduct($product);
        }
    }
    
    return convertToPricingCards($pricing_tiers, $onclick_function);
}

/**
 * Get single tier pricing
 * @param string $product_slug
 * @param string $tier_level (basic, standard, premium)
 * @return array|null
 */
function getSingleTierPricing($product_slug, $tier_level = 'basic') {
    $pricing_tiers = getProductPricingBySlug($product_slug);
    
    foreach ($pricing_tiers as $tier) {
        if ($tier['tier_level'] === $tier_level) {
            return $tier;
        }
    }
    
    return !empty($pricing_tiers) ? $pricing_tiers[0] : null;
}
