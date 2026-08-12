<?php
// Include SEO functions first
include '../utilities/seo-meta.php';
include_once '../utilities/dynamic_pricing.php';

// Page variables
$current_page = 'hosting';
$page_title = 'Cloud Hosting - Scalable Infrastructure | HiveNest Matrix';
$page_description = 'Cloud Hosting - Scalable cloud infrastructure with auto-scaling, load balancing, and quantum-level performance across multiple data centers.';
$page_keywords = 'cloud hosting, scalable hosting, cloud infrastructure, auto-scaling, load balancing';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-email-global.jpg',
    'url' => 'https://hivenest.co.za/hosting/cloud-hosting.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Cloud Hosting Services',
        'description' => 'Scalable cloud infrastructure with enterprise-grade performance and reliability',
        'serviceType' => 'Cloud Hosting Services'
    ])
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Quantum Servers', 'url' => '../main-services/hosting.php'],
    ['text' => 'Cloud Hosting', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = <<<'JS'
// Queue for cart actions before system is ready
window.cartActionQueue = window.cartActionQueue || [];
window.cartSystemReady = false;

function addCloudHostingToCart(planSlug, price, planName) {
    if (!planName) planName = String(planSlug).replace(/-/g, ' ').toUpperCase();
    const cartAction = function() {
        if (window.addToCart) {
            window.addToCart({
                id: 'cloud-hosting-' + planSlug,
                name: 'Cloud Hosting: ' + planName,
                price: price,
                type: 'hosting'
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
            console.warn('Cart system is taking longer than expected. Please refresh the page and try again.');
        }
    }, 100);
}

// Cloud resource calculator
function calculateResources() {
    const traffic = document.getElementById("traffic-estimate").value;
    const storage = document.getElementById("storage-estimate").value;
    const features = document.getElementById("features-estimate").value;
    
    let recommendedPlan = "cloud-starter";
    let recommendation = "Cloud Starter";
    let planName = "CLOUD STARTER";
    
    if (traffic === "high" || storage === "large" || features === "advanced") {
        recommendedPlan = "cloud-enterprise";
        recommendation = "Cloud Enterprise";
        planName = "CLOUD ENTERPRISE";
    } else if (traffic === "medium" || storage === "medium" || features === "standard") {
        recommendedPlan = "cloud-professional";
        recommendation = "Cloud Professional";
        planName = "CLOUD PROFESSIONAL";
    }
    
    const resultDiv = document.getElementById("calculator-result");
    const planPrices = {"cloud-starter": 20, "cloud-professional": 45, "cloud-enterprise": 89};
    
    resultDiv.innerHTML = 
        '<div class="cyber-card" style="border: 2px solid var(--cyber-neon-green);">' +
            '<h4 style="color: var(--cyber-neon-green); margin-bottom: 1rem;">RECOMMENDED PLAN</h4>' +
            '<h3 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">' + recommendation + '</h3>' +
            '<p style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem;">' +
                'Based on your requirements, this plan provides the optimal balance of performance, ' +
                'scalability, and cost-effectiveness for your cloud infrastructure needs.' +
            '</p>' +
            '<button onclick="addCloudHostingToCart(\'' + recommendedPlan + '\', \'' + planName + '\', ' + planPrices[recommendedPlan] + ')" ' +
                    'class="btn btn-primary" style="width: 100%;">' +
                'ADD TO CART' +
            '</button>' +
        '</div>';
    
    resultDiv.style.display = "block";
}
JS;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php 
echo renderSEOMeta($seo_config);
?>
</head>
<body>
<?php include '../utilities/nav.php'; ?>
<?php include '../utilities/mobile-menu.php'; ?>

<?php
// Hero Section
$hero_title = '<span class="cyber-text">CLOUD</span><br>HOSTING MATRIX';
$hero_subtitle = 'Scalable cloud infrastructure with auto-scaling, load balancing, and quantum-level performance across multiple data centers in the digital multiverse.';
$hero_image = '../assets/images/heroes/hero-email-global.jpg';
$hero_alt = 'Cloud Hosting Matrix';
include '../utilities/hero-minimal.php';
?>

<!-- Cloud Resource Calculator -->
<section class="section" style="padding-top: 2rem;">
    <div class="container">
        <div class="text-center mb-8">
            <h2>CLOUD RESOURCE CALCULATOR</h2>
            <p class="hero-subtitle">Find the perfect cloud plan for your needs</p>
        </div>
        
        <div class="cyber-card" style="max-width: 800px; margin: 0 auto;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                <div>
                    <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">EXPECTED TRAFFIC</label>
                    <select id="traffic-estimate" style="width: 100%; padding: 1rem; border: 1px solid rgba(0,255,255,0.3); border-radius: 6px; background: var(--cyber-dark); color: white;">
                        <option value="low">Low (< 10K visitors/month)</option>
                        <option value="medium">Medium (10K - 100K visitors/month)</option>
                        <option value="high">High (> 100K visitors/month)</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">STORAGE REQUIREMENTS</label>
                    <select id="storage-estimate" style="width: 100%; padding: 1rem; border: 1px solid rgba(0,255,255,0.3); border-radius: 6px; background: var(--cyber-dark); color: white;">
                        <option value="small">Small (< 50GB)</option>
                        <option value="medium">Medium (50GB - 200GB)</option>
                        <option value="large">Large (> 200GB)</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">FEATURES NEEDED</label>
                    <select id="features-estimate" style="width: 100%; padding: 1rem; border: 1px solid rgba(0,255,255,0.3); border-radius: 6px; background: var(--cyber-dark); color: white;">
                        <option value="basic">Basic (Website hosting)</option>
                        <option value="standard">Standard (E-commerce, CMS)</option>
                        <option value="advanced">Advanced (High availability, scaling)</option>
                    </select>
                </div>
            </div>
            
            <div style="text-align: center; margin-bottom: 2rem;">
                <button onclick="calculateResources()" class="btn btn-primary" style="padding: 1rem 2rem; font-size: 1.1rem;">
                    CALCULATE OPTIMAL PLAN
                </button>
            </div>
            
            <div id="calculator-result" style="display: none;">
                <!-- Results will be populated here -->
            </div>
        </div>
    </div>
</section>

<?php
// Cloud Hosting Plans
include '../utilities/pricing-cards.php';
$cloud_plans = [
    [
        'name' => 'CLOUD STARTER',
        'price' => '$20',
        'period' => '/mo',
        'features' => [
            '2 CPU Cores',
            '4GB RAM Memory',
            '80GB SSD Storage',
            '2TB Data Transfer',
            'Auto-scaling Ready',
            'Load Balancer',
            'Free SSL Certificate',
            '99.95% Uptime SLA',
            '24/7 Support'
        ],
        'cta_link' => '#',
        'cta_text' => 'ADD TO CART',
        'onclick' => 'addCloudHostingToCart(\'cloud-starter\', \'CLOUD STARTER\', 20)'
    ],
    [
        'name' => 'CLOUD PROFESSIONAL',
        'price' => '$45',
        'period' => '/mo',
        'features' => [
            '4 CPU Cores',
            '8GB RAM Memory',
            '200GB SSD Storage',
            '5TB Data Transfer',
            'Auto-scaling Enabled',
            'Advanced Load Balancer',
            'Free SSL Certificate',
            'CDN Integration',
            '99.99% Uptime SLA',
            'Priority Support',
            'Daily Backups'
        ],
        'cta_link' => '#',
        'cta_text' => 'ADD TO CART',
        'onclick' => 'addCloudHostingToCart(\'cloud-professional\', \'CLOUD PROFESSIONAL\', 45)',
        'featured' => true
    ],
    [
        'name' => 'CLOUD ENTERPRISE',
        'price' => '$89',
        'period' => '/mo',
        'features' => [
            '8 CPU Cores',
            '16GB RAM Memory',
            '500GB SSD Storage',
            'Unlimited Data Transfer',
            'Advanced Auto-scaling',
            'Multi-region Load Balancer',
            'Wildcard SSL Certificate',
            'Premium CDN',
            '99.99% Uptime SLA',
            '24/7 Phone Support',
            'Real-time Backups',
            'Dedicated Account Manager'
        ],
        'cta_link' => '#',
        'cta_text' => 'ADD TO CART',
        'onclick' => 'addCloudHostingToCart(\'cloud-enterprise\', \'CLOUD ENTERPRISE\', 89)'
    ]
];

echo '<section class="section">';
echo '<div class="container">';
echo '<div class="text-center mb-8">';
echo '<h2>CLOUD HOSTING PLANS</h2>';
echo '<p class="hero-subtitle">Scalable cloud infrastructure for any workload</p>';
echo '</div>';

// Load live pricing (cache → DB by id → DB by slug → hardcoded fallback above)
$cloud_plans = loadProductPricingPlans([
    'product_id'     => 29,
    'product_slug'   => 'cloud-hosting',
    'cart_function'  => 'addCloudHostingToCart',
    'fallback_plans' => $cloud_plans,
]);

// Display pricing cards
echo '<div class="pricing-grid">';
foreach($cloud_plans as $plan) {
    $featured_class = isset($plan['featured']) && $plan['featured'] ? 'featured' : '';
    echo '<div class="pricing-card ' . $featured_class . '">';
    echo '<div class="pricing-plan">' . $plan['name'] . '</div>';
    echo '<div class="pricing-amount">' . $plan['price'] . '<span style="font-size: 1rem;">' . $plan['period'] . '</span></div>';
    echo '<ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">';
    foreach($plan['features'] as $feature) {
        $color = $featured_class ? 'var(--cyber-neon-green)' : 'var(--cyber-neon-cyan)';
        echo '<li style="margin: 0.5rem 0; color: ' . $color . ';">◉ ' . $feature . '</li>';
    }
    echo '</ul>';
    if(isset($plan['onclick'])) {
        echo '<button onclick="' . $plan['onclick'] . '" class="btn btn-primary" style="width: 100%;">' . $plan['cta_text'] . '</button>';
    } else {
        echo '<a href="' . $plan['cta_link'] . '" class="btn btn-primary" style="width: 100%;">' . $plan['cta_text'] . '</a>';
    }
    echo '</div>';
}
echo '</div>';
echo '</div>';
echo '</section>';
?>

<?php
// Cloud Features
include '../utilities/cyber-cards.php';
$cloud_features = [
    [
        'icon' => 'fas fa-expand-arrows-alt',
        'title' => 'AUTO-SCALING',
        'description' => 'Automatically scale resources up or down based on demand. Handle traffic spikes without manual intervention.'
    ],
    [
        'icon' => 'fas fa-balance-scale',
        'title' => 'LOAD BALANCING',
        'description' => 'Distribute traffic across multiple servers for optimal performance and high availability across all dimensions.'
    ],
    [
        'icon' => 'fas fa-globe',
        'title' => 'GLOBAL CDN',
        'description' => 'Content delivery network with edge locations worldwide for fastest possible loading times everywhere.'
    ],
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'ADVANCED SECURITY',
        'description' => 'DDoS protection, firewall, intrusion detection, and automated security updates across the cloud matrix.'
    ],
    [
        'icon' => 'fas fa-sync-alt',
        'title' => 'AUTOMATED BACKUPS',
        'description' => 'Automated backups across multiple data centers with one-click restoration capabilities.'
    ],
    [
        'icon' => 'fas fa-chart-line',
        'title' => 'REAL-TIME MONITORING',
        'description' => 'Advanced monitoring dashboard with real-time metrics, alerts, and performance analytics.'
    ]
];

// Display cloud features
echo '<section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">';
echo '<div class="container">';
echo '<div class="text-center mb-8">';
echo '<h2>CLOUD INFRASTRUCTURE FEATURES</h2>';
echo '<p class="hero-subtitle">Enterprise-grade cloud hosting capabilities</p>';
echo '</div>';

echo '<div class="services-grid">';
foreach($cloud_features as $feature) {
    echo '<div class="cyber-card">';
    echo '<i class="' . $feature['icon'] . ' service-icon"></i>';
    echo '<h3 class="service-title">' . $feature['title'] . '</h3>';
    echo '<p class="service-description">' . $feature['description'] . '</p>';
    echo '</div>';
}
echo '</div>';
echo '</div>';
echo '</section>';
?>

<?php
// Cloud Architecture
$cloud_architecture = [
    [
        'title' => 'INFRASTRUCTURE COMPONENTS',
        'items' => [
            'Multi-zone deployment architecture',
            'Redundant network connections',
            'SSD storage with RAID 10',
            'Enterprise-grade hardware',
            'Automated failover systems',
            'Geographic load distribution',
            'Edge caching nodes',
            'Real-time health monitoring'
        ]
    ],
    [
        'title' => 'PERFORMANCE OPTIMIZATION',
        'items' => [
            'HTTP/2 and HTTP/3 support',
            'Brotli and Gzip compression',
            'Advanced caching layers',
            'Database query optimization',
            'Image optimization and WebP',
            'Minification and bundling',
            'Lazy loading implementation',
            'Critical rendering path optimization'
        ]
    ]
];

// Display cloud architecture
echo '<section class="section">';
echo '<div class="container">';
echo '<div class="text-center mb-8">';
echo '<h2>CLOUD ARCHITECTURE</h2>';
echo '<p class="hero-subtitle">Built for scale, performance, and reliability</p>';
echo '</div>';

echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem;">';
foreach($cloud_architecture as $column) {
    echo '<div class="cyber-card">';
    echo '<h3 style="color: var(--cyber-neon-cyan); margin-bottom: 1.5rem;">' . $column['title'] . '</h3>';
    echo '<ul style="list-style: none; padding: 0;">';
    foreach($column['items'] as $item) {
        echo '<li style="margin: 0.75rem 0; color: rgba(255,255,255,0.8); padding-left: 1.5rem; position: relative;">';
        echo '<i class="fas fa-check" style="position: absolute; left: 0; top: 0.2rem; color: var(--cyber-neon-green);"></i>';
        echo $item . '</li>';
    }
    echo '</ul>';
    echo '</div>';
}
echo '</div>';
echo '</div>';
echo '</section>';
?>

<!-- Migration and Support -->
<section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
    <div class="container">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; align-items: center;">
            <div class="cyber-card">
                <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 2rem;">FREE CLOUD MIGRATION</h3>
                
                <div style="margin-bottom: 2rem;">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <i class="fas fa-cloud-upload-alt" style="color: var(--cyber-neon-green); font-size: 1.5rem;"></i>
                        <h4 style="color: var(--cyber-neon-green); margin: 0;">SEAMLESS MIGRATION</h4>
                    </div>
                    <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">
                        Our cloud experts will migrate your existing infrastructure to our cloud platform with zero downtime.
                    </p>
                </div>
                
                <div style="margin-bottom: 2rem;">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <i class="fas fa-tools" style="color: var(--cyber-neon-green); font-size: 1.5rem;"></i>
                        <h4 style="color: var(--cyber-neon-green); margin: 0;">OPTIMIZATION</h4>
                    </div>
                    <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">
                        We'll optimize your applications and configurations for optimal cloud performance.
                    </p>
                </div>
                
                <a href="../contact.php" class="btn btn-primary" style="width: 100%;">
                    REQUEST MIGRATION
                </a>
            </div>
            
            <div class="cyber-card">
                <h3 style="color: var(--cyber-neon-pink); margin-bottom: 2rem;">24/7 CLOUD SUPPORT</h3>
                
                <div style="margin-bottom: 2rem;">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <i class="fas fa-headset" style="color: var(--cyber-neon-pink); font-size: 1.5rem;"></i>
                        <h4 style="color: var(--cyber-neon-pink); margin: 0;">EXPERT SUPPORT</h4>
                    </div>
                    <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">
                        Our certified cloud engineers are available 24/7 to help with any technical issues.
                    </p>
                </div>
                
                <div style="margin-bottom: 2rem;">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <i class="fas fa-chart-line" style="color: var(--cyber-neon-pink); font-size: 1.5rem;"></i>
                        <h4 style="color: var(--cyber-neon-pink); margin: 0;">PROACTIVE MONITORING</h4>
                    </div>
                    <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">
                        We monitor your cloud infrastructure 24/7 and proactively address potential issues.
                    </p>
                </div>
                
                <a href="tel:+27123456789" class="btn btn-secondary" style="width: 100%;">
                    CALL SUPPORT
                </a>
            </div>
        </div>
    </div>
</section>

<?php
// CTA Section
$cta_title = 'READY TO SCALE IN THE CLOUD MATRIX?';
$cta_subtitle = 'Launch your applications with our enterprise-grade cloud infrastructure and experience unlimited scalability across multiple dimensions.';
$cta_buttons = '<a href="../contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">GET CLOUD HOSTING</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
