<?php
// Page variables
$current_page = 'services';
$page_title = 'Domain Services - Neural Network Portals | HiveNest Matrix';
$page_description = 'Professional domain registration, management, and transfer services. Secure your digital identity across all dimensions with our neural domain network.';
$page_keywords = 'domain registration, domain transfer, domain management, cyberpunk domains, neural domains';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-email-global.jpg',
    'url' => 'https://hivenest.co.za/services/domains.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Domain Services',
        'description' => 'Professional domain registration and management services',
        'serviceType' => 'Domain Registration Services'
    ])
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Services', 'url' => '../main-services/domains.php'],
    ['text' => 'Domain Services', 'url' => null]
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
$hero_title = '<span class="cyber-text">DOMAIN</span><br>NEURAL NETWORK';
$hero_subtitle = 'Professional domain registration, management, and transfer services. Secure your digital identity across all dimensions with our neural domain network.';
$hero_image = '../assets/images/heroes/hero-domain-server-blue.jpg';
$hero_alt = 'Domain Network Infrastructure';
include '../utilities/hero-minimal.php';
?>

<?php
// Breadcrumbs
include '../utilities/breadcrumbs.php';
?>

<?php
// Domain Services Overview
include '../utilities/cyber-cards.php';
$domain_services = [
    [
        'icon' => 'fas fa-plus-circle',
        'title' => 'DOMAIN REGISTRATION',
        'description' => 'Register new domains from 500+ TLD options with instant activation and global DNS network integration.'
    ],
    [
        'icon' => 'fas fa-exchange-alt',
        'title' => 'DOMAIN TRANSFER',
        'description' => 'Seamless domain transfers with free WhoisGuard privacy protection and expert migration assistance.'
    ],
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'DOMAIN PROTECTION',
        'description' => 'Advanced domain security with SSL certificates, DNSSEC protection, and malware monitoring.'
    ],
    [
        'icon' => 'fas fa-search',
        'title' => 'DOMAIN LOOKUP',
        'description' => 'Advanced domain search tools with AI-powered name suggestions and availability monitoring.'
    ],
    [
        'icon' => 'fas fa-cog',
        'title' => 'DNS MANAGEMENT',
        'description' => 'Professional DNS management with global nameservers, advanced records, and monitoring tools.'
    ]
];

$grid_title = 'DOMAIN SERVICES MATRIX';
$grid_subtitle = 'Complete domain management solutions for digital empires';
$grid_content = renderCyberCardsGrid($domain_services);
include '../utilities/grid-section.php';
?>

<!-- Popular Domain Extensions -->
<section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
    <div class="container">
        <div class="text-center mb-8">
            <h2>POPULAR DOMAIN EXTENSIONS</h2>
            <p class="hero-subtitle">Choose from 500+ domain extensions for your digital identity</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
            <div class="service-card">
                <h3 class="service-title">CLASSIC EXTENSIONS</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; text-align: left; margin-top: 1rem;">
                    <span style="color: var(--cyber-neon-cyan);">.com</span>
                    <span style="color: rgba(255, 255, 255, 0.8);">$12.99/yr</span>
                    <span style="color: var(--cyber-neon-cyan);">.net</span>
                    <span style="color: rgba(255, 255, 255, 0.8);">$14.99/yr</span>
                    <span style="color: var(--cyber-neon-cyan);">.org</span>
                    <span style="color: rgba(255, 255, 255, 0.8);">$13.99/yr</span>
                    <span style="color: var(--cyber-neon-cyan);">.info</span>
                    <span style="color: rgba(255, 255, 255, 0.8);">$15.99/yr</span>
                </div>
            </div>

            <div class="service-card">
                <h3 class="service-title">BUSINESS EXTENSIONS</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; text-align: left; margin-top: 1rem;">
                    <span style="color: var(--cyber-neon-green);">.biz</span>
                    <span style="color: rgba(255, 255, 255, 0.8);">$16.99/yr</span>
                    <span style="color: var(--cyber-neon-green);">.company</span>
                    <span style="color: rgba(255, 255, 255, 0.8);">$19.99/yr</span>
                    <span style="color: var(--cyber-neon-green);">.corp</span>
                    <span style="color: rgba(255, 255, 255, 0.8);">$24.99/yr</span>
                    <span style="color: var(--cyber-neon-green);">.llc</span>
                    <span style="color: rgba(255, 255, 255, 0.8);">$29.99/yr</span>
                </div>
            </div>

            <div class="service-card">
                <h3 class="service-title">TECH EXTENSIONS</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; text-align: left; margin-top: 1rem;">
                    <span style="color: var(--cyber-neon-pink);">.tech</span>
                    <span style="color: rgba(255, 255, 255, 0.8);">$39.99/yr</span>
                    <span style="color: var(--cyber-neon-pink);">.ai</span>
                    <span style="color: rgba(255, 255, 255, 0.8);">$99.99/yr</span>
                    <span style="color: var(--cyber-neon-pink);">.io</span>
                    <span style="color: rgba(255, 255, 255, 0.8);">$49.99/yr</span>
                    <span style="color: var(--cyber-neon-pink);">.dev</span>
                    <span style="color: rgba(255, 255, 255, 0.8);">$19.99/yr</span>
                </div>
            </div>

            <div class="service-card">
                <h3 class="service-title">CREATIVE EXTENSIONS</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; text-align: left; margin-top: 1rem;">
                    <span style="color: var(--cyber-neon-orange);">.design</span>
                    <span style="color: rgba(255, 255, 255, 0.8);">$34.99/yr</span>
                    <span style="color: var(--cyber-neon-orange);">.art</span>
                    <span style="color: rgba(255, 255, 255, 0.8);">$19.99/yr</span>
                    <span style="color: var(--cyber-neon-orange);">.studio</span>
                    <span style="color: rgba(255, 255, 255, 0.8);">$24.99/yr</span>
                    <span style="color: var(--cyber-neon-orange);">.media</span>
                    <span style="color: rgba(255, 255, 255, 0.8);">$29.99/yr</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Domain Management Tools -->
<section class="section">
    <div class="container">
        <div class="text-center mb-8">
            <h2>ADVANCED DOMAIN TOOLS</h2>
            <p class="hero-subtitle">Professional domain management and monitoring tools</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div class="service-card">
                <i class="fas fa-brain service-icon"></i>
                <h3 class="service-title">AI NAME GENERATOR</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Intelligent domain name suggestions based on keywords, industry, 
                    and brand preferences with availability checking.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-radar-alt service-icon"></i>
                <h3 class="service-title">DOMAIN MONITORING</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Monitor domain availability, track expiration dates, and receive 
                    alerts for domains you're interested in acquiring.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-shield-virus service-icon"></i>
                <h3 class="service-title">SECURITY SCANNING</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Comprehensive security scanning for malware, blacklist status, 
                    and reputation monitoring across global security networks.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-chart-bar service-icon"></i>
                <h3 class="service-title">DOMAIN ANALYTICS</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Detailed analytics including traffic estimates, SEO metrics, 
                    and competitive analysis for informed domain decisions.
                </p>
            </div>
        </div>
    </div>
</section>

<?php
// CTA Section
$cta_title = 'READY TO CLAIM YOUR DIGITAL TERRITORY?';
$cta_subtitle = 'Register your perfect domain name today and establish your presence across all digital dimensions with our neural domain network.';
$cta_buttons = '<a href="../domains/register.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">REGISTER DOMAIN</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
