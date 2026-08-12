<?php
// Include required utilities first
include '../utilities/seo-meta.php';
include_once '../utilities/dynamic_pricing.php';

// Page variables
$current_page = 'tools';
$page_title = 'SiteLock Website Security - Complete Protection | HiveNest Matrix';
$page_description = 'SiteLock Website Security - Comprehensive website security with malware removal, firewall, and vulnerability scanning from HiveNest.';
$page_keywords = 'sitelock security, website protection, malware removal, web firewall, vulnerability scanning';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-security-circuit.jpg',
    'url' => 'https://hivenest.co.za/tools/sitelock.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'SiteLock Website Security',
        'description' => 'Comprehensive website security with malware removal and firewall protection',
        'serviceType' => 'Web Security Services'
    ])
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Digital Arsenal', 'url' => '../main-services/tools.php'],
    ['text' => 'SiteLock Security', 'url' => null]
];

$sitelock_fallback = [
    ['name'=>'BASIC','price'=>'$0.90','period'=>'/mo','features'=>['Daily malware scanning','Security badge','Vulnerability checks','Email alerts'],'cta_link'=>'#','cta_text'=>'ADD TO CART','onclick'=>"addSiteLockToCart('basic', 0.90)",'featured'=>false],
    ['name'=>'PROFESSIONAL','price'=>'$2.58','period'=>'/mo','features'=>['Daily malware scanning','Web application firewall','Automatic malware removal','Priority support'],'cta_link'=>'#','cta_text'=>'ADD TO CART','onclick'=>"addSiteLockToCart('professional', 2.58)",'featured'=>true],
    ['name'=>'PREMIUM','price'=>'$3.34','period'=>'/mo','features'=>['Advanced website security','Automatic malware removal','DDoS protection','CDN acceleration'],'cta_link'=>'#','cta_text'=>'ADD TO CART','onclick'=>"addSiteLockToCart('premium', 3.34)",'featured'=>false],
    ['name'=>'ENTERPRISE','price'=>'$11.40','period'=>'/mo','features'=>['Real-time scanning','Enterprise firewall','Database scanning','PCI compliance scanning'],'cta_link'=>'#','cta_text'=>'ADD TO CART','onclick'=>"addSiteLockToCart('enterprise', 11.40)",'featured'=>false],
];
$sitelock_plans = loadProductPricingPlans([
    'product_id'=>19,
    'product_slug'=>'sitelock-security',
    'cart_function'=>'addSiteLockToCart',
    'fallback_plans'=>$sitelock_fallback,
]);

// Page-specific JavaScript
$page_scripts = <<<'JAVASCRIPT'
function addSiteLockToCart(planId, planName, price) {
    if (price === undefined) {
        price = planName;
        planName = String(planId).replace(/-/g, ' ').toUpperCase();
    }
    if (window.addToCart) {
        window.addToCart({
            id: 'sitelock-' + planId,
            name: 'SiteLock Security: ' + planName,
            price: price,
            type: 'security'
        });
    } else {
        console.error('Cart system not loaded');
        console.warn('Cart system not ready. Please refresh the page and try again.');
    }
}
JAVASCRIPT;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include '../utilities/head.php'; ?>
<?php 
echo renderSEOMeta($seo_config);
?>
</head>
<body>
<?php include '../utilities/nav.php'; ?>
<?php include '../utilities/mobile-menu.php'; ?>

<?php
// Hero Section
$hero_title = '<span class="cyber-text">SITELOCK</span><br>WEBSITE SECURITY';
$hero_subtitle = 'Comprehensive website security with malware removal, firewall, and vulnerability scanning. Protect your website from cyber threats.';
$hero_image = '../assets/images/heroes/hero-security-circuit.jpg';
$hero_alt = 'SiteLock Website Security';
include '../utilities/hero-minimal.php';
?>

<?php
// Breadcrumbs
?>

<?php
// SiteLock Plans Section - Retrieved from database above
include '../utilities/pricing-cards.php';

$grid_title = 'SITELOCK SECURITY PLANS';
$grid_subtitle = 'Choose the right level of protection for your website';
$grid_content = renderPricingGrid($sitelock_plans);
include '../utilities/grid-section.php';
?>

<?php
// Security Features
include '../utilities/cyber-cards.php';
$security_features = [
    [
        'icon' => 'fas fa-bug',
        'title' => 'MALWARE SCANNING',
        'description' => 'Daily automated malware scans detect and identify threats before they impact your website or visitors.'
    ],
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'WEB FIREWALL',
        'description' => 'Advanced web application firewall blocks malicious traffic and prevents attacks before they reach your server.'
    ],
    [
        'icon' => 'fas fa-search',
        'title' => 'VULNERABILITY SCAN',
        'description' => 'Comprehensive vulnerability assessments identify security weaknesses and provide remediation guidance.'
    ],
    [
        'icon' => 'fas fa-magic',
        'title' => 'AUTO MALWARE REMOVAL',
        'description' => 'Automatic malware removal cleans infected files and restores your website to a safe state instantly.'
    ],
    [
        'icon' => 'fas fa-eye',
        'title' => 'CONTINUOUS MONITORING',
        'description' => '24/7 security monitoring watches for threats and suspicious activity across your entire website.'
    ],
    [
        'icon' => 'fas fa-certificate',
        'title' => 'TRUST SEAL',
        'description' => 'Display the SiteLock trust seal to build customer confidence and improve conversion rates.'
    ]
];

$grid_title = 'SECURITY FEATURES';
$grid_subtitle = 'Advanced protection against cyber threats';
$grid_content = renderCyberCardsGrid($security_features);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<?php
// How SiteLock Works (Using numbered steps)
$sitelock_process = [
    [
        'icon' => 'fas fa-search',
        'title' => 'SCAN',
        'description' => 'SiteLock scans your website daily for malware, vulnerabilities, and security threats using advanced detection algorithms.'
    ],
    [
        'icon' => 'fas fa-exclamation-triangle',
        'title' => 'ALERT',
        'description' => 'Instant notifications are sent when threats are detected, providing detailed information about the security issue.'
    ],
    [
        'icon' => 'fas fa-broom',
        'title' => 'CLEAN',
        'description' => 'Automatic malware removal cleans infected files and removes malicious code from your website safely.'
    ],
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'PROTECT',
        'description' => 'Continuous protection with firewall and monitoring prevents future attacks and vulnerabilities.'
    ]
];

$grid_title = 'HOW SITELOCK WORKS';
$grid_subtitle = 'Complete website security in 4 simple steps';
$grid_content = renderCyberCardsGrid($sitelock_process);
include '../utilities/grid-section.php';
?>

<?php
// Trust Seal Benefits (Two Column Layout)
$column1_content = '
    <div style="text-align: center; margin-bottom: 2rem;">
        <i class="fas fa-certificate" style="font-size: 4rem; color: var(--cyber-neon-cyan); margin-bottom: 1rem;"></i>
        <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">SITELOCK TRUST SEAL</h3>
        <p style="color: rgba(255, 255, 255, 0.8);">
            Display the SiteLock trust seal on your website to show visitors 
            that your site is secure and regularly monitored.
        </p>
    </div>
';

$column2_content = '
    <div>
        <h4 style="color: var(--cyber-neon-green); margin-bottom: 1rem;">TRUST SEAL BENEFITS</h4>
        <ul style="list-style: none; padding: 0; text-align: left;">
            <li style="margin: 1rem 0; color: rgba(255, 255, 255, 0.8); display: flex; align-items: center;">
                <i class="fas fa-check" style="color: var(--cyber-neon-green); margin-right: 0.5rem;"></i>
                Increases customer confidence
            </li>
            <li style="margin: 1rem 0; color: rgba(255, 255, 255, 0.8); display: flex; align-items: center;">
                <i class="fas fa-check" style="color: var(--cyber-neon-green); margin-right: 0.5rem;"></i>
                Improves conversion rates
            </li>
            <li style="margin: 1rem 0; color: rgba(255, 255, 255, 0.8); display: flex; align-items: center;">
                <i class="fas fa-check" style="color: var(--cyber-neon-green); margin-right: 0.5rem;"></i>
                Reduces cart abandonment
            </li>
            <li style="margin: 1rem 0; color: rgba(255, 255, 255, 0.8); display: flex; align-items: center;">
                <i class="fas fa-check" style="color: var(--cyber-neon-green); margin-right: 0.5rem;"></i>
                Enhances brand reputation
            </li>
            <li style="margin: 1rem 0; color: rgba(255, 255, 255, 0.8); display: flex; align-items: center;">
                <i class="fas fa-check" style="color: var(--cyber-neon-green); margin-right: 0.5rem;"></i>
                Provides SEO benefits
            </li>
        </ul>
    </div>
';

$section_title = 'SITELOCK TRUST SEAL';
$section_subtitle = 'Build customer trust and increase conversions';

$grid_title = $section_title;
$grid_subtitle = $section_subtitle;
$grid_content = '
    <div class="cyber-card" style="max-width: 800px; margin: 0 auto;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; align-items: center;">
            ' . $column1_content . $column2_content . '
        </div>
    </div>
';
include '../utilities/grid-section.php';
?>

<?php
// CTA Section
$cta_title = 'READY TO SECURE YOUR WEBSITE?';
$cta_subtitle = 'Get comprehensive website security and build customer trust with SiteLock protection.';
$cta_buttons = '<a href="../contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">START PROTECTION</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
