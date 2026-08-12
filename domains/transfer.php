<?php
// Include required utilities
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/seo-meta.php';

// Page variables
$current_page = 'domains';
$page_title = 'Domain Transfer - Move Your Domain to HiveNest Matrix';
$page_description = 'Transfer Your Domain to HiveNest - Secure, fast domain transfers with competitive pricing and expert support.';
$page_keywords = 'domain transfer, move domain, domain migration, transfer to hivenest';

// SEO configuration (after including seo-meta.php)
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-domain-server-green.jpg',
    'url' => 'https://hivenest.co.za/domains/transfer.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Domain Transfer',
        'description' => 'Secure and fast domain transfer services to HiveNest',
        'serviceType' => 'Domain Transfer Services'
    ])
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Neural Domains', 'url' => '/main-services/domains.php'],
    ['text' => 'Domain Transfer', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = "
// Domain transfer form handling
document.getElementById('domain-transfer-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const domain = formData.get('domain');
    const authCode = formData.get('auth_code');
    const extendRegistration = formData.get('extend_registration');
    const privacyProtection = formData.get('privacy_protection');
    
    // Show loading state
    const submitBtn = this.querySelector('button[type=\"submit\"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class=\"fas fa-spinner fa-spin\"></i> PROCESSING TRANSFER...';
    submitBtn.disabled = true;
    
    // Simulate transfer validation (replace with actual API call)
    setTimeout(() => {
        const resultDiv = document.getElementById('transfer-result');
        resultDiv.style.display = 'block';
        resultDiv.style.background = 'rgba(0,255,0,0.1)';
        resultDiv.style.border = '1px solid rgba(0,255,0,0.3)';
        resultDiv.style.color = 'var(--cyber-neon-green)';
        resultDiv.innerHTML = '<h4><i class=\"fas fa-check\"></i> ' + domain.toUpperCase() + ' TRANSFER INITIATED!</h4>' +
            '<p>Your domain transfer request has been submitted. You\'ll receive confirmation within 24 hours.</p>' +
            '<button class=\"btn btn-secondary\" onclick=\"addTransferToCartAndCheckout()\">' +
            '<i class=\"fas fa-shopping-cart\"></i> CONTINUE TO CHECKOUT' +
            '</button>';
        
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 2000);
});

// Function to add domain transfer items to cart
function addTransferToCartAndCheckout() {
    const formData = new FormData(document.getElementById('domain-transfer-form'));
    const domain = formData.get('domain');
    const extendRegistration = formData.get('extend_registration');
    const privacyProtection = formData.get('privacy_protection');
    
    // Initialize cart from localStorage
    let cart = JSON.parse(localStorage.getItem('neuralCart') || '[]');
    
    // Base transfer price
    const transferPrice = 12.99;
    
    // Create base domain transfer item
    const transferItem = {
        id: 'domain_transfer_' + domain.replace(/\\./g, '_'),
        name: 'Domain Transfer: ' + domain,
        description: 'Domain transfer with authorization code',
        category: 'domain',
        type: 'domain_transfer',
        price: transferPrice,
        quantity: 1,
        domain: domain,
        parent_product: 'domain_transfer',
        allows_addons: true
    };
    
    // Remove existing transfer item for this domain
    cart = cart.filter(item => item.id !== transferItem.id);
    cart.push(transferItem);
    
    // Add registration extension as separate item if selected
    if (extendRegistration) {
        const extendItem = {
            id: 'extend_registration_' + domain.replace(/\\./g, '_'),
            name: 'Extended Registration: ' + domain,
            description: 'Extend domain by 1 year',
            category: 'domain_addon',
            type: 'domain_extend',
            price: 12.99,
            quantity: 1,
            domain: domain,
            parent_product: 'domain_transfer',
            parent_id: transferItem.id
        };
        
        cart = cart.filter(item => item.id !== extendItem.id);
        cart.push(extendItem);
    } else {
        cart = cart.filter(item => item.id !== ('extend_registration_' + domain.replace(/\\./g, '_')));
    }
    
    // Add privacy protection as separate item if selected
    if (privacyProtection) {
        const privacyItem = {
            id: 'privacy_' + domain.replace(/\\./g, '_'),
            name: 'Neural Privacy Protection: ' + domain,
            description: 'WHOIS privacy protection (1 year)',
            category: 'domain_addon',
            type: 'domain_privacy',
            price: 9.99,
            quantity: 1,
            domain: domain,
            parent_product: 'domain_transfer',
            parent_id: transferItem.id
        };
        
        cart = cart.filter(item => item.id !== privacyItem.id);
        cart.push(privacyItem);
    } else {
        cart = cart.filter(item => item.id !== ('privacy_' + domain.replace(/\\./g, '_')));
    }
    
    // Save cart
    localStorage.setItem('neuralCart', JSON.stringify(cart));
    
    // Redirect to cart
    window.location.href = '/cart.php';
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
// Hero Section with Domain Transfer Form
$hero_title = 'TRANSFER<br><span class="cyber-text">DOMAIN</span><br>PROTOCOL';
$hero_subtitle = 'Move your domains to HiveNest for enhanced security, competitive pricing, and quantum-level management tools.';
$hero_image = '../assets/images/heroes/hero-domain-server-green.jpg';
$hero_alt = 'Domain Transfer Matrix';
include '../utilities/hero-minimal.php';
?>

<?php
// Breadcrumbs
?>

<!-- Domain Transfer Form Section -->
<section class="section" style="padding-top: 2rem;">
    <div class="container">
        <div class="cyber-card" style="max-width: 800px; margin: 0 auto;">
            <h3 style="color: var(--cyber-neon-cyan); text-align: center; margin-bottom: 2rem;">DOMAIN TRANSFER PROTOCOL</h3>
            
            <form id="domain-transfer-form">
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                        Domain to Transfer
                    </label>
                    <input 
                        type="text" 
                        name="domain" 
                        id="domain-input"
                        placeholder="yourdomain.com" 
                        style="width: 100%; padding: 16px; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white; font-size: 1.1rem;"
                        required
                    >
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                        Authorization Code (EPP Code)
                    </label>
                    <input 
                        type="text" 
                        name="auth_code" 
                        placeholder="Get this from your current registrar"
                        style="width: 100%; padding: 16px; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white; font-size: 1.1rem;"
                        required
                    >
                    <small style="color: rgba(255,255,255,0.7); margin-top: 0.5rem; display: block;">
                        Contact your current registrar to obtain the authorization code for your domain.
                    </small>
                </div>
                
                <!-- Transfer Options -->
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                        Transfer Options
                    </label>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: flex; align-items: center; color: rgba(255,255,255,0.8);">
                            <input type="checkbox" name="extend_registration" style="margin-right: 0.5rem;" checked>
                            Extend registration by 1 year (+$12.99) - Recommended
                        </label>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: flex; align-items: center; color: rgba(255,255,255,0.8);">
                            <input type="checkbox" name="privacy_protection" style="margin-right: 0.5rem;">
                            Add Neural Privacy Protection (+$9.99/year)
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 16px; font-size: 1.1rem;">
                    <i class="fas fa-exchange-alt" style="margin-right: 0.5rem;"></i>
                    INITIATE TRANSFER
                </button>
                
                <div id="transfer-result" style="margin-top: 1rem; padding: 1rem; border-radius: 8px; display: none;"></div>
            </form>
        </div>
    </div>
</section>

<?php
// Transfer Benefits
include '../utilities/cyber-cards.php';
$transfer_benefits = [
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'SECURE TRANSFER',
        'description' => 'Encrypted transfer protocol with quantum-level security. Your domain remains secure throughout the entire process.'
    ],
    [
        'icon' => 'fas fa-dollar-sign',
        'title' => 'COMPETITIVE PRICING',
        'description' => 'Lower renewal rates and transparent pricing. Save money while getting better service and features.'
    ],
    [
        'icon' => 'fas fa-cog',
        'title' => 'ADVANCED MANAGEMENT',
        'description' => 'Quantum DNS management, subdomain control, and neural-level domain configuration tools.'
    ],
    [
        'icon' => 'fas fa-headset',
        'title' => 'EXPERT SUPPORT',
        'description' => '24/7 transfer support from certified domain specialists. We handle the technical details for you.'
    ],
    [
        'icon' => 'fas fa-bolt',
        'title' => 'FAST PROCESSING',
        'description' => 'Most transfers complete within 5-7 days. Track progress with real-time updates and notifications.'
    ],
    [
        'icon' => 'fas fa-lock',
        'title' => 'TRANSFER PROTECTION',
        'description' => 'Transfer lock protection and registry security features to prevent unauthorized domain transfers.'
    ]
];

$grid_title = 'TRANSFER ADVANTAGES';
$grid_subtitle = 'Why transfer your domains to HiveNest Matrix';
$grid_content = renderCyberCardsGrid($transfer_benefits);
include '../utilities/grid-section.php';
?>

<?php
// Transfer Process
include '../utilities/pricing-cards.php';
$transfer_process = [
    [
        'name' => 'STEP 1',
        'price' => 'INITIATE',
        'period' => '',
        'features' => [
            'Enter domain name',
            'Provide authorization code',
            'Select transfer options',
            'Submit transfer request'
        ],
        'cta_link' => '#',
        'cta_text' => 'START HERE'
    ],
    [
        'name' => 'STEP 2',
        'price' => 'VERIFY',
        'period' => '',
        'features' => [
            'Email verification sent',
            'Approve transfer request',
            'Current registrar notified',
            'Transfer begins processing'
        ],
        'cta_link' => '#',
        'cta_text' => 'AUTOMATIC',
        'featured' => true
    ],
    [
        'name' => 'STEP 3',
        'price' => 'COMPLETE',
        'period' => '',
        'features' => [
            'Transfer completes (5-7 days)',
            'Domain active at HiveNest',
            'Full management access',
            'Welcome email with details'
        ],
        'cta_link' => '#',
        'cta_text' => 'FINISHED'
    ]
];

$grid_title = 'TRANSFER PROCESS';
$grid_subtitle = 'Simple 3-step domain transfer protocol';
$grid_content = renderPricingGrid($transfer_process);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<?php
// CTA Section
$cta_title = 'READY TO TRANSFER YOUR DOMAINS?';
$cta_subtitle = 'Move your domains to HiveNest and experience superior management, security, and support.';
$cta_buttons = '<a href="/contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">GET TRANSFER HELP</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>