<?php
// Include required utilities
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/dynamic_pricing.php';

// Page variables
$current_page = 'marketing';
$page_title = 'SEO Services - Search Engine Optimization | HiveNest Matrix';
$page_description = 'Professional SEO Services - Boost your search rankings with advanced SEO strategies, keyword optimization, and neural search algorithms.';
$page_keywords = 'SEO services, search engine optimization, keyword optimization, digital marketing, website ranking';

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Marketing Matrix', 'url' => '../marketing/offers.php'],
    ['text' => 'SEO Services', 'url' => null]
];

// Get pricing from database
$seo_plans = loadProductPricingPlans([
    'product_id' => 20,
    'product_slug' => 'seo-services',
    'cart_function' => 'addSEOToCart',
]);

// Page-specific JavaScript
$page_scripts = <<<'JAVASCRIPT'
function addSEOToCart(planId, planName, price) {
    if (typeof price === 'undefined') {
        price = planName;
        planName = planId.replace(/-/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase());
    }
    if (window.addToCart) {
        window.addToCart({
            id: 'seo-' + planId,
            name: 'SEO Services: ' + planName,
            price: price,
            type: 'marketing'
        });
    } else {
        console.error('Cart system not loaded');
        console.warn('Cart system not ready. Please refresh the page and try again.');
    }
}

// SEO Analyzer
function analyzeSEO() {
    const website = document.getElementById('website-url').value.trim();
    const resultDiv = document.getElementById('seo-analysis-result');
    
    if (!website) {
        return;
    }
    
    resultDiv.innerHTML = `
        <div class="cyber-card" style="text-align: center;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--cyber-neon-cyan); margin-bottom: 1rem;"></i>
            <h4 style="color: var(--cyber-neon-cyan);">ANALYZING SEO MATRIX...</h4>
            <p style="color: rgba(255,255,255,0.7);">Scanning ${website} for optimization opportunities</p>
        </div>
    `;
    resultDiv.style.display = 'block';
    
    // Simulate analysis
    setTimeout(() => {
        const score = Math.floor(Math.random() * 40) + 35; // Random score between 35-75
        const scoreColor = score < 50 ? 'var(--cyber-neon-pink)' : score < 70 ? 'var(--cyber-neon-yellow)' : 'var(--cyber-neon-green)';
        
        resultDiv.innerHTML = `
            <div class="cyber-card">
                <div style="text-align: center; margin-bottom: 2rem;">
                    <div style="width: 100px; height: 100px; border-radius: 50%; border: 5px solid ${scoreColor}; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center;">
                        <span style="font-size: 2rem; font-weight: bold; color: ${scoreColor};">${score}</span>
                    </div>
                    <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">SEO SCORE: ${score}/100</h3>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                    <div style="text-align: center; padding: 1rem; background: rgba(0,0,0,0.3); border-radius: 8px;">
                        <i class="fas fa-search" style="font-size: 1.5rem; color: var(--cyber-neon-green); margin-bottom: 0.5rem;"></i>
                        <h4 style="color: var(--cyber-neon-green); margin: 0;">KEYWORDS</h4>
                        <p style="color: rgba(255,255,255,0.8); margin: 0.5rem 0 0;">Needs optimization</p>
                    </div>
                    <div style="text-align: center; padding: 1rem; background: rgba(0,0,0,0.3); border-radius: 8px;">
                        <i class="fas fa-tachometer-alt" style="font-size: 1.5rem; color: var(--cyber-neon-yellow); margin-bottom: 0.5rem;"></i>
                        <h4 style="color: var(--cyber-neon-yellow); margin: 0;">SPEED</h4>
                        <p style="color: rgba(255,255,255,0.8); margin: 0.5rem 0 0;">Good performance</p>
                    </div>
                    <div style="text-align: center; padding: 1rem; background: rgba(0,0,0,0.3); border-radius: 8px;">
                        <i class="fas fa-mobile-alt" style="font-size: 1.5rem; color: var(--cyber-neon-pink); margin-bottom: 0.5rem;"></i>
                        <h4 style="color: var(--cyber-neon-pink); margin: 0;">MOBILE</h4>
                        <p style="color: rgba(255,255,255,0.8); margin: 0.5rem 0 0;">Requires improvement</p>
                    </div>
                </div>
                
                <div style="text-align: center;">
                    <p style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem;">
                        Your website has potential for significant SEO improvements. Our experts can help boost your rankings.
                    </p>
                    <a href="../cart.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                        VIEW CART & CHECKOUT
                    </a>
                </div>
            </div>
        `;
    }, 3000);
}
JAVASCRIPT;
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
    'image' => 'assets/images/heroes/hero-marketing-seo.jpg',
    'url' => 'https://hivenest.co.za/marketing/seo.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'SEO Services',
        'description' => 'Professional search engine optimization services',
        'serviceType' => 'SEO Marketing Services'
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
$hero_title = '<span class="cyber-text">SEO</span><br>NEURAL OPTIMIZATION';
$hero_subtitle = 'Dominate search rankings with advanced SEO strategies, neural keyword optimization, and quantum-level search visibility across all digital dimensions.';
$hero_image = '../assets/images/heroes/hero-marketing-seo.jpg';
$hero_alt = 'SEO Search Engine Optimization';
include '../utilities/hero-minimal.php';
?>

<!-- SEO Analyzer Tool -->
<section class="section" style="padding-top: 2rem;">
    <div class="container">
        <div style="max-width: 800px; margin: 0 auto;">
            <div class="cyber-card">
                <h3 style="color: var(--cyber-neon-cyan); text-align: center; margin-bottom: 2rem;">FREE SEO ANALYSIS SCANNER</h3>
                
                <div style="margin-bottom: 2rem;">
                    <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                        ENTER YOUR WEBSITE URL:
                    </label>
                    <div style="display: flex; gap: 1rem;">
                        <input 
                            type="url" 
                            id="website-url"
                            placeholder="https://yourwebsite.com" 
                            style="flex: 1; padding: 16px; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white; font-size: 1.1rem;"
                        >
                        <button onclick="analyzeSEO()" class="btn btn-primary" style="padding: 16px 24px; white-space: nowrap;">
                            ANALYZE SEO
                        </button>
                    </div>
                    <p style="color: rgba(255,255,255,0.6); font-size: 0.9rem; margin-top: 0.5rem;">
                        Get a comprehensive SEO analysis of your website's performance
                    </p>
                </div>
                
                <div id="seo-analysis-result" style="display: none;">
                    <!-- Results will be populated here -->
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// SEO Service Plans - Retrieved from database above
include '../utilities/pricing-cards.php';

$grid_title = 'SEO SERVICE PLANS';
$grid_subtitle = 'Choose the perfect SEO strategy for your business growth';
$grid_content = renderPricingGrid($seo_plans);
include '../utilities/grid-section.php';
?>

<?php
// SEO Features
include '../utilities/cyber-cards.php';
$seo_features = [
    [
        'icon' => 'fas fa-search',
        'title' => 'KEYWORD OPTIMIZATION',
        'description' => 'Advanced keyword research and optimization using AI-powered tools to target high-converting search terms and boost organic traffic.'
    ],
    [
        'icon' => 'fas fa-code',
        'title' => 'TECHNICAL SEO',
        'description' => 'Complete technical optimization including site speed, mobile responsiveness, schema markup, and crawl optimization for search engines.'
    ],
    [
        'icon' => 'fas fa-link',
        'title' => 'LINK BUILDING',
        'description' => 'Strategic link building campaigns to build domain authority through high-quality, relevant backlinks from authoritative websites.'
    ],
    [
        'icon' => 'fas fa-chart-line',
        'title' => 'ANALYTICS & REPORTING',
        'description' => 'Comprehensive tracking and reporting with custom dashboards to monitor rankings, traffic, and conversion improvements.'
    ],
    [
        'icon' => 'fas fa-map-marker-alt',
        'title' => 'LOCAL SEO',
        'description' => 'Local search optimization for businesses targeting specific geographic areas with Google My Business and local citation management.'
    ],
    [
        'icon' => 'fas fa-edit',
        'title' => 'CONTENT STRATEGY',
        'description' => 'Strategic content creation and optimization that engages users and signals expertise, authority, and trustworthiness to search engines.'
    ]
];

$grid_title = 'SEO OPTIMIZATION FEATURES';
$grid_subtitle = 'Comprehensive search engine optimization services';
$grid_content = renderCyberCardsGrid($seo_features);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<?php
// SEO Process
include '../utilities/four-column-grid.php';
$seo_process = [
    [
        'icon' => 'fas fa-search',
        'title' => 'SEO AUDIT & RESEARCH',
        'items' => [
            'Complete website SEO audit',
            'Competitor analysis',
            'Keyword research & mapping',
            'Technical issue identification',
            'Current ranking assessment'
        ]
    ],
    [
        'icon' => 'fas fa-cogs',
        'title' => 'STRATEGY DEVELOPMENT',
        'items' => [
            'Custom SEO strategy creation',
            'Content marketing plan',
            'Link building roadmap',
            'Technical optimization plan',
            'Timeline and milestone setting'
        ]
    ],
    [
        'icon' => 'fas fa-tools',
        'title' => 'IMPLEMENTATION',
        'items' => [
            'On-page optimization',
            'Technical SEO fixes',
            'Content creation & optimization',
            'Link building execution',
            'Local SEO setup'
        ]
    ],
    [
        'icon' => 'fas fa-chart-bar',
        'title' => 'MONITORING & REPORTING',
        'items' => [
            'Ranking tracking',
            'Traffic analysis',
            'Conversion monitoring',
            'Monthly progress reports',
            'Strategy adjustments'
        ]
    ]
];

$grid_title = 'SEO PROCESS WORKFLOW';
$grid_subtitle = 'Our proven 4-step approach to SEO success';
$grid_content = renderFourColumnGrid($seo_process);
include '../utilities/grid-section.php';
?>

<?php
// CTA Section
$cta_title = 'READY TO DOMINATE SEARCH RANKINGS?';
$cta_subtitle = 'Start your SEO journey today and watch your website climb to the top of search results with our proven optimization strategies.';
$cta_buttons = '<a href="../contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">START SEO CAMPAIGN</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
