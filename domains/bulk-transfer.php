<?php
// Include required utilities
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/seo-meta.php';

// Page variables
$current_page = 'domains';
$page_title = 'Bulk Domain Transfer - Mass Migration | HiveNest Matrix';
$page_description = 'Bulk Domain Transfer - Migrate multiple domains simultaneously with our quantum-powered bulk transfer system.';
$page_keywords = 'bulk domain transfer, mass domain migration, multiple domain transfer, cyberpunk transfer';

// SEO configuration (after including seo-meta.php)
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-email-global.jpg',
    'url' => 'https://hivenest.co.za/domains/bulk-transfer.php',
    'type' => 'service',
    'structured_data' => generateStructuredData('service', [
        'name' => 'Bulk Domain Transfer Service',
        'description' => 'Quantum-powered mass domain migration system',
        'serviceType' => 'Domain Transfer Services'
    ])
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Neural Domains', 'url' => '/main-services/domains.php'],
    ['text' => 'Bulk Transfer', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = "
let domainList = [];

// Add domain to transfer list
function addDomain() {
    const domainInput = document.getElementById('domain-input');
    const authCodeInput = document.getElementById('auth-code-input');
    const domain = domainInput.value.trim();
    const authCode = authCodeInput.value.trim();
    
    if (!domain) {
        console.warn('Please enter a domain name');
        return;
    }
    
    // Check if domain already exists
    if (domainList.find(d => d.domain === domain)) {
        console.warn('Domain already added to list');
        return;
    }
    
    // Add to list
    domainList.push({
        domain: domain,
        authCode: authCode,
        status: 'pending'
    });
    
    // Clear inputs
    domainInput.value = '';
    authCodeInput.value = '';
    
    // Update display
    updateDomainList();
    updateTransferSummary();
}

// Remove domain from list
function removeDomain(domain) {
    domainList = domainList.filter(d => d.domain !== domain);
    updateDomainList();
    updateTransferSummary();
}

// Update domain list display
function updateDomainList() {
    const container = document.getElementById('domain-list');
    
    if (domainList.length === 0) {
        container.innerHTML = '<p style=\"text-align: center; color: rgba(255,255,255,0.6); font-style: italic;\">No domains added yet</p>';
        return;
    }
    
    container.innerHTML = domainList.map((item, index) => \`
        <div class=\"cyber-card\" style=\"margin-bottom: 1rem;\">
            <div style=\"display: flex; justify-content: space-between; align-items: center;\">
                <div style=\"flex: 1;\">
                    <h4 style=\"color: var(--cyber-neon-cyan); margin: 0 0 0.5rem 0; font-size: 1.1rem;\">\${item.domain}</h4>
                    <p style=\"color: rgba(255,255,255,0.7); margin: 0; font-size: 0.9rem;\">
                        Auth Code: \${item.authCode ? '••••••••' : 'Not provided'}
                    </p>
                </div>
                <div style=\"display: flex; gap: 1rem; align-items: center;\">
                    <span style=\"color: var(--cyber-neon-green); font-size: 0.9rem;\">Ready</span>
                    <button onclick=\"removeDomain('\${item.domain}')\" class=\"btn btn-outline\" style=\"padding: 0.5rem 1rem; font-size: 0.9rem;\">
                        <i class=\"fas fa-trash\"></i>
                    </button>
                </div>
            </div>
        </div>
    \`).join('');
}

// Update transfer summary
function updateTransferSummary() {
    const summaryDiv = document.getElementById('transfer-summary');
    const processBtn = document.getElementById('process-transfer-btn');
    
    if (domainList.length === 0) {
        summaryDiv.style.display = 'none';
        return;
    }
    
    const totalDomains = domainList.length;
    const transferFee = 12.99; // Per domain
    const bulkDiscount = totalDomains >= 10 ? 0.15 : totalDomains >= 5 ? 0.10 : 0;
    const subtotal = totalDomains * transferFee;
    const discount = subtotal * bulkDiscount;
    const total = subtotal - discount;
    
    summaryDiv.style.display = 'block';
    summaryDiv.innerHTML = \`
        <div class=\"cyber-card\">
            <h3 style=\"color: var(--cyber-neon-cyan); margin-bottom: 1rem;\">TRANSFER SUMMARY</h3>
            <div style=\"display: grid; gap: 0.5rem; margin-bottom: 1rem;\">
                <div style=\"display: flex; justify-content: space-between;\">
                    <span style=\"color: rgba(255,255,255,0.8);\">\${totalDomains} Domain(s)</span>
                    <span style=\"color: rgba(255,255,255,0.8);\">$\${subtotal.toFixed(2)}</span>
                </div>
                \${bulkDiscount > 0 ? \`
                    <div style=\"display: flex; justify-content: space-between;\">
                        <span style=\"color: var(--cyber-neon-green);\">Bulk Discount (\${(bulkDiscount * 100).toFixed(0)}%)</span>
                        <span style=\"color: var(--cyber-neon-green);\">-$\${discount.toFixed(2)}</span>
                    </div>
                \` : ''}
                <div style=\"display: flex; justify-content: space-between; font-weight: bold; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 0.5rem;\">
                    <span style=\"color: var(--cyber-neon-cyan);\">Total</span>
                    <span style=\"color: var(--cyber-neon-cyan);\">$\${total.toFixed(2)}</span>
                </div>
            </div>
            <button onclick=\"processTransfer()\" class=\"btn btn-primary\" style=\"width: 100%;\">
                <i class=\"fas fa-rocket\" style=\"margin-right: 0.5rem;\"></i>
                INITIATE BULK TRANSFER
            </button>
        </div>
    \`;
}

// Process bulk transfer
function processTransfer() {
    if (domainList.length === 0) {
        console.warn('Please add domains to transfer');
        return;
    }
    
    // Show processing state
    const processBtn = document.querySelector('#transfer-summary button');
    processBtn.innerHTML = '<i class=\"fas fa-spinner fa-spin\" style=\"margin-right: 0.5rem;\"></i>PROCESSING TRANSFER...';
    processBtn.disabled = true;
    
    // Simulate processing
    setTimeout(() => {
        }
        
        // Reset form
        domainList = [];
        updateDomainList();
        updateTransferSummary();
        
        processBtn.innerHTML = '<i class=\"fas fa-rocket\" style=\"margin-right: 0.5rem;\"></i>INITIATE BULK TRANSFER';
        processBtn.disabled = false;
    }, 3000);
}

// Bulk import from CSV/text
function importDomains() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.csv,.txt';
    input.onchange = function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const text = e.target.result;
            const lines = text.split('\\n').filter(line => line.trim());
            
            lines.forEach(line => {
                const parts = line.split(',').map(p => p.trim());
                const domain = parts[0];
                const authCode = parts[1] || '';
                
                if (domain && !domainList.find(d => d.domain === domain)) {
                    domainList.push({
                        domain: domain,
                        authCode: authCode,
                        status: 'pending'
                    });
                }
            });
            
            updateDomainList();
            updateTransferSummary();
        };
        reader.readAsText(file);
    };
    input.click();
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    updateDomainList();
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
$hero_title = 'BULK<br><span class="cyber-text">DOMAIN</span><br>TRANSFER';
$hero_subtitle = 'Migrate multiple domains simultaneously with our quantum-powered bulk transfer system. Save time and money with mass domain migration.';
$hero_image = '../assets/images/heroes/hero-domain-network.jpg';
$hero_alt = 'Bulk Domain Migration';
include '../utilities/hero-minimal.php';
?>

<?php
// Breadcrumbs
?>

<!-- Bulk Transfer Form Section -->
<section class="section" style="padding-top: 2rem;">
    <div class="container">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; max-width: 1200px; margin: 0 auto;">
            
            <!-- Add Domains Panel -->
            <div class="cyber-card">
                <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 2rem;">ADD DOMAINS TO TRANSFER</h3>
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                        Domain Name
                    </label>
                    <input 
                        type="text" 
                        id="domain-input"
                        placeholder="example.com" 
                        style="width: 100%; padding: 12px; border-radius: 6px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white;"
                    >
                </div>
                
                <div style="margin-bottom: 2rem;">
                    <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                        Auth Code (Optional)
                    </label>
                    <input 
                        type="text" 
                        id="auth-code-input"
                        placeholder="Authorization code from current registrar" 
                        style="width: 100%; padding: 12px; border-radius: 6px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white;"
                    >
                    <p style="color: rgba(255,255,255,0.6); font-size: 0.9rem; margin-top: 0.5rem;">
                        Auth codes can be added later if not available now
                    </p>
                </div>
                
                <button onclick="addDomain()" class="btn btn-primary" style="width: 100%; margin-bottom: 1rem;">
                    <i class="fas fa-plus" style="margin-right: 0.5rem;"></i>
                    ADD DOMAIN
                </button>
                
                <button onclick="importDomains()" class="btn btn-secondary" style="width: 100%;">
                    <i class="fas fa-upload" style="margin-right: 0.5rem;"></i>
                    IMPORT FROM FILE
                </button>
                
                <div style="margin-top: 1rem; padding: 1rem; background: rgba(0,255,255,0.1); border-radius: 6px; border: 1px solid rgba(0,255,255,0.2);">
                    <h4 style="color: var(--cyber-neon-cyan); margin: 0 0 0.5rem 0; font-size: 0.9rem;">BULK DISCOUNTS</h4>
                    <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.9rem;">
                        <li style="color: rgba(255,255,255,0.8); margin: 0.25rem 0;">5-9 domains: 10% discount</li>
                        <li style="color: rgba(255,255,255,0.8); margin: 0.25rem 0;">10+ domains: 15% discount</li>
                    </ul>
                </div>
            </div>
            
            <!-- Transfer List Panel -->
            <div class="cyber-card">
                <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 2rem;">TRANSFER LIST</h3>
                
                <div id="domain-list" style="max-height: 400px; overflow-y: auto; margin-bottom: 2rem;">
                    <p style="text-align: center; color: rgba(255,255,255,0.6); font-style: italic;">No domains added yet</p>
                </div>
                
                <div id="transfer-summary" style="display: none;">
                    <!-- Summary will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Bulk Transfer Benefits
include '../utilities/cyber-cards.php';
$transfer_benefits = [
    [
        'icon' => 'fas fa-rocket',
        'title' => 'MASS MIGRATION',
        'description' => 'Transfer multiple domains simultaneously with our quantum-powered bulk processing system.'
    ],
    [
        'icon' => 'fas fa-percentage',
        'title' => 'BULK DISCOUNTS',
        'description' => 'Save up to 15% on transfer fees when moving 10 or more domains to our neural network.'
    ],
    [
        'icon' => 'fas fa-shield-alt',
        'title' => 'SECURE PROCESS',
        'description' => 'Military-grade encryption and secure auth code handling throughout the transfer process.'
    ],
    [
        'icon' => 'fas fa-clock',
        'title' => 'RAPID DEPLOYMENT',
        'description' => 'Most transfers complete within 5-7 days with automated processing and status updates.'
    ],
    [
        'icon' => 'fas fa-headset',
        'title' => 'TRANSFER SUPPORT',
        'description' => 'Dedicated transfer specialists guide you through the process and handle any complications.'
    ],
    [
        'icon' => 'fas fa-chart-line',
        'title' => 'PROGRESS TRACKING',
        'description' => 'Real-time dashboard shows transfer status for each domain with detailed progress reports.'
    ]
];

$grid_title = 'BULK TRANSFER ADVANTAGES';
$grid_subtitle = 'Everything you need for mass domain migration';
$grid_content = renderCyberCardsGrid($transfer_benefits);
include '../utilities/grid-section.php';
?>

<?php
// Transfer Process Steps
include '../utilities/tabs.php';
$transfer_steps = [
    [
        'id' => 'step-1',
        'title' => '1. PREPARATION',
        'content' => '<h4 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">PREPARE FOR TRANSFER</h4>
                     <ul style="color: rgba(255,255,255,0.8); line-height: 1.6;">
                         <li>Unlock domains at current registrar</li>
                         <li>Obtain authorization codes (auth codes)</li>
                         <li>Ensure contact information is up to date</li>
                         <li>Disable privacy protection if enabled</li>
                         <li>Verify domains are not within 60 days of registration</li>
                     </ul>'
    ],
    [
        'id' => 'step-2',
        'title' => '2. SUBMISSION',
        'content' => '<h4 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">SUBMIT TRANSFER REQUEST</h4>
                     <ul style="color: rgba(255,255,255,0.8); line-height: 1.6;">
                         <li>Add domains to transfer list above</li>
                         <li>Provide auth codes for each domain</li>
                         <li>Review bulk discount calculations</li>
                         <li>Complete payment process</li>
                         <li>Submit bulk transfer request</li>
                     </ul>'
    ],
    [
        'id' => 'step-3',
        'title' => '3. PROCESSING',
        'content' => '<h4 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">AUTOMATED PROCESSING</h4>
                     <ul style="color: rgba(255,255,255,0.8); line-height: 1.6;">
                         <li>Automated transfer initiation for all domains</li>
                         <li>Email confirmations sent to domain owners</li>
                         <li>Real-time status tracking in your dashboard</li>
                         <li>Automatic retry for any failed attempts</li>
                         <li>Progress notifications throughout process</li>
                     </ul>'
    ],
    [
        'id' => 'step-4',
        'title' => '4. COMPLETION',
        'content' => '<h4 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">TRANSFER COMPLETION</h4>
                     <ul style="color: rgba(255,255,255,0.8); line-height: 1.6;">
                         <li>Domains appear in your HiveNest account</li>
                         <li>Full DNS management capabilities activated</li>
                         <li>Free 1-year extension for each transferred domain</li>
                         <li>Access to all HiveNest domain features</li>
                         <li>24/7 support for your domain portfolio</li>
                     </ul>'
    ]
];

$tabs_title = 'BULK TRANSFER PROCESS';
$tabs_subtitle = 'Step-by-step guide to mass domain migration';
$tabs_content = renderTabsSection($transfer_steps);
$tabs_background = 'background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);';
include '../utilities/grid-section.php';
?>

<?php
// CTA Section
$cta_title = 'READY TO MIGRATE YOUR DOMAIN PORTFOLIO?';
$cta_subtitle = 'Start your bulk domain transfer today and consolidate your digital assets in our secure neural network.';
$cta_buttons = '<a href="/contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">GET TRANSFER HELP</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
