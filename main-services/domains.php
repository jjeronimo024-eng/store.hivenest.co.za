<?php
// Page variables
$current_page = 'domains';
$page_title = 'Neural Domains - Digital Territory Control | HiveNest Matrix';
$page_description = 'Complete domain services including registration, transfer, name generation, and advanced domain management solutions.';
$page_keywords = 'domain registration, domain transfer, bulk domains, digital territory, cyberpunk domains';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-email-global.jpg',
    'url' => 'https://hivenest.co.za/main-services/domains.php',
    'type' => 'service'
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Neural Domains', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = "
function searchDomain() {
    const domain = document.getElementById('domain-search').value;
    if (domain) {
        console.log('Searching domain:', domain);
    }
}

function addDomainToCart(domain, price) {
    if (window.shoppingCart) {
        window.shoppingCart.addItem({
            id: 'domain-' + domain,
            name: 'Domain: ' + domain,
            price: price,
            type: 'domain'
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
        <img src="assets/images/heroes/hero-domain-server-blue.jpg" alt="Neural Domains" class="hero-background">
        
        <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title">
                    <span class="cyber-text">NEURAL</span><br>
                    DOMAINS
                </h1>
                <p class="hero-subtitle">
                    Secure your digital identity across all dimensions. Register, transfer, and manage 
                    domains with quantum-grade security and instant activation.
                </p>
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                    <a href="../domains/register.php" class="btn btn-primary">REGISTER DOMAIN</a>
                    <a href="../domains/transfer.php" class="btn btn-secondary">TRANSFER DOMAIN</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Domain Services -->
    <section class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>DOMAIN SERVICES</h2>
                <p class="hero-subtitle">Complete domain management solutions</p>
            </div>
            
            <div class="services-grid">
                <div class="service-card">
                    <i class="fas fa-plus service-icon"></i>
                    <h3 class="service-title">DOMAIN REGISTRATION</h3>
                    <p class="service-description">
                        Register your perfect domain name with instant activation and quantum-grade security.
                    </p>
                    <div style="margin-top: 2rem;">
                        <a href="../domains/register.php" class="btn btn-primary">REGISTER NOW</a>
                    </div>
                </div>

                <div class="service-card">
                    <i class="fas fa-exchange-alt service-icon"></i>
                    <h3 class="service-title">DOMAIN TRANSFER</h3>
                    <p class="service-description">
                        Transfer your domains to HiveNest for better security, pricing, and management.
                    </p>
                    <div style="margin-top: 2rem;">
                        <a href="../domains/transfer.php" class="btn btn-primary">TRANSFER DOMAIN</a>
                    </div>
                </div>

                <div class="service-card">
                    <i class="fas fa-lightbulb service-icon"></i>
                    <h3 class="service-title">NAME GENERATOR</h3>
                    <p class="service-description">
                        AI-powered domain name suggestions based on your keywords and industry.
                    </p>
                    <div style="margin-top: 2rem;">
                        <a href="../domains/name-suggestion.php" class="btn btn-primary">GENERATE NAMES</a>
                    </div>
                </div>

                <div class="service-card">
                    <i class="fas fa-star service-icon"></i>
                    <h3 class="service-title">NEW EXTENSIONS</h3>
                    <p class="service-description">
                        Explore cutting-edge domain extensions for next-generation digital branding.
                    </p>
                    <div style="margin-top: 2rem;">
                        <a href="../domains/extensions.php" class="btn btn-primary">EXPLORE EXTENSIONS</a>
                    </div>
                </div>

                <div class="service-card">
                    <i class="fas fa-chart-line service-icon"></i>
                    <h3 class="service-title">DIGITAL MARKETING</h3>
                    <p class="service-description">
                        Boost your online presence with SEO, Google Marketing, and Social Media Marketing services.
                    </p>
                    <div style="margin-top: 2rem;">
                        <a href="../marketing/seo.php" class="btn btn-primary">VIEW MARKETING</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Domain Features -->
    <section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
        <div class="container">
            <div class="text-center mb-8">
                <h2>DOMAIN FEATURES</h2>
                <p class="hero-subtitle">Advanced domain management capabilities</p>
            </div>
            
            <div class="services-grid">
                <div class="cyber-card">
                    <i class="fas fa-bolt service-icon"></i>
                    <h3 class="service-title">INSTANT ACTIVATION</h3>
                    <p class="service-description">
                        Domains activated immediately with quantum-speed propagation across all networks.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-user-shield service-icon"></i>
                    <h3 class="service-title">PRIVACY PROTECTION</h3>
                    <p class="service-description">
                        Shield your personal information from public WHOIS databases and spam.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-sync service-icon"></i>
                    <h3 class="service-title">AUTO-RENEWAL</h3>
                    <p class="service-description">
                        Automatic renewal protection ensures your domains never expire unexpectedly.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-dns service-icon"></i>
                    <h3 class="service-title">DNS MANAGEMENT</h3>
                    <p class="service-description">
                        Advanced DNS control panel with full record management and subdomain support.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-lock service-icon"></i>
                    <h3 class="service-title">DOMAIN LOCK</h3>
                    <p class="service-description">
                        Registry lock protection prevents unauthorized transfers and domain theft.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-headset service-icon"></i>
                    <h3 class="service-title">EXPERT SUPPORT</h3>
                    <p class="service-description">
                        24/7 domain support from certified experts for all your domain needs.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Popular Extensions -->
    <section class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>POPULAR EXTENSIONS</h2>
                <p class="hero-subtitle">Most requested domain extensions</p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; margin-top: 3rem;">
                <div class="cyber-card" style="text-align: center;">
                    <div style="font-size: 2rem; font-weight: bold; color: var(--cyber-neon-cyan); margin-bottom: 1rem;">.COM</div>
                    <div style="font-size: 1.2rem; color: var(--cyber-neon-green); margin-bottom: 1rem;">$12.99/year</div>
                    <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 1rem;">Most popular global extension</p>
                    <a href="../domains/register.php" class="btn btn-primary">REGISTER</a>
                </div>
                
                <div class="cyber-card" style="text-align: center;">
                    <div style="font-size: 2rem; font-weight: bold; color: var(--cyber-neon-cyan); margin-bottom: 1rem;">.CO.ZA</div>
                    <div style="font-size: 1.2rem; color: var(--cyber-neon-green); margin-bottom: 1rem;">$8.99/year</div>
                    <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 1rem;">South African domain</p>
                    <a href="../domains/register.php" class="btn btn-primary">REGISTER</a>
                </div>
                
                <div class="cyber-card" style="text-align: center;">
                    <div style="font-size: 2rem; font-weight: bold; color: var(--cyber-neon-cyan); margin-bottom: 1rem;">.NET</div>
                    <div style="font-size: 1.2rem; color: var(--cyber-neon-green); margin-bottom: 1rem;">$14.99/year</div>
                    <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 1rem;">Network and tech businesses</p>
                    <a href="../domains/register.php" class="btn btn-primary">REGISTER</a>
                </div>
                
                <div class="cyber-card" style="text-align: center;">
                    <div style="font-size: 2rem; font-weight: bold; color: var(--cyber-neon-cyan); margin-bottom: 1rem;">.ORG</div>
                    <div style="font-size: 1.2rem; color: var(--cyber-neon-green); margin-bottom: 1rem;">$13.99/year</div>
                    <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 1rem;">Organizations and nonprofits</p>
                    <a href="../domains/register.php" class="btn btn-primary">REGISTER</a>
                </div>
                
                <div class="cyber-card" style="text-align: center;">
                    <div style="font-size: 2rem; font-weight: bold; color: var(--cyber-neon-cyan); margin-bottom: 1rem;">.IO</div>
                    <div style="font-size: 1.2rem; color: var(--cyber-neon-green); margin-bottom: 1rem;">$59.99/year</div>
                    <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 1rem;">Tech startups and apps</p>
                    <a href="../domains/register.php" class="btn btn-primary">REGISTER</a>
                </div>
                
                <div class="cyber-card" style="text-align: center;">
                    <div style="font-size: 2rem; font-weight: bold; color: var(--cyber-neon-cyan); margin-bottom: 1rem;">.TECH</div>
                    <div style="font-size: 1.2rem; color: var(--cyber-neon-green); margin-bottom: 1rem;">$49.99/year</div>
                    <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 1rem;">Technology companies</p>
                    <a href="../domains/register.php" class="btn btn-primary">REGISTER</a>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 3rem;">
                <a href="../domains/extensions.php" class="btn btn-secondary">VIEW ALL EXTENSIONS</a>
            </div>
        </div>
    </section>

<?php include '../utilities/footer.php'; ?>

<?php include '../utilities/scripts.php'; ?>
</body>
</html>
