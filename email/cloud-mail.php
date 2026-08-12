<?php
// Include required utilities first
include '../utilities/seo-meta.php';
include_once '../utilities/dynamic_pricing.php';

// Page variables
$current_page = 'email';
$page_title = 'Cloud Mail - Scalable Email Solutions | HiveNest Matrix';
$page_description = 'Cloud Mail hosting with auto-scaling, global delivery, advanced spam protection, and unlimited storage for modern businesses.';
$page_keywords = 'cloud email, scalable email hosting, cloud mail solutions, cyberpunk cloud email, business email hosting';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-email-global.jpg',
    'url' => 'https://hivenest.co.za/email/cloud-mail.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Cloud Mail Hosting',
        'description' => 'Scalable cloud email hosting with auto-scaling and global delivery',
        'serviceType' => 'Cloud Email Services'
    ])
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Comm Arrays', 'url' => '../main-services/email.php'],
    ['text' => 'Cloud Mail', 'url' => null]
];

// Get pricing: cache → DB (getProductPricingById) → DB by slug → empty
$cloudmail_plans = loadProductPricingPlans([
    'product_id'    => 14,
    'product_slug'  => 'cloud-mail',
    'cart_function' => 'addToCart',
]);

// Page-specific JavaScript
$page_scripts = "
function addToCart(planId, price) {
    const planNames = {
        'cloud-starter': 'Cloud Mail - Starter',
        'cloud-professional': 'Cloud Mail - Professional',
        'cloud-enterprise': 'Cloud Mail - Enterprise'
    };
    const fallbackName = planId.split('--').pop().replace(/-/g, ' ').toUpperCase();
    
    if (window.shoppingCart) {
        window.shoppingCart.addItem({
            id: planId,
            name: planNames[planId] || fallbackName,
            price: price,
            type: 'email'
        });
    } else {
        console.error('Shopping cart not initialized');
        console.warn('Cart system not ready. Please refresh the page and try again.');
    }
}
";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include '../utilities/head.php'; ?>
<?php echo renderSEOMeta($seo_config); ?>
</head>
<body>
<?php include '../utilities/nav.php'; ?>
<?php include '../utilities/mobile-menu.php'; ?>

<?php
// Hero Section
$hero_title = '<span class="cyber-text">CLOUD</span><br>MAIL MATRIX';
$hero_subtitle = 'Scalable cloud email hosting with auto-scaling, global delivery, advanced spam protection, and unlimited storage for modern businesses.';
$hero_image = '../assets/images/heroes/hero-email-network.jpg';
$hero_alt = 'Cloud Email Infrastructure';
include '../utilities/hero-minimal.php';
?>

<?php
// Breadcrumbs
include '../utilities/breadcrumbs.php';
?>

<?php
// Cloud Mail Plans - Retrieved from database above
include '../utilities/pricing-cards.php';

$grid_title = 'CLOUD MAIL POWER LEVELS';
$grid_subtitle = 'Scalable cloud email solutions for every business size';
$grid_content = renderPricingGrid($cloudmail_plans);
include '../utilities/grid-section.php';
?>

<?php
// Cloud Mail Features
include '../utilities/cyber-cards.php';
$cloudmail_features = [
    [
        'icon' => 'fas fa-cloud',
        'title' => 'AUTO-SCALING INFRASTRUCTURE',
        'description' => 'Automatically scales resources based on demand with quantum-level precision and real-time load balancing.'
    ],
    [
        'icon' => 'fas fa-globe',
        'title' => 'GLOBAL DELIVERY NETWORK',
        'description' => 'Email delivery optimized across multiple continents with low-latency routing and regional data centers.'
    ],
    [
        'icon' => 'fas fa-shield-virus',
        'title' => 'ADVANCED THREAT PROTECTION',
        'description' => 'AI-powered spam detection, malware scanning, and advanced threat protection against zero-day attacks.'
    ],
    [
        'icon' => 'fas fa-sync-alt',
        'title' => 'REAL-TIME SYNCHRONIZATION',
        'description' => 'Instant synchronization across all devices with real-time push notifications and offline access.'
    ],
    [
        'icon' => 'fas fa-database',
        'title' => 'UNLIMITED STORAGE',
        'description' => 'Unlimited email storage with high-performance SSD arrays and automated backup across multiple zones.'
    ],
    [
        'icon' => 'fas fa-mobile-alt',
        'title' => 'MOBILE OPTIMIZATION',
        'description' => 'Native mobile apps for iOS and Android with offline access and push notification support.'
    ]
];

$grid_title = 'CLOUD MAIL NEURAL FEATURES';
$grid_subtitle = 'Advanced cloud-native features for modern email communication';
$grid_content = renderCyberCardsGrid($cloudmail_features);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<!-- Cloud Infrastructure Section -->
<section class="section">
    <div class="container">
        <div class="text-center mb-8">
            <h2>CLOUD INFRASTRUCTURE</h2>
            <p class="hero-subtitle">Built on enterprise-grade cloud infrastructure</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div class="service-card">
                <i class="fas fa-server service-icon"></i>
                <h3 class="service-title">SCALABLE ARCHITECTURE</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Auto-scaling servers</li>
                    <li style="margin: 0.5rem 0;">◉ Load balancing</li>
                    <li style="margin: 0.5rem 0;">◉ Redundant infrastructure</li>
                    <li style="margin: 0.5rem 0;">◉ High availability design</li>
                </ul>
            </div>

            <div class="service-card">
                <i class="fas fa-lock service-icon"></i>
                <h3 class="service-title">SECURITY & COMPLIANCE</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ End-to-end encryption</li>
                    <li style="margin: 0.5rem 0;">◉ SOC 2 Type II certified</li>
                    <li style="margin: 0.5rem 0;">◉ GDPR compliant</li>
                    <li style="margin: 0.5rem 0;">◉ Regular security audits</li>
                </ul>
            </div>

            <div class="service-card">
                <i class="fas fa-chart-line service-icon"></i>
                <h3 class="service-title">PERFORMANCE MONITORING</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Real-time monitoring</li>
                    <li style="margin: 0.5rem 0;">◉ Performance analytics</li>
                    <li style="margin: 0.5rem 0;">◉ Proactive alerts</li>
                    <li style="margin: 0.5rem 0;">◉ 24/7 network monitoring</li>
                </ul>
            </div>

            <div class="service-card">
                <i class="fas fa-backup service-icon"></i>
                <h3 class="service-title">BACKUP & RECOVERY</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Automated daily backups</li>
                    <li style="margin: 0.5rem 0;">◉ Point-in-time recovery</li>
                    <li style="margin: 0.5rem 0;">◉ Geographic redundancy</li>
                    <li style="margin: 0.5rem 0;">◉ Instant restore capability</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- Integration & API Section -->
<section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
    <div class="container">
        <div class="text-center mb-8">
            <h2>INTEGRATIONS & API</h2>
            <p class="hero-subtitle">Seamlessly integrate with your existing tools and workflows</p>
        </div>
        
        <div class="cyber-card" style="max-width: 800px; margin: 0 auto;">
            <div style="text-align: center; margin-bottom: 2rem;">
                <i class="fas fa-code-branch" style="font-size: 3rem; color: var(--cyber-neon-cyan); margin-bottom: 1rem;"></i>
                <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">POWERFUL API & INTEGRATIONS</h3>
                <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 2rem;">
                    RESTful API and extensive integrations with popular business tools, 
                    CRM systems, and productivity applications.
                </p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                <div style="text-align: center;">
                    <i class="fas fa-plug" style="font-size: 2rem; color: var(--cyber-neon-green); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-green); margin-bottom: 0.5rem;">REST API</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Full-featured API for custom integrations</p>
                </div>
                
                <div style="text-align: center;">
                    <i class="fas fa-tools" style="font-size: 2rem; color: var(--cyber-neon-green); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-green); margin-bottom: 0.5rem;">CRM INTEGRATION</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Salesforce, HubSpot, and more</p>
                </div>
                
                <div style="text-align: center;">
                    <i class="fas fa-sync" style="font-size: 2rem; color: var(--cyber-neon-green); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-green); margin-bottom: 0.5rem;">WORKFLOW AUTOMATION</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Zapier, Microsoft Flow, and webhooks</p>
                </div>
            </div>
            
            <div style="text-align: center;">
                <a href="../contact.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                    EXPLORE INTEGRATIONS
                </a>
            </div>
        </div>
    </div>
</section>

<?php
// CTA Section
$cta_title = 'READY TO SCALE YOUR EMAIL TO THE CLOUD?';
$cta_subtitle = 'Experience the power of cloud-native email hosting with unlimited scalability and enterprise-grade reliability.';
$cta_buttons = '<a href="../contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">START CLOUD MAIL</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
