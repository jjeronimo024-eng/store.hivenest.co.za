<?php
// Page variables
$current_page = 'tools';
$page_title = 'Cyber Arsenal - Security & Backup Tools | HiveNest';
$page_description = 'Cyber Arsenal - Advanced security protocols including SSL certificates, CodeGuard backups, SiteLock protection, and Acronis cyber backup systems.';
$page_keywords = 'cyber security, ssl certificates, backup solutions, website security, codeguard, sitelock, acronis';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-security-laptop.jpg',
    'url' => 'https://hivenest.co.za/main-services/tools.php',
    'type' => 'service'
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Cyber Arsenal', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = "
function selectSecurityTool(toolName, price) {
    if (window.shoppingCart) {
        window.shoppingCart.addItem({
            id: 'tool-' + toolName.toLowerCase().replace(' ', '-'),
            name: 'Security Tool: ' + toolName,
            price: price,
            type: 'security'
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
        <img src="assets/images/heroes/hero-security-laptop.jpg" alt="Cyber Security Arsenal" class="hero-background">
        
        <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title">
                    CYBER<br>
                    <span class="cyber-text">ARSENAL</span><br>
                    DIGITAL SHIELDS
                </h1>
                <p class="hero-subtitle">
                    Advanced security protocols including SSL certificates, CodeGuard backups, 
                    SiteLock protection, and Acronis cyber backup systems across all dimensions.
                </p>
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                    <a href="#tools" class="btn btn-primary">ACTIVATE SHIELDS</a>
                    <a href="#pricing" class="btn btn-secondary">VIEW ARSENAL</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Security Tools -->
    <section id="tools" class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>SECURITY PROTOCOLS</h2>
                <p class="hero-subtitle">Choose your cyber defense systems</p>
            </div>
            
            <div class="services-grid">
                <div class="cyber-card">
                    <i class="fas fa-shield-alt service-icon" style="color: var(--cyber-neon-green); font-size: 4rem;"></i>
                    <h3 class="service-title">SSL CERTIFICATES</h3>
                    <p class="service-description">
                        Secure websites and customer data with trusted SSL encryption.
                        Choose standard, premium, wildcard, or extended validation protection.
                    </p>
                    <div style="margin: 1rem 0;">
                        <div style="color: var(--cyber-neon-green); font-weight: bold;">Starting at $0.60/mo</div>
                    </div>
                    <a href="../tools/sslcert.php" class="btn btn-primary">VIEW SSL PLANS</a>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-lock service-icon" style="color: var(--cyber-neon-cyan); font-size: 4rem;"></i>
                    <h3 class="service-title">SITELOCK SECURITY</h3>
                    <p class="service-description">
                        Advanced malware detection and removal with neural firewall protection. 
                        AI-powered threat analysis and quantum-level security scanning.
                    </p>
                    <div style="margin: 1rem 0;">
                        <div style="color: var(--cyber-neon-cyan); font-weight: bold;">Starting at $11.99/mo</div>
                    </div>
                    <a href="../tools/sitelock.php" class="btn btn-primary">ACTIVATE SHIELDS</a>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-cloud-upload-alt service-icon" style="color: var(--cyber-neon-pink); font-size: 4rem;"></i>
                    <h3 class="service-title">XCITIUM BACKUP</h3>
                    <p class="service-description">
                        Secure cloud backup with flexible schedules and reliable recovery tools.
                        Protect essential files and business data from unexpected loss.
                    </p>
                    <div style="margin: 1rem 0;">
                        <div style="color: var(--cyber-neon-pink); font-weight: bold;">Starting at $1.27/mo</div>
                    </div>
                    <a href="../tools/xcitium.php" class="btn btn-primary">VIEW BACKUP PLANS</a>
                </div>
            </div>
        </div>
    </section>

    <!-- SSL Certificates -->
    <section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
        <div class="container">
            <div class="text-center mb-8">
                <h2>SSL NEURAL SHIELDS</h2>
                <p class="hero-subtitle">Quantum-encrypted security certificates</p>
            </div>
            
            <div class="pricing-grid">
                <!-- Basic SSL -->
                <div class="pricing-card">
                    <div class="pricing-plan">BASIC NEURAL SHIELD</div>
                    <div class="pricing-amount">$24.99<span style="font-size: 1rem;">/year</span></div>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Domain Validation</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ 256-bit Encryption</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Single Domain Protection</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Browser Recognition</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Basic Trust Seal</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Instant Issuance</li>
                    </ul>
                    <a href="../contact.php" class="btn btn-primary" style="width: 100%;">ACTIVATE BASIC</a>
                </div>

                <!-- Wildcard SSL -->
                <div class="pricing-card featured">
                    <div class="pricing-plan">WILDCARD NEURAL SHIELD</div>
                    <div class="pricing-amount">$119.99<span style="font-size: 1rem;">/year</span></div>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Wildcard Protection</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ 256-bit Encryption</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Unlimited Subdomains</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Enhanced Browser Support</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Advanced Trust Seal</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Priority Support</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Multi-Server License</li>
                    </ul>
                    <a href="../contact.php" class="btn btn-secondary" style="width: 100%;">DEPLOY WILDCARD</a>
                </div>

                <!-- EV SSL -->
                <div class="pricing-card">
                    <div class="pricing-plan">QUANTUM NEURAL SHIELD</div>
                    <div class="pricing-amount">$249.99<span style="font-size: 1rem;">/year</span></div>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Extended Validation</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ 256-bit Encryption</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Green Address Bar</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Company Name Display</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Maximum Trust Level</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ $1.75M Warranty</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Dedicated Support</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Reality-Grade Security</li>
                    </ul>
                    <a href="../contact.php" class="btn btn-primary" style="width: 100%;">ACHIEVE QUANTUM</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Security Features -->
    <section class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>SECURITY CAPABILITIES</h2>
                <p class="hero-subtitle">Advanced protection features across all digital dimensions</p>
            </div>
            
            <div class="services-grid">
                <div class="cyber-card">
                    <i class="fas fa-virus service-icon"></i>
                    <h3 class="service-title">MALWARE DETECTION</h3>
                    <p class="service-description">
                        Real-time scanning and removal of threats using AI-powered neural networks.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-sync-alt service-icon"></i>
                    <h3 class="service-title">AUTO BACKUP</h3>
                    <p class="service-description">
                        Automated daily backups with instant recovery and cross-dimensional redundancy.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-fire service-icon"></i>
                    <h3 class="service-title">FIREWALL PROTECTION</h3>
                    <p class="service-description">
                        Advanced firewall with intrusion detection and quantum-level threat blocking.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-chart-line service-icon"></i>
                    <h3 class="service-title">NEURAL MONITORING</h3>
                    <p class="service-description">
                        24/7 monitoring with instant alerts and predictive threat analysis protocols.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-certificate service-icon"></i>
                    <h3 class="service-title">SSL OPTIMIZATION</h3>
                    <p class="service-description">
                        Automatic SSL deployment and renewal with performance optimization features.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-shield-virus service-icon"></i>
                    <h3 class="service-title">QUANTUM CLEAN</h3>
                    <p class="service-description">
                        Automated malware removal and site cleaning with reality-restoration protocols.
                    </p>
                </div>
            </div>
        </div>
    </section>

<?php include '../utilities/footer.php'; ?>

<?php include '../utilities/scripts.php'; ?>
</body>
</html>
