<?php
/**
 * Simplified Product Pricing - Centralized Cache System
 * All pages now read from a single cached pricing file
 */

/**
 * Get pricing for a specific product from cache
 * @param string $product_slug - The product slug (e.g., 'windows-vps', 'google-workspace-basic')
 * @param string $onclick_function - JavaScript function to call on button click
 * @return array - Formatted pricing cards array
 */
function getCachedProductPricing($product_slug, $onclick_function = 'addToCart') {
    $cache_file = __DIR__ . '/pricing_cache.json';
    
    // If cache doesn't exist, return empty
    if (!file_exists($cache_file)) {
        error_log("Pricing cache file not found: $cache_file");
        return [];
    }
    
    $pricing_data = json_decode(file_get_contents($cache_file), true);
    
    if (!$pricing_data || !isset($pricing_data[$product_slug])) {
        error_log("Product not found in cache: $product_slug");
        return [];
    }
    
    $product = $pricing_data[$product_slug];
    $pricing_tiers = $product['pricing_tiers'];
    
    if (empty($pricing_tiers)) {
        return [];
    }
    
    // Convert to pricing cards format
    $cards = [];
    
    foreach ($pricing_tiers as $tier) {
        // Format price
        $price = floatval($tier['price']);
        $formatted_price = '$' . number_format($price, 2);
        
        // Remove .00 for whole numbers
        if (floor($price) == $price) {
            $formatted_price = '$' . number_format($price, 0);
        }
        
        // Format period
        $period_map = [
            'monthly' => '/mo',
            'quarterly' => '/3mo',
            'semi_annually' => '/6mo',
            'annually' => '/yr',
            'one_time' => '',
            'per_user_monthly' => '/user/mo'
        ];
        
        $period = isset($period_map[$tier['billing_cycle']]) ? $period_map[$tier['billing_cycle']] : '/mo';
        
        // Build card
        $card = [
            'name' => $tier['tier_name'],
            'price' => $formatted_price,
            'period' => $period,
            'features' => is_array($tier['features']) ? $tier['features'] : [],
            'cta_link' => '#',
            'cta_text' => $tier['is_featured'] ? 'MOST POPULAR' : 'ADD TO CART',
            'onclick' => sprintf("%s('%s', %s)", $onclick_function, $tier['tier_slug'], $price),
            'featured' => $tier['is_featured'] == 1
        ];
        
        $cards[] = $card;
    }
    
    return $cards;
}

/**
 * Get all products from cache
 * @return array - All products with pricing
 */
function getAllCachedProducts() {
    $cache_file = __DIR__ . '/pricing_cache.json';
    
    if (!file_exists($cache_file)) {
        return [];
    }
    
    return json_decode(file_get_contents($cache_file), true) ?: [];
}

/**
 * Check if cache file exists and is recent
 * @return array - ['exists' => bool, 'age_hours' => int]
 */
function getCacheStatus() {
    $cache_file = __DIR__ . '/pricing_cache.json';
    
    if (!file_exists($cache_file)) {
        return ['exists' => false, 'age_hours' => null, 'last_modified' => null];
    }
    
    $last_modified = filemtime($cache_file);
    $age_seconds = time() - $last_modified;
    $age_hours = round($age_seconds / 3600, 1);
    
    return [
        'exists' => true,
        'age_hours' => $age_hours,
        'last_modified' => date('Y-m-d H:i:s', $last_modified)
    ];
}
