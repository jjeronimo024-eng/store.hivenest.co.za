<?php
// Include required utilities
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/seo-meta.php';

// Page variables
$current_page = 'cyber-scan';
$page_title = 'Cyber Scan - Advanced Domain Scanner | HiveNest Matrix';
$page_description = 'Cyber Scan - Advanced domain scanner with quantum-level intelligence for complete neural data analysis.';
$page_keywords = 'domain scanner, cyberpunk whois, digital identity scan, neural scan';

// SEO configuration (after including seo-meta.php)
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-domain-server-blue.jpg',
    'url' => 'https://hivenest.co.za/domains/cyber-scan.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Cyber Scan Domain Scanner',
        'description' => 'Advanced domain scanner with quantum-level intelligence',
        'serviceType' => 'Domain Analysis Services'
    ])
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Neural Domains', 'url' => '/main-services/domains.php'],
    ['text' => 'Cyber Scan', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = "
// Domain name validation with visual feedback
function validateDomainInput(input) {
    const value = input.value.trim().toLowerCase();
    
    let isValid = true;
    let errorMsg = '';
    let domainName = '';
    let tld = 'com';
    
    if (!value) {
        isValid = false;
        errorMsg = 'Domain name is required';
    } else if (value.includes('.')) {
        // Match multi-label public suffixes before ordinary one-label TLDs.
        // Example: jasper.co.za must be sent as domain=jasper, tld=co.za.
        const parts = value.split('.');
        if (parts.length >= 2) {
            const multiLabelTlds = [
                'co.za', 'org.za', 'net.za', 'web.za',
                'co.uk', 'org.uk', 'me.uk',
                'com.au', 'net.au', 'org.au',
                'co.nz', 'org.nz', 'net.nz',
                'co.ke', 'or.ke', 'ne.ke'
            ];
            const finalTwoLabels = parts.slice(-2).join('.');
            const suffixLabels = multiLabelTlds.includes(finalTwoLabels) ? 2 : 1;
            const domainLabels = parts.slice(0, -suffixLabels);
            domainName = domainLabels.join('.');
            tld = parts.slice(-suffixLabels).join('.');
            
            // Availability checks accept a registrable domain label, not a URL
            // or subdomain such as www.example.com.
            const validPattern = /^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/;
            if (!validPattern.test(domainName)) {
                isValid = false;
                errorMsg = 'Enter a domain only, for example jasper.co.za';
            }
        } else {
            isValid = false;
            errorMsg = 'Invalid domain format';
        }
    } else {
        // User entered just domain name
        const validPattern = /^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/;
        if (!validPattern.test(value)) {
            isValid = false;
            errorMsg = 'Invalid characters. Use only letters, numbers, and hyphens.';
        } else if (value.length < 2) {
            isValid = false;
            errorMsg = 'Domain name must be at least 2 characters.';
        } else {
            domainName = value;
        }
    }
    
    // Apply visual feedback
    if (value === '') {
        input.style.border = '1px solid rgba(0,255,255,0.3)';
        input.style.boxShadow = 'none';
    } else if (isValid) {
        input.style.border = '1px solid rgba(0,255,0,0.5)';
        input.style.boxShadow = '0 0 10px rgba(0,255,0,0.3)';
    } else {
        input.style.border = '1px solid rgba(255,0,0,0.7)';
        input.style.boxShadow = '0 0 15px rgba(255,0,0,0.5)';
    }
    
    return { isValid, errorMsg, domainName, tld };
}

// Domain scan form handling - LIVE API
const cyberScanForm = document.getElementById('scan-form');
if (cyberScanForm) cyberScanForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const domainInput = e.target.domain;
    const validation = validateDomainInput(domainInput);
    
    // Validate before submitting
    if (!validation.isValid) {
        console.warn('❌ ' + validation.errorMsg);
        domainInput.focus();
        return;
    }
    
    const domainName = validation.domainName;
    const tld = validation.tld;
    
    // Show loading state
    const submitBtn = this.querySelector('button[type=\"submit\"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class=\"fas fa-spinner fa-spin\"></i> SCANNING MATRIX...';
    submitBtn.disabled = true;
    
    const results = document.getElementById('scan-results');
    
    try {
        // Call LIVE MyOrderBox API
        const response = await fetch('/api/domains_live.php?action=check-availability', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                domain: domainName,
                tlds: [tld]
            })
        });
        
        const data = await response.json();
        
        if (data.success && data.results && data.results.length > 0) {
            const domainResult = data.results[0];
            const isAvailable = domainResult.available;
            const statusColor = isAvailable ? 'var(--cyber-neon-green)' : 'var(--cyber-neon-pink)';
            const statusBg = isAvailable ? 'rgba(0,255,0,0.1)' : 'rgba(255,0,255,0.1)';
            const statusText = isAvailable ? 'AVAILABLE FOR REGISTRATION' : 'REGISTERED / NOT AVAILABLE';
            const fullDomain = domainResult.domain || (domainName + '.' + tld);
            
            results.style.display = 'block';
            results.innerHTML = '<div class=\"cyber-card\" style=\"max-width: 800px; margin: 2rem auto;\">' +
                '<h3 style=\"color: var(--cyber-neon-cyan); margin-bottom: 1rem;\">SCAN RESULTS FOR: ' + fullDomain.toUpperCase() + '</h3>' +
                '<div style=\"display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;\">' +
                '<div style=\"background: ' + statusBg + '; padding: 1rem; border-radius: 8px; border: 1px solid ' + statusColor + ';\">' +
                '<h4 style=\"color: ' + statusColor + '; margin-bottom: 0.5rem;\">DOMAIN STATUS</h4>' +
                '<p style=\"color: white;\">' + statusText + '</p>' +
                '<p style=\"color: rgba(255,255,255,0.7); font-size: 0.9em; margin-top: 0.5rem;\">Status Code: ' + domainResult.status + '</p>' +
                '</div>' +
                '<div style=\"background: rgba(0,255,255,0.1); padding: 1rem; border-radius: 8px; border: 1px solid var(--cyber-neon-cyan);\">' +
                '<h4 style=\"color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;\">PRICING</h4>' +
                '<p style=\"color: white;\">$' + domainResult.price + ' USD/year</p>' +
                '<p style=\"color: rgba(255,255,255,0.7); font-size: 0.9em; margin-top: 0.5rem;\">TLD: .' + domainResult.tld + '</p>' +
                '</div>' +
                '<div style=\"background: rgba(255,0,255,0.1); padding: 1rem; border-radius: 8px; border: 1px solid var(--cyber-neon-pink);\">' +
                '<h4 style=\"color: var(--cyber-neon-pink); margin-bottom: 0.5rem;\">API SOURCE</h4>' +
                '<p style=\"color: white;\">MyOrderBox Live</p>' +
                '<p style=\"color: rgba(255,255,255,0.7); font-size: 0.9em; margin-top: 0.5rem;\">Real-time data</p>' +
                '</div>' +
                '</div>' +
                '<div style=\"text-align: center; margin-top: 2rem;\">' +
                (isAvailable ? 
                    '<a href=\"/domains/register.php?tld=.' + encodeURIComponent(domainResult.tld) + '\" class=\"btn btn-primary\">REGISTER THIS DOMAIN</a>' :
                    '<p style=\"color: rgba(255,255,255,0.8);\">This domain is already registered by someone else.</p>'
                ) +
                '<p style=\"color: rgba(255,255,255,0.6); font-size: 0.85em; margin-top: 1rem;\">Scan completed: ' + data.timestamp + '</p>' +
                '</div>' +
                '</div>';
        } else {
            throw new Error('Invalid API response');
        }
    } catch (error) {
        console.error('Domain scan error:', error);
        results.style.display = 'block';
        results.innerHTML = '<div class=\"cyber-card\" style=\"max-width: 800px; margin: 2rem auto; background: rgba(255,0,0,0.1); border: 1px solid rgba(255,0,0,0.3);\">' +
            '<h4 style=\"color: var(--cyber-neon-pink);\"><i class=\"fas fa-exclamation-triangle\"></i> SCAN FAILED</h4>' +
            '<p style=\"color: white;\">Unable to scan domain. Please check the domain name and try again.</p>' +
            '</div>';
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
});

// Add real-time validation
const scanInput = document.querySelector('#scan-form input[name=\"domain\"]');
if (scanInput) {
    scanInput.addEventListener('input', function() {
        validateDomainInput(this);
    });
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
// Hero Section with Scan Form
$hero_title = 'CYBER<br><span class="cyber-text">SCAN</span><br>PROTOCOL';
$hero_subtitle = 'Advanced domain scanner with quantum-level intelligence. Scan any domain across all digital dimensions for complete neural data.';
$hero_image = '../assets/images/heroes/hero-security-interface.jpg';
$hero_alt = 'Cyber Scan Matrix';
include '../utilities/hero-minimal.php';
?>

<!-- Domain Scan Form Section -->
<section class="section" style="padding-top: 2rem;">
    <div class="container">
        <div class="cyber-card" style="max-width: 700px; margin: 0 auto; text-align: center;">
            <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 2rem;">SELECT A CYBER SCAN TOOL</h3>
            <div class="services-grid">
                <a href="/domains/whois.php" class="cyber-card" style="text-decoration:none"><i class="fas fa-database" style="font-size:2rem;color:var(--cyber-neon-cyan)"></i><h3>WHOIS REGISTRY</h3><p>Registration dates, registrar, status and nameservers.</p></a>
                <a href="/domains/dns-analysis.php" class="cyber-card" style="text-decoration:none"><i class="fas fa-network-wired" style="font-size:2rem;color:var(--cyber-neon-green)"></i><h3>DNS ANALYSIS</h3><p>Inspect live DNS records and mail routing.</p></a>
                <a href="/domains/site-analyzer.php" class="cyber-card" style="text-decoration:none"><i class="fas fa-chart-line" style="font-size:2rem;color:var(--cyber-neon-pink)"></i><h3>SITE ANALYZER</h3><p>Open the detailed website analyzer inside HiveNest.</p></a>
            </div>
        </div>
        
        <div id="scan-results" style="display: none;"></div>
    </div>
</section>

<?php
// Scan Protocols Features
include '../utilities/cyber-cards.php';
$scan_features = [
    [
        'icon' => 'fas fa-search',
        'title' => 'DOMAIN INTELLIGENCE',
        'description' => 'Complete domain ownership, registration, and expiration data across all dimensions.'
    ],
    [
        'icon' => 'fas fa-server',
        'title' => 'SERVER ANALYSIS',
        'description' => 'DNS records, nameservers, and hosting infrastructure neural mapping.'
    ],
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'SECURITY SCAN',
        'description' => 'SSL certificates, security protocols, and vulnerability assessment systems.'
    ],
    [
        'icon' => 'fas fa-globe',
        'title' => 'GLOBAL PRESENCE',
        'description' => 'Geographic location, IP ranges, and multi-dimensional hosting analysis.'
    ],
    [
        'icon' => 'fas fa-network-wired',
        'title' => 'NETWORK MAPPING',
        'description' => 'Advanced network topology analysis and connection pattern recognition.'
    ],
    [
        'icon' => 'fas fa-chart-line',
        'title' => 'PERFORMANCE METRICS',
        'description' => 'Real-time performance data, load times, and optimization recommendations.'
    ]
];

$grid_title = 'SCAN PROTOCOLS';
$grid_subtitle = 'Advanced domain intelligence gathering systems';
$grid_content = renderCyberCardsGrid($scan_features);
include '../utilities/grid-section.php';
?>

<?php
// Scan Types (Using Tabs)
include '../utilities/tabs.php';
$scan_tabs = [
    [
        'title' => 'WHOIS DATA',
        'icon' => 'fas fa-database',
        'content' => '
            <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">Domain Registration Information</h3>
            <div style="columns: 2; gap: 2rem;">
                <ul style="list-style: none; padding: 0; text-align: left;">
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Domain Owner Details</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Registration Date</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Expiration Date</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Registrar Information</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Contact Information</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Privacy Status</li>
                </ul>
            </div>
        '
    ],
    [
        'title' => 'DNS ANALYSIS',
        'icon' => 'fas fa-sitemap',
        'content' => '
            <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">DNS Configuration Analysis</h3>
            <div style="columns: 2; gap: 2rem;">
                <ul style="list-style: none; padding: 0; text-align: left;">
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ A Records</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ CNAME Records</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ MX Records</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ TXT Records</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Nameservers</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ TTL Settings</li>
                </ul>
            </div>
        '
    ],
    [
        'title' => 'SECURITY SCAN',
        'icon' => 'fas fa-shield-alt',
        'content' => '
            <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">Security & SSL Analysis</h3>
            <div style="columns: 2; gap: 2rem;">
                <ul style="list-style: none; padding: 0; text-align: left;">
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ SSL Certificate Status</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Certificate Authority</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Security Headers</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Vulnerability Check</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Encryption Strength</li>
                    <li style="margin: 0.5rem 0; color: rgba(255, 255, 255, 0.8);">✓ Security Rating</li>
                </ul>
            </div>
        '
    ]
];

$grid_title = 'SCAN CAPABILITIES';
$grid_subtitle = 'Comprehensive domain analysis across multiple protocols';
$grid_content = renderTabs($scan_tabs, 'scan-types', 0);
$grid_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<?php
// Scan Use Cases
$scan_uses = [
    [
        'icon' => 'fas fa-shield-virus',
        'title' => 'SECURITY AUDIT',
        'description' => 'Comprehensive security assessment for domain and hosting infrastructure vulnerabilities.'
    ],
    [
        'icon' => 'fas fa-search-dollar',
        'title' => 'ACQUISITION RESEARCH',
        'description' => 'Research domain availability, history, and ownership for potential acquisition opportunities.'
    ],
    [
        'icon' => 'fas fa-chart-bar',
        'title' => 'COMPETITIVE ANALYSIS',
        'description' => 'Analyze competitor domains for infrastructure insights and strategic intelligence.'
    ],
    [
        'icon' => 'fas fa-tools',
        'title' => 'TECHNICAL AUDIT',
        'description' => 'Deep technical analysis for DNS optimization, performance tuning, and best practices.'
    ]
];

$grid_title = 'CYBER SCAN USE CASES';
$grid_subtitle = 'Practical applications for domain intelligence';
$grid_content = renderCyberCardsGrid($scan_uses);
include '../utilities/grid-section.php';
?>

<?php
// CTA Section
$cta_title = 'READY TO SCAN THE DIGITAL MATRIX?';
$cta_subtitle = 'Unlock domain intelligence with our advanced cyber scan protocols and neural analysis systems.';
$cta_buttons = '<a href="/contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">START SCANNING</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
