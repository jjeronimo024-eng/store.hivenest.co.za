<?php
// Page variables
$current_page = 'services';
$page_title = 'Email Services - Communication Matrix | HiveNest Matrix';
$page_description = 'Professional email hosting services including Google Workspace, Enterprise Email, and Cloud Mail solutions for businesses of all sizes.';
$page_keywords = 'email hosting, business email, google workspace, enterprise email, cloud mail, professional email';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-email-tech.jpg',
    'url' => 'https://hivenest.co.za/services/email-services.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Email Services',
        'description' => 'Professional email hosting and communication solutions',
        'serviceType' => 'Email and Communication Services'
    ])
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Services', 'url' => '../main-services/email.php'],
    ['text' => 'Email Services', 'url' => null]
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
$hero_title = '<span class="cyber-text">EMAIL</span><br>COMMUNICATION MATRIX';
$hero_subtitle = 'Professional email hosting services including Google Workspace, Enterprise Email, and Cloud Mail solutions for businesses of all sizes.';
$hero_image = '../assets/images/heroes/hero-email-tech.jpg';
$hero_alt = 'Email Communication Services';
include '../utilities/hero-minimal.php';
?>

<?php
// Breadcrumbs
include '../utilities/breadcrumbs.php';
?>

<?php
// Email Services Overview
include '../utilities/cyber-cards.php';
$email_services = [
    [
        'icon' => 'fab fa-google',
        'title' => 'GOOGLE WORKSPACE',
        'description' => 'Complete productivity suite with Gmail, Drive, Meet, Calendar, and collaboration tools for modern businesses.'
    ],
    [
        'icon' => 'fas fa-building',
        'title' => 'ENTERPRISE EMAIL',
        'description' => 'Advanced enterprise email hosting with unlimited storage, compliance features, and dedicated support.'
    ],
    [
        'icon' => 'fas fa-cloud',
        'title' => 'CLOUD MAIL',
        'description' => 'Scalable cloud email solutions with auto-scaling infrastructure and global delivery networks.'
    ],
    [
        'icon' => 'fas fa-mobile-alt',
        'title' => 'MOBILE EMAIL',
        'description' => 'Mobile-optimized email solutions with push notifications and offline access capabilities.'
    ],
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'SECURE EMAIL',
        'description' => 'End-to-end encrypted email hosting with advanced security features and compliance support.'
    ],
    [
        'icon' => 'fas fa-sync-alt',
        'title' => 'EMAIL MIGRATION',
        'description' => 'Professional email migration services from any existing provider with zero downtime guarantee.'
    ]
];

$grid_title = 'EMAIL SERVICES MATRIX';
$grid_subtitle = 'Complete communication solutions for digital enterprises';
$grid_content = renderCyberCardsGrid($email_services);
include '../utilities/grid-section.php';
?>

<!-- Email Solutions Comparison -->
<section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
    <div class="container">
        <div class="text-center mb-8">
            <h2>CHOOSE YOUR EMAIL DIMENSION</h2>
            <p class="hero-subtitle">Find the perfect email solution for your organization</p>
        </div>
        
        <div class="cyber-card" style="max-width: 1000px; margin: 0 auto;">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; color: rgba(255, 255, 255, 0.9);">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--cyber-neon-cyan);">
                            <th style="padding: 1rem; text-align: left; color: var(--cyber-neon-cyan);">EMAIL SOLUTION</th>
                            <th style="padding: 1rem; text-align: center; color: var(--cyber-neon-cyan);">BEST FOR</th>
                            <th style="padding: 1rem; text-align: center; color: var(--cyber-neon-cyan);">FEATURES</th>
                            <th style="padding: 1rem; text-align: center; color: var(--cyber-neon-cyan);">STARTING PRICE</th>
                            <th style="padding: 1rem; text-align: center; color: var(--cyber-neon-cyan);">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                            <td style="padding: 1rem; font-weight: bold;">Google Workspace</td>
                            <td style="padding: 1rem; text-align: center;">Small-Medium Teams</td>
                            <td style="padding: 1rem; text-align: center;">Gmail, Drive, Meet, Calendar</td>
                            <td style="padding: 1rem; text-align: center; color: var(--cyber-neon-green);">$6/user/mo</td>
                            <td style="padding: 1rem; text-align: center;">
                                <a href="../email/google-workspace.php" class="btn btn-primary" style="padding: 0.5rem 1rem;">Choose</a>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                            <td style="padding: 1rem; font-weight: bold;">Enterprise Email</td>
                            <td style="padding: 1rem; text-align: center;">Large Organizations</td>
                            <td style="padding: 1rem; text-align: center;">Unlimited Storage, Compliance</td>
                            <td style="padding: 1rem; text-align: center; color: var(--cyber-neon-green);">$8/user/mo</td>
                            <td style="padding: 1rem; text-align: center;">
                                <a href="../email/enterprise.php" class="btn btn-primary" style="padding: 0.5rem 1rem;">Choose</a>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                            <td style="padding: 1rem; font-weight: bold;">Cloud Mail</td>
                            <td style="padding: 1rem; text-align: center;">Scalable Solutions</td>
                            <td style="padding: 1rem; text-align: center;">Auto-scaling, Global CDN</td>
                            <td style="padding: 1rem; text-align: center; color: var(--cyber-neon-green);">$4/user/mo</td>
                            <td style="padding: 1rem; text-align: center;">
                                <a href="../email/cloud-mail.php" class="btn btn-primary" style="padding: 0.5rem 1rem;">Choose</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- Email Features -->
<section class="section">
    <div class="container">
        <div class="text-center mb-8">
            <h2>ADVANCED EMAIL FEATURES</h2>
            <p class="hero-subtitle">Professional email capabilities for modern businesses</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div class="service-card">
                <i class="fas fa-shield-virus service-icon"></i>
                <h3 class="service-title">ADVANCED SECURITY</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Multi-layer security with spam filtering, virus protection, 
                    and advanced threat detection systems.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-mobile-alt service-icon"></i>
                <h3 class="service-title">MOBILE SYNC</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Seamless synchronization across all devices with native 
                    mobile apps and push notifications.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-users service-icon"></i>
                <h3 class="service-title">COLLABORATION</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Advanced collaboration features including shared calendars, 
                    contacts, and team scheduling tools.
                </p>
            </div>

            <div class="service-card">
                <i class="fas fa-backup service-icon"></i>
                <h3 class="service-title">AUTO BACKUP</h3>
                <p style="color: rgba(255, 255, 255, 0.8);">
                    Automated daily backups with point-in-time recovery and 
                    geographic redundancy for data protection.
                </p>
            </div>
        </div>
    </div>
</section>

<?php
// CTA Section
$cta_title = 'READY TO UPGRADE YOUR COMMUNICATION SYSTEMS?';
$cta_subtitle = 'Choose the perfect email solution for your business and experience professional communication at quantum levels.';
$cta_buttons = '<a href="../contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">GET EMAIL HOSTING</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>