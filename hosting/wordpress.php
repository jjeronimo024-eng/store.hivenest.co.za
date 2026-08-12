<?php
// Include required utilities
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/dynamic_pricing.php';

// Page variables
$current_page = 'hosting';
$page_title = 'WordPress Hosting - Quantum Optimized | HiveNest Matrix';
$page_description = 'WordPress Hosting - Optimized quantum hosting for WordPress websites with auto-updates, neural caching, and quantum-level security protocols.';
$page_keywords = 'wordpress hosting, managed wordpress, wordpress optimization, cyberpunk wordpress hosting';

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Quantum Servers', 'url' => '../main-services/hosting.php'],
    ['text' => 'WordPress Hosting', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = "
// Queue for cart actions before system is ready
window.cartActionQueue = window.cartActionQueue || [];
window.cartRetryTimer = window.cartRetryTimer || null;

function getWordPressHostingSelectedTerm() {
    const termInput = document.querySelector('input[name=\"wordpress_hosting_term_months\"]:checked');
    const months = termInput ? parseInt(termInput.value, 10) : 1;
    const validMonths = [1, 6, 12, 24];
    const safeMonths = validMonths.includes(months) ? months : 1;
    const labels = {
        1: '1 Month',
        6: '6 Months',
        12: '12 Months',
        24: '24 Months'
    };
    const billingCycles = {
        1: 'monthly',
        6: 'semi_annually',
        12: 'annually',
        24: 'biennially'
    };

    return {
        months: safeMonths,
        label: labels[safeMonths],
        billingCycle: billingCycles[safeMonths]
    };
}

function addWordPressHostingToCart(planSlug, planName, price) {
    // Database-generated buttons pass (slug, price); fallback cards pass
    // (slug, display name, price). Support both without changing the cart ID.
    if (price === undefined) {
        price = planName;
        planName = String(planSlug).replace(/-/g, ' ').toUpperCase();
    }

    const domainInput = document.getElementById('wordpress-primary-domain');
    const domainOptionInput = document.querySelector('input[name=\"wordpress_domain_option\"]:checked');
    const primaryDomain = domainInput ? domainInput.value.trim().toLowerCase() : '';
    const domainOption = domainOptionInput ? domainOptionInput.value : 'existing';
    const selectedTerm = getWordPressHostingSelectedTerm();
    const unitPrice = Number(price);
    const totalPrice = unitPrice * selectedTerm.months;

    if (!primaryDomain) {
        console.warn('Please enter the primary domain for this WordPress hosting package before adding it to cart.');
        if (domainInput) domainInput.focus();
        return false;
    }

    if (!/^[a-z0-9][a-z0-9-]*(\.[a-z0-9][a-z0-9-]*)+$/i.test(primaryDomain)) {
        console.warn('Please enter a valid domain name, for example yourdomain.com.');
        if (domainInput) domainInput.focus();
        return false;
    }

    const item = {
        id: 'wordpress-hosting--' + planSlug,
        name: 'WordPress Hosting: ' + planName + ' (' + selectedTerm.label + ')',
        description: 'WordPress Hosting: ' + planName + ' for ' + primaryDomain + ' - ' + selectedTerm.label,
        price: totalPrice,
        type: 'hosting',
        category: 'wordpress-hosting',
        billing_cycle: selectedTerm.billingCycle,
        term_months: selectedTerm.months,
        monthly_price: unitPrice,
        domain: primaryDomain,
        domain_name: primaryDomain,
        primary_domain: primaryDomain,
        domain_option: domainOption,
        product_config: {
            sku: 'wordpress-hosting--' + planSlug,
            domain: primaryDomain,
            primary_domain: primaryDomain,
            domain_option: domainOption,
            term_months: selectedTerm.months,
            billing_cycle: selectedTerm.billingCycle,
            monthly_price: unitPrice
        }
    };

    if (window.addToCart) {
        return window.addToCart(item);
    }

    // Queue each product only once while the shared cart script loads.
    if (!window.cartActionQueue.some(entry => entry.id === item.id)) {
        window.cartActionQueue.push(item);
    }

    if (!window.cartRetryTimer) {
        let retryCount = 0;
        window.cartRetryTimer = setInterval(function() {
            retryCount++;
            if (window.addToCart) {
                clearInterval(window.cartRetryTimer);
                window.cartRetryTimer = null;
                const queuedItems = window.cartActionQueue.splice(0);
                queuedItems.forEach(queuedItem => window.addToCart(queuedItem));
            } else if (retryCount >= 50) {
                clearInterval(window.cartRetryTimer);
                window.cartRetryTimer = null;
                window.cartActionQueue.length = 0;
                console.warn('Cart system is taking longer than expected. Please refresh the page and try again.');
            }
        }, 100);
    }

    return true;
}
";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php 
include '../utilities/head.php'; 
include_once '../utilities/seo-meta.php';

// SEO configuration (after including seo-meta.php)
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-branding-workspace.jpg',
    'url' => 'https://hivenest.co.za/hosting/wordpress.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'WordPress Hosting',
        'description' => 'Optimized WordPress hosting with quantum-level performance and security',
        'serviceType' => 'WordPress Hosting Services'
    ])
];
echo renderSEOMeta($seo_config);
?>
</head>
<body>
<?php include '../utilities/nav.php'; ?>
<?php include '../utilities/mobile-menu.php'; ?>

<?php
// Hero Section
$hero_title = '<span class="cyber-text">WORDPRESS</span><br>QUANTUM REALM';
$hero_subtitle = 'Optimized WordPress hosting with auto-updates, neural caching, and quantum-level security protocols. Perfect for content creators and digital architects.';
$hero_image = '../assets/images/heroes/hero-branding-ipad-night.jpg';
$hero_alt = 'WordPress Quantum Hosting';
include '../utilities/hero-minimal.php';
?>

<?php
// WordPress Hosting Plans
include '../utilities/pricing-cards.php';
?>

<section class="section" style="background: rgba(0, 0, 0, 0.88); border-top: 1px solid rgba(0, 255, 255, 0.18); border-bottom: 1px solid rgba(0, 255, 255, 0.18);">
    <div class="container">
        <div class="cyber-card" style="max-width: 900px; margin: 0 auto; padding: 2rem; border-color: var(--primary-cyan); box-shadow: 0 0 30px rgba(0, 255, 255, 0.16);">
            <h2 class="cyber-text" style="text-align:center; margin-bottom: 0.75rem;">PRIMARY DOMAIN</h2>
            <p style="text-align:center; color: rgba(255,255,255,0.78); margin-bottom: 1.5rem;">
                WordPress Hosting needs a primary domain before it can be provisioned through MyOrderBox.
            </p>
            <label for="wordpress-primary-domain" style="display:block; color: var(--primary-cyan); font-weight: 700; margin-bottom: 0.5rem;">Domain for this WordPress hosting</label>
            <input
                type="text"
                id="wordpress-primary-domain"
                placeholder="yourdomain.com"
                autocomplete="off"
                style="width:100%; padding: 16px 18px; border:1px solid var(--primary-cyan); border-radius:8px; background:rgba(0,0,0,0.72); color:#fff; font-size:1rem; margin-bottom: 1rem;"
            >
            <div style="display:flex; flex-wrap:wrap; gap: 1rem; color: rgba(255,255,255,0.84);">
                <label style="display:flex; align-items:center; gap:0.45rem;"><input type="radio" name="wordpress_domain_option" value="existing" checked> I already own this domain</label>
                <label style="display:flex; align-items:center; gap:0.45rem;"><input type="radio" name="wordpress_domain_option" value="register_new"> I also want to register this domain</label>
                <label style="display:flex; align-items:center; gap:0.45rem;"><input type="radio" name="wordpress_domain_option" value="dns_later"> I will point DNS later</label>
            </div>
            <div style="margin-top: 1.5rem;">
                <h3 style="color: var(--primary-cyan); font-size: 1rem; margin-bottom: 0.85rem;">Hosting term</h3>
                <p style="color: rgba(255,255,255,0.72); margin-bottom: 1rem;">
                    Select the MyOrderBox provisioning term for this WordPress hosting package.
                </p>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.85rem;">
                    <label class="cyber-card" style="padding: 0.9rem; cursor:pointer; border-color: rgba(0,255,255,0.35);">
                        <input type="radio" name="wordpress_hosting_term_months" value="1" checked style="margin-right:0.4rem;"> 1 Month
                    </label>
                    <label class="cyber-card" style="padding: 0.9rem; cursor:pointer; border-color: rgba(0,255,255,0.35);">
                        <input type="radio" name="wordpress_hosting_term_months" value="6" style="margin-right:0.4rem;"> 6 Months
                    </label>
                    <label class="cyber-card" style="padding: 0.9rem; cursor:pointer; border-color: rgba(0,255,255,0.35);">
                        <input type="radio" name="wordpress_hosting_term_months" value="12" style="margin-right:0.4rem;"> 12 Months
                    </label>
                    <label class="cyber-card" style="padding: 0.9rem; cursor:pointer; border-color: rgba(0,255,255,0.35);">
                        <input type="radio" name="wordpress_hosting_term_months" value="24" style="margin-right:0.4rem;"> 24 Months
                    </label>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$wordpress_plans = [
    [
        'name' => 'WP STARTER',
        'price' => '$7',
        'period' => '/mo',
        'features' => [
            '1 WordPress Site',
            '15GB SSD Storage',
            '100GB Bandwidth',
            'Auto WordPress Updates',
            'Neural Caching',
            'Free SSL Certificate',
            'Daily Backups',
            'Malware Protection'
        ],
        'cta_link' => '#',
        'cta_text' => 'ADD TO CART',
        'onclick' => 'addWordPressHostingToCart(\'wp-starter\', \'WP STARTER\', 7)'
    ],
    [
        'name' => 'WP PROFESSIONAL',
        'price' => '$18',
        'period' => '/mo',
        'features' => [
            '5 WordPress Sites',
            '50GB SSD Storage',
            'Unlimited Bandwidth',
            'Auto WordPress Updates',
            'Advanced Neural Caching',
            'Free SSL Certificate',
            'Daily Backups',
            'Premium Malware Protection',
            'CDN Integration',
            'WordPress Staging'
        ],
        'cta_link' => '#',
        'cta_text' => 'ADD TO CART',
        'onclick' => 'addWordPressHostingToCart(\'wp-professional\', \'WP PROFESSIONAL\', 18)',
        'featured' => true
    ],
    [
        'name' => 'WP ENTERPRISE',
        'price' => '$35',
        'period' => '/mo',
        'features' => [
            '15 WordPress Sites',
            '150GB SSD Storage',
            'Unlimited Bandwidth',
            'Auto WordPress Updates',
            'Quantum Neural Caching',
            'Wildcard SSL Certificate',
            'Real-time Backups',
            'Enterprise Security Suite',
            'Global CDN',
            'Multi-site Management',
            'Priority Support'
        ],
        'cta_link' => '#',
        'cta_text' => 'ADD TO CART',
        'onclick' => 'addWordPressHostingToCart(\'wp-enterprise\', \'WP ENTERPRISE\', 35)'
    ]
];

$grid_title = 'WORDPRESS POWER LEVELS';
$grid_subtitle = 'Optimized hosting for WordPress digital realms';
// Pull from DB (getProductPricingById) with hardcoded array above as safety fallback
$wordpress_plans = loadProductPricingPlans([
    'product_id'     => 28,
    'product_slug'   => 'wordpress-hosting',
    'cart_function'  => 'addWordPressHostingToCart',
    'fallback_plans' => $wordpress_plans,
]);
$grid_content = renderPricingGrid($wordpress_plans);
include '../utilities/grid-section.php';
?>

<?php
// WordPress Features
include '../utilities/cyber-cards.php';
$wordpress_features = [
    [
        'icon' => 'fab fa-wordpress',
        'title' => 'AUTO WORDPRESS UPDATES',
        'description' => 'Automatic core, plugin, and theme updates with neural rollback protection in case of compatibility issues.'
    ],
    [
        'icon' => 'fas fa-rocket',
        'title' => 'NEURAL CACHING',
        'description' => 'Advanced caching algorithms that adapt to your site\'s traffic patterns for optimal performance across all dimensions.'
    ],
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'MALWARE PROTECTION',
        'description' => 'Real-time malware scanning and removal with quantum-level threat detection and automated security patches.'
    ],
    [
        'icon' => 'fas fa-sync-alt',
        'title' => 'AUTOMATED BACKUPS',
        'description' => 'Daily automated backups with one-click restoration capabilities stored across multiple secure dimensions.'
    ],
    [
        'icon' => 'fas fa-globe',
        'title' => 'CDN INTEGRATION',
        'description' => 'Global content delivery network for faster loading times across all continents and parallel universes.'
    ],
    [
        'icon' => 'fas fa-code',
        'title' => 'STAGING ENVIRONMENT',
        'description' => 'Test changes safely in a staging environment before deploying to your live WordPress site.'
    ]
];

$grid_title = 'WORDPRESS NEURAL FEATURES';
$grid_subtitle = 'Quantum-optimized features for WordPress excellence';
$grid_content = renderCyberCardsGrid($wordpress_features);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<?php
// CTA Section
$cta_title = 'READY TO ENTER THE WORDPRESS QUANTUM REALM?';
$cta_subtitle = 'Launch your WordPress site with our quantum-optimized hosting platform and experience unparalleled performance.';
$cta_buttons = '<a href="../contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">START HOSTING</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
