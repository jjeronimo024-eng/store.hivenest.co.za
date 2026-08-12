<?php
// Include required utilities
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/seo-meta.php';

// Page variables
$current_page = 'domains';
$page_title = 'Sunrise Domains - Priority Access | HiveNest Matrix';
$page_description = 'Sunrise Domains - Get priority access to premium domain names before general availability. Secure your trademark and brand protection.';
$page_keywords = 'sunrise domains, premium domains, early access, trademark domains, priority registration';

// SEO configuration (after including seo-meta.php)
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-email-network.jpg',
    'url' => 'https://hivenest.co.za/domains/sunrise.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Sunrise Domain Registration',
        'description' => 'Priority access to premium domains before general availability',
        'serviceType' => 'Premium Domain Services'
    ])
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Neural Domains', 'url' => '/main-services/domains.php'],
    ['text' => 'Sunrise Domains', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = "
// Sunrise application functions
function applySunrise(extension) {
    document.querySelector('select[name=\"extension\"]').value = extension;
    document.getElementById('sunrise-form').scrollIntoView({ behavior: 'smooth' });
}

function registerDomain(extension) {
    console.info(\`Opening domain registration for \${extension}\`);
    window.location.href = \`/domains/register.php?tld=\${encodeURIComponent(extension)}\`;
}

// Form submission
document.getElementById('sunrise-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    const submitBtn = e.target.querySelector('button[type=\"submit\"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class=\"fas fa-spinner fa-spin\" style=\"margin-right: 0.5rem;\"></i>PROCESSING...';
    submitBtn.disabled = true;
    
    try {
        // Simulate API call
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        showMessage('success', 'Sunrise application submitted successfully! Our trademark validation team will contact you within 24 hours.');
        e.target.reset();
        
    } catch (error) {
        showMessage('error', 'Application submission failed. Please try again or contact support.');
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
});

// Show message function
function showMessage(type, text) {
    const message = document.createElement('div');
    message.style.cssText = \`
        position: fixed;
        top: 20px;
        right: 20px;
        background: rgba(26, 26, 26, 0.95);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        border: 1px solid \${type === 'success' ? 'var(--cyber-neon-green)' : 'var(--cyber-neon-pink)'};
        z-index: 10000;
        max-width: 400px;
        animation: slideIn 0.3s ease-out;
    \`;
    message.textContent = text;
    
    document.body.appendChild(message);
    
    setTimeout(() => {
        message.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => message.remove(), 300);
    }, 5000);
}

// Add animations CSS
const style = document.createElement('style');
style.textContent = \`
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    .sunrise-active {
        border-color: var(--cyber-neon-green);
        position: relative;
    }
    
    .sunrise-upcoming {
        border-color: var(--cyber-neon-cyan);
        position: relative;
    }
    
    .sunrise-ended {
        border-color: var(--cyber-neon-pink);
        position: relative;
        opacity: 0.8;
    }
\`;
document.head.appendChild(style);
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
// Hero Section
$hero_title = 'SUNRISE<br><span class="cyber-text">DOMAIN</span><br>PRIORITY ACCESS';
$hero_subtitle = 'Get priority access to premium domain names before general availability. Secure your trademark and brand protection in the digital matrix.';
$hero_image = '../assets/images/heroes/hero-domain-server-blue.jpg';
$hero_alt = 'Sunrise Domains Priority Access';
include '../utilities/hero-minimal.php';
?>

<?php
// Breadcrumbs
?>

<!-- What is Sunrise Section -->
<section id="sunrise" class="section">
    <div class="container">
        <div class="text-center mb-8">
            <h2>WHAT IS SUNRISE PERIOD?</h2>
            <p class="hero-subtitle">Exclusive early access for trademark holders</p>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center;">
            <div>
                <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 2rem;">PRIORITY REGISTRATION</h3>
                <p style="color: rgba(255,255,255,0.9); line-height: 1.8; margin-bottom: 2rem;">
                    Sunrise period is a special registration phase that occurs before domains become available to the general public. 
                    During this exclusive window, trademark holders can register domains that match their registered trademarks.
                </p>
                
                <div style="background: rgba(0,255,255,0.1); padding: 1.5rem; border-radius: 12px; border: 1px solid rgba(0,255,255,0.3);">
                    <h4 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">
                        <i class="fas fa-clock"></i> TYPICAL SUNRISE TIMELINE
                    </h4>
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin: 0.5rem 0; color: rgba(255,255,255,0.8);">📅 Days 1-30: Trademark validation</li>
                        <li style="margin: 0.5rem 0; color: rgba(255,255,255,0.8);">🌅 Days 31-60: Sunrise registration</li>
                        <li style="margin: 0.5rem 0; color: rgba(255,255,255,0.8);">🌍 Day 61+: General availability</li>
                    </ul>
                </div>
            </div>
            
            <div class="cyber-card">
                <h3 style="margin-bottom: 2rem;">SUNRISE BENEFITS</h3>
                <div style="display: grid; gap: 1.5rem;">
                    <div style="display: flex; align-items: flex-start; gap: 1rem;">
                        <div style="width: 40px; height: 40px; background: linear-gradient(45deg, var(--cyber-neon-green), var(--cyber-neon-orange)); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="fas fa-shield-alt" style="color: white;"></i>
                        </div>
                        <div>
                            <h4 style="color: var(--cyber-neon-green); margin-bottom: 0.5rem;">BRAND PROTECTION</h4>
                            <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">
                                Secure your trademark before competitors or domain squatters
                            </p>
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: flex-start; gap: 1rem;">
                        <div style="width: 40px; height: 40px; background: linear-gradient(45deg, var(--cyber-neon-cyan), var(--cyber-electric-blue)); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="fas fa-star" style="color: white;"></i>
                        </div>
                        <div>
                            <h4 style="color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;">PREMIUM NAMES</h4>
                            <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">
                                Access to the most valuable domain names in new extensions
                            </p>
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: flex-start; gap: 1rem;">
                        <div style="width: 40px; height: 40px; background: linear-gradient(45deg, var(--cyber-neon-pink), var(--cyber-neon-purple)); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="fas fa-trophy" style="color: white;"></i>
                        </div>
                        <div>
                            <h4 style="color: var(--cyber-neon-pink); margin-bottom: 0.5rem;">EXCLUSIVE ACCESS</h4>
                            <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">
                                No competition from general public during registration period
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Current Sunrise Opportunities
include '../utilities/cyber-cards.php';
$sunrise_opportunities = [
    [
        'icon' => 'fas fa-store',
        'title' => '.STORE',
        'description' => 'Perfect for e-commerce and retail businesses. Secure your brand in the .store namespace.',
        'status' => 'ACTIVE',
        'status_class' => 'sunrise-active',
        'end_date' => 'March 15, 2025',
        'price' => '$299/year',
        'button' => '<button onclick="applySunrise(\'.store\')" class="btn btn-primary" style="width: 100%;">APPLY NOW</button>'
    ],
    [
        'icon' => 'fas fa-certificate',
        'title' => '.BRAND',
        'description' => 'Ideal for companies looking to establish their brand presence online with a premium extension.',
        'status' => 'UPCOMING',
        'status_class' => 'sunrise-upcoming',
        'start_date' => 'April 1, 2025',
        'price' => '$199/year',
        'button' => '<button onclick="applySunrise(\'.brand\')" class="btn btn-secondary" style="width: 100%;">PRE-REGISTER</button>'
    ],
    [
        'icon' => 'fas fa-microchip',
        'title' => '.TECH',
        'description' => 'Now available for general registration. Perfect for technology companies and startups.',
        'status' => 'ENDED',
        'status_class' => 'sunrise-ended',
        'end_date' => 'February 28, 2025',
        'price' => '$59.99/year',
        'button' => '<button onclick="registerDomain(\'.tech\')" class="btn btn-outline" style="width: 100%;">REGISTER NOW</button>'
    ]
];

$grid_title = 'CURRENT SUNRISE OPPORTUNITIES';
$grid_subtitle = 'Active and upcoming sunrise periods';
$grid_content = renderSunriseCardsGrid($sunrise_opportunities);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<!-- Sunrise Application Form -->
<section id="apply" class="section">
    <div class="container">
        <div class="text-center mb-8">
            <h2>SUNRISE APPLICATION PROCESS</h2>
            <p class="hero-subtitle">How to apply for sunrise domain registration</p>
        </div>
        
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 3rem;">
            <!-- Application Form -->
            <div class="cyber-card">
                <h3 style="margin-bottom: 2rem;">TRADEMARK VALIDATION</h3>
                
                <form id="sunrise-form" style="display: grid; gap: 1.5rem;">
                    <div>
                        <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; text-transform: uppercase;">Domain Extension:</label>
                        <select name="extension" required style="width: 100%; padding: 1rem; background: rgba(26, 26, 26, 0.8); border: 1px solid rgba(255, 0, 255, 0.3); border-radius: 8px; color: white;">
                            <option value="">Select extension</option>
                            <option value=".store">.store (Active)</option>
                            <option value=".brand">.brand (Upcoming)</option>
                            <option value=".shop">.shop (Upcoming)</option>
                            <option value=".online">.online (Upcoming)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; text-transform: uppercase;">Desired Domain Name:</label>
                        <input type="text" name="domain" placeholder="yourbrand" required style="width: 100%; padding: 1rem; background: rgba(26, 26, 26, 0.8); border: 1px solid rgba(255, 0, 255, 0.3); border-radius: 8px; color: white;">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; text-transform: uppercase;">Company Name:</label>
                            <input type="text" name="company" required style="width: 100%; padding: 1rem; background: rgba(26, 26, 26, 0.8); border: 1px solid rgba(255, 0, 255, 0.3); border-radius: 8px; color: white;">
                        </div>
                        
                        <div>
                            <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; text-transform: uppercase;">Trademark Number:</label>
                            <input type="text" name="trademark" required style="width: 100%; padding: 1rem; background: rgba(26, 26, 26, 0.8); border: 1px solid rgba(255, 0, 255, 0.3); border-radius: 8px; color: white;">
                        </div>
                    </div>
                    
                    <div>
                        <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; text-transform: uppercase;">Trademark Office:</label>
                        <select name="trademark_office" required style="width: 100%; padding: 1rem; background: rgba(26, 26, 26, 0.8); border: 1px solid rgba(255, 0, 255, 0.3); border-radius: 8px; color: white;">
                            <option value="">Select office</option>
                            <option value="USPTO">USPTO (United States)</option>
                            <option value="EUIPO">EUIPO (European Union)</option>
                            <option value="WIPO">WIPO (World Intellectual Property)</option>
                            <option value="CIPRO">CIPRO (South Africa)</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; text-transform: uppercase;">Contact Email:</label>
                        <input type="email" name="email" required style="width: 100%; padding: 1rem; background: rgba(26, 26, 26, 0.8); border: 1px solid rgba(255, 0, 255, 0.3); border-radius: 8px; color: white;">
                    </div>
                    
                    <div>
                        <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; text-transform: uppercase;">Additional Information:</label>
                        <textarea name="notes" rows="4" placeholder="Any additional information about your trademark or registration..." style="width: 100%; padding: 1rem; background: rgba(26, 26, 26, 0.8); border: 1px solid rgba(255, 0, 255, 0.3); border-radius: 8px; color: white; resize: vertical;"></textarea>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="terms" id="terms" required style="width: 18px; height: 18px;">
                        <label for="terms" style="color: rgba(255, 255, 255, 0.8);">
                            I agree to the <a href="/legal/terms-of-service.php" style="color: var(--cyber-neon-cyan);">Sunrise Terms</a> and trademark validation process
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="font-size: 1.1rem; padding: 1.2rem 2rem; margin-top: 1rem;">
                        <i class="fas fa-paper-plane" style="margin-right: 0.5rem;"></i>
                        SUBMIT APPLICATION
                    </button>
                </form>
            </div>
            
            <!-- Requirements Panel -->
            <div class="cyber-card">
                <h3 style="margin-bottom: 2rem;">REQUIREMENTS</h3>
                
                <div style="display: grid; gap: 1.5rem;">
                    <div>
                        <h4 style="color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;">TRADEMARK VALIDATION</h4>
                        <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">
                            Valid trademark registration in recognized intellectual property office
                        </p>
                    </div>
                    
                    <div>
                        <h4 style="color: var(--cyber-neon-green); margin-bottom: 0.5rem;">EXACT MATCH</h4>
                        <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">
                            Domain must exactly match your registered trademark
                        </p>
                    </div>
                    
                    <div>
                        <h4 style="color: var(--cyber-neon-pink); margin-bottom: 0.5rem;">DOCUMENTATION</h4>
                        <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">
                            Trademark certificate and proof of registration required
                        </p>
                    </div>
                    
                    <div>
                        <h4 style="color: var(--cyber-neon-orange); margin-bottom: 0.5rem;">VALIDATION FEE</h4>
                        <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">
                            $150 non-refundable validation fee per application
                        </p>
                    </div>
                </div>
                
                <div style="margin-top: 2rem; padding: 1rem; background: rgba(255,165,0,0.1); border-radius: 8px; border: 1px solid rgba(255,165,0,0.3);">
                    <h4 style="color: var(--cyber-neon-orange); margin-bottom: 0.5rem;">
                        <i class="fas fa-exclamation-triangle"></i> IMPORTANT NOTE
                    </h4>
                    <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">
                        Applications are processed on a first-come, first-served basis. Submit early to secure your desired domain.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// CTA Section
$cta_title = 'READY TO SECURE YOUR PREMIUM DOMAIN?';
$cta_subtitle = 'Don\'t wait for general availability. Secure your trademark domain during the exclusive sunrise period and protect your brand.';
$cta_buttons = '<a href="/contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">GET SUNRISE HELP</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
