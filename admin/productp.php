<?php
/**
 * Product Pricing Management Page
 * Central admin page to manage all product pricing across the website.
 * NOTE: This is the legacy admin. New work happens in products-admin.php
 * which also exposes products-table CRUD. Auth is shared via admin_auth.php.
 */

require_once __DIR__ . '/../utilities/admin_auth.php';
requireAdminAuth();
$admin = currentAdmin();

function productpRedirectWithMessage(string $message, string $type = 'success'): void {
    $_SESSION['productp_flash_message'] = $message;
    $_SESSION['productp_flash_type'] = $type;
    header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? 'productp.php', '?'));
    exit;
}

function productpFlashMessage(): array {
    $message = (string)($_SESSION['productp_flash_message'] ?? '');
    $type = (string)($_SESSION['productp_flash_type'] ?? '');
    unset($_SESSION['productp_flash_message'], $_SESSION['productp_flash_type']);
    return [$message, $type ?: 'success'];
}

// Include pricing utility
require_once '../utilities/product_pricing.php';
require_once '../utilities/currency.php';

// Pricing cache file
$pricing_cache_file = __DIR__ . '/../utilities/pricing_cache.json';

function adminDefaultProductPage(string $slug, string $type): string {
    $known = [
        'domain-registration' => '/domains/register.php',
        'cyber-initiate-hosting' => '/hosting/linux-shared.php',
        'digital-warrior-hosting' => '/hosting/linux-shared.php',
        'quantum-master-hosting' => '/hosting/linux-shared.php',
        'vps-basic' => '/main-services/servers.php',
        'vps-standard' => '/main-services/servers.php',
        'ssl-basic' => '/tools/sslcert.php',
        'google-workspace-basic' => '/email/google-workspace.php',
    ];
    if (isset($known[$slug])) return $known[$slug];

    $type_defaults = [
        'domain' => '/main-services/domains.php',
        'hosting' => '/main-services/hosting.php',
        'server' => '/main-services/servers.php',
        'email' => '/main-services/email.php',
        'ssl' => '/tools/sslcert.php',
        'security' => '/main-services/tools.php',
        'backup' => '/main-services/tools.php',
        'design' => '/branding/website-builder.php',
        'marketing' => '/marketing/seo.php',
        'promotion' => '/marketing/offers.php',
    ];
    return $type_defaults[$type] ?? '/index.php';
}

function adminProductPricingStyleColumns(PDO $conn): bool {
    static $has_columns = null;
    if ($has_columns !== null) return $has_columns;
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM product_pricing LIKE 'accent_color'");
        $has_accent = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $conn->query("SHOW COLUMNS FROM product_pricing LIKE 'glow_color'");
        $has_glow = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        return $has_columns = ($has_accent && $has_glow);
    } catch (Throwable $e) {
        return $has_columns = false;
    }
}

function adminProductPricingBundleColumn(PDO $conn): bool {
    static $has_column = null;
    if ($has_column !== null) return $has_column;
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM product_pricing LIKE 'bundle_items'");
        return $has_column = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return $has_column = false;
    }
}

function adminBundleItemsValue($value): ?string {
    $value = trim((string)$value);
    if ($value === '') return null;
    $decoded = json_decode($value, true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Bundle items must be valid JSON array syntax.');
    }
    return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

function loadAdminPricingData(PDO $conn): array {
    $style_select = adminProductPricingStyleColumns($conn)
        ? 'pp.accent_color, pp.glow_color,'
        : 'NULL AS accent_color, NULL AS glow_color,';
    $bundle_select = adminProductPricingBundleColumn($conn)
        ? 'pp.bundle_items,'
        : 'NULL AS bundle_items,';
    $stmt = $conn->query("
        SELECT
            p.id AS product_id, p.name AS product_name, p.slug AS product_slug,
            p.page_url, p.product_type, p.base_price, p.category_id, p.sort_order AS product_sort_order, p.is_active AS product_is_active,
            pc.name AS category_name, pc.slug AS category_slug, pc.sort_order AS category_sort_order,
            p.billing_cycle AS product_billing_cycle, p.features AS product_features,
            pp.id AS pricing_id, pp.tier_name, pp.tier_slug, pp.tier_level,
            pp.price, pp.setup_fee, pp.billing_cycle, pp.features,
            {$style_select}
            {$bundle_select}
            pp.is_featured, pp.sort_order, pp.is_active
        FROM products p
        LEFT JOIN product_categories pc ON pc.id = p.category_id
        LEFT JOIN product_pricing pp
            ON p.id = pp.product_id
            AND NOT (p.slug = 'sitelock-security' AND pp.tier_slug LIKE 'sitelock-%')
        WHERE 1 = 1
          AND p.slug NOT IN (
              'digital-warrior-hosting',
              'quantum-master-hosting',
              'vps-basic',
              'vps-standard',
              'acronis-backup',
              'ssl-basic'
          )
        ORDER BY p.sort_order, p.id, pp.sort_order, pp.id
    ");

    $products = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $slug = $row['product_slug'];
        if (!isset($products[$slug])) {
            $page_url = trim((string)($row['page_url'] ?? ''));
            if ($page_url === '') {
                $page_url = adminDefaultProductPage($slug, strtolower((string)$row['product_type']));
            }
            $products[$slug] = [
                'product_id' => (int)$row['product_id'],
                'product_name' => $row['product_name'],
                'product_slug' => $slug,
                'page_url' => $page_url,
                'product_type' => $row['product_type'],
                'category_id' => (int)$row['category_id'],
                'category_name' => $row['category_name'] ?? '',
                'category_slug' => $row['category_slug'] ?? '',
                'category_sort_order' => (int)($row['category_sort_order'] ?? 90),
                'sort_order' => (int)($row['product_sort_order'] ?? 0),
                'is_active' => (int)$row['product_is_active'],
                'base_price' => (float)$row['base_price'],
                'product_billing_cycle' => $row['product_billing_cycle'] ?: 'monthly',
                'product_features' => json_decode((string)($row['product_features'] ?? '[]'), true) ?: [],
                'pricing_tiers' => [],
            ];
        }

        if ($row['pricing_id']) {
            $features = json_decode((string)($row['features'] ?? '[]'), true);
            if (!is_array($features) || empty($features)) {
                $features = $products[$slug]['product_features'];
            }
            $products[$slug]['pricing_tiers'][] = [
                'pricing_id' => (int)$row['pricing_id'],
                'tier_name' => $row['tier_name'],
                'tier_slug' => $row['tier_slug'],
                'tier_level' => $row['tier_level'],
                'price' => (float)$row['price'],
                'setup_fee' => (float)$row['setup_fee'],
                'billing_cycle' => $row['billing_cycle'],
                'features' => is_array($features) ? $features : [],
                'accent_color' => (string)($row['accent_color'] ?? ''),
                'glow_color' => (string)($row['glow_color'] ?? ''),
                'bundle_items' => (string)($row['bundle_items'] ?? ''),
                'is_featured' => (int)$row['is_featured'],
                'sort_order' => (int)$row['sort_order'],
                'is_active' => (int)$row['is_active'],
                'source' => 'tier',
            ];
        }
    }

    // Products without tier rows still have a real, editable base package.
    foreach ($products as &$product) {
        if (empty($product['pricing_tiers'])) {
            $product['pricing_tiers'][] = [
                'pricing_id' => null,
                'tier_name' => 'BASE PACKAGE',
                'tier_slug' => 'base-package',
                'tier_level' => 'base',
                'price' => $product['base_price'],
                'setup_fee' => 0,
                'billing_cycle' => $product['product_billing_cycle'],
                'features' => $product['product_features'],
                'accent_color' => '',
                'glow_color' => '',
                'bundle_items' => '',
                'is_featured' => 0,
                'sort_order' => 0,
                'is_active' => 1,
                'source' => 'base',
            ];
        }
    }
    unset($product);

    return $products;
}

function adminSiteSection(array $product): array {
    $path = strtolower((string)($product['page_url'] ?? ''));
    $type = strtolower((string)($product['product_type'] ?? ''));
    $slug = strtolower((string)($product['product_slug'] ?? ''));
    $category_name = trim((string)($product['category_name'] ?? ''));
    $category_slug = strtolower((string)($product['category_slug'] ?? ''));

    if ($path === '/index.php' || $path === 'index.php' || $path === '/') return [0, 'HOME PAGE'];

    $standard_categories = ['domains','hosting','email','security','servers','design'];
    if ($category_name !== '' && !in_array($category_slug, $standard_categories, true)) {
        return [(int)($product['category_sort_order'] ?? 90), strtoupper($category_name)];
    }

    if (strpos($path, '/domains/') !== false || $type === 'domain') return [10, 'NEURAL DOMAINS'];
    if (strpos($path, 'cyber-scan') !== false) return [20, 'CYBER SCAN'];
    if (strpos($path, '/hosting/') !== false || strpos($path, '/servers/') !== false || in_array($type, ['hosting', 'server'], true)) return [30, 'QUANTUM SERVERS'];
    if (strpos($path, '/tools/') !== false || in_array($type, ['ssl', 'security', 'backup'], true)) return [40, 'DIGITAL ARSENAL'];
    if (strpos($path, '/email/') !== false || $type === 'email') return [50, 'COMM ARRAYS'];
    if (strpos($path, '/branding/') !== false || ($type === 'design' && strpos($path, '/marketing/') === false && $slug !== 'seo-services')) return [60, 'NEURAL GRAPHICS'];
    if (strpos($path, '/marketing/offers') !== false || $type === 'promotion') return [80, 'SPECIAL OPS'];
    if (strpos($path, '/marketing/') !== false || $slug === 'seo-services' || $type === 'marketing') return [70, 'MARKETING MATRIX'];
    return [0, 'HOME PAGE'];
}

function alignStorefrontCatalogue(PDO $conn): int {
    $conn->beginTransaction();
    try {
        $legacy = ['digital-warrior-hosting','quantum-master-hosting','vps-basic','vps-standard','acronis-backup','ssl-basic'];
        $placeholders = implode(',', array_fill(0, count($legacy), '?'));
        $stmt = $conn->prepare("UPDATE products SET is_active = 0 WHERE slug IN ($placeholders)");
        $stmt->execute($legacy);
        $conn->exec("UPDATE products SET page_url = '/domains/register.php' WHERE slug = 'domain-registration'");

        $products = [
            ['Google Marketing','google-marketing','/marketing/google-marketing.php',299.00,'Google Ads management packages',20],
            ['Social Media Marketing','social-media-marketing','/marketing/social-media.php',199.00,'Social media management packages',30],
            ['Special Ops','special-ops','/marketing/offers.php',5.00,'Limited-time Flash Operations packages',40],
        ];
        $product_stmt = $conn->prepare("
            INSERT INTO products
                (uuid, category_id, name, slug, page_url, description, short_description,
                 product_type, service_type, billing_cycle, base_price, setup_fee, features,
                 is_active, is_featured, sort_order)
            VALUES
                (UUID(), 6, :name, :slug, :page_url, :description, :short_description,
                 'design', 'recurring', 'monthly', :base_price, 0,
                 '[\"Storefront package management\"]', 1, 0, :sort_order)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name), page_url = VALUES(page_url), description = VALUES(description),
                base_price = VALUES(base_price), is_active = 1, sort_order = VALUES(sort_order)
        ");
        foreach ($products as [$name,$slug,$page,$price,$description,$sort]) {
            $product_stmt->execute([
                'name'=>$name,
                'slug'=>$slug,
                'page_url'=>$page,
                'description'=>$description,
                'short_description'=>$description,
                'base_price'=>$price,
                'sort_order'=>$sort
            ]);
        }

        $tiers = [
            ['domain-registration','POPULAR DOMAINS','popular-domains','basic',8.99,'annually',['.com','.co.za','.net','.org','WHOIS privacy','DNS management'],0,1],
            ['domain-registration','TECH DOMAINS','tech-domains','standard',49.99,'annually',['.io','.tech','.dev','.app','Premium DNS','Advanced security'],1,2],
            ['domain-registration','BUSINESS DOMAINS','business-domains','premium',29.99,'annually',['.biz','.info','.pro','.mobi','Business email setup','Marketing tools'],0,3],
            ['google-marketing','GOOGLE STARTER','google-starter','basic',299,'monthly',['Google Ads setup','Keyword research','Ad copy creation','Monthly reports'],0,1],
            ['google-marketing','GOOGLE PROFESSIONAL','google-professional','standard',599,'monthly',['Multi-campaign management','A/B testing','Remarketing','Advanced reporting'],1,2],
            ['google-marketing','GOOGLE ENTERPRISE','google-enterprise','premium',1299,'monthly',['Enterprise strategy','YouTube Ads','Audience targeting','Dedicated manager'],0,3],
            ['social-media-marketing','SOCIAL STARTER','social-starter','basic',199,'monthly',['2 platforms','12 posts monthly','Content creation','Monthly analytics'],0,1],
            ['social-media-marketing','SOCIAL PROFESSIONAL','social-professional','standard',399,'monthly',['4 platforms','30 posts monthly','Paid advertising','Weekly analytics'],1,2],
            ['social-media-marketing','SOCIAL ENTERPRISE','social-enterprise','premium',799,'monthly',['All major platforms','60+ posts monthly','Real-time monitoring','Dedicated manager'],0,3],
            ['special-ops','NEURAL STARTER','flash-neural-starter','basic',5,'monthly',['3 websites','25GB SSD','Unlimited bandwidth','Free SSL'],1,1],
            ['special-ops','DESIGN PACKAGE','flash-design-package','standard',99,'one_time',['Logo','Business cards','Letterhead','Email signature'],0,2],
            ['special-ops','NEURAL BUNDLE','flash-neural-bundle','premium',29,'monthly',['WordPress hosting','Domain','SSL','Xcitium backup'],0,3],
            ['special-ops','WORDPRESS LAUNCH','flash-wordpress-launch','basic',12,'monthly',['Managed WordPress','25GB SSD','Free SSL','Daily backups'],0,4],
            ['special-ops','BUSINESS MAIL','flash-business-mail','basic',8,'per_user_monthly',['Custom email','25GB mailbox','Spam protection','Device sync'],0,5],
            ['special-ops','SSL SHIELD','flash-ssl-shield','basic',3,'monthly',['Domain validation','Encryption','Browser compatibility','Trust indicator'],0,6],
            ['special-ops','XCITIUM BACKUP','flash-xcitium-backup','standard',6,'monthly',['Cloud backup','Scheduling','Secure retention','Recovery'],0,7],
            ['special-ops','SITELOCK DEFENSE','flash-sitelock-defense','standard',4,'monthly',['Malware scan','Vulnerability checks','Security badge','Alerts'],0,8],
            ['special-ops','SEO BOOSTER','flash-seo-booster','standard',149,'monthly',['Keyword research','On-page SEO','Technical audit','Monthly report'],0,9],
            ['special-ops','SOCIAL LAUNCH','flash-social-launch','standard',129,'monthly',['2 platforms','Content calendar','8 posts','Report'],0,10],
            ['special-ops','BRAND IDENTITY','flash-brand-identity','premium',249,'one_time',['Logo','Business card','Email signature','Colour palette'],0,11],
            ['special-ops','CLOUD GROWTH','flash-cloud-growth','premium',25,'monthly',['Cloud hosting','50GB SSD','Unlimited bandwidth','Priority support'],0,12],
        ];
        $tier_stmt = $conn->prepare("
            INSERT INTO product_pricing
                (uuid, product_id, tier_name, tier_slug, tier_level, price, setup_fee,
                 billing_cycle, features, is_featured, sort_order, is_active)
            SELECT UUID(), p.id, :tier_name, :tier_slug, :tier_level, :price, 0,
                   :billing_cycle, :features, :is_featured, :sort_order, 1
            FROM products p WHERE p.slug = :product_slug
            ON DUPLICATE KEY UPDATE
                tier_name = VALUES(tier_name), tier_level = VALUES(tier_level), price = VALUES(price),
                billing_cycle = VALUES(billing_cycle), features = VALUES(features),
                is_featured = VALUES(is_featured), sort_order = VALUES(sort_order), is_active = 1
        ");
        foreach ($tiers as [$product_slug,$name,$slug,$level,$price,$cycle,$features,$featured,$sort]) {
            $tier_stmt->execute([
                'tier_name'=>$name,'tier_slug'=>$slug,'tier_level'=>$level,'price'=>$price,
                'billing_cycle'=>$cycle,'features'=>json_encode($features),'is_featured'=>$featured,
                'sort_order'=>$sort,'product_slug'=>$product_slug,
            ]);
        }

        $conn->commit();
        return count($tiers);
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        throw $e;
    }
}

function adminSlug(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function adminColorValue(?string $value): ?string {
    $value = trim((string)$value);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : null;
}

function createAdminProductWithFirstPackage(PDO $conn, array $input): int {
    $name = trim((string)($input['product_name'] ?? ''));
    $slug = adminSlug((string)($input['product_slug'] ?? $name));
    $page_url = trim((string)($input['page_url'] ?? ''));
    $category_id = (int)($input['category_id'] ?? 0);
    $product_type = (string)($input['product_type'] ?? 'design');
    $package_name = trim((string)($input['package_name'] ?? ''));
    $package_slug = adminSlug((string)($input['package_slug'] ?? $package_name));
    $price = (float)($input['price'] ?? 0);
    $billing_cycle = (string)($input['billing_cycle'] ?? 'monthly');
    $tier_level = (string)($input['tier_level'] ?? 'basic');
    $features = array_values(array_filter(array_map('trim', preg_split('/\R/', (string)($input['features'] ?? '')) ?: [])));
    $accent_color = adminColorValue($input['accent_color'] ?? null);
    $glow_color = adminColorValue($input['glow_color'] ?? null);

    $valid_types = ['domain','hosting','email','security','design','server','ssl','backup'];
    $valid_cycles = ['monthly','quarterly','semi_annually','annually','one_time','per_user_monthly'];
    $valid_levels = ['basic','standard','premium'];
    if ($name === '' || $slug === '' || $page_url === '' || $category_id < 1 || $package_name === '' || $package_slug === '') {
        throw new InvalidArgumentException('Category, product name, page, and first package are required.');
    }
    if (!in_array($product_type, $valid_types, true) || !in_array($billing_cycle, $valid_cycles, true) || !in_array($tier_level, $valid_levels, true)) {
        throw new InvalidArgumentException('Invalid product type, billing cycle, or tier level.');
    }

    $service_type = $billing_cycle === 'one_time' ? 'one_time' : 'recurring';
    $product_cycle = $billing_cycle === 'one_time' || $billing_cycle === 'per_user_monthly' ? 'monthly' : $billing_cycle;
    $stmt = $conn->prepare("
        INSERT INTO products
            (uuid, category_id, name, slug, page_url, description, short_description,
             product_type, service_type, billing_cycle, base_price, setup_fee, features,
             is_active, is_featured, sort_order)
        VALUES
            (UUID(), :category_id, :name, :slug, :page_url, :description, :short_description,
             :product_type, :service_type, :billing_cycle, :base_price, 0, :features, 1, 0, 0)
    ");
    $stmt->execute([
        'category_id'=>$category_id, 'name'=>$name, 'slug'=>$slug, 'page_url'=>$page_url,
        'description'=>$name, 'product_type'=>$product_type, 'service_type'=>$service_type,
        'short_description'=>$name, 'billing_cycle'=>$product_cycle, 'base_price'=>$price, 'features'=>json_encode($features),
    ]);
    $product_id = (int)$conn->lastInsertId();

    $has_style_columns = adminProductPricingStyleColumns($conn);
    $stmt = $conn->prepare("
        INSERT INTO product_pricing
            (uuid, product_id, tier_name, tier_slug, tier_level, price, setup_fee,
             billing_cycle, features" . ($has_style_columns ? ", accent_color, glow_color" : "") . ", is_featured, sort_order, is_active)
        VALUES
            (UUID(), :product_id, :tier_name, :tier_slug, :tier_level, :price, 0,
             :billing_cycle, :features" . ($has_style_columns ? ", :accent_color, :glow_color" : "") . ", 1, 1, 1)
    ");
    $params = [
        'product_id'=>$product_id, 'tier_name'=>$package_name, 'tier_slug'=>$package_slug,
        'tier_level'=>$tier_level, 'price'=>$price, 'billing_cycle'=>$billing_cycle,
        'features'=>json_encode($features),
    ];
    if ($has_style_columns) {
        $params['accent_color'] = $accent_color;
        $params['glow_color'] = $glow_color;
    }
    $stmt->execute($params);
    return $product_id;
}

function addAdminPackage(PDO $conn, array $input): void {
    $product_id = (int)($input['product_id'] ?? 0);
    $name = trim((string)($input['package_name'] ?? ''));
    $slug = adminSlug((string)($input['package_slug'] ?? $name));
    $level = (string)($input['tier_level'] ?? 'basic');
    $cycle = (string)($input['billing_cycle'] ?? 'monthly');
    $price = (float)($input['price'] ?? 0);
    $features = array_values(array_filter(array_map('trim', preg_split('/\R/', (string)($input['features'] ?? '')) ?: [])));
    $accent_color = adminColorValue($input['accent_color'] ?? null);
    $glow_color = adminColorValue($input['glow_color'] ?? null);
    if ($product_id < 1 || $name === '' || $slug === '') throw new InvalidArgumentException('Product and package name are required.');
    if (!in_array($level, ['basic','standard','premium'], true)) throw new InvalidArgumentException('Invalid tier level.');
    if (!in_array($cycle, ['monthly','quarterly','semi_annually','annually','one_time','per_user_monthly'], true)) throw new InvalidArgumentException('Invalid billing cycle.');

    $sort_stmt = $conn->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM product_pricing WHERE product_id = ?');
    $sort_stmt->execute([$product_id]);
    $sort_order = (int)$sort_stmt->fetchColumn();
    $has_style_columns = adminProductPricingStyleColumns($conn);
    $stmt = $conn->prepare("
        INSERT INTO product_pricing
            (uuid, product_id, tier_name, tier_slug, tier_level, price, setup_fee,
             billing_cycle, features" . ($has_style_columns ? ", accent_color, glow_color" : "") . ", is_featured, sort_order, is_active)
        VALUES (UUID(), :product_id, :name, :slug, :level, :price, 0, :cycle, :features" . ($has_style_columns ? ", :accent_color, :glow_color" : "") . ", 0, :sort_order, 1)
    ");
    $params = ['product_id'=>$product_id,'name'=>$name,'slug'=>$slug,'level'=>$level,'price'=>$price,'cycle'=>$cycle,'features'=>json_encode($features),'sort_order'=>$sort_order];
    if ($has_style_columns) {
        $params['accent_color'] = $accent_color;
        $params['glow_color'] = $glow_color;
    }
    $stmt->execute($params);
}

// Handle actions
$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    [$message, $message_type] = productpFlashMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $catalog_actions = [
        'add_product',
        'add_package',
        'toggle_product',
        'toggle_package',
        'add_category',
        'update_currency_rates',
        'save_promotion',
        'toggle_promotion',
    ];
    $requested_action = (string)($_POST['action'] ?? '');
    if (in_array($requested_action, ['save_promotion', 'toggle_promotion'], true)) {
        $_SESSION['productp_active_tab'] = 'promotions';
    }
    if (in_array($requested_action, $catalog_actions, true)) {
        if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
            $message = '✗ Session expired. Reload the page and try again.';
            $message_type = 'error';
        } else {
            $conn = getPricingDBConnection();
            if (!$conn) {
                $message = '✗ Database connection failed';
                $message_type = 'error';
            } else {
                try {
                    if ($requested_action === 'add_product') {
                        createAdminProductWithFirstPackage($conn, $_POST);
                        $message = '✓ Product and first package created.';
                    } elseif ($requested_action === 'add_package') {
                        addAdminPackage($conn, $_POST);
                        $message = '✓ Package added to product.';
                    } elseif ($requested_action === 'toggle_product') {
                        $stmt = $conn->prepare('UPDATE products SET is_active = :is_active, updated_at = NOW() WHERE id = :id');
                        $stmt->execute(['is_active'=>(int)($_POST['is_active'] ?? 0),'id'=>(int)($_POST['product_id'] ?? 0)]);
                        $message = (int)($_POST['is_active'] ?? 0) === 1 ? '✓ Product is now visible.' : '✓ Product is now hidden from the storefront.';
                    } elseif ($requested_action === 'toggle_package') {
                        $stmt = $conn->prepare('UPDATE product_pricing SET is_active = :is_active, updated_at = NOW() WHERE id = :id');
                        $stmt->execute(['is_active'=>(int)($_POST['is_active'] ?? 0),'id'=>(int)($_POST['pricing_id'] ?? 0)]);
                        $message = (int)($_POST['is_active'] ?? 0) === 1 ? '✓ Package is now visible.' : '✓ Package is now hidden from the storefront.';
                    } elseif ($requested_action === 'add_category') {
                        $category_name = trim((string)($_POST['category_name'] ?? ''));
                        $category_slug = adminSlug((string)($_POST['category_slug'] ?? $category_name));
                        if ($category_name === '' || $category_slug === '') throw new InvalidArgumentException('Category name is required.');
                        $conn->beginTransaction();
                        $stmt = $conn->prepare("
                            INSERT INTO product_categories (uuid, name, slug, description, sort_order, is_active)
                            VALUES (UUID(), :name, :slug, :description, :sort_order, 1)
                        ");
                        $stmt->execute(['name'=>$category_name,'slug'=>$category_slug,'description'=>'Storefront category: '.$category_name,'sort_order'=>(int)($_POST['category_sort_order'] ?? 90)]);
                        $_POST['category_id'] = (int)$conn->lastInsertId();
                        createAdminProductWithFirstPackage($conn, $_POST);
                        $conn->commit();
                        $message = '✓ Category created with its first product and package.';
                    } elseif ($requested_action === 'update_currency_rates') {
                        $rateInputs = [
                            'display_rate_zar_per_usd' => (float)($_POST['zar_rate'] ?? 0),
                            'display_rate_eur_per_usd' => (float)($_POST['eur_rate'] ?? 0),
                            'display_rate_sgd_per_usd' => (float)($_POST['sgd_rate'] ?? 0),
                        ];
                        foreach ($rateInputs as $key => $rate) {
                            if (!is_finite($rate) || $rate <= 0 || $rate >= 100000) {
                                throw new InvalidArgumentException('Every exchange rate must be a positive number.');
                            }
                        }
                        $descriptions = [
                            'display_rate_zar_per_usd' => 'Indicative ZAR amount displayed per USD; checkout remains USD',
                            'display_rate_eur_per_usd' => 'Indicative EUR amount displayed per USD; checkout remains USD',
                            'display_rate_sgd_per_usd' => 'Indicative SGD amount displayed per USD; checkout remains USD',
                        ];
                        $stmt = $conn->prepare("
                            INSERT INTO system_settings
                                (setting_key, setting_value, setting_type, description, is_editable)
                            VALUES
                                (:setting_key, :setting_value, 'number', :description, 1)
                            ON DUPLICATE KEY UPDATE
                                setting_value = VALUES(setting_value),
                                setting_type = 'number',
                                description = VALUES(description),
                                is_editable = 1,
                                updated_at = NOW()
                        ");
                        foreach ($rateInputs as $key => $rate) {
                            $stmt->execute([
                                'setting_key' => $key,
                                'setting_value' => number_format($rate, 6, '.', ''),
                                'description' => $descriptions[$key],
                            ]);
                        }
                        $_SESSION['productp_active_tab'] = 'currency';
                        $message = '✓ Display currency rates updated. Storefront prices will use the new rates immediately.';
                    } elseif ($requested_action === 'save_promotion') {
                        $promotionId = max(0, (int)($_POST['promotion_id'] ?? 0));
                        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
                        $description = trim((string)($_POST['description'] ?? ''));
                        $discountType = (string)($_POST['discount_type'] ?? 'percentage');
                        $discountValue = round((float)($_POST['discount_value'] ?? 0), 2);
                        $minimumOrder = round(max(0, (float)($_POST['minimum_order_amount'] ?? 0)), 2);
                        $usageLimit = max(0, (int)($_POST['usage_limit'] ?? 0));
                        $customerUsageLimit = max(0, (int)($_POST['customer_usage_limit'] ?? 0));
                        $isActive = !empty($_POST['is_active']) ? 1 : 0;

                        if (!preg_match('/^[A-Z0-9_-]{3,50}$/', $code)) {
                            throw new InvalidArgumentException('Code must contain 3–50 letters, numbers, underscores or hyphens.');
                        }
                        if (!in_array($discountType, ['percentage', 'fixed_amount'], true)) {
                            throw new InvalidArgumentException('Choose percentage or fixed amount for PayPal checkout.');
                        }
                        if (!is_finite($discountValue) || $discountValue <= 0) {
                            throw new InvalidArgumentException('Discount value must be greater than zero.');
                        }
                        if ($discountType === 'percentage' && $discountValue > 100) {
                            throw new InvalidArgumentException('Percentage discount cannot exceed 100%.');
                        }

                        $startInput = trim((string)($_POST['start_date'] ?? ''));
                        $endInput = trim((string)($_POST['end_date'] ?? ''));
                        $startTimestamp = strtotime($startInput);
                        $endTimestamp = strtotime($endInput);
                        if ($startTimestamp === false || $endTimestamp === false || $endTimestamp <= $startTimestamp) {
                            throw new InvalidArgumentException('End date must be later than the start date.');
                        }
                        $startDate = date('Y-m-d H:i:s', $startTimestamp);
                        $endDate = date('Y-m-d H:i:s', $endTimestamp);

                        $normaliseRestrictions = static function (string $raw): string {
                            $values = preg_split('/[\r\n,]+/', strtolower($raw)) ?: [];
                            $values = array_values(array_unique(array_filter(array_map('trim', $values))));
                            return json_encode($values ?: ['all'], JSON_UNESCAPED_SLASHES);
                        };
                        $productsJson = $normaliseRestrictions((string)($_POST['applicable_products'] ?? 'all'));
                        $categoriesJson = $normaliseRestrictions((string)($_POST['applicable_categories'] ?? 'all'));

                        if ($promotionId > 0) {
                            $stmt = $conn->prepare("
                                UPDATE promotion_codes
                                SET code=:code,
                                    description=:description,
                                    discount_type=:discount_type,
                                    discount_value=:discount_value,
                                    minimum_order_amount=:minimum_order_amount,
                                    usage_limit=:usage_limit,
                                    customer_usage_limit=:customer_usage_limit,
                                    applicable_products=:applicable_products,
                                    applicable_categories=:applicable_categories,
                                    start_date=:start_date,
                                    end_date=:end_date,
                                    is_active=:is_active,
                                    updated_at=NOW()
                                WHERE id=:id
                            ");
                            $params = ['id' => $promotionId];
                        } else {
                            $stmt = $conn->prepare("
                                INSERT INTO promotion_codes
                                    (uuid, code, description, discount_type, discount_value,
                                     minimum_order_amount, usage_limit, usage_count,
                                     customer_usage_limit, applicable_products,
                                     applicable_categories, start_date, end_date, is_active)
                                VALUES
                                    (UUID(), :code, :description, :discount_type, :discount_value,
                                     :minimum_order_amount, :usage_limit, 0,
                                     :customer_usage_limit, :applicable_products,
                                     :applicable_categories, :start_date, :end_date, :is_active)
                            ");
                            $params = [];
                        }
                        $stmt->execute(array_merge($params, [
                            'code' => $code,
                            'description' => $description,
                            'discount_type' => $discountType,
                            'discount_value' => $discountValue,
                            'minimum_order_amount' => $minimumOrder,
                            'usage_limit' => $usageLimit,
                            'customer_usage_limit' => $customerUsageLimit,
                            'applicable_products' => $productsJson,
                            'applicable_categories' => $categoriesJson,
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'is_active' => $isActive,
                        ]));
                        $_SESSION['productp_active_tab'] = 'promotions';
                        $message = $promotionId > 0 ? '✓ Promotion code updated.' : '✓ Promotion code created.';
                    } elseif ($requested_action === 'toggle_promotion') {
                        $promotionId = max(0, (int)($_POST['promotion_id'] ?? 0));
                        if ($promotionId <= 0) throw new InvalidArgumentException('Promotion code was not found.');
                        $isActive = !empty($_POST['is_active']) ? 1 : 0;
                        $stmt = $conn->prepare('UPDATE promotion_codes SET is_active=:is_active, updated_at=NOW() WHERE id=:id');
                        $stmt->execute(['is_active' => $isActive, 'id' => $promotionId]);
                        $_SESSION['productp_active_tab'] = 'promotions';
                        $message = $isActive ? '✓ Promotion code activated.' : '✓ Promotion code deactivated.';
                    }
                    $message_type = 'success';
                } catch (Throwable $e) {
                    if ($conn->inTransaction()) $conn->rollBack();
                    $message = '✗ Catalogue update failed: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'align_catalog') {
        $conn = getPricingDBConnection();
        if ($conn) {
            try {
                $aligned = alignStorefrontCatalogue($conn);
                $message = "✓ Storefront catalogue aligned. {$aligned} page packages are now database-backed.";
                $message_type = 'success';
            } catch (Throwable $e) {
                $message = '✗ Catalogue alignment failed: ' . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = '✗ Database connection failed';
            $message_type = 'error';
        }
    }
    
    // Rescan database
    if (isset($_POST['action']) && $_POST['action'] === 'rescan') {
        $conn = getPricingDBConnection();
        
        if ($conn) {
            try {
                $style_select = adminProductPricingStyleColumns($conn)
                    ? 'pp.accent_color, pp.glow_color,'
                    : 'NULL AS accent_color, NULL AS glow_color,';
                // Get all products with pricing
                $stmt = $conn->query("
                    SELECT 
                        p.id as product_id,
                        p.name as product_name,
                        p.slug as product_slug,
                        p.page_url,
                        p.product_type,
                        pp.id as pricing_id,
                        pp.tier_name,
                        pp.tier_slug,
                        pp.tier_level,
                        pp.price,
                        pp.setup_fee,
                        pp.billing_cycle,
                        pp.features,
                        {$style_select}
                        pp.is_featured,
                        pp.sort_order,
                        pp.is_active
                    FROM products p
                    LEFT JOIN product_pricing pp ON p.id = pp.product_id
                    WHERE p.is_active = 1
                    ORDER BY p.product_type, p.id, pp.sort_order
                ");
                
                $products_data = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $slug = $row['product_slug'];
                    
                    if (!isset($products_data[$slug])) {
                        $products_data[$slug] = [
                            'product_id' => $row['product_id'],
                            'product_name' => $row['product_name'],
                            'product_slug' => $row['product_slug'],
                            'page_url' => $row['page_url'],
                            'product_type' => $row['product_type'],
                            'pricing_tiers' => []
                        ];
                    }
                    
                    if ($row['pricing_id']) {
                        $features = json_decode($row['features'], true);
                        $products_data[$slug]['pricing_tiers'][] = [
                            'pricing_id' => $row['pricing_id'],
                            'tier_name' => $row['tier_name'],
                            'tier_slug' => $row['tier_slug'],
                            'tier_level' => $row['tier_level'],
                            'price' => $row['price'],
                            'setup_fee' => $row['setup_fee'],
                            'billing_cycle' => $row['billing_cycle'],
                            'features' => is_array($features) ? $features : [],
                            'accent_color' => (string)($row['accent_color'] ?? ''),
                            'glow_color' => (string)($row['glow_color'] ?? ''),
                            'is_featured' => $row['is_featured'],
                            'sort_order' => $row['sort_order'],
                            'is_active' => $row['is_active']
                        ];
                    }
                }
                
                // Save to cache file
                file_put_contents($pricing_cache_file, json_encode($products_data, JSON_PRETTY_PRINT));
                
                $message = "✓ Database rescanned successfully! Found " . count($products_data) . " products.";
                $message_type = "success";
                
            } catch (Exception $e) {
                $message = "✗ Error: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "✗ Database connection failed";
            $message_type = "error";
        }
    }
    
    // Save edited pricing to cache
    if (isset($_POST['action']) && $_POST['action'] === 'save') {
        $pricing_data = json_decode($_POST['pricing_data'], true);
        
        if ($pricing_data) {
            file_put_contents($pricing_cache_file, json_encode($pricing_data, JSON_PRETTY_PRINT));
            $message = "✓ Pricing data saved to cache successfully!";
            $message_type = "success";
        } else {
            $message = "✗ Invalid pricing data";
            $message_type = "error";
        }
    }
    
    // Sync cache to database
    if (isset($_POST['action']) && $_POST['action'] === 'sync_to_db') {
        $conn = getPricingDBConnection();
        
        if ($conn) {
            try {
                $pricing_data = json_decode($_POST['pricing_data'], true);
                $has_style_columns = adminProductPricingStyleColumns($conn);
                $has_bundle_column = adminProductPricingBundleColumn($conn);
                
                if (!$pricing_data) {
                    throw new Exception("Invalid pricing data");
                }
                
                $conn->beginTransaction();
                $updated_count = 0;
                
                foreach ($pricing_data as $slug => $product) {
                    // Update product info
                    $stmt = $conn->prepare("
                        UPDATE products 
                        SET name = :name, 
                            page_url = :page_url,
                            sort_order = :sort_order,
                            updated_at = NOW()
                        WHERE id = :product_id
                    ");
                    $stmt->execute([
                        'name' => $product['product_name'],
                        'page_url' => $product['page_url'],
                        'sort_order' => (int)($product['sort_order'] ?? 0),
                        'product_id' => $product['product_id']
                    ]);
                    
                    // Update pricing tiers
                    foreach ($product['pricing_tiers'] as $tier) {
                        if (isset($tier['pricing_id']) && $tier['pricing_id']) {
                            $style_set = $has_style_columns ? "
                                    accent_color = :accent_color,
                                    glow_color = :glow_color," : "";
                            $bundle_set = $has_bundle_column ? "
                                    bundle_items = :bundle_items," : "";
                            $stmt = $conn->prepare("
                                UPDATE product_pricing 
                                SET tier_name = :tier_name,
                                    price = :price,
                                    setup_fee = :setup_fee,
                                    billing_cycle = :billing_cycle,
                                    features = :features,
                                    is_featured = :is_featured,
                                    sort_order = :sort_order,
                                    {$style_set}
                                    {$bundle_set}
                                    updated_at = NOW()
                                WHERE id = :pricing_id
                            ");
                            $params = [
                                'tier_name' => $tier['tier_name'],
                                'price' => $tier['price'],
                                'setup_fee' => $tier['setup_fee'] ?? 0,
                                'billing_cycle' => $tier['billing_cycle'],
                                'features' => json_encode($tier['features']),
                                'is_featured' => $tier['is_featured'] ?? 0,
                                'sort_order' => (int)($tier['sort_order'] ?? 0),
                                'pricing_id' => $tier['pricing_id']
                            ];
                            if ($has_style_columns) {
                                $params['accent_color'] = adminColorValue($tier['accent_color'] ?? null);
                                $params['glow_color'] = adminColorValue($tier['glow_color'] ?? null);
                            }
                            if ($has_bundle_column) {
                                $params['bundle_items'] = adminBundleItemsValue($tier['bundle_items'] ?? '');
                            }
                            $stmt->execute($params);
                            $updated_count++;
                        } elseif (($tier['source'] ?? '') === 'base') {
                            $stmt = $conn->prepare("
                                UPDATE products
                                SET base_price = :price,
                                    billing_cycle = :billing_cycle,
                                    features = :features,
                                    sort_order = :sort_order,
                                    updated_at = NOW()
                                WHERE id = :product_id
                            ");
                            $stmt->execute([
                                'price' => $tier['price'],
                                'billing_cycle' => $tier['billing_cycle'],
                                'features' => json_encode($tier['features']),
                                'sort_order' => (int)($product['sort_order'] ?? 0),
                                'product_id' => $product['product_id'],
                            ]);
                            $updated_count++;
                        }
                    }
                }
                
                $conn->commit();
                
                $message = "✓ Successfully synced cache to database! Updated {$updated_count} pricing tiers.";
                $message_type = "success";
                
            } catch (Exception $e) {
                $conn->rollBack();
                $message = "✗ Sync Error: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "✗ Database connection failed";
            $message_type = "error";
        }
    }

    if ($message !== '') {
        productpRedirectWithMessage($message, $message_type ?: 'success');
    }
    
    // Fetch domain extensions from database
    if (isset($_POST['action']) && $_POST['action'] === 'fetch_domains') {
        $conn = getPricingDBConnection();
        
        if ($conn) {
            try {
                $stmt = $conn->query("
                    SELECT * FROM domain_extensions 
                    WHERE is_active = 1 
                    ORDER BY is_popular DESC, extension ASC
                ");
                
                $domains = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $message = "✓ Fetched " . count($domains) . " domain extensions from database!";
                $message_type = "success";
                
            } catch (Exception $e) {
                $message = "✗ Error: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "✗ Database connection failed";
            $message_type = "error";
        }
    }
    
    // Update domain extension pricing
    if (isset($_POST['action']) && $_POST['action'] === 'save_domains') {
        $conn = getPricingDBConnection();
        
        if ($conn) {
            try {
                $domain_data = json_decode($_POST['domain_data'], true);
                
                if (!$domain_data) {
                    throw new Exception("Invalid domain data");
                }
                
                $conn->beginTransaction();
                $updated = 0;
                
                foreach ($domain_data as $domain) {
                    $stmt = $conn->prepare("
                        UPDATE domain_extensions 
                        SET register_price = :register_price,
                            renew_price = :renew_price,
                            transfer_price = :transfer_price,
                            is_popular = :is_popular,
                            updated_at = NOW()
                        WHERE extension = :extension
                    ");
                    
                    $stmt->execute([
                        'register_price' => $domain['register_price'],
                        'renew_price' => $domain['renew_price'],
                        'transfer_price' => $domain['transfer_price'],
                        'is_popular' => $domain['is_popular'] ? 1 : 0,
                        'extension' => $domain['extension']
                    ]);
                    
                    $updated++;
                }
                
                $conn->commit();
                
                $message = "✓ Updated $updated domain extensions in database!";
                $message_type = "success";
                
            } catch (Exception $e) {
                $conn->rollBack();
                $message = "✗ Update Error: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
    
    // Fetch addons from database
    if (isset($_POST['action']) && $_POST['action'] === 'fetch_addons') {
        $conn = getPricingDBConnection();
        
        if ($conn) {
            try {
                $stmt = $conn->query("
                    SELECT * FROM product_addons 
                    WHERE is_active = 1 
                    ORDER BY addon_name ASC
                ");
                
                $addons = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $message = "✓ Fetched " . count($addons) . " addons from database!";
                $message_type = "success";
                
            } catch (Exception $e) {
                $message = "✗ Error: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "✗ Database connection failed";
            $message_type = "error";
        }
    }
    
    // Save addons to database
    if (isset($_POST['action']) && $_POST['action'] === 'save_addons') {
        $conn = getPricingDBConnection();
        
        if ($conn) {
            try {
                $addon_data = json_decode($_POST['addon_data'], true);
                
                if (!$addon_data) {
                    throw new Exception("Invalid addon data");
                }
                
                $conn->beginTransaction();
                $updated = 0;
                
                foreach ($addon_data as $addon) {
                    $stmt = $conn->prepare("
                        UPDATE product_addons 
                        SET addon_name = :addon_name,
                            description = :description,
                            price = :price,
                            billing_cycle = :billing_cycle,
                            applies_to_product_types = :applies_to,
                            updated_at = NOW()
                        WHERE id = :id
                    ");
                    
                    $stmt->execute([
                        'addon_name' => $addon['addon_name'],
                        'description' => $addon['description'],
                        'price' => $addon['price'],
                        'billing_cycle' => $addon['billing_cycle'],
                        'applies_to' => $addon['applies_to_product_types'],
                        'id' => $addon['id']
                    ]);
                    
                    $updated++;
                }
                
                $conn->commit();
                
                $message = "✓ Updated $updated addons in database!";
                $message_type = "success";
                
            } catch (Exception $e) {
                $conn->rollBack();
                $message = "✗ Update Error: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }

    if ($message !== '') {
        productpRedirectWithMessage($message, $message_type ?: 'success');
    }
}

// Load current pricing data
$pricing_data = [];
$conn = getPricingDBConnection();
if ($conn) {
    try {
        $pricing_data = loadAdminPricingData($conn);
        file_put_contents($pricing_cache_file, json_encode($pricing_data, JSON_PRETTY_PRINT));
    } catch (Throwable $e) {
        $message = '✗ Could not load live product pricing: ' . $e->getMessage();
        $message_type = 'error';
    }
}
if (empty($pricing_data) && file_exists($pricing_cache_file)) {
    $pricing_data = json_decode(file_get_contents($pricing_cache_file), true) ?: [];
}

// Arrange products in the same order as the public navigation.
uasort($pricing_data, static function (array $a, array $b): int {
    [$a_order] = adminSiteSection($a);
    [$b_order] = adminSiteSection($b);
    return $a_order === $b_order
        ? strcasecmp((string)$a['product_name'], (string)$b['product_name'])
        : $a_order <=> $b_order;
});

// Load domain extensions
$domain_extensions = [];
if ($conn) {
    try {
        $stmt = $conn->query("SELECT * FROM domain_extensions WHERE is_active = 1 ORDER BY is_popular DESC, extension ASC");
        $domain_extensions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Silently fail
    }
}

// Load addons
$product_addons = [];
if ($conn) {
    try {
        $stmt = $conn->query("SELECT * FROM product_addons WHERE is_active = 1 ORDER BY addon_name ASC");
        $product_addons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Silently fail
    }
}

$product_categories = [];
if ($conn) {
    try {
        $product_categories = $conn->query('SELECT id, name, slug, sort_order, is_active FROM product_categories ORDER BY sort_order, name')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* shown through catalogue actions when relevant */ }
}

$promotion_codes = [];
$promotion_redemptions = [];
$promotion_schema_ready = false;
if ($conn) {
    try {
        $tableCheck = $conn->query("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN ('promotion_codes', 'promotion_redemptions')
        ");
        $promotion_schema_ready = (int)$tableCheck->fetchColumn() === 2;
        if ($promotion_schema_ready) {
            $promotion_codes = $conn->query("
                SELECT *
                FROM promotion_codes
                ORDER BY is_active DESC, end_date DESC, code ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            $promotion_redemptions = $conn->query("
                SELECT
                    pr.id,
                    pr.code,
                    pr.discount_amount,
                    pr.currency,
                    pr.redeemed_at,
                    pr.reversed_at,
                    pr.reversal_reason,
                    o.order_number,
                    o.payment_status,
                    o.total_amount,
                    c.email,
                    c.first_name,
                    c.last_name,
                    c.company_name
                FROM promotion_redemptions pr
                INNER JOIN orders o ON o.id = pr.order_id
                INNER JOIN customers c ON c.id = pr.customer_id
                ORDER BY pr.redeemed_at DESC, pr.id DESC
                LIMIT 200
            ")->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        $promotion_schema_ready = false;
    }
}

$site_pages = [
    '/index.php' => 'Home Page — Power Levels',
    '/domains/register.php' => 'Neural Domains — Register',
    '/domains/extensions.php' => 'Neural Domains — Extensions',
    '/domains/cyber-scan.php' => 'Cyber Scan',
    '/domains/whois.php' => 'Cyber Scan — WHOIS Registry',
    '/domains/dns-analysis.php' => 'Cyber Scan — DNS Analysis',
    '/domains/site-analyzer.php' => 'Cyber Scan — Site Analyzer',
    '/hosting/linux-shared.php' => 'Quantum Servers — Linux Shared',
    '/hosting/wordpress.php' => 'Quantum Servers — WordPress',
    '/hosting/cloud-hosting.php' => 'Quantum Servers — Cloud Hosting',
    '/hosting/windows.php' => 'Quantum Servers — Windows Hosting',
    '/servers/linux-dedicated.php' => 'Quantum Servers — Linux Dedicated',
    '/servers/windows.php' => 'Quantum Servers — Windows Dedicated',
    '/tools/sslcert.php' => 'Digital Arsenal — SSL Certificates',
    '/tools/sitelock.php' => 'Digital Arsenal — SiteLock',
    '/tools/xcitium.php' => 'Digital Arsenal — Xcitium',
    '/email/google-workspace.php' => 'Comm Arrays — Google Workspace',
    '/email/enterprise.php' => 'Comm Arrays — Enterprise Email',
    '/email/cloud-mail.php' => 'Comm Arrays — Cloud Mail',
    '/branding/logo.php' => 'Neural Graphics — Logo',
    '/branding/business-cards.php' => 'Neural Graphics — Business Cards',
    '/branding/letterheads.php' => 'Neural Graphics — Letterheads',
    '/branding/signatures.php' => 'Neural Graphics — Signatures',
    '/branding/website-builder.php' => 'Neural Graphics — Website Builder',
    '/marketing/seo.php' => 'Marketing Matrix — SEO',
    '/marketing/google-marketing.php' => 'Marketing Matrix — Google Marketing',
    '/marketing/social-media.php' => 'Marketing Matrix — Social Media',
    '/marketing/offers.php' => 'Special Ops',
    '/main-services/domains.php' => 'Neural Domains — Overview',
    '/main-services/hosting.php' => 'Quantum Servers — Hosting Overview',
    '/main-services/servers.php' => 'Quantum Servers — Server Overview',
    '/main-services/email.php' => 'Comm Arrays — Overview',
    '/main-services/tools.php' => 'Digital Arsenal — Overview',
    '/pricing/domain-pricing.php' => 'Pricing — Domains',
    '/pricing/hosting-plans.php' => 'Pricing — Hosting Plans',
    '/pricing/design-packages.php' => 'Pricing — Design Packages',
    '/domains/name-suggestion.php' => 'Neural Domains — Name Suggestions',
    '/domains/transfer.php' => 'Neural Domains — Transfer',
    '/domains/bulk-transfer.php' => 'Neural Domains — Bulk Transfer',
    '/services/web-hosting.php' => 'Services — Web Hosting',
    '/services/domains.php' => 'Services — Domains',
    '/services/email-services.php' => 'Services — Email',
    '/services/website-design.php' => 'Services — Website Design',
    '/services/ssl-security.php' => 'Services — SSL & Security',
    '/services/business-tools.php' => 'Services — Business Tools',
];
$currency_rates = hivenest_currency_rates($conn);
$active_tab = (string)($_SESSION['productp_active_tab'] ?? 'products');
unset($_SESSION['productp_active_tab']);
if (!in_array($active_tab, ['products', 'domains', 'addons', 'currency', 'promotions'], true)) {
    $active_tab = 'products';
}
$admin_csrf = csrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Pricing Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 { font-size: 28px; }
        
        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        
        .btn-primary:hover { background: #45a049; }
        
        .btn-secondary {
            background: #2196F3;
            color: white;
        }
        
        .btn-secondary:hover { background: #0b7dda; }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .btn-danger:hover { background: #da190b; }
        
        .message {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .product-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 16px;
            overflow: hidden;
        }

        .product-card.collapsed .pricing-tiers { display: none; }
        .product-card.collapsed .product-toggle { transform: rotate(-90deg); }
        
        .product-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .product-title { font-size: 20px; font-weight: 600; }
        .product-meta { font-size: 14px; opacity: 0.9; }

        .product-toggle {
            flex: 0 0 auto;
            width: 38px;
            height: 38px;
            padding: 0;
            border: 1px solid rgba(255,255,255,.55);
            border-radius: 50%;
            background: rgba(255,255,255,.12);
            color: white;
            cursor: pointer;
            font-size: 18px;
            transition: transform .2s ease, background .2s ease;
        }

        .product-toggle:hover { background: rgba(255,255,255,.25); }

        .tier-count {
            display: inline-block;
            margin-left: 8px;
            padding: 3px 8px;
            border-radius: 10px;
            background: rgba(255,255,255,.18);
            font-size: 12px;
        }
        
        .pricing-tiers {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
            gap: 14px;
            padding: 18px;
        }

        .site-section-title {
            margin: 34px 0 16px;
            padding: 14px 18px;
            color: #fff;
            background: linear-gradient(135deg, #172554, #312e81);
            border-left: 6px solid #22d3ee;
            border-radius: 7px;
            font-size: 20px;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .section-add-btn, .header-action-btn {
            border: 1px solid rgba(255,255,255,.55);
            border-radius: 6px;
            background: rgba(255,255,255,.14);
            color: white;
            padding: 8px 12px;
            cursor: pointer;
            font-weight: 700;
        }
        .section-add-btn:hover, .header-action-btn:hover { background: rgba(255,255,255,.25); }
        .product-header-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .product-card.product-hidden { opacity: .68; border: 2px dashed #ef4444; }
        .badge-hidden { background: #ef4444; color: white; }

        .catalog-modal {
            position: fixed; inset: 0; z-index: 5000; display: none;
            place-items: center; padding: 20px; background: rgba(0,0,0,.72);
        }
        .catalog-modal.open { display: grid; }
        .catalog-modal-panel {
            width: min(760px, 100%); max-height: 90vh; overflow-y: auto;
            background: white; border-radius: 12px; padding: 24px; box-shadow: 0 20px 70px rgba(0,0,0,.35);
        }
        .catalog-modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .catalog-modal .tier-field.full { grid-column: 1 / -1; }
        .catalog-modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        
        .tier-row {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            margin: 0;
            align-items: start;
            background: #f9f9f9;
            min-width: 0;
        }
        
        .tier-row.featured {
            border-color: #4CAF50;
            background: #f1f8f4;
        }
        .tier-row.package-hidden { opacity: .62; border-style: dashed; border-color: #ef4444; }
        .package-actions { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .package-visibility-btn { border: 0; border-radius: 5px; padding: 6px 10px; cursor: pointer; color: white; background: #475569; }
        .package-visibility-btn.hide { background: #dc2626; }
        .package-visibility-btn.show { background: #16a34a; }
        .bundle-builder {
            border: 1px solid #dbeafe;
            border-radius: 8px;
            background: #f8fbff;
            padding: 10px;
            margin-top: 10px;
        }
        .bundle-builder-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        .bundle-builder-title {
            font-weight: 700;
            color: #1e3a8a;
            font-size: 13px;
        }
        .bundle-add-btn, .bundle-remove-btn {
            border: 0;
            border-radius: 5px;
            padding: 7px 10px;
            cursor: pointer;
            color: white;
            font-weight: 700;
            font-size: 12px;
        }
        .bundle-add-btn { background: #0f766e; }
        .bundle-remove-btn { background: #dc2626; align-self: end; }
        .bundle-row {
            display: grid;
            grid-template-columns: minmax(190px, 1.4fr) minmax(160px, 1fr) minmax(145px, .85fr) minmax(135px, .75fr) 95px 38px;
            gap: 8px;
            align-items: end;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            margin-top: 8px;
        }
        .bundle-row label {
            display: block;
            font-size: 11px;
            color: #64748b;
            margin-bottom: 4px;
            font-weight: 700;
        }
        .bundle-row input,
        .bundle-row select {
            width: 100%;
            padding: 7px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 12px;
        }
        .bundle-empty {
            color: #64748b;
            font-size: 12px;
            padding: 8px 0;
        }
        .bundle-json-error {
            display: none;
            color: #b91c1c;
            background: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 7px 9px;
            margin-top: 8px;
            font-size: 12px;
        }
        
        .tier-field label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .tier-field { width: 100%; }
        
        .tier-field input,
        .tier-field select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .tier-field textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            min-height: 110px;
            resize: vertical;
            font-family: inherit;
        }
        
        .feature-list {
            font-size: 12px;
            color: #555;
            line-height: 1.6;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .no-data-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-featured {
            background: #4CAF50;
            color: white;
        }
        
        .badge-type {
            background: #2196F3;
            color: white;
        }
        
        @media (max-width: 768px) {
            .pricing-tiers { grid-template-columns: 1fr; padding: 12px; }
            .product-header { align-items: flex-start; }
            .catalog-modal-grid { grid-template-columns: 1fr; }
            .catalog-modal .tier-field.full { grid-column: auto; }
            .bundle-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>📦 Product Pricing Management</h1>
            <p style="margin-top: 8px; opacity: 0.9;">Centralized pricing control for all website pages</p>
        </div>
        <div class="header-actions">
            <a href="provisioning.php" class="btn" style="background:#0f766e;color:white;">⚙️ Provisioning Monitor</a>
            <a href="system-test.php" class="btn" style="background:#334155;color:white;">🩺 System Health</a>
            <a href="?logout" class="btn btn-danger">🚪 Logout</a>
        </div>
    </div>
    
    <!-- Tab Navigation -->
    <div style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button onclick="showTab('products')" id="tab-products" class="btn btn-primary">📦 Products & Packages</button>
            <button onclick="showTab('domains')" id="tab-domains" class="btn btn-secondary">🌐 Domain Extensions</button>
            <button onclick="showTab('addons')" id="tab-addons" class="btn btn-secondary">➕ Categories & Addons</button>
            <button onclick="showTab('currency')" id="tab-currency" class="btn btn-secondary">💱 Currency Rates</button>
            <button onclick="showTab('promotions')" id="tab-promotions" class="btn btn-secondary">🏷️ Promotions</button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Products Tab -->
    <div id="products-tab" style="display: block;">
        <div style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <h2 style="margin: 0;">Products & Packages Management</h2>
                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="openProductModal(2, 'HOME PAGE / ANY PAGE')" class="btn" style="background:#0f766e;color:white;">＋ Add Product</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="align_catalog">
                        <button type="submit" class="btn" style="background:#7c3aed;color:white;">Align Storefront Catalogue</button>
                    </form>
                    <button type="button" onclick="setAllProductCards(false)" class="btn btn-secondary">Expand All</button>
                    <button type="button" onclick="setAllProductCards(true)" class="btn btn-secondary">Collapse All</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="rescan">
                        <button type="submit" class="btn btn-primary">🔄 Refresh from DB</button>
                    </form>
                    <button onclick="saveToCache()" class="btn btn-secondary">💾 Save to Cache</button>
                    <button onclick="syncToDatabase()" class="btn" style="background: #ff9800; color: white;">🔃 Save to Database</button>
                </div>
            </div>
        </div>

    <div class="stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo count($pricing_data); ?></div>
            <div class="stat-label">Total Products</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">
                <?php 
                $total_tiers = 0;
                foreach ($pricing_data as $product) {
                    $total_tiers += count($product['pricing_tiers']);
                }
                echo $total_tiers;
                ?>
            </div>
            <div class="stat-label">Pricing Tiers</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">
                <?php echo file_exists($pricing_cache_file) ? date('Y-m-d H:i', filemtime($pricing_cache_file)) : 'Never'; ?>
            </div>
            <div class="stat-label">Last Updated</div>
        </div>
    </div>

    <datalist id="site-pages">
        <?php foreach ($site_pages as $page_path => $page_label): ?>
            <option value="<?php echo htmlspecialchars($page_path); ?>"><?php echo htmlspecialchars($page_label); ?></option>
        <?php endforeach; ?>
    </datalist>
    <datalist id="bundle-sku-options">
        <?php foreach ($pricing_data as $bundle_product): ?>
            <?php foreach (($bundle_product['pricing_tiers'] ?? []) as $bundle_tier): ?>
                <?php
                $bundle_sku = $bundle_product['product_slug'] . '--' . ($bundle_tier['tier_slug'] ?: 'base');
                $bundle_label = $bundle_product['product_name'] . ' / ' . $bundle_tier['tier_name'];
                ?>
                <option value="<?php echo htmlspecialchars($bundle_sku); ?>"><?php echo htmlspecialchars($bundle_label); ?></option>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </datalist>

    <div id="pricing-container">
        <?php if (empty($pricing_data)): ?>
            <div class="no-data">
                <div class="no-data-icon">📭</div>
                <h2>No Pricing Data Found</h2>
                <p>Click "Rescan Database" to load product pricing from the database.</p>
            </div>
        <?php else: ?>
            <?php $current_admin_section = null; ?>
            <?php foreach ($pricing_data as $slug => $product): ?>
                <?php
                [, $product_section] = adminSiteSection($product);
                if ($product_section !== $current_admin_section):
                    $current_admin_section = $product_section;
                ?>
                    <h2 class="site-section-title">
                        <span><?php echo htmlspecialchars($product_section); ?></span>
                        <button type="button" class="section-add-btn" onclick='openProductModal(<?php echo (int)$product["category_id"]; ?>, <?php echo json_encode($product_section); ?>)'>＋ Add Product</button>
                    </h2>
                <?php endif; ?>
                <div class="product-card collapsed <?php echo !empty($product['is_active']) ? '' : 'product-hidden'; ?>" data-slug="<?php echo $slug; ?>">
                    <div class="product-header">
                        <div>
                            <div class="product-title">
                                <input type="text" 
                                       style="background: transparent; border: none; color: white; font-size: 20px; font-weight: 600; width: 100%;"
                                       data-field="product_name" 
                                       data-slug="<?php echo $slug; ?>"
                                       value="<?php echo htmlspecialchars($product['product_name']); ?>"
                                       placeholder="Product Name">
                                <span class="badge badge-type"><?php echo $product['product_type']; ?></span>
                                <span class="tier-count"><?php echo count($product['pricing_tiers']); ?> package<?php echo count($product['pricing_tiers']) === 1 ? '' : 's'; ?></span>
                                <?php if (empty($product['is_active'])): ?><span class="badge badge-hidden">Hidden</span><?php endif; ?>
                            </div>
                            <div class="product-meta">
                                Slug: <strong><?php echo $slug; ?></strong>
                                | Page: 
                                <input type="text" 
                                       style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 2px 8px; border-radius: 3px;"
                                       data-field="page_url" 
                                       data-slug="<?php echo $slug; ?>"
                                       list="site-pages"
                                       value="<?php echo htmlspecialchars($product['page_url']); ?>"
                                       placeholder="/path/to/page.php">
                                | Product order:
                                <input type="number"
                                       style="width: 78px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 2px 8px; border-radius: 3px;"
                                       data-field="sort_order"
                                       data-slug="<?php echo $slug; ?>"
                                       value="<?php echo (int)$product['sort_order']; ?>">
                            </div>
                        </div>
                        <div class="product-header-actions">
                            <button type="button" class="header-action-btn" onclick='openPackageModal(<?php echo (int)$product["product_id"]; ?>, <?php echo json_encode($product["product_name"]); ?>)'>＋ Package</button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($admin_csrf); ?>">
                                <input type="hidden" name="action" value="toggle_product">
                                <input type="hidden" name="product_id" value="<?php echo (int)$product['product_id']; ?>">
                                <input type="hidden" name="is_active" value="<?php echo !empty($product['is_active']) ? 0 : 1; ?>">
                                <button type="submit" class="header-action-btn"><?php echo !empty($product['is_active']) ? 'Hide' : 'Show'; ?></button>
                            </form>
                            <button type="button" class="product-toggle" onclick="toggleProductCard(this)" aria-label="Expand product packages" aria-expanded="false">▼</button>
                        </div>
                    </div>
                    
                    <div class="pricing-tiers">
                        <?php if (empty($product['pricing_tiers'])): ?>
                            <p style="color: #999; text-align: center; padding: 20px;">No pricing tiers defined</p>
                        <?php else: ?>
                            <?php
                            $ordered_tiers = $product['pricing_tiers'];
                            uasort($ordered_tiers, static function($a, $b) {
                                $sortA = (int)($a['sort_order'] ?? 0);
                                $sortB = (int)($b['sort_order'] ?? 0);
                                if ($sortA === $sortB) {
                                    return (int)($a['pricing_id'] ?? 0) <=> (int)($b['pricing_id'] ?? 0);
                                }
                                return $sortA <=> $sortB;
                            });
                            ?>
                            <?php foreach ($ordered_tiers as $index => $tier): ?>
                                <div class="tier-row <?php echo $tier['is_featured'] ? 'featured' : ''; ?> <?php echo empty($tier['is_active']) ? 'package-hidden' : ''; ?>" data-tier-row="true" data-slug="<?php echo $slug; ?>" data-index="<?php echo $index; ?>" data-sort-order="<?php echo (int)$tier['sort_order']; ?>">
                                    <?php if (!empty($tier['pricing_id'])): ?>
                                    <div class="package-actions">
                                        <strong><?php echo empty($tier['is_active']) ? 'Hidden package' : 'Visible package'; ?></strong>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($admin_csrf); ?>">
                                            <input type="hidden" name="action" value="toggle_package">
                                            <input type="hidden" name="pricing_id" value="<?php echo (int)$tier['pricing_id']; ?>">
                                            <input type="hidden" name="is_active" value="<?php echo empty($tier['is_active']) ? 1 : 0; ?>">
                                            <button type="submit" class="package-visibility-btn <?php echo empty($tier['is_active']) ? 'show' : 'hide'; ?>"><?php echo empty($tier['is_active']) ? 'Show Package' : 'Hide Package'; ?></button>
                                        </form>
                                    </div>
                                    <?php endif; ?>
                                    <div class="tier-field">
                                        <label>Tier Name <?php if ($tier['is_featured']): ?><span class="badge badge-featured">Featured</span><?php endif; ?></label>
                                        <input type="text" 
                                               data-field="tier_name" 
                                               data-slug="<?php echo $slug; ?>" 
                                               data-index="<?php echo $index; ?>"
                                               value="<?php echo htmlspecialchars($tier['tier_name']); ?>">
                                    </div>
                                    
                                    <div class="tier-field">
                                        <label>Price ($)</label>
                                        <input type="number" 
                                               step="0.01"
                                               data-field="price" 
                                               data-slug="<?php echo $slug; ?>" 
                                               data-index="<?php echo $index; ?>"
                                               value="<?php echo $tier['price']; ?>">
                                    </div>
                                    
                                    <div class="tier-field">
                                        <label>Billing Cycle</label>
                                        <select data-field="billing_cycle" 
                                                data-slug="<?php echo $slug; ?>" 
                                                data-index="<?php echo $index; ?>">
                                            <option value="monthly" <?php echo $tier['billing_cycle'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                            <option value="quarterly" <?php echo $tier['billing_cycle'] === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                                            <option value="annually" <?php echo $tier['billing_cycle'] === 'annually' ? 'selected' : ''; ?>>Annually</option>
                                            <option value="one_time" <?php echo $tier['billing_cycle'] === 'one_time' ? 'selected' : ''; ?>>One Time</option>
                                            <option value="per_user_monthly" <?php echo $tier['billing_cycle'] === 'per_user_monthly' ? 'selected' : ''; ?>>Per User/Month</option>
                                        </select>
                                    </div>

                                    <div class="tier-field">
                                        <label>Package Order</label>
                                        <input type="number"
                                               data-field="sort_order"
                                               data-slug="<?php echo $slug; ?>"
                                               data-index="<?php echo $index; ?>"
                                               value="<?php echo (int)$tier['sort_order']; ?>">
                                    </div>

                                    <div class="tier-field">
                                        <label>Accent Color</label>
                                        <input type="color"
                                               data-field="accent_color"
                                               data-slug="<?php echo $slug; ?>"
                                               data-index="<?php echo $index; ?>"
                                               value="<?php echo htmlspecialchars($tier['accent_color'] ?: '#00ffff'); ?>">
                                    </div>

                                    <div class="tier-field">
                                        <label>Glow Color</label>
                                        <input type="color"
                                               data-field="glow_color"
                                               data-slug="<?php echo $slug; ?>"
                                               data-index="<?php echo $index; ?>"
                                               value="<?php echo htmlspecialchars($tier['glow_color'] ?: '#00ffff'); ?>">
                                    </div>
                                     
                                    <div class="tier-field">
                                        <label>Features (one per line)</label>
                                        <textarea data-field="features" 
                                                  data-slug="<?php echo $slug; ?>" 
                                                  data-index="<?php echo $index; ?>"><?php echo implode("\n", $tier['features']); ?></textarea>
                                    </div>

                                    <?php if ($product_section === 'SPECIAL OPS'): ?>
                                    <div class="tier-field full">
                                        <label>Bundle Items JSON</label>
                                        <textarea data-field="bundle_items"
                                                  data-slug="<?php echo $slug; ?>"
                                                  data-index="<?php echo $index; ?>"
                                                  data-bundle-json="true"
                                                  placeholder='[{"sku":"wordpress-hosting--starter-lite","name":"WordPress Starter Lite","job_type":"hosting_setup","provider":"myorderbox","requires_domain":true}]'
                                                  style="min-height: 130px; font-family: Consolas, monospace;"><?php echo htmlspecialchars((string)($tier['bundle_items'] ?? '')); ?></textarea>
                                        <div class="bundle-json-error" data-bundle-error="<?php echo $slug . '-' . $index; ?>"></div>
                                        <div class="bundle-builder" data-bundle-builder="true" data-slug="<?php echo $slug; ?>" data-index="<?php echo $index; ?>">
                                            <div class="bundle-builder-header">
                                                <span class="bundle-builder-title">Service Bundle</span>
                                                <button type="button" class="bundle-add-btn" onclick="addBundleServiceRow('<?php echo htmlspecialchars($slug, ENT_QUOTES); ?>', <?php echo (int)$index; ?>)">＋ Add Service Bundle</button>
                                            </div>
                                            <div class="bundle-rows" data-bundle-rows="<?php echo $slug . '-' . $index; ?>"></div>
                                        </div>
                                        <small style="display:block;color:#666;margin-top:6px;">
                                            Optional. Use the row editor above; the JSON is kept as the saved technical format.
                                        </small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="tier-field">
                                        <label>Featured</label>
                                        <input type="checkbox" 
                                               data-field="is_featured" 
                                               data-slug="<?php echo $slug; ?>" 
                                               data-index="<?php echo $index; ?>"
                                               <?php echo $tier['is_featured'] ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    </div>
    <!-- End Products Tab -->

    <!-- Domain Extensions Tab -->
    <div id="domains-tab" style="display: none;">
        <div style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <h2 style="margin: 0;">🌐 Domain Extensions Pricing</h2>
                <div style="display: flex; gap: 10px;">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="fetch_domains">
                        <button type="submit" class="btn btn-primary">🔄 Refresh from DB</button>
                    </form>
                    <button onclick="saveDomainPricing()" class="btn" style="background: #ff9800; color: white;">💾 Save to Database</button>
                </div>
            </div>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($domain_extensions); ?></div>
                <div class="stat-label">Total TLDs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php echo count(array_filter($domain_extensions, function($d) { return $d['is_popular']; })); ?>
                </div>
                <div class="stat-label">Popular TLDs</div>
            </div>
        </div>
        
        <div id="domain-pricing-container">
            <?php if (empty($domain_extensions)): ?>
                <div class="no-data">
                    <div class="no-data-icon">📭</div>
                    <h2>No Domain Extensions Found</h2>
                    <p>Click "Refresh from DB" to load domain extensions from the database.</p>
                </div>
            <?php else: ?>
                <?php foreach ($domain_extensions as $index => $domain): ?>
                    <div class="product-card" style="margin-bottom: 15px;">
                        <div class="tier-row" style="grid-template-columns: 1fr 1fr 1fr 1fr auto;">
                            <div class="tier-field">
                                <label>Extension <?php if ($domain['is_popular']): ?><span class="badge badge-featured">POPULAR</span><?php endif; ?></label>
                                <input type="text" value="<?php echo htmlspecialchars($domain['extension']); ?>" readonly style="background: #f0f0f0; font-weight: bold; font-size: 16px;">
                            </div>
                            <div class="tier-field">
                                <label>Register Price ($)</label>
                                <input type="number" step="0.01" 
                                       data-domain-field="register_price" 
                                       data-domain-index="<?php echo $index; ?>"
                                       value="<?php echo $domain['register_price']; ?>">
                            </div>
                            <div class="tier-field">
                                <label>Renew Price ($)</label>
                                <input type="number" step="0.01" 
                                       data-domain-field="renew_price" 
                                       data-domain-index="<?php echo $index; ?>"
                                       value="<?php echo $domain['renew_price']; ?>">
                            </div>
                            <div class="tier-field">
                                <label>Transfer Price ($)</label>
                                <input type="number" step="0.01" 
                                       data-domain-field="transfer_price" 
                                       data-domain-index="<?php echo $index; ?>"
                                       value="<?php echo $domain['transfer_price']; ?>">
                            </div>
                            <div class="tier-field">
                                <label>Popular</label>
                                <input type="checkbox" 
                                       data-domain-field="is_popular" 
                                       data-domain-index="<?php echo $index; ?>"
                                       <?php echo $domain['is_popular'] ? 'checked' : ''; ?>>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <!-- End Domain Extensions Tab -->

    <!-- Product Addons Tab -->
    <div id="addons-tab" style="display: none;">
        <div style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <h2 style="margin: 0;">➕ Categories & Product Addons</h2>
                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="openCategoryModal()" class="btn" style="background:#7c3aed;color:white;">＋ New Category + First Product</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="fetch_addons">
                        <button type="submit" class="btn btn-primary">🔄 Refresh from DB</button>
                    </form>
                    <button onclick="saveAddonPricing()" class="btn" style="background: #ff9800; color: white;">💾 Save to Database</button>
                </div>
            </div>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($product_categories); ?></div>
                <div class="stat-label">Product Categories</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($product_addons); ?></div>
                <div class="stat-label">Total Addons</div>
            </div>
        </div>
        
        <div id="addon-pricing-container">
            <?php if (empty($product_addons)): ?>
                <div class="no-data">
                    <div class="no-data-icon">📭</div>
                    <h2>No Product Addons Found</h2>
                    <p>Click "Refresh from DB" to load addons from the database.</p>
                </div>
            <?php else: ?>
                <?php foreach ($product_addons as $index => $addon): ?>
                    <div class="product-card" style="margin-bottom: 15px;">
                        <div class="tier-row" style="grid-template-columns: 2fr 1fr 1fr 2fr;">
                            <div class="tier-field">
                                <label>Addon Name</label>
                                <input type="text" 
                                       data-addon-field="addon_name" 
                                       data-addon-index="<?php echo $index; ?>"
                                       value="<?php echo htmlspecialchars($addon['addon_name']); ?>">
                            </div>
                            <div class="tier-field">
                                <label>Price ($)</label>
                                <input type="number" step="0.01" 
                                       data-addon-field="price" 
                                       data-addon-index="<?php echo $index; ?>"
                                       value="<?php echo $addon['price']; ?>">
                            </div>
                            <div class="tier-field">
                                <label>Billing Cycle</label>
                                <select data-addon-field="billing_cycle" 
                                        data-addon-index="<?php echo $index; ?>">
                                    <option value="one_time" <?php echo $addon['billing_cycle'] === 'one_time' ? 'selected' : ''; ?>>One Time</option>
                                    <option value="monthly" <?php echo $addon['billing_cycle'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    <option value="quarterly" <?php echo $addon['billing_cycle'] === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                                    <option value="annually" <?php echo $addon['billing_cycle'] === 'annually' ? 'selected' : ''; ?>>Annually</option>
                                </select>
                            </div>
                            <div class="tier-field">
                                <label>Description</label>
                                <textarea data-addon-field="description" 
                                          data-addon-index="<?php echo $index; ?>"
                                          style="min-height: 60px;"><?php echo htmlspecialchars($addon['description']); ?></textarea>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <!-- End Product Addons Tab -->

    <!-- Currency Rates Tab -->
    <div id="currency-tab" style="display: none;">
        <div style="background:white;padding:24px;border-radius:10px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
            <h2 style="margin-bottom:8px;">💱 Storefront Display Currency Rates</h2>
            <p style="color:#5b6472;line-height:1.6;margin-bottom:22px;">
                Enter how much of each display currency equals <strong>USD 1.00</strong>.
                These values change visible storefront estimates only. Catalogue records,
                orders, invoices, loyalty calculations, MyOrderBox credit and PayPal remain in USD.
            </p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($admin_csrf); ?>">
                <input type="hidden" name="action" value="update_currency_rates">
                <div class="catalog-modal-grid" style="max-width:1000px;">
                    <div class="tier-field">
                        <label>USD Base Rate</label>
                        <input type="number" value="1.000000" disabled>
                        <small style="display:block;color:#64748b;margin-top:5px;">Fixed base currency</small>
                    </div>
                    <div class="tier-field">
                        <label>ZAR per USD</label>
                        <input type="number" min="0.000001" max="99999" step="0.000001" name="zar_rate" required value="<?php echo htmlspecialchars(number_format((float)$currency_rates['ZAR'], 6, '.', '')); ?>">
                        <small style="display:block;color:#64748b;margin-top:5px;">USD 100 ≈ ZAR <?php echo htmlspecialchars(number_format(100 * (float)$currency_rates['ZAR'], 2)); ?></small>
                    </div>
                    <div class="tier-field">
                        <label>EUR per USD</label>
                        <input type="number" min="0.000001" max="99999" step="0.000001" name="eur_rate" required value="<?php echo htmlspecialchars(number_format((float)$currency_rates['EUR'], 6, '.', '')); ?>">
                        <small style="display:block;color:#64748b;margin-top:5px;">USD 100 ≈ EUR <?php echo htmlspecialchars(number_format(100 * (float)$currency_rates['EUR'], 2)); ?></small>
                    </div>
                    <div class="tier-field">
                        <label>SGD per USD</label>
                        <input type="number" min="0.000001" max="99999" step="0.000001" name="sgd_rate" required value="<?php echo htmlspecialchars(number_format((float)$currency_rates['SGD'], 6, '.', '')); ?>">
                        <small style="display:block;color:#64748b;margin-top:5px;">USD 100 ≈ SGD <?php echo htmlspecialchars(number_format(100 * (float)$currency_rates['SGD'], 2)); ?></small>
                    </div>
                </div>
                <div style="margin-top:22px;padding:14px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;color:#9a3412;">
                    Rates are manually maintained and indicative. Review them regularly before advertising converted prices.
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:20px;">Save Display Rates</button>
            </form>
        </div>
    </div>
    <!-- End Currency Rates Tab -->

    <!-- Promotions Tab -->
    <div id="promotions-tab" style="display: none;">
        <?php if (!$promotion_schema_ready): ?>
            <div class="message error">
                Promotion storage is not ready. Import
                <strong>Database/promotion_redemptions.sql</strong> for a new installation, or
                <strong>Database/promotion_refund_tracking.sql</strong> if the redemption table already exists.
            </div>
        <?php else: ?>
            <div style="background:white;padding:24px;border-radius:10px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
                <h2 style="margin-bottom:8px;">🏷️ Create Promotion Code</h2>
                <p style="color:#5b6472;line-height:1.6;margin-bottom:22px;">
                    Codes are verified against live catalogue prices at checkout. Promotion discounts stack with the
                    customer's loyalty discount, but the final PayPal total can never be reduced below USD 0.01.
                </p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($admin_csrf); ?>">
                    <input type="hidden" name="action" value="save_promotion">
                    <input type="hidden" name="promotion_id" value="0">
                    <div class="catalog-modal-grid">
                        <div class="tier-field">
                            <label>Code *</label>
                            <input type="text" name="code" required minlength="3" maxlength="50" pattern="[A-Za-z0-9_-]{3,50}" placeholder="WELCOME20" style="text-transform:uppercase;">
                        </div>
                        <div class="tier-field">
                            <label>Discount Type *</label>
                            <select name="discount_type" required>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed_amount">Fixed USD amount</option>
                            </select>
                        </div>
                        <div class="tier-field">
                            <label>Discount Value *</label>
                            <input type="number" name="discount_value" required min="0.01" max="999999" step="0.01" value="10.00">
                        </div>
                        <div class="tier-field">
                            <label>Minimum Order (USD)</label>
                            <input type="number" name="minimum_order_amount" min="0" max="99999999" step="0.01" value="0.00">
                        </div>
                        <div class="tier-field">
                            <label>Global Usage Limit</label>
                            <input type="number" name="usage_limit" min="0" step="1" value="0">
                            <small style="display:block;color:#64748b;margin-top:5px;">0 means unlimited</small>
                        </div>
                        <div class="tier-field">
                            <label>Uses Per Customer</label>
                            <input type="number" name="customer_usage_limit" min="0" step="1" value="1">
                            <small style="display:block;color:#64748b;margin-top:5px;">0 means unlimited</small>
                        </div>
                        <div class="tier-field">
                            <label>Starts *</label>
                            <input type="datetime-local" name="start_date" required value="<?php echo htmlspecialchars(date('Y-m-d\TH:i')); ?>">
                        </div>
                        <div class="tier-field">
                            <label>Ends *</label>
                            <input type="datetime-local" name="end_date" required value="<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime('+30 days'))); ?>">
                        </div>
                        <div class="tier-field full">
                            <label>Description</label>
                            <input type="text" name="description" maxlength="1000" placeholder="Customer-facing reason for this promotion">
                        </div>
                        <div class="tier-field">
                            <label>Applicable Product SKUs / Groups</label>
                            <textarea name="applicable_products" rows="3" placeholder="all, hosting, domain">all</textarea>
                            <small style="display:block;color:#64748b;margin-top:5px;">Comma or line separated. Use exact SKU, product slug, or all.</small>
                        </div>
                        <div class="tier-field">
                            <label>Applicable Categories</label>
                            <textarea name="applicable_categories" rows="3" placeholder="all, hosting, design">all</textarea>
                            <small style="display:block;color:#64748b;margin-top:5px;">Supported groups include domain, hosting, server, email, ssl, security, backup, design and marketing.</small>
                        </div>
                        <div class="tier-field full">
                            <label style="display:flex;align-items:center;gap:8px;">
                                <input type="checkbox" name="is_active" value="1" checked style="width:auto;">
                                Active immediately within the selected dates
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top:20px;">Create Promotion</button>
                </form>
            </div>

            <div style="background:white;padding:24px;border-radius:10px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
                <h2 style="margin-bottom:18px;">Existing Promotion Codes</h2>
                <?php if (!$promotion_codes): ?>
                    <p style="color:#64748b;">No promotion codes have been created.</p>
                <?php endif; ?>
                <?php foreach ($promotion_codes as $promotion): ?>
                    <?php
                    $productRestrictions = json_decode((string)($promotion['applicable_products'] ?? ''), true);
                    $categoryRestrictions = json_decode((string)($promotion['applicable_categories'] ?? ''), true);
                    $productRestrictions = is_array($productRestrictions) ? $productRestrictions : ['all'];
                    $categoryRestrictions = is_array($categoryRestrictions) ? $categoryRestrictions : ['all'];
                    $now = time();
                    $starts = strtotime((string)$promotion['start_date']) ?: 0;
                    $ends = strtotime((string)$promotion['end_date']) ?: 0;
                    $liveNow = !empty($promotion['is_active']) && $starts <= $now && $ends > $now;
                    ?>
                    <div style="border:1px solid #dbe2ea;border-left:5px solid <?php echo $liveNow ? '#16a34a' : '#94a3b8'; ?>;border-radius:9px;padding:18px;margin-bottom:18px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:15px;">
                            <div>
                                <h3 style="margin:0 0 5px;"><?php echo htmlspecialchars((string)$promotion['code']); ?></h3>
                                <span style="display:inline-block;padding:4px 8px;border-radius:999px;background:<?php echo $liveNow ? '#dcfce7' : '#e2e8f0'; ?>;color:<?php echo $liveNow ? '#166534' : '#475569'; ?>;font-size:12px;font-weight:700;">
                                    <?php echo $liveNow ? 'LIVE' : (!empty($promotion['is_active']) ? 'SCHEDULED / EXPIRED' : 'INACTIVE'); ?>
                                </span>
                                <span style="margin-left:8px;color:#64748b;font-size:13px;">
                                    Used <?php echo (int)$promotion['usage_count']; ?>
                                    / <?php echo (int)$promotion['usage_limit'] > 0 ? (int)$promotion['usage_limit'] : 'unlimited'; ?>
                                </span>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($admin_csrf); ?>">
                                <input type="hidden" name="action" value="toggle_promotion">
                                <input type="hidden" name="promotion_id" value="<?php echo (int)$promotion['id']; ?>">
                                <input type="hidden" name="is_active" value="<?php echo !empty($promotion['is_active']) ? '0' : '1'; ?>">
                                <button type="submit" class="btn <?php echo !empty($promotion['is_active']) ? 'btn-danger' : 'btn-primary'; ?>">
                                    <?php echo !empty($promotion['is_active']) ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </form>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($admin_csrf); ?>">
                            <input type="hidden" name="action" value="save_promotion">
                            <input type="hidden" name="promotion_id" value="<?php echo (int)$promotion['id']; ?>">
                            <div class="catalog-modal-grid">
                                <div class="tier-field">
                                    <label>Code *</label>
                                    <input type="text" name="code" required minlength="3" maxlength="50" pattern="[A-Za-z0-9_-]{3,50}" value="<?php echo htmlspecialchars((string)$promotion['code']); ?>" style="text-transform:uppercase;">
                                </div>
                                <div class="tier-field">
                                    <label>Discount Type *</label>
                                    <select name="discount_type">
                                        <option value="percentage" <?php echo $promotion['discount_type'] === 'percentage' ? 'selected' : ''; ?>>Percentage (%)</option>
                                        <option value="fixed_amount" <?php echo $promotion['discount_type'] === 'fixed_amount' ? 'selected' : ''; ?>>Fixed USD amount</option>
                                    </select>
                                </div>
                                <div class="tier-field">
                                    <label>Discount Value *</label>
                                    <input type="number" name="discount_value" required min="0.01" max="999999" step="0.01" value="<?php echo htmlspecialchars(number_format((float)$promotion['discount_value'], 2, '.', '')); ?>">
                                </div>
                                <div class="tier-field">
                                    <label>Minimum Order (USD)</label>
                                    <input type="number" name="minimum_order_amount" min="0" max="99999999" step="0.01" value="<?php echo htmlspecialchars(number_format((float)$promotion['minimum_order_amount'], 2, '.', '')); ?>">
                                </div>
                                <div class="tier-field">
                                    <label>Global Usage Limit</label>
                                    <input type="number" name="usage_limit" min="0" step="1" value="<?php echo (int)$promotion['usage_limit']; ?>">
                                </div>
                                <div class="tier-field">
                                    <label>Uses Per Customer</label>
                                    <input type="number" name="customer_usage_limit" min="0" step="1" value="<?php echo (int)$promotion['customer_usage_limit']; ?>">
                                </div>
                                <div class="tier-field">
                                    <label>Starts *</label>
                                    <input type="datetime-local" name="start_date" required value="<?php echo htmlspecialchars(date('Y-m-d\TH:i', $starts)); ?>">
                                </div>
                                <div class="tier-field">
                                    <label>Ends *</label>
                                    <input type="datetime-local" name="end_date" required value="<?php echo htmlspecialchars(date('Y-m-d\TH:i', $ends)); ?>">
                                </div>
                                <div class="tier-field full">
                                    <label>Description</label>
                                    <input type="text" name="description" maxlength="1000" value="<?php echo htmlspecialchars((string)($promotion['description'] ?? '')); ?>">
                                </div>
                                <div class="tier-field">
                                    <label>Applicable Product SKUs / Groups</label>
                                    <textarea name="applicable_products" rows="3"><?php echo htmlspecialchars(implode(', ', $productRestrictions)); ?></textarea>
                                </div>
                                <div class="tier-field">
                                    <label>Applicable Categories</label>
                                    <textarea name="applicable_categories" rows="3"><?php echo htmlspecialchars(implode(', ', $categoryRestrictions)); ?></textarea>
                                </div>
                                <div class="tier-field full">
                                    <label style="display:flex;align-items:center;gap:8px;">
                                        <input type="checkbox" name="is_active" value="1" <?php echo !empty($promotion['is_active']) ? 'checked' : ''; ?> style="width:auto;">
                                        Active
                                    </label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary" style="margin-top:16px;">Save Changes</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="background:white;padding:24px;border-radius:10px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
                <h2 style="margin-bottom:8px;">Promotion Redemption History</h2>
                <p style="color:#5b6472;line-height:1.6;margin-bottom:18px;">
                    Latest 200 successful paid redemptions. A code appears here only after PayPal capture and local order creation.
                </p>
                <?php if (!$promotion_redemptions): ?>
                    <p style="color:#64748b;">No paid promotion redemptions recorded yet.</p>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;min-width:850px;">
                            <thead>
                                <tr style="background:#f1f5f9;text-align:left;">
                                    <th style="padding:11px;border-bottom:1px solid #cbd5e1;">Redeemed</th>
                                    <th style="padding:11px;border-bottom:1px solid #cbd5e1;">Code</th>
                                    <th style="padding:11px;border-bottom:1px solid #cbd5e1;">Customer</th>
                                    <th style="padding:11px;border-bottom:1px solid #cbd5e1;">Order</th>
                                    <th style="padding:11px;border-bottom:1px solid #cbd5e1;">Discount</th>
                                    <th style="padding:11px;border-bottom:1px solid #cbd5e1;">Paid Total</th>
                                    <th style="padding:11px;border-bottom:1px solid #cbd5e1;">Payment</th>
                                    <th style="padding:11px;border-bottom:1px solid #cbd5e1;">Redemption</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($promotion_redemptions as $redemption): ?>
                                    <?php
                                    $customerName = trim((string)$redemption['first_name'] . ' ' . (string)$redemption['last_name']);
                                    if ($customerName === '') $customerName = (string)($redemption['company_name'] ?: $redemption['email']);
                                    ?>
                                    <tr>
                                        <td style="padding:11px;border-bottom:1px solid #e2e8f0;white-space:nowrap;"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)$redemption['redeemed_at']) ?: time())); ?></td>
                                        <td style="padding:11px;border-bottom:1px solid #e2e8f0;font-weight:700;"><?php echo htmlspecialchars((string)$redemption['code']); ?></td>
                                        <td style="padding:11px;border-bottom:1px solid #e2e8f0;">
                                            <?php echo htmlspecialchars($customerName); ?><br>
                                            <small style="color:#64748b;"><?php echo htmlspecialchars((string)$redemption['email']); ?></small>
                                        </td>
                                        <td style="padding:11px;border-bottom:1px solid #e2e8f0;">
                                            <?php if (!empty($redemption['order_number'])): ?>
                                                <a href="../invoice.php?order=<?php echo rawurlencode((string)$redemption['order_number']); ?>" target="_blank" rel="noopener" style="color:#2563eb;font-weight:700;text-decoration:none;">
                                                    <?php echo htmlspecialchars((string)$redemption['order_number']); ?>
                                                </a>
                                            <?php else: ?>
                                                <strong>-</strong>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:11px;border-bottom:1px solid #e2e8f0;color:#16a34a;font-weight:700;white-space:nowrap;">
                                            -<?php echo htmlspecialchars((string)$redemption['currency']); ?>
                                            <?php echo htmlspecialchars(number_format((float)$redemption['discount_amount'], 2)); ?>
                                        </td>
                                        <td style="padding:11px;border-bottom:1px solid #e2e8f0;white-space:nowrap;">
                                            USD <?php echo htmlspecialchars(number_format((float)$redemption['total_amount'], 2)); ?>
                                        </td>
                                        <td style="padding:11px;border-bottom:1px solid #e2e8f0;">
                                            <?php echo htmlspecialchars(strtoupper((string)$redemption['payment_status'])); ?>
                                        </td>
                                        <td style="padding:11px;border-bottom:1px solid #e2e8f0;">
                                            <?php if (!empty($redemption['reversed_at'])): ?>
                                                <strong style="color:#dc2626;">REVERSED</strong><br>
                                                <small style="color:#64748b;">
                                                    <?php echo htmlspecialchars((string)($redemption['reversal_reason'] ?? 'Fully refunded')); ?><br>
                                                    <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)$redemption['reversed_at']) ?: time())); ?>
                                                </small>
                                            <?php else: ?>
                                                <strong style="color:#16a34a;">REDEEMED</strong>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <!-- End Promotions Tab -->

    <div id="product-modal" class="catalog-modal" role="dialog" aria-modal="true" aria-labelledby="product-modal-title">
        <div class="catalog-modal-panel">
            <h2 id="product-modal-title">Add Product</h2>
            <p id="product-modal-section" style="color:#666;margin:6px 0 18px;"></p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($admin_csrf); ?>">
                <input type="hidden" name="action" value="add_product">
                <div class="catalog-modal-grid">
                    <div class="tier-field"><label>Database Category *</label><select name="category_id" id="new-product-category-id" required><?php foreach ($product_categories as $category): ?><option value="<?php echo (int)$category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="tier-field"><label>Product Name *</label><input name="product_name" required></div>
                    <div class="tier-field"><label>Product Slug</label><input name="product_slug" placeholder="generated-from-name"></div>
                    <div class="tier-field full"><label>Assign to Website Page *</label><select name="page_url" required><option value="">Select page…</option><?php foreach ($site_pages as $path=>$label): ?><option value="<?php echo htmlspecialchars($path); ?>"><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select></div>
                    <div class="tier-field"><label>Product Type *</label><select name="product_type" required><option value="domain">Domain</option><option value="hosting">Hosting</option><option value="server">Server</option><option value="email">Email</option><option value="security">Security</option><option value="ssl">SSL</option><option value="backup">Backup</option><option value="design">Design / Marketing</option></select></div>
                    <div class="tier-field"><label>First Package Name *</label><input name="package_name" required></div>
                    <div class="tier-field"><label>Package Slug</label><input name="package_slug" placeholder="generated-from-package-name"></div>
                    <div class="tier-field"><label>Price ($) *</label><input type="number" min="0" step="0.01" name="price" required></div>
                    <div class="tier-field"><label>Tier Level</label><select name="tier_level"><option value="basic">Basic</option><option value="standard">Standard</option><option value="premium">Premium</option></select></div>
                    <div class="tier-field"><label>Billing Cycle</label><select name="billing_cycle"><option value="monthly">Monthly</option><option value="per_user_monthly">Per User / Month</option><option value="quarterly">Quarterly</option><option value="semi_annually">Semi-annually</option><option value="annually">Annually</option><option value="one_time">One Time</option></select></div>
                    <div class="tier-field"><label>Accent Color</label><input type="color" name="accent_color" value="#00ffff"></div>
                    <div class="tier-field"><label>Glow Color</label><input type="color" name="glow_color" value="#00ffff"></div>
                    <div class="tier-field full"><label>Features (one per line)</label><textarea name="features"></textarea></div>
                </div>
                <div class="catalog-modal-actions"><button type="button" class="btn btn-secondary" onclick="closeCatalogModal('product-modal')">Cancel</button><button type="submit" class="btn btn-primary">Create Product + Package</button></div>
            </form>
        </div>
    </div>

    <div id="package-modal" class="catalog-modal" role="dialog" aria-modal="true" aria-labelledby="package-modal-title">
        <div class="catalog-modal-panel">
            <h2 id="package-modal-title">Add Package</h2>
            <p id="package-modal-product" style="color:#666;margin:6px 0 18px;"></p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($admin_csrf); ?>">
                <input type="hidden" name="action" value="add_package">
                <input type="hidden" name="product_id" id="new-package-product-id">
                <div class="catalog-modal-grid">
                    <div class="tier-field"><label>Package Name *</label><input name="package_name" required></div>
                    <div class="tier-field"><label>Package Slug</label><input name="package_slug" placeholder="generated-from-name"></div>
                    <div class="tier-field"><label>Price ($) *</label><input type="number" min="0" step="0.01" name="price" required></div>
                    <div class="tier-field"><label>Tier Level</label><select name="tier_level"><option value="basic">Basic</option><option value="standard">Standard</option><option value="premium">Premium</option></select></div>
                    <div class="tier-field"><label>Billing Cycle</label><select name="billing_cycle"><option value="monthly">Monthly</option><option value="per_user_monthly">Per User / Month</option><option value="quarterly">Quarterly</option><option value="semi_annually">Semi-annually</option><option value="annually">Annually</option><option value="one_time">One Time</option></select></div>
                    <div class="tier-field"><label>Accent Color</label><input type="color" name="accent_color" value="#00ffff"></div>
                    <div class="tier-field"><label>Glow Color</label><input type="color" name="glow_color" value="#00ffff"></div>
                    <div class="tier-field full"><label>Features (one per line)</label><textarea name="features"></textarea></div>
                </div>
                <div class="catalog-modal-actions"><button type="button" class="btn btn-secondary" onclick="closeCatalogModal('package-modal')">Cancel</button><button type="submit" class="btn btn-primary">Add Package</button></div>
            </form>
        </div>
    </div>

    <div id="category-modal" class="catalog-modal" role="dialog" aria-modal="true" aria-labelledby="category-modal-title">
        <div class="catalog-modal-panel">
            <h2 id="category-modal-title">Create Category + First Product</h2>
            <p style="color:#666;margin:6px 0 18px;">A new category cannot be empty. Complete its first product and package below.</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($admin_csrf); ?>">
                <input type="hidden" name="action" value="add_category">
                <div class="catalog-modal-grid">
                    <div class="tier-field"><label>Category Name *</label><input name="category_name" required placeholder="e.g. AI SERVICES"></div>
                    <div class="tier-field"><label>Category Slug</label><input name="category_slug" placeholder="generated-from-name"></div>
                    <div class="tier-field"><label>Category Position</label><input type="number" name="category_sort_order" value="90" min="1"></div>
                    <div class="tier-field"><label>First Product Name *</label><input name="product_name" required></div>
                    <div class="tier-field"><label>Product Slug</label><input name="product_slug"></div>
                    <div class="tier-field"><label>Product Type *</label><select name="product_type" required><option value="domain">Domain</option><option value="hosting">Hosting</option><option value="server">Server</option><option value="email">Email</option><option value="security">Security</option><option value="ssl">SSL</option><option value="backup">Backup</option><option value="design">Design / Marketing</option></select></div>
                    <div class="tier-field full"><label>Assign to Website Page *</label><select name="page_url" required><option value="">Select page…</option><?php foreach ($site_pages as $path=>$label): ?><option value="<?php echo htmlspecialchars($path); ?>"><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select></div>
                    <div class="tier-field"><label>First Package Name *</label><input name="package_name" required></div>
                    <div class="tier-field"><label>Package Slug</label><input name="package_slug"></div>
                    <div class="tier-field"><label>Price ($) *</label><input type="number" min="0" step="0.01" name="price" required></div>
                    <div class="tier-field"><label>Tier Level</label><select name="tier_level"><option value="basic">Basic</option><option value="standard">Standard</option><option value="premium">Premium</option></select></div>
                    <div class="tier-field"><label>Billing Cycle</label><select name="billing_cycle"><option value="monthly">Monthly</option><option value="per_user_monthly">Per User / Month</option><option value="quarterly">Quarterly</option><option value="semi_annually">Semi-annually</option><option value="annually">Annually</option><option value="one_time">One Time</option></select></div>
                    <div class="tier-field"><label>Accent Color</label><input type="color" name="accent_color" value="#00ffff"></div>
                    <div class="tier-field"><label>Glow Color</label><input type="color" name="glow_color" value="#00ffff"></div>
                    <div class="tier-field full"><label>Features (one per line)</label><textarea name="features"></textarea></div>
                </div>
                <div class="catalog-modal-actions"><button type="button" class="btn btn-secondary" onclick="closeCatalogModal('category-modal')">Cancel</button><button type="submit" class="btn btn-primary">Create Category + Product</button></div>
            </form>
        </div>
    </div>

    <script>
        // Store original data
        let pricingData = <?php echo json_encode($pricing_data); ?>;
        let domainData = <?php echo json_encode($domain_extensions); ?>;
        let addonData = <?php echo json_encode($product_addons); ?>;

        function openProductModal(categoryId, sectionName) {
            document.getElementById('new-product-category-id').value = categoryId;
            document.getElementById('product-modal-section').textContent = 'Category: ' + sectionName;
            document.getElementById('product-modal').classList.add('open');
        }

        function openPackageModal(productId, productName) {
            document.getElementById('new-package-product-id').value = productId;
            document.getElementById('package-modal-product').textContent = 'Product: ' + productName;
            document.getElementById('package-modal').classList.add('open');
        }

        function openCategoryModal() {
            document.getElementById('category-modal').classList.add('open');
        }

        function closeCatalogModal(id) {
            document.getElementById(id).classList.remove('open');
        }

        document.querySelectorAll('.catalog-modal').forEach(modal => {
            modal.addEventListener('click', event => {
                if (event.target === modal) modal.classList.remove('open');
            });
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') document.querySelectorAll('.catalog-modal.open').forEach(modal => modal.classList.remove('open'));
        });

        function toggleProductCard(button) {
            const card = button.closest('.product-card');
            const collapsed = card.classList.toggle('collapsed');
            button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            button.setAttribute('aria-label', collapsed ? 'Expand product packages' : 'Collapse product packages');
        }

        function setAllProductCards(collapse) {
            document.querySelectorAll('.product-card').forEach(card => {
                card.classList.toggle('collapsed', collapse);
                const button = card.querySelector('.product-toggle');
                if (button) button.setAttribute('aria-expanded', collapse ? 'false' : 'true');
            });
        }

        function bundleSkuLabelMap() {
            const map = {};
            Object.values(pricingData || {}).forEach(product => {
                (product.pricing_tiers || []).forEach(tier => {
                    const sku = product.product_slug + '--' + (tier.tier_slug || 'base');
                    map[sku] = {
                        name: tier.tier_name || product.product_name || sku,
                        product: product.product_name || '',
                        type: product.product_type || ''
                    };
                });
            });
            return map;
        }

        function defaultBundleJobType(sku, name) {
            const text = (sku + ' ' + name).toLowerCase();
            if (text.includes('domain')) return 'domain_registration';
            if (text.includes('hosting') || text.includes('wordpress') || text.includes('server') || text.includes('vps')) return 'hosting_setup';
            if (text.includes('ssl')) return 'ssl_setup';
            if (text.includes('backup') || text.includes('xcitium')) return 'backup_setup';
            if (text.includes('sitelock') || text.includes('security')) return 'security_setup';
            if (text.includes('email') || text.includes('mail') || text.includes('workspace')) return 'email_setup';
            if (text.includes('seo') || text.includes('marketing') || text.includes('social')) return 'marketing_queue';
            return 'design_queue';
        }

        function defaultBundleProvider(jobType) {
            return ['design_queue', 'marketing_queue', 'manual_queue'].includes(jobType) ? 'hivenest_team' : 'myorderbox';
        }

        function parseBundleItems(slug, index) {
            const textarea = document.querySelector('textarea[data-bundle-json="true"][data-slug="' + slug + '"][data-index="' + index + '"]');
            const error = document.querySelector('[data-bundle-error="' + slug + '-' + index + '"]');
            if (!textarea) return [];
            const value = textarea.value.trim();
            if (error) {
                error.style.display = 'none';
                error.textContent = '';
            }
            if (value === '') return [];
            try {
                const parsed = JSON.parse(value);
                if (Array.isArray(parsed)) return parsed.filter(item => item && typeof item === 'object');
                throw new Error('Bundle JSON must be an array.');
            } catch (e) {
                if (error) {
                    error.textContent = 'Bundle JSON error: ' + e.message;
                    error.style.display = 'block';
                }
                console.error('Bundle JSON error', { slug, index, error: e });
                return [];
            }
        }

        function saveBundleItems(slug, index, items) {
            const textarea = document.querySelector('textarea[data-bundle-json="true"][data-slug="' + slug + '"][data-index="' + index + '"]');
            if (!textarea) return;
            const cleanItems = items
                .filter(item => item && (String(item.sku || '').trim() !== '' || String(item.name || '').trim() !== ''))
                .map(item => {
                    const clean = {
                        sku: String(item.sku || '').trim(),
                        name: String(item.name || '').trim(),
                        job_type: String(item.job_type || '').trim() || defaultBundleJobType(item.sku || '', item.name || ''),
                        provider: String(item.provider || '').trim() || defaultBundleProvider(item.job_type || ''),
                        quantity: Math.max(1, parseInt(item.quantity || 1, 10) || 1)
                    };
                    if (item.requires_domain) clean.requires_domain = true;
                    return clean;
                });
            textarea.value = cleanItems.length ? JSON.stringify(cleanItems, null, 2) : '';
            if (pricingData[slug] && pricingData[slug].pricing_tiers[index]) {
                pricingData[slug].pricing_tiers[index].bundle_items = textarea.value;
            }
            textarea.style.borderColor = '#4CAF50';
            setTimeout(() => { textarea.style.borderColor = ''; }, 1000);
        }

        function renderBundleEditor(slug, index) {
            const container = document.querySelector('[data-bundle-rows="' + slug + '-' + index + '"]');
            if (!container) return;
            const items = parseBundleItems(slug, index);
            const skuMap = bundleSkuLabelMap();
            container.innerHTML = '';

            if (!items.length) {
                container.innerHTML = '<div class="bundle-empty">No bundled services yet. Click “Add Service Bundle”.</div>';
                return;
            }

            items.forEach((item, rowIndex) => {
                const sku = String(item.sku || '');
                const inferred = skuMap[sku] || {};
                const name = String(item.name || inferred.name || '');
                const jobType = String(item.job_type || defaultBundleJobType(sku, name));
                const provider = String(item.provider || defaultBundleProvider(jobType));
                const quantity = Math.max(1, parseInt(item.quantity || 1, 10) || 1);
                const requiresDomain = item.requires_domain === true || item.requires_domain === 1 || item.requires_domain === 'true';
                const row = document.createElement('div');
                row.className = 'bundle-row';
                row.innerHTML = `
                    <div>
                        <label>Service SKU</label>
                        <input list="bundle-sku-options" data-bundle-field="sku" value="${escapeHtml(sku)}" placeholder="wordpress-hosting--starter-lite">
                    </div>
                    <div>
                        <label>Service Name</label>
                        <input data-bundle-field="name" value="${escapeHtml(name)}" placeholder="WordPress Starter Lite">
                    </div>
                    <div>
                        <label>Job Type</label>
                        <select data-bundle-field="job_type">
                            ${bundleJobTypeOptions(jobType)}
                        </select>
                    </div>
                    <div>
                        <label>Provider</label>
                        <select data-bundle-field="provider">
                            <option value="myorderbox" ${provider === 'myorderbox' ? 'selected' : ''}>MyOrderBox</option>
                            <option value="hivenest_team" ${provider === 'hivenest_team' ? 'selected' : ''}>HiveNest Team</option>
                        </select>
                    </div>
                    <div>
                        <label>Qty / Domain</label>
                        <input data-bundle-field="quantity" type="number" min="1" value="${quantity}">
                        <label style="margin-top:5px;display:flex;align-items:center;gap:5px;">
                            <input data-bundle-field="requires_domain" type="checkbox" ${requiresDomain ? 'checked' : ''} style="width:auto;"> Domain
                        </label>
                    </div>
                    <button type="button" class="bundle-remove-btn" title="Remove service">×</button>
                `;
                row.querySelector('.bundle-remove-btn').addEventListener('click', () => {
                    const next = parseBundleItems(slug, index);
                    next.splice(rowIndex, 1);
                    saveBundleItems(slug, index, next);
                    renderBundleEditor(slug, index);
                });
                row.querySelectorAll('[data-bundle-field]').forEach(input => {
                    input.addEventListener('change', () => {
                        const next = parseBundleItems(slug, index);
                        const current = next[rowIndex] || {};
                        const field = input.getAttribute('data-bundle-field');
                        let value = input.type === 'checkbox' ? input.checked : input.value;
                        current[field] = value;
                        if (field === 'sku') {
                            const selected = bundleSkuLabelMap()[String(value || '')];
                            if (selected) current.name = selected.name;
                            current.job_type = defaultBundleJobType(value, current.name || '');
                            current.provider = defaultBundleProvider(current.job_type);
                            current.requires_domain = ['domain_registration', 'hosting_setup', 'ssl_setup', 'backup_setup', 'security_setup', 'email_setup'].includes(current.job_type);
                        }
                        if (field === 'job_type') {
                            current.provider = defaultBundleProvider(String(value || ''));
                        }
                        next[rowIndex] = current;
                        saveBundleItems(slug, index, next);
                        renderBundleEditor(slug, index);
                    });
                });
                container.appendChild(row);
            });
        }

        function addBundleServiceRow(slug, index) {
            const items = parseBundleItems(slug, index);
            items.push({
                sku: '',
                name: '',
                job_type: 'design_queue',
                provider: 'hivenest_team',
                quantity: 1,
                requires_domain: false
            });
            saveBundleItems(slug, index, items);
            renderBundleEditor(slug, index);
        }

        function bundleJobTypeOptions(selected) {
            const options = [
                ['domain_registration', 'Domain Registration'],
                ['hosting_setup', 'Hosting / Server Setup'],
                ['email_setup', 'Email Setup'],
                ['ssl_setup', 'SSL Setup'],
                ['backup_setup', 'Backup Setup'],
                ['security_setup', 'Security Setup'],
                ['design_queue', 'Design / Build CRM'],
                ['marketing_queue', 'Marketing CRM'],
                ['manual_queue', 'Manual Review']
            ];
            return options.map(([value, label]) => `<option value="${value}" ${selected === value ? 'selected' : ''}>${label}</option>`).join('');
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
        
        // Tab switching
        function showTab(tabName) {
            // Hide all tabs
            document.getElementById('products-tab').style.display = 'none';
            document.getElementById('domains-tab').style.display = 'none';
            document.getElementById('addons-tab').style.display = 'none';
            document.getElementById('currency-tab').style.display = 'none';
            document.getElementById('promotions-tab').style.display = 'none';
            
            // Remove active class from all buttons
            document.getElementById('tab-products').className = 'btn btn-secondary';
            document.getElementById('tab-domains').className = 'btn btn-secondary';
            document.getElementById('tab-addons').className = 'btn btn-secondary';
            document.getElementById('tab-currency').className = 'btn btn-secondary';
            document.getElementById('tab-promotions').className = 'btn btn-secondary';
            
            // Show selected tab
            document.getElementById(tabName + '-tab').style.display = 'block';
            document.getElementById('tab-' + tabName).className = 'btn btn-primary';
        }

        function reorderPackageRows(slug) {
            const rows = Array.from(document.querySelectorAll('.tier-row[data-tier-row="true"][data-slug="' + slug + '"]'));
            if (!rows.length) return;
            const container = rows[0].parentElement;
            rows.sort((a, b) => {
                const sortA = parseInt(a.getAttribute('data-sort-order') || '0', 10);
                const sortB = parseInt(b.getAttribute('data-sort-order') || '0', 10);
                if (sortA !== sortB) return sortA - sortB;
                const indexA = parseInt(a.getAttribute('data-index') || '0', 10);
                const indexB = parseInt(b.getAttribute('data-index') || '0', 10);
                return indexA - indexB;
            });
            rows.forEach(row => container.appendChild(row));
        }

        function collectProductsFromInputs() {
            document.querySelectorAll('input[data-field][data-slug]:not([data-index])').forEach(element => {
                const slug = element.getAttribute('data-slug');
                const field = element.getAttribute('data-field');
                if (!slug || !field || !pricingData[slug]) return;
                pricingData[slug][field] = field === 'sort_order' ? parseInt(element.value || '0', 10) : element.value;
            });

            document.querySelectorAll('input[data-index], select[data-index], textarea[data-index]').forEach(element => {
                const slug = element.getAttribute('data-slug');
                const index = parseInt(element.getAttribute('data-index'), 10);
                const field = element.getAttribute('data-field');
                if (!slug || field === null || Number.isNaN(index) || !pricingData[slug] || !pricingData[slug].pricing_tiers || !pricingData[slug].pricing_tiers[index]) return;

                let value = element.value;
                if (element.type === 'checkbox') value = element.checked ? 1 : 0;
                if (field === 'features') value = value.split('\n').filter(f => f.trim() !== '');
                if (field === 'price' || field === 'setup_fee') value = parseFloat(value || '0');
                if (field === 'sort_order') value = parseInt(value || '0', 10);
                pricingData[slug].pricing_tiers[index][field] = value;
            });
        }
        
        // Track changes for products
        document.querySelectorAll('input[data-field][data-slug]:not([data-index])').forEach(element => {
            element.addEventListener('change', function() {
                const slug = this.getAttribute('data-slug');
                const field = this.getAttribute('data-field');
                
                if (slug && field && pricingData[slug]) {
                    pricingData[slug][field] = field === 'sort_order' ? parseInt(this.value || '0', 10) : this.value;
                    
                    // Visual feedback
                    this.style.borderColor = '#4CAF50';
                    this.style.boxShadow = '0 0 10px rgba(76, 175, 80, 0.5)';
                    setTimeout(() => {
                        this.style.borderColor = '';
                        this.style.boxShadow = '';
                    }, 1000);
                }
            });
        });
        
        // Track changes for pricing tiers
        document.querySelectorAll('input[data-index], select[data-index], textarea[data-index]').forEach(element => {
            const tierChangeHandler = function() {
                const slug = this.getAttribute('data-slug');
                const index = parseInt(this.getAttribute('data-index'));
                const field = this.getAttribute('data-field');
                
                if (slug && field !== null && index !== null) {
                    let value = this.value;
                    
                    // Handle checkbox
                    if (this.type === 'checkbox') {
                        value = this.checked ? 1 : 0;
                    }
                    
                    // Handle features textarea
                    if (field === 'features') {
                        value = value.split('\n').filter(f => f.trim() !== '');
                    }
                    
                    // Handle numeric fields
                    if (field === 'price' || field === 'setup_fee') {
                        value = parseFloat(value);
                    }
                    if (field === 'sort_order') {
                        value = parseInt(value || '0', 10);
                    }
                    
                    // Update the data
                    pricingData[slug]['pricing_tiers'][index][field] = value;
                    if (field === 'sort_order') {
                        const tierRow = this.closest('.tier-row[data-tier-row="true"]');
                        if (tierRow) tierRow.setAttribute('data-sort-order', String(value));
                        reorderPackageRows(slug);
                    }
                    
                    // Visual feedback
                    this.style.borderColor = '#4CAF50';
                    setTimeout(() => {
                        this.style.borderColor = '';
                    }, 1000);
                }
            };
            element.addEventListener('change', tierChangeHandler);
            if (element.getAttribute('data-field') === 'sort_order') {
                element.addEventListener('input', tierChangeHandler);
            }
        });

        document.querySelectorAll('textarea[data-bundle-json="true"]').forEach(textarea => {
            const slug = textarea.getAttribute('data-slug');
            const index = parseInt(textarea.getAttribute('data-index'), 10);
            if (slug && !Number.isNaN(index)) {
                renderBundleEditor(slug, index);
                textarea.addEventListener('change', () => {
                    if (pricingData[slug] && pricingData[slug].pricing_tiers[index]) {
                        pricingData[slug].pricing_tiers[index].bundle_items = textarea.value;
                    }
                    renderBundleEditor(slug, index);
                });
            }
        });
        
        // Track changes for domain extensions
        document.querySelectorAll('input[data-domain-index]').forEach(element => {
            element.addEventListener('change', function() {
                const index = parseInt(this.getAttribute('data-domain-index'));
                const field = this.getAttribute('data-domain-field');
                
                if (index !== null && field) {
                    let value = this.value;
                    
                    // Handle checkbox
                    if (this.type === 'checkbox') {
                        value = this.checked ? 1 : 0;
                    }
                    
                    // Handle numeric fields
                    if (field.includes('price')) {
                        value = parseFloat(value);
                    }
                    
                    // Update the data
                    domainData[index][field] = value;
                    
                    // Visual feedback
                    this.style.borderColor = '#4CAF50';
                    setTimeout(() => {
                        this.style.borderColor = '';
                    }, 1000);
                }
            });
        });
        
        // Track changes for addons
        document.querySelectorAll('input[data-addon-index], select[data-addon-index], textarea[data-addon-index]').forEach(element => {
            element.addEventListener('change', function() {
                const index = parseInt(this.getAttribute('data-addon-index'));
                const field = this.getAttribute('data-addon-field');
                
                if (index !== null && field) {
                    let value = this.value;
                    
                    // Handle numeric fields
                    if (field === 'price') {
                        value = parseFloat(value);
                    }
                    
                    // Update the data
                    addonData[index][field] = value;
                    
                    // Visual feedback
                    this.style.borderColor = '#4CAF50';
                    setTimeout(() => {
                        this.style.borderColor = '';
                    }, 1000);
                }
            });
        });
        
        function saveToCache() {
            collectProductsFromInputs();
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="pricing_data" value='${JSON.stringify(pricingData)}'>
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function syncToDatabase() {
            collectProductsFromInputs();
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="sync_to_db">
                <input type="hidden" name="pricing_data" value='${JSON.stringify(pricingData)}'>
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function saveDomainPricing() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="save_domains">
                <input type="hidden" name="domain_data" value='${JSON.stringify(domainData)}'>
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function saveAddonPricing() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="save_addons">
                <input type="hidden" name="addon_data" value='${JSON.stringify(addonData)}'>
            `;
            document.body.appendChild(form);
            form.submit();
        }

        showTab(<?php echo json_encode($active_tab); ?>);
    </script>
</body>
</html>
