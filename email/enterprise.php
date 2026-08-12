<?php
// Include required utilities first
include '../utilities/seo-meta.php';
include_once '../utilities/dynamic_pricing.php';

// Page variables
$current_page = 'email';
$page_title = 'Enterprise Email - Professional Communication | HiveNest Matrix';
$page_description = 'Enterprise Email hosting with advanced security, compliance, unlimited storage, and professional support for large organizations.';
$page_keywords = 'enterprise email, professional email hosting, business email, secure email, cyberpunk email solutions';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-email-workspace.jpg',
    'url' => 'https://hivenest.co.za/email/enterprise.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Enterprise Email Hosting',
        'description' => 'Professional enterprise email hosting with advanced security and compliance features',
        'serviceType' => 'Enterprise Email Services'
    ])
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Comm Arrays', 'url' => '../main-services/email.php'],
    ['text' => 'Enterprise Email', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = "
function addToCart(planId, price) {
    const planNames = {
        'enterprise-basic': 'Enterprise Email - Basic',
        'enterprise-pro': 'Enterprise Email - Professional',
        'enterprise-ultimate': 'Enterprise Email - Ultimate'
    };
    const fallbackName = planId.split('--').pop().replace(/-/g, ' ').toUpperCase();
    
    if (window.shoppingCart) {
        window.shoppingCart.addItem({
            id: planId,
            name: planNames[planId] || fallbackName,
            price: price,
            type: 'email'
        });
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
<?php echo renderSEOMeta($seo_config); ?>
</head>
<body>
<?php include '../utilities/nav.php'; ?>
<?php include '../utilities/mobile-menu.php'; ?>

<?php
// Hero Section
$hero_title = '<span class="cyber-text">ENTERPRISE</span><br>EMAIL MATRIX';
$hero_subtitle = 'Professional enterprise email hosting with advanced security, compliance, unlimited storage, and 24/7 support for large organizations.';
$hero_image = '../assets/images/heroes/hero-email-satellite.jpg';
$hero_alt = 'Enterprise Email Communication';
include '../utilities/hero-minimal.php';
?>

<?php
// Breadcrumbs
include '../utilities/breadcrumbs.php';
?>

<?php
// Enterprise Email Plans
include '../utilities/pricing-cards.php';
$enterprise_plans = [
    [
        'name' => 'ENTERPRISE BASIC',
        'price' => '$8',
        'period' => '/user/mo',
        'features' => [
            '50GB Email Storage',
            'Custom Domain Email',
            'Active Directory Integration',
            'Basic Security Features',
            'Webmail Access',
            'Mobile Device Support',
            'Standard Support (9-5)'
        ],
        'cta_link' => '#',
        'cta_text' => 'START ENTERPRISE',
        'onclick' => 'addToCart(\'enterprise-basic\', 8)'
    ],
    [
        'name' => 'ENTERPRISE PRO',
        'price' => '$15',
        'period' => '/user/mo',
        'features' => [
            'Unlimited Email Storage',
            'Advanced Security Suite',
            'Compliance & Archiving',
            'Exchange Server Access',
            'Mobile Device Management',
            'SharePoint Integration',
            'Priority Support (24/7)',
            'Data Loss Prevention'
        ],
        'cta_link' => '#',
        'cta_text' => 'MOST POPULAR',
        'onclick' => 'addToCart(\'enterprise-pro\', 15)',
        'featured' => true
    ],
    [
        'name' => 'ENTERPRISE ULTIMATE',
        'price' => '$25',
        'period' => '/user/mo',
        'features' => [
            'Unlimited Everything',
            'Advanced Threat Protection',
            'Compliance Suite (HIPAA/SOX)',
            'Custom Integrations',
            'Dedicated Account Manager',
            'On-Premise Hybrid Options',
            'White Glove Migration',
            'Custom SLA Agreement'
        ],
        'cta_link' => '#',
        'cta_text' => 'ULTIMATE POWER',
        'onclick' => 'addToCart(\'enterprise-ultimate\', 25)'
    ]
];

$grid_title = 'ENTERPRISE EMAIL POWER LEVELS';
$grid_subtitle = 'Professional email solutions for enterprise organizations';
$enterprise_plans = loadProductPricingPlans([
    'product_id'     => 22,
    'product_slug'   => 'enterprise-email',
    'cart_function'  => 'addToCart',
    'fallback_plans' => $enterprise_plans,
]);
$grid_content = renderPricingGrid($enterprise_plans);
include '../utilities/grid-section.php';
?>

<?php
// Enterprise Features
include '../utilities/cyber-cards.php';
$enterprise_features = [
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'ADVANCED SECURITY',
        'description' => 'Multi-layer security with encryption, anti-spam, anti-virus, and advanced threat protection against cyber attacks.'
    ],
    [
        'icon' => 'fas fa-balance-scale',
        'title' => 'COMPLIANCE READY',
        'description' => 'Built-in compliance features for HIPAA, SOX, GDPR with automated archiving and legal hold capabilities.'
    ],
    [
        'icon' => 'fas fa-database',
        'title' => 'UNLIMITED STORAGE',
        'description' => 'Unlimited email storage with high-performance SSD arrays and automatic backup systems across multiple data centers.'
    ],
    [
        'icon' => 'fas fa-mobile-alt',
        'title' => 'MOBILE DEVICE MGMT',
        'description' => 'Complete mobile device management with remote wipe, policy enforcement, and secure container technology.'
    ],
    [
        'icon' => 'fas fa-sync-alt',
        'title' => 'ACTIVE DIRECTORY',
        'description' => 'Seamless integration with existing Active Directory infrastructure for single sign-on and user management.'
    ],
    [
        'icon' => 'fas fa-headset',
        'title' => '24/7 ENTERPRISE SUPPORT',
        'description' => 'Dedicated enterprise support team with guaranteed response times and escalation procedures.'
    ]
];

$grid_title = 'ENTERPRISE NEURAL FEATURES';
$grid_subtitle = 'Advanced features designed for enterprise-level organizations';
$grid_content = renderCyberCardsGrid($enterprise_features);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<!-- Security & Compliance Section -->
<section class="section">
    <div class="container">
        <div class="text-center mb-8">
            <h2>SECURITY & COMPLIANCE</h2>
            <p class="hero-subtitle">Enterprise-grade security and compliance features</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div class="service-card">
                <i class="fas fa-lock service-icon"></i>
                <h3 class="service-title">DATA PROTECTION</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ End-to-end encryption</li>
                    <li style="margin: 0.5rem 0;">◉ Data loss prevention</li>
                    <li style="margin: 0.5rem 0;">◉ Rights management</li>
                    <li style="margin: 0.5rem 0;">◉ Secure email gateways</li>
                </ul>
            </div>

            <div class="service-card">
                <i class="fas fa-file-contract service-icon"></i>
                <h3 class="service-title">COMPLIANCE</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ HIPAA compliance</li>
                    <li style="margin: 0.5rem 0;">◉ SOX compliance</li>
                    <li style="margin: 0.5rem 0;">◉ GDPR compliance</li>
                    <li style="margin: 0.5rem 0;">◉ Custom compliance rules</li>
                </ul>
            </div>

            <div class="service-card">
                <i class="fas fa-archive service-icon"></i>
                <h3 class="service-title">ARCHIVING & BACKUP</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Automated archiving</li>
                    <li style="margin: 0.5rem 0;">◉ Legal hold management</li>
                    <li style="margin: 0.5rem 0;">◉ eDiscovery tools</li>
                    <li style="margin: 0.5rem 0;">◉ Point-in-time recovery</li>
                </ul>
            </div>

            <div class="service-card">
                <i class="fas fa-user-shield service-icon"></i>
                <h3 class="service-title">ACCESS CONTROL</h3>
                <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.8);">
                    <li style="margin: 0.5rem 0;">◉ Multi-factor authentication</li>
                    <li style="margin: 0.5rem 0;">◉ Single sign-on (SSO)</li>
                    <li style="margin: 0.5rem 0;">◉ Role-based permissions</li>
                    <li style="margin: 0.5rem 0;">◉ Conditional access policies</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- Enterprise Services -->
<section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
    <div class="container">
        <div class="text-center mb-8">
            <h2>ENTERPRISE SERVICES</h2>
            <p class="hero-subtitle">Comprehensive enterprise email and collaboration services</p>
        </div>
        
        <div class="cyber-card" style="max-width: 800px; margin: 0 auto;">
            <div style="text-align: center; margin-bottom: 2rem;">
                <i class="fas fa-building" style="font-size: 3rem; color: var(--cyber-neon-purple); margin-bottom: 1rem;"></i>
                <h3 style="color: var(--cyber-neon-purple); margin-bottom: 1rem;">DEDICATED ENTERPRISE TEAM</h3>
                <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 2rem;">
                    Your organization gets a dedicated account manager, technical team, 
                    and custom solutions tailored to your specific business requirements.
                </p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                <div style="text-align: center;">
                    <i class="fas fa-user-tie" style="font-size: 2rem; color: var(--cyber-neon-green); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-green); margin-bottom: 0.5rem;">ACCOUNT MANAGER</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Dedicated relationship management</p>
                </div>
                
                <div style="text-align: center;">
                    <i class="fas fa-tools" style="font-size: 2rem; color: var(--cyber-neon-green); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-green); margin-bottom: 0.5rem;">TECHNICAL TEAM</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Expert technical support and consulting</p>
                </div>
                
                <div style="text-align: center;">
                    <i class="fas fa-cogs" style="font-size: 2rem; color: var(--cyber-neon-green); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-green); margin-bottom: 0.5rem;">CUSTOM SOLUTIONS</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Tailored to your business needs</p>
                </div>
            </div>
            
            <div style="text-align: center;">
                <a href="../contact.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                    CONTACT ENTERPRISE TEAM
                </a>
            </div>
        </div>
    </div>
</section>

<?php
// CTA Section
$cta_title = 'READY FOR ENTERPRISE-LEVEL EMAIL COMMUNICATION?';
$cta_subtitle = 'Contact our enterprise team for a custom solution designed for your organization\'s specific needs and requirements.';
$cta_buttons = '<a href="../contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">REQUEST CONSULTATION</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
