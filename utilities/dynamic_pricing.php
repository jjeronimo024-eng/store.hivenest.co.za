<?php
/**
 * HiveNest – Dynamic Pricing Helper
 * -----------------------------------------------------------------
 * Single-call helper that lets every product page pull its pricing
 * tiers from the live DB (via getProductPricingById / BySlug) and
 * fall back to the page's hardcoded array when the DB has nothing
 * for that product yet. This keeps the UI lit during migration.
 *
 * Typical page usage:
 *
 *   include_once __DIR__ . '/../utilities/dynamic_pricing.php';
 *   include_once __DIR__ . '/../utilities/pricing-cards.php';
 *
 *   $fallback_plans = [ ['name'=>'WP STARTER', 'price'=>'$7', ...], ... ];
 *   $plans = loadProductPricingPlans([
 *       'product_id'      => 5,                    // preferred
 *       'product_slug'    => 'wordpress-hosting',  // used if product_id misses
 *       'cart_function'   => 'addWordPressHostingToCart',
 *       'fallback_plans'  => $fallback_plans,
 *   ]);
 *   echo renderPricingGrid($plans);
 */

require_once __DIR__ . '/product_pricing.php';
if (file_exists(__DIR__ . '/pricing_simple.php')) {
    require_once __DIR__ . '/pricing_simple.php'; // for getCachedProductPricing()
}

/**
 * Read pricing rows for a product directly from the JSON cache
 * written by admin/products-admin.php. Returns DB-row-shape rows
 * (NOT pricing-card rows) so they can be passed through
 * convertToPricingCards().
 *
 * @return array list of pricing-tier rows ([] when missing)
 */
function getCachedPricingRows(?int $product_id, ?string $product_slug): array {
    $cache_file = __DIR__ . '/pricing_cache.json';
    if (!file_exists($cache_file)) return [];
    $raw = @file_get_contents($cache_file);
    if (!$raw) return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) return [];

    // 1) match by slug (fastest path — keys are slugs)
    if ($product_slug && isset($data[$product_slug])) {
        $rows = $data[$product_slug]['pricing_tiers'] ?? [];
        if (!empty($rows)) return $rows;
    }
    // 2) scan by product_id
    if ($product_id) {
        foreach ($data as $entry) {
            if ((int)($entry['product_id'] ?? 0) === (int)$product_id) {
                return $entry['pricing_tiers'] ?? [];
            }
        }
    }
    return [];
}

/**
 * Return active packages belonging to active products assigned to a page.
 * This is the bridge between Product Pricing Management and every storefront
 * page: adding a product or changing either Show/Hide switch requires no
 * additional PHP box to be added to the destination page.
 */
function loadAssignedPagePricingPlans(
    ?string $page_url = null,
    string $cart_function = 'addToCart',
    ?int $exclude_product_id = null,
    ?string $exclude_product_slug = null
): array {
    // The shared footer uses this marker to avoid rendering the same assigned
    // products a second time on pages that already have a dedicated grid.
    $GLOBALS['hivenest_assigned_products_rendered'] = true;
    $page_url = $page_url ?: (parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: '');
    $page_key = ltrim(str_replace('\\', '/', $page_url), '/');
    if ($page_key === '') return [];

    $conn = getPricingDBConnection();
    if (!$conn) return [];

    try {
        $styleSelect = function_exists('hivenestPricingStyleColumnsAvailable') && hivenestPricingStyleColumnsAvailable($conn)
            ? 'pp.accent_color, pp.glow_color,'
            : 'NULL AS accent_color, NULL AS glow_color,';
        $sql = "
            SELECT
                p.id AS product_id,
                p.name AS product_name,
                p.slug AS product_slug,
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
                pp.sort_order
            FROM products p
            INNER JOIN product_pricing pp
                ON pp.product_id = p.id AND pp.is_active = 1
            WHERE p.is_active = 1
              AND TRIM(LEADING '/' FROM REPLACE(p.page_url, '\\\\', '/')) = :page_key
        ";
        $params = ['page_key' => $page_key];

        if ($exclude_product_id) {
            $sql .= " AND p.id <> :excluded_product_id";
            $params['excluded_product_id'] = $exclude_product_id;
        } elseif ($exclude_product_slug) {
            $sql .= " AND p.slug <> :excluded_product_slug";
            $params['excluded_product_slug'] = $exclude_product_slug;
        }

        $sql .= " ORDER BY p.sort_order ASC, p.id ASC, pp.sort_order ASC, pp.id ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $features = $row['features'] ?? [];
            $row['features'] = is_array($features) ? $features : (json_decode((string)$features, true) ?: []);
            // Tier slugs are only unique within a product. Prefixing prevents
            // collisions when several products share the same destination page.
            $row['tier_slug'] = $row['product_slug'] . '--' . $row['tier_slug'];
        }
        unset($row);

        return convertToPricingCards($rows, $cart_function);
    } catch (Throwable $e) {
        error_log('dynamic_pricing: assigned-page lookup failed for ' . $page_key . ': ' . $e->getMessage());
        return [];
    }
}

/**
 * Resolve pricing plans for a product. Lookup chain:
 *
 *   1. Live DB via getProductPricingById()        ← Show/Hide authority
 *   2. Live DB via getProductPricingBySlug()      ← fallback by slug
 *   3. JSON cache only when the DB is unavailable
 *   4. Hardcoded fallback_plans array             ← keeps UI lit if DB is down
 *
 * @param array $opts {
 *   product_id?:     int     primary lookup
 *   product_slug?:   string  fallback lookup
 *   cart_function?:  string  JS function called by the CTA (default 'addToCart')
 *   fallback_plans?: array   hardcoded plans (already in pricing-card shape)
 * }
 * @return array Plans ready for renderPricingGrid()
 */
function loadProductPricingPlans(array $opts): array {
    // A page that explicitly renders its product grid should not also receive
    // the footer's assigned-product fallback for the same page.
    $GLOBALS['hivenest_assigned_products_rendered'] = true;

    $product_id    = isset($opts['product_id']) ? (int)$opts['product_id'] : null;
    $product_slug  = $opts['product_slug']  ?? null;
    $cart_function = $opts['cart_function'] ?? 'addToCart';
    $fallback      = $opts['fallback_plans'] ?? [];

    // The live DB is authoritative for the admin Show/Hide switches.
    $database_available = getPricingDBConnection() !== null;
    $managed_in_database = false;
    $tiers = [];

    if ($database_available && ($product_id || $product_slug)) {
        try {
            $state_sql = 'SELECT id FROM products WHERE ';
            $state_params = [];
            if ($product_id) {
                $state_sql .= 'id = :managed_product_id';
                $state_params['managed_product_id'] = $product_id;
            } else {
                $state_sql .= 'slug = :managed_product_slug';
                $state_params['managed_product_slug'] = $product_slug;
            }
            $state_stmt = getPricingDBConnection()->prepare($state_sql . ' LIMIT 1');
            $state_stmt->execute($state_params);
            $managed_in_database = (bool)$state_stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('dynamic_pricing: product visibility lookup failed: ' . $e->getMessage());
        }
    }

    // 1) DB by ID
    if ($product_id) {
        try {
            $tiers = getProductPricingById($product_id);
        } catch (Throwable $e) {
            error_log("dynamic_pricing: id={$product_id} lookup failed: " . $e->getMessage());
        }
    }

    // 2) DB by slug
    if (empty($tiers) && $product_slug) {
        try {
            $tiers = getProductPricingBySlug($product_slug);
        } catch (Throwable $e) {
            error_log("dynamic_pricing: slug={$product_slug} lookup failed: " . $e->getMessage());
        }
    }

    // 3) Never let stale cache rows resurrect a package hidden in the DB.
    if (empty($tiers) && !$database_available) {
        $tiers = getCachedPricingRows($product_id, $product_slug);
    }

    // 4) Resolve the primary product without returning early so products newly
    // assigned to this page can be appended automatically.
    if (!empty($tiers)) {
        $plans = convertToPricingCards($tiers, $cart_function);
    } elseif ($managed_in_database) {
        // The product is deliberately hidden, or all of its packages are hidden.
        $plans = [];
    } elseif (!empty($fallback)) {
        $plans = $fallback;
    } else {
        $plans = getBuiltInPricingFallback($product_slug, $cart_function);
    }

    if (($opts['include_assigned_products'] ?? false) === true) {
        $plans = array_merge($plans, loadAssignedPagePricingPlans(
            $opts['page_url'] ?? null,
            $cart_function,
            $product_id,
            $product_slug
        ));
    }

    return $plans;
}

/**
 * Stable USD catalogue used only when neither cache nor database pricing exists.
 */
function getBuiltInPricingFallback(?string $product_slug, string $cart_function): array {
    $catalogue = [
        'cloud-mail' => [
            ['CLOUD STARTER', 'cloud-starter', 4.00, '/user/mo', ['25GB Cloud Storage', 'Custom Domain Email', 'Spam Protection', '99.9% Uptime SLA']],
            ['CLOUD PROFESSIONAL', 'cloud-professional', 9.00, '/user/mo', ['Unlimited Cloud Storage', 'Advanced Security', 'Calendar & Contact Sync', 'Priority Support']],
            ['CLOUD ENTERPRISE', 'cloud-enterprise', 18.00, '/user/mo', ['Advanced Threat Protection', 'Multi-region Redundancy', 'Dedicated Support', '99.99% Uptime SLA']],
        ],
        'website-builder' => [
            ['STARTER NEURAL', 'starter-neural', 19.00, '/mo', ['5 Pages', 'Drag & Drop Editor', 'Mobile Responsive', 'SSL Certificate']],
            ['PROFESSIONAL MATRIX', 'professional-matrix', 49.00, '/mo', ['Unlimited Pages', 'E-commerce Integration', 'Advanced SEO', 'Priority Support']],
            ['ENTERPRISE QUANTUM', 'enterprise-quantum', 149.00, '/mo', ['Custom Templates', 'Multi-site Management', 'Unlimited Storage', 'Dedicated Manager']],
        ],
        'logo-design' => [
            ['BASIC NEURAL', 'basic-neural', 299.00, ' once-off', ['3 Logo Concepts', '2 Revisions', 'High-resolution Files', 'Web & Print Ready']],
            ['PROFESSIONAL MATRIX', 'professional-matrix-logo', 599.00, ' once-off', ['5 Logo Concepts', 'Unlimited Revisions', 'Brand Package', 'Dedicated Designer']],
            ['ENTERPRISE QUANTUM', 'enterprise-quantum-logo', 1299.00, ' once-off', ['10 Logo Concepts', 'Complete Identity Suite', 'Custom Typography', 'Full Licensing']],
        ],
        'business-cards' => [
            ['STANDARD NEURAL', 'standard-neural', 99.00, ' once-off', ['2 Design Concepts', 'Front & Back Layout', 'Print-ready Files', 'Digital Delivery']],
            ['PREMIUM MATRIX', 'premium-matrix', 199.00, ' once-off', ['4 Design Concepts', 'Unlimited Revisions', 'Premium Finish Consultation', 'Brand Integration']],
            ['LUXURY QUANTUM', 'luxury-quantum', 399.00, ' once-off', ['Custom Die-cut Design', 'Luxury Materials', 'Foil Effects', 'Printing Coordination']],
        ],
        'letterheads' => [
            ['BASIC NEURAL', 'basic-neural-lh', 149.00, ' once-off', ['2 Concepts', '1 Revision', 'Print-ready PDF', '5-day Turnaround']],
            ['PROFESSIONAL MATRIX', 'professional-matrix-lh', 299.00, ' once-off', ['4 Concepts', 'Unlimited Revisions', 'Stationery Suite', 'Multiple Formats']],
            ['ENTERPRISE QUANTUM', 'enterprise-quantum-lh', 599.00, ' once-off', ['Complete Stationery Suite', 'Brand Integration', 'Corporate Templates', 'Ongoing Support']],
        ],
        'email-signatures' => [
            ['INDIVIDUAL NEURAL', 'individual-neural', 49.00, ' once-off', ['1 Custom Signature', '2 Revisions', 'HTML & Image Formats', 'Mobile Optimized']],
            ['TEAM MATRIX', 'team-matrix', 199.00, ' once-off', ['5 Custom Signatures', 'Unlimited Revisions', 'Department Variations', 'Setup Support']],
            ['ENTERPRISE QUANTUM', 'enterprise-quantum-sig', 599.00, ' once-off', ['Unlimited Signatures', 'Brand Integration', 'Dynamic Content', 'Priority Support']],
        ],
        'seo-services' => [
            ['SEO STARTER', 'seo-starter', 199.00, '/mo', ['Keyword Research', 'On-page SEO', 'Technical Audit', 'Monthly Reports']],
            ['SEO PROFESSIONAL', 'seo-professional', 399.00, '/mo', ['Advanced Keyword Strategy', 'On/Off-page SEO', 'Competitor Analysis', 'Bi-weekly Calls']],
            ['SEO ENTERPRISE', 'seo-enterprise', 799.00, '/mo', ['Enterprise SEO Strategy', 'Content Marketing', 'Reputation Management', 'Dedicated SEO Manager']],
        ],
    ];

    if (!$product_slug || empty($catalogue[$product_slug])) {
        return [];
    }

    $cards = [];
    foreach ($catalogue[$product_slug] as $index => $plan) {
        [$name, $slug, $price, $period, $features] = $plan;
        $cards[] = [
            'name' => $name,
            'price' => '$' . number_format($price, 2),
            'period' => $period,
            'features' => $features,
            'cta_link' => '#',
            'cta_text' => 'ADD TO CART',
            'onclick' => sprintf("%s('%s', %.2f)", $cart_function, $slug, $price),
            'featured' => $index === 1,
        ];
    }

    return $cards;
}
