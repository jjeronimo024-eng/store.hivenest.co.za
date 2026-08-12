<?php
// Include required utilities
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/domain_pricing_helper.php';
include_once '../utilities/dynamic_pricing.php';

// Load domain extensions from database
$domain_extensions = getAllDomainExtensions();
usort($domain_extensions, static function (array $a, array $b): int {
    return strcasecmp((string)$a['extension'], (string)$b['extension']);
});
$tld_pricing_js = generateTLDPricingJS();

// Support links from WHOIS/name suggestions containing a complete domain.
$requested_domain = strtolower(trim((string)($_GET['domain'] ?? '')));
$selected_tld = (string)($_GET['tld'] ?? '');
$prefill_domain = $requested_domain;
if ($requested_domain !== '') {
    $extensions_by_length = $domain_extensions;
    usort($extensions_by_length, static fn(array $a, array $b): int => strlen((string)$b['extension']) <=> strlen((string)$a['extension']));
    foreach ($extensions_by_length as $extension_row) {
        $candidate_tld = strtolower((string)$extension_row['extension']);
        if ($candidate_tld !== '' && str_ends_with($requested_domain, $candidate_tld)) {
            $prefill_domain = substr($requested_domain, 0, -strlen($candidate_tld));
            $selected_tld = $candidate_tld;
            break;
        }
    }
}

// Page variables
$current_page = 'domains';
$page_title = 'Register Neural Domain - Claim Digital Territory | HiveNest Matrix';
$page_description = 'Register Domain - Claim your digital territory across all dimensions with instant neural domain registration.';
$page_keywords = 'domain registration, neural domains, cyberpunk domains, register domain';

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Neural Domains', 'url' => '/main-services/domains.php'],
    ['text' => 'Register Domain', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = <<<'JAVASCRIPT'
// Domain name validation with visual feedback
function validateDomainInput(input) {
    const value = input.value.trim().toLowerCase();
    const validPattern = /^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/;
    
    // List of common TLDs to detect
    const commonTLDs = ['.com', '.net', '.org', '.co.za', '.io', '.tech', '.dev', '.app', '.biz', '.info', '.co', '.za', '.uk'];
    
    let isValid = true;
    let errorMsg = '';
    
    if (!value) {
        isValid = false;
        errorMsg = 'Domain name is required';
    } else if (value.includes('.')) {
        isValid = false;
        errorMsg = 'Please enter domain name only (without extension). Select extension from dropdown.';
    } else if (!validPattern.test(value)) {
        isValid = false;
        errorMsg = 'Invalid characters. Use only letters, numbers, and hyphens.';
    } else if (value.length < 2) {
        isValid = false;
        errorMsg = 'Domain name must be at least 2 characters.';
    }
    
    // Apply visual feedback
    if (value === '') {
        input.style.border = '1px solid rgba(0,255,255,0.3)';
        input.style.boxShadow = 'none';
    } else if (isValid) {
        input.style.border = '1px solid rgba(0,255,0,0.5)';
        input.style.boxShadow = '0 0 15px rgba(0,255,0,0.4), 0 0 25px rgba(0,255,0,0.2)';
    } else {
        input.style.border = '1px solid rgba(255,0,0,0.8)';
        input.style.boxShadow = '0 0 20px rgba(255,0,0,0.7), 0 0 35px rgba(255,0,0,0.5), 0 0 50px rgba(255,0,0,0.3)';
    }
    
    return { isValid, errorMsg, cleanValue: value };
}

// Add real-time validation
document.addEventListener('DOMContentLoaded', function() {
    const domainInput = document.getElementById('domain-input');
    if (domainInput) {
        domainInput.addEventListener('input', function() {
            validateDomainInput(this);
        });
    }
});

// Domain registration form handling - LIVE API
document.getElementById('domain-register-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const domainInput = document.getElementById('domain-input');
    const resultDiv = document.getElementById('domain-result');
    const validation = validateDomainInput(domainInput);
    
    // Validate before submitting
    if (!validation.isValid) {
        showDomainResultMessage(resultDiv, 'error', '<h4><i class="fas fa-times"></i> Domain name needs attention</h4><p>' + escapeHtml(validation.errorMsg) + '</p>');
        domainInput.focus();
        return;
    }
    
    const domain = validation.cleanValue;
    const tld = formData.get('tld');
    const supportedTlds = Array.from(document.querySelectorAll('#tld-options option')).map(option => option.value.toLowerCase());
    if (!tld || !supportedTlds.includes(tld.toLowerCase())) {
        showDomainResultMessage(resultDiv, 'error', '<h4><i class="fas fa-times"></i> Unsupported TLD</h4><p>Please select a supported TLD from the search list.</p>');
        document.getElementById('tld-input').focus();
        return;
    }
    const fullDomain = domain + tld;
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> SCANNING MATRIX...';
    submitBtn.disabled = true;
    
    try {
        // Call LIVE MyOrderBox API
        const response = await fetch('/api/domains_live.php?action=check-availability', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                domain: domain,
                tlds: [tld.replace(/^\./, '')]  // Remove leading dot if present
            })
        });
        
        const data = await response.json();
        
        if (data.success && data.results && data.results.length > 0) {
            const domainResult = data.results[0];
            
            resultDiv.style.display = 'block';
            
            const status = String(domainResult.status || 'unknown').toLowerCase();
            const supportHref = buildDomainSupportHref(fullDomain, status);
            const registeredStatuses = ['registered', 'regthroughus', 'regthroughothers', 'active', 'taken'];
            const premiumStatuses = ['premium', 'premium_domain', 'premium_quote_required'];
            const documentStatuses = ['document_required', 'documents_required', 'requires_documents', 'pending_document', 'pending_documents', 'manual_review', 'application_required', 'local_presence_required', 'trustee_required'];
            const reservedStatuses = ['reserved', 'blocked'];
            const price = Number(domainResult.price);

            if (domainResult.available && Number.isFinite(price) && price > 0 && !domainResult.requires_quote) {
                // Domain is AVAILABLE
                resultDiv.style.background = 'rgba(0,255,0,0.1)';
                resultDiv.style.border = '1px solid rgba(0,255,0,0.3)';
                resultDiv.style.color = 'var(--cyber-neon-green)';
                resultDiv.innerHTML = '<h4><i class="fas fa-check"></i> ' + fullDomain + ' is AVAILABLE!</h4>' +
                    '<p>Ready to register this neural domain and claim your digital territory.</p>' +
                    '<p style="margin-top: 1rem;"><strong>Price: $' + price.toFixed(2) + '/year</strong></p>' +
                    '<button type="button" class="btn btn-secondary" data-cart-once="true" onclick="addDomainToCart(\'' + fullDomain + '\', \'' + domainResult.tld + '\', ' + price.toFixed(2) + ')">' +
                    '<i class="fas fa-cart-plus"></i> ADD TO NEURAL CART</button>';
            } else {
                // Domain is not available for instant checkout. Do not call it
                // "registered" unless the registry/API actually says that.
                const registrar = domainResult.registrar || domainResult.registrar_name || domainResult.provider || 'Not disclosed by the availability service';

                if (registeredStatuses.includes(status)) {
                    resultDiv.style.background = 'rgba(255,0,0,0.1)';
                    resultDiv.style.border = '1px solid rgba(255,0,0,0.3)';
                    resultDiv.style.color = 'var(--cyber-neon-pink)';
                    resultDiv.innerHTML = '<h4><i class="fas fa-times"></i> ' + fullDomain + ' is REGISTERED</h4>' +
                        '<p>This domain is already registered.</p>' +
                        '<p style="margin-top: 0.5rem; font-size: 0.9em;"><strong>Status:</strong> ' + escapeHtml(domainResult.status || 'registered') + '</p>' +
                        '<p style="margin-top: 0.5rem; font-size: 0.9em;"><strong>Registrar:</strong> ' + escapeHtml(registrar) + '</p>' +
                        '<a class="btn btn-secondary" href="/domains/name-suggestion.php?keyword=' + encodeURIComponent(domain) + '">GENERATE ALTERNATIVE NAMES</a>';
                } else if (premiumStatuses.includes(status) || domainResult.requires_quote) {
                    resultDiv.style.background = 'rgba(255,165,0,0.1)';
                    resultDiv.style.border = '1px solid rgba(255,165,0,0.35)';
                    resultDiv.style.color = 'var(--cyber-neon-orange)';
                    resultDiv.innerHTML = '<h4><i class="fas fa-star"></i> ' + fullDomain + ' needs a live registry quote</h4>' +
                        '<p>This domain may be available, but the registry flagged it as premium or quote-based. It cannot be added to cart with the normal TLD price.</p>' +
                        '<p style="margin-top: 0.5rem; font-size: 0.9em;"><strong>Status:</strong> ' + escapeHtml(domainResult.status || 'premium quote required') + '</p>' +
                        '<a class="btn btn-secondary" href="' + supportHref + '">SEND TO SUPPORT FOR FEEDBACK</a>';
                } else if (documentStatuses.includes(status)) {
                    resultDiv.style.background = 'rgba(255,165,0,0.1)';
                    resultDiv.style.border = '1px solid rgba(255,165,0,0.35)';
                    resultDiv.style.color = 'var(--cyber-neon-orange)';
                    resultDiv.innerHTML = '<h4><i class="fas fa-file-signature"></i> ' + fullDomain + ' needs extra registry checks</h4>' +
                        '<p>This TLD may need documents, local presence, manual review, or provider confirmation before registration.</p>' +
                        '<p style="margin-top: 0.5rem; font-size: 0.9em;"><strong>Status:</strong> ' + escapeHtml(domainResult.status || 'documents required') + '</p>' +
                        '<a class="btn btn-secondary" href="' + supportHref + '">SEND TO SUPPORT FOR FEEDBACK</a>';
                } else if (reservedStatuses.includes(status)) {
                    resultDiv.style.background = 'rgba(255,165,0,0.1)';
                    resultDiv.style.border = '1px solid rgba(255,165,0,0.35)';
                    resultDiv.style.color = 'var(--cyber-neon-orange)';
                    resultDiv.innerHTML = '<h4><i class="fas fa-lock"></i> ' + fullDomain + ' is not available for instant registration</h4>' +
                        '<p>The registry marked this name as reserved or blocked.</p>' +
                        '<p style="margin-top: 0.5rem; font-size: 0.9em;"><strong>Status:</strong> ' + escapeHtml(domainResult.status || 'reserved') + '</p>' +
                        '<a class="btn btn-secondary" href="/domains/name-suggestion.php?keyword=' + encodeURIComponent(domain) + '">GENERATE ALTERNATIVE NAMES</a>';
                } else if (domainResult.available && (!Number.isFinite(price) || price <= 0)) {
                    resultDiv.style.background = 'rgba(255,165,0,0.1)';
                    resultDiv.style.border = '1px solid rgba(255,165,0,0.35)';
                    resultDiv.style.color = 'var(--cyber-neon-orange)';
                    resultDiv.innerHTML = '<h4><i class="fas fa-tags"></i> ' + fullDomain + ' needs price confirmation</h4>' +
                        '<p>The registry says this domain may be available, but no valid selling price was returned. We need to confirm it before checkout.</p>' +
                        '<p style="margin-top: 0.5rem; font-size: 0.9em;"><strong>Status:</strong> ' + escapeHtml(domainResult.status || 'price unavailable') + '</p>' +
                        '<a class="btn btn-secondary" href="' + supportHref + '">SEND TO SUPPORT FOR FEEDBACK</a>';
                } else {
                    resultDiv.style.background = 'rgba(255,165,0,0.1)';
                    resultDiv.style.border = '1px solid rgba(255,165,0,0.35)';
                    resultDiv.style.color = 'var(--cyber-neon-orange)';
                    resultDiv.innerHTML = '<h4><i class="fas fa-exclamation-triangle"></i> ' + fullDomain + ' could not be confirmed</h4>' +
                        '<p>The registry did not return a clear available/registered result. No registration assumption was made.</p>' +
                        '<p style="margin-top: 0.5rem; font-size: 0.9em;"><strong>Status:</strong> ' + escapeHtml(domainResult.status || 'unknown') + '</p>' +
                        '<a class="btn btn-secondary" href="' + supportHref + '">SEND TO SUPPORT FOR FEEDBACK</a>';
                }
            }
            requestAnimationFrame(() => resultDiv.scrollIntoView({ behavior: 'smooth', block: 'center' }));
        } else {
            throw new Error('Invalid API response');
        }
    } catch (error) {
        console.error('Domain check error:', error);
        resultDiv.style.display = 'block';
        resultDiv.style.background = 'rgba(255,165,0,0.1)';
        resultDiv.style.border = '1px solid rgba(255,165,0,0.3)';
        resultDiv.style.color = 'var(--cyber-neon-orange)';
        resultDiv.innerHTML = '<h4><i class="fas fa-exclamation-triangle"></i> Check Failed</h4>' +
            '<p>Unable to pull live registry data right now. Please send this to support for feedback.</p>' +
            '<a class="btn btn-secondary" href="' + buildDomainSupportHref(fullDomain || domain || '', 'availability check failed') + '">SEND TO SUPPORT FOR FEEDBACK</a>';
        requestAnimationFrame(() => resultDiv.scrollIntoView({ behavior: 'smooth', block: 'center' }));
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
});

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value || '');
    return div.innerHTML;
}

function buildDomainSupportHref(domain, reason) {
    const params = new URLSearchParams({
        domain: domain || '',
        reason: reason || 'domain support required'
    });
    return '/contact.php?' + params.toString();
}

function showDomainResultMessage(resultDiv, type, html) {
    if (!resultDiv) return;
    resultDiv.style.display = 'block';
    if (type === 'error') {
        resultDiv.style.background = 'rgba(255,0,0,0.1)';
        resultDiv.style.border = '1px solid rgba(255,0,0,0.3)';
        resultDiv.style.color = 'var(--cyber-neon-pink)';
    } else {
        resultDiv.style.background = 'rgba(0,255,255,0.1)';
        resultDiv.style.border = '1px solid rgba(0,255,255,0.3)';
        resultDiv.style.color = 'var(--cyber-neon-cyan)';
    }
    resultDiv.innerHTML = html;
    requestAnimationFrame(() => resultDiv.scrollIntoView({ behavior: 'smooth', block: 'center' }));
}

// Add to cart function
function addDomainToCart(domain, tld, price) {
    // Initialize cart from localStorage
    let cart = JSON.parse(localStorage.getItem('neuralCart') || '[]');
    
    // Get privacy checkbox state
    const privacyCheckbox = document.querySelector('input[name="privacy"]');
    const privacySelected = privacyCheckbox ? privacyCheckbox.checked : false;
    
    // Create domain cart item
    const domainItem = {
        id: 'domain_' + domain.replace(/\./g, '_'),
        name: 'Domain Registration: ' + domain,
        description: 'Domain registration (1 year)',
        category: 'domain',
        type: 'domain',
        tld: tld,
        price: parseFloat(price),
        quantity: 1,
        domain: domain,
        parent_product: 'domain',
        allows_addons: true
    };
    
    // Check if domain already exists and remove it (we'll re-add)
    cart = cart.filter(item => item.id !== domainItem.id);
    cart.push(domainItem);
    
    // Add privacy protection as separate item if selected
    if (privacySelected) {
        const privacyItem = {
            id: 'privacy_' + domain.replace(/\./g, '_'),
            name: 'Neural Privacy Shield: ' + domain,
            description: 'WHOIS privacy protection (1 year)',
            category: 'domain_addon',
            type: 'domain_privacy',
            price: 9.99,
            quantity: 1,
            domain: domain,
            parent_product: 'domain',
            parent_id: domainItem.id
        };
        
        // Remove existing privacy for this domain if exists
        cart = cart.filter(item => item.id !== privacyItem.id);
        cart.push(privacyItem);
    } else {
        // Remove privacy if exists but not selected
        cart = cart.filter(item => item.id !== ('privacy_' + domain.replace(/\./g, '_')));
    }
    
    // Save cart
    if (!Number.isFinite(domainItem.price)) {
        showDomainResultMessage(document.getElementById('domain-result'), 'error', '<h4><i class="fas fa-times"></i> Price could not be verified</h4><p>Please run the search again.</p>');
        return;
    }
    localStorage.setItem('neuralCart', JSON.stringify(cart));
    updateDomainCartBadge(cart);
    
    const included = privacySelected ? ' Domain privacy was added as a separate removable cart item.' : '';
    showDomainResultMessage(
        document.getElementById('domain-result'),
        'info',
        '<h4><i class="fas fa-check"></i> ' + escapeHtml(domain) + ' added to cart</h4>' +
        '<p>' + escapeHtml(included || 'Domain registration added to your cart.') + '</p>' +
        '<p>You can search and add more domains, or open the cart when you are ready.</p>' +
        '<div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap; margin-top:1rem;">' +
            '<button type="button" class="btn btn-secondary" onclick="prepareAnotherDomainSearch()"><i class="fas fa-search"></i> REGISTER ANOTHER DOMAIN</button>' +
            '<a class="btn btn-primary" href="/cart.php"><i class="fas fa-shopping-cart"></i> VIEW CART</a>' +
        '</div>'
    );
}

function updateDomainCartBadge(cart) {
    if (window.neuralCart && typeof window.neuralCart.updateDisplay === 'function') {
        window.neuralCart.items = cart;
        window.neuralCart.updateDisplay();
        return;
    }

    const count = cart.reduce((total, item) => total + Number(item.quantity || 1), 0);
    document.querySelectorAll('[id="cart-count"], [id="mobile-cart-count"], .cart-count, .cart-badge').forEach((badge) => {
        badge.textContent = count > 0 ? String(count) : '';
        badge.classList.toggle('is-zero', count <= 0);
        badge.classList.toggle('has-items', count > 0);
    });
}

function prepareAnotherDomainSearch() {
    const domainInput = document.getElementById('domain-input');
    const resultDiv = document.getElementById('domain-result');
    if (domainInput) {
        domainInput.value = '';
        domainInput.focus();
    }
    if (resultDiv) {
        resultDiv.style.display = 'none';
        resultDiv.innerHTML = '';
    }
}

function selectDomainPackage(planId, price) {
    const registration = document.getElementById('register');
    if (registration) registration.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
JAVASCRIPT;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include '../utilities/head.php'; ?>
</head>
<body>
<?php include '../utilities/nav.php'; ?>
<?php include '../utilities/mobile-menu.php'; ?>

<?php
// Hero Section with Domain Search Form
$hero_title = 'REGISTER<br><span class="cyber-text">NEURAL</span><br>DOMAIN';
$hero_subtitle = 'Claim your digital territory across all dimensions. Instant activation with quantum-encrypted security and multi-dimensional portals.';
$hero_image = '../assets/images/heroes/hero-domain-server-blue.jpg';
$hero_alt = 'Domain Registration Matrix';
include '../utilities/hero-minimal.php';
?>

<!-- Domain Search Form Section -->
<section id="register" class="section" style="padding-top: 2rem;">
    <div class="container">
        <div class="cyber-card" style="max-width: 800px; margin: 0 auto;">
            <h3 style="color: var(--cyber-neon-cyan); text-align: center; margin-bottom: 2rem;">DOMAIN SEARCH & REGISTRATION</h3>
            
            <form id="domain-register-form">
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                        Enter Domain Name
                    </label>
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <input 
                            type="text" 
                            name="domain" 
                            id="domain-input"
                            placeholder="yourdomain" 
                            value="<?php echo htmlspecialchars($prefill_domain, ENT_QUOTES); ?>"
                            style="flex: 1; min-width: 200px; padding: 16px; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white; font-size: 1.1rem;"
                            required
                        >
                        <input name="tld" id="tld-input" list="tld-options" autocomplete="off" placeholder="Type .com or another TLD" value="<?php echo htmlspecialchars($selected_tld, ENT_QUOTES); ?>" required style="min-width:265px;padding:16px;border-radius:8px;border:1px solid rgba(0,255,255,0.3);background:rgba(0,0,0,0.5);color:white;font-size:1.1rem;">
                        <datalist id="tld-options">
                            <?php 
                            foreach ($domain_extensions as $ext) {
                                echo sprintf(
                                    '<option value="%s" label="%s - $%s"></option>',
                                    htmlspecialchars($ext['extension']),
                                    htmlspecialchars($ext['extension']),
                                    number_format($ext['register_price'], 2)
                                );
                            }
                            ?>
                        </datalist>
                    </div>
                </div>
                
                <!-- Registration Period -->
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                        Registration Period
                    </label>
                    <select name="period" style="width: 100%; padding: 16px; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white; font-size: 1.1rem;">
                        <option value="1">1 Year</option>
                        <option value="2">2 Years (5% discount)</option>
                        <option value="3">3 Years (10% discount)</option>
                        <option value="5">5 Years (15% discount)</option>
                    </select>
                </div>
                
                <!-- Domain Privacy -->
                <div style="margin-bottom: 2rem;">
                    <label style="display: flex; align-items: center; color: rgba(255,255,255,0.8);">
                        <input type="checkbox" name="privacy" style="margin-right: 0.5rem;" checked>
                        Add Neural Privacy Shield (+$9.99/year) - Hide personal information from WHOIS database
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 16px; font-size: 1.1rem;">
                    <i class="fas fa-rocket" style="margin-right: 0.5rem;"></i>
                    REGISTER DOMAIN
                </button>
                
                <div id="domain-result" style="margin-top: 1rem; padding: 1rem; border-radius: 8px; display: none;"></div>
            </form>
        </div>
    </div>
</section>

<?php
// Domain Registration Benefits
include '../utilities/cyber-cards.php';
$registration_benefits = [
    [
        'icon' => 'fas fa-bolt',
        'title' => 'INSTANT ACTIVATION',
        'description' => 'Domain activated immediately after registration with quantum-speed propagation across all dimensions.'
    ],
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'NEURAL PRIVACY',
        'description' => 'Optional privacy protection shields your personal information from the WHOIS database scanner bots.'
    ],
    [
        'icon' => 'fas fa-sync',
        'title' => 'AUTO RENEWAL',
        'description' => 'Automatic renewal protection ensures your domain never expires and stays under your control.'
    ],
    [
        'icon' => 'fas fa-dns',
        'title' => 'DNS MANAGEMENT',
        'description' => 'Complete DNS control panel with advanced record management and subdomain configuration.'
    ],
    [
        'icon' => 'fas fa-headset',
        'title' => 'NEURAL SUPPORT',
        'description' => '24/7 expert support for domain transfers, DNS changes, and technical configuration assistance.'
    ],
    [
        'icon' => 'fas fa-lock',
        'title' => 'DOMAIN LOCK',
        'description' => 'Registry lock protection prevents unauthorized transfers and maintains domain security protocols.'
    ]
];

$grid_title = 'DOMAIN REGISTRATION BENEFITS';
$grid_subtitle = 'Everything included with your neural domain registration';
$grid_content = renderCyberCardsGrid($registration_benefits);
include '../utilities/grid-section.php';
?>

<?php
// Domain Extensions Pricing
include '../utilities/pricing-cards.php';
$domain_extensions = [
    [
        'name' => 'POPULAR DOMAINS',
        'price' => '$8.99',
        'period' => '/year',
        'features' => [
            '.com - $12.99/year',
            '.co.za - $8.99/year',
            '.net - $14.99/year',
            '.org - $13.99/year',
            'Free WHOIS Privacy',
            'DNS Management',
            'Domain Forwarding',
            'Email Forwarding'
        ],
        'cta_link' => '#register',
        'cta_text' => 'REGISTER NOW'
    ],
    [
        'name' => 'TECH DOMAINS',
        'price' => '$49.99',
        'period' => '/year',
        'features' => [
            '.io - $59.99/year',
            '.tech - $49.99/year',
            '.dev - $55.99/year',
            '.app - $45.99/year',
            'Premium DNS',
            'Advanced Security',
            'Priority Support',
            'SSL Certificate Ready'
        ],
        'cta_link' => '#register',
        'cta_text' => 'GET TECH DOMAIN',
        'featured' => true
    ],
    [
        'name' => 'BUSINESS DOMAINS',
        'price' => '$29.99',
        'period' => '/year',
        'features' => [
            '.biz - $29.99/year',
            '.info - $24.99/year',
            '.pro - $39.99/year',
            '.mobi - $34.99/year',
            'Business Email Setup',
            'Marketing Tools',
            'SEO Optimization',
            'Analytics Dashboard'
        ],
        'cta_link' => '#register',
        'cta_text' => 'BUSINESS READY'
    ]
];
$domain_extensions = loadProductPricingPlans([
    'product_id' => 1,
    'product_slug' => 'domain-registration',
    'cart_function' => 'selectDomainPackage',
    'fallback_plans' => $domain_extensions,
]);

$grid_title = 'DOMAIN EXTENSION PRICING';
$grid_subtitle = 'Choose from hundreds of domain extensions';
$grid_content = renderPricingGrid($domain_extensions);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<?php
// CTA Section
$cta_title = 'READY TO CLAIM YOUR DIGITAL TERRITORY?';
$cta_subtitle = 'Register your neural domain today and establish your presence across all digital dimensions.';
$cta_buttons = '<a href="/contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">GET STARTED</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
