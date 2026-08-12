<?php
include_once '../utilities/dynamic_pricing.php';
$special_ops_rows = [];
$special_ops_db = getPricingDBConnection();
if ($special_ops_db) {
    try {
        // Include the original Special Ops product and every additional active
        // product assigned to this page. Hidden products/packages are excluded.
        $special_ops_stmt = $special_ops_db->prepare("
            SELECT pp.*, p.name AS product_name, p.slug AS product_slug
            FROM products p
            INNER JOIN product_pricing pp ON pp.product_id = p.id
            WHERE p.is_active = 1
              AND pp.is_active = 1
              AND (
                    p.slug = :special_slug
                    OR TRIM(LEADING '/' FROM REPLACE(p.page_url, '\\\\', '/')) = :offers_page
              )
            ORDER BY p.sort_order ASC, p.id ASC, pp.sort_order ASC, pp.id ASC
        ");
        $special_ops_stmt->execute([
            'special_slug' => 'special-ops',
            'offers_page' => 'marketing/offers.php',
        ]);
        $special_ops_rows = $special_ops_stmt->fetchAll();
        foreach ($special_ops_rows as &$special_ops_row) {
            $special_ops_row['features'] = is_array($special_ops_row['features'])
                ? $special_ops_row['features']
                : (json_decode((string)$special_ops_row['features'], true) ?: []);
            $special_ops_row['tier_slug'] = $special_ops_row['product_slug'] . '--' . $special_ops_row['tier_slug'];
        }
        unset($special_ops_row);
    } catch (Throwable $e) {
        error_log('offers pricing lookup failed: ' . $e->getMessage());
    }
}

// Page variables
$current_page = 'offers';
$page_title = 'Special Ops - Exclusive Offers | HiveNest Matrix';
$page_description = 'Special Ops - Exclusive offers and promotions on quantum hosting, neural domains, and digital services.';
$page_keywords = 'special offers, hosting deals, domain promotions, cyberpunk hosting discounts, exclusive deals';

// Page-specific JavaScript
$page_scripts = "
function showSpecialOpsMessage(message, isError) {
    const messageBox = document.getElementById('special-ops-domain-message');
    if (!messageBox) return;
    messageBox.textContent = message || '';
    messageBox.style.display = message ? 'block' : 'none';
    messageBox.style.color = isError ? 'var(--cyber-neon-pink)' : 'var(--cyber-neon-green)';
    messageBox.style.borderColor = isError ? 'rgba(255, 0, 255, 0.7)' : 'rgba(0, 255, 0, 0.55)';
}

function addOfferToCart(offerId, offerName, price, type, requiresDomain, bundleItems) {
    if (window.addToCart) {
        bundleItems = Array.isArray(bundleItems) ? bundleItems : [];
        const domainInput = document.getElementById('special-ops-domain');
        const primaryDomain = domainInput ? domainInput.value.trim().toLowerCase() : '';
        if (requiresDomain && !primaryDomain) {
            showSpecialOpsMessage('Please enter the domain for this bundled package before adding it to cart.', true);
            if (domainInput) {
                domainInput.focus();
                domainInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return false;
        }
        if (primaryDomain && !/^[a-z0-9][a-z0-9-]*(\.[a-z0-9][a-z0-9-]*)+$/i.test(primaryDomain)) {
            showSpecialOpsMessage('Please enter a valid domain name, for example yourdomain.com.', true);
            if (domainInput) domainInput.focus();
            return false;
        }
        showSpecialOpsMessage('', false);
        const item = {
            id: offerId,
            name: offerName,
            price: price,
            type: type || 'offer'
        };
        if (bundleItems.length > 0) {
            item.bundle_items = bundleItems;
            item.product_config = {
                ...(item.product_config || {}),
                sku: offerId,
                bundle_items: bundleItems
            };
        }
        if (primaryDomain) {
            item.domain = primaryDomain;
            item.domain_name = primaryDomain;
            item.primary_domain = primaryDomain;
            item.product_config = {
                ...(item.product_config || {}),
                sku: offerId,
                domain: primaryDomain,
                domain_name: primaryDomain,
                primary_domain: primaryDomain,
                bundle_items: bundleItems.length > 0 ? bundleItems : undefined
            };
        }
        window.addToCart({
            ...item
        });
        showSpecialOpsMessage('Added to cart. You can keep shopping or open the cart when ready.', false);
        return true;
    } else {
        console.error('Cart system not loaded');
        console.warn('Cart system not ready. Please refresh the page and try again.');
        return false;
    }
}
";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include '../utilities/head.php'; ?>
</head>
<body>
<?php include '../utilities/nav.php'; ?>

<?php include '../utilities/mobile-menu.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        <img src="../assets/images/heroes/hero-pricing-packages.jpg" alt="Special Operations" class="hero-background">
        
        <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title">
                    SPECIAL<br>
                    <span class="cyber-text">OPS</span><br>
                    EXCLUSIVE OFFERS
                </h1>
                <p class="hero-subtitle">
                    Classified deals and exclusive promotions on quantum hosting, neural domains, 
                    and digital services. Access denied to ordinary mortals.
                </p>
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                    <a href="#offers" class="btn btn-primary">VIEW OPERATIONS</a>
                    <a href="#limited" class="btn btn-secondary">LIMITED TIME</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Special Offers -->
    <section id="offers" class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>CLASSIFIED OPERATIONS</h2>
                <p class="hero-subtitle">Exclusive deals for digital agents and neural architects</p>
            </div>
            
            <div class="services-grid">
                <div class="cyber-card">
                    <div style="background: linear-gradient(45deg, var(--cyber-neon-red), var(--cyber-neon-pink)); padding: 0.5rem 1rem; border-radius: 20px; display: inline-block; margin-bottom: 1rem;">
                        <span style="color: white; font-weight: bold; font-size: 0.9rem;">OPERATION: MATRIX</span>
                    </div>
                    <h3 class="service-title">50% OFF HOSTING</h3>
                    <p class="service-description">
                        First 6 months at 50% off on all quantum hosting plans. 
                        Perfect for new digital realms entering the matrix.
                    </p>
                    <div style="margin: 1rem 0; padding: 1rem; background: rgba(255, 255, 255, 0.1); border-radius: 8px;">
                        <div style="color: var(--cyber-neon-green); font-weight: bold;">Code: MATRIX50</div>
                        <div style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Valid until: March 31, 2025</div>
                    </div>
                    <button onclick="addOfferToCart('offer-matrix50', '50% OFF HOSTING - MATRIX50', 0, 'hosting')" class="btn btn-primary">ACTIVATE CODE</button>
                </div>
                
                <div class="cyber-card">
                    <div style="background: linear-gradient(45deg, var(--cyber-neon-cyan), var(--cyber-neon-blue)); padding: 0.5rem 1rem; border-radius: 20px; display: inline-block; margin-bottom: 1rem;">
                        <span style="color: white; font-weight: bold; font-size: 0.9rem;">OPERATION: NEURAL</span>
                    </div>
                    <h3 class="service-title">FREE DOMAIN + SSL</h3>
                    <p class="service-description">
                        Free .com domain and SSL certificate with any annual hosting plan. 
                        Establish your neural presence across all dimensions.
                    </p>
                    <div style="margin: 1rem 0; padding: 1rem; background: rgba(255, 255, 255, 0.1); border-radius: 8px;">
                        <div style="color: var(--cyber-neon-green); font-weight: bold;">Code: NEURAL2025</div>
                        <div style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Valid until: April 15, 2025</div>
                    </div>
                    <button onclick="addOfferToCart('offer-neural2025', 'FREE DOMAIN + SSL - NEURAL2025', 0, 'domain')" class="btn btn-primary">CLAIM DOMAIN</button>
                </div>
                
                <div class="cyber-card">
                    <div style="background: linear-gradient(45deg, var(--cyber-neon-orange), var(--cyber-neon-yellow)); padding: 0.5rem 1rem; border-radius: 20px; display: inline-block; margin-bottom: 1rem;">
                        <span style="color: white; font-weight: bold; font-size: 0.9rem;">OPERATION: CYBER</span>
                    </div>
                    <h3 class="service-title">SECURITY BUNDLE</h3>
                    <p class="service-description">
                        Complete security package: SSL, SiteLock, CodeGuard, and Acronis backup 
                        for 60% off. Protect your digital empire.
                    </p>
                    <div style="margin: 1rem 0; padding: 1rem; background: rgba(255, 255, 255, 0.1); border-radius: 8px;">
                        <div style="color: var(--cyber-neon-green); font-weight: bold;">Code: CYBER60</div>
                        <div style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Valid until: May 1, 2025</div>
                    </div>
                    <button onclick="addOfferToCart('offer-cyber60', 'SECURITY BUNDLE 60% OFF - CYBER60', 0, 'security')" class="btn btn-primary">SECURE EMPIRE</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Limited Time Offers -->
    <section id="limited" class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
        <div class="container">
            <div class="text-center mb-8">
                <h2>FLASH OPERATIONS</h2>
                <p class="hero-subtitle">Ultra-classified deals with limited neural access</p>
            </div>
            <div class="cyber-card" style="max-width: 900px; margin: 0 auto 2rem; padding: 1.5rem; border-color: var(--cyber-neon-cyan);">
                <label for="special-ops-domain" style="display:block; color: var(--cyber-neon-cyan); font-weight: 700; margin-bottom: .5rem;">Domain for bundled services</label>
                <input
                    id="special-ops-domain"
                    type="text"
                    placeholder="yourdomain.com - optional, but needed for bundles with hosting, SSL, email or domain services"
                    style="width:100%; padding: 14px 16px; border:1px solid var(--cyber-neon-cyan); border-radius:8px; background:rgba(0,0,0,.75); color:white;"
                >
                <p style="margin:.75rem 0 0; color:rgba(255,255,255,.68); font-size:.92rem;">
                    If this Special Ops package includes hosting, SSL, email or domain registration, enter the domain here so provisioning can start automatically.
                </p>
                <div
                    id="special-ops-domain-message"
                    style="display:none; margin-top: 1rem; padding: .85rem 1rem; border: 1px solid rgba(255, 0, 255, 0.65); border-radius: 8px; background: rgba(255, 0, 255, 0.1); text-align: center;"
                ></div>
            </div>
            
            <div class="pricing-grid">
                <?php if (empty($special_ops_rows)): ?>
                <div class="pricing-card featured">
                    <div style="background: linear-gradient(45deg, var(--cyber-neon-red), var(--cyber-neon-pink)); padding: 0.5rem 1rem; border-radius: 20px; display: inline-block; margin-bottom: 1rem;">
                        <span style="color: white; font-weight: bold; font-size: 0.9rem;">FLASH OPERATION</span>
                    </div>
                    <div class="pricing-plan">NEURAL STARTER</div>
                    <div class="pricing-amount">
                        <span style="text-decoration: line-through; opacity: 0.6;">$15</span>
                        <span style="color: var(--cyber-neon-green);">$5</span>
                        <span style="font-size: 1rem;">/mo</span>
                    </div>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ 3 Websites</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ 25GB SSD Storage</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Unlimited Bandwidth</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Free Domain (.com)</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Free SSL Certificate</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ 10 Email Accounts</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Daily Backups</li>
                    </ul>
                    <div style="background: rgba(0, 255, 0, 0.2); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <div style="color: var(--cyber-neon-green); font-weight: bold; text-align: center;">
                            <i class="fas fa-tags"></i> Savings
                        </div>
                    </div>
                    <button onclick="addOfferToCart('flash-neural-starter', 'NEURAL STARTER (Flash Sale)', 5, 'hosting')" class="btn btn-secondary" style="width: 100%;">
                        ADD TO CART
                    </button>
                </div>

                <div class="pricing-card">
                    <div style="background: linear-gradient(45deg, var(--cyber-neon-cyan), var(--cyber-neon-blue)); padding: 0.5rem 1rem; border-radius: 20px; display: inline-block; margin-bottom: 1rem;">
                        <span style="color: white; font-weight: bold; font-size: 0.9rem;">WEEKEND SPECIAL</span>
                    </div>
                    <div class="pricing-plan">DESIGN PACKAGE</div>
                    <div class="pricing-amount">
                        <span style="text-decoration: line-through; opacity: 0.6;">$299</span>
                        <span style="color: var(--cyber-neon-green);">$99</span>
                        <span style="font-size: 1rem;">/once</span>
                    </div>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Custom Logo Design</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Business Card Design</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Letterhead Design</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Email Signature</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Website Banner</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ 3 Revisions</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ 7-Day Delivery</li>
                    </ul>
                    <div style="background: rgba(0, 255, 0, 0.2); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <div style="color: var(--cyber-neon-green); font-weight: bold; text-align: center;">
                            <i class="fas fa-tags"></i> Savings
                        </div>
                    </div>
                    <button onclick="addOfferToCart('flash-design-package', 'DESIGN PACKAGE (Special)', 99, 'design')" class="btn btn-primary" style="width: 100%;">
                        ADD TO CART
                    </button>
                </div>

                <div class="pricing-card">
                    <div style="background: linear-gradient(45deg, var(--cyber-neon-purple), var(--cyber-neon-pink)); padding: 0.5rem 1rem; border-radius: 20px; display: inline-block; margin-bottom: 1rem;">
                        <span style="color: white; font-weight: bold; font-size: 0.9rem;">MEGA DEAL</span>
                    </div>
                    <div class="pricing-plan">NEURAL BUNDLE</div>
                    <div class="pricing-amount">
                        <span style="text-decoration: line-through; opacity: 0.6;">$89</span>
                        <span style="color: var(--cyber-neon-green);">$29</span>
                        <span style="font-size: 1rem;">/mo</span>
                    </div>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ WordPress Hosting</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Domain Registration</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ SSL Certificate</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Google Workspace</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ SiteLock Security</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Xcitium Backup</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Priority Support</li>
                    </ul>
                    <div style="background: rgba(0, 255, 0, 0.2); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <div style="color: var(--cyber-neon-green); font-weight: bold; text-align: center;">
                            <i class="fas fa-tags"></i> Savings
                        </div>
                    </div>
                    <button onclick="addOfferToCart('flash-neural-bundle', 'NEURAL BUNDLE (Mega Deal)', 29, 'bundle', true, [])" class="btn btn-primary" style="width: 100%;">
                        ADD TO CART
                    </button>
                </div>
                <?php endif; ?>

                <?php
                if (!empty($special_ops_rows)) {
                    $colors = ['var(--cyber-neon-cyan)','var(--cyber-neon-green)','var(--cyber-neon-yellow)','var(--cyber-neon-pink)','var(--cyber-neon-purple)'];
                    $additional_flash_offers = [];
                    foreach ($special_ops_rows as $index => $row) {
                        $cycle = $row['billing_cycle'] ?? 'monthly';
                        $period = $cycle === 'one_time' ? '/once' : ($cycle === 'per_user_monthly' ? '/user/mo' : '/mo');
                        $bundle_items = json_decode((string)($row['bundle_items'] ?? ''), true);
                        $requires_domain = false;
                        if (is_array($bundle_items)) {
                            foreach ($bundle_items as $bundle_item) {
                                if (!is_array($bundle_item)) continue;
                                $job_type = strtolower((string)($bundle_item['job_type'] ?? ''));
                                $sku_text = strtolower((string)($bundle_item['sku'] ?? '') . ' ' . (string)($bundle_item['name'] ?? ''));
                                if (!empty($bundle_item['requires_domain'])
                                    || in_array($job_type, ['domain_registration','hosting_setup','email_setup','ssl_setup','backup_setup','security_setup'], true)
                                    || strpos($sku_text, 'domain') !== false
                                    || strpos($sku_text, 'hosting') !== false
                                    || strpos($sku_text, 'ssl') !== false
                                ) {
                                    $requires_domain = true;
                                    break;
                                }
                            }
                        }
                        $additional_flash_offers[] = [
                            'id' => $row['tier_slug'],
                            'badge' => 'FLASH OPERATION',
                            'name' => $row['tier_name'],
                            'old' => '$' . number_format(((float)$row['price']) * 2, 0),
                            'price' => (float)$row['price'],
                            'period' => $period,
                            'type' => 'bundle',
                            'requires_domain' => $requires_domain,
                            'bundle_items' => is_array($bundle_items) ? $bundle_items : [],
                            'color' => $colors[$index % count($colors)],
                            'features' => !empty($row['features']) ? $row['features'] : ['Limited-time package','Special offer pricing'],
                        ];
                    }
                } else {
                $additional_flash_offers = [
                    ['id'=>'flash-wordpress-launch','badge'=>'LAUNCH DEAL','name'=>'WORDPRESS LAUNCH','old'=>'$29','price'=>12,'period'=>'/mo','type'=>'hosting','requires_domain'=>true,'color'=>'var(--cyber-neon-cyan)','features'=>['Managed WordPress Hosting','25GB SSD Storage','Free SSL Certificate','Daily Backups']],
                    ['id'=>'flash-business-mail','badge'=>'COMM SPECIAL','name'=>'BUSINESS MAIL','old'=>'$18','price'=>8,'period'=>'/user/mo','type'=>'email','requires_domain'=>true,'color'=>'var(--cyber-neon-green)','features'=>['Custom Domain Email','25GB Mailbox','Spam Protection','Mobile & Desktop Sync']],
                    ['id'=>'flash-ssl-shield','badge'=>'SECURITY DROP','name'=>'SSL SHIELD','old'=>'$9','price'=>3,'period'=>'/mo','type'=>'security','requires_domain'=>true,'color'=>'var(--cyber-neon-yellow)','features'=>['Domain Validation','Strong Encryption','Browser Compatibility','Trust Indicator']],
                    ['id'=>'flash-xcitium-backup','badge'=>'DATA RESCUE','name'=>'XCITIUM BACKUP','old'=>'$15','price'=>6,'period'=>'/mo','type'=>'backup','requires_domain'=>true,'color'=>'var(--cyber-neon-pink)','features'=>['Cloud Backup','Scheduled Protection','Secure Retention','Fast Recovery']],
                    ['id'=>'flash-sitelock-defense','badge'=>'THREAT LOCK','name'=>'SITELOCK DEFENSE','old'=>'$12','price'=>4,'period'=>'/mo','type'=>'security','requires_domain'=>true,'color'=>'var(--cyber-neon-purple)','features'=>['Daily Malware Scan','Vulnerability Checks','Security Badge','Threat Alerts']],
                    ['id'=>'flash-seo-booster','badge'=>'RANK BOOST','name'=>'SEO BOOSTER','old'=>'$299','price'=>149,'period'=>'/mo','type'=>'marketing','requires_domain'=>false,'color'=>'var(--cyber-neon-cyan)','features'=>['Keyword Research','On-page Optimization','Technical SEO Audit','Monthly Report']],
                    ['id'=>'flash-social-launch','badge'=>'SOCIAL BURST','name'=>'SOCIAL LAUNCH','old'=>'$249','price'=>129,'period'=>'/mo','type'=>'marketing','requires_domain'=>false,'color'=>'var(--cyber-neon-green)','features'=>['Two Social Platforms','Content Calendar','Eight Monthly Posts','Performance Report']],
                    ['id'=>'flash-brand-identity','badge'=>'CREATIVE DROP','name'=>'BRAND IDENTITY','old'=>'$499','price'=>249,'period'=>'/once','type'=>'design','requires_domain'=>false,'color'=>'var(--cyber-neon-yellow)','features'=>['Custom Logo Design','Business Card Design','Email Signature','Brand Colour Palette']],
                    ['id'=>'flash-cloud-growth','badge'=>'SCALE UP','name'=>'CLOUD GROWTH','old'=>'$59','price'=>25,'period'=>'/mo','type'=>'hosting','requires_domain'=>true,'color'=>'var(--cyber-neon-pink)','features'=>['Cloud Hosting','50GB SSD Storage','Unlimited Bandwidth','Priority Support']],
                ];
                }

                foreach ($additional_flash_offers as $offer):
                ?>
                <div class="pricing-card">
                    <div style="background: linear-gradient(45deg, <?php echo $offer['color']; ?>, var(--cyber-neon-blue)); padding: 0.5rem 1rem; border-radius: 20px; display: inline-block; margin-bottom: 1rem;">
                        <span style="color: white; font-weight: bold; font-size: 0.9rem;"><?php echo htmlspecialchars($offer['badge']); ?></span>
                    </div>
                    <div class="pricing-plan"><?php echo htmlspecialchars($offer['name']); ?></div>
                    <div class="pricing-amount">
                        <span style="text-decoration: line-through; opacity: 0.6;"><?php echo htmlspecialchars($offer['old']); ?></span>
                        <span style="color: var(--cyber-neon-green);">$<?php echo number_format($offer['price'], 0); ?></span>
                        <span style="font-size: 1rem;"><?php echo htmlspecialchars($offer['period']); ?></span>
                    </div>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <?php foreach ($offer['features'] as $feature): ?>
                            <li style="margin: 0.5rem 0; color: <?php echo $offer['color']; ?>;">◉ <?php echo htmlspecialchars($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <div style="background: rgba(0, 255, 0, 0.2); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <div style="color: var(--cyber-neon-green); font-weight: bold; text-align: center;"><i class="fas fa-tags"></i> Flash Savings</div>
                    </div>
                    <button
                        onclick='addOfferToCart(<?php echo json_encode($offer["id"]); ?>, <?php echo json_encode($offer["name"] . " (Flash Offer)"); ?>, <?php echo json_encode($offer["price"]); ?>, <?php echo json_encode($offer["type"]); ?>, <?php echo !empty($offer["requires_domain"]) ? "true" : "false"; ?>, <?php echo json_encode($offer["bundle_items"] ?? []); ?>)'
                        class="btn btn-primary"
                        style="width: 100%;">
                        ADD TO CART
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- How to Redeem -->
    <section class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>OPERATION PROTOCOLS</h2>
                <p class="hero-subtitle">How to activate your classified codes</p>
            </div>
            
            <div class="cyber-card" style="max-width: 800px; margin: 0 auto;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                    <div style="text-align: center;">
                        <div style="width: 60px; height: 60px; background: var(--cyber-neon-cyan); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                            <span style="color: var(--cyber-black); font-weight: bold; font-size: 1.5rem;">1</span>
                        </div>
                        <h4 style="color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;">SELECT SERVICE</h4>
                        <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Choose your desired hosting plan or service</p>
                    </div>
                    
                    <div style="text-align: center;">
                        <div style="width: 60px; height: 60px; background: var(--cyber-neon-green); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                            <span style="color: var(--cyber-black); font-weight: bold; font-size: 1.5rem;">2</span>
                        </div>
                        <h4 style="color: var(--cyber-neon-green); margin-bottom: 0.5rem;">ENTER CODE</h4>
                        <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Apply the promo code during checkout</p>
                    </div>
                    
                    <div style="text-align: center;">
                        <div style="width: 60px; height: 60px; background: var(--cyber-neon-pink); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                            <span style="color: var(--cyber-black); font-weight: bold; font-size: 1.5rem;">3</span>
                        </div>
                        <h4 style="color: var(--cyber-neon-pink); margin-bottom: 0.5rem;">ACTIVATE</h4>
                        <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Complete payment and activate your service</p>
                    </div>
                </div>
                
                <div style="text-align: center; padding: 1.5rem; background: rgba(255, 255, 255, 0.1); border-radius: 8px;">
                    <h4 style="color: var(--cyber-neon-yellow); margin-bottom: 1rem;">NEED ASSISTANCE?</h4>
                    <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 1rem;">
                        Our cyber guardians are available 24/7 to help you with code activation and service setup.
                    </p>
                    <a href="../contact.php" class="btn btn-secondary">
                        CONTACT SUPPORT
                    </a>
                </div>
            </div>
        </div>
    </section>

<?php $GLOBALS['hivenest_assigned_products_rendered'] = true; include '../utilities/footer.php'; ?>

<?php include '../utilities/scripts.php'; ?>
</body>
</html>
