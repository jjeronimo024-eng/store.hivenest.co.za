<?php
// Page variables
$current_page = 'services';
$page_title = 'SSL Security Services - Digital Fortress | HiveNest Matrix';
$page_description = 'Professional SSL certificates and security services. Protect your website with quantum-level encryption and advanced security protocols.';
$page_keywords = 'ssl certificates, website security, digital security, cyberpunk security, encryption services';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-security-circuit.jpg',
    'url' => 'https://hivenest.co.za/services/ssl-security.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'SSL Security Services',
        'description' => 'Professional SSL certificates and website security services',
        'serviceType' => 'Security and SSL Services'
    ])
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Services', 'url' => '../main-services/tools.php'],
    ['text' => 'SSL Security', 'url' => null]
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
$hero_title = '<span class="cyber-text">SSL</span><br>DIGITAL FORTRESS';
$hero_subtitle = 'Professional SSL certificates and security services. Protect your website with quantum-level encryption and advanced security protocols.';
$hero_image = '../assets/images/heroes/hero-security-interface.jpg';
$hero_alt = 'SSL Security Protection';
include '../utilities/hero-minimal.php';
?>

<?php
// Breadcrumbs
include '../utilities/breadcrumbs.php';
?>

<?php
// SSL Security Services
include '../utilities/cyber-cards.php';
$security_services = [
    [
        'icon' => 'fas fa-certificate',
        'title' => 'SSL CERTIFICATES',
        'description' => 'Professional SSL certificates with 256-bit encryption, browser trust, and warranty protection for secure websites.'
    ],
    [
        'icon' => 'fas fa-shield-virus',
        'title' => 'MALWARE SCANNING',
        'description' => 'Advanced malware detection and removal with real-time monitoring and automatic threat response systems.'
    ],
    [
        'icon' => 'fas fa-user-shield',
        'title' => 'DDoS PROTECTION',
        'description' => 'Enterprise-grade DDoS protection with global mitigation network and quantum-level traffic filtering.'
    ],
    [
        'icon' => 'fas fa-lock',
        'title' => 'WEB APPLICATION FIREWALL',
        'description' => 'Advanced WAF protection filtering malicious traffic and protecting against OWASP Top 10 vulnerabilities.'
    ],
    [
        'icon' => 'fas fa-radar-alt',
        'title' => 'SECURITY MONITORING',
        'description' => '24/7 security monitoring with real-time alerts, incident response, and forensic analysis capabilities.'
    ],
    [
        'icon' => 'fas fa-backup',
        'title' => 'SECURITY BACKUPS',
        'description' => 'Automated security backups with versioning, encryption, and rapid restoration capabilities.'
    ]
];

$grid_title = 'SECURITY SERVICES MATRIX';
$grid_subtitle = 'Comprehensive digital security solutions for modern websites';
$grid_content = renderCyberCardsGrid($security_services);
include '../utilities/grid-section.php';
?>

<!-- SSL Certificate Types -->
<section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
    <div class="container">
        <div class="text-center mb-8">
            <h2>SSL CERTIFICATE TYPES</h2>
            <p class="hero-subtitle">Choose the right SSL certificate for your security needs</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div class="service-card">
                <i class="fas fa-certificate service-icon"></i>
                <h3 class="service-title">DOMAIN VALIDATION (DV)</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Quick validation process</li>
                    <li style="margin: 0.5rem 0;">◉ Issued in minutes</li>
                    <li style="margin: 0.5rem 0;">◉ 256-bit encryption</li>
                    <li style="margin: 0.5rem 0;">◉ $10,000 warranty</li>
                    <li style="margin: 0.5rem 0;">◉ Perfect for personal sites</li>
                </ul>
                <div style="margin-top: 2rem; text-align: center;">
                    <strong style="color: var(--cyber-neon-green); font-size: 1.2rem;">$49/year</strong>
                </div>
            </div>

            <div class="service-card featured">
                <i class="fas fa-building service-icon"></i>
                <h3 class="service-title">ORGANIZATION VALIDATION (OV)</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Organization verification</li>
                    <li style="margin: 0.5rem 0;">◉ Enhanced trust indicators</li>
                    <li style="margin: 0.5rem 0;">◉ 256-bit encryption</li>
                    <li style="margin: 0.5rem 0;">◉ $100,000 warranty</li>
                    <li style="margin: 0.5rem 0;">◉ Ideal for businesses</li>
                </ul>
                <div style="margin-top: 2rem; text-align: center;">
                    <strong style="color: var(--cyber-neon-green); font-size: 1.2rem;">$149/year</strong>
                </div>
            </div>

            <div class="service-card">
                <i class="fas fa-crown service-icon"></i>
                <h3 class="service-title">EXTENDED VALIDATION (EV)</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Highest trust level</li>
                    <li style="margin: 0.5rem 0;">◉ Green address bar</li>
                    <li style="margin: 0.5rem 0;">◉ 256-bit encryption</li>
                    <li style="margin: 0.5rem 0;">◉ $1,000,000 warranty</li>
                    <li style="margin: 0.5rem 0;">◉ Enterprise grade</li>
                </ul>
                <div style="margin-top: 2rem; text-align: center;">
                    <strong style="color: var(--cyber-neon-green); font-size: 1.2rem;">$399/year</strong>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Security Features -->
<section class="section">
    <div class="container">
        <div class="text-center mb-8">
            <h2>ADVANCED SECURITY FEATURES</h2>
            <p class="hero-subtitle">Comprehensive protection for your digital assets</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div class="service-card">
                <i class="fas fa-globe service-icon"></i>
                <h3 class="service-title">GLOBAL TRUST</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Certificates trusted by 99.9% of browsers worldwide with 
                    compatibility across all devices and platforms.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-tachometer-alt service-icon"></i>
                <h3 class="service-title">INSTANT ISSUANCE</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Automated validation and instant certificate issuance for 
                    DV certificates with immediate website protection.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-sync-alt service-icon"></i>
                <h3 class="service-title">AUTO RENEWAL</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Automatic certificate renewal with seamless updates and 
                    zero downtime for continuous protection.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-headset service-icon"></i>
                <h3 class="service-title">24/7 SUPPORT</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Round-the-clock technical support with SSL experts for 
                    installation, troubleshooting, and optimization.
                </p>
            </div>
        </div>
    </div>
</section>

<?php
// CTA Section
$cta_title = 'READY TO SECURE YOUR DIGITAL FORTRESS?';
$cta_subtitle = 'Protect your website and customer data with professional SSL certificates and advanced security services.';
$cta_buttons = '<a href="../contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">GET SSL CERTIFICATE</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>