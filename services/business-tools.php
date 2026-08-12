<?php
// Page variables
$current_page = 'services';
$page_title = 'Business Tools - Digital Arsenal | HiveNest Matrix';
$page_description = 'Professional business tools and productivity solutions. Advanced neural systems for project management, automation, and business optimization.';
$page_keywords = 'business tools, productivity tools, project management, business automation, cyberpunk business solutions';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-marketing-seo.jpg',
    'url' => 'https://hivenest.co.za/services/business-tools.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Business Tools and Solutions',
        'description' => 'Professional business tools and productivity solutions for modern enterprises',
        'serviceType' => 'Business Tools and Productivity Services'
    ])
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Services', 'url' => '../main-services/tools.php'],
    ['text' => 'Business Tools', 'url' => null]
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
$hero_title = '<span class="cyber-text">BUSINESS</span><br>DIGITAL ARSENAL';
$hero_subtitle = 'Professional business tools and productivity solutions. Advanced neural systems for project management, automation, and business optimization.';
$hero_image = '../assets/images/heroes/hero-branding-workspace.jpg';
$hero_alt = 'Business Tools Dashboard';
include '../utilities/hero-minimal.php';
?>

<?php
// Breadcrumbs
include '../utilities/breadcrumbs.php';
?>

<?php
// Business Tools Overview
include '../utilities/cyber-cards.php';
$business_tools = [
    [
        'icon' => 'fas fa-tasks',
        'title' => 'PROJECT MANAGEMENT',
        'description' => 'Advanced project management systems with neural task automation, team collaboration, and quantum-level efficiency tracking.'
    ],
    [
        'icon' => 'fas fa-chart-bar',
        'title' => 'ANALYTICS DASHBOARD',
        'description' => 'Comprehensive business analytics with AI-powered insights, predictive modeling, and real-time performance monitoring.'
    ],
    [
        'icon' => 'fas fa-robot',
        'title' => 'WORKFLOW AUTOMATION',
        'description' => 'Intelligent workflow automation systems that eliminate repetitive tasks and optimize business processes.'
    ],
    [
        'icon' => 'fas fa-users',
        'title' => 'TEAM COLLABORATION',
        'description' => 'Advanced collaboration platforms with video conferencing, file sharing, and synchronized project workspaces.'
    ],
    [
        'icon' => 'fas fa-money-bill-wave',
        'title' => 'FINANCIAL MANAGEMENT',
        'description' => 'Integrated financial tools with invoicing, expense tracking, payroll management, and tax optimization.'
    ],
    [
        'icon' => 'fas fa-phone-alt',
        'title' => 'COMMUNICATION SYSTEMS',
        'description' => 'Unified communication platforms with VoIP, messaging, video calls, and customer relationship management.'
    ]
];

$grid_title = 'BUSINESS TOOLS MATRIX';
$grid_subtitle = 'Comprehensive digital solutions for business optimization';
$grid_content = renderCyberCardsGrid($business_tools);
include '../utilities/grid-section.php';
?>

<!-- Tool Categories -->
<section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
    <div class="container">
        <div class="text-center mb-8">
            <h2>NEURAL PRODUCTIVITY CATEGORIES</h2>
            <p class="hero-subtitle">Specialized tools for every aspect of business operations</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div class="service-card">
                <i class="fas fa-cogs service-icon"></i>
                <h3 class="service-title">OPERATIONS</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Process automation</li>
                    <li style="margin: 0.5rem 0;">◉ Inventory management</li>
                    <li style="margin: 0.5rem 0;">◉ Supply chain optimization</li>
                    <li style="margin: 0.5rem 0;">◉ Quality control systems</li>
                </ul>
            </div>

            <div class="service-card">
                <i class="fas fa-users-cog service-icon"></i>
                <h3 class="service-title">HUMAN RESOURCES</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Employee management</li>
                    <li style="margin: 0.5rem 0;">◉ Recruitment systems</li>
                    <li style="margin: 0.5rem 0;">◉ Performance tracking</li>
                    <li style="margin: 0.5rem 0;">◉ Training & development</li>
                </ul>
            </div>

            <div class="service-card">
                <i class="fas fa-chart-line service-icon"></i>
                <h3 class="service-title">SALES & MARKETING</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ CRM systems</li>
                    <li style="margin: 0.5rem 0;">◉ Lead generation</li>
                    <li style="margin: 0.5rem 0;">◉ Campaign management</li>
                    <li style="margin: 0.5rem 0;">◉ Sales analytics</li>
                </ul>
            </div>

            <div class="service-card">
                <i class="fas fa-calculator service-icon"></i>
                <h3 class="service-title">FINANCE & ACCOUNTING</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Bookkeeping automation</li>
                    <li style="margin: 0.5rem 0;">◉ Invoice management</li>
                    <li style="margin: 0.5rem 0;">◉ Financial reporting</li>
                    <li style="margin: 0.5rem 0;">◉ Tax preparation</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- Integration Ecosystem -->
<section class="section">
    <div class="container">
        <div class="text-center mb-8">
            <h2>INTEGRATION ECOSYSTEM</h2>
            <p class="hero-subtitle">Seamless integration with your existing business systems</p>
        </div>
        
        <div class="cyber-card" style="max-width: 800px; margin: 0 auto;">
            <div style="text-align: center; margin-bottom: 2rem;">
                <i class="fas fa-network-wired" style="font-size: 3rem; color: var(--cyber-neon-blue); margin-bottom: 1rem;"></i>
                <h3 style="color: var(--cyber-neon-blue); margin-bottom: 1rem;">UNIFIED BUSINESS PLATFORM</h3>
                <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 2rem;">
                    All your business tools connected through a single, powerful interface 
                    with real-time data synchronization and cross-platform compatibility.
                </p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                <div style="text-align: center;">
                    <i class="fab fa-microsoft" style="font-size: 2rem; color: var(--cyber-neon-green); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-green); margin-bottom: 0.5rem;">MICROSOFT 365</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Office apps and cloud services</p>
                </div>
                
                <div style="text-align: center;">
                    <i class="fab fa-google" style="font-size: 2rem; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;">GOOGLE WORKSPACE</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Gmail, Drive, and productivity suite</p>
                </div>
                
                <div style="text-align: center;">
                    <i class="fas fa-credit-card" style="font-size: 2rem; color: var(--cyber-neon-pink); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-pink); margin-bottom: 0.5rem;">PAYMENT SYSTEMS</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Stripe, PayPal, and banking APIs</p>
                </div>
                
                <div style="text-align: center;">
                    <i class="fas fa-cloud" style="font-size: 2rem; color: var(--cyber-neon-orange); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-orange); margin-bottom: 0.5rem;">CLOUD STORAGE</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">AWS, Azure, and Google Cloud</p>
                </div>
            </div>
            
            <div style="text-align: center;">
                <a href="../contact.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                    EXPLORE INTEGRATIONS
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Custom Solutions -->
<section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
    <div class="container">
        <div class="text-center mb-8">
            <h2>CUSTOM BUSINESS SOLUTIONS</h2>
            <p class="hero-subtitle">Tailored tools designed specifically for your business needs</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div class="service-card">
                <i class="fas fa-code service-icon"></i>
                <h3 class="service-title">CUSTOM DEVELOPMENT</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Bespoke software solutions developed specifically for your unique 
                    business processes and operational requirements.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-plug service-icon"></i>
                <h3 class="service-title">API INTEGRATIONS</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Custom API integrations connecting your existing systems 
                    with new tools for seamless data flow and automation.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-mobile-alt service-icon"></i>
                <h3 class="service-title">MOBILE SOLUTIONS</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Native and cross-platform mobile applications for field teams, 
                    customer service, and business management on the go.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-graduation-cap service-icon"></i>
                <h3 class="service-title">TRAINING & SUPPORT</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Comprehensive training programs and ongoing support to ensure 
                    maximum adoption and efficiency of your business tools.
                </p>
            </div>
        </div>
    </div>
</section>

<?php
// CTA Section
$cta_title = 'READY TO OPTIMIZE YOUR BUSINESS OPERATIONS?';
$cta_subtitle = 'Transform your business with advanced digital tools and automation systems designed for the future of enterprise operations.';
$cta_buttons = '<a href="../contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">GET BUSINESS TOOLS</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>