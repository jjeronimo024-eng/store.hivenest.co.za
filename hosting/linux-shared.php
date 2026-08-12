<?php
// Page variables
$current_page = 'hosting';
include_once '../utilities/dynamic_pricing.php';
$page_title = 'Linux Shared Hosting - Powerful & Reliable | HiveNest Matrix';
$page_description = 'Linux Shared Hosting - Powerful, reliable Linux hosting with cPanel, unlimited bandwidth, and 99.9% uptime guarantee from HiveNest.';
$page_keywords = 'linux hosting, cpanel hosting, linux web hosting, reliable hosting, php hosting';

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Quantum Servers', 'url' => '../main-services/hosting.php'],
    ['text' => 'Linux Shared Hosting', 'url' => null]
];

// Hardcoded fallback so the page NEVER goes empty even if DB/cache are down
$pricing_fallback = [
    [
        'name' => 'CYBER INITIATE',
        'price' => '$3.99',
        'period' => '/mo',
        'features' => [
            '1 Website', '10GB SSD Storage', '100GB Bandwidth',
            'cPanel Control Panel', 'Free SSL Certificate', '10 Email Accounts',
            '5 MySQL Databases', '99.9% Uptime'
        ],
        'button' => ['text' => 'ADD TO CART', 'class' => 'btn-secondary',
                     'onclick' => "addLinuxHostingToCart('cyber-initiate', 3.99)"],
    ],
    [
        'name' => 'CYBER WARRIOR',
        'price' => '$7.99',
        'period' => '/mo',
        'features' => [
            '5 Websites', '50GB SSD Storage', 'Unlimited Bandwidth',
            'cPanel Control Panel', 'Free SSL Certificate', 'Unlimited Email',
            'Unlimited Databases', 'Daily Backups', 'Priority Support'
        ],
        'featured' => true,
        'button' => ['text' => 'ADD TO CART', 'class' => 'btn-primary',
                     'onclick' => "addLinuxHostingToCart('cyber-warrior', 7.99)"],
    ],
    [
        'name' => 'CYBER MASTER',
        'price' => '$14.99',
        'period' => '/mo',
        'features' => [
            'Unlimited Websites', '200GB SSD Storage', 'Unlimited Bandwidth',
            'cPanel + WHM', 'Wildcard SSL Certificate', 'Unlimited Email',
            'Unlimited Databases', 'Real-time Backups', 'Dedicated IP', '24/7 Phone Support'
        ],
        'button' => ['text' => 'ADD TO CART', 'class' => 'btn-secondary',
                     'onclick' => "addLinuxHostingToCart('cyber-master', 14.99)"],
    ],
];

// Get Linux hosting plans: cache → DB (getProductPricingById) → DB by slug → hardcoded fallback
$pricing_plans = loadProductPricingPlans([
    'product_id'     => 26,
    'product_slug'   => 'multi-domain-linux-hosting',
    'cart_function'  => 'addLinuxHostingToCart',
    'fallback_plans' => $pricing_fallback,
    'include_assigned_products' => true,
]);

// Page-specific JavaScript - MUST declare as global for scripts.php to access it
global $page_scripts;
$page_scripts = "
function addLinuxHostingToCart(planSlug, price) {
    const planName = String(planSlug).replace(/-/g, ' ').toUpperCase();
    const productSku = String(planSlug).includes('--')
        ? String(planSlug)
        : 'multi-domain-linux-hosting--' + planSlug;
    console.log('addLinuxHostingToCart called with:', planSlug, price);
    
    // Try multiple times if cart not ready
    let attempts = 0;
    const maxAttempts = 20;
    
    const tryAddToCart = function() {
        if (window.addToCart) {
            console.log('Cart system found, adding item...');
            window.addToCart({
                id: productSku,
                name: 'Linux Hosting: ' + planName,
                price: price,
                type: 'hosting',
                category: 'linux-hosting',
                product_config: {
                    sku: productSku
                }
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
            
            <?php
            include '../utilities/pricing-cards.php';
            echo renderPricingGrid($pricing_plans);
            ?>
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

    <!-- Technical Specifications -->
    <section class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>TECHNICAL SPECIFICATIONS</h2>
                <p class="hero-subtitle">Detailed technical information about our Linux hosting</p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem;">
                <div class="service-card">
                    <i class="fas fa-server service-icon"></i>
                    <h3 class="service-title">SERVER SPECIFICATIONS</h3>
                    <ul style="list-style: none; padding: 0; text-align: left; color: rgba(255, 255, 255, 0.8);">
                        <li style="margin: 0.5rem 0;">• Intel Xeon E5-2690 v4 Processors</li>
                        <li style="margin: 0.5rem 0;">• 256GB DDR4 ECC Memory</li>
                        <li style="margin: 0.5rem 0;">• NVMe SSD Storage</li>
                        <li style="margin: 0.5rem 0;">• 1Gbps Network Connection</li>
                        <li style="margin: 0.5rem 0;">• Enterprise-grade Hardware</li>
                    </ul>
                </div>

                <div class="service-card">
                    <i class="fas fa-cog service-icon"></i>
                    <h3 class="service-title">SOFTWARE STACK</h3>
                    <ul style="list-style: none; padding: 0; text-align: left; color: rgba(255, 255, 255, 0.8);">
                        <li style="margin: 0.5rem 0;">• Apache 2.4 / Nginx Web Server</li>
                        <li style="margin: 0.5rem 0;">• PHP 7.4 - 8.2 Support</li>
                        <li style="margin: 0.5rem 0;">• MySQL 8.0 / MariaDB 10.5</li>
                        <li style="margin: 0.5rem 0;">• Python, Perl, Ruby Support</li>
                        <li style="margin: 0.5rem 0;">• Node.js Available</li>
                    </ul>
                </div>

                <div class="service-card">
                    <i class="fas fa-tools service-icon"></i>
                    <h3 class="service-title">DEVELOPER TOOLS</h3>
                    <ul style="list-style: none; padding: 0; text-align: left; color: rgba(255, 255, 255, 0.8);">
                        <li style="margin: 0.5rem 0;">• Git Version Control</li>
                        <li style="margin: 0.5rem 0;">• SSH Access</li>
                        <li style="margin: 0.5rem 0;">• WP-CLI for WordPress</li>
                        <li style="margin: 0.5rem 0;">• Composer for PHP</li>
                        <li style="margin: 0.5rem 0;">• Cron Job Management</li>
                    </ul>
                </div>

                <div class="service-card">
                    <i class="fas fa-chart-line service-icon"></i>
                    <h3 class="service-title">PERFORMANCE</h3>
                    <ul style="list-style: none; padding: 0; text-align: left; color: rgba(255, 255, 255, 0.8);">
                        <li style="margin: 0.5rem 0;">• 99.9% Uptime SLA</li>
                        <li style="margin: 0.5rem 0;">• Global CDN Integration</li>
                        <li style="margin: 0.5rem 0;">• HTTP/2 Support</li>
                        <li style="margin: 0.5rem 0;">• Gzip Compression</li>
                        <li style="margin: 0.5rem 0;">• Browser Caching</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- One-Click Installs -->
    <section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
        <div class="container">
            <div class="text-center mb-8">
                <h2>ONE-CLICK INSTALLS</h2>
                <p class="hero-subtitle">Install popular applications with just one click</p>
            </div>
            
            <div class="services-grid">
                <div class="cyber-card">
                    <i class="fab fa-wordpress service-icon"></i>
                    <h3 class="service-title">WORDPRESS</h3>
                    <p class="service-description">
                        World's most popular CMS with automatic updates and security features.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fab fa-joomla service-icon"></i>
                    <h3 class="service-title">JOOMLA</h3>
                    <p class="service-description">
                        Powerful CMS for complex websites with advanced user management.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fab fa-drupal service-icon"></i>
                    <h3 class="service-title">DRUPAL</h3>
                    <p class="service-description">
                        Enterprise-grade CMS for large-scale websites and applications.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fab fa-magento service-icon"></i>
                    <h3 class="service-title">MAGENTO</h3>
                    <p class="service-description">
                        Professional e-commerce platform for online stores and marketplaces.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-shopping-cart service-icon"></i>
                    <h3 class="service-title">OPENCART</h3>
                    <p class="service-description">
                        Easy-to-use e-commerce solution for small to medium businesses.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-comments service-icon"></i>
                    <h3 class="service-title">PHPBB</h3>
                    <p class="service-description">
                        Popular forum software for building online communities.
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
