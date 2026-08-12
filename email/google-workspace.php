<?php
// Include required utilities
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/dynamic_pricing.php';

// Page variables
$current_page = 'email';
$page_title = 'Google Workspace - Professional Collaboration | HiveNest Matrix';
$page_description = 'Google Workspace - Professional email and collaboration suite with Gmail, Drive, Meet, and Calendar for enhanced productivity and team collaboration.';
$page_keywords = 'google workspace, professional email, gmail business, collaboration suite, cyberpunk email hosting';

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Comm Arrays', 'url' => '../main-services/email.php'],
    ['text' => 'Google Workspace', 'url' => null]
];

// Get pricing: cache → DB (getProductPricingById) → DB by slug → empty
$workspace_plans = loadProductPricingPlans([
    'product_id'    => 23,
    'product_slug'  => 'google-workspace',
    'cart_function' => 'addToCart',
]);

// Page-specific JavaScript
$page_scripts = "
function addToCart(planId, price) {
    const planNames = {
        'workspace-starter': 'Google Workspace - Business Starter',
        'workspace-standard': 'Google Workspace - Business Standard',
        'workspace-plus': 'Google Workspace - Business Plus'
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
<?php 
include '../utilities/head.php'; 
include_once '../utilities/seo-meta.php';

// SEO configuration (after including seo-meta.php)
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-email-workspace.jpg',
    'url' => 'https://hivenest.co.za/email/google-workspace.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Google Workspace',
        'description' => 'Professional Google Workspace email and collaboration services',
        'serviceType' => 'Email and Collaboration Services'
    ])
];
echo renderSEOMeta($seo_config);
?>
</head>
<body>
<?php include '../utilities/nav.php'; ?>
<?php include '../utilities/mobile-menu.php'; ?>

<?php
// Hero Section
$hero_title = '<span class="cyber-text">GOOGLE</span><br>WORKSPACE';
$hero_subtitle = 'Professional email and collaboration suite with Gmail, Drive, Meet, and Calendar. Boost productivity with Google\'s enterprise tools.';
$hero_image = '../assets/images/heroes/hero-email-workspace.jpg';
$hero_alt = 'Google Workspace Collaboration';
include '../utilities/hero-minimal.php';
?>

<?php
// Breadcrumbs
?>

<?php
// Google Workspace Plans - Retrieved from database above
include '../utilities/pricing-cards.php';

$grid_title = 'GOOGLE WORKSPACE PLANS';
$grid_subtitle = 'Professional email and collaboration for every business';
$grid_content = renderPricingGrid($workspace_plans);
include '../utilities/grid-section.php';
?>

<?php
// Google Workspace Features
include '../utilities/cyber-cards.php';
$workspace_features = [
    [
        'icon' => 'fab fa-google',
        'title' => 'GMAIL BUSINESS',
        'description' => 'Professional email with your domain. Advanced spam filtering, 99.9% uptime, and mobile device management.'
    ],
    [
        'icon' => 'fab fa-google-drive',
        'title' => 'GOOGLE DRIVE',
        'description' => 'Cloud storage and file sharing with real-time collaboration. Access files from anywhere, anytime, on any device.'
    ],
    [
        'icon' => 'fas fa-video',
        'title' => 'GOOGLE MEET',
        'description' => 'Video conferencing for up to 500 participants. Recording, screen sharing, and integration with Calendar.'
    ],
    [
        'icon' => 'fas fa-calendar',
        'title' => 'GOOGLE CALENDAR',
        'description' => 'Shared calendars for team scheduling. Resource booking, meeting rooms, and automatic meeting scheduling.'
    ],
    [
        'icon' => 'fas fa-file-alt',
        'title' => 'DOCS & SHEETS',
        'description' => 'Create and edit documents, spreadsheets, and presentations. Real-time collaboration and version control.'
    ],
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'ENTERPRISE SECURITY',
        'description' => 'Advanced security with 2-factor authentication, SSO, and comprehensive admin controls.'
    ]
];

$grid_title = 'WORKSPACE FEATURES';
$grid_subtitle = 'Complete productivity suite for modern businesses';
$grid_content = renderCyberCardsGrid($workspace_features);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<?php
// CTA Section
$cta_title = 'READY TO BOOST PRODUCTIVITY WITH GOOGLE WORKSPACE?';
$cta_subtitle = 'Start your free trial today and experience the power of professional collaboration tools designed for the digital future.';
$cta_buttons = '<a href="../contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">START FREE TRIAL</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
