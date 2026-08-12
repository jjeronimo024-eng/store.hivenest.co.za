<?php
// Include required utilities
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/dynamic_pricing.php';

// Page variables
$current_page = 'servers';
$page_title = 'Windows Dedicated Servers - Full RDP Access | HiveNest Matrix';
$page_description = 'Windows Dedicated Servers - Powerful Windows Server hosting with RDP access, MSSQL, and full Windows Server features from HiveNest.';
$page_keywords = 'windows dedicated servers, rdp access, mssql server, windows server 2022, iis hosting';

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Neural Servers', 'url' => '../main-services/servers.php'],
    ['text' => 'Windows Dedicated', 'url' => null]
];

// Live DB pricing plus any additional active products assigned to this page.
$windows_plans = loadProductPricingPlans([
    'product_slug' => 'windows-vps',
    'cart_function' => 'selectServerPlan',
]);

// Page-specific JavaScript for cart functionality
$page_scripts = "
// Queue for cart actions before system is ready
window.cartActionQueue = window.cartActionQueue || [];
window.cartSystemReady = false;

function selectServerPlan(planSlug, planName, price) {
    if (price === undefined) {
        price = planName;
        planName = String(planSlug).replace(/-/g, ' ').toUpperCase();
    }

    const domainInput = document.getElementById('windows-server-primary-domain');
    const domainOptionInput = document.querySelector('input[name=\"windows_server_domain_option\"]:checked');
    const primaryDomain = domainInput ? domainInput.value.trim().toLowerCase() : '';
    const domainOption = domainOptionInput ? domainOptionInput.value : 'existing';

    if (!primaryDomain) {
        console.warn('Please enter the primary domain or hostname for this Windows server before adding it to cart.');
        if (domainInput) domainInput.focus();
        return false;
    }

    if (!/^[a-z0-9][a-z0-9-]*(\.[a-z0-9][a-z0-9-]*)+$/i.test(primaryDomain)) {
        console.warn('Please enter a valid domain or hostname, for example server.yourdomain.com.');
        if (domainInput) domainInput.focus();
        return false;
    }

    const cartAction = function() {
        if (window.shoppingCart || window.addToCart) {
            const item = {
                id: 'server-windows-' + String(planSlug).toLowerCase().replace(/ /g, '-'),
                name: 'Windows Server: ' + planName,
                description: 'Windows Server: ' + planName + ' for ' + primaryDomain,
                price: Number(price),
                type: 'server',
                category: 'windows-vps',
                domain: primaryDomain,
                domain_name: primaryDomain,
                primary_domain: primaryDomain,
                domain_option: domainOption
            };
            
            if (window.shoppingCart) {
                window.shoppingCart.addItem(item);
            } else if (window.addToCart) {
                window.addToCart(item);
            }
            
            console.log(planName + ' Windows server plan added to cart for ' + primaryDomain + '!');
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
        
        if ((window.shoppingCart || window.addToCart) && cartAction()) {
            clearInterval(retryInterval);
            // Process any queued actions
            window.cartSystemReady = true;
            while (window.cartActionQueue.length > 0) {
                const action = window.cartActionQueue.shift();
                action();
            }
        } else if (retryCount >= maxRetries) {
            clearInterval(retryInterval);
            console.error('Shopping cart not initialized after ' + maxRetries + ' attempts');
            console.warn('Cart system is taking longer than expected. Please refresh the page and try again.');
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
    'image' => 'assets/images/heroes/hero-domain-server-blue.jpg',
    'url' => 'https://hivenest.co.za/servers/windows.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Windows Dedicated Servers',
        'description' => 'Enterprise Windows Server hosting with RDP access and MSSQL',
        'serviceType' => 'Windows Server Hosting'
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
$hero_title = '<span class="cyber-text">WINDOWS</span><br>DEDICATED SERVERS';
$hero_subtitle = 'Enterprise Windows Server hosting with full RDP access, MSSQL, and complete Windows Server ecosystem for your applications.';
$hero_image = '../assets/images/heroes/hero-domain-server-blue.jpg';
$hero_alt = 'Windows Dedicated Servers';
include '../utilities/hero-minimal.php';
?>

<?php
// Windows Server Plans - Retrieved from database above
include '../utilities/pricing-cards.php';
?>

<section class="section" style="background: rgba(0, 0, 0, 0.88); border-top: 1px solid rgba(0, 255, 255, 0.18); border-bottom: 1px solid rgba(0, 255, 255, 0.18);">
    <div class="container">
        <div class="cyber-card" style="max-width: 900px; margin: 0 auto; padding: 2rem; border-color: var(--primary-cyan); box-shadow: 0 0 30px rgba(0, 255, 255, 0.16);">
            <h2 class="cyber-text" style="text-align:center; margin-bottom: 0.75rem;">PRIMARY DOMAIN / HOSTNAME</h2>
            <p style="text-align:center; color: rgba(255,255,255,0.78); margin-bottom: 1.5rem;">
                Windows server provisioning needs a primary domain or hostname before it can be sent to MyOrderBox.
            </p>
            <label for="windows-server-primary-domain" style="display:block; color: var(--primary-cyan); font-weight: 700; margin-bottom: 0.5rem;">Domain or hostname for this Windows server</label>
            <input
                type="text"
                id="windows-server-primary-domain"
                placeholder="server.yourdomain.com"
                autocomplete="off"
                style="width:100%; padding: 16px 18px; border:1px solid var(--primary-cyan); border-radius:8px; background:rgba(0,0,0,0.72); color:#fff; font-size:1rem; margin-bottom: 1rem;"
            >
            <div style="display:flex; flex-wrap:wrap; gap: 1rem; color: rgba(255,255,255,0.84);">
                <label style="display:flex; align-items:center; gap:0.45rem;"><input type="radio" name="windows_server_domain_option" value="existing" checked> I already own this domain</label>
                <label style="display:flex; align-items:center; gap:0.45rem;"><input type="radio" name="windows_server_domain_option" value="register_new"> I also want to register this domain</label>
                <label style="display:flex; align-items:center; gap:0.45rem;"><input type="radio" name="windows_server_domain_option" value="dns_later"> I will point DNS later</label>
            </div>
        </div>
    </div>
</section>

<?php
$grid_title = 'WINDOWS DEDICATED SERVERS';
$grid_subtitle = 'Professional Windows Server hosting solutions';
$grid_content = renderPricingGrid($windows_plans);
include '../utilities/grid-section.php';
?>

<?php
// Windows Server Features
include '../utilities/cyber-cards.php';
$windows_features = [
    [
        'icon' => 'fab fa-microsoft',
        'title' => 'WINDOWS SERVER 2022',
        'description' => 'Latest Windows Server with enhanced security, hybrid cloud capabilities, and improved performance features.'
    ],
    [
        'icon' => 'fas fa-desktop',
        'title' => 'FULL RDP ACCESS',
        'description' => 'Complete remote desktop access with administrator privileges for full server control and management.'
    ],
    [
        'icon' => 'fas fa-database',
        'title' => 'MSSQL SERVER',
        'description' => 'Microsoft SQL Server with Management Studio, reporting services, and integration services included.'
    ],
    [
        'icon' => 'fas fa-server',
        'title' => 'IIS WEB SERVER',
        'description' => 'Internet Information Services with ASP.NET support, URL rewriting, and application hosting capabilities.'
    ],
    [
        'icon' => 'fas fa-cloud',
        'title' => 'HYPER-V VIRTUALIZATION',
        'description' => 'Native Hyper-V support for virtual machine hosting and containerization solutions.'
    ],
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'WINDOWS DEFENDER',
        'description' => 'Built-in security with Windows Defender, firewall, and advanced threat protection features.'
    ]
];

$grid_title = 'WINDOWS SERVER FEATURES';
$grid_subtitle = 'Enterprise Windows Server capabilities';
$grid_content = renderCyberCardsGrid($windows_features);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<?php
// Windows Server Editions (Using Tabs)
include '../utilities/tabs.php';
$edition_tabs = [
    [
        'title' => 'STANDARD',
        'icon' => 'fab fa-windows',
        'content' => '
            <h3 class="service-title">Windows Server 2022 Standard</h3>
            <div style="columns: 2; gap: 2rem;">
                <ul style="list-style: none; padding: 0; text-align: left;">
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ 2 Virtual Machines</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Hyper-V Role</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Active Directory</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ DNS and DHCP</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ File and Print Services</li>
                </ul>
            </div>
        '
    ],
    [
        'title' => 'DATACENTER',
        'icon' => 'fab fa-windows',
        'content' => '
            <h3 class="service-title">Windows Server 2022 Datacenter</h3>
            <div style="columns: 2; gap: 2rem;">
                <ul style="list-style: none; padding: 0; text-align: left;">
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Unlimited Virtual Machines</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Advanced Hyper-V</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Storage Spaces Direct</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Network Controller</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Shielded VMs</li>
                </ul>
            </div>
        '
    ],
    [
        'title' => 'SERVER CORE',
        'icon' => 'fab fa-windows',
        'content' => '
            <h3 class="service-title">Windows Server Core</h3>
            <div style="columns: 2; gap: 2rem;">
                <ul style="list-style: none; padding: 0; text-align: left;">
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Minimal Installation</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Command Line Interface</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Reduced Attack Surface</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Lower Resource Usage</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ PowerShell Management</li>
                </ul>
            </div>
        '
    ],
    [
        'title' => 'CUSTOM',
        'icon' => 'fab fa-windows',
        'content' => '
            <h3 class="service-title">Custom Configuration</h3>
            <div style="columns: 2; gap: 2rem;">
                <ul style="list-style: none; padding: 0; text-align: left;">
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Custom OS Installation</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Application Pre-installation</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Security Hardening</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Performance Tuning</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Backup Configuration</li>
                </ul>
            </div>
        '
    ]
];

$grid_title = 'WINDOWS SERVER EDITIONS';
$grid_subtitle = 'Choose the right Windows Server edition for your needs';
$grid_content = renderTabs($edition_tabs, 'windows-editions', 0);
include '../utilities/grid-section.php';
?>

<?php
// Microsoft Technologies
$microsoft_tech = [
    [
        'icon' => 'fas fa-code',
        'title' => '.NET FRAMEWORK',
        'description' => 'Full .NET Framework and .NET Core support with ASP.NET for web application development and hosting.'
    ],
    [
        'icon' => 'fas fa-database',
        'title' => 'SQL SERVER',
        'description' => 'Microsoft SQL Server with reporting services, integration services, and analysis services included.'
    ],
    [
        'icon' => 'fas fa-users',
        'title' => 'ACTIVE DIRECTORY',
        'description' => 'Domain controller capabilities with Active Directory for user and computer management in enterprise environments.'
    ],
    [
        'icon' => 'fas fa-exchange-alt',
        'title' => 'EXCHANGE SERVER',
        'description' => 'Microsoft Exchange Server for enterprise email, calendaring, and collaboration solutions.'
    ],
    [
        'icon' => 'fas fa-share-alt',
        'title' => 'SHAREPOINT',
        'description' => 'SharePoint Server for collaboration, document management, and business intelligence applications.'
    ],
    [
        'icon' => 'fas fa-terminal',
        'title' => 'POWERSHELL',
        'description' => 'PowerShell scripting and automation with Windows Management Framework for system administration.'
    ]
];

$grid_title = 'MICROSOFT TECHNOLOGIES';
$grid_subtitle = 'Complete Microsoft ecosystem support';
$grid_content = renderCyberCardsGrid($microsoft_tech);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<?php
// Windows Server Management (Three Column Layout)
$mgmt_col1 = '
    <div style="text-align: center;">
        <i class="fas fa-desktop" style="font-size: 3rem; color: var(--cyber-neon-cyan); margin-bottom: 1rem;"></i>
        <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">REMOTE DESKTOP</h3>
        <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 1rem;">
            Full GUI access via Remote Desktop Protocol with administrator privileges.
        </p>
        <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.7);">
            <li style="margin: 0.5rem 0;">• Administrator Access</li>
            <li style="margin: 0.5rem 0;">• Multiple User Sessions</li>
            <li style="margin: 0.5rem 0;">• Clipboard Sharing</li>
            <li style="margin: 0.5rem 0;">• Drive Redirection</li>
        </ul>
    </div>
';

$mgmt_col2 = '
    <div style="text-align: center;">
        <i class="fas fa-cogs" style="font-size: 3rem; color: var(--cyber-neon-green); margin-bottom: 1rem;"></i>
        <h3 style="color: var(--cyber-neon-green); margin-bottom: 1rem;">SERVER MANAGER</h3>
        <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 1rem;">
            Windows Server Manager for role and feature management.
        </p>
        <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.7);">
            <li style="margin: 0.5rem 0;">• Role Installation</li>
            <li style="margin: 0.5rem 0;">• Feature Management</li>
            <li style="margin: 0.5rem 0;">• Service Monitoring</li>
            <li style="margin: 0.5rem 0;">• Event Log Access</li>
        </ul>
    </div>
';

$mgmt_col3 = '
    <div style="text-align: center;">
        <i class="fas fa-shield-alt" style="font-size: 3rem; color: var(--cyber-neon-pink); margin-bottom: 1rem;"></i>
        <h3 style="color: var(--cyber-neon-pink); margin-bottom: 1rem;">WINDOWS UPDATES</h3>
        <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 1rem;">
            Automated Windows Updates with WSUS support for enterprise environments.
        </p>
        <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.7);">
            <li style="margin: 0.5rem 0;">• Automatic Updates</li>
            <li style="margin: 0.5rem 0;">• Security Patches</li>
            <li style="margin: 0.5rem 0;">• Update Scheduling</li>
            <li style="margin: 0.5rem 0;">• Rollback Support</li>
        </ul>
    </div>
';

$grid_title = 'WINDOWS SERVER MANAGEMENT';
$grid_subtitle = 'Comprehensive Windows Server management tools';
$grid_content = '
    <div class="cyber-card" style="max-width: 900px; margin: 0 auto;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
            ' . $mgmt_col1 . $mgmt_col2 . $mgmt_col3 . '
        </div>
    </div>
';
include '../utilities/grid-section.php';
?>

<?php
// CTA Section
$cta_title = 'READY FOR WINDOWS POWER?';
$cta_subtitle = 'Get enterprise Windows Server hosting with full RDP access and complete Microsoft ecosystem.';
$cta_buttons = '<a href="../contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">ORDER SERVER</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
