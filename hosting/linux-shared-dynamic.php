<?php
// Include product pricing utility
include_once '../utilities/product_pricing.php';
include_once '../utilities/pricing-cards.php';

// Page variables
$current_page = 'hosting';
$page_title = 'Linux Shared Hosting - Powerful & Reliable | HiveNest Matrix';
$page_description = 'Linux Shared Hosting - Powerful, reliable Linux hosting with cPanel, unlimited bandwidth, and 99.9% uptime guarantee from HiveNest.';
$page_keywords = 'linux hosting, cpanel hosting, linux web hosting, reliable hosting, php hosting';

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Quantum Servers', 'url' => '../main-services/hosting.php'],
    ['text' => 'Linux Shared Hosting', 'url' => null]
];

// Get pricing from database
$pricing_tiers = getProductPricingById(2); // Product ID 2 is Linux Shared Hosting
$pricing_plans = convertToPricingCards($pricing_tiers, 'addLinuxHostingToCart');

// Page-specific JavaScript - MUST declare as global for scripts.php to access it
global $page_scripts;
$page_scripts = "
function addLinuxHostingToCart(planSlug, price) {
    console.log('addLinuxHostingToCart called with:', planSlug, price);
    
    // Try multiple times if cart not ready
    let attempts = 0;
    const maxAttempts = 20;
    
    const tryAddToCart = function() {
        if (window.addToCart) {
            console.log('Cart system found, adding item...');
            window.addToCart({
                id: 'multi-domain-linux-hosting--' + planSlug,
                name: 'Linux Hosting: ' + planSlug,
                price: price,
                type: 'hosting'
            });
            return true;
        }
        
        attempts++;
        if (attempts < maxAttempts) {
            console.log('Cart not ready, retry attempt ' + attempts);
            setTimeout(tryAddToCart, 100);
        } else {
            console.error('Cart system not loaded after ' + maxAttempts + ' attempts');
            console.warn('Cart system is not ready. Please refresh the page and try again.');
        }
        return false;
    };
    
    tryAddToCart();
}

console.log('Linux Shared Hosting page loaded');
console.log('addLinuxHostingToCart function defined:', typeof addLinuxHostingToCart);
console.log('Cart system available:', typeof window.addToCart);
";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<?php include '../utilities/head.php'; ?>
<?php 
include_once '../utilities/seo-meta.php';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-domain-server-blue.jpg',
    'url' => 'https://hivenest.co.za/hosting/linux-shared.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Linux Shared Hosting',
        'description' => 'Powerful and reliable Linux hosting with cPanel and enterprise features',
        'serviceType' => 'Linux Hosting Services'
    ])
];
echo renderSEOMeta($seo_config);
?>
</head>
<body>
<?php include '../utilities/nav.php'; ?>
<?php include '../utilities/mobile-menu.php'; ?>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <img src="../assets/images/heroes/hero-domain-server-green.jpg" alt="Linux Shared Hosting" class="hero-background">
        
        <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title">
                    <span class="cyber-text">LINUX</span><br>
                    SHARED HOSTING
                </h1>
                <p class="hero-subtitle">
                    Powerful, reliable Linux hosting with cPanel, unlimited bandwidth, and enterprise-grade security. Built for performance.
                </p>
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                    <a href="#plans" class="btn btn-primary">EXPLORE PLANS</a>
                    <a href="#features" class="btn btn-secondary">VIEW FEATURES</a>
                </div>
            </div>
            
            <div class="hero-visual">
                <div class="cyber-orb"></div>
            </div>
        </div>
    </section>

    <!-- Linux Hosting Plans -->
    <section id="plans" class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>LINUX HOSTING PLANS</h2>
                <p class="hero-subtitle">Choose the perfect Linux hosting plan for your needs</p>
            </div>
            
            <?php echo renderPricingGrid($pricing_plans); ?>
        </div>
    </section>

    <!-- Linux Features -->
    <section id="features" class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
        <div class="container">
            <div class="text-center mb-8">
                <h2>LINUX HOSTING FEATURES</h2>
                <p class="hero-subtitle">Why choose Linux hosting from HiveNest?</p>
            </div>
            
            <div class="services-grid">
                <div class="cyber-card">
                    <i class="fab fa-linux service-icon"></i>
                    <h3 class="service-title">LATEST LINUX OS</h3>
                    <p class="service-description">
                        CentOS and Ubuntu latest versions with regular security updates and optimized performance configurations.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-code service-icon"></i>
                    <h3 class="service-title">MULTIPLE PHP VERSIONS</h3>
                    <p class="service-description">
                        Support for PHP 7.4, 8.0, 8.1, 8.2 with easy version switching and optimized configurations for maximum performance.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-database service-icon"></i>
                    <h3 class="service-title">MYSQL & MARIADB</h3>
                    <p class="service-description">
                        Latest MySQL and MariaDB databases with unlimited databases, phpMyAdmin access, and automated backups.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-tachometer-alt service-icon"></i>
                    <h3 class="service-title">CPANEL CONTROL</h3>
                    <p class="service-description">
                        Industry-standard cPanel with one-click installs, file manager, and advanced hosting management tools.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-shield-alt service-icon"></i>
                    <h3 class="service-title">ADVANCED SECURITY</h3>
                    <p class="service-description">
                        ModSecurity firewall, DDoS protection, malware scanning, and automated security updates.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-rocket service-icon"></i>
                    <h3 class="service-title">SSD STORAGE</h3>
                    <p class="service-description">
                        Ultra-fast SSD storage with RAID configuration for maximum performance and data redundancy.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="section">
        <div class="container text-center">
            <h2>READY TO POWER YOUR WEBSITES WITH LINUX?</h2>
            <p class="hero-subtitle mb-8">
                Launch your websites with our reliable Linux hosting platform featuring cPanel, SSD storage, and enterprise-grade performance.
            </p>
            <div style="display: flex; gap: 2rem; justify-content: center; flex-wrap: wrap;">
                <a href="../contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">GET LINUX HOSTING</a>
            </div>
        </div>
    </section>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
