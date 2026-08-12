<?php
// Include required utilities FIRST
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/dynamic_pricing.php';

// Page variables
$current_page = 'branding';
$page_title = 'Logo Design - Neural Graphics | HiveNest Matrix';
$page_description = 'Professional cyberpunk logo design services for your digital empire. Custom neural graphics that define your brand identity in the digital future.';
$page_keywords = 'logo design, cyberpunk logo, neural graphics, digital branding, custom logo design';

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Neural Graphics', 'url' => '/main-services/branding.php'],
    ['text' => 'Logo Design', 'url' => null]
];

// Get pricing: cache → DB (getProductPricingById) → DB by slug → empty
$logo_packages = loadProductPricingPlans([
    'product_id'    => 10,
    'product_slug'  => 'logo-design',
    'cart_function' => 'addToCart',
]);

// Page-specific JavaScript
$page_scripts = "
function addToCart(planId, price) {
    const planNames = {
        'logo-basic': 'Logo Design - Basic Package',
        'logo-professional': 'Logo Design - Professional Package',
        'logo-enterprise': 'Logo Design - Enterprise Package'
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
$hero_title = '<span class="cyber-text">LOGO</span><br>NEURAL DESIGN';
$hero_subtitle = 'Professional cyberpunk logo design services for your digital empire. Custom neural graphics that define your brand identity in the digital future.';
$hero_image = '../assets/images/heroes/hero-branding-design.jpg';
$hero_alt = 'Logo Design Neural Graphics';
include '../utilities/hero-minimal.php';
?>

<?php
// Logo Design Packages - Retrieved from database above
$grid_title = 'LOGO DESIGN POWER LEVELS';
$grid_subtitle = 'Professional logo design packages for every business need';
include '../utilities/pricing-cards.php';
$grid_content = renderPricingGrid($logo_packages);
include '../utilities/grid-section.php';
?>

<?php
// Design Features
include '../utilities/cyber-cards.php';
$design_features = [
    [
        'icon' => 'fas fa-paint-brush',
        'title' => 'CUSTOM ARTWORK',
        'description' => 'Original, hand-crafted designs tailored to your brand vision with cyberpunk aesthetics and futuristic elements.'
    ],
    [
        'icon' => 'fas fa-palette',
        'title' => 'NEURAL COLOR THEORY',
        'description' => 'Advanced color psychology and neural color combinations that resonate with your target audience.'
    ],
    [
        'icon' => 'fas fa-vector-square',
        'title' => 'VECTOR PERFECTION',
        'description' => 'Scalable vector graphics that maintain quality at any size, from business cards to billboards.'
    ],
    [
        'icon' => 'fas fa-mobile-alt',
        'title' => 'RESPONSIVE DESIGN',
        'description' => 'Logos optimized for all platforms - web, mobile, print, and social media applications.'
    ],
    [
        'icon' => 'fas fa-search',
        'title' => 'TRADEMARK RESEARCH',
        'description' => 'Comprehensive trademark and copyright research to ensure your logo is unique and legally protected.'
    ],
    [
        'icon' => 'fas fa-file-contract',
        'title' => 'BRAND GUIDELINES',
        'description' => 'Detailed brand guidelines document with usage rules, color codes, and application examples.'
    ]
];

$grid_title = 'DESIGN NEURAL FEATURES';
$grid_subtitle = 'Advanced design features for professional brand identity';
$grid_content = renderCyberCardsGrid($design_features);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<!-- Design Process Section -->
<section class="section">
    <div class="container">
        <div class="text-center mb-8">
            <h2>NEURAL DESIGN PROCESS</h2>
            <p class="hero-subtitle">Our systematic approach to creating iconic brand identities</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div class="service-card">
                <i class="fas fa-lightbulb service-icon"></i>
                <h3 class="service-title">DISCOVERY</h3>
                <p style="color: rgba(255, 255, 255, 0.8); text-align: left;">
                    Deep dive into your brand vision, target audience, and competitive landscape 
                    to establish design direction and neural aesthetic preferences.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-drafting-compass service-icon"></i>
                <h3 class="service-title">CONCEPT CREATION</h3>
                <p style="color: rgba(255, 255, 255, 0.8); text-align: left;">
                    Multiple unique logo concepts exploring different visual directions, 
                    cyberpunk elements, and brand personality expressions.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-sync-alt service-icon"></i>
                <h3 class="service-title">REFINEMENT</h3>
                <p style="color: rgba(255, 255, 255, 0.8); text-align: left;">
                    Collaborative refinement process with unlimited revisions to perfect 
                    your chosen concept and achieve pixel-perfect execution.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-rocket service-icon"></i>
                <h3 class="service-title">DELIVERY</h3>
                <p style="color: rgba(255, 255, 255, 0.8); text-align: left;">
                    Complete logo package with all file formats, color variations, 
                    and comprehensive brand guidelines for immediate implementation.
                </p>
            </div>
        </div>
    </div>
</section>

<?php
// CTA Section
$cta_title = 'READY TO CREATE YOUR ICONIC BRAND IDENTITY?';
$cta_subtitle = 'Let our neural design team craft a logo that defines your digital empire and resonates across all dimensions of reality.';
$cta_buttons = '<a href="/contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">START DESIGN PROJECT</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
