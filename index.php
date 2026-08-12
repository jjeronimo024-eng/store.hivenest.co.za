<?php
// Page variables
$current_page = 'home';
$page_title = 'HiveNest - Digital Revolution Starts Here';
$page_description = 'HiveNest - The Future of Digital Services. Cyberpunk-inspired hosting, domains, and digital solutions that break all the rules.';
$page_keywords = 'futuristic hosting, cyberpunk web services, next-gen digital solutions';

// Load database helper for dynamic pricing
require_once 'utilities/db_helper.php';

// Get hosting products from database
$hosting_products = $db_helper->getProductsByType('hosting');

// Map products to pricing tiers
$initiate_plan = null;
$warrior_plan = null;
$master_plan = null;

foreach ($hosting_products as $product) {
    if (strpos(strtolower($product['name']), 'initiate') !== false) {
        $initiate_plan = $product;
    } elseif (strpos(strtolower($product['name']), 'warrior') !== false) {
        $warrior_plan = $product;
    } elseif (strpos(strtolower($product['name']), 'master') !== false) {
        $master_plan = $product;
    }
}

// Fallback if products not found
if (!$initiate_plan) {
    $initiate_plan = ['base_price' => 5.99, 'billing_cycle' => 'monthly', 'features' => []];
}
if (!$warrior_plan) {
    $warrior_plan = ['base_price' => 15.99, 'billing_cycle' => 'monthly', 'features' => []];
}
if (!$master_plan) {
    $master_plan = ['base_price' => 29.99, 'billing_cycle' => 'monthly', 'features' => []];
}

$homepage_plans = [
    [
        'id' => 'initiate', 'name' => 'INITIATE', 'price' => (float)$initiate_plan['base_price'],
        'period' => '/mo', 'featured' => false, 'type' => 'hosting',
        'features' => !empty($initiate_plan['features']) ? $initiate_plan['features'] : ['1 Website','10GB SSD Storage','100GB Data Transfer','Free SSL Certificate','5 Email Accounts'],
    ],
    [
        'id' => 'warrior', 'name' => 'WARRIOR', 'price' => (float)$warrior_plan['base_price'],
        'period' => '/mo', 'featured' => true, 'type' => 'hosting',
        'features' => !empty($warrior_plan['features']) ? $warrior_plan['features'] : ['10 Websites','100GB SSD Storage','Unlimited Data Transfer','Free Domain','50 Email Accounts'],
    ],
    [
        'id' => 'master', 'name' => 'DIGITAL MASTER', 'price' => (float)$master_plan['base_price'],
        'period' => '/mo', 'featured' => false, 'type' => 'hosting',
        'features' => !empty($master_plan['features']) ? $master_plan['features'] : ['Unlimited Websites','500GB SSD Storage','Wildcard SSL','Unlimited Email','Daily Backups'],
    ],
];

// Append every visible package assigned to /index.php in Product Management.
$homepage_periods = ['monthly'=>'/mo','quarterly'=>'/3mo','semi_annually'=>'/6mo','annually'=>'/yr','one_time'=>'/once','per_user_monthly'=>'/user/mo'];
try {
    $home_db = hivenest_db();
    if ($home_db) {
        $core_stmt = $home_db->query("
            SELECT pp.tier_name, pp.tier_slug, pp.price, pp.billing_cycle,
                   pp.features, pp.is_featured
            FROM product_pricing pp
            INNER JOIN products p ON p.id = pp.product_id
            WHERE p.slug = 'cyber-initiate-hosting'
              AND p.is_active = 1 AND pp.is_active = 1
            ORDER BY pp.sort_order, pp.id
        ");
        $core_rows = $core_stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($core_rows)) {
            $homepage_plans = [];
            foreach ($core_rows as $row) {
                $features = json_decode((string)$row['features'], true);
                $homepage_plans[] = [
                    'id' => $row['tier_slug'], 'name' => $row['tier_name'],
                    'price' => (float)$row['price'],
                    'period' => $homepage_periods[$row['billing_cycle']] ?? '/mo',
                    'featured' => (bool)$row['is_featured'], 'type' => 'hosting',
                    'features' => is_array($features) ? $features : [],
                ];
            }
        }

        $stmt = $home_db->query("
            SELECT p.slug AS product_slug, p.name AS product_name, p.product_type, p.base_price,
                   p.billing_cycle AS product_cycle, p.features AS product_features,
                   pp.tier_name, pp.tier_slug, pp.price AS tier_price,
                   pp.billing_cycle AS tier_cycle, pp.features AS tier_features, pp.is_featured
            FROM products p
            LEFT JOIN product_pricing pp ON pp.product_id = p.id AND pp.is_active = 1
            WHERE p.is_active = 1
              AND p.page_url IN ('/index.php', 'index.php', '/')
              AND p.slug NOT IN ('cyber-initiate-hosting','digital-warrior-hosting','quantum-master-hosting')
              AND (
                  pp.id IS NOT NULL
                  OR NOT EXISTS (SELECT 1 FROM product_pricing all_pp WHERE all_pp.product_id = p.id)
              )
            ORDER BY p.sort_order, p.id, pp.sort_order, pp.id
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cycle = $row['tier_cycle'] ?: $row['product_cycle'];
            $features = json_decode((string)($row['tier_features'] ?: $row['product_features']), true);
            $homepage_plans[] = [
                'id' => $row['product_slug'] . '-' . ($row['tier_slug'] ?: 'base'),
                'name' => $row['tier_name'] ?: $row['product_name'],
                'price' => (float)($row['tier_price'] ?? $row['base_price']),
                'period' => $homepage_periods[$cycle] ?? '/mo',
                'featured' => (bool)($row['is_featured'] ?? false),
                'type' => $row['product_type'] ?: 'service',
                'features' => is_array($features) ? $features : [],
            ];
        }
    }
} catch (Throwable $e) {
    error_log('Homepage package loading failed: ' . $e->getMessage());
}

// Page-specific JavaScript
$page_scripts = "
function addHostingPlanToCart(planId, planName, price, productType) {
    productType = productType || 'hosting';
    if (window.addToCart) {
        window.addToCart({
            id: 'hosting-plan-' + planId,
            name: productType === 'hosting' ? planName + ' Hosting Plan' : planName,
            price: price,
            type: productType
        });
    } else {
        console.error('Cart system not loaded');
        console.warn('Cart system not ready. Please refresh the page and try again.');
    }
}
";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'utilities/head.php'; ?>
</head>
<body>
<?php include 'utilities/nav.php'; ?>

<?php include 'utilities/mobile-menu.php'; ?>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <img src="assets/images/heroes/hero-cyberpunk-main.jpg" alt="Cyberpunk Future" class="hero-background">
        
        <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title">
                    DIGITAL<br>
                    <span class="cyber-text">REVOLUTION</span><br>
                    STARTS HERE
                </h1>
                <p class="hero-subtitle">
                    Break free from ordinary hosting. Enter the future of digital services 
                    where cutting-edge technology meets unlimited possibilities.
                </p>
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                    <a href="#services" class="btn btn-primary">EXPLORE MATRIX</a>
                    <a href="#pricing" class="btn btn-secondary">VIEW SYSTEMS</a>
                </div>
            </div>
            
            <div class="hero-visual">
                <div class="cyber-orb"></div>
            </div>
        </div>
    </section>

    <!-- Features Matrix -->
    <section class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>SYSTEM CAPABILITIES</h2>
                <p class="hero-subtitle">Advanced protocols for digital domination</p>
            </div>
            
            <div class="services-grid">
                <div class="cyber-card">
                    <i class="fas fa-rocket service-icon"></i>
                    <h3 class="service-title">QUANTUM SPEED</h3>
                    <p class="service-description">
                        99.99% uptime with quantum-grade SSD arrays. Your digital empire loads at light speed.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-shield-alt service-icon"></i>
                    <h3 class="service-title">FORTRESS SECURITY</h3>
                    <p class="service-description">
                        Military-grade encryption and AI-powered threat detection. Your data is unbreachable.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-brain service-icon"></i>
                    <h3 class="service-title">AI INTELLIGENCE</h3>
                    <p class="service-description">
                        Smart auto-scaling, predictive maintenance, and neural network optimization.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Matrix -->
    <section id="services" class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
        <div class="container">
            <div class="text-center mb-8">
                <h2>SERVICE PROTOCOLS</h2>
                <p class="hero-subtitle">Choose your digital weapons</p>
            </div>
            
            <div class="services-grid">
                <!-- Neural Domains -->
                <div class="service-card">
                    <i class="fas fa-globe service-icon"></i>
                    <h3 class="service-title">NEURAL DOMAINS</h3>
                    <p class="service-description">
                        Register your digital identity across all dimensions. 100+ TLD options with 
                        quantum-encrypted privacy protection and instant activation protocols.
                    </p>
                    <div style="margin-top: 2rem;">
                        <a href="main-services/domains.php" class="btn btn-primary">CLAIM TERRITORY</a>
                    </div>
                </div>

                <!-- Quantum Hosting -->
                <div class="service-card">
                    <i class="fas fa-server service-icon"></i>
                    <h3 class="service-title">QUANTUM HOSTING</h3>
                    <p class="service-description">
                        Next-generation hosting infrastructure powered by quantum processors. 
                        Linux/Windows platforms, unlimited bandwidth, SSD NVMe storage, and molecular-level security.
                    </p>
                    <div style="margin-top: 2rem;">
                        <a href="main-services/hosting.php" class="btn btn-primary">ACCESS PROTOCOL</a>
                    </div>
                </div>

                <!-- Dedicated Servers -->
                <div class="service-card">
                    <i class="fas fa-microchip service-icon"></i>
                    <h3 class="service-title">NEURAL SERVERS</h3>
                    <p class="service-description">
                        Dedicated server architecture with direct neural processing connections. 
                        Linux and Windows servers with complete root access and unlimited power.
                    </p>
                    <div style="margin-top: 2rem;">
                        <a href="main-services/servers.php" class="btn btn-primary">DEPLOY POWER</a>
                    </div>
                </div>

                <!-- Cloud Matrix -->
                <div class="service-card">
                    <i class="fas fa-cloud service-icon"></i>
                    <h3 class="service-title">CLOUD MATRIX</h3>
                    <p class="service-description">
                        Scalable cloud infrastructure that adapts to your digital evolution. 
                        Auto-scaling, load balancing, and parallel universe redundancy.
                    </p>
                    <div style="margin-top: 2rem;">
                        <a href="hosting/cloud-hosting.php" class="btn btn-primary">ENTER MATRIX</a>
                    </div>
                </div>

                <!-- Digital Shields -->
                <div class="service-card">
                    <i class="fas fa-shield-alt service-icon"></i>
                    <h3 class="service-title">CYBER ARSENAL</h3>
                    <p class="service-description">
                        Advanced security protocols including SSL certificates, CodeGuard backups, 
                        SiteLock protection, and Acronis cyber backup systems.
                    </p>
                    <div style="margin-top: 2rem;">
                        <a href="main-services/tools.php" class="btn btn-primary">ACTIVATE SHIELDS</a>
                    </div>
                </div>

                <!-- Communication Arrays -->
                <div class="service-card">
                    <i class="fas fa-envelope service-icon"></i>
                    <h3 class="service-title">COMM ARRAYS</h3>
                    <p class="service-description">
                        Quantum-encrypted communication systems with Google Workspace integration 
                        and enterprise-grade email hosting across multiple dimensions.
                    </p>
                    <div style="margin-top: 2rem;">
                        <a href="main-services/email.php" class="btn btn-primary">SECURE CHANNEL</a>
                    </div>
                </div>

                <!-- Neural Graphics -->
                <div class="service-card">
                    <i class="fas fa-palette service-icon"></i>
                    <h3 class="service-title">NEURAL GRAPHICS</h3>
                    <p class="service-description">
                        Professional branding services including logo design, signatures, letterheads, 
                        business cards, and drag & drop website builder.
                    </p>
                    <div style="margin-top: 2rem;">
                        <a href="branding/logo.php" class="btn btn-primary">CREATE IDENTITY</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Matrix -->
    <section id="pricing" class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>POWER LEVELS</h2>
                <p class="hero-subtitle">Select your digital evolution tier</p>
            </div>
            
            <div class="pricing-grid">
                <?php foreach ($homepage_plans as $plan_index => $plan): ?>
                <div class="pricing-card <?php echo !empty($plan['featured']) ? 'featured' : ''; ?>">
                    <div class="pricing-plan"><?php echo htmlspecialchars($plan['name']); ?></div>
                    <div class="pricing-amount">$<?php echo number_format((float)$plan['price'], 2); ?><span style="font-size: 1rem;"><?php echo htmlspecialchars($plan['period']); ?></span></div>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <?php foreach ($plan['features'] as $feature): ?>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ <?php echo htmlspecialchars($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button onclick='addHostingPlanToCart(<?php echo json_encode($plan["id"]); ?>, <?php echo json_encode($plan["name"]); ?>, <?php echo json_encode((float)$plan["price"]); ?>, <?php echo json_encode($plan["type"]); ?>)' class="btn <?php echo !empty($plan['featured']) ? 'btn-secondary' : 'btn-primary'; ?>" style="width:100%;cursor:pointer;">ADD TO CART</button>
                </div>
                <?php endforeach; ?>

                <?php if (false): // Legacy fixed cards retained temporarily for reference. ?>
                <!-- Initiate Level -->
                <div class="pricing-card">
                    <div class="pricing-plan">INITIATE</div>
                    <div class="pricing-amount">$<?php echo number_format($initiate_plan['base_price'], 0); ?><span style="font-size: 1rem;">/mo</span></div>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <?php if (!empty($initiate_plan['features']) && is_array($initiate_plan['features'])): ?>
                            <?php foreach ($initiate_plan['features'] as $feature): ?>
                                <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ <?php echo htmlspecialchars($feature); ?></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ 1 Digital Realm (Website)</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ 10GB Quantum Storage (SSD)</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ 100GB Data Transfer</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Free Neural Shield (SSL)</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Basic Encrypted Comms (5 Email)</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Linux/Windows Matrix</li>
                        <?php endif; ?>
                    </ul>
                    <button onclick="addHostingPlanToCart('initiate', 'INITIATE', <?php echo $initiate_plan['base_price']; ?>)" class="btn btn-primary" style="width: 100%; cursor: pointer;">ADD TO CART</button>
                </div>

                <!-- Warrior Level (Featured) -->
                <div class="pricing-card featured">
                    <div class="pricing-plan">WARRIOR</div>
                    <div class="pricing-amount">$<?php echo number_format($warrior_plan['base_price'], 0); ?><span style="font-size: 1rem;">/mo</span></div>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <?php if (!empty($warrior_plan['features']) && is_array($warrior_plan['features'])): ?>
                            <?php foreach ($warrior_plan['features'] as $feature): ?>
                                <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ <?php echo htmlspecialchars($feature); ?></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ 10 Digital Realms (Websites)</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ 100GB Quantum Storage (SSD)</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Unlimited Data Transfer</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Advanced Neural Shield (SSL)</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Free Domain Portal (.com/.co.za)</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Enhanced Comm Array (50 Email)</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Priority Neural Support</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ WordPress Integration</li>
                        <?php endif; ?>
                    </ul>
                    <button onclick="addHostingPlanToCart('warrior', 'WARRIOR', <?php echo $warrior_plan['base_price']; ?>)" class="btn btn-secondary" style="width: 100%; cursor: pointer;">ADD TO CART</button>
                </div>

                <!-- Master Level -->
                <div class="pricing-card">
                    <div class="pricing-plan">DIGITAL MASTER</div>
                    <div class="pricing-amount">$<?php echo number_format($master_plan['base_price'], 0); ?><span style="font-size: 1rem;">/mo</span></div>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <?php if (!empty($master_plan['features']) && is_array($master_plan['features'])): ?>
                            <?php foreach ($master_plan['features'] as $feature): ?>
                                <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ <?php echo htmlspecialchars($feature); ?></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Unlimited Realms (Websites)</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ 500GB Quantum Storage (SSD)</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Unlimited Everything</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Quantum Neural Shield (Wildcard SSL)</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Multi-Dimensional Portals (5 Domains)</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Complete Comm Arsenal (Unlimited Email)</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ 24/7 Cyber Guardian</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Reality Backup Systems (Daily)</li>
                            <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Dedicated Server Access</li>
                        <?php endif; ?>
                    </ul>
                    <button onclick="addHostingPlanToCart('master', 'DIGITAL MASTER', <?php echo $master_plan['base_price']; ?>)" class="btn btn-primary" style="width: 100%; cursor: pointer;">ADD TO CART</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="section">
        <div class="container text-center">
            <h2>READY TO TRANSCEND REALITY?</h2>
            <p class="hero-subtitle mb-8">
                Join the digital revolution. Break free from ordinary. Enter the HiveNest matrix.
            </p>
            <div style="display: flex; gap: 2rem; justify-content: center; flex-wrap: wrap;">
                <a href="contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">INITIALIZE SEQUENCE</a>
            </div>
        </div>
    </section>

<?php $GLOBALS['hivenest_assigned_products_rendered'] = true; include 'utilities/footer.php'; ?>

<?php include 'utilities/scripts.php'; ?>
</body>
</html>
