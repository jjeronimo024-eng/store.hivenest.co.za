<?php
// Include required utilities
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/dynamic_pricing.php';

// Page variables
$current_page = 'hosting';
$GLOBALS['hivenest_assigned_products_rendered'] = true;
$GLOBALS['hivenest_disable_footer_products'] = true;
$page_title = 'Windows Web Hosting - ASP.NET & MSSQL | HiveNest Matrix';
$page_description = 'Windows Web Hosting - Professional ASP.NET, MSSQL, and IIS hosting with Plesk control panel from HiveNest.';
$page_keywords = 'windows hosting, asp.net hosting, mssql hosting, windows web hosting, IIS hosting';

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Quantum Servers', 'url' => '../main-services/hosting.php'],
    ['text' => 'Windows Hosting', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = "
// Queue for cart actions before system is ready
window.cartActionQueue = window.cartActionQueue || [];
window.cartSystemReady = false;

function showWindowsHostingDomainMessage(message) {
    const messageBox = document.getElementById('windows-hosting-domain-message');
    if (!messageBox) return;
    messageBox.textContent = message;
    messageBox.style.display = message ? 'block' : 'none';
}

function getWindowsHostingSelectedTerm() {
    const termInput = document.querySelector('input[name=\"windows_hosting_term_months\"]:checked');
    const months = termInput ? parseInt(termInput.value, 10) : 3;
    const validMonths = [3, 6, 12, 24];
    const safeMonths = validMonths.includes(months) ? months : 3;
    const labels = {
        3: '3 Months',
        6: '6 Months',
        12: '12 Months',
        24: '24 Months'
    };
    const billingCycles = {
        3: 'quarterly',
        6: 'semi_annually',
        12: 'annually',
        24: 'biennially'
    };

    return {
        months: safeMonths,
        label: labels[safeMonths],
        billingCycle: billingCycles[safeMonths]
    };
}

function addWindowsHostingToCart(planSlug, planName, price) {
    if (price === undefined) {
        price = planName;
        planName = String(planSlug).replace(/-/g, ' ').toUpperCase();
    }

    const domainInput = document.getElementById('windows-hosting-primary-domain');
    const domainOptionInput = document.querySelector('input[name=\"windows_hosting_domain_option\"]:checked');
    const primaryDomain = domainInput ? domainInput.value.trim().toLowerCase() : '';
    const domainOption = domainOptionInput ? domainOptionInput.value : 'existing';
    const selectedTerm = getWindowsHostingSelectedTerm();
    const unitPrice = Number(price);
    const totalPrice = unitPrice * selectedTerm.months;
    const productSku = String(planSlug).includes('--')
        ? String(planSlug)
        : 'multi-domain-windows-hosting--' + planSlug;

    if (!primaryDomain) {
        showWindowsHostingDomainMessage('Please enter the primary domain for this Windows hosting package before adding it to cart.');
        if (domainInput) domainInput.focus();
        return false;
    }

    if (!/^[a-z0-9][a-z0-9-]*(\.[a-z0-9][a-z0-9-]*)+$/i.test(primaryDomain)) {
        showWindowsHostingDomainMessage('Please enter a valid domain name, for example yourdomain.com.');
        if (domainInput) domainInput.focus();
        return false;
    }

    showWindowsHostingDomainMessage('');

    const cartAction = function() {
        if (window.addToCart) {
            window.addToCart({
                id: productSku,
                name: 'Windows Hosting: ' + planName + ' (' + selectedTerm.label + ')',
                description: 'Windows Hosting: ' + planName + ' for ' + primaryDomain + ' - ' + selectedTerm.label,
                price: totalPrice,
                type: 'hosting',
                category: 'windows-hosting',
                billing_cycle: selectedTerm.billingCycle,
                term_months: selectedTerm.months,
                monthly_price: unitPrice,
                domain: primaryDomain,
                domain_name: primaryDomain,
                primary_domain: primaryDomain,
                domain_option: domainOption,
                product_config: {
                    sku: productSku,
                    domain: primaryDomain,
                    primary_domain: primaryDomain,
                    domain_option: domainOption,
                    term_months: selectedTerm.months,
                    billing_cycle: selectedTerm.billingCycle,
                    monthly_price: unitPrice
                }
            });
            return true;
        }
        return false;
    };
    
    // Try to execute immediately
    if (window.cartSystemReady && cartAction()) {
        return;
    }
    
    // Queue the action and retry
    window.cartActionQueue.push(cartAction);
    
    // Set up a retry mechanism
    const maxRetries = 50;
    let retryCount = 0;
    
    const retryInterval = setInterval(function() {
        retryCount++;
        
        if (window.addToCart && cartAction()) {
            clearInterval(retryInterval);
            // Process any queued actions
            window.cartSystemReady = true;
            while (window.cartActionQueue.length > 0) {
                const action = window.cartActionQueue.shift();
                action();
            }
        } else if (retryCount >= maxRetries) {
            clearInterval(retryInterval);
            console.error('Cart system not loaded after ' + maxRetries + ' attempts');
            showWindowsHostingDomainMessage('Cart system is taking longer than expected. Please refresh the page and try again.');
        }
    }, 100);
}
";
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
    'image' => 'assets/images/heroes/hero-security-circuit.jpg',
    'url' => 'https://hivenest.co.za/hosting/windows.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Windows Web Hosting',
        'description' => 'Professional Windows hosting with ASP.NET and MSSQL support',
        'serviceType' => 'Windows Hosting Services'
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
$hero_title = '<span class="cyber-text">WINDOWS</span><br>NEURAL HOSTING';
$hero_subtitle = 'Professional Windows hosting with ASP.NET, MSSQL, IIS, and Plesk control panel. Built for Microsoft technology stack excellence.';
$hero_image = '../assets/images/heroes/hero-domain-server-blue.jpg';
$hero_alt = 'Windows Neural Hosting';
include '../utilities/hero-minimal.php';
?>

<?php
// Windows Hosting Plans
include '../utilities/pricing-cards.php';
?>

<section class="section" style="background: rgba(0, 0, 0, 0.88); border-top: 1px solid rgba(0, 255, 255, 0.18); border-bottom: 1px solid rgba(0, 255, 255, 0.18);">
    <div class="container">
        <div class="cyber-card" style="max-width: 900px; margin: 0 auto; padding: 2rem; border-color: var(--primary-cyan); box-shadow: 0 0 30px rgba(0, 255, 255, 0.16);">
            <h2 class="cyber-text" style="text-align:center; margin-bottom: 0.75rem;">PRIMARY DOMAIN</h2>
            <p style="text-align:center; color: rgba(255,255,255,0.78); margin-bottom: 1.5rem;">
                Windows hosting needs a primary domain before it can be provisioned through MyOrderBox.
            </p>
            <label for="windows-hosting-primary-domain" style="display:block; color: var(--primary-cyan); font-weight: 700; margin-bottom: 0.5rem;">Domain for this Windows hosting package</label>
            <input
                type="text"
                id="windows-hosting-primary-domain"
                placeholder="yourdomain.com"
                autocomplete="off"
                style="width:100%; padding: 16px 18px; border:1px solid var(--primary-cyan); border-radius:8px; background:rgba(0,0,0,0.72); color:#fff; font-size:1rem; margin-bottom: 1rem;"
            >
            <div style="display:flex; flex-wrap:wrap; gap: 1rem; color: rgba(255,255,255,0.84);">
                <label style="display:flex; align-items:center; gap:0.45rem;"><input type="radio" name="windows_hosting_domain_option" value="existing" checked> I already own this domain</label>
                <label style="display:flex; align-items:center; gap:0.45rem;"><input type="radio" name="windows_hosting_domain_option" value="register_new"> I also want to register this domain</label>
                <label style="display:flex; align-items:center; gap:0.45rem;"><input type="radio" name="windows_hosting_domain_option" value="dns_later"> I will point DNS later</label>
            </div>
            <div style="margin-top: 1.5rem;">
                <h3 style="color: var(--primary-cyan); font-size: 1rem; margin-bottom: 0.85rem;">Hosting term</h3>
                <p style="color: rgba(255,255,255,0.72); margin-bottom: 1rem;">
                    Select the MyOrderBox provisioning term for this Windows hosting package.
                </p>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.85rem;">
                    <label class="cyber-card" style="padding: 0.9rem; cursor:pointer; border-color: rgba(0,255,255,0.35);">
                        <input type="radio" name="windows_hosting_term_months" value="3" checked style="margin-right:0.4rem;"> 3 Months
                    </label>
                    <label class="cyber-card" style="padding: 0.9rem; cursor:pointer; border-color: rgba(0,255,255,0.35);">
                        <input type="radio" name="windows_hosting_term_months" value="6" style="margin-right:0.4rem;"> 6 Months
                    </label>
                    <label class="cyber-card" style="padding: 0.9rem; cursor:pointer; border-color: rgba(0,255,255,0.35);">
                        <input type="radio" name="windows_hosting_term_months" value="12" style="margin-right:0.4rem;"> 12 Months
                    </label>
                    <label class="cyber-card" style="padding: 0.9rem; cursor:pointer; border-color: rgba(0,255,255,0.35);">
                        <input type="radio" name="windows_hosting_term_months" value="24" style="margin-right:0.4rem;"> 24 Months
                    </label>
                </div>
            </div>
            <div
                id="windows-hosting-domain-message"
                style="display:none; margin-top: 1rem; padding: 0.85rem 1rem; border: 1px solid rgba(255, 0, 255, 0.65); border-radius: 8px; background: rgba(255, 0, 255, 0.12); color: var(--cyber-neon-pink); text-align: center;"
            ></div>
        </div>
    </div>
</section>

<?php
$windows_plans = [
    [
        'name' => 'WINDOWS BASIC',
        'price' => '$12',
        'period' => '/mo',
        'features' => [
            '3 Websites',
            '25GB SSD Storage',
            '250GB Bandwidth',
            'ASP.NET 4.8 & Core',
            'MSSQL 2019 Database',
            'IIS 10 Web Server',
            'Plesk Control Panel',
            'Free SSL Certificate'
        ],
        'cta_link' => '#',
        'cta_text' => 'ADD TO CART',
        'onclick' => 'addWindowsHostingToCart(\'windows-basic\', \'WINDOWS BASIC\', 12)'
    ],
    [
        'name' => 'WINDOWS PRO',
        'price' => '$25',
        'period' => '/mo',
        'features' => [
            '10 Websites',
            '75GB SSD Storage',
            'Unlimited Bandwidth',
            'ASP.NET 4.8 & Core',
            '5 MSSQL Databases',
            'IIS 10 Web Server',
            'Advanced Plesk Panel',
            'Free SSL Certificate',
            'Daily Backups',
            'Priority Support'
        ],
        'cta_link' => '#',
        'cta_text' => 'ADD TO CART',
        'onclick' => 'addWindowsHostingToCart(\'windows-pro\', \'WINDOWS PRO\', 25)',
        'featured' => true
    ],
    [
        'name' => 'WINDOWS ENTERPRISE',
        'price' => '$45',
        'period' => '/mo',
        'features' => [
            'Unlimited Websites',
            '200GB SSD Storage',
            'Unlimited Bandwidth',
            'ASP.NET 4.8 & Core',
            'Unlimited MSSQL Databases',
            'IIS 10 Web Server',
            'Premium Plesk Panel',
            'Wildcard SSL Certificate',
            'Real-time Backups',
            '24/7 Phone Support',
            'Dedicated IP'
        ],
        'cta_link' => '#',
        'cta_text' => 'ADD TO CART',
        'onclick' => 'addWindowsHostingToCart(\'windows-enterprise\', \'WINDOWS ENTERPRISE\', 45)'
    ]
];

$grid_title = 'WINDOWS HOSTING PLANS';
$grid_subtitle = 'Professional Windows hosting for Microsoft technology stack';
$windows_plans = loadProductPricingPlans([
    'product_id'     => 27,
    'product_slug'   => 'multi-domain-windows-hosting',
    'cart_function'  => 'addWindowsHostingToCart',
    'fallback_plans' => $windows_plans,
    'include_assigned_products' => true,
]);
$grid_content = renderPricingGrid($windows_plans);
include '../utilities/grid-section.php';
$GLOBALS['hivenest_assigned_products_rendered'] = true;
?>

<?php
// Technical Specifications
include '../utilities/two-column-section.php';
$tech_specs = [
    [
        'title' => 'SUPPORTED TECHNOLOGIES',
        'items' => [
            'ASP.NET Framework 4.8',
            'ASP.NET Core 6.0 & 7.0',
            'C# and VB.NET',
            'MVC and Web API',
            'Blazor Server & WebAssembly',
            'Entity Framework',
            'Crystal Reports',
            'Classic ASP Support'
        ]
    ],
    [
        'title' => 'DATABASE & TOOLS',
        'items' => [
            'Microsoft SQL Server 2019',
            'SQL Server Management Studio',
            'MySQL 8.0 Support',
            'phpMyAdmin Access',
            'ODBC Connections',
            'ADO.NET Data Access',
            'Connection Pooling',
            'Database Backup Tools'
        ]
    ]
];

$grid_title = 'TECHNICAL SPECIFICATIONS';
$grid_subtitle = 'Complete Microsoft technology stack support';
$grid_content = renderTwoColumnLists($tech_specs);
include '../utilities/grid-section.php';
?>

<!-- Free Migration Service -->
<section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
    <div class="container">
        <div class="cyber-card" style="max-width: 800px; margin: 0 auto;">
            <div style="text-align: center; margin-bottom: 2rem;">
                <i class="fas fa-rocket" style="font-size: 3rem; color: var(--cyber-neon-cyan); margin-bottom: 1rem;"></i>
                <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">FREE MIGRATION SERVICE</h3>
                <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 2rem;">
                    Our expert team will migrate your websites, databases, and applications 
                    from your current hosting provider to our Windows hosting platform at no cost.
                </p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                <div style="text-align: center;">
                    <i class="fas fa-file-export" style="font-size: 2rem; color: var(--cyber-neon-green); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-green); margin-bottom: 0.5rem;">WEBSITE MIGRATION</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Complete website files and configurations</p>
                </div>
                
                <div style="text-align: center;">
                    <i class="fas fa-database" style="font-size: 2rem; color: var(--cyber-neon-green); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-green); margin-bottom: 0.5rem;">DATABASE MIGRATION</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">MSSQL and MySQL database transfer</p>
                </div>
                
                <div style="text-align: center;">
                    <i class="fas fa-envelope" style="font-size: 2rem; color: var(--cyber-neon-green); margin-bottom: 0.5rem;"></i>
                    <h4 style="color: var(--cyber-neon-green); margin-bottom: 0.5rem;">EMAIL MIGRATION</h4>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Email accounts and settings</p>
                </div>
            </div>
            
            <div style="text-align: center;">
                <a href="../contact.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                    REQUEST FREE MIGRATION
                </a>
            </div>
        </div>
    </div>
</section>

<?php
// CTA Section
$cta_title = 'READY TO POWER YOUR APPLICATIONS WITH WINDOWS?';
$cta_subtitle = 'Launch your ASP.NET applications with our professional Windows hosting platform and Microsoft technology stack.';
$cta_buttons = '<a href="../contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">GET WINDOWS HOSTING</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
