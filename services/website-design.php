<?php
// Page variables
$current_page = 'services';
$page_title = 'Website Design Services - Neural Web Architecture | HiveNest Matrix';
$page_description = 'Professional website design services with cyberpunk aesthetics. Custom neural web designs that captivate audiences and drive conversions.';
$page_keywords = 'website design, web design, cyberpunk web design, neural web architecture, custom websites';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-branding-ipad-night.jpg',
    'url' => 'https://hivenest.co.za/services/website-design.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Website Design Services',
        'description' => 'Professional website design with cyberpunk aesthetics and neural architecture',
        'serviceType' => 'Web Design and Development Services'
    ])
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Services', 'url' => '../branding/website-builder.php'],
    ['text' => 'Website Design', 'url' => null]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php 
include '../utilities/seo-meta.php';
echo renderSEOMeta($seo_config);
include '../utilities/head.php';
?>
    
</head>
<body>
<?php include '../utilities/nav.php'; ?>
<?php include '../utilities/mobile-menu.php'; ?>

<?php
// Hero Section
$hero_title = '<span class="cyber-text">WEBSITE</span><br>NEURAL ARCHITECTURE';
$hero_subtitle = 'Professional website design services with cyberpunk aesthetics. Custom neural web designs that captivate audiences and drive conversions.';
$hero_image = '../assets/images/heroes/hero-branding-gradients.jpg';
$hero_alt = 'Website Design Architecture';
include '../utilities/hero-minimal.php';
?>

<?php
// Breadcrumbs
include '../utilities/breadcrumbs.php';
?>

<?php
// Website Design Services
include '../utilities/cyber-cards.php';
$design_services = [
    [
        'icon' => 'fas fa-paint-brush',
        'title' => 'CUSTOM WEB DESIGN',
        'description' => 'Unique, hand-crafted website designs tailored to your brand with cutting-edge cyberpunk aesthetics and neural elements.'
    ],
    [
        'icon' => 'fas fa-mobile-alt',
        'title' => 'RESPONSIVE DESIGN',
        'description' => 'Mobile-first responsive designs that adapt seamlessly across all devices with pixel-perfect precision.'
    ],
    [
        'icon' => 'fas fa-shopping-cart',
        'title' => 'E-COMMERCE DESIGN',
        'description' => 'Professional e-commerce website designs with optimized user experience and conversion-focused layouts.'
    ],
    [
        'icon' => 'fas fa-rocket',
        'title' => 'PERFORMANCE OPTIMIZATION',
        'description' => 'Speed-optimized designs with advanced caching, image optimization, and quantum-level loading speeds.'
    ],
    [
        'icon' => 'fas fa-search',
        'title' => 'SEO-OPTIMIZED DESIGN',
        'description' => 'SEO-friendly website architecture with clean code, semantic markup, and search engine optimization.'
    ],
    [
        'icon' => 'fas fa-users',
        'title' => 'UX/UI DESIGN',
        'description' => 'User-centered design approach with intuitive interfaces and engaging user experiences that convert visitors.'
    ]
];

$grid_title = 'WEBSITE DESIGN SERVICES MATRIX';
$grid_subtitle = 'Comprehensive web design solutions for digital dominance';
$grid_content = renderCyberCardsGrid($design_services);
include '../utilities/grid-section.php';
?>

<!-- Design Process -->
<section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
    <div class="container">
        <div class="text-center mb-8">
            <h2>NEURAL DESIGN PROCESS</h2>
            <p class="hero-subtitle">Our systematic approach to creating exceptional websites</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
            <div class="service-card">
                <div style="text-align: center; margin-bottom: 1rem;">
                    <div style="width: 60px; height: 60px; background: var(--cyber-neon-cyan); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: black; font-weight: bold; font-size: 1.2rem;">1</div>
                </div>
                <h3 class="service-title">DISCOVERY</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Deep dive into your brand, goals, target audience, and competitive landscape 
                    to establish project requirements and design direction.
                </p>
            </div>

            <div class="service-card">
                <div style="text-align: center; margin-bottom: 1rem;">
                    <div style="width: 60px; height: 60px; background: var(--cyber-neon-green); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: black; font-weight: bold; font-size: 1.2rem;">2</div>
                </div>
                <h3 class="service-title">WIREFRAMING</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Strategic wireframing and information architecture planning to optimize 
                    user flow and ensure intuitive navigation patterns.
                </p>
            </div>

            <div class="service-card">
                <div style="text-align: center; margin-bottom: 1rem;">
                    <div style="width: 60px; height: 60px; background: var(--cyber-neon-pink); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: black; font-weight: bold; font-size: 1.2rem;">3</div>
                </div>
                <h3 class="service-title">VISUAL DESIGN</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    High-fidelity visual design creation with cyberpunk aesthetics, 
                    neural elements, and brand-consistent styling.
                </p>
            </div>

            <div class="service-card">
                <div style="text-align: center; margin-bottom: 1rem;">
                    <div style="width: 60px; height: 60px; background: var(--cyber-neon-orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: black; font-weight: bold; font-size: 1.2rem;">4</div>
                </div>
                <h3 class="service-title">DEVELOPMENT</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Professional front-end development with clean, semantic code, 
                    responsive design, and performance optimization.
                </p>
            </div>

            <div class="service-card">
                <div style="text-align: center; margin-bottom: 1rem;">
                    <div style="width: 60px; height: 60px; background: var(--cyber-neon-purple); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: black; font-weight: bold; font-size: 1.2rem;">5</div>
                </div>
                <h3 class="service-title">TESTING</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Comprehensive testing across devices, browsers, and performance 
                    metrics to ensure flawless user experience.
                </p>
            </div>

            <div class="service-card">
                <div style="text-align: center; margin-bottom: 1rem;">
                    <div style="width: 60px; height: 60px; background: var(--cyber-neon-blue); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: black; font-weight: bold; font-size: 1.2rem;">6</div>
                </div>
                <h3 class="service-title">LAUNCH</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Professional website launch with SEO setup, analytics integration, 
                    and ongoing support for continued success.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Design Specialties -->
<section class="section">
    <div class="container">
        <div class="text-center mb-8">
            <h2>DESIGN SPECIALTIES</h2>
            <p class="hero-subtitle">Expertise across industries and website types</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div class="service-card">
                <i class="fas fa-briefcase service-icon"></i>
                <h3 class="service-title">CORPORATE WEBSITES</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Professional corporate websites with executive presence, 
                    investor relations, and enterprise-grade functionality.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-store service-icon"></i>
                <h3 class="service-title">E-COMMERCE STORES</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Conversion-optimized online stores with advanced product catalogs, 
                    secure checkout, and inventory management.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-palette service-icon"></i>
                <h3 class="service-title">CREATIVE PORTFOLIOS</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Stunning portfolio websites for artists, photographers, 
                    and creative professionals with gallery functionality.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-graduation-cap service-icon"></i>
                <h3 class="service-title">EDUCATIONAL PLATFORMS</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Learning management systems with course delivery, 
                    student portals, and educational content organization.
                </p>
            </div>
        </div>
    </div>
</section>

<?php
// CTA Section
$cta_title = 'READY TO CREATE YOUR DIGITAL MASTERPIECE?';
$cta_subtitle = 'Transform your vision into a stunning website that captivates audiences and drives business success with our neural design approach.';
$cta_buttons = '<a href="../contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">START PROJECT</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>