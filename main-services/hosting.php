<?php
// Page variables
$current_page = 'hosting';
$page_title = 'Quantum Servers - Advanced Hosting Matrix | HiveNest';
$page_description = 'Complete hosting solutions including WordPress, Windows, Linux shared hosting, and dedicated servers with quantum-grade performance.';
$page_keywords = 'quantum hosting, wordpress hosting, windows hosting, linux hosting, dedicated servers, cyberpunk hosting';

// Load database helper for dynamic pricing
require_once '../utilities/db_helper.php';

// Get all hosting products from database
$hosting_products = $db_helper->getProductsByType('hosting');

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-about-team.jpg',
    'url' => 'https://hivenest.co.za/main-services/hosting.php',
    'type' => 'service'
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Quantum Servers', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = "
function selectPlan(planName, price) {
    if (window.shoppingCart) {
        window.shoppingCart.addItem({
            id: 'hosting-' + planName.toLowerCase().replace(' ', '-'),
            name: 'Hosting Plan: ' + planName,
            price: price,
            type: 'hosting'
        });
    }
    
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
        <img src="assets/images/heroes/hero-about-team.jpg" alt="Quantum Servers" class="hero-background">
        
        <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title">
                    <span class="cyber-text">QUANTUM</span><br>
                    SERVERS
                </h1>
                <p class="hero-subtitle">
                    Advanced hosting solutions with quantum-grade performance. WordPress, Windows, Linux shared hosting, 
                    and dedicated servers powered by next-generation infrastructure.
                </p>
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                    <a href="../hosting/wordpress.php" class="btn btn-primary">WORDPRESS HOSTING</a>
                    <a href="../servers/windows.php" class="btn btn-secondary">DEDICATED SERVERS</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Hosting Services -->
    <section class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>HOSTING SERVICES</h2>
                <p class="hero-subtitle">Complete hosting solutions for every need</p>
            </div>
            
            <div class="services-grid">
                <div class="service-card">
                    <i class="fab fa-wordpress service-icon"></i>
                    <h3 class="service-title">WORDPRESS HOSTING</h3>
                    <p class="service-description">
                        Optimized WordPress hosting with automatic updates, security monitoring, and performance optimization.
                    </p>
                    <div style="margin-top: 2rem;">
                        <a href="../hosting/wordpress.php" class="btn btn-primary">EXPLORE WORDPRESS</a>
                    </div>
                </div>

                <div class="service-card">
                    <i class="fab fa-windows service-icon"></i>
                    <h3 class="service-title">WINDOWS SHARED</h3>
                    <p class="service-description">
                        Professional Windows hosting with ASP.NET, MSSQL, and IIS support for .NET applications.
                    </p>
                    <div style="margin-top: 2rem;">
                        <a href="../hosting/windows.php" class="btn btn-primary">WINDOWS HOSTING</a>
                    </div>
                </div>

                <div class="service-card">
                    <i class="fab fa-linux service-icon"></i>
                    <h3 class="service-title">LINUX SHARED</h3>
                    <p class="service-description">
                        Reliable Linux hosting with PHP, MySQL, and Apache support for modern web applications.
                    </p>
                    <div style="margin-top: 2rem;">
                        <a href="../hosting/linux-shared.php" class="btn btn-primary">LINUX HOSTING</a>
                    </div>
                </div>

                <div class="service-card">
                    <i class="fas fa-server service-icon"></i>
                    <h3 class="service-title">WINDOWS DEDICATED</h3>
                    <p class="service-description">
                        Dedicated Windows servers with full administrative control and enterprise-grade performance.
                    </p>
                    <div style="margin-top: 2rem;">
                        <a href="../servers/windows.php" class="btn btn-primary">WINDOWS SERVERS</a>
                    </div>
                </div>

                <div class="service-card">
                    <i class="fas fa-microchip service-icon"></i>
                    <h3 class="service-title">LINUX DEDICATED</h3>
                    <p class="service-description">
                        High-performance Linux dedicated servers with root access and custom configurations.
                    </p>
                    <div style="margin-top: 2rem;">
                        <a href="../servers/linux-dedicated.php" class="btn btn-primary">LINUX SERVERS</a>
                    </div>
                </div>

                <div class="service-card">
                    <i class="fas fa-cloud service-icon"></i>
                    <h3 class="service-title">CLOUD MATRIX</h3>
                    <p class="service-description">
                        Scalable cloud hosting with auto-scaling, load balancing, and parallel universe redundancy.
                    </p>
                    <div style="margin-top: 2rem;">
                        <a href="../hosting/cloud-hosting.php" class="btn btn-primary">CLOUD HOSTING</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Hosting Features -->
    <section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
        <div class="container">
            <div class="text-center mb-8">
                <h2>QUANTUM FEATURES</h2>
                <p class="hero-subtitle">Advanced hosting capabilities</p>
            </div>
            
            <div class="services-grid">
                <div class="cyber-card">
                    <i class="fas fa-rocket service-icon"></i>
                    <h3 class="service-title">QUANTUM SPEED</h3>
                    <p class="service-description">
                        SSD storage, advanced caching, and global CDN for lightning-fast performance.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-shield-alt service-icon"></i>
                    <h3 class="service-title">FORTRESS SECURITY</h3>
                    <p class="service-description">
                        Advanced security protocols, DDoS protection, and real-time malware scanning.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-clock service-icon"></i>
                    <h3 class="service-title">99.9% UPTIME</h3>
                    <p class="service-description">
                        Redundant infrastructure and proactive monitoring ensure maximum availability.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-certificate service-icon"></i>
                    <h3 class="service-title">FREE SSL</h3>
                    <p class="service-description">
                        Complimentary SSL certificates for all domains to secure your websites.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-sync service-icon"></i>
                    <h3 class="service-title">DAILY BACKUPS</h3>
                    <p class="service-description">
                        Automated daily backups with instant restoration capabilities.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-headset service-icon"></i>
                    <h3 class="service-title">24/7 SUPPORT</h3>
                    <p class="service-description">
                        Expert support team available around the clock for technical assistance.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Popular Plans -->
    <section class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>POPULAR HOSTING PLANS</h2>
                <p class="hero-subtitle">Choose your hosting power level</p>
            </div>
            
            <div class="pricing-grid">
                <?php if (count($hosting_products) > 0): ?>
                    <?php foreach ($hosting_products as $index => $product): ?>
                        <?php 
                        $card_class = 'pricing-card';
                        $color_var = '--cyber-neon-cyan';
                        $btn_class = 'btn-primary';
                        $link_url = '../hosting/linux-shared.php';
                        
                        if ($product['is_featured'] == 1 && $index == 1) {
                            $card_class .= ' featured';
                            $color_var = '--cyber-neon-green';
                            $btn_class = 'btn-secondary';
                            $link_url = '../hosting/wordpress.php';
                        } elseif ($index == 2) {
                            $color_var = '--cyber-neon-pink';
                            $link_url = '../servers/windows.php';
                        }
                        
                        $display_name = strtoupper(str_replace(['Cyber ', 'Digital ', ' Hosting'], ['', '', ''], $product['name']));
                        ?>
                        <div class="<?php echo $card_class; ?>">
                            <div class="pricing-plan"><?php echo htmlspecialchars($display_name); ?></div>
                            <div class="pricing-amount">$<?php echo number_format($product['base_price'], 0); ?><span style="font-size: 1rem;">/mo</span></div>
                            <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                                <?php if (!empty($product['features']) && is_array($product['features'])): ?>
                                    <?php foreach ($product['features'] as $feature): ?>
                                        <li style="margin: 0.5rem 0; color: var(<?php echo $color_var; ?>);">◉ <?php echo htmlspecialchars($feature); ?></li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                            <a href="<?php echo $link_url; ?>" class="btn <?php echo $btn_class; ?>" style="width: 100%;">
                                <?php echo $index == 1 ? 'MOST POPULAR' : ($index == 2 ? 'ENTERPRISE LEVEL' : 'START HOSTING'); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Fallback if no products found -->
                    <div class="pricing-card">
                        <div class="pricing-plan">INITIATE</div>
                        <div class="pricing-amount">$6<span style="font-size: 1rem;">/mo</span></div>
                        <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ 1 Website</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ 10GB SSD Storage</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ 100GB Bandwidth</li>
                        </ul>
                        <a href="../hosting/linux-shared.php" class="btn btn-primary" style="width: 100%;">START HOSTING</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

<?php include '../utilities/footer.php'; ?>

<?php include '../utilities/scripts.php'; ?>
</body>
</html>