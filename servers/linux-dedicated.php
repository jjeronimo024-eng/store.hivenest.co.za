<?php
// Include required utilities
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/dynamic_pricing.php';

// Page variables
$current_page = 'servers';
$page_title = 'Linux Dedicated Servers - Full Root Access | HiveNest Matrix';
$page_description = 'Linux Dedicated Servers - Powerful dedicated Linux servers with full root access, SSD storage, and 24/7 support from HiveNest.';
$page_keywords = 'linux dedicated servers, root access, ubuntu server, centos hosting, debian server';

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Neural Servers', 'url' => '../main-services/servers.php'],
    ['text' => 'Linux Dedicated', 'url' => null]
];

// Keep the three core plans visible if the cache is missing or stale.
$linux_fallback_plans = [
    [
        'name' => 'SERVER BASIC',
        'price' => '$99',
        'period' => '/mo',
        'features' => [
            'Intel Xeon E3-1230', '16GB DDR4 RAM', '1TB SSD Storage',
            '10TB Bandwidth', 'Full Root Access', 'Basic DDoS Protection',
            'Standard Support'
        ],
        'cta_link' => '#',
        'cta_text' => 'ADD TO CART',
        'onclick' => "selectServerPlan('SERVER BASIC', 99)",
        'featured' => false
    ],
    [
        'name' => 'SERVER PROFESSIONAL',
        'price' => '$199',
        'period' => '/mo',
        'features' => [
            'Intel Xeon E5-2620', '32GB DDR4 RAM', '2x 1TB SSD RAID',
            '20TB Bandwidth', 'Full Root Access', 'Advanced DDoS Protection',
            'Priority Support', 'Free cPanel/WHM'
        ],
        'cta_link' => '#',
        'cta_text' => 'ADD TO CART',
        'onclick' => "selectServerPlan('SERVER PROFESSIONAL', 199)",
        'featured' => true
    ],
    [
        'name' => 'SERVER ENTERPRISE',
        'price' => '$399',
        'period' => '/mo',
        'features' => [
            'Dual Intel Xeon E5-2690', '64GB DDR4 RAM', '4x 1TB SSD RAID 10',
            'Unlimited Bandwidth', 'Full Root Access', 'Enterprise DDoS Protection',
            '24/7 Priority Support', 'Free cPanel/WHM', 'Dedicated Support Manager'
        ],
        'cta_link' => '#',
        'cta_text' => 'ADD TO CART',
        'onclick' => "selectServerPlan('SERVER ENTERPRISE', 399)",
        'featured' => false
    ]
];

$linux_plans = loadProductPricingPlans([
    'product_id' => 31,
    'product_slug' => 'dedicated-server-linux',
    'cart_function' => 'selectServerPlan',
    'fallback_plans' => $linux_fallback_plans,
]);

// Page-specific JavaScript for cart functionality
$page_scripts = "
function selectServerPlan(planName, price) {
    console.log('selectServerPlan called with:', planName, price);
    if (window.addToCart) {
        window.addToCart({
            id: 'server-linux-' + planName.toLowerCase().replace(/ /g, '-'),
            name: 'Linux Dedicated Server: ' + planName,
            price: price,
            type: 'server'
        });
    } else {
        console.error('Cart system not loaded');
        console.warn('Cart system not ready. Please refresh the page and try again.');
    }
}

console.log('selectServerPlan function defined:', typeof selectServerPlan);
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
    'url' => 'https://hivenest.co.za/servers/linux-dedicated.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Linux Dedicated Servers',
        'description' => 'High-performance dedicated Linux servers with full root access',
        'serviceType' => 'Dedicated Server Hosting'
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
$hero_title = '<span class="cyber-text">LINUX</span><br>DEDICATED SERVERS';
$hero_subtitle = 'Unleash maximum performance with dedicated Linux servers. Full root access, enterprise hardware, and unmetered bandwidth.';
$hero_image = '../assets/images/heroes/hero-domain-server-green.jpg';
$hero_alt = 'Linux Dedicated Servers';
include '../utilities/hero-minimal.php';
?>

<?php
// Linux Server Plans - Retrieved from database above
include '../utilities/pricing-cards.php';

$grid_title = 'LINUX DEDICATED SERVERS';
$grid_subtitle = 'High-performance dedicated Linux servers for every need';
$grid_content = renderPricingGrid($linux_plans);
include '../utilities/grid-section.php';
?>

<?php
// Linux Server Features
include '../utilities/cyber-cards.php';
$linux_features = [
    [
        'icon' => 'fab fa-linux',
        'title' => 'LINUX DISTRIBUTIONS',
        'description' => 'Choose from CentOS, Ubuntu, Debian, Rocky Linux, and more. All distributions with latest security updates and patches.'
    ],
    [
        'icon' => 'fas fa-user-cog',
        'title' => 'FULL ROOT ACCESS',
        'description' => 'Complete server control with root access. Install any software, configure services, and customize your environment freely.'
    ],
    [
        'icon' => 'fas fa-hdd',
        'title' => 'NVME SSD STORAGE',
        'description' => 'Lightning-fast NVMe SSD storage with hardware RAID for maximum performance and data protection.'
    ],
    [
        'icon' => 'fas fa-network-wired',
        'title' => 'PREMIUM NETWORK',
        'description' => 'Tier-1 network providers with global connectivity, low latency, and 99.9% uptime guarantee.'
    ],
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'DDOS PROTECTION',
        'description' => 'Advanced DDoS protection with real-time monitoring and automatic mitigation for maximum security.'
    ],
    [
        'icon' => 'fas fa-tools',
        'title' => 'IPMI/KVM ACCESS',
        'description' => 'Remote server management with IPMI and KVM access. Reboot, monitor, and manage your server remotely.'
    ]
];

$grid_title = 'LINUX SERVER FEATURES';
$grid_subtitle = 'Enterprise-grade features for maximum performance';
$grid_content = renderCyberCardsGrid($linux_features);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<?php
// Technical Specifications (Using Tabs)
include '../utilities/tabs.php';
$spec_tabs = [
    [
        'title' => 'CPU & MEMORY',
        'icon' => 'fas fa-microchip',
        'content' => '
            <h3 class="service-title">Processor & Memory Specifications</h3>
            <div style="columns: 2; gap: 2rem;">
                <ul style="list-style: none; padding: 0; text-align: left;">
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Intel Xeon E5/Gold Series</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Up to 3.5GHz Base Clock</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ ECC Registered Memory</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ DDR4-2933 Speed</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Memory Upgrades Available</li>
                </ul>
            </div>
        '
    ],
    [
        'title' => 'STORAGE & RAID',
        'icon' => 'fas fa-server',
        'content' => '
            <h3 class="service-title">Storage & RAID Configuration</h3>
            <div style="columns: 2; gap: 2rem;">
                <ul style="list-style: none; padding: 0; text-align: left;">
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Enterprise NVMe SSDs</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Hardware RAID Controller</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ RAID 0, 1, 10 Support</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Hot-swap Drive Bays</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Storage Expansion Available</li>
                </ul>
            </div>
        '
    ],
    [
        'title' => 'NETWORK',
        'icon' => 'fas fa-globe',
        'content' => '
            <h3 class="service-title">Network & Connectivity</h3>
            <div style="columns: 2; gap: 2rem;">
                <ul style="list-style: none; padding: 0; text-align: left;">
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ 1Gbps/10Gbps Uplinks</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Unmetered Bandwidth</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Multiple IPv4 Addresses</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ IPv6 Support</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ BGP Routing Available</li>
                </ul>
            </div>
        '
    ],
    [
        'title' => 'SUPPORT',
        'icon' => 'fas fa-cog',
        'content' => '
            <h3 class="service-title">Management & Support</h3>
            <div style="columns: 2; gap: 2rem;">
                <ul style="list-style: none; padding: 0; text-align: left;">
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ 24/7 Hardware Monitoring</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Proactive Support</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Hardware Replacement</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ OS Installation Service</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">◉ Migration Assistance</li>
                </ul>
            </div>
        '
    ]
];

$grid_title = 'TECHNICAL SPECIFICATIONS';
$grid_subtitle = 'Detailed server specifications and capabilities';
$grid_content = renderTabs($spec_tabs, 'tech-specs', 0);
include '../utilities/grid-section.php';
?>

<?php
// Available Linux Distributions
$linux_distros = [
    [
        'icon' => 'fab fa-centos',
        'title' => 'CENTOS/ROCKY LINUX',
        'description' => 'Enterprise-grade Linux with long-term support and stability. Perfect for production environments and enterprise applications.'
    ],
    [
        'icon' => 'fab fa-ubuntu',
        'title' => 'UBUNTU SERVER',
        'description' => 'Popular and user-friendly Linux distribution with extensive community support and regular updates. Great for beginners.'
    ],
    [
        'icon' => 'fab fa-debian',
        'title' => 'DEBIAN',
        'description' => 'Stable and reliable Linux distribution known for its excellent package management and security features.'
    ],
    [
        'icon' => 'fab fa-redhat',
        'title' => 'RHEL',
        'description' => 'Red Hat Enterprise Linux for mission-critical applications with commercial support and enterprise certifications.'
    ],
    [
        'icon' => 'fab fa-fedora',
        'title' => 'FEDORA SERVER',
        'description' => 'Cutting-edge Linux distribution with latest features and technologies for advanced users and developers.'
    ],
    [
        'icon' => 'fas fa-server',
        'title' => 'CUSTOM INSTALL',
        'description' => 'Need a specific Linux distribution? We can install any compatible Linux OS on your dedicated server.'
    ]
];

$grid_title = 'LINUX DISTRIBUTIONS';
$grid_subtitle = 'Choose from popular Linux distributions';
$grid_content = renderCyberCardsGrid($linux_distros);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<?php
// Server Management (Three Column Layout)
$column1_content = '
    <div style="text-align: center; margin-bottom: 2rem;">
        <i class="fas fa-desktop" style="font-size: 3rem; color: var(--cyber-neon-cyan); margin-bottom: 1rem;"></i>
        <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">CONTROL PANEL</h3>
        <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 1rem;">
            Web-based control panel for server management, monitoring, and configuration.
        </p>
        <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.7);">
            <li style="margin: 0.5rem 0;">• Server Status & Monitoring</li>
            <li style="margin: 0.5rem 0;">• Resource Usage Graphs</li>
            <li style="margin: 0.5rem 0;">• Network Traffic Analysis</li>
            <li style="margin: 0.5rem 0;">• Service Management</li>
        </ul>
    </div>
';

$column2_content = '
    <div style="text-align: center; margin-bottom: 2rem;">
        <i class="fas fa-terminal" style="font-size: 3rem; color: var(--cyber-neon-green); margin-bottom: 1rem;"></i>
        <h3 style="color: var(--cyber-neon-green); margin-bottom: 1rem;">SSH ACCESS</h3>
        <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 1rem;">
            Secure shell access with root privileges for complete server control.
        </p>
        <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.7);">
            <li style="margin: 0.5rem 0;">• Full Root Access</li>
            <li style="margin: 0.5rem 0;">• Key-based Authentication</li>
            <li style="margin: 0.5rem 0;">• Multiple User Accounts</li>
            <li style="margin: 0.5rem 0;">• Sudo Configuration</li>
        </ul>
    </div>
';

$column3_content = '
    <div style="text-align: center;">
        <i class="fas fa-backup" style="font-size: 3rem; color: var(--cyber-neon-pink); margin-bottom: 1rem;"></i>
        <h3 style="color: var(--cyber-neon-pink); margin-bottom: 1rem;">BACKUP SOLUTIONS</h3>
        <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 1rem;">
            Automated backup solutions to protect your data and applications.
        </p>
        <ul style="list-style: none; text-align: left; color: rgba(255, 255, 255, 0.7);">
            <li style="margin: 0.5rem 0;">• Automated Daily Backups</li>
            <li style="margin: 0.5rem 0;">• Offsite Backup Storage</li>
            <li style="margin: 0.5rem 0;">• Point-in-time Recovery</li>
            <li style="margin: 0.5rem 0;">• Custom Backup Schedules</li>
        </ul>
    </div>
';

$grid_title = 'SERVER MANAGEMENT';
$grid_subtitle = 'Comprehensive server management tools and services';
$grid_content = '
    <div class="cyber-card" style="max-width: 900px; margin: 0 auto;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
            ' . $column1_content . $column2_content . $column3_content . '
        </div>
    </div>
';
include '../utilities/grid-section.php';
?>

<?php
// CTA Section
$cta_title = 'READY FOR LINUX POWER?';
$cta_subtitle = 'Get high-performance Linux dedicated servers with full root access and enterprise-grade hardware.';
$cta_buttons = '<a href="../contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">ORDER SERVER</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
