<?php
// Components Usage Guide - Examples of how to use all modular components
// This file serves as documentation and examples for developers

/*
=============================================================================
HIVENEST MODULAR COMPONENTS USAGE GUIDE
=============================================================================

This file contains examples of how to use all the modular components 
created for the HiveNest website. Copy and modify these examples for your pages.

=============================================================================
1. HERO COMPONENTS
=============================================================================
*/

// HERO FULL (Homepage style with cyber orb)
/*
<?php
$hero_title = 'DIGITAL<br><span class="cyber-text">REVOLUTION</span><br>STARTS HERE';
$hero_subtitle = 'Break free from ordinary hosting. Enter the future of digital services.';
$hero_buttons = '<a href="#services" class="btn btn-primary">EXPLORE MATRIX</a><a href="#pricing" class="btn btn-secondary">VIEW SYSTEMS</a>';
$hero_image = 'assets/images/heroes/hero-cyberpunk-main.jpg';
$show_cyber_orb = true;
include 'utilities/hero-full.php';
?>
*/

// HERO MINIMAL (About/Contact style)
/*
<?php
$hero_title = 'OUR<br><span class="cyber-text">DIGITAL DNA</span>';
$hero_subtitle = 'Born in the digital underground, forged in quantum code.';
$hero_image = 'assets/images/heroes/hero-about-team.jpg';
include 'utilities/hero-minimal.php';
?>
*/

/*
=============================================================================
2. CARD COMPONENTS
=============================================================================
*/

// CYBER CARDS (Feature/Service cards)
/*
<?php
include 'utilities/cyber-cards.php';

// Single card
echo renderCyberCard(
    'fas fa-rocket',                    // Icon
    'QUANTUM SPEED',                    // Title
    '99.99% uptime with quantum-grade SSD arrays.', // Description
    'hosting/wordpress.php',            // Link (optional)
    'EXPLORE'                           // Link text (optional)
);

// Multiple cards from array
$features = [
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'FORTRESS SECURITY',
        'description' => 'Military-grade encryption and AI-powered threat detection.',
        'link' => 'tools/sitelock.php',
        'link_text' => 'ACTIVATE SHIELDS'
    ],
    [
        'icon' => 'fas fa-brain',
        'title' => 'AI INTELLIGENCE',
        'description' => 'Smart auto-scaling and neural network optimization.'
    ]
];

echo renderCyberCardsGrid($features);
?>
*/

// PRICING CARDS
/*
<?php
include 'utilities/pricing-cards.php';

$pricing_plans = [
    [
        'name' => 'INITIATE',
        'price' => '$5',
        'period' => '/mo',
        'features' => [
            '1 Digital Realm (Website)',
            '10GB Quantum Storage (SSD)',
            '100GB Data Transfer',
            'Free Neural Shield (SSL)'
        ],
        'cta_link' => 'contact.php',
        'cta_text' => 'BEGIN EVOLUTION'
    ],
    [
        'name' => 'WARRIOR',
        'price' => '$15',
        'period' => '/mo',
        'features' => [
            '10 Digital Realms (Websites)',
            '100GB Quantum Storage (SSD)',
            'Unlimited Data Transfer',
            'Advanced Neural Shield (SSL)',
            'Free Domain Portal (.com/.co.za)'
        ],
        'cta_link' => 'hosting/wordpress.php',
        'cta_text' => 'ASCEND TO WARRIOR',
        'featured' => true
    ]
];

echo renderPricingGrid($pricing_plans);
?>
*/

/*
=============================================================================
3. LAYOUT COMPONENTS
=============================================================================
*/

// GRID SECTION (Section with header and grid content)
/*
<?php
$grid_title = 'SYSTEM CAPABILITIES';
$grid_subtitle = 'Advanced protocols for digital domination';
$grid_content = renderCyberCardsGrid($features); // From above example
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include 'utilities/grid-section.php';
?>
*/

// TWO COLUMN SECTION
/*
<?php
$column1_content = '<h2>THE ORIGIN PROTOCOL</h2><p>Content for left column...</p>';
$column2_content = '<img src="image.jpg" alt="Image" style="width: 100%; border-radius: 20px;">';
$section_background = 'background: rgba(26, 26, 26, 0.5);';
include 'utilities/two-column-section.php';
?>
*/

// CTA SECTION (Call-to-action)
/*
<?php
$cta_title = 'READY TO TRANSCEND REALITY?';
$cta_subtitle = 'Join the digital revolution. Break free from ordinary.';
$cta_buttons = '<a href="contact.php" class="btn btn-primary">INITIALIZE SEQUENCE</a>';
include 'utilities/cta-section.php';
?>
*/

/*
=============================================================================
4. FORM COMPONENTS
=============================================================================
*/

// CONTACT FORM
/*
<?php
$form_title = 'SEND NEURAL TRANSMISSION';
$form_subtitle = 'Send us a message and we\'ll respond within the neural network timeframe.';
$form_id = 'contact-form';
include 'utilities/contact-form.php';
?>
*/

// DOMAIN SEARCH FORM
/*
<?php
$search_placeholder = 'Enter your domain name...';
$form_action = 'domains/cyber-scan.php';
$popular_extensions = ['.com', '.co.za', '.net', '.org', '.io', '.tech'];
include 'utilities/domain-search-form.php';
?>
*/

// NEWSLETTER FORM
/*
<?php
include 'utilities/newsletter-form.php';
echo renderNewsletterForm(
    'JOIN THE NEURAL NETWORK',
    'Get exclusive updates and cyberpunk insights.',
    'Enter your email to join the matrix...',
    'INITIALIZE CONNECTION'
);
?>
*/

/*
=============================================================================
5. INTERACTIVE COMPONENTS
=============================================================================
*/

// TABS
/*
<?php
include 'utilities/tabs.php';

$tabs = [
    [
        'title' => 'HOSTING PLANS',
        'icon' => 'fas fa-server',
        'content' => '<h3>Web Hosting</h3><p>Professional hosting solutions...</p>'
    ],
    [
        'title' => 'DOMAIN SERVICES', 
        'icon' => 'fas fa-globe',
        'content' => '<h3>Domain Registration</h3><p>Secure your digital identity...</p>'
    ],
    [
        'title' => 'SECURITY TOOLS',
        'icon' => 'fas fa-shield-alt', 
        'content' => '<h3>Cyber Protection</h3><p>Advanced security protocols...</p>'
    ]
];

echo renderTabs($tabs, 'services-tabs', 0);
?>
*/

// IMAGE GALLERY
/*
<?php
include 'utilities/image-gallery.php';

$images = [
    [
        'src' => 'assets/images/portfolio/project1.jpg',
        'alt' => 'Project 1',
        'title' => 'Cyberpunk Website',
        'description' => 'Full-stack web application with neural interfaces.'
    ],
    [
        'src' => 'assets/images/portfolio/project2.jpg',
        'alt' => 'Project 2', 
        'title' => 'Digital Identity',
        'description' => 'Brand identity design for tech startup.'
    ]
];

echo renderImageGallery($images, 'portfolio-gallery', 'grid', 3);
?>
*/

/*
=============================================================================
6. UTILITY COMPONENTS  
=============================================================================
*/

// BREADCRUMBS
/*
<?php
include 'utilities/breadcrumbs.php';

// Manual breadcrumbs
$breadcrumbs = [
    ['text' => 'Neural Domains', 'url' => 'main-services/domains.php'],
    ['text' => 'Register Domain', 'url' => null] // Current page (no URL)
];


// Auto-generated breadcrumbs
$breadcrumbs = generateBreadcrumbs([
    'domains' => 'Neural Domains',
    'register' => 'Register Domain'
]);
?>
*/

// PAGE HEADER
/*
<?php
$page_title = 'NEURAL DOMAINS';
$page_subtitle = 'Secure your digital identity across all dimensions';
$breadcrumbs = [
    ['text' => 'Services', 'url' => 'index.php#services'],
    ['text' => 'Neural Domains', 'url' => null]
];
include 'utilities/page-header.php';
?>
*/

// ALERTS
/*
<?php
include 'utilities/alerts.php';

echo showAlert('success', 'Domain registered successfully!');
echo showAlert('error', 'Registration failed. Please try again.');
echo showAlert('warning', 'Domain expires in 30 days.');
echo showAlert('info', 'New TLD extensions now available.');

// Auto-dismiss script
echo getAlertScript();
?>
*/

// LOADING STATES
/*
<?php
include 'utilities/loading-states.php';

echo renderLoader('spinner', 'Processing domain registration...', 'large');
echo renderLoader('pulse', 'Connecting to neural network...', 'medium'); 
echo renderLoader('dots', 'Loading quantum data...', 'small');

// CSS and JavaScript
echo getLoadingCSS();
echo getLoadingScript();
?>
*/

// MODAL DIALOGS
/*
<?php
include 'utilities/modal-dialogs.php';

$modal_content = '<p>Are you sure you want to register this domain?</p>';
$modal_footer = '<button class="btn btn-outline" onclick="closeModal(\'confirm-modal\')">Cancel</button>
                <button class="btn btn-primary" onclick="confirmRegistration()">Confirm</button>';

echo renderModal('confirm-modal', 'Confirm Registration', $modal_content, $modal_footer, 'medium');

// Modal scripts
echo getModalScript();
?>
*/

/*
=============================================================================
7. FORM VALIDATION & SECURITY
=============================================================================
*/

// FORM VALIDATION
/*
<?php
include 'utilities/form-validation.php';

if ($_POST) {
    $validator = new FormValidator($_POST);
    
    $validator->required('name')
             ->minLength('name', 2)
             ->required('email') 
             ->email('email')
             ->required('domain')
             ->domain('domain')
             ->required('message')
             ->minLength('message', 10);
    
    if ($validator->passes()) {
        $clean_data = $validator->getData();
        // Process form...
    } else {
        $errors = $validator->getErrors();
        // Show errors...
    }
}

// CSRF Protection
echo csrfInput();

// Rate limiting
if (!checkRateLimit($_SERVER['REMOTE_ADDR'], 5, 300)) {
    die('Rate limit exceeded. Please try again later.');
}

// Honeypot (spam protection)
echo honeypotField();
?>
*/

/*
=============================================================================
8. SEO & META TAGS
=============================================================================
*/

// SEO META TAGS
/*
<?php
include 'utilities/seo-meta.php';

$seo_config = [
    'title' => 'Domain Registration - HiveNest Matrix',
    'description' => 'Register your domain with quantum-encrypted security and instant activation.',
    'keywords' => 'domain registration, web domains, cyberpunk hosting',
    'image' => 'https://hivenest.co.za/assets/images/domains-og.jpg',
    'url' => 'https://hivenest.co.za/domains/register.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Domain Registration',
        'description' => 'Professional domain registration services',
        'serviceType' => 'Domain Services'
    ])
];

echo renderSEOMeta($seo_config);
?>
*/

/*
=============================================================================
COMPLETE PAGE TEMPLATE EXAMPLE
=============================================================================
*/

// Example: domains/register.php
/*
<?php
// Page variables
$current_page = 'domains';
$page_title = 'Register Neural Domain - HiveNest Matrix';
$page_description = 'Register your domain with quantum-encrypted security and instant activation protocols.';
$page_keywords = 'domain registration, neural domains, cyberpunk hosting';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'url' => 'https://hivenest.co.za/domains/register.php'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php 
include '../utilities/seo-meta.php';
echo renderSEOMeta($seo_config);
?>
</head>
<body>
<?php include '../utilities/nav.php'; ?>
<?php include '../utilities/mobile-menu.php'; ?>

<?php
// Hero section
$hero_title = 'REGISTER<br><span class="cyber-text">NEURAL DOMAIN</span>';
$hero_subtitle = 'Secure your digital identity across all dimensions with quantum-encrypted domain registration.';
$hero_image = 'assets/images/heroes/hero-email-global.jpg';
include '../utilities/hero-minimal.php';
?>

<?php
// Domain search form
$search_placeholder = 'Enter your desired domain name...';
$form_action = 'cyber-scan.php';
$popular_extensions = ['.com', '.co.za', '.net', '.org', '.io', '.tech'];
include '../utilities/domain-search-form.php';
?>

<?php
// Features section
include '../utilities/cyber-cards.php';
$domain_features = [
    [
        'icon' => 'fas fa-rocket',
        'title' => 'INSTANT ACTIVATION',
        'description' => 'Your domain goes live immediately after registration with quantum-speed propagation.'
    ],
    [
        'icon' => 'fas fa-shield-alt', 
        'title' => 'QUANTUM SECURITY',
        'description' => 'Military-grade encryption and privacy protection for your digital identity.'
    ],
    [
        'icon' => 'fas fa-globe',
        'title' => 'GLOBAL REACH',
        'description' => 'Access your domain from any dimension with our neural network infrastructure.'
    ]
];

$grid_title = 'DOMAIN PROTOCOLS';
$grid_subtitle = 'Advanced domain registration features';
$grid_content = renderCyberCardsGrid($domain_features);
include '../utilities/grid-section.php';
?>

<?php
// CTA section
$cta_title = 'READY TO CLAIM YOUR TERRITORY?';
$cta_subtitle = 'Register your neural domain and establish your digital presence.';
$cta_buttons = '<a href="#domain-search" class="btn btn-primary">START REGISTRATION</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
*/

// This usage guide helps developers quickly implement components
// Copy the examples above and modify them for your specific needs
?>