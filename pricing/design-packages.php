<?php
// Load database helper to fetch pricing
require_once '../utilities/db_helper.php';
$db = new DatabaseHelper();

// Fetch design products from database
$design_products = $db->getProductsByType('design');

// Create a mapping for easy access
$products_map = [];
foreach ($design_products as $product) {
    $products_map[$product['slug']] = $product;
}

// Page variables
$current_page = 'design';
$page_title = 'Design Packages - Neural Graphics | HiveNest';
$page_description = 'Professional design packages for logo, branding, and digital identity creation with quantum-level creativity and neural precision.';
$page_keywords = 'design packages, logo design, branding, neural graphics, cyberpunk design';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-pricing-packages.jpg',
    'url' => 'https://hivenest.co.za/pricing/design-packages.php',
    'type' => 'pricing'
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Design Packages', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = "
function selectDesignPackage(productSlug, productName, price) {
    if (window.shoppingCart) {
        window.shoppingCart.addItem({
            id: productSlug,
            name: productName,
            price: parseFloat(price),
            type: 'design'
        });
        
        // Show success message
        console.log(productName + ' added to cart!');
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
</head>
<body>
<?php include '../utilities/nav.php'; ?>

<?php include '../utilities/mobile-menu.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        <img src="assets/images/heroes/hero-pricing-packages.jpg" alt="Design Packages" class="hero-background">
        
        <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title">
                    NEURAL<br>
                    <span class="cyber-text">GRAPHICS</span><br>
                    DESIGN PACKAGES
                </h1>
                <p class="hero-subtitle">
                    Professional design packages for logo, branding, and digital identity creation 
                    with quantum-level creativity and neural precision.
                </p>
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                    <a href="#packages" class="btn btn-primary">VIEW PACKAGES</a>
                    <a href="#portfolio" class="btn btn-secondary">NEURAL PORTFOLIO</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Design Packages -->
    <section id="packages" class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>DESIGN POWER LEVELS</h2>
                <p class="hero-subtitle">Choose your creative evolution tier</p>
            </div>
            
            <div class="pricing-grid">
                <!-- Starter Package -->
                <div class="pricing-card">
                    <div class="pricing-plan">NEURAL BASIC</div>
                    <div class="pricing-amount">$149<span style="font-size: 1rem;">/package</span></div>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Logo Design (3 concepts)</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ 2 Revision Rounds</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ PNG & JPG Files</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Basic Color Palette</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ 7-Day Delivery</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Basic Brand Guidelines</li>
                    </ul>
                    <button onclick="selectDesignPackage('neural-basic', 'Neural Basic', 149)" class="btn btn-primary" style="width: 100%;">ORDER PACKAGE</button>
                </div>

                <!-- Professional Package -->
                <div class="pricing-card featured">
                    <div class="pricing-plan">NEURAL PROFESSIONAL</div>
                    <div class="pricing-amount">$299<span style="font-size: 1rem;">/package</span></div>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Logo Design (5 concepts)</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Business Card Design</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Letterhead Design</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Email Signature</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ 4 Revision Rounds</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ All File Formats</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Extended Color Palette</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ 5-Day Delivery</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Brand Style Guide</li>
                    </ul>
                    <button onclick="selectDesignPackage('neural-professional', 'Neural Professional', 299)" class="btn btn-secondary" style="width: 100%;">MOST POPULAR</button>
                </div>

                <!-- Enterprise Package -->
                <div class="pricing-card">
                    <div class="pricing-plan">NEURAL ENTERPRISE</div>
                    <div class="pricing-amount">$599<span style="font-size: 1rem;">/package</span></div>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Logo Design (Unlimited concepts)</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Complete Stationery Set</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Website Banner Design</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Social Media Kit</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Brand Guidelines Book</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Unlimited Revisions</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ All File Formats + Vector</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ 3-Day Delivery</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Dedicated Designer</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ 6-Month Support</li>
                    </ul>
                    <button onclick="selectDesignPackage('neural-enterprise', 'Neural Enterprise', 599)" class="btn btn-primary" style="width: 100%;">ENTERPRISE GRADE</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Individual Services -->
    <section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
        <div class="container">
            <div class="text-center mb-8">
                <h2>INDIVIDUAL SERVICES</h2>
                <p class="hero-subtitle">À la carte design services for specific needs</p>
            </div>
            
            <div class="services-grid">
                <div class="cyber-card">
                    <i class="fas fa-palette service-icon"></i>
                    <h3 class="service-title">LOGO DESIGN</h3>
                    <p class="service-description">
                        Professional logo design with 3 concepts and 2 revision rounds.
                    </p>
                    <div style="margin: 1rem 0;">
                        <div style="color: var(--cyber-neon-cyan); font-weight: bold;">$89</div>
                    </div>
                    <a href="../branding/logo.php" class="btn btn-primary">ORDER NOW</a>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-id-card service-icon"></i>
                    <h3 class="service-title">BUSINESS CARDS</h3>
                    <p class="service-description">
                        Professional business card design with print-ready files.
                    </p>
                    <div style="margin: 1rem 0;">
                        <div style="color: var(--cyber-neon-cyan); font-weight: bold;">$59</div>
                    </div>
                    <a href="../branding/business-cards.php" class="btn btn-primary">ORDER NOW</a>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-file-alt service-icon"></i>
                    <h3 class="service-title">LETTERHEAD</h3>
                    <p class="service-description">
                        Custom letterhead design matching your brand identity.
                    </p>
                    <div style="margin: 1rem 0;">
                        <div style="color: var(--cyber-neon-cyan); font-weight: bold;">$49</div>
                    </div>
                    <a href="../branding/letterheads.php" class="btn btn-primary">ORDER NOW</a>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-signature service-icon"></i>
                    <h3 class="service-title">EMAIL SIGNATURE</h3>
                    <p class="service-description">
                        Professional email signature with contact information.
                    </p>
                    <div style="margin: 1rem 0;">
                        <div style="color: var(--cyber-neon-cyan); font-weight: bold;">$29</div>
                    </div>
                    <a href="../branding/signatures.php" class="btn btn-primary">ORDER NOW</a>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-globe service-icon"></i>
                    <h3 class="service-title">WEBSITE BANNER</h3>
                    <p class="service-description">
                        Custom website banner design for headers and promotions.
                    </p>
                    <div style="margin: 1rem 0;">
                        <div style="color: var(--cyber-neon-cyan); font-weight: bold;">$79</div>
                    </div>
                    <a href="../contact.php" class="btn btn-primary">ORDER NOW</a>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-share-alt service-icon"></i>
                    <h3 class="service-title">SOCIAL MEDIA KIT</h3>
                    <p class="service-description">
                        Complete social media graphics package for all platforms.
                    </p>
                    <div style="margin: 1rem 0;">
                        <div style="color: var(--cyber-neon-cyan); font-weight: bold;">$149</div>
                    </div>
                    <a href="../contact.php" class="btn btn-primary">ORDER NOW</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Design Process -->
    <section class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>DESIGN PROCESS</h2>
                <p class="hero-subtitle">Our neural design methodology</p>
            </div>
            
            <div class="cyber-card" style="max-width: 900px; margin: 0 auto;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                    <div style="text-align: center;">
                        <div style="width: 80px; height: 80px; background: var(--cyber-neon-cyan); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                            <i class="fas fa-comments" style="font-size: 2rem; color: var(--cyber-black);"></i>
                        </div>
                        <h4 style="color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;">CONSULTATION</h4>
                        <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Understanding your vision and requirements</p>
                    </div>
                    
                    <div style="text-align: center;">
                        <div style="width: 80px; height: 80px; background: var(--cyber-neon-green); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                            <i class="fas fa-lightbulb" style="font-size: 2rem; color: var(--cyber-black);"></i>
                        </div>
                        <h4 style="color: var(--cyber-neon-green); margin-bottom: 0.5rem;">CONCEPT CREATION</h4>
                        <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Developing initial design concepts</p>
                    </div>
                    
                    <div style="text-align: center;">
                        <div style="width: 80px; height: 80px; background: var(--cyber-neon-orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                            <i class="fas fa-edit" style="font-size: 2rem; color: var(--cyber-black);"></i>
                        </div>
                        <h4 style="color: var(--cyber-neon-orange); margin-bottom: 0.5rem;">REFINEMENT</h4>
                        <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Refining based on your feedback</p>
                    </div>
                    
                    <div style="text-align: center;">
                        <div style="width: 80px; height: 80px; background: var(--cyber-neon-pink); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                            <i class="fas fa-rocket" style="font-size: 2rem; color: var(--cyber-black);"></i>
                        </div>
                        <h4 style="color: var(--cyber-neon-pink); margin-bottom: 0.5rem;">DELIVERY</h4>
                        <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Final files and brand guidelines</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Portfolio Preview -->
    <section id="portfolio" class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
        <div class="container">
            <div class="text-center mb-8">
                <h2>NEURAL PORTFOLIO</h2>
                <p class="hero-subtitle">Examples of our quantum-level design work</p>
            </div>
            
            <div class="cyber-card" style="text-align: center;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                    <div style="background: rgba(255, 255, 255, 0.1); padding: 2rem; border-radius: 8px;">
                        <i class="fas fa-building" style="font-size: 4rem; color: var(--cyber-neon-cyan); margin-bottom: 1rem;"></i>
                        <h4 style="color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;">CORPORATE IDENTITY</h4>
                        <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Complete corporate branding solutions</p>
                    </div>
                    
                    <div style="background: rgba(255, 255, 255, 0.1); padding: 2rem; border-radius: 8px;">
                        <i class="fas fa-store" style="font-size: 4rem; color: var(--cyber-neon-green); margin-bottom: 1rem;"></i>
                        <h4 style="color: var(--cyber-neon-green); margin-bottom: 0.5rem;">RETAIL BRANDS</h4>
                        <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Engaging retail and e-commerce designs</p>
                    </div>
                    
                    <div style="background: rgba(255, 255, 255, 0.1); padding: 2rem; border-radius: 8px;">
                        <i class="fas fa-laptop-code" style="font-size: 4rem; color: var(--cyber-neon-pink); margin-bottom: 1rem;"></i>
                        <h4 style="color: var(--cyber-neon-pink); margin-bottom: 0.5rem;">TECH STARTUPS</h4>
                        <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Modern tech and startup identities</p>
                    </div>
                </div>
                
                <div style="margin-top: 2rem;">
                    <a href="../contact.php" class="btn btn-primary" style="margin-right: 1rem;">VIEW FULL PORTFOLIO</a>
                    <a href="../contact.php" class="btn btn-secondary">START YOUR PROJECT</a>
                </div>
            </div>
        </div>
    </section>

<?php include '../utilities/footer.php'; ?>

<?php include '../utilities/scripts.php'; ?>
</body>
</html>
