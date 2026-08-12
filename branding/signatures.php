<?php
// Include required utilities FIRST
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/dynamic_pricing.php';

// Page variables
$current_page = 'branding';
$page_title = 'Email Signatures - Neural Graphics | HiveNest Matrix';
$page_description = 'Professional email signatures with cyberpunk aesthetics. Custom neural signature designs that enhance your digital communication presence.';
$page_keywords = 'email signatures, professional signatures, cyberpunk signatures, neural graphics, digital signatures';

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Neural Graphics', 'url' => '/main-services/branding.php'],
    ['text' => 'Email Signatures', 'url' => null]
];

// Get pricing: cache → DB (getProductPricingById) → DB by slug → empty
$signature_packages = loadProductPricingPlans([
    'product_id'    => 13,
    'product_slug'  => 'email-signatures',
    'cart_function' => 'addToCart',
]);

// Page-specific JavaScript
$page_scripts = "
function addToCart(planId, price) {
    const planNames = {
        'signature-individual': 'Email Signature - Individual',
        'signature-team': 'Email Signature - Team Package',
        'signature-enterprise': 'Email Signature - Enterprise Suite'
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
$hero_title = '<span class="cyber-text">EMAIL</span><br>SIGNATURES';
$hero_subtitle = 'Professional email signatures with cyberpunk aesthetics. Custom neural signature designs that enhance your digital communication presence.';
$hero_image = '../assets/images/heroes/hero-branding-workspace.jpg';
$hero_alt = 'Email Signature Design';
include '../utilities/hero-minimal.php';
?>

<?php
// Email Signature Packages - Retrieved from database above
include '../utilities/pricing-cards.php';

$grid_title = 'EMAIL SIGNATURE POWER LEVELS';
$grid_subtitle = 'Professional signature packages for individuals and teams';
$grid_content = renderPricingGrid($signature_packages);
include '../utilities/grid-section.php';
?>

<?php
// Signature Features
include '../utilities/cyber-cards.php';
$signature_features = [
    [
        'icon' => 'fas fa-mobile-alt',
        'title' => 'MOBILE OPTIMIZED',
        'description' => 'Signatures that look perfect on desktop, mobile, and tablet devices with responsive design principles.'
    ],
    [
        'icon' => 'fas fa-share-alt',
        'title' => 'SOCIAL INTEGRATION',
        'description' => 'Seamless integration of social media links, contact information, and professional networking profiles.'
    ],
    [
        'icon' => 'fas fa-palette',
        'title' => 'BRAND CONSISTENCY',
        'description' => 'Signatures that match your brand identity with consistent colors, fonts, and visual elements.'
    ],
    [
        'icon' => 'fas fa-code',
        'title' => 'HTML COMPATIBILITY',
        'description' => 'Clean HTML code compatible with all major email clients including Outlook, Gmail, and Apple Mail.'
    ],
    [
        'icon' => 'fas fa-chart-line',
        'title' => 'CLICK TRACKING',
        'description' => 'Advanced analytics and click tracking to measure signature performance and engagement rates.'
    ],
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'PROFESSIONAL SECURITY',
        'description' => 'Secure image hosting and professional appearance that builds trust and credibility.'
    ]
];

$grid_title = 'SIGNATURE NEURAL FEATURES';
$grid_subtitle = 'Advanced features for professional email communication';
$grid_content = renderCyberCardsGrid($signature_features);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<!-- Email Client Compatibility Section -->
<section class="section">
    <div class="container">
        <div class="text-center mb-8">
            <h2>EMAIL CLIENT COMPATIBILITY</h2>
            <p class="hero-subtitle">Perfect rendering across all major email clients</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
            <div class="service-card">
                <i class="fab fa-microsoft service-icon"></i>
                <h3 class="service-title">OUTLOOK</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Full compatibility with Outlook 2016, 2019, Office 365, and Outlook.com 
                    with proper rendering and interactive elements.
                </p>
            </div>

            <div class="service-card">
                <i class="fab fa-google service-icon"></i>
                <h3 class="service-title">GMAIL</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Optimized for Gmail web, mobile app, and G Suite with perfect 
                    image rendering and clickable elements.
                </p>
            </div>

            <div class="service-card">
                <i class="fab fa-apple service-icon"></i>
                <h3 class="service-title">APPLE MAIL</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Native support for Apple Mail on macOS and iOS with high-resolution 
                    image display and touch-friendly links.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-envelope service-icon"></i>
                <h3 class="service-title">UNIVERSAL CLIENTS</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Compatible with Thunderbird, Yahoo Mail, AOL, and other popular 
                    email clients with fallback support.
                </p>
            </div>
        </div>
    </div>
</section>

<?php
// CTA Section
$cta_title = 'READY TO ENHANCE YOUR EMAIL COMMUNICATION?';
$cta_subtitle = 'Create professional email signatures that make every message count and build your brand with every communication.';
$cta_buttons = '<a href="/contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">GET SIGNATURES</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
