<?php
// Include required utilities
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/dynamic_pricing.php';

// Page variables
$current_page = 'marketing';
$page_title = 'Social Media Marketing - Digital Engagement | HiveNest Matrix';
$page_description = 'Professional Social Media Marketing Services - Build your brand, engage audiences, and drive conversions across all social platforms.';
$page_keywords = 'social media marketing, facebook marketing, instagram marketing, social media management, digital marketing';

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Marketing Matrix', 'url' => '../marketing/offers.php'],
    ['text' => 'Social Media Marketing', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = <<<'JAVASCRIPT'
function addSocialMediaToCart(planId, planName, price) {
    if (typeof price === 'undefined') {
        price = planName;
        planName = planId.replace(/-/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase());
    }
    if (window.addToCart) {
        window.addToCart({
            id: 'social-media-' + planId,
            name: 'Social Media Marketing: ' + planName,
            price: price,
            type: 'marketing'
        });
    } else {
        console.error('Cart system not loaded');
        console.warn('Cart system not ready. Please refresh the page and try again.');
    }
}

// Social Media Audit
function auditSocialMedia() {
    const platform = document.getElementById('social-platform').value;
    const handle = document.getElementById('social-handle').value.trim();
    
    if (!handle) {
        return;
    }
    
    const resultDiv = document.getElementById('social-audit-result');
    
    resultDiv.innerHTML = `
        <div class="cyber-card" style="text-align: center;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--cyber-neon-cyan); margin-bottom: 1rem;"></i>
            <h4 style="color: var(--cyber-neon-cyan);">ANALYZING SOCIAL PRESENCE...</h4>
            <p style="color: rgba(255,255,255,0.7);">Scanning @${handle} on ${platform.charAt(0).toUpperCase() + platform.slice(1)}</p>
        </div>
    `;
    resultDiv.style.display = 'block';
    
    // Simulate audit
    setTimeout(() => {
        const followers = Math.floor(Math.random() * 50000) + 1000;
        const engagement = (Math.random() * 8 + 1).toFixed(2);
        const score = Math.floor(Math.random() * 40) + 40; // Random score between 40-80
        const scoreColor = score < 55 ? 'var(--cyber-neon-pink)' : score < 70 ? 'var(--cyber-neon-yellow)' : 'var(--cyber-neon-green)';
        
        const platformData = {
            'facebook': { icon: 'fab fa-facebook-f', color: '#1877f2' },
            'instagram': { icon: 'fab fa-instagram', color: '#e4405f' },
            'twitter': { icon: 'fab fa-twitter', color: '#1da1f2' },
            'linkedin': { icon: 'fab fa-linkedin-in', color: '#0077b5' },
            'tiktok': { icon: 'fab fa-tiktok', color: '#000000' }
        };
        
        const data = platformData[platform] || platformData['facebook'];
        
        resultDiv.innerHTML = `
            <div class="cyber-card">
                <div style="text-align: center; margin-bottom: 2rem;">
                    <div style="width: 100px; height: 100px; border-radius: 50%; border: 5px solid ${scoreColor}; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center;">
                        <span style="font-size: 2rem; font-weight: bold; color: ${scoreColor};">${score}</span>
                    </div>
                    <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;">SOCIAL MEDIA SCORE: ${score}/100</h3>
                    <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <i class="${data.icon}" style="color: ${data.color}; font-size: 1.5rem;"></i>
                        <span style="color: rgba(255,255,255,0.8);">@${handle}</span>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                    <div style="text-align: center; padding: 1rem; background: rgba(0,0,0,0.3); border-radius: 8px;">
                        <h3 style="color: var(--cyber-neon-cyan); margin: 0; font-size: 1.5rem;">${followers.toLocaleString()}</h3>
                        <p style="color: rgba(255,255,255,0.8); margin: 0.5rem 0 0;">Followers</p>
                    </div>
                    <div style="text-align: center; padding: 1rem; background: rgba(0,0,0,0.3); border-radius: 8px;">
                        <h3 style="color: var(--cyber-neon-green); margin: 0; font-size: 1.5rem;">${engagement}%</h3>
                        <p style="color: rgba(255,255,255,0.8); margin: 0.5rem 0 0;">Engagement Rate</p>
                    </div>
                    <div style="text-align: center; padding: 1rem; background: rgba(0,0,0,0.3); border-radius: 8px;">
                        <h3 style="color: var(--cyber-neon-pink); margin: 0; font-size: 1.5rem;">${Math.floor(Math.random() * 20) + 5}</h3>
                        <p style="color: rgba(255,255,255,0.8); margin: 0.5rem 0 0;">Posts/Week</p>
                    </div>
                </div>
                
                <div style="background: rgba(0,0,0,0.3); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                    <h4 style="color: var(--cyber-neon-yellow); margin-bottom: 1rem;">IMPROVEMENT OPPORTUNITIES:</h4>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <li style="margin: 0.5rem 0; color: rgba(255,255,255,0.8);">📈 Increase posting consistency for better reach</li>
                        <li style="margin: 0.5rem 0; color: rgba(255,255,255,0.8);">🎯 Optimize content for higher engagement</li>
                        <li style="margin: 0.5rem 0; color: rgba(255,255,255,0.8);">🔗 Implement strategic hashtag usage</li>
                        <li style="margin: 0.5rem 0; color: rgba(255,255,255,0.8);">💬 Improve community interaction</li>
                    </ul>
                </div>
                
                <div style="text-align: center;">
                    <p style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem;">
                        Ready to supercharge your social media presence? Our experts can help you reach new heights.
                    </p>
                    <button onclick="addSocialMediaToCart('professional', 'SOCIAL PROFESSIONAL', 399)" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                        ADD TO CART
                    </button>
                </div>
            </div>
        `;
    }, 3000);
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
    'image' => 'assets/images/heroes/hero-email-workspace.jpg',
    'url' => 'https://hivenest.co.za/marketing/social-media.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Social Media Marketing',
        'description' => 'Professional social media marketing and management services',
        'serviceType' => 'Social Media Marketing Services'
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
$hero_title = '<span class="cyber-text">SOCIAL</span><br>MEDIA MATRIX';
$hero_subtitle = 'Dominate social networks with strategic content, viral campaigns, and quantum-level engagement across all platforms in the digital social verse.';
$hero_image = '../assets/images/heroes/hero-branding-colors.jpg';
$hero_alt = 'Social Media Marketing';
include '../utilities/hero-minimal.php';
?>

<!-- Social Media Audit Tool -->
<section class="section" style="padding-top: 2rem;">
    <div class="container">
        <div style="max-width: 800px; margin: 0 auto;">
            <div class="cyber-card">
                <h3 style="color: var(--cyber-neon-cyan); text-align: center; margin-bottom: 2rem;">FREE SOCIAL MEDIA AUDIT</h3>
                
                <div style="display: grid; grid-template-columns: 1fr 2fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                    <div>
                        <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">PLATFORM</label>
                        <select id="social-platform" style="width: 100%; padding: 1rem; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white; font-size: 1.1rem;">
                            <option value="facebook">Facebook</option>
                            <option value="instagram">Instagram</option>
                            <option value="twitter">Twitter</option>
                            <option value="linkedin">LinkedIn</option>
                            <option value="tiktok">TikTok</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">YOUR HANDLE</label>
                        <input 
                            type="text" 
                            id="social-handle"
                            placeholder="yourcompany" 
                            style="width: 100%; padding: 1rem; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white; font-size: 1.1rem;"
                        >
                    </div>
                    
                    <div style="display: flex; align-items: end;">
                        <button onclick="auditSocialMedia()" class="btn btn-primary" style="width: 100%; padding: 1rem; white-space: nowrap;">
                            AUDIT NOW
                        </button>
                    </div>
                </div>
                
                <p style="color: rgba(255,255,255,0.6); font-size: 0.9rem; text-align: center; margin-bottom: 2rem;">
                    Get a comprehensive analysis of your social media performance and growth opportunities
                </p>
                
                <div id="social-audit-result" style="display: none;">
                    <!-- Results will be populated here -->
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Social Media Plans
include '../utilities/pricing-cards.php';
$social_plans = [
    [
        'name' => 'SOCIAL STARTER',
        'price' => '$199',
        'period' => '/mo',
        'features' => [
            '2 Social Media Platforms',
            '12 Posts per Month',
            'Content Creation & Design',
            'Basic Hashtag Research',
            'Community Management',
            'Monthly Analytics Report',
            'Social Media Calendar',
            'Brand Voice Development'
        ],
        'cta_link' => '#',
        'cta_text' => 'ADD TO CART',
        'onclick' => 'addSocialMediaToCart(\'starter\', \'SOCIAL STARTER\', 199)'
    ],
    [
        'name' => 'SOCIAL PROFESSIONAL',
        'price' => '$399',
        'period' => '/mo',
        'features' => [
            '4 Social Media Platforms',
            '30 Posts per Month',
            'Advanced Content Strategy',
            'Influencer Outreach',
            'Paid Social Advertising',
            'Weekly Analytics Reports',
            'Video Content Creation',
            'Community Building',
            'Social Listening & Monitoring'
        ],
        'cta_link' => '#',
        'cta_text' => 'ADD TO CART',
        'onclick' => 'addSocialMediaToCart(\'professional\', \'SOCIAL PROFESSIONAL\', 399)',
        'featured' => true
    ],
    [
        'name' => 'SOCIAL ENTERPRISE',
        'price' => '$799',
        'period' => '/mo',
        'features' => [
            'All Major Social Platforms',
            '60+ Posts per Month',
            'Enterprise Content Strategy',
            'Advanced Social Advertising',
            'Influencer Partnership Program',
            'Real-time Monitoring',
            'Custom Video & Graphics',
            'Crisis Management',
            'Dedicated Social Media Manager',
            'Advanced Analytics & Insights'
        ],
        'cta_link' => '#',
        'cta_text' => 'ADD TO CART',
        'onclick' => 'addSocialMediaToCart(\'enterprise\', \'SOCIAL ENTERPRISE\', 799)'
    ]
];
$social_plans = loadProductPricingPlans([
    'product_slug' => 'social-media-marketing',
    'cart_function' => 'addSocialMediaToCart',
    'fallback_plans' => $social_plans,
]);

$grid_title = 'SOCIAL MEDIA PLANS';
$grid_subtitle = 'Comprehensive social media marketing solutions';
$grid_content = renderPricingGrid($social_plans);
include '../utilities/grid-section.php';
?>

<?php
// Social Media Features
include '../utilities/cyber-cards.php';
$social_features = [
    [
        'icon' => 'fas fa-edit',
        'title' => 'CONTENT CREATION',
        'description' => 'Professional content creation including graphics, videos, captions, and stories optimized for each platform\'s unique audience.'
    ],
    [
        'icon' => 'fas fa-users',
        'title' => 'COMMUNITY MANAGEMENT',
        'description' => 'Active community engagement, response management, and relationship building to foster loyal brand communities.'
    ],
    [
        'icon' => 'fas fa-bullseye',
        'title' => 'TARGETED ADVERTISING',
        'description' => 'Strategic paid social campaigns with precision targeting to reach your ideal customers and maximize ad spend ROI.'
    ],
    [
        'icon' => 'fas fa-star',
        'title' => 'INFLUENCER MARKETING',
        'description' => 'Influencer partnership programs and collaborations to expand reach and build authentic brand credibility.'
    ],
    [
        'icon' => 'fas fa-chart-bar',
        'title' => 'ANALYTICS & INSIGHTS',
        'description' => 'Comprehensive social media analytics, performance tracking, and actionable insights for continuous optimization.'
    ],
    [
        'icon' => 'fas fa-hashtag',
        'title' => 'TREND MONITORING',
        'description' => 'Real-time trend monitoring and social listening to capitalize on viral opportunities and manage brand reputation.'
    ]
];

$grid_title = 'SOCIAL MEDIA FEATURES';
$grid_subtitle = 'Complete social media marketing solutions';
$grid_content = renderCyberCardsGrid($social_features);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<?php
// Social Media Platforms
include '../utilities/four-column-grid.php';
$social_platforms = [
    [
        'icon' => 'fab fa-facebook-f',
        'title' => 'FACEBOOK MARKETING',
        'items' => [
            'Page optimization & setup',
            'Targeted Facebook Ads',
            'Community building',
            'Event promotion',
            'Facebook Shop integration'
        ]
    ],
    [
        'icon' => 'fab fa-instagram',
        'title' => 'INSTAGRAM MARKETING',
        'items' => [
            'Visual content strategy',
            'Stories & Reels creation',
            'Instagram Shopping',
            'Hashtag optimization',
            'Influencer collaborations'
        ]
    ],
    [
        'icon' => 'fab fa-linkedin-in',
        'title' => 'LINKEDIN MARKETING',
        'items' => [
            'B2B content strategy',
            'LinkedIn Ads management',
            'Professional networking',
            'Thought leadership',
            'Lead generation campaigns'
        ]
    ],
    [
        'icon' => 'fab fa-tiktok',
        'title' => 'TIKTOK MARKETING',
        'items' => [
            'Viral content creation',
            'TikTok Ads campaigns',
            'Trend participation',
            'Hashtag challenges',
            'Gen Z audience targeting'
        ]
    ]
];

$grid_title = 'SOCIAL MEDIA PLATFORMS';
$grid_subtitle = 'Comprehensive marketing across all major social networks';
$grid_content = renderFourColumnGrid($social_platforms);
include '../utilities/grid-section.php';
?>

<?php
// Content Calendar Preview
?>
<section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
    <div class="container">
        <div class="text-center mb-8">
            <h2>SAMPLE CONTENT CALENDAR</h2>
            <p class="hero-subtitle">See how we plan and execute your social media strategy</p>
        </div>
        
        <div class="cyber-card" style="max-width: 1000px; margin: 0 auto;">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--cyber-neon-cyan);">
                            <th style="padding: 1rem; color: var(--cyber-neon-cyan); text-align: left;">DAY</th>
                            <th style="padding: 1rem; color: var(--cyber-neon-cyan); text-align: center;">FACEBOOK</th>
                            <th style="padding: 1rem; color: var(--cyber-neon-cyan); text-align: center;">INSTAGRAM</th>
                            <th style="padding: 1rem; color: var(--cyber-neon-cyan); text-align: center;">LINKEDIN</th>
                            <th style="padding: 1rem; color: var(--cyber-neon-cyan); text-align: center;">TWITTER</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <td style="padding: 1rem; color: var(--cyber-neon-green); font-weight: bold;">MONDAY</td>
                            <td style="padding: 1rem; color: rgba(255,255,255,0.8); text-align: center;">Motivational Quote</td>
                            <td style="padding: 1rem; color: rgba(255,255,255,0.8); text-align: center;">Behind-the-scenes Story</td>
                            <td style="padding: 1rem; color: rgba(255,255,255,0.8); text-align: center;">Industry Insight</td>
                            <td style="padding: 1rem; color: rgba(255,255,255,0.8); text-align: center;">News & Updates</td>
                        </tr>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <td style="padding: 1rem; color: var(--cyber-neon-green); font-weight: bold;">TUESDAY</td>
                            <td style="padding: 1rem; color: rgba(255,255,255,0.8); text-align: center;">Product Showcase</td>
                            <td style="padding: 1rem; color: rgba(255,255,255,0.8); text-align: center;">Product Photo</td>
                            <td style="padding: 1rem; color: rgba(255,255,255,0.8); text-align: center;">Case Study</td>
                            <td style="padding: 1rem; color: rgba(255,255,255,0.8); text-align: center;">Quick Tips</td>
                        </tr>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <td style="padding: 1rem; color: var(--cyber-neon-green); font-weight: bold;">WEDNESDAY</td>
                            <td style="padding: 1rem; color: rgba(255,255,255,0.8); text-align: center;">Educational Content</td>
                            <td style="padding: 1rem; color: rgba(255,255,255,0.8); text-align: center;">Reels Video</td>
                            <td style="padding: 1rem; color: rgba(255,255,255,0.8); text-align: center;">Thought Leadership</td>
                            <td style="padding: 1rem; color: rgba(255,255,255,0.8); text-align: center;">Engagement Poll</td>
                        </tr>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <td style="padding: 1rem; color: var(--cyber-neon-green); font-weight: bold;">THURSDAY</td>
                            <td style="padding: 1rem; color: rgba(255,255,255,0.8); text-align: center;">Customer Testimonial</td>
                            <td style="padding: 1rem; color: rgba(255,255,255,0.8); text-align: center;">User-generated Content</td>
                            <td style="padding: 1rem; color: rgba(255,255,255,0.8); text-align: center;">Company Update</td>
                            <td style="padding: 1rem; color: rgba(255,255,255,0.8); text-align: center;">Thread Series</td>
                        </tr>
                        <tr>
                            <td style="padding: 1rem; color: var(--cyber-neon-green); font-weight: bold;">FRIDAY</td>
                            <td style="padding: 1rem; color: rgba(255,255,255,0.8); text-align: center;">Fun Friday Post</td>
                            <td style="padding: 1rem; color: rgba(255,255,255,0.8); text-align: center;">TGIF Stories</td>
                            <td style="padding: 1rem; color: rgba(255,255,255,0.8); text-align: center;">Weekly Roundup</td>
                            <td style="padding: 1rem; color: rgba(255,255,255,0.8); text-align: center;">Weekend Wishes</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php
// CTA Section
$cta_title = 'READY TO DOMINATE SOCIAL MEDIA?';
$cta_subtitle = 'Transform your social media presence with our comprehensive marketing strategies and start building a loyal community of engaged followers.';
$cta_buttons = '<a href="../contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">START SOCIAL MEDIA MARKETING</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
