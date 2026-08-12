<?php
// Load database helper to fetch pricing
require_once '../utilities/db_helper.php';
$db = new DatabaseHelper();

// Fetch hosting products from database
$hosting_products = $db->getProductsByType('hosting');

// Create a mapping for easy access
$products_map = [];
foreach ($hosting_products as $product) {
    $products_map[$product['slug']] = $product;
}

// Page variables
$current_page = 'hosting';
$page_title = 'Hosting Plans & Pricing - Quantum Infrastructure | HiveNest';
$page_description = 'Compare our quantum hosting plans. From starter to enterprise hosting with SSD storage, free SSL, and 99.9% uptime. Find the perfect plan for your digital empire.';
$page_keywords = 'hosting plans, hosting pricing, quantum hosting, shared hosting, dedicated servers, cyberpunk hosting';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-about-team.jpg',
    'url' => 'https://hivenest.co.za/pricing/hosting-plans.php',
    'type' => 'pricing'
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Hosting Plans', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = "
function selectPlan(productSlug, productName, price) {
    if (window.shoppingCart) {
        window.shoppingCart.addItem({
            id: productSlug,
            name: productName,
            price: parseFloat(price),
            type: 'hosting'
        });
        
        // Show success message
        console.log(productName + ' added to cart!');
    } else {
        console.error('Shopping cart not initialized');
        console.warn('Cart system not ready. Please refresh the page and try again.');
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
        <img src="assets/images/heroes/hero-about-team.jpg" alt="Quantum Hosting Plans" class="hero-background">
        
        <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title">
                    QUANTUM<br>
                    <span class="cyber-text">HOSTING</span><br>
                    POWER LEVELS
                </h1>
                <p class="hero-subtitle">
                    Choose the perfect hosting plan for your digital empire. All plans include neural shields, 
                    quantum backups, and 24/7 cyber guardian support.
                </p>
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                    <a href="#plans" class="btn btn-primary">VIEW POWER LEVELS</a>
                    <a href="#features" class="btn btn-secondary">COMPARE PROTOCOLS</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Hosting Plans -->
    <section id="plans" class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>POWER LEVELS</h2>
                <p class="hero-subtitle">Select your digital evolution tier</p>
            </div>
            
            <div class="pricing-grid">
                <?php
                // Display hosting products from database
                $colors = ['cyan', 'green', 'orange', 'pink'];
                $button_classes = ['btn-primary', 'btn-secondary', 'btn-primary', 'btn-primary'];
                $button_texts = ['BEGIN EVOLUTION', 'ASCEND TO WARRIOR', 'PROFESSIONAL GRADE', 'ACHIEVE MASTERY'];
                $plan_index = 0;
                
                foreach ($hosting_products as $product): 
                    $is_featured = $product['is_featured'] == 1 && $plan_index == 1;
                    $color = $colors[$plan_index % 4];
                    $button_class = $button_classes[$plan_index % 4];
                    $button_text = $button_texts[$plan_index % 4];
                    $features = is_array($product['features']) ? $product['features'] : json_decode($product['features'], true);
                    if (!$features) $features = [];
                    
                    // Format billing cycle
                    $billing_display = $product['billing_cycle'] == 'monthly' ? '/mo' : 
                                      ($product['billing_cycle'] == 'annually' ? '/yr' : '/' . $product['billing_cycle']);
                ?>
                <div class="pricing-card<?php echo $is_featured ? ' featured' : ''; ?>">
                    <div class="pricing-plan"><?php echo strtoupper($product['name']); ?></div>
                    <div class="pricing-amount">$<?php echo number_format($product['base_price'], 0); ?><span style="font-size: 1rem;"><?php echo $billing_display; ?></span></div>
                    <?php if ($product['setup_fee'] > 0): ?>
                    <div style="text-align: center; color: var(--cyber-neon-<?php echo $color; ?>); margin-top: -10px; font-size: 0.9rem;">
                        Setup: $<?php echo number_format($product['setup_fee'], 2); ?>
                    </div>
                    <?php endif; ?>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <?php foreach ($features as $feature): ?>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-<?php echo $color; ?>);">◉ <?php echo htmlspecialchars($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button onclick="selectPlan('<?php echo $product['slug']; ?>', '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['base_price']; ?>)" 
                            class="btn <?php echo $button_class; ?>" style="width: 100%;">
                        <?php echo $button_text; ?>
                    </button>
                </div>
                <?php 
                    $plan_index++; 
                endforeach; 
                
                // If no products found, show fallback message
                if (empty($hosting_products)):
                ?>
                <div class="cyber-card" style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
                    <p style="color: rgba(255,255,255,0.8);">No hosting plans available at this time. Please check back later or contact support.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Feature Comparison -->
    <section id="features" class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
        <div class="container">
            <div class="text-center mb-8">
                <h2>PROTOCOL COMPARISON</h2>
                <p class="hero-subtitle">See exactly what's included in each power level</p>
            </div>
            
            <div class="cyber-card">
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: rgba(255, 255, 255, 0.1);">
                                <th style="padding: 1rem; text-align: left; color: var(--cyber-neon-cyan); border-bottom: 1px solid rgba(255, 255, 255, 0.2);">Features</th>
                                <th style="padding: 1rem; text-align: center; color: var(--cyber-neon-cyan); border-bottom: 1px solid rgba(255, 255, 255, 0.2);">Initiate</th>
                                <th style="padding: 1rem; text-align: center; color: var(--cyber-neon-green); border-bottom: 1px solid rgba(255, 255, 255, 0.2);">Warrior</th>
                                <th style="padding: 1rem; text-align: center; color: var(--cyber-neon-orange); border-bottom: 1px solid rgba(255, 255, 255, 0.2);">Professional</th>
                                <th style="padding: 1rem; text-align: center; color: var(--cyber-neon-pink); border-bottom: 1px solid rgba(255, 255, 255, 0.2);">Master</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 1rem; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">Websites</td>
                                <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">1</td>
                                <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">5</td>
                                <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">10</td>
                                <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">Unlimited</td>
                            </tr>
                            <tr>
                                <td style="padding: 1rem; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">SSD Storage</td>
                                <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">10 GB</td>
                                <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">50 GB</td>
                                <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">100 GB</td>
                                <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">500 GB</td>
                            </tr>
                            <tr>
                                <td style="padding: 1rem; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">Bandwidth</td>
                                <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">100 GB</td>
                                <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">Unlimited</td>
                                <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">Unlimited</td>
                                <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">Unlimited</td>
                            </tr>
                            <tr>
                                <td style="padding: 1rem; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">Free SSL</td>
                                <td style="padding: 1rem; text-align: center; color: var(--cyber-neon-green); border-bottom: 1px solid rgba(255, 255, 255, 0.1);"><i class="fas fa-check"></i></td>
                                <td style="padding: 1rem; text-align: center; color: var(--cyber-neon-green); border-bottom: 1px solid rgba(255, 255, 255, 0.1);"><i class="fas fa-check"></i></td>
                                <td style="padding: 1rem; text-align: center; color: var(--cyber-neon-green); border-bottom: 1px solid rgba(255, 255, 255, 0.1);"><i class="fas fa-check"></i></td>
                                <td style="padding: 1rem; text-align: center; color: var(--cyber-neon-green); border-bottom: 1px solid rgba(255, 255, 255, 0.1);"><i class="fas fa-check"></i></td>
                            </tr>
                            <tr>
                                <td style="padding: 1rem; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">Email Accounts</td>
                                <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">5</td>
                                <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">25</td>
                                <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">100</td>
                                <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">Unlimited</td>
                            </tr>
                            <tr>
                                <td style="padding: 1rem; color: rgba(255, 255, 255, 0.8);">Support Level</td>
                                <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8);">24/7</td>
                                <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8);">Priority</td>
                                <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8);">Priority</td>
                                <td style="padding: 1rem; text-align: center; color: rgba(255, 255, 255, 0.8);">Dedicated</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- Money Back Guarantee -->
    <section class="section">
        <div class="container">
            <div class="cyber-card" style="max-width: 800px; margin: 0 auto; text-align: center;">
                <div style="margin-bottom: 2rem;">
                    <i class="fas fa-shield-alt" style="font-size: 3rem; color: var(--cyber-neon-green); margin-bottom: 1rem;"></i>
                    <h3 style="color: var(--cyber-neon-green); margin-bottom: 1rem;">30-DAY MONEY-BACK GUARANTEE</h3>
                    <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 2rem;">
                        Try our quantum hosting risk-free. If you're not completely satisfied within 30 days, 
                        we'll refund your credits to the matrix, no questions asked.
                    </p>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem;">
                    <div>
                        <div style="font-size: 2rem; font-weight: bold; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;">99.9%</div>
                        <div style="color: rgba(255, 255, 255, 0.7);">Uptime Guarantee</div>
                    </div>
                    <div>
                        <div style="font-size: 2rem; font-weight: bold; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;">24/7</div>
                        <div style="color: rgba(255, 255, 255, 0.7);">Cyber Guardian</div>
                    </div>
                    <div>
                        <div style="font-size: 2rem; font-weight: bold; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;">30 Days</div>
                        <div style="color: rgba(255, 255, 255, 0.7);">Money Back</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
        <div class="container text-center">
            <h2>READY TO TRANSCEND REALITY?</h2>
            <p class="hero-subtitle mb-8">
                Join the digital revolution. Break free from ordinary hosting. Enter the HiveNest matrix.
            </p>
            <div style="display: flex; gap: 2rem; justify-content: center; flex-wrap: wrap;">
                <a href="../contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">CHOOSE YOUR POWER</a>
                <a href="../contact.php" class="btn btn-secondary" style="font-size: 1.2rem; padding: 20px 40px;">NEURAL CONSULTATION</a>
            </div>
        </div>
    </section>

<?php include '../utilities/footer.php'; ?>

<?php include '../utilities/scripts.php'; ?>
</body>
</html>
