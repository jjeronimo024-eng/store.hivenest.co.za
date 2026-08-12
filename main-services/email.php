<?php
// Page variables
$current_page = 'email';
$page_title = 'Comm Arrays - Enterprise Email Solutions | HiveNest';
$page_description = 'Comm Arrays - Quantum-encrypted communication systems with Google Workspace integration and enterprise-grade email hosting.';
$page_keywords = 'email hosting, google workspace, enterprise email, communication systems, cyberpunk email';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-email-network.jpg',
    'url' => 'https://hivenest.co.za/main-services/email.php',
    'type' => 'service'
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Comm Arrays', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = "
function selectEmailPlan(planName, price) {
    if (window.shoppingCart) {
        window.shoppingCart.addItem({
            id: 'email-' + planName.toLowerCase().replace(' ', '-'),
            name: 'Email Plan: ' + planName,
            price: price,
            type: 'email'
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
        <img src="assets/images/heroes/hero-email-network.jpg" alt="Communication Networks" class="hero-background">
        
        <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title">
                    COMM<br>
                    <span class="cyber-text">ARRAYS</span><br>
                    SECURE CHANNELS
                </h1>
                <p class="hero-subtitle">
                    Quantum-encrypted communication systems with Google Workspace integration 
                    and enterprise-grade email hosting across multiple digital dimensions.
                </p>
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                    <a href="#solutions" class="btn btn-primary">SECURE CHANNEL</a>
                    <a href="#pricing" class="btn btn-secondary">VIEW ARRAYS</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Email Solutions -->
    <section id="solutions" class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>COMMUNICATION PROTOCOLS</h2>
                <p class="hero-subtitle">Choose your enterprise communication platform</p>
            </div>
            
            <div class="services-grid">
                <div class="cyber-card">
                    <i class="fab fa-google service-icon" style="color: var(--cyber-neon-green); font-size: 4rem;"></i>
                    <h3 class="service-title">GOOGLE WORKSPACE</h3>
                    <p class="service-description">
                        Complete productivity suite with Gmail, Drive, Docs, Sheets, and Meet. 
                        Neural integration with advanced AI collaboration protocols.
                    </p>
                    <div style="margin: 1rem 0;">
                        <div style="color: var(--cyber-neon-green); font-weight: bold;">Starting at $9.99/user/mo</div>
                    </div>
                    <a href="../email/google-workspace.php" class="btn btn-primary">DEPLOY WORKSPACE</a>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-envelope service-icon" style="color: var(--cyber-neon-cyan); font-size: 4rem;"></i>
                    <h3 class="service-title">ENTERPRISE EMAIL</h3>
                    <p class="service-description">
                        Professional email hosting with unlimited storage and advanced security. 
                        IMAP/POP3 support with quantum-level spam protection.
                    </p>
                    <div style="margin: 1rem 0;">
                        <div style="color: var(--cyber-neon-cyan); font-weight: bold;">Starting at $2.99/user/mo</div>
                    </div>
                    <a href="../email/enterprise.php" class="btn btn-primary">ACTIVATE EMAIL</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Email Pricing Plans -->
    <section id="pricing" class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
        <div class="container">
            <div class="text-center mb-8">
                <h2>COMMUNICATION TIERS</h2>
                <p class="hero-subtitle">Select your enterprise communication level</p>
            </div>
            
            <div class="pricing-grid">
                <!-- Basic Email -->
                <div class="pricing-card">
                    <div class="pricing-plan">NEURAL COMM</div>
                    <div class="pricing-amount">$2.99<span style="font-size: 1rem;">/user/mo</span></div>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ 50GB Neural Storage</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Custom Domain Email</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ IMAP/POP3/SMTP Access</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Anti-Spam Protection</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Webmail Interface</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Mobile Sync</li>
                    </ul>
                    <a href="../contact.php" class="btn btn-primary" style="width: 100%;">ACTIVATE COMM</a>
                </div>

                <!-- Google Workspace -->
                <div class="pricing-card featured">
                    <div class="pricing-plan">GOOGLE WORKSPACE</div>
                    <div class="pricing-amount">$9.99<span style="font-size: 1rem;">/user/mo</span></div>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ 30GB Neural Storage</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Gmail Professional</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Google Drive Integration</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Docs, Sheets, Slides</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Meet Video Conferencing</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Calendar Integration</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Mobile Apps Suite</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Admin Console</li>
                    </ul>
                    <a href="../contact.php" class="btn btn-secondary" style="width: 100%;">DEPLOY WORKSPACE</a>
                </div>

                <!-- Enterprise Email -->
                <div class="pricing-card">
                    <div class="pricing-plan">ENTERPRISE COMM</div>
                    <div class="pricing-amount">$17.99<span style="font-size: 1rem;">/user/mo</span></div>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Unlimited Neural Storage</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Advanced Email Features</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Exchange Compatibility</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Advanced Security</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Backup & Archiving</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ White-label Options</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ API Integration</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Priority Support</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ SLA Guarantee</li>
                    </ul>
                    <a href="../contact.php" class="btn btn-primary" style="width: 100%;">ACHIEVE ENTERPRISE</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Email Features -->
    <section class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>COMMUNICATION FEATURES</h2>
                <p class="hero-subtitle">Advanced email capabilities across all digital platforms</p>
            </div>
            
            <div class="services-grid">
                <div class="cyber-card">
                    <i class="fas fa-shield-alt service-icon"></i>
                    <h3 class="service-title">QUANTUM SECURITY</h3>
                    <p class="service-description">
                        Advanced encryption and anti-spam protection with neural threat detection algorithms.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-mobile-alt service-icon"></i>
                    <h3 class="service-title">MOBILE SYNC</h3>
                    <p class="service-description">
                        Seamless synchronization across all devices with real-time push notifications.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-archive service-icon"></i>
                    <h3 class="service-title">AUTO ARCHIVING</h3>
                    <p class="service-description">
                        Automated email archiving and backup with cross-dimensional storage redundancy.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-users service-icon"></i>
                    <h3 class="service-title">COLLABORATION</h3>
                    <p class="service-description">
                        Advanced collaboration tools with shared calendars and contact management.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-cog service-icon"></i>
                    <h3 class="service-title">ADMIN CONTROL</h3>
                    <p class="service-description">
                        Comprehensive admin panel with user management and security controls.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-chart-line service-icon"></i>
                    <h3 class="service-title">ANALYTICS</h3>
                    <p class="service-description">
                        Detailed email analytics and reporting with neural pattern recognition.
                    </p>
                </div>
            </div>
        </div>
    </section>

<?php include '../utilities/footer.php'; ?>

<?php include '../utilities/scripts.php'; ?>
</body>
</html>