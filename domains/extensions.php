<?php
// Include required utilities
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/seo-meta.php';
include_once '../utilities/domain_pricing_helper.php';

$domain_extensions = getAllDomainExtensions();

function hivenest_extension_category(array $extension): string {
    $tld = strtolower($extension['extension'] ?? '');
    $db_category = strtolower($extension['category'] ?? 'generic');
    $tech = ['.app', '.cloud', '.dev', '.host', '.hosting', '.io', '.online', '.site', '.software', '.tech', '.website'];
    $business = ['.agency', '.biz', '.business', '.company', '.consulting', '.enterprises', '.finance', '.global', '.group', '.ltd', '.market', '.services'];
    $creative = ['.art', '.audio', '.blog', '.design', '.media', '.music', '.photo', '.photography', '.studio', '.video'];

    if (in_array($tld, ['.com', '.net', '.org', '.info'], true)) return 'classic';
    if ($db_category === 'country' || preg_match('/^\.[a-z]{2}$/', $tld) || substr_count($tld, '.') > 1) return 'country';
    if ($db_category === 'premium' || !empty($extension['is_premium'])) return 'premium';
    if (in_array($tld, $tech, true)) return 'tech';
    if (in_array($tld, $business, true)) return 'business';
    if (in_array($tld, $creative, true)) return 'creative';
    return 'business';
}

$page_scripts = <<<'JAVASCRIPT'
function addExtensionPackageToCart(packageId, price) {
    const item = {
        id: 'domain-extension-' + packageId,
        name: packageId.split('--').pop().replace(/-/g, ' ').toUpperCase(),
        price: Number(price),
        type: 'domain'
    };
    if (window.shoppingCart && typeof window.shoppingCart.addItem === 'function') {
        window.shoppingCart.addItem(item);
    } else if (typeof window.addToCart === 'function') {
        window.addToCart(item);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('extensionSearch');
    const category = document.getElementById('categoryFilter');
    const price = document.getElementById('priceFilter');
    const cards = Array.from(document.querySelectorAll('.extension-result-card'));
    const empty = document.getElementById('extensionEmptyState');
    const count = document.getElementById('extensionResultCount');

    function matchesPrice(amount, range) {
        if (!range) return true;
        if (range === '0-20') return amount < 20;
        if (range === '20-50') return amount >= 20 && amount < 50;
        if (range === '50-100') return amount >= 50 && amount < 100;
        return amount >= 100;
    }

    function filterExtensions() {
        const term = search.value.trim().toLowerCase().replace(/^\./, '');
        let visible = 0;
        cards.forEach(function (card) {
            const tld = card.dataset.extension.replace(/^\./, '');
            const show = (!term || tld.includes(term)) &&
                (!category.value || card.dataset.category === category.value) &&
                matchesPrice(Number(card.dataset.price), price.value);
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        empty.style.display = visible ? 'none' : 'block';
        count.textContent = visible + ' extension' + (visible === 1 ? '' : 's') + ' shown';
    }

    search.addEventListener('input', filterExtensions);
    category.addEventListener('change', filterExtensions);
    price.addEventListener('change', filterExtensions);
    filterExtensions();
});
JAVASCRIPT;

// Page variables
$current_page = 'domains';
$page_title = 'Domain Extensions - New TLD Options | HiveNest Matrix';
$page_description = 'Explore new domain extensions and TLD options. Choose from hundreds of neural domain extensions for your digital identity.';
$page_keywords = 'domain extensions, TLD options, new domains, domain TLD, top level domains';

// SEO configuration (after including seo-meta.php)
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-email-global.jpg',
    'url' => 'https://hivenest.co.za/domains/extensions.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Domain Extensions',
        'description' => 'Comprehensive selection of domain extensions and TLD options',
        'serviceType' => 'Domain Registration Services'
    ])
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Neural Domains', 'url' => '/main-services/domains.php'],
    ['text' => 'Domain Extensions', 'url' => null]
];
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
$hero_title = 'DOMAIN<br><span class="cyber-text">EXTENSIONS</span><br>MATRIX';
$hero_subtitle = 'Explore hundreds of neural domain extensions. Find the perfect TLD for your digital identity and brand expansion across all dimensions.';
$hero_image = '../assets/images/heroes/hero-domain-server-blue.jpg';
$hero_alt = 'Domain Extensions Matrix';
include '../utilities/hero-minimal.php';
?>

<?php
// Breadcrumbs
?>

<!-- Extension Categories -->
<?php
include '../utilities/cyber-cards.php';
$extension_categories = [
    [
        'icon' => 'fas fa-globe',
        'title' => 'CLASSIC EXTENSIONS',
        'description' => '.com, .net, .org - Traditional and trusted domain extensions with universal recognition and maximum compatibility.'
    ],
    [
        'icon' => 'fas fa-rocket',
        'title' => 'TECH EXTENSIONS',
        'description' => '.tech, .io, .dev - Modern tech-focused extensions perfect for startups, developers, and innovation-driven businesses.'
    ],
    [
        'icon' => 'fas fa-building',
        'title' => 'BUSINESS EXTENSIONS',
        'description' => '.biz, .company, .enterprises - Professional extensions designed for businesses, corporations, and commercial ventures.'
    ],
    [
        'icon' => 'fas fa-paint-brush',
        'title' => 'CREATIVE EXTENSIONS',
        'description' => '.design, .art, .studio - Creative extensions for artists, designers, agencies, and creative professionals.'
    ],
    [
        'icon' => 'fas fa-flag',
        'title' => 'COUNTRY EXTENSIONS',
        'description' => '.co.za, .uk, .de - Country-specific extensions for local presence and regional market targeting.'
    ],
    [
        'icon' => 'fas fa-star',
        'title' => 'PREMIUM EXTENSIONS',
        'description' => '.luxury, .diamonds, .gold - Exclusive premium extensions for high-end brands and luxury markets.'
    ]
];

$grid_title = 'EXTENSION CATEGORIES';
$grid_subtitle = 'Choose from hundreds of neural domain extensions';
$grid_content = renderCyberCardsGrid($extension_categories);
include '../utilities/grid-section.php';
?>

<?php
// Popular Extensions Pricing
include '../utilities/pricing-cards.php';
include_once '../utilities/dynamic_pricing.php';
$popular_extensions = [
    [
        'name' => 'CLASSIC DOMAINS',
        'price' => '$8.99',
        'period' => '/year',
        'features' => [
            '.com - $12.99/year',
            '.net - $14.99/year',
            '.org - $13.99/year',
            '.co.za - $8.99/year',
            'Universal Recognition',
            'Maximum Compatibility',
            'SEO Friendly',
            'Global Trust'
        ],
        'cta_link' => '/domains/register.php',
        'cta_text' => 'REGISTER CLASSIC'
    ],
    [
        'name' => 'TECH DOMAINS',
        'price' => '$49.99',
        'period' => '/year',
        'features' => [
            '.tech - $49.99/year',
            '.io - $59.99/year',
            '.dev - $55.99/year',
            '.app - $45.99/year',
            'Tech Industry Focus',
            'Modern Appeal',
            'Startup Friendly',
            'Innovation Brand'
        ],
        'cta_link' => '/domains/register.php',
        'cta_text' => 'GET TECH DOMAIN',
        'featured' => true
    ],
    [
        'name' => 'PREMIUM DOMAINS',
        'price' => '$99.99',
        'period' => '/year',
        'features' => [
            '.luxury - $199.99/year',
            '.diamonds - $149.99/year',
            '.gold - $99.99/year',
            '.vip - $89.99/year',
            'Exclusive Branding',
            'Premium Positioning',
            'Luxury Market',
            'High-End Appeal'
        ],
        'cta_link' => '/domains/register.php',
        'cta_text' => 'GO PREMIUM'
    ]
];

// Products assigned to /domains/extensions.php in the admin are appended here.
$popular_extensions = array_merge(
    $popular_extensions,
    loadAssignedPagePricingPlans('/domains/extensions.php', 'addExtensionPackageToCart')
);

$grid_title = 'POPULAR EXTENSION PRICING';
$grid_subtitle = 'Most requested domain extensions with competitive pricing';
$grid_content = renderPricingGrid($popular_extensions);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<!-- Extension Search and Filter -->
<section class="section">
    <div class="container">
        <div class="text-center mb-8">
            <h2>EXTENSION SEARCH MATRIX</h2>
            <p class="hero-subtitle">Find the perfect extension for your digital identity</p>
        </div>
        
        <div class="cyber-card" style="max-width: 900px; margin: 0 auto;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                <input type="text" id="extensionSearch" placeholder="Search extensions (e.g., tech, design)" 
                       style="padding: 12px; border-radius: 6px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white;">
                
                <select id="categoryFilter" style="padding: 12px; border-radius: 6px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white;">
                    <option value="">All Categories</option>
                    <option value="classic">Classic Extensions</option>
                    <option value="tech">Tech Extensions</option>
                    <option value="business">Business Extensions</option>
                    <option value="creative">Creative Extensions</option>
                    <option value="country">Country Extensions</option>
                    <option value="premium">Premium Extensions</option>
                </select>
                
                <select id="priceFilter" style="padding: 12px; border-radius: 6px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white;">
                    <option value="">All Prices</option>
                    <option value="0-20">Under $20</option>
                    <option value="20-50">$20 - $50</option>
                    <option value="50-100">$50 - $100</option>
                    <option value="100+">$100+</option>
                </select>
            </div>

            <p id="extensionResultCount" style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;"></p>

            <div id="extensionResults" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <?php foreach ($domain_extensions as $extension):
                    $category = hivenest_extension_category($extension);
                    $tld = $extension['extension'];
                    $extension_price = (float)$extension['register_price'];
                ?>
                <article class="extension-result-card"
                         data-extension="<?php echo htmlspecialchars(strtolower($tld)); ?>"
                         data-category="<?php echo htmlspecialchars($category); ?>"
                         data-price="<?php echo $extension_price; ?>"
                         style="padding: 1rem; border: 1px solid rgba(0,255,255,.25); border-radius: 8px; background: rgba(0,0,0,.3);">
                    <h3 style="color: var(--cyber-neon-cyan); margin: 0 0 .5rem;"><?php echo htmlspecialchars($tld); ?></h3>
                    <p style="margin: 0 0 .75rem; color: white;">$<?php echo number_format($extension_price, 2); ?> / year</p>
                    <a class="btn btn-outline" style="width:100%; text-align:center;" href="register.php?tld=<?php echo rawurlencode($tld); ?>">REGISTER</a>
                </article>
                <?php endforeach; ?>
                <div id="extensionEmptyState" style="display:none; text-align: center; grid-column: 1 / -1; padding: 2rem; color: rgba(255,255,255,0.6);">
                    <p>No extensions match these filters.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Extension Benefits (Using Tabs)
include '../utilities/tabs.php';
$extension_tabs = [
    [
        'title' => 'BRANDING BENEFITS',
        'icon' => 'fas fa-bullhorn',
        'content' => '
            <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">Strategic Branding Advantages</h3>
            <div style="columns: 2; gap: 2rem;">
                <ul style="list-style: none; padding: 0; text-align: left;">
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Industry-Specific Identity</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Memorable Web Addresses</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Professional Credibility</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Brand Differentiation</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Global Recognition</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Market Positioning</li>
                </ul>
            </div>
        '
    ],
    [
        'title' => 'SEO IMPACT',
        'icon' => 'fas fa-chart-line',
        'content' => '
            <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">Search Engine Benefits</h3>
            <div style="columns: 2; gap: 2rem;">
                <ul style="list-style: none; padding: 0; text-align: left;">
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Keyword Relevance</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Geographic Targeting</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Industry Signals</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Click-Through Rates</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Local Search Benefits</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Trust Signals</li>
                </ul>
            </div>
        '
    ],
    [
        'title' => 'AVAILABILITY',
        'icon' => 'fas fa-check-circle',
        'content' => '
            <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">Domain Availability Advantages</h3>
            <div style="columns: 2; gap: 2rem;">
                <ul style="list-style: none; padding: 0; text-align: left;">
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ More Name Options</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Perfect Match Domains</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Short Domain Names</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Brand Exact Matches</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Creative Combinations</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Future-Proof Options</li>
                </ul>
            </div>
        '
    ]
];

$grid_title = 'EXTENSION ADVANTAGES';
$grid_subtitle = 'Why choosing the right extension matters for your digital strategy';
$grid_content = renderTabs($extension_tabs, 'extension-benefits', 0);
include '../utilities/grid-section.php';
?>

<?php
// New and Trending Extensions
$trending_extensions = [
    [
        'icon' => 'fas fa-fire',
        'title' => '.AI DOMAINS',
        'description' => 'Perfect for artificial intelligence companies, AI startups, and machine learning platforms. $89.99/year'
    ],
    [
        'icon' => 'fas fa-coins',
        'title' => '.CRYPTO DOMAINS',
        'description' => 'Ideal for cryptocurrency exchanges, blockchain projects, and crypto-related services. $79.99/year'
    ],
    [
        'icon' => 'fas fa-gamepad',
        'title' => '.GAMES DOMAINS',
        'description' => 'Gaming companies, esports teams, and gaming communities. Build your gaming empire. $45.99/year'
    ],
    [
        'icon' => 'fas fa-leaf',
        'title' => '.GREEN DOMAINS',
        'description' => 'Environmental organizations, sustainable businesses, and eco-friendly initiatives. $69.99/year'
    ]
];

$grid_title = 'NEW & TRENDING EXTENSIONS';
$grid_subtitle = 'Latest domain extensions for emerging industries';
$grid_content = renderCyberCardsGrid($trending_extensions);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<?php
// CTA Section
$cta_title = 'READY TO CLAIM YOUR PERFECT EXTENSION?';
$cta_subtitle = 'Choose from hundreds of domain extensions and establish your unique digital identity across all neural networks.';
$cta_buttons = '<a href="/domains/register.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">REGISTER DOMAIN</a><a href="/contact.php" class="btn btn-secondary" style="font-size: 1.2rem; padding: 20px 40px;">GET GUIDANCE</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
