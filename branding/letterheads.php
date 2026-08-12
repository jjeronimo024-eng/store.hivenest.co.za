<?php
// Include required utilities FIRST
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/dynamic_pricing.php';

// Page variables
$current_page = 'branding';
$page_title = 'Letterheads - Neural Graphics | HiveNest Matrix';
$page_description = 'Professional letterhead designs with cyberpunk aesthetics. Custom neural letterheads that establish authority and enhance business communications.';
$page_keywords = 'letterhead design, professional letterheads, cyberpunk letterheads, neural graphics, business stationery';

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Neural Graphics', 'url' => '/main-services/branding.php'],
    ['text' => 'Letterheads', 'url' => null]
];

// Get pricing: cache → DB (getProductPricingById) → DB by slug → empty
$letterhead_packages = loadProductPricingPlans([
    'product_id'    => 12,
    'product_slug'  => 'letterheads',
    'cart_function' => 'addToCart',
]);

// Page-specific JavaScript
$page_scripts = "
function addToCart(planId, price) {
    const planNames = {
        'letterhead-basic': 'Letterhead Design - Basic Package',
        'letterhead-professional': 'Letterhead Design - Professional Package',
        'letterhead-enterprise': 'Letterhead Design - Enterprise Suite'
    };
    const fallbackName = planId.split('--').pop().replace(/-/g, ' ').toUpperCase();
    
    if (window.shoppingCart) {
        window.shoppingCart.addItem({
            id: planId,
            name: planNames[planId] || fallbackName,
            price: price,
            type: 'branding'
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
</head>
<body>
<?php include '../utilities/nav.php'; ?>
<?php include '../utilities/mobile-menu.php'; ?>

<?php
// Hero Section
$hero_title = '<span class="cyber-text">LETTERHEAD</span><br>NEURAL DESIGN';
$hero_subtitle = 'Professional letterhead designs with cyberpunk aesthetics. Custom neural letterheads that establish authority and enhance business communications.';
$hero_image = '../assets/images/heroes/hero-branding-layout.jpg';
$hero_alt = 'Professional Letterhead Design';
include '../utilities/hero-minimal.php';
?>

<?php
// Letterhead Packages - Retrieved from database above
include '../utilities/pricing-cards.php';

$grid_title = 'LETTERHEAD POWER LEVELS';
$grid_subtitle = 'Professional letterhead packages for every business need';
$grid_content = renderPricingGrid($letterhead_packages);
include '../utilities/grid-section.php';
?>

<?php
// Letterhead Features
include '../utilities/cyber-cards.php';
$letterhead_features = [
    [
        'icon' => 'fas fa-print',
        'title' => 'PRINT OPTIMIZATION',
        'description' => 'High-resolution designs optimized for professional printing with CMYK color profiles and bleed specifications.'
    ],
    [
        'icon' => 'fas fa-palette',
        'title' => 'BRAND INTEGRATION',
        'description' => 'Seamless integration with your existing brand identity, colors, fonts, and visual elements.'
    ],
    [
        'icon' => 'fas fa-file-pdf',
        'title' => 'MULTIPLE FORMATS',
        'description' => 'Delivered in multiple formats including PDF, AI, EPS, and editable templates for easy customization.'
    ],
    [
        'icon' => 'fas fa-crown',
        'title' => 'PREMIUM AESTHETICS',
        'description' => 'Sophisticated cyberpunk design elements that convey professionalism and technological advancement.'
    ],
    [
        'icon' => 'fas fa-ruler-combined',
        'title' => 'STANDARD DIMENSIONS',
        'description' => 'Designed to standard business letterhead dimensions with proper margins and layout guidelines.'
    ],
    [
        'icon' => 'fas fa-sync-alt',
        'title' => 'TEMPLATE SYSTEM',
        'description' => 'Easy-to-use template system that allows your team to create consistent documents effortlessly.'
    ]
];

$grid_title = 'LETTERHEAD NEURAL FEATURES';
$grid_subtitle = 'Advanced features for professional business stationery';
$grid_content = renderCyberCardsGrid($letterhead_features);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<!-- Stationery Suite Section -->
<section class="section">
    <div class="container">
        <div class="text-center mb-8">
            <h2>COMPLETE STATIONERY SUITE</h2>
            <p class="hero-subtitle">Comprehensive business stationery with consistent branding</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div class="service-card">
                <i class="fas fa-file-alt service-icon"></i>
                <h3 class="service-title">LETTERHEAD</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Official business letterhead</li>
                    <li style="margin: 0.5rem 0;">◉ Header and footer design</li>
                    <li style="margin: 0.5rem 0;">◉ Contact information layout</li>
                    <li style="margin: 0.5rem 0;">◉ Brand element integration</li>
                </ul>
            </div>

            <div class="service-card">
                <i class="fas fa-envelope service-icon"></i>
                <h3 class="service-title">ENVELOPES</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Matching envelope design</li>
                    <li style="margin: 0.5rem 0;">◉ Return address layout</li>
                    <li style="margin: 0.5rem 0;">◉ Standard envelope sizes</li>
                    <li style="margin: 0.5rem 0;">◉ Postal optimization</li>
                </ul>
            </div>

            <div class="service-card">
                <i class="fas fa-id-card service-icon"></i>
                <h3 class="service-title">BUSINESS CARDS</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Consistent brand design</li>
                    <li style="margin: 0.5rem 0;">◉ Professional layout</li>
                    <li style="margin: 0.5rem 0;">◉ Contact information</li>
                    <li style="margin: 0.5rem 0;">◉ Premium finish options</li>
                </ul>
            </div>

            <div class="service-card">
                <i class="fas fa-sticky-note service-icon"></i>
                <h3 class="service-title">COMPLIMENT SLIPS</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Branded compliment slips</li>
                    <li style="margin: 0.5rem 0;">◉ Minimalist design</li>
                    <li style="margin: 0.5rem 0;">◉ Professional messaging</li>
                    <li style="margin: 0.5rem 0;">◉ Brand consistency</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<?php
// CTA Section
$cta_title = 'READY TO ESTABLISH PROFESSIONAL COMMUNICATIONS?';
$cta_subtitle = 'Create stunning letterheads that enhance your business communications and establish authority in every correspondence.';
$cta_buttons = '<a href="/contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">GET LETTERHEADS</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
