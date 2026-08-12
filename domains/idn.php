<?php
// Include required utilities
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/seo-meta.php';

// Page variables
$current_page = 'domains';
$page_title = 'International Domain Names (IDN) - Multilingual Domains | HiveNest Matrix';
$page_description = 'International Domain Names (IDN) - Register domains in your native language and script with full multilingual support.';
$page_keywords = 'international domain names, IDN domains, multilingual domains, unicode domains, native language domains';

// SEO configuration (after including seo-meta.php)
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-email-network.jpg',
    'url' => 'https://hivenest.co.za/domains/idn.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'International Domain Names',
        'description' => 'Multilingual domain registration with native script support',
        'serviceType' => 'International Domain Services'
    ])
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Neural Domains', 'url' => '/main-services/domains.php'],
    ['text' => 'International Domains', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = "
// IDN conversion and validation
function convertToIDN() {
    const input = document.getElementById('idn-input');
    const output = document.getElementById('idn-output');
    const domain = input.value.trim();
    
    if (!domain) {
        output.innerHTML = '<p style=\"color: rgba(255,255,255,0.6);\">Enter a domain name to see IDN conversion</p>';
        return;
    }
    
    // Simple punycode simulation (in real implementation, use proper IDN library)
    const converted = convertToPunycode(domain);
    
    output.innerHTML = \`
        <div class=\"cyber-card\">
            <h4 style=\"color: var(--cyber-neon-cyan); margin-bottom: 1rem;\">IDN CONVERSION RESULT</h4>
            <div style=\"margin-bottom: 1rem;\">
                <label style=\"color: var(--cyber-neon-green); font-weight: 600;\">Original (Unicode):</label>
                <p style=\"color: white; font-family: monospace; background: rgba(0,0,0,0.3); padding: 0.5rem; border-radius: 4px; margin: 0.5rem 0;\">\${domain}</p>
            </div>
            <div style=\"margin-bottom: 1.5rem;\">
                <label style=\"color: var(--cyber-neon-pink); font-weight: 600;\">Punycode (ASCII):</label>
                <p style=\"color: white; font-family: monospace; background: rgba(0,0,0,0.3); padding: 0.5rem; border-radius: 4px; margin: 0.5rem 0;\">\${converted}</p>
            </div>
            <button onclick=\"checkIDNAvailability('\${domain}', '\${converted}')\" class=\"btn btn-primary\" style=\"width: 100%;\">
                CHECK AVAILABILITY
            </button>
        </div>
    \`;
}

function convertToPunycode(domain) {
    // Simplified punycode conversion for demo
    // In real implementation, use proper IDN/punycode library
    if (!/[^-\x7F]/.test(domain)) {
        return domain; // Already ASCII
    }
    
    // Simple conversion simulation
    return 'xn--' + btoa(domain).replace(/[^a-zA-Z0-9]/g, '').toLowerCase().substring(0, 10);
}

function checkIDNAvailability(original, punycode) {
    const resultDiv = document.getElementById('availability-result');
    
    resultDiv.innerHTML = \`
        <div class=\"cyber-card\" style=\"text-align: center;\">
            <i class=\"fas fa-spinner fa-spin\" style=\"font-size: 2rem; color: var(--cyber-neon-cyan); margin-bottom: 1rem;\"></i>
            <h4 style=\"color: var(--cyber-neon-cyan);\">CHECKING AVAILABILITY...</h4>
            <p style=\"color: rgba(255,255,255,0.7);\">Scanning global registries for \${original}</p>
        </div>
    \`;
    resultDiv.style.display = 'block';
    
    // Simulate availability check
    setTimeout(() => {
        const available = Math.random() > 0.4;
        const price = Math.floor(Math.random() * 20) + 15; // Random price between 15-35
        
        if (available) {
            resultDiv.innerHTML = \`
                <div class=\"cyber-card\" style=\"border: 2px solid var(--cyber-neon-green);\">
                    <div style=\"text-align: center; margin-bottom: 2rem;\">
                        <i class=\"fas fa-check-circle\" style=\"font-size: 3rem; color: var(--cyber-neon-green); margin-bottom: 1rem;\"></i>
                        <h3 style=\"color: var(--cyber-neon-green); font-size: 1.5rem;\">\${original} IS AVAILABLE</h3>
                        <p style=\"color: rgba(255,255,255,0.8); margin: 1rem 0;\">This international domain is ready for registration</p>
                        <div style=\"background: rgba(0,255,0,0.1); padding: 1rem; border-radius: 8px; margin: 1rem 0;\">
                            <p style=\"color: var(--cyber-neon-green); font-weight: bold; margin: 0;\">Price: $\${price}.99/year</p>
                        </div>
                    </div>
                    <div style=\"text-align: center;\">
                        <button onclick=\"registerIDN('\${original}', '\${punycode}', \${price}.99)\" class=\"btn btn-primary\" style=\"font-size: 1.1rem; padding: 1rem 2rem;\">
                            <i class=\"fas fa-globe\" style=\"margin-right: 0.5rem;\"></i>
                            REGISTER IDN DOMAIN
                        </button>
                    </div>
                </div>
            \`;
        } else {
            resultDiv.innerHTML = \`
                <div class=\"cyber-card\" style=\"border: 2px solid var(--cyber-neon-pink);\">
                    <div style=\"text-align: center; margin-bottom: 2rem;\">
                        <i class=\"fas fa-times-circle\" style=\"font-size: 3rem; color: var(--cyber-neon-pink); margin-bottom: 1rem;\"></i>
                        <h3 style=\"color: var(--cyber-neon-pink); font-size: 1.5rem;\">\${original} IS TAKEN</h3>
                        <p style=\"color: rgba(255,255,255,0.8); margin: 1rem 0;\">This domain is already registered by another user</p>
                    </div>
                    <div style=\"text-align: center;\">
                        <button onclick=\"suggestIDNAlternatives('\${original}')\" class=\"btn btn-secondary\" style=\"margin-right: 1rem;\">
                            SUGGEST ALTERNATIVES
                        </button>
                        <a href=\"/domains/register.php\" class=\"btn btn-primary\">
                            TRY DIFFERENT DOMAIN
                        </a>
                    </div>
                </div>
            \`;
        }
    }, 2000);
}

function registerIDN(original, punycode, price) {
    if (window.shoppingCart) {
        window.shoppingCart.addItem({
            id: \`idn_\${punycode}\`,
            name: \`IDN Domain: \${original}\`,
            price: price,
            type: 'idn_domain',
            punycode: punycode
        });
    }
    
    }
}

function suggestIDNAlternatives(domain) {
    }
}

// Language selector functionality
function selectLanguage(lang) {
    const examples = {
        'arabic': 'موقع.تجاري',
        'chinese': '网站.中国',
        'japanese': 'ウェブサイト.jp',
        'korean': '웹사이트.한국',
        'russian': 'сайт.рф',
        'hindi': 'वेबसाइट.भारत',
        'thai': 'เว็บไซต์.ไทย',
        'hebrew': 'אתר.ישראל'
    };
    
    const input = document.getElementById('idn-input');
    if (examples[lang]) {
        input.value = examples[lang];
        convertToIDN();
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('idn-input');
    if (input) {
        input.addEventListener('input', convertToIDN);
    }
});
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
$hero_title = 'INTERNATIONAL<br><span class="cyber-text">DOMAIN</span><br>NAMES (IDN)';
$hero_subtitle = 'Register domains in your native language and script. Break language barriers with multilingual domain support across all digital dimensions.';
$hero_image = '../assets/images/heroes/hero-domain-server-green.jpg';
$hero_alt = 'International Languages';
include '../utilities/hero-minimal.php';
?>

<?php
// Breadcrumbs
?>

<!-- IDN Converter Section -->
<section class="section" style="padding-top: 2rem;">
    <div class="container">
        <div style="max-width: 800px; margin: 0 auto;">
            <div class="cyber-card">
                <h3 style="color: var(--cyber-neon-cyan); text-align: center; margin-bottom: 2rem;">IDN DOMAIN CONVERTER & CHECKER</h3>
                
                <!-- Language Selection -->
                <div style="margin-bottom: 2rem;">
                    <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 1rem; font-weight: 600;">
                        CHOOSE LANGUAGE EXAMPLE:
                    </label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.5rem;">
                        <button onclick="selectLanguage('arabic')" class="btn btn-outline" style="padding: 0.5rem; font-size: 0.9rem;">العربية</button>
                        <button onclick="selectLanguage('chinese')" class="btn btn-outline" style="padding: 0.5rem; font-size: 0.9rem;">中文</button>
                        <button onclick="selectLanguage('japanese')" class="btn btn-outline" style="padding: 0.5rem; font-size: 0.9rem;">日本語</button>
                        <button onclick="selectLanguage('korean')" class="btn btn-outline" style="padding: 0.5rem; font-size: 0.9rem;">한국어</button>
                        <button onclick="selectLanguage('russian')" class="btn btn-outline" style="padding: 0.5rem; font-size: 0.9rem;">Русский</button>
                        <button onclick="selectLanguage('hindi')" class="btn btn-outline" style="padding: 0.5rem; font-size: 0.9rem;">हिंदी</button>
                        <button onclick="selectLanguage('thai')" class="btn btn-outline" style="padding: 0.5rem; font-size: 0.9rem;">ไทย</button>
                        <button onclick="selectLanguage('hebrew')" class="btn btn-outline" style="padding: 0.5rem; font-size: 0.9rem;">עברית</button>
                    </div>
                </div>
                
                <!-- IDN Input -->
                <div style="margin-bottom: 2rem;">
                    <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                        ENTER DOMAIN IN YOUR LANGUAGE:
                    </label>
                    <input 
                        type="text" 
                        id="idn-input"
                        placeholder="Type domain in any language (e.g., موقع.تجاري, 网站.中国)" 
                        style="width: 100%; padding: 16px; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white; font-size: 1.1rem;"
                    >
                    <p style="color: rgba(255,255,255,0.6); font-size: 0.9rem; margin-top: 0.5rem;">
                        Type directly in your native script or select an example above
                    </p>
                </div>
                
                <!-- Conversion Output -->
                <div id="idn-output" style="margin-bottom: 2rem;">
                    <p style="color: rgba(255,255,255,0.6);">Enter a domain name to see IDN conversion</p>
                </div>
                
                <!-- Availability Result -->
                <div id="availability-result" style="display: none;">
                    <!-- Results will be populated here -->
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// IDN Features and Benefits
include '../utilities/cyber-cards.php';
$idn_features = [
    [
        'icon' => 'fas fa-globe',
        'title' => 'NATIVE SCRIPT SUPPORT',
        'description' => 'Register domains in Arabic, Chinese, Japanese, Korean, Russian, Hindi, Thai, Hebrew, and 40+ other scripts.'
    ],
    [
        'icon' => 'fas fa-language',
        'title' => 'MULTILINGUAL SEO',
        'description' => 'Boost local search rankings with native language domains that resonate with your target audience.'
    ],
    [
        'icon' => 'fas fa-users',
        'title' => 'CULTURAL CONNECTION',
        'description' => 'Build stronger connections with local communities by using familiar language and cultural references.'
    ],
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'BRAND PROTECTION',
        'description' => 'Protect your brand across different languages and scripts with comprehensive IDN registration.'
    ],
    [
        'icon' => 'fas fa-mobile-alt',
        'title' => 'MOBILE OPTIMIZED',
        'description' => 'IDN domains work perfectly on all devices and browsers with full Unicode support.'
    ],
    [
        'icon' => 'fas fa-chart-line',
        'title' => 'MARKET EXPANSION',
        'description' => 'Expand into new markets with domains that speak your customers\' language literally.'
    ]
];

$grid_title = 'INTERNATIONAL DOMAIN ADVANTAGES';
$grid_subtitle = 'Why choose multilingual domains for your global presence';
$grid_content = renderCyberCardsGrid($idn_features);
include '../utilities/grid-section.php';
?>

<?php
// Supported Languages and Scripts
include '../utilities/two-column-section.php';
$supported_languages = [
    [
        'title' => 'MAJOR SCRIPTS SUPPORTED',
        'items' => [
            'Arabic (العربية) - .السعودية, .امارات',
            'Chinese (中文) - .中国, .网络, .公司',
            'Japanese (日本語) - .日本',
            'Korean (한국어) - .한국',
            'Russian (Русский) - .рф, .москва',
            'Hindi (हिंदी) - .भारत',
            'Thai (ไทย) - .ไทย',
            'Hebrew (עברית) - .ישראל'
        ]
    ],
    [
        'title' => 'POPULAR IDN EXTENSIONS',
        'items' => [
            '.中国 (China) - Chinese businesses',
            '.السعودية (Saudi Arabia) - Arabic sites',
            '.рф (Russia) - Russian Federation',
            '.한국 (Korea) - Korean domains',
            '.ישראל (Israel) - Hebrew websites',
            '.भारत (India) - Hindi content',
            '.ไทย (Thailand) - Thai language',
            '.укр (Ukraine) - Ukrainian sites'
        ]
    ]
];

$section_title = 'SUPPORTED LANGUAGES & SCRIPTS';
$section_subtitle = 'Register domains in your native language with full script support';
$section_content = renderTwoColumnLists($supported_languages);
$section_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<?php
// IDN Registration Process
include '../utilities/tabs.php';
$idn_process = [
    [
        'id' => 'step1',
        'title' => '1. LANGUAGE SELECTION',
        'content' => '<h4 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">CHOOSE YOUR SCRIPT</h4>
                     <ul style="color: rgba(255,255,255,0.8); line-height: 1.8;">
                         <li>Select your target language and script</li>
                         <li>Choose appropriate country-code TLD</li>
                         <li>Consider cultural and linguistic preferences</li>
                         <li>Verify character set compatibility</li>
                     </ul>'
    ],
    [
        'id' => 'step2',
        'title' => '2. DOMAIN CREATION',
        'content' => '<h4 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">CREATE YOUR IDN</h4>
                     <ul style="color: rgba(255,255,255,0.8); line-height: 1.8;">
                         <li>Type domain name in native script</li>
                         <li>Automatic punycode conversion</li>
                         <li>Real-time availability checking</li>
                         <li>Character validation and suggestions</li>
                     </ul>'
    ],
    [
        'id' => 'step3',
        'title' => '3. VALIDATION',
        'content' => '<h4 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">TECHNICAL VALIDATION</h4>
                     <ul style="color: rgba(255,255,255,0.8); line-height: 1.8;">
                         <li>Unicode normalization checks</li>
                         <li>Registry policy compliance</li>
                         <li>Character combination validation</li>
                         <li>Trademark conflict screening</li>
                     </ul>'
    ],
    [
        'id' => 'step4',
        'title' => '4. REGISTRATION',
        'content' => '<h4 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">COMPLETE REGISTRATION</h4>
                     <ul style="color: rgba(255,255,255,0.8); line-height: 1.8;">
                         <li>Submit registration to appropriate registry</li>
                         <li>Configure DNS and nameservers</li>
                         <li>Enable IDN-compatible hosting</li>
                         <li>Verify proper display across platforms</li>
                     </ul>'
    ]
];

$tabs_title = 'IDN REGISTRATION PROCESS';
$tabs_subtitle = 'Step-by-step guide to international domain registration';
$tabs_content = renderTabsSection($idn_process);
include '../utilities/grid-section.php';
?>

<?php
// CTA Section
$cta_title = 'READY TO GO GLOBAL WITH IDN DOMAINS?';
$cta_subtitle = 'Break language barriers and connect with global audiences using international domain names in their native scripts.';
$cta_buttons = '<a href="/contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">START IDN REGISTRATION</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>