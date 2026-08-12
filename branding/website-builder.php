<?php
// Include required utilities FIRST
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/dynamic_pricing.php';

// Page variables
$current_page = 'branding';
$page_title = 'Website Builder - Neural Graphics | HiveNest Matrix';
$page_description = 'Drag & drop website builder with cyberpunk templates. Create stunning neural websites without coding using our advanced visual editor.';
$page_keywords = 'website builder, drag drop website, cyberpunk websites, neural web design, visual editor';

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Neural Graphics', 'url' => '/main-services/branding.php'],
    ['text' => 'Website Builder', 'url' => null]
];

// Get pricing: cache → DB (getProductPricingById) → DB by slug → empty
$builder_plans = loadProductPricingPlans([
    'product_id'    => 9,
    'product_slug'  => 'website-builder',
    'cart_function' => 'addWebsiteBuilderToCart',
]);

// Page-specific JavaScript
$page_scripts = "
function addWebsiteBuilderToCart(planSlug, price) {
    const cleanSlug = String(planSlug);
    const tierSlug = cleanSlug.includes('--') ? cleanSlug.split('--').pop() : cleanSlug;
    const cartId = cleanSlug.includes('--') ? cleanSlug : 'website-builder--' + cleanSlug;
    const planName = tierSlug.replace(/-/g, ' ').toUpperCase();
    if (window.addToCart) {
        window.addToCart({
            id: cartId,
            name: 'Website Builder: ' + planName,
            price: price,
            type: 'website'
        });
    } else {
        console.error('Cart system not loaded');
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
$hero_title = '<span class="cyber-text">WEBSITE</span><br>BUILDER MATRIX';
$hero_subtitle = 'Drag & drop website builder with cyberpunk templates. Create stunning neural websites without coding using our advanced visual editor.';
$hero_image = '../assets/images/heroes/hero-branding-ipad-day.jpg';
$hero_alt = 'Website Builder Interface';
include '../utilities/hero-minimal.php';
?>

<?php
// Website Builder Plans - Retrieved from database above
$grid_title = 'WEBSITE BUILDER POWER LEVELS';
$grid_subtitle = 'Build stunning websites without coding knowledge';
include '../utilities/pricing-cards.php';
$grid_content = renderPricingGrid($builder_plans);
include '../utilities/grid-section.php';
?>

<?php
// Builder Features
include '../utilities/cyber-cards.php';
$builder_features = [
    [
        'icon' => 'fas fa-mouse-pointer',
        'title' => 'DRAG & DROP EDITOR',
        'description' => 'Intuitive visual editor that lets you build professional websites by simply dragging and dropping elements.'
    ],
    [
        'icon' => 'fas fa-mobile-alt',
        'title' => 'RESPONSIVE DESIGN',
        'description' => 'All websites automatically adapt to desktop, tablet, and mobile devices with pixel-perfect responsiveness.'
    ],
    [
        'icon' => 'fas fa-rocket',
        'title' => 'SEO OPTIMIZED',
        'description' => 'Built-in SEO tools, meta tag management, and performance optimization for better search engine rankings.'
    ],
    [
        'icon' => 'fas fa-shopping-cart',
        'title' => 'E-COMMERCE READY',
        'description' => 'Integrated e-commerce features with payment processing, inventory management, and order tracking.'
    ],
    [
        'icon' => 'fas fa-chart-bar',
        'title' => 'ANALYTICS INTEGRATION',
        'description' => 'Built-in analytics dashboard with Google Analytics integration for detailed visitor insights.'
    ],
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'SECURE HOSTING',
        'description' => 'Enterprise-grade security with SSL certificates, DDoS protection, and automated backups.'
    ]
];

$grid_title = 'BUILDER NEURAL FEATURES';
$grid_subtitle = 'Advanced features for professional website creation';
$grid_content = renderCyberCardsGrid($builder_features);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<!-- Template Categories Section -->
<section class="section">
    <div class="container">
        <div class="text-center mb-8">
            <h2>CYBERPUNK TEMPLATE LIBRARY</h2>
            <p class="hero-subtitle">Professionally designed templates for every industry</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div class="service-card">
                <i class="fas fa-briefcase service-icon"></i>
                <h3 class="service-title">BUSINESS</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Corporate websites</li>
                    <li style="margin: 0.5rem 0;">◉ Consulting agencies</li>
                    <li style="margin: 0.5rem 0;">◉ Professional services</li>
                    <li style="margin: 0.5rem 0;">◉ Financial firms</li>
                </ul>
            </div>

            <div class="service-card">
                <i class="fas fa-store service-icon"></i>
                <h3 class="service-title">E-COMMERCE</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Online stores</li>
                    <li style="margin: 0.5rem 0;">◉ Product catalogs</li>
                    <li style="margin: 0.5rem 0;">◉ Fashion boutiques</li>
                    <li style="margin: 0.5rem 0;">◉ Tech retailers</li>
                </ul>
            </div>

            <div class="service-card">
                <i class="fas fa-palette service-icon"></i>
                <h3 class="service-title">CREATIVE</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Design portfolios</li>
                    <li style="margin: 0.5rem 0;">◉ Photography</li>
                    <li style="margin: 0.5rem 0;">◉ Art galleries</li>
                    <li style="margin: 0.5rem 0;">◉ Creative agencies</li>
                </ul>
            </div>

            <div class="service-card">
                <i class="fas fa-utensils service-icon"></i>
                <h3 class="service-title">RESTAURANTS</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Restaurant websites</li>
                    <li style="margin: 0.5rem 0;">◉ Food delivery</li>
                    <li style="margin: 0.5rem 0;">◉ Cafes & bars</li>
                    <li style="margin: 0.5rem 0;">◉ Catering services</li>
                </ul>
            </div>

            <div class="service-card">
                <i class="fas fa-heartbeat service-icon"></i>
                <h3 class="service-title">HEALTHCARE</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Medical practices</li>
                    <li style="margin: 0.5rem 0;">◉ Dental clinics</li>
                    <li style="margin: 0.5rem 0;">◉ Wellness centers</li>
                    <li style="margin: 0.5rem 0;">◉ Healthcare providers</li>
                </ul>
            </div>

            <div class="service-card">
                <i class="fas fa-graduation-cap service-icon"></i>
                <h3 class="service-title">EDUCATION</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Online courses</li>
                    <li style="margin: 0.5rem 0;">◉ Educational institutions</li>
                    <li style="margin: 0.5rem 0;">◉ Training centers</li>
                    <li style="margin: 0.5rem 0;">◉ Learning platforms</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- Integration Options Section -->
<section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
    <div class="container">
        <div class="text-center mb-8">
            <h2>POWERFUL INTEGRATIONS</h2>
            <p class="hero-subtitle">Connect your website with essential business tools</p>
        </div>
        
        <div class="cyber-card" style="max-width: 800px; margin: 0 auto;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                <div style="text-align: center;">
                    <i class="fab fa-google" style="font-size: 2rem; color: var(--cyber-neon-blue); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-blue); margin-bottom: 0.5rem;">GOOGLE SERVICES</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Analytics, Maps, Fonts, and more</p>
                </div>
                
                <div style="text-align: center;">
                    <i class="fab fa-facebook" style="font-size: 2rem; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;">SOCIAL MEDIA</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Facebook, Instagram, Twitter feeds</p>
                </div>
                
                <div style="text-align: center;">
                    <i class="fas fa-credit-card" style="font-size: 2rem; color: var(--cyber-neon-green); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-green); margin-bottom: 0.5rem;">PAYMENT</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Stripe, PayPal, Square</p>
                </div>
                
                <div style="text-align: center;">
                    <i class="fas fa-envelope" style="font-size: 2rem; color: var(--cyber-neon-pink); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-pink); margin-bottom: 0.5rem;">EMAIL MARKETING</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Mailchimp, Constant Contact</p>
                </div>
            </div>
            
            <div style="text-align: center;">
                <a href="/contact.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                    VIEW ALL INTEGRATIONS
                </a>
            </div>
        </div>
    </div>
</section>

<?php
// CTA Section
$cta_title = 'READY TO BUILD YOUR DIGITAL EMPIRE?';
$cta_subtitle = 'Start creating your professional website today with our powerful drag & drop builder and cyberpunk templates.';
$cta_buttons = '<a href="/contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">START BUILDING</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
