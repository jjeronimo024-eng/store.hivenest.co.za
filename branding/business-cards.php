<?php
// Include required utilities FIRST
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/dynamic_pricing.php';

// Page variables
$current_page = 'branding';
$page_title = 'Business Cards - Neural Graphics | HiveNest Matrix';
$page_description = 'Professional business card designs with cyberpunk aesthetics. Custom neural business cards that make lasting impressions in the digital age.';
$page_keywords = 'business card design, professional business cards, cyberpunk business cards, neural graphics, custom cards';

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Neural Graphics', 'url' => '/main-services/branding.php'],
    ['text' => 'Business Cards', 'url' => null]
];

// Get pricing: cache → DB (getProductPricingById) → DB by slug → empty
$card_packages = loadProductPricingPlans([
    'product_id'    => 11,
    'product_slug'  => 'business-cards',
    'cart_function' => 'addToCart',
]);

// Page-specific JavaScript
$page_scripts = "
function addToCart(planId, price) {
    const planNames = {
        'card-standard': 'Business Cards - Standard Design',
        'card-premium': 'Business Cards - Premium Design',
        'card-luxury': 'Business Cards - Luxury Collection'
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
$hero_title = '<span class="cyber-text">BUSINESS</span><br>CARD MATRIX';
$hero_subtitle = 'Professional business card designs with cyberpunk aesthetics. Custom neural business cards that make lasting impressions in the digital age.';
$hero_image = '../assets/images/heroes/hero-branding-workspace.jpg';
$hero_alt = 'Professional Business Card Design';
include '../utilities/hero-minimal.php';
?>

<?php
// Business Card Packages - Retrieved from database above
$grid_title = 'BUSINESS CARD POWER LEVELS';
$grid_subtitle = 'Professional card designs for every networking need';
include '../utilities/pricing-cards.php';
$grid_content = renderPricingGrid($card_packages);
include '../utilities/grid-section.php';
?>

<?php
// Business Card Features
include '../utilities/cyber-cards.php';
$card_features = [
    [
        'icon' => 'fas fa-magic',
        'title' => 'CUSTOM DESIGN',
        'description' => 'Unique, hand-crafted designs tailored to your personal brand and professional identity with cyberpunk elements.'
    ],
    [
        'icon' => 'fas fa-gem',
        'title' => 'PREMIUM FINISHES',
        'description' => 'Extensive finish options including matte, gloss, spot UV, foil stamping, and embossing for memorable cards.'
    ],
    [
        'icon' => 'fas fa-qrcode',
        'title' => 'DIGITAL INTEGRATION',
        'description' => 'QR codes, NFC chips, and digital contact integration for seamless contact exchange in the digital age.'
    ],
    [
        'icon' => 'fas fa-cut',
        'title' => 'CUSTOM SHAPES',
        'description' => 'Unique die-cut shapes and custom dimensions that break the mold and create unforgettable impressions.'
    ],
    [
        'icon' => 'fas fa-print',
        'title' => 'PRINT READY',
        'description' => 'High-resolution files with proper bleed and CMYK color profiles for professional printing results.'
    ],
    [
        'icon' => 'fas fa-handshake',
        'title' => 'NETWORKING OPTIMIZED',
        'description' => 'Designed for maximum impact during networking events with clear hierarchy and memorable visual elements.'
    ]
];

$grid_title = 'BUSINESS CARD NEURAL FEATURES';
$grid_subtitle = 'Advanced features for professional networking tools';
$grid_content = renderCyberCardsGrid($card_features);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<!-- Finish Options Section -->
<section class="section">
    <div class="container">
        <div class="text-center mb-8">
            <h2>PREMIUM FINISH OPTIONS</h2>
            <p class="hero-subtitle">Luxury finishes that make your cards stand out</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
            <div class="service-card">
                <i class="fas fa-star service-icon"></i>
                <h3 class="service-title">FOIL STAMPING</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Metallic foil applications in gold, silver, copper, or holographic 
                    finishes that catch light and attention.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-mountain service-icon"></i>
                <h3 class="service-title">EMBOSSING</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Raised texture elements that add tactile dimension and 
                    premium feel to your business cards.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-sun service-icon"></i>
                <h3 class="service-title">SPOT UV</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Selective gloss coating on matte backgrounds creating 
                    stunning contrast and visual interest.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-cut service-icon"></i>
                <h3 class="service-title">DIE CUTTING</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Custom shapes and unique cuts that break traditional 
                    rectangular formats for memorable impact.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-layer-group service-icon"></i>
                <h3 class="service-title">THICK STOCK</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Premium heavyweight cardstock options from 16pt to 32pt 
                    for substantial feel and professional presence.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-wifi service-icon"></i>
                <h3 class="service-title">NFC INTEGRATION</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Near Field Communication chips for instant contact sharing 
                    and digital business card experiences.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Design Process Section -->
<section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
    <div class="container">
        <div class="text-center mb-8">
            <h2>NEURAL DESIGN PROCESS</h2>
            <p class="hero-subtitle">Our systematic approach to creating memorable business cards</p>
        </div>
        
        <div class="cyber-card" style="max-width: 800px; margin: 0 auto;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                <div style="text-align: center;">
                    <i class="fas fa-user-tie" style="font-size: 2rem; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;">CONSULTATION</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Understanding your professional needs and brand identity</p>
                </div>
                
                <div style="text-align: center;">
                    <i class="fas fa-palette" style="font-size: 2rem; color: var(--cyber-neon-green); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-green); margin-bottom: 0.5rem;">CONCEPT DESIGN</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Creating multiple design concepts and layouts</p>
                </div>
                
                <div style="text-align: center;">
                    <i class="fas fa-sync-alt" style="font-size: 2rem; color: var(--cyber-neon-pink); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-pink); margin-bottom: 0.5rem;">REFINEMENT</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Collaborative refinement and perfection</p>
                </div>
                
                <div style="text-align: center;">
                    <i class="fas fa-rocket" style="font-size: 2rem; color: var(--cyber-neon-orange); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-orange); margin-bottom: 0.5rem;">DELIVERY</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Print-ready files and printing coordination</p>
                </div>
            </div>
            
            <div style="text-align: center;">
                <a href="/contact.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                    START CARD DESIGN
                </a>
            </div>
        </div>
    </div>
</section>

<?php
// CTA Section
$cta_title = 'READY TO MAKE UNFORGETTABLE FIRST IMPRESSIONS?';
$cta_subtitle = 'Create stunning business cards that open doors, start conversations, and build your professional network in style.';
$cta_buttons = '<a href="/contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">GET BUSINESS CARDS</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
