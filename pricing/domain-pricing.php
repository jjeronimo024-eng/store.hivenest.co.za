<?php
// Load database helper to fetch pricing
require_once '../utilities/db_helper.php';
$db = new DatabaseHelper();

// Fetch domain products from database
$domain_products = $db->getProductsByType('domain');

// Create a mapping for easy access
$products_map = [];
foreach ($domain_products as $product) {
    $products_map[$product['slug']] = $product;
}

// Page variables
$current_page = 'domains';
$page_title = 'Domain Pricing - Neural Domains | HiveNest';
$page_description = 'Transparent domain pricing for all TLD extensions. Register your neural domain across all digital dimensions with quantum-level security.';
$page_keywords = 'domain pricing, TLD extensions, neural domains, domain registration pricing, cyberpunk domains';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-domain-server-blue.jpg',
    'url' => 'https://hivenest.co.za/pricing/domain-pricing.php',
    'type' => 'pricing'
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Domain Pricing', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = "
function registerDomain(productSlug, productName, price) {
    if (window.shoppingCart) {
        window.shoppingCart.addItem({
            id: productSlug,
            name: productName,
            price: parseFloat(price),
            type: 'domain'
        });
        
        // Show success message
        console.log(productName + ' added to cart!');
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

    <!-- Hero Section -->
    <section class="hero">
        <img src="assets/images/heroes/hero-domain-server-blue.jpg" alt="Domain Pricing" class="hero-background">
        
        <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title">
                    NEURAL<br>
                    <span class="cyber-text">DOMAIN</span><br>
                    PRICING
                </h1>
                <p class="hero-subtitle">
                    Transparent domain pricing for all TLD extensions. Register your neural domain 
                    across all digital dimensions with quantum-level security.
                </p>
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                    <a href="#popular" class="btn btn-primary">POPULAR DOMAINS</a>
                    <a href="#extensions" class="btn btn-secondary">ALL EXTENSIONS</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Popular Domains -->
    <section id="popular" class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>POPULAR NEURAL DOMAINS</h2>
                <p class="hero-subtitle">Most requested domain extensions across the matrix</p>
            </div>
            
            <div class="cyber-card" style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: rgba(255, 255, 255, 0.1);">
                            <th style="padding: 1rem; text-align: left; color: var(--cyber-neon-cyan); border-bottom: 1px solid rgba(255, 255, 255, 0.2);">Domain Extension</th>
                            <th style="padding: 1rem; text-align: center; color: var(--cyber-neon-cyan); border-bottom: 1px solid rgba(255, 255, 255, 0.2);">Registration</th>
                            <th style="padding: 1rem; text-align: center; color: var(--cyber-neon-cyan); border-bottom: 1px solid rgba(255, 255, 255, 0.2);">Renewal</th>
                            <th style="padding: 1rem; text-align: center; color: var(--cyber-neon-cyan); border-bottom: 1px solid rgba(255, 255, 255, 0.2);">Transfer</th>
                            <th style="padding: 1rem; text-align: center; color: var(--cyber-neon-cyan); border-bottom: 1px solid rgba(255, 255, 255, 0.2);">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 1rem; color: rgba(255, 255, 255, 0.9); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                                <strong>.com</strong> - Global Standard
                            </td>
                            <td style="padding: 1rem; text-align: center; color: var(--cyber-neon-green); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">$12.99</td>
                            <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">$14.99</td>
                            <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">$12.99</td>
                            <td style="padding: 1rem; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                                <a href="../domains/register.php" class="btn btn-primary" style="font-size: 0.9rem; padding: 0.5rem 1rem;">Register</a>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 1rem; color: rgba(255, 255, 255, 0.9); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                                <strong>.co.za</strong> - South African
                            </td>
                            <td style="padding: 1rem; text-align: center; color: var(--cyber-neon-green); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">$9.99</td>
                            <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">$11.99</td>
                            <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">$9.99</td>
                            <td style="padding: 1rem; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                                <a href="../domains/register.php" class="btn btn-primary" style="font-size: 0.9rem; padding: 0.5rem 1rem;">Register</a>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 1rem; color: rgba(255, 255, 255, 0.9); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                                <strong>.net</strong> - Network Services
                            </td>
                            <td style="padding: 1rem; text-align: center; color: var(--cyber-neon-green); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">$14.99</td>
                            <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">$16.99</td>
                            <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">$14.99</td>
                            <td style="padding: 1rem; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                                <a href="../domains/register.php" class="btn btn-primary" style="font-size: 0.9rem; padding: 0.5rem 1rem;">Register</a>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 1rem; color: rgba(255, 255, 255, 0.9); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                                <strong>.org</strong> - Organizations
                            </td>
                            <td style="padding: 1rem; text-align: center; color: var(--cyber-neon-green); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">$13.99</td>
                            <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">$15.99</td>
                            <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">$13.99</td>
                            <td style="padding: 1rem; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                                <a href="../domains/register.php" class="btn btn-primary" style="font-size: 0.9rem; padding: 0.5rem 1rem;">Register</a>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 1rem; color: rgba(255, 255, 255, 0.9); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                                <strong>.info</strong> - Information
                            </td>
                            <td style="padding: 1rem; text-align: center; color: var(--cyber-neon-green); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">$11.99</td>
                            <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">$13.99</td>
                            <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">$11.99</td>
                            <td style="padding: 1rem; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                                <a href="../domains/register.php" class="btn btn-primary" style="font-size: 0.9rem; padding: 0.5rem 1rem;">Register</a>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 1rem; color: rgba(255, 255, 255, 0.9);">
                                <strong>.tech</strong> - Technology
                            </td>
                            <td style="padding: 1rem; text-align: center; color: var(--cyber-neon-green);">$19.99</td>
                            <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8);">$21.99</td>
                            <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8);">$19.99</td>
                            <td style="padding: 1rem; text-align: center;">
                                <a href="../domains/register.php" class="btn btn-primary" style="font-size: 0.9rem; padding: 0.5rem 1rem;">Register</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- All Extensions -->
    <section id="extensions" class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
        <div class="container">
            <div class="text-center mb-8">
                <h2>ALL NEURAL EXTENSIONS</h2>
                <p class="hero-subtitle">Complete list of available domain extensions</p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                <!-- Business Domains -->
                <div class="cyber-card">
                    <h3 class="service-title">BUSINESS DOMAINS</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: rgba(255, 255, 255, 0.1);">
                                    <th style="padding: 0.5rem; text-align: left; color: var(--cyber-neon-cyan); font-size: 0.9rem;">Extension</th>
                                    <th style="padding: 0.5rem; text-align: center; color: var(--cyber-neon-cyan); font-size: 0.9rem;">Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="padding: 0.5rem; color: rgba(255, 255, 255, 0.9); font-size: 0.9rem;">.business</td>
                                    <td style="padding: 0.5rem; text-align: center; color: var(--cyber-neon-green); font-size: 0.9rem;">$24.99</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0.5rem; color: rgba(255, 255, 255, 0.9); font-size: 0.9rem;">.company</td>
                                    <td style="padding: 0.5rem; text-align: center; color: var(--cyber-neon-green); font-size: 0.9rem;">$29.99</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0.5rem; color: rgba(255, 255, 255, 0.9); font-size: 0.9rem;">.corp</td>
                                    <td style="padding: 0.5rem; text-align: center; color: var(--cyber-neon-green); font-size: 0.9rem;">$49.99</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0.5rem; color: rgba(255, 255, 255, 0.9); font-size: 0.9rem;">.shop</td>
                                    <td style="padding: 0.5rem; text-align: center; color: var(--cyber-neon-green); font-size: 0.9rem;">$39.99</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0.5rem; color: rgba(255, 255, 255, 0.9); font-size: 0.9rem;">.store</td>
                                    <td style="padding: 0.5rem; text-align: center; color: var(--cyber-neon-green); font-size: 0.9rem;">$59.99</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tech Domains -->
                <div class="cyber-card">
                    <h3 class="service-title">TECH DOMAINS</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: rgba(255, 255, 255, 0.1);">
                                    <th style="padding: 0.5rem; text-align: left; color: var(--cyber-neon-cyan); font-size: 0.9rem;">Extension</th>
                                    <th style="padding: 0.5rem; text-align: center; color: var(--cyber-neon-cyan); font-size: 0.9rem;">Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="padding: 0.5rem; color: rgba(255, 255, 255, 0.9); font-size: 0.9rem;">.tech</td>
                                    <td style="padding: 0.5rem; text-align: center; color: var(--cyber-neon-green); font-size: 0.9rem;">$19.99</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0.5rem; color: rgba(255, 255, 255, 0.9); font-size: 0.9rem;">.app</td>
                                    <td style="padding: 0.5rem; text-align: center; color: var(--cyber-neon-green); font-size: 0.9rem;">$17.99</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0.5rem; color: rgba(255, 255, 255, 0.9); font-size: 0.9rem;">.dev</td>
                                    <td style="padding: 0.5rem; text-align: center; color: var(--cyber-neon-green); font-size: 0.9rem;">$15.99</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0.5rem; color: rgba(255, 255, 255, 0.9); font-size: 0.9rem;">.ai</td>
                                    <td style="padding: 0.5rem; text-align: center; color: var(--cyber-neon-green); font-size: 0.9rem;">$89.99</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0.5rem; color: rgba(255, 255, 255, 0.9); font-size: 0.9rem;">.cloud</td>
                                    <td style="padding: 0.5rem; text-align: center; color: var(--cyber-neon-green); font-size: 0.9rem;">$24.99</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Creative Domains -->
                <div class="cyber-card">
                    <h3 class="service-title">CREATIVE DOMAINS</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: rgba(255, 255, 255, 0.1);">
                                    <th style="padding: 0.5rem; text-align: left; color: var(--cyber-neon-cyan); font-size: 0.9rem;">Extension</th>
                                    <th style="padding: 0.5rem; text-align: center; color: var(--cyber-neon-cyan); font-size: 0.9rem;">Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="padding: 0.5rem; color: rgba(255, 255, 255, 0.9); font-size: 0.9rem;">.design</td>
                                    <td style="padding: 0.5rem; text-align: center; color: var(--cyber-neon-green); font-size: 0.9rem;">$49.99</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0.5rem; color: rgba(255, 255, 255, 0.9); font-size: 0.9rem;">.art</td>
                                    <td style="padding: 0.5rem; text-align: center; color: var(--cyber-neon-green); font-size: 0.9rem;">$24.99</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0.5rem; color: rgba(255, 255, 255, 0.9); font-size: 0.9rem;">.photo</td>
                                    <td style="padding: 0.5rem; text-align: center; color: var(--cyber-neon-green); font-size: 0.9rem;">$34.99</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0.5rem; color: rgba(255, 255, 255, 0.9); font-size: 0.9rem;">.media</td>
                                    <td style="padding: 0.5rem; text-align: center; color: var(--cyber-neon-green); font-size: 0.9rem;">$39.99</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0.5rem; color: rgba(255, 255, 255, 0.9); font-size: 0.9rem;">.studio</td>
                                    <td style="padding: 0.5rem; text-align: center; color: var(--cyber-neon-green); font-size: 0.9rem;">$29.99</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Domain Features -->
    <section class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>DOMAIN FEATURES</h2>
                <p class="hero-subtitle">What's included with every neural domain</p>
            </div>
            
            <div class="services-grid">
                <div class="cyber-card">
                    <i class="fas fa-shield-alt service-icon"></i>
                    <h3 class="service-title">DOMAIN PRIVACY</h3>
                    <p class="service-description">
                        Free WHOIS privacy protection to keep your personal information secure.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-sync-alt service-icon"></i>
                    <h3 class="service-title">AUTO-RENEWAL</h3>
                    <p class="service-description">
                        Automatic domain renewal to prevent accidental expiration.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-cog service-icon"></i>
                    <h3 class="service-title">DNS MANAGEMENT</h3>
                    <p class="service-description">
                        Easy-to-use DNS management panel with advanced record types.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-exchange-alt service-icon"></i>
                    <h3 class="service-title">FREE TRANSFERS</h3>
                    <p class="service-description">
                        Free domain transfers from other registrars with no downtime.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-envelope service-icon"></i>
                    <h3 class="service-title">EMAIL FORWARDING</h3>
                    <p class="service-description">
                        Free email forwarding with unlimited aliases and redirects.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-headset service-icon"></i>
                    <h3 class="service-title">24/7 SUPPORT</h3>
                    <p class="service-description">
                        Expert support for all domain-related questions and issues.
                    </p>
                </div>
            </div>
        </div>
    </section>

<?php include '../utilities/footer.php'; ?>

<?php include '../utilities/scripts.php'; ?>
</body>
</html>
