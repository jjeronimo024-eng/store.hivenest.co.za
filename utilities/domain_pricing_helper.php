<?php
/**
 * Domain Pricing Helper
 * Fetches domain TLD pricing from database dynamically
 */

require_once __DIR__ . '/product_pricing.php';

/**
 * Get all active domain extensions with pricing
 * @return array
 */
function getAllDomainExtensions() {
    $conn = getPricingDBConnection();
    
    if (!$conn) {
        return getFallbackDomainPricing();
    }
    
    try {
        $stmt = $conn->query("
            SELECT 
                extension,
                register_price,
                renew_price,
                transfer_price,
                is_popular,
                category,
                description
            FROM domain_extensions
            WHERE is_active = 1
            ORDER BY is_popular DESC, register_price ASC
        ");
        
        $extensions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($extensions)) {
            return getFallbackDomainPricing();
        }

        // A live database may contain only the original starter TLDs. Merge
        // those authoritative rows into the complete cache so neither the
        // register dropdown nor extension browser is silently limited.
        $complete = [];
        foreach (getFallbackDomainPricing() as $extension) {
            $complete[strtolower($extension['extension'])] = $extension;
        }
        foreach ($extensions as $extension) {
            $complete[strtolower($extension['extension'])] = $extension;
        }

        $extensions = array_values($complete);
        usort($extensions, static function ($a, $b) {
            $popular = ((int) ($b['is_popular'] ?? 0)) <=> ((int) ($a['is_popular'] ?? 0));
            return $popular !== 0
                ? $popular
                : ((float) $a['register_price'] <=> (float) $b['register_price']);
        });

        return $extensions;
        
    } catch (PDOException $e) {
        error_log("Error fetching domain extensions: " . $e->getMessage());
        return getFallbackDomainPricing();
    }
}

/**
 * Get domain extension price by TLD
 * @param string $tld (e.g., '.com', '.net')
 * @return float
 */
function getDomainPrice($tld) {
    $conn = getPricingDBConnection();
    
    if (!$conn) {
        return getFallbackPriceForTLD($tld);
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT register_price 
            FROM domain_extensions 
            WHERE extension = :tld AND is_active = 1
        ");
        $stmt->execute(['tld' => $tld]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? floatval($result['register_price']) : getFallbackPriceForTLD($tld);
        
    } catch (PDOException $e) {
        error_log("Error fetching domain price: " . $e->getMessage());
        return getFallbackPriceForTLD($tld);
    }
}

/**
 * Get all addon pricing
 * @return array
 */
function getAllAddons() {
    $conn = getPricingDBConnection();
    
    if (!$conn) {
        return getFallbackAddons();
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
        
        return !empty($addons) ? $addons : getFallbackAddons();
        
    } catch (PDOException $e) {
        error_log("Error fetching addons: " . $e->getMessage());
        return getFallbackAddons();
    }
}

/**
 * Fallback domain pricing if database is unavailable
 * @return array
 */
function getFallbackDomainPricing() {
    $cache_file = __DIR__ . '/domain_pricing_cache.json';
    if (is_readable($cache_file)) {
        $cached = json_decode((string) file_get_contents($cache_file), true);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }
    }

    // Emergency fallback if both the database and complete cache are missing.
    return [
        ['extension' => '.com', 'register_price' => 12.99, 'renew_price' => 14.99, 'transfer_price' => 12.99, 'is_popular' => 1],
        ['extension' => '.net', 'register_price' => 14.99, 'renew_price' => 16.99, 'transfer_price' => 14.99, 'is_popular' => 1],
        ['extension' => '.org', 'register_price' => 13.99, 'renew_price' => 15.99, 'transfer_price' => 13.99, 'is_popular' => 1],
        ['extension' => '.co.za', 'register_price' => 8.99, 'renew_price' => 8.99, 'transfer_price' => 8.99, 'is_popular' => 1],
        ['extension' => '.info', 'register_price' => 11.99, 'renew_price' => 11.99, 'transfer_price' => 11.99, 'is_popular' => 0],
        ['extension' => '.biz', 'register_price' => 12.99, 'renew_price' => 12.99, 'transfer_price' => 12.99, 'is_popular' => 0],
        ['extension' => '.io', 'register_price' => 49.99, 'renew_price' => 49.99, 'transfer_price' => 49.99, 'is_popular' => 1],
        ['extension' => '.tech', 'register_price' => 39.99, 'renew_price' => 39.99, 'transfer_price' => 39.99, 'is_popular' => 1],
        ['extension' => '.dev', 'register_price' => 15.99, 'renew_price' => 15.99, 'transfer_price' => 15.99, 'is_popular' => 1],
        ['extension' => '.app', 'register_price' => 18.99, 'renew_price' => 18.99, 'transfer_price' => 18.99, 'is_popular' => 1],
    ];
}

/**
 * Get fallback price for specific TLD
 * @param string $tld
 * @return float
 */
function getFallbackPriceForTLD($tld) {
    $pricing = [
        '.com' => 12.99,
        '.net' => 14.99,
        '.org' => 13.99,
        '.co.za' => 8.99,
        '.info' => 11.99,
        '.biz' => 12.99,
        '.io' => 49.99,
        '.tech' => 39.99,
        '.dev' => 15.99,
        '.app' => 18.99
    ];
    
    return isset($pricing[$tld]) ? $pricing[$tld] : 12.99;
}

/**
 * Fallback addons if database is unavailable
 * @return array
 */
function getFallbackAddons() {
    return [
        [
            'addon_name' => 'Neural Privacy Shield',
            'addon_slug' => 'domain-privacy',
            'addon_type' => 'domain_privacy',
            'description' => 'WHOIS privacy protection',
            'price' => 9.99,
            'billing_cycle' => 'annually',
            'applies_to_product_types' => '["domain","domain_transfer"]'
        ]
    ];
}

/**
 * Generate TLD dropdown HTML options
 * @return string
 */
function generateTLDOptions() {
    $extensions = getAllDomainExtensions();
    $html = '';
    
    foreach ($extensions as $ext) {
        $html .= sprintf(
            '<option value="%s">%s - $%s</option>',
            htmlspecialchars($ext['extension']),
            htmlspecialchars($ext['extension']),
            number_format($ext['register_price'], 2)
        );
    }
    
    return $html;
}

/**
 * Generate TLD pricing JavaScript object
 * @return string
 */
function generateTLDPricingJS() {
    $extensions = getAllDomainExtensions();
    $pricing = [];
    
    foreach ($extensions as $ext) {
        $pricing[$ext['extension']] = floatval($ext['register_price']);
    }
    
    return json_encode($pricing);
}
?>
