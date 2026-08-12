<?php
// Include required utilities
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/seo-meta.php';
include_once '../utilities/domain_pricing_helper.php';

// Load domain extensions from database
$domain_extensions = getAllDomainExtensions();
$tld_pricing_js = generateTLDPricingJS();

// Page variables
$current_page = 'domains';
$page_title = 'AI Domain Name Generator - Neural Suggestions | HiveNest Matrix';
$page_description = 'AI-powered domain name generator. Discover the perfect domain name for your business with our intelligent domain suggestion engine.';
$page_keywords = 'domain name generator, AI domain generator, neural domains, domain suggestions';

// SEO configuration (after including seo-meta.php)
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-branding-ipad-day.jpg',
    'url' => 'https://hivenest.co.za/domains/name-suggestion.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'AI Domain Name Generator',
        'description' => 'Intelligent domain suggestion engine with neural network analysis',
        'serviceType' => 'Domain Generation Services'
    ])
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Neural Domains', 'url' => '/main-services/domains.php'],
    ['text' => 'Name Generator', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = "
// Load TLD pricing from PHP
const tldPrices = $tld_pricing_js;

// Domain name generator
document.getElementById('domainGeneratorForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const keywords = document.getElementById('keywords').value.split(',').map(k => k.trim());
    const industry = document.getElementById('industry').value;
    const style = document.getElementById('style').value;
    const length = document.getElementById('length').value;
    const extensions = document.getElementById('extensions').value;
    
    generateDomainNames(keywords, industry, style, length, extensions);
});

function generateDomainNames(keywords, industry, style, length, extensions) {
    const resultsSection = document.getElementById('results');
    const resultsContainer = document.getElementById('domainResults');
    
    // Show results section and scroll to it smoothly
    resultsSection.style.display = 'block';
    resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    
    resultsContainer.innerHTML = '<div style=\"text-align: center; color: var(--cyber-neon-cyan); grid-column: 1 / -1;\"><i class=\"fas fa-spinner fa-spin\" style=\"font-size: 3rem; margin-bottom: 1rem;\"></i><p style=\"font-size: 1.2rem;\">Generating domain names...</p></div>';
    
    // Simulate AI generation
    setTimeout(() => {
        const suggestions = generateSuggestions(keywords, industry, style, length, extensions);
        displayResults(suggestions);
    }, 2000);
}

function generateSuggestions(keywords, industry, style, length, extensions) {
    const prefixes = ['cyber', 'digital', 'smart', 'next', 'pro', 'ultra', 'mega', 'super', 'tech', 'neo'];
    const suffixes = ['hub', 'lab', 'tech', 'pro', 'zone', 'space', 'world', 'net', 'works', 'solutions'];
    const tlds = extensions === 'popular' ? ['.com', '.net', '.org'] : 
                extensions === 'new' ? ['.tech', '.app', '.dev', '.io'] :
                extensions === 'local' ? ['.co.za'] :
                Object.keys(tldPrices);
    
    const suggestions = [];
    
    // Function to get TLD price from database
    function getTLDPrice(tld) {
        return tldPrices[tld] || 12.99;
    }
    
    keywords.forEach(keyword => {
        // Direct keyword combinations
        prefixes.forEach(prefix => {
            tlds.forEach(tld => {
                suggestions.push({
                    name: prefix + keyword + tld,
                    available: Math.random() > 0.7,
                    price: getTLDPrice(tld)
                });
            });
        });
        
        // Keyword with suffix
        suffixes.forEach(suffix => {
            tlds.forEach(tld => {
                suggestions.push({
                    name: keyword + suffix + tld,
                    available: Math.random() > 0.6,
                    price: getTLDPrice(tld)
                });
            });
        });
    });
    
    // Return random selection
    return suggestions.sort(() => Math.random() - 0.5).slice(0, 12);
}

function displayResults(suggestions) {
    const resultsContainer = document.getElementById('domainResults');
    
    resultsContainer.innerHTML = suggestions.map(suggestion => {
        return '<div class=\"cyber-card\">' +
            '<h3 style=\"color: var(--cyber-neon-cyan); margin-bottom: 1rem; font-size: 1.1rem; word-break: break-all;\">' +
                suggestion.name +
            '</h3>' +
            '<div style=\"display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;\">' +
                '<span style=\"color: ' + (suggestion.available ? 'var(--cyber-neon-green)' : 'var(--cyber-neon-pink)') + '; font-weight: bold;\">' +
                    (suggestion.available ? 'AVAILABLE' : 'TAKEN') +
                '</span>' +
                (suggestion.available ? '<span style=\"color: var(--cyber-neon-cyan); font-weight: bold;\">$' + suggestion.price + '/year</span>' : '') +
            '</div>' +
            (suggestion.available ? 
                '<button onclick=\"registerDomain(this, \'' + suggestion.name + '\', ' + suggestion.price + ')\" ' +
                        'class=\"btn btn-primary\" style=\"width: 100%; padding: 0.75rem; font-size: 0.9rem;\">' +
                    'REGISTER NOW' +
                '</button>' :
                '<button onclick=\"suggestAlternative(\'' + suggestion.name + '\')\" ' +
                        'class=\"btn btn-outline\" style=\"width: 100%; padding: 0.75rem; font-size: 0.9rem;\">' +
                    'SUGGEST ALTERNATIVE' +
                '</button>') +
        '</div>';
    }).join('');
}

function registerDomain(button, domain, price) {
    // Initialize cart from localStorage
    let cart = JSON.parse(localStorage.getItem('neuralCart') || '[]');
    
    // Create cart item
    const cartItem = {
        id: 'domain_' + domain.replace(/\\./g, '_'),
        name: 'Domain Registration: ' + domain,
        description: 'AI-suggested domain (1 year)',
        category: 'domain',
        type: 'domain_registration',
        price: parseFloat(price),
        quantity: 1,
        domain: domain,
        parent_product: 'domain',
        allows_addons: true
    };
    
    // Check if item already exists
    const existingIndex = cart.findIndex(item => item.id === cartItem.id);
    if (existingIndex >= 0) {
        cart[existingIndex] = cartItem;
    } else {
        cart.push(cartItem);
    }
    
    // Save cart
    localStorage.setItem('neuralCart', JSON.stringify(cart));
    
    // Show inline success message and change button
    button.innerHTML = '<i class=\"fas fa-check\"></i> ADDED';
    button.style.background = 'var(--cyber-neon-green)';
    button.disabled = true;
    
    // Show the Go to Cart button if not already visible
    if (!document.getElementById('go-to-cart-btn')) {
        const goToCartBtn = document.createElement('button');
        goToCartBtn.id = 'go-to-cart-btn';
        goToCartBtn.className = 'btn btn-primary';
        goToCartBtn.style.cssText = 'position: fixed; bottom: 30px; right: 30px; z-index: 1000; padding: 16px 32px; font-size: 1.1rem; box-shadow: 0 4px 20px rgba(0,255,255,0.5);';
        goToCartBtn.innerHTML = '<i class=\"fas fa-shopping-cart\" style=\"margin-right: 0.5rem;\"></i> GO TO CART (' + cart.length + ')';
        goToCartBtn.onclick = function() {
            window.location.href = '/cart.php';
        };
        document.body.appendChild(goToCartBtn);
    } else {
        // Update cart count
        const goToCartBtn = document.getElementById('go-to-cart-btn');
        goToCartBtn.innerHTML = '<i class=\"fas fa-shopping-cart\" style=\"margin-right: 0.5rem;\"></i> GO TO CART (' + cart.length + ')';
    }
}

function suggestAlternative(domain) {
    console.info('Finding alternatives for ' + domain + '...');
    // Could implement real alternative suggestions here
}
";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php 
include '../utilities/head.php'; 
echo renderSEOMeta($seo_config);
?>
</head>
<body>
<?php include '../utilities/nav.php'; ?>
<?php include '../utilities/mobile-menu.php'; ?>

<?php
// Hero Section
$hero_title = '<span class="cyber-text">AI-POWERED</span><br>DOMAIN GENERATOR';
$hero_subtitle = 'Discover the perfect domain name for your business with our intelligent domain suggestion engine.';
$hero_image = '../assets/images/heroes/hero-domain-network.jpg';
$hero_alt = 'AI Domain Generator';
include '../utilities/hero-minimal.php';
?>

<?php
// Breadcrumbs
?>

<!-- Domain Generator -->
<section id="generator" class="section">
    <div class="container">
        <div class="text-center mb-8">
            <h2>DOMAIN NAME GENERATOR</h2>
            <p class="hero-subtitle">Generate creative domain names for your project</p>
        </div>
        
        <div class="cyber-card" style="max-width: 900px; margin: 0 auto;">
            <form id="domainGeneratorForm">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <div>
                        <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">PROJECT KEYWORDS</label>
                        <input type="text" id="keywords" placeholder="Enter keywords (e.g., tech, startup, creative)" required
                               style="width: 100%; padding: 1rem; border: 1px solid var(--cyber-neon-cyan); border-radius: 6px; background: var(--cyber-dark); color: white;">
                        <p style="color: rgba(255, 255, 255, 0.6); font-size: 0.9rem; margin-top: 0.5rem;">
                            Separate multiple keywords with commas
                        </p>
                    </div>
                    
                    <div>
                        <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">INDUSTRY</label>
                        <select id="industry" style="width: 100%; padding: 1rem; border: 1px solid var(--cyber-neon-cyan); border-radius: 6px; background: var(--cyber-dark); color: white;">
                            <option value="">Select Industry</option>
                            <option value="technology">Technology</option>
                            <option value="business">Business</option>
                            <option value="creative">Creative & Design</option>
                            <option value="ecommerce">E-commerce</option>
                            <option value="health">Health & Wellness</option>
                            <option value="education">Education</option>
                            <option value="finance">Finance</option>
                            <option value="food">Food & Beverage</option>
                            <option value="travel">Travel</option>
                            <option value="gaming">Gaming</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                    <div>
                        <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">STYLE</label>
                        <select id="style" style="width: 100%; padding: 1rem; border: 1px solid var(--cyber-neon-cyan); border-radius: 6px; background: var(--cyber-dark); color: white;">
                            <option value="modern">Modern</option>
                            <option value="classic">Classic</option>
                            <option value="creative">Creative</option>
                            <option value="professional">Professional</option>
                            <option value="playful">Playful</option>
                            <option value="minimalist">Minimalist</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">LENGTH</label>
                        <select id="length" style="width: 100%; padding: 1rem; border: 1px solid var(--cyber-neon-cyan); border-radius: 6px; background: var(--cyber-dark); color: white;">
                            <option value="short">Short (4-8 characters)</option>
                            <option value="medium">Medium (9-15 characters)</option>
                            <option value="long">Long (16+ characters)</option>
                            <option value="any">Any length</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">EXTENSIONS</label>
                        <select id="extensions" style="width: 100%; padding: 1rem; border: 1px solid var(--cyber-neon-cyan); border-radius: 6px; background: var(--cyber-dark); color: white;">
                            <option value="popular">Popular (.com, .net, .org)</option>
                            <option value="all">All extensions</option>
                            <option value="new">New TLDs (.tech, .store, .app)</option>
                            <option value="local">Local (.co.za, .com.au)</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem;">
                    <input type="checkbox" id="excludeHyphens" checked style="width: 18px; height: 18px;">
                    <label for="excludeHyphens" style="color: rgba(255, 255, 255, 0.8);">Exclude hyphens and numbers</label>
                    
                    <input type="checkbox" id="includeAvailability" checked style="width: 18px; height: 18px;">
                    <label for="includeAvailability" style="color: rgba(255, 255, 255, 0.8);">Check availability</label>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1.1rem;">
                    GENERATE DOMAIN NAMES
                </button>
            </form>
        </div>
    </div>
</section>

<!-- Generated Results -->
<section id="results" class="section" style="display: none;">
    <div class="container">
        <div class="text-center mb-8">
            <h2>GENERATED SUGGESTIONS</h2>
            <p class="hero-subtitle">AI-powered domain name suggestions for your project</p>
        </div>
        
        <div id="domainResults" class="services-grid">
            <!-- Results will be populated here -->
        </div>
    </div>
</section>

<?php
// Domain Naming Tips
include '../utilities/cyber-cards.php';
$naming_tips = [
    [
        'icon' => 'fas fa-lightbulb',
        'title' => 'KEEP IT SIMPLE',
        'description' => 'Choose a name that\'s easy to spell, pronounce, and remember. Avoid complex words and confusing spellings.'
    ],
    [
        'icon' => 'fas fa-bolt',
        'title' => 'MAKE IT MEMORABLE',
        'description' => 'Use unique, catchy names that stick in people\'s minds. Consider wordplay, alliteration, or creative combinations.'
    ],
    [
        'icon' => 'fas fa-search',
        'title' => 'SEO FRIENDLY',
        'description' => 'Include relevant keywords when possible, but don\'t sacrifice brandability for SEO. Balance is key.'
    ],
    [
        'icon' => 'fas fa-globe',
        'title' => 'CHOOSE .COM FIRST',
        'description' => 'While new TLDs are available, .com remains the gold standard for credibility and memorability.'
    ],
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'AVOID TRADEMARKS',
        'description' => 'Research existing trademarks and brands to avoid legal issues. Choose completely original names.'
    ],
    [
        'icon' => 'fas fa-mobile-alt',
        'title' => 'MOBILE FRIENDLY',
        'description' => 'Ensure your domain is easy to type on mobile devices. Avoid long names and special characters.'
    ]
];

$grid_title = 'DOMAIN NAMING TIPS';
$grid_subtitle = 'Best practices for choosing the perfect domain name';
$grid_content = renderCyberCardsGrid($naming_tips);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<?php
// Popular Domain Extensions
include '../utilities/pricing-cards.php';
$domain_extensions = [
    [
        'name' => '.COM',
        'price' => '$12.99',
        'period' => '/year',
        'features' => [
            'Most trusted and memorable',
            'Perfect for businesses',
            'Global reach and recognition',
            'Strong SEO benefits',
            'High resale value',
            'Universal acceptance'
        ],
        'cta_link' => '/domains/register.php',
        'cta_text' => 'REGISTER .COM'
    ],
    [
        'name' => '.TECH',
        'price' => '$24.99',
        'period' => '/year',
        'features' => [
            'Perfect for technology companies',
            'Modern and innovative',
            'Great for startups',
            'Tech industry credibility',
            'Memorable and brandable',
            'Future-focused identity'
        ],
        'cta_link' => '/domains/register.php',
        'cta_text' => 'GET .TECH',
        'featured' => true
    ],
    [
        'name' => '.CO.ZA',
        'price' => '$8.99',
        'period' => '/year',
        'features' => [
            'South African local domain',
            'Local SEO advantages',
            'Affordable pricing',
            'Community trust',
            'Regional authority',
            'Local business credibility'
        ],
        'cta_link' => '/domains/register.php',
        'cta_text' => 'CLAIM .CO.ZA'
    ]
];

$grid_title = 'POPULAR EXTENSIONS';
$grid_subtitle = 'Choose the right extension for your domain';
$grid_content = renderPricingGrid($domain_extensions);
include '../utilities/grid-section.php';
?>

<?php
// CTA Section
$cta_title = 'READY TO GENERATE YOUR PERFECT DOMAIN?';
$cta_subtitle = 'Use our AI-powered domain generator to discover unique, brandable names for your digital empire.';
$cta_buttons = '<a href="/contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">GET STARTED</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
