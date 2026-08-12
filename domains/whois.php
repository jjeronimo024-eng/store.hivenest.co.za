<?php
// Include required utilities
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/seo-meta.php';

// Page variables
$current_page = 'domains';
$page_title = 'Neural Whois Lookup - Domain Investigation | HiveNest Matrix';
$page_description = 'Whois Lookup - Investigate domain ownership across all digital dimensions with quantum-powered analysis.';
$page_keywords = 'whois lookup, domain search, domain ownership, cyberpunk whois, neural lookup';

// SEO configuration (after including seo-meta.php)
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-domain-network.jpg',
    'url' => 'https://hivenest.co.za/domains/whois.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Neural Whois Lookup',
        'description' => 'Advanced domain investigation with quantum-powered analysis',
        'serviceType' => 'Domain Investigation Services'
    ])
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Neural Domains', 'url' => '/main-services/domains.php'],
    ['text' => 'Whois Lookup', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = "
// Whois search functionality
document.getElementById('whois-search-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const domain = e.target.domain.value.trim();
    if (!domain) return;
    
    performWhoisLookup(domain);
});

function performWhoisLookup(domain) {
    const resultsSection = document.getElementById('whois-results');
    const resultsContainer = document.getElementById('whois-data');
    
    // Show loading state
    resultsContainer.innerHTML = '<div class=\"cyber-card\" style=\"text-align: center;\">' +
        '<i class=\"fas fa-spinner fa-spin\" style=\"font-size: 2rem; color: var(--cyber-neon-cyan); margin-bottom: 1rem;\"></i>' +
        '<h3 style=\"color: var(--cyber-neon-cyan);\">SCANNING NEURAL NETWORKS...</h3>' +
        '<p style=\"color: rgba(255,255,255,0.7);\">Investigating ' + domain + ' across all dimensions</p>' +
        '</div>';
    resultsSection.style.display = 'block';
    requestAnimationFrame(() => resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' }));
    
    fetch('/api/domain-intelligence.php?action=whois&domain=' + encodeURIComponent(domain))
        .then(response => response.json().then(data => ({ response, data })))
        .then(({ response, data }) => {
            if (!response.ok || !data.success) throw new Error(data.error || 'WHOIS lookup failed');
            displayWhoisResults(data);
        })
        .catch(() => {
            // RDAP/WHOIS uses not-found for unregistered domains. Confirm that
            // state with the live registrar API before offering registration.
            return checkLiveAvailability(domain)
                .then(displayWhoisResults)
                .catch(error => {
                    resultsContainer.innerHTML = '<div class=\"cyber-card\" style=\"color:var(--cyber-neon-pink)\">' + error.message + '</div>';
                    resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
        });
}

function splitRegistrableDomain(value) {
    const parts = value.toLowerCase().replace(/^https?:\/\//, '').split('/')[0].split('.');
    const multi = ['co.za','org.za','net.za','web.za','co.uk','org.uk','me.uk','com.au','net.au','org.au','in.net'];
    const lastTwo = parts.slice(-2).join('.');
    const suffixCount = multi.includes(lastTwo) ? 2 : 1;
    return { domain: parts.slice(0, -suffixCount).join('.'), tld: parts.slice(-suffixCount).join('.') };
}

async function checkLiveAvailability(fullDomain) {
    const parsed = splitRegistrableDomain(fullDomain);
    if (!parsed.domain || parsed.domain.includes('.')) throw new Error('Enter a registrable domain without a subdomain.');
    const response = await fetch('/api/domains_live.php?action=check-availability', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({domain: parsed.domain, tlds: [parsed.tld]})
    });
    const payload = await response.json();
    if (!response.ok || !payload.success || !payload.results?.length) {
        throw new Error(payload.error || 'Registry information is currently unavailable. No availability assumption was made.');
    }
    const result = payload.results[0];
    if (!result.available) {
        if (result.status === 'regthroughus' || result.status === 'regthroughothers') {
            return {
                domain: result.domain,
                status: 'REGISTERED',
                registrar: 'Not disclosed by the availability service',
                registrationDate: 'Not disclosed',
                expirationDate: 'Not disclosed',
                nameservers: []
            };
        }
        throw new Error('Registry information is currently unavailable. No availability assumption was made.');
    }
    return {
        domain: result.domain,
        status: 'AVAILABLE',
        message: 'This domain is available for registration.',
        price: Number(result.price),
        tld: result.tld
    };
}

function addWhoisDomainToCart(domain, tld, price) {
    const normalizedTld = tld.startsWith('.') ? tld : '.' + tld;
    const item = {
        id: 'domain_' + domain.replace(/\./g, '_'),
        name: 'Domain Registration: ' + domain,
        description: 'Domain registration (1 year)',
        category: 'domain',
        type: 'domain',
        tld: normalizedTld,
        price: Number(price),
        quantity: 1,
        domain: domain,
        parent_product: 'domain',
        allows_addons: true
    };
    if (!Number.isFinite(item.price) || !window.shoppingCart?.addItem(item)) return;
    window.location.href = '/cart.php';
}

function displayWhoisResults(data) {
    const resultsContainer = document.getElementById('whois-data');
    
    if (data.status === 'AVAILABLE') {
        const registrationUrl = '/domains/register.php?domain=' + encodeURIComponent(data.domain) + '&tld=.' + encodeURIComponent(data.tld);
        resultsContainer.innerHTML = '<div class=\"cyber-card\" style=\"border: 2px solid var(--cyber-neon-green);\">' +
            '<div style=\"text-align: center; margin-bottom: 2rem;\">' +
            '<i class=\"fas fa-check-circle\" style=\"font-size: 3rem; color: var(--cyber-neon-green); margin-bottom: 1rem;\"></i>' +
            '<h3 style=\"color: var(--cyber-neon-green); font-size: 1.5rem;\">' + data.domain + ' IS AVAILABLE</h3>' +
            '<p style=\"color: rgba(255,255,255,0.8); margin: 1rem 0;\">' + data.message + '</p>' +
            '</div>' +
            '<div style=\"text-align: center;\">' +
            '<a href=\"' + registrationUrl + '\" class=\"btn btn-primary\" style=\"font-size: 1.1rem; padding: 1rem 2rem; margin:.35rem;\">' +
            '<i class=\"fas fa-rocket\" style=\"margin-right: 0.5rem;\"></i>' +
            'REGISTER THIS DOMAIN' +
            '</a>' +
            '<button type=\"button\" class=\"btn btn-secondary\" data-cart-once=\"true\" style=\"margin:.35rem\" onclick=\"addWhoisDomainToCart(' + JSON.stringify(data.domain) + ', ' + JSON.stringify(data.tld) + ', ' + Number(data.price) + ')\"><i class=\"fas fa-cart-plus\"></i> ADD TO CART</button>' +
            '</div>' +
            '</div>';
    } else {
        const nameserverList = (data.nameservers || []).map(ns => '<li style=\"color: rgba(255,255,255,0.8); margin: 0.25rem 0;\">' + ns + '</li>').join('');
        const privacyColor = data.privacy === true ? 'var(--cyber-neon-green)' :
            (data.privacy === false ? 'var(--cyber-neon-pink)' : 'var(--cyber-neon-cyan)');
        const privacyText = data.privacy === true ? 'ENABLED' :
            (data.privacy === false ? 'DISABLED' : 'NOT DISCLOSED');
        
        resultsContainer.innerHTML = '<div class=\"cyber-card\">' +
            '<div style=\"display: grid; gap: 2rem;\">' +
            '<div>' +
            '<h3 style=\"color: var(--cyber-neon-cyan); margin-bottom: 1rem; font-size: 1.3rem;\">' +
            '<i class=\"fas fa-globe\" style=\"margin-right: 0.5rem;\"></i>' +
            'DOMAIN STATUS: REGISTERED' +
            '</h3>' +
            '<div style=\"background: rgba(255,0,255,0.1); padding: 1rem; border-radius: 8px; border: 1px solid rgba(255,0,255,0.3);\">' +
            '<p style=\"margin: 0; color: var(--cyber-neon-pink); font-weight: bold;\">' + data.domain + '</p>' +
            '</div>' +
            '</div>' +
            '<div style=\"display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;\">' +
            '<div>' +
            '<h4 style=\"color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;\">REGISTRAR</h4>' +
            '<p style=\"color: rgba(255,255,255,0.8);\">' + data.registrar + '</p>' +
            '</div>' +
            '<div>' +
            '<h4 style=\"color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;\">REGISTRATION DATE</h4>' +
            '<p style=\"color: rgba(255,255,255,0.8);\">' + data.registrationDate + '</p>' +
            '</div>' +
            '<div>' +
            '<h4 style=\"color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;\">EXPIRATION DATE</h4>' +
            '<p style=\"color: rgba(255,255,255,0.8);\">' + data.expirationDate + '</p>' +
            '</div>' +
            '<div>' +
            '<h4 style=\"color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;\">PRIVACY PROTECTION</h4>' +
            '<p style=\"color: ' + privacyColor + ';\">' + privacyText + '</p>' +
            '</div>' +
            '</div>' +
            '<div>' +
            '<h4 style=\"color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;\">NAMESERVERS</h4>' +
            '<ul style=\"list-style: none; padding: 0;\">' + nameserverList + '</ul>' +
            '</div>' +
            '<div style=\"text-align: center;\">' +
            '<a href=\"/domains/name-suggestion.php?keyword=' + encodeURIComponent(data.domain.split('.')[0]) + '\" class=\"btn btn-secondary\" style=\"margin-right: 1rem;\">' +
            'SUGGEST SIMILAR DOMAINS' +
            '</a>' +
            '<a href=\"/domains/register.php\" class=\"btn btn-primary\">' +
            'FIND AVAILABLE DOMAIN' +
            '</a>' +
            '</div>' +
            '</div>' +
            '</div>';
    }
    document.getElementById('whois-results').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function suggestSimilar(domain) {
    // Redirect to name suggestion tool with prefilled data
    window.location.href = '/domains/name-suggestion.php?keyword=' + domain.split('.')[0];
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

<!-- Hero Section with Whois Search -->
<section class="hero">
    <img src="assets/images/heroes/hero-domain-network.jpg" alt="Neural Data Analysis" class="hero-background">
    
    <div class="hero-content">
        <div class="hero-text">
            <h1 class="hero-title">
                NEURAL<br>
                <span class="cyber-text">WHOIS</span><br>
                INVESTIGATION
            </h1>
            <p class="hero-subtitle">
                Investigate domain ownership across all digital dimensions. 
                Quantum-powered analysis reveals hidden data patterns and neural connections.
            </p>
            
            <!-- Whois Search -->
            <div class="domain-search-container" style="margin: 2rem 0;">
                <form id="whois-search-form" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <input 
                        type="text" 
                        name="domain" 
                        placeholder="Enter domain to investigate..." 
                        style="flex: 1; min-width: 300px; padding: 16px; border-radius: 8px; border: none; font-size: 1.1rem; background: rgba(0,0,0,0.5); color: white; border: 1px solid rgba(0,255,255,0.3);"
                        required
                    >
                    <button type="submit" class="btn btn-secondary" style="padding: 16px 32px;">SCAN NEURAL NET</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php
// Breadcrumbs
?>

<!-- Whois Results Section -->
<section id="whois-results" class="section" style="display: none; scroll-margin-top: 100px;">
    <div class="container">
        <div class="text-center mb-8">
            <h2>INVESTIGATION RESULTS</h2>
            <p class="hero-subtitle">Neural network analysis complete</p>
        </div>
        
        <div id="whois-data" style="max-width: 900px; margin: 0 auto;">
            <!-- Results will be populated here -->
        </div>
    </div>
</section>

<?php
// Domain Investigation Features
include '../utilities/cyber-cards.php';
$investigation_features = [
    [
        'icon' => 'fas fa-search',
        'title' => 'DEEP SCAN',
        'description' => 'Comprehensive domain investigation with registrar details, contact information, and registration history.'
    ],
    [
        'icon' => 'fas fa-server',
        'title' => 'DNS ANALYSIS',
        'description' => 'Complete DNS record analysis including nameservers, MX records, and neural network configuration.'
    ],
    [
        'icon' => 'fas fa-history',
        'title' => 'TEMPORAL TRACKING',
        'description' => 'Historical domain data tracking with registration timeline and ownership change detection.'
    ],
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'PRIVACY DETECTION',
        'description' => 'Identify privacy protection services and hidden ownership patterns across dimensions.'
    ],
    [
        'icon' => 'fas fa-globe',
        'title' => 'GLOBAL NETWORK',
        'description' => 'Access domain data from registries worldwide with real-time updates and accurate information.'
    ],
    [
        'icon' => 'fas fa-chart-line',
        'title' => 'ANALYTICS DASHBOARD',
        'description' => 'Visual data representation with charts, graphs, and detailed reports for domain intelligence.'
    ]
];

$grid_title = 'INVESTIGATION PROTOCOLS';
$grid_subtitle = 'Advanced domain analysis capabilities';
$grid_content = renderCyberCardsGrid($investigation_features);
include '../utilities/grid-section.php';
?>

<?php
// Whois Tools and Services
include '../utilities/pricing-cards.php';
$whois_services = [
    [
        'name' => 'BASIC LOOKUP',
        'price' => 'FREE',
        'period' => '',
        'features' => [
            'Standard whois data',
            'Registration information',
            'Expiration dates',
            'Nameserver details',
            'Basic domain status',
            'Registrar information'
        ],
        'cta_link' => '#whois-search-form',
        'cta_text' => 'START SCAN'
    ],
    [
        'name' => 'ADVANCED ANALYSIS',
        'price' => '$9.99',
        'period' => '/month',
        'features' => [
            'All basic features',
            'Historical data tracking',
            'DNS record analysis',
            'Security threat detection',
            'Bulk lookup capability',
            'API access available',
            'Export reports (PDF/CSV)',
            'Email notifications'
        ],
        'cta_link' => '/contact.php',
        'cta_text' => 'UPGRADE NOW',
        'featured' => true
    ],
    [
        'name' => 'ENTERPRISE',
        'price' => '$49.99',
        'period' => '/month',
        'features' => [
            'All advanced features',
            'Unlimited lookups',
            'Priority support',
            'Custom integrations',
            'White-label solutions',
            'Dedicated account manager',
            'SLA guarantees',
            'Custom reporting'
        ],
        'cta_link' => '/contact.php',
        'cta_text' => 'CONTACT SALES'
    ]
];

$grid_title = 'WHOIS INVESTIGATION PLANS';
$grid_subtitle = 'Choose your level of domain intelligence';
$grid_content = renderPricingGrid($whois_services);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<?php
// CTA Section
$cta_title = 'READY TO INVESTIGATE THE DIGITAL MATRIX?';
$cta_subtitle = 'Use our neural whois lookup to uncover domain secrets and make informed decisions about your digital assets.';
$cta_buttons = '<a href="/contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">GET STARTED</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
