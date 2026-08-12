<?php
// Include required utilities first
include '../utilities/seo-meta.php';

// Page variables
$current_page = 'services';
$page_title = 'Web Hosting Services - Quantum Infrastructure | HiveNest Matrix';
$page_description = 'Professional web hosting services with quantum-level performance, security, and reliability. Complete hosting solutions for businesses of all sizes.';
$page_keywords = 'web hosting, quantum hosting, professional hosting, cyberpunk hosting, business hosting';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-domain-server-blue.jpg',
    'url' => 'https://hivenest.co.za/services/web-hosting.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Web Hosting Services',
        'description' => 'Professional web hosting with quantum-level performance and security',
        'serviceType' => 'Web Hosting Services'
    ])
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Services', 'url' => '../main-services/hosting.php'],
    ['text' => 'Web Hosting', 'url' => null]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include '../utilities/head.php'; ?>
<?php echo renderSEOMeta($seo_config); ?>
</head>
<body>
<?php include '../utilities/nav.php'; ?>
<?php include '../utilities/mobile-menu.php'; ?>

<?php
// Hero Section
$hero_title = '<span class="cyber-text">WEB</span><br>HOSTING MATRIX';
$hero_subtitle = 'Professional web hosting services with quantum-level performance, security, and reliability. Complete hosting solutions for businesses of all sizes.';
$hero_image = '../assets/images/heroes/hero-domain-server-green.jpg';
$hero_alt = 'Web Hosting Infrastructure';
include '../utilities/hero-minimal.php';
?>

<?php
// Breadcrumbs
include '../utilities/breadcrumbs.php';
?>

<?php
// Hosting Service Overview
include '../utilities/cyber-cards.php';
$hosting_services = [
    [
        'icon' => 'fab fa-wordpress',
        'title' => 'WORDPRESS HOSTING',
        'description' => 'Optimized WordPress hosting with neural caching, auto-updates, and quantum-level security for content creators.'
    ],
    [
        'icon' => 'fab fa-linux',
        'title' => 'LINUX SHARED HOSTING',
        'description' => 'Reliable Linux hosting with cPanel, unlimited bandwidth, and 99.9% uptime guarantee for websites and applications.'
    ],
    [
        'icon' => 'fab fa-windows',
        'title' => 'WINDOWS HOSTING',
        'description' => 'Professional Windows hosting with ASP.NET support, MSSQL databases, and Plesk control panel for .NET applications.'
    ],
    [
        'icon' => 'fas fa-cloud',
        'title' => 'CLOUD HOSTING',
        'description' => 'Scalable cloud infrastructure with auto-scaling, load balancing, and global CDN for maximum performance.'
    ],
    [
        'icon' => 'fas fa-server',
        'title' => 'DEDICATED SERVERS',
        'description' => 'Dedicated server solutions with full root access, custom configurations, and enterprise-grade hardware.'
    ],
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'MANAGED HOSTING',
        'description' => 'Fully managed hosting solutions with 24/7 monitoring, maintenance, and expert technical support.'
    ]
];

$grid_title = 'HOSTING SERVICES MATRIX';
$grid_subtitle = 'Comprehensive web hosting solutions for every need';
$grid_content = renderCyberCardsGrid($hosting_services);
include '../utilities/grid-section.php';
?>

<!-- Performance Features -->
<section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
    <div class="container">
        <div class="text-center mb-8">
            <h2>QUANTUM PERFORMANCE FEATURES</h2>
            <p class="hero-subtitle">Advanced hosting features for optimal performance</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div class="service-card">
                <i class="fas fa-tachometer-alt service-icon"></i>
                <h3 class="service-title">SSD STORAGE</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Ultra-fast SSD storage arrays with NVMe technology providing 10x faster 
                    data access speeds compared to traditional hard drives.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-globe service-icon"></i>
                <h3 class="service-title">GLOBAL CDN</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Worldwide content delivery network with edge locations across 
                    multiple continents for maximum speed and availability.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-shield-virus service-icon"></i>
                <h3 class="service-title">SECURITY SUITE</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Comprehensive security including SSL certificates, malware scanning, 
                    DDoS protection, and automated backup systems.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-chart-line service-icon"></i>
                <h3 class="service-title">99.9% UPTIME</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Guaranteed 99.9% uptime SLA with redundant infrastructure, 
                    load balancing, and 24/7 network monitoring.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Hosting Comparison -->
<section class="section">
    <div class="container">
        <div class="text-center mb-8">
            <h2>CHOOSE YOUR HOSTING DIMENSION</h2>
            <p class="hero-subtitle">Find the perfect hosting solution for your project</p>
        </div>
        
        <div class="cyber-card" style="max-width: 1000px; margin: 0 auto;">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; color: rgba(255, 255, 255, 0.9);">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--cyber-neon-cyan);">
                            <th style="padding: 1rem; text-align: left; color: var(--cyber-neon-cyan);">HOSTING TYPE</th>
                            <th style="padding: 1rem; text-align: center; color: var(--cyber-neon-cyan);">BEST FOR</th>
                            <th style="padding: 1rem; text-align: center; color: var(--cyber-neon-cyan);">STARTING PRICE</th>
                            <th style="padding: 1rem; text-align: center; color: var(--cyber-neon-cyan);">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                            <td style="padding: 1rem; font-weight: bold;">WordPress Hosting</td>
                            <td style="padding: 1rem; text-align: center;">Blogs & Content Sites</td>
                            <td style="padding: 1rem; text-align: center; color: var(--cyber-neon-green);">$7/mo</td>
                            <td style="padding: 1rem; text-align: center;">
                                <a href="../hosting/wordpress.php" class="btn btn-primary" style="padding: 0.5rem 1rem;">Choose</a>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                            <td style="padding: 1rem; font-weight: bold;">Linux Shared</td>
                            <td style="padding: 1rem; text-align: center;">Small-Medium Websites</td>
                            <td style="padding: 1rem; text-align: center; color: var(--cyber-neon-green);">$5/mo</td>
                            <td style="padding: 1rem; text-align: center;">
                                <a href="../hosting/linux-shared.php" class="btn btn-primary" style="padding: 0.5rem 1rem;">Choose</a>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                            <td style="padding: 1rem; font-weight: bold;">Windows Hosting</td>
                            <td style="padding: 1rem; text-align: center;">.NET Applications</td>
                            <td style="padding: 1rem; text-align: center; color: var(--cyber-neon-green);">$8/mo</td>
                            <td style="padding: 1rem; text-align: center;">
                                <a href="../hosting/windows.php" class="btn btn-primary" style="padding: 0.5rem 1rem;">Choose</a>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                            <td style="padding: 1rem; font-weight: bold;">Cloud Hosting</td>
                            <td style="padding: 1rem; text-align: center;">Scalable Applications</td>
                            <td style="padding: 1rem; text-align: center; color: var(--cyber-neon-green);">$49/mo</td>
                            <td style="padding: 1rem; text-align: center;">
                                <a href="../hosting/cloud-hosting.php" class="btn btn-primary" style="padding: 0.5rem 1rem;">Choose</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php
// CTA Section
$cta_title = 'READY TO POWER YOUR DIGITAL PRESENCE?';
$cta_subtitle = 'Choose the perfect hosting solution for your website and experience quantum-level performance and reliability.';
$cta_buttons = '<a href="../contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">GET STARTED</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>