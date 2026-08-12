<?php
// Include required utilities
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/dynamic_pricing.php';

// Page variables
$current_page = 'marketing';
$page_title = 'Google Marketing Services - PPC & Google Ads | HiveNest Matrix';
$page_description = 'Professional Google Marketing Services - Maximize ROI with Google Ads, PPC campaigns, and advanced Google marketing strategies.';
$page_keywords = 'google marketing, google ads, PPC, pay per click, google advertising, digital marketing';

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Marketing Matrix', 'url' => '../marketing/offers.php'],
    ['text' => 'Google Marketing', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = <<<'JAVASCRIPT'
function addGoogleMarketingToCart(planId, planName, price) {
    if (typeof price === 'undefined') {
        price = planName;
        planName = planId.replace(/-/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase());
    }
    if (window.addToCart) {
        window.addToCart({
            id: 'google-marketing-' + planId,
            name: 'Google Marketing: ' + planName,
            price: price,
            type: 'marketing'
        });
    } else {
        console.error('Cart system not loaded');
        console.warn('Cart system not ready. Please refresh the page and try again.');
    }
}

// ROI Calculator
function calculateROI() {
    const budget = parseFloat(document.getElementById('monthly-budget').value);
    const avgOrder = parseFloat(document.getElementById('avg-order-value').value);
    const industry = document.getElementById('industry-type').value;
    
    if (!budget || !avgOrder) {
        return;
    }
    
    const resultDiv = document.getElementById('roi-calculation-result');
    
    // Industry-specific conversion rates and CPCs
    const industryData = {
        'ecommerce': { ctr: 3.17, cvr: 2.63, cpc: 1.16 },
        'services': { ctr: 2.41, cvr: 3.04, cpc: 2.62 },
        'software': { ctr: 2.83, cvr: 2.92, cpc: 3.80 },
        'healthcare': { ctr: 3.27, cvr: 3.36, cpc: 2.62 },
        'finance': { ctr: 2.91, cvr: 5.10, cpc: 3.77 }
    };
    
    const data = industryData[industry] || industryData['services'];
    
    const clicks = Math.floor(budget / data.cpc);
    const conversions = Math.floor(clicks * (data.cvr / 100));
    const revenue = conversions * avgOrder;
    const roi = ((revenue - budget) / budget * 100).toFixed(1);
    const roas = (revenue / budget).toFixed(2);
    
    resultDiv.innerHTML = `
        <div class="cyber-card" style="border: 2px solid var(--cyber-neon-green);">
            <h4 style="color: var(--cyber-neon-cyan); text-align: center; margin-bottom: 2rem;">PROJECTED GOOGLE ADS PERFORMANCE</h4>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                <div style="text-align: center; padding: 1rem; background: rgba(0,255,255,0.1); border-radius: 8px;">
                    <h3 style="color: var(--cyber-neon-cyan); margin: 0; font-size: 2rem;">${clicks.toLocaleString()}</h3>
                    <p style="color: rgba(255,255,255,0.8); margin: 0.5rem 0 0;">Clicks per month</p>
                </div>
                <div style="text-align: center; padding: 1rem; background: rgba(0,255,0,0.1); border-radius: 8px;">
                    <h3 style="color: var(--cyber-neon-green); margin: 0; font-size: 2rem;">${conversions}</h3>
                    <p style="color: rgba(255,255,255,0.8); margin: 0.5rem 0 0;">Conversions</p>
                </div>
                <div style="text-align: center; padding: 1rem; background: rgba(255,0,255,0.1); border-radius: 8px;">
                    <h3 style="color: var(--cyber-neon-pink); margin: 0; font-size: 2rem;">$${revenue.toLocaleString()}</h3>
                    <p style="color: rgba(255,255,255,0.8); margin: 0.5rem 0 0;">Revenue</p>
                </div>
                <div style="text-align: center; padding: 1rem; background: rgba(255,255,0,0.1); border-radius: 8px;">
                    <h3 style="color: var(--cyber-neon-yellow); margin: 0; font-size: 2rem;">${roi}%</h3>
                    <p style="color: rgba(255,255,255,0.8); margin: 0.5rem 0 0;">ROI</p>
                </div>
            </div>
            
            <div style="background: rgba(0,0,0,0.3); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                <h4 style="color: var(--cyber-neon-green); margin-bottom: 1rem;">KEY METRICS:</h4>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <li style="margin: 0.5rem 0; color: rgba(255,255,255,0.8);">✓ Return on Ad Spend (ROAS): ${roas}x</li>
                    <li style="margin: 0.5rem 0; color: rgba(255,255,255,0.8);">✓ Cost Per Acquisition: $${(budget / conversions).toFixed(2)}</li>
                    <li style="margin: 0.5rem 0; color: rgba(255,255,255,0.8);">✓ Average Cost Per Click: $${data.cpc}</li>
                    <li style="margin: 0.5rem 0; color: rgba(255,255,255,0.8);">✓ Expected Conversion Rate: ${data.cvr}%</li>
                </ul>
            </div>
            
            <div style="text-align: center;">
                <p style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem;">
                    Ready to achieve these results? Our Google Ads experts can help optimize your campaigns for maximum ROI.
                </p>
                <button onclick="addGoogleMarketingToCart('professional', 'GOOGLE PROFESSIONAL', 599)" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                    START GOOGLE ADS CAMPAIGN
                </button>
            </div>
        </div>
    `;
    
    resultDiv.style.display = 'block';
}
JAVASCRIPT;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php 
include '../utilities/head.php'; 
include_once '../utilities/seo-meta.php';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-branding-workspace.jpg',
    'url' => 'https://hivenest.co.za/marketing/google-marketing.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Google Marketing Services',
        'description' => 'Professional Google Ads and PPC marketing services',
        'serviceType' => 'Google Marketing Services'
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
$hero_title = '<span class="cyber-text">GOOGLE</span><br>MARKETING MATRIX';
$hero_subtitle = 'Dominate Google search results with precision-targeted ads, advanced PPC strategies, and quantum-level campaign optimization for maximum ROI.';
$hero_image = '../assets/images/heroes/hero-marketing-seo.jpg';
$hero_alt = 'Google Marketing Services';
include '../utilities/hero-minimal.php';
?>

<!-- ROI Calculator -->
<section class="section" style="padding-top: 2rem;">
    <div class="container">
        <div style="max-width: 800px; margin: 0 auto;">
            <div class="cyber-card">
                <h3 style="color: var(--cyber-neon-cyan); text-align: center; margin-bottom: 2rem;">GOOGLE ADS ROI CALCULATOR</h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <div>
                        <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">MONTHLY AD BUDGET ($)</label>
                        <input 
                            type="number" 
                            id="monthly-budget"
                            placeholder="1000" 
                            style="width: 100%; padding: 1rem; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white; font-size: 1.1rem;"
                        >
                    </div>
                    
                    <div>
                        <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">AVERAGE ORDER VALUE ($)</label>
                        <input 
                            type="number" 
                            id="avg-order-value"
                            placeholder="50" 
                            style="width: 100%; padding: 1rem; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white; font-size: 1.1rem;"
                        >
                    </div>
                    
                    <div>
                        <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">INDUSTRY TYPE</label>
                        <select id="industry-type" style="width: 100%; padding: 1rem; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white; font-size: 1.1rem;">
                            <option value="ecommerce">E-commerce</option>
                            <option value="services">Services</option>
                            <option value="software">Software/SaaS</option>
                            <option value="healthcare">Healthcare</option>
                            <option value="finance">Finance</option>
                        </select>
                    </div>
                </div>
                
                <div style="text-align: center; margin-bottom: 2rem;">
                    <button onclick="calculateROI()" class="btn btn-primary" style="padding: 1rem 2rem; font-size: 1.1rem;">
                        CALCULATE PROJECTED ROI
                    </button>
                </div>
                
                <div id="roi-calculation-result" style="display: none;">
                    <!-- Results will be populated here -->
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Google Marketing Plans
include '../utilities/pricing-cards.php';
$google_plans = [
    [
        'name' => 'GOOGLE STARTER',
        'price' => '$299',
        'period' => '/mo',
        'features' => [
            'Google Ads Setup & Configuration',
            'Keyword Research & Selection',
            'Ad Copy Creation (5 variations)',
            'Landing Page Optimization',
            'Basic Campaign Management',
            'Monthly Performance Reports',
            'Google Analytics Setup',
            'Up to $2K ad spend management'
        ],
        'cta_link' => '#',
        'cta_text' => 'ADD TO CART',
        'onclick' => 'addGoogleMarketingToCart(\'starter\', \'GOOGLE STARTER\', 299)'
    ],
    [
        'name' => 'GOOGLE PROFESSIONAL',
        'price' => '$599',
        'period' => '/mo',
        'features' => [
            'Advanced Campaign Strategy',
            'Multi-Campaign Management',
            'A/B Testing & Optimization',
            'Conversion Tracking Setup',
            'Remarketing Campaign',
            'Google Shopping Ads',
            'Bi-weekly Optimization Calls',
            'Advanced Reporting Dashboard',
            'Up to $10K ad spend management'
        ],
        'cta_link' => '#',
        'cta_text' => 'ADD TO CART',
        'onclick' => 'addGoogleMarketingToCart(\'professional\', \'GOOGLE PROFESSIONAL\', 599)',
        'featured' => true
    ],
    [
        'name' => 'GOOGLE ENTERPRISE',
        'price' => '$1299',
        'period' => '/mo',
        'features' => [
            'Enterprise Campaign Strategy',
            'Multi-Platform Integration',
            'Advanced Audience Targeting',
            'YouTube Ads Management',
            'Google Display Network',
            'Custom Attribution Modeling',
            'Weekly Strategy Sessions',
            'Dedicated Account Manager',
            'Custom Reporting & Analytics',
            'Unlimited ad spend management'
        ],
        'cta_link' => '#',
        'cta_text' => 'ADD TO CART',
        'onclick' => 'addGoogleMarketingToCart(\'enterprise\', \'GOOGLE ENTERPRISE\', 1299)'
    ]
];
$google_plans = loadProductPricingPlans([
    'product_slug' => 'google-marketing',
    'cart_function' => 'addGoogleMarketingToCart',
    'fallback_plans' => $google_plans,
]);

$grid_title = 'GOOGLE MARKETING PLANS';
$grid_subtitle = 'Professional Google Ads management for maximum ROI';
$grid_content = renderPricingGrid($google_plans);
include '../utilities/grid-section.php';
?>

<?php
// Google Marketing Features
include '../utilities/cyber-cards.php';
$google_features = [
    [
        'icon' => 'fab fa-google',
        'title' => 'GOOGLE ADS MASTERY',
        'description' => 'Expert management of Google Ads campaigns including Search, Display, Shopping, and YouTube ads for comprehensive market coverage.'
    ],
    [
        'icon' => 'fas fa-bullseye',
        'title' => 'PRECISION TARGETING',
        'description' => 'Advanced audience targeting using demographics, interests, behaviors, and custom audiences to reach your ideal customers.'
    ],
    [
        'icon' => 'fas fa-chart-line',
        'title' => 'CONVERSION OPTIMIZATION',
        'description' => 'Continuous optimization of campaigns, ad copy, and landing pages to maximize conversions and reduce cost per acquisition.'
    ],
    [
        'icon' => 'fas fa-sync-alt',
        'title' => 'REMARKETING CAMPAIGNS',
        'description' => 'Strategic remarketing to re-engage website visitors and previous customers with personalized ads across Google\'s network.'
    ],
    [
        'icon' => 'fas fa-mobile-alt',
        'title' => 'MOBILE OPTIMIZATION',
        'description' => 'Mobile-first campaign strategies optimized for smartphone users with location-based targeting and mobile-specific ad formats.'
    ],
    [
        'icon' => 'fas fa-analytics',
        'title' => 'ADVANCED ANALYTICS',
        'description' => 'Comprehensive tracking and analytics with custom dashboards, attribution modeling, and ROI analysis for data-driven decisions.'
    ]
];

$grid_title = 'GOOGLE MARKETING FEATURES';
$grid_subtitle = 'Comprehensive Google advertising solutions';
$grid_content = renderCyberCardsGrid($google_features);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<?php
// Google Ads Campaign Types
include '../utilities/four-column-grid.php';
$campaign_types = [
    [
        'icon' => 'fas fa-search',
        'title' => 'SEARCH CAMPAIGNS',
        'items' => [
            'Text ads on Google search results',
            'Keyword-targeted advertising',
            'High-intent user targeting',
            'Immediate visibility',
            'Cost-effective for conversions'
        ]
    ],
    [
        'icon' => 'fas fa-images',
        'title' => 'DISPLAY CAMPAIGNS',
        'items' => [
            'Visual ads across Google network',
            'Brand awareness building',
            'Remarketing opportunities',
            'Creative ad formats',
            'Massive reach potential'
        ]
    ],
    [
        'icon' => 'fas fa-shopping-cart',
        'title' => 'SHOPPING CAMPAIGNS',
        'items' => [
            'Product-based advertising',
            'Visual product showcases',
            'Direct e-commerce integration',
            'Competitive pricing display',
            'Higher click-through rates'
        ]
    ],
    [
        'icon' => 'fab fa-youtube',
        'title' => 'VIDEO CAMPAIGNS',
        'items' => [
            'YouTube advertising',
            'Video content promotion',
            'Engaging visual storytelling',
            'Demographic targeting',
            'Cost-effective brand building'
        ]
    ]
];

$grid_title = 'GOOGLE ADS CAMPAIGN TYPES';
$grid_subtitle = 'Comprehensive advertising solutions across Google\'s platforms';
$grid_content = renderFourColumnGrid($campaign_types);
include '../utilities/grid-section.php';
?>

<?php
// CTA Section
$cta_title = 'READY TO DOMINATE GOOGLE SEARCH?';
$cta_subtitle = 'Launch your Google Ads campaigns today and start generating high-quality leads and sales with our proven marketing strategies.';
$cta_buttons = '<a href="../contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">START GOOGLE MARKETING</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
