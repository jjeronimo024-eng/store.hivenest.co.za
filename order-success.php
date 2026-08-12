<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/utilities/order_notifications.php';

// Page variables
$current_page = 'order-success';
$page_title = 'Order Successful - Thank You | HiveNest Matrix';
$page_description = 'Your order has been successfully placed and is being processed.';
$page_keywords = 'order success, order confirmation, thank you';

// Get order ID from URL
$order_id = isset($_GET['order']) ? trim((string)$_GET['order']) : null;
$success_order = null;
if ($order_id) {
    $success_db = hivenest_db();
    if ($success_db) {
        $success_order = hivenest_fetch_order_for_email($success_db, $order_id);
        $sessionCustomerId = (int)($_SESSION['customer_id'] ?? 0);
        if (!$success_order || (int)($success_order['customer_id'] ?? 0) !== $sessionCustomerId) {
            $success_order = null;
        }
    }
}

function success_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Page-specific JavaScript
$page_scripts = <<<'JAVASCRIPT'
// Confetti animation
function createConfetti() {
    const colors = ['#00ffff', '#ff00ff', '#00ff00', '#ffff00'];
    const confettiContainer = document.createElement('div');
    confettiContainer.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 9999;';
    document.body.appendChild(confettiContainer);
    
    for (let i = 0; i < 50; i++) {
        setTimeout(() => {
            const confetti = document.createElement('div');
            confetti.style.cssText = `
                position: absolute;
                width: 10px;
                height: 10px;
                background: ${colors[Math.floor(Math.random() * colors.length)]};
                top: -10px;
                left: ${Math.random() * 100}%;
                animation: fall ${3 + Math.random() * 2}s linear;
                opacity: 0.8;
            `;
            confettiContainer.appendChild(confetti);
            
            setTimeout(() => confetti.remove(), 5000);
        }, i * 50);
    }
    
    setTimeout(() => confettiContainer.remove(), 6000);
}

// Add CSS animation once without leaking a global variable name.
(() => {
const confettiStyle = document.createElement('style');
confettiStyle.textContent = `
    @keyframes fall {
        to {
            transform: translateY(100vh) rotate(360deg);
            opacity: 0;
        }
    }
`;
document.head.appendChild(confettiStyle);
})();

// Trigger confetti on page load
window.addEventListener('DOMContentLoaded', () => {
    createConfetti();
});
JAVASCRIPT;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'utilities/head.php'; ?>
<style>
.success-icon {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(0, 255, 0, 0.2), rgba(0, 255, 255, 0.2));
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 2rem;
    border: 3px solid var(--cyber-neon-green);
    animation: pulse 2s ease-in-out infinite;
}

.success-icon i {
    font-size: 4rem;
    color: var(--cyber-neon-green);
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin: 2rem 0;
}

.info-card {
    background: rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(0, 255, 255, 0.3);
    border-radius: 8px;
    padding: 1.5rem;
    text-align: center;
}

.info-card i {
    font-size: 2.5rem;
    color: var(--cyber-neon-cyan);
    margin-bottom: 1rem;
}

.info-card h4 {
    color: var(--cyber-neon-cyan);
    margin-bottom: 0.5rem;
}

.info-card p {
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}

.success-action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem;
    margin: 2rem 0;
}

.success-action-card {
    display: block;
    background: rgba(0, 0, 0, 0.55);
    border: 1px solid rgba(0, 255, 255, 0.35);
    border-radius: 10px;
    padding: 1.6rem;
    text-align: center;
    text-decoration: none;
    min-height: 160px;
    transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.success-action-card:hover {
    transform: translateY(-4px);
    border-color: var(--cyber-neon-cyan);
    box-shadow: 0 0 24px rgba(0, 255, 255, 0.22);
}

.success-action-card i {
    font-size: 2.6rem;
    color: var(--cyber-neon-cyan);
    margin-bottom: 1rem;
}

.success-action-card h4 {
    color: var(--cyber-neon-cyan);
    margin-bottom: 0.55rem;
}

.success-action-card p {
    color: rgba(255, 255, 255, 0.82);
    margin: 0;
}

.success-action-card.primary {
    background: linear-gradient(135deg, rgba(0, 255, 255, 0.16), rgba(255, 0, 255, 0.16));
}

.success-action-card.green i,
.success-action-card.green h4 {
    color: var(--cyber-neon-green);
}

.success-action-card.pink i,
.success-action-card.pink h4 {
    color: var(--cyber-neon-pink);
}
</style>
</head>
<body>
<?php include 'utilities/nav.php'; ?>

<?php include 'utilities/mobile-menu.php'; ?>

    <!-- Success Section -->
    <section class="section" style="min-height: 80vh; display: flex; align-items: center;">
        <div class="container">
            <div class="cyber-card" style="max-width: 800px; margin: 0 auto; text-align: center; padding: 3rem;">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                
                <h1 style="color: var(--cyber-neon-green); font-size: 2.5rem; margin-bottom: 1rem;">
                    PAYMENT SUCCESSFUL!
                </h1>
                
                <p style="color: rgba(255, 255, 255, 0.9); font-size: 1.2rem; margin-bottom: 2rem;">
                    Your payment has been captured and your order has been recorded. Welcome to the HiveNest Matrix!
                </p>
                
                <?php if ($success_order): ?>
                <div style="background: rgba(0, 255, 255, 0.1); border: 1px solid rgba(0, 255, 255, 0.3); border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem;">
                    <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 0.5rem;">Order Number</p>
                    <h3 style="color: var(--cyber-neon-cyan); font-family: 'Courier New', monospace; margin: 0;">
                        #<?php echo success_h($success_order['order_number']); ?>
                    </h3>
                </div>
                <?php endif; ?>

                <?php if ($success_order && !empty($success_order['items'])): ?>
                <div style="text-align:left; background:rgba(0,255,255,0.06); border:1px solid rgba(0,255,255,0.25); border-radius:10px; padding:1.4rem; margin-bottom:2rem;">
                    <h3 style="color:var(--cyber-neon-cyan); margin:0 0 1rem; text-align:center;">
                        <i class="fas fa-box-open" style="margin-right:0.5rem;"></i>
                        PURCHASED SERVICES
                    </h3>
                    <div style="display:grid; gap:0.9rem;">
                        <?php foreach ($success_order['items'] as $item): ?>
                            <?php $bundleItems = hivenest_order_item_bundle_items($item); ?>
                            <div style="background:rgba(0,0,0,0.45); border:1px solid rgba(0,255,255,0.2); border-radius:8px; padding:0.95rem;">
                                <div style="display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                                    <strong style="color:var(--cyber-neon-cyan);"><?php echo success_h($item['product_name']); ?></strong>
                                    <span style="color:var(--cyber-neon-green);"><?php echo success_h(hivenest_money((float)$item['line_total'], (string)$success_order['currency'])); ?></span>
                                </div>
                                <div style="color:rgba(255,255,255,0.72); font-size:0.9rem; margin-top:0.35rem;">
                                    <?php if (!empty($item['domain_name'])): ?>
                                        Domain: <span class="mono"><?php echo success_h($item['domain_name']); ?></span> ·
                                    <?php endif; ?>
                                    Cycle: <?php echo success_h($item['billing_cycle']); ?> · Qty: <?php echo (int)$item['quantity']; ?>
                                </div>
                                <?php if ($bundleItems): ?>
                                    <div style="margin-top:0.75rem; padding:0.75rem; border-left:3px solid var(--cyber-neon-green); background:rgba(0,255,0,0.06);">
                                        <div style="color:var(--cyber-neon-green); font-size:0.78rem; letter-spacing:0.08em; margin-bottom:0.4rem;">BUNDLE INCLUDES</div>
                                        <?php foreach ($bundleItems as $bundleItem): ?>
                                            <?php
                                                $bundleLine = $bundleItem['name'];
                                                if ((int)$bundleItem['quantity'] > 1) $bundleLine .= ' x' . (int)$bundleItem['quantity'];
                                                if ($bundleItem['domain'] !== '') $bundleLine .= ' · ' . $bundleItem['domain'];
                                                if ($bundleItem['term_months'] !== '') $bundleLine .= ' · ' . $bundleItem['term_months'] . ' month' . ($bundleItem['term_months'] === '1' ? '' : 's');
                                            ?>
                                            <div style="color:rgba(255,255,255,0.78); font-size:0.88rem;">✓ <?php echo success_h($bundleLine); ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin:1.2rem 0 0 auto; max-width:390px; border-top:1px solid rgba(0,255,255,0.28); padding-top:0.9rem;">
                        <div style="display:flex;justify-content:space-between;gap:1rem;margin:0.45rem 0;">
                            <span style="color:rgba(255,255,255,0.72);">Subtotal</span>
                            <span><?php echo success_h(hivenest_money((float)$success_order['subtotal'], (string)$success_order['currency'])); ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;gap:1rem;margin:0.45rem 0;">
                            <span style="color:rgba(255,255,255,0.72);">Tax</span>
                            <span><?php echo success_h(hivenest_money((float)$success_order['tax_amount'], (string)$success_order['currency'])); ?></span>
                        </div>
                        <?php if ((float)($success_order['loyalty_discount_amount'] ?? 0) > 0): ?>
                            <div style="display:flex;justify-content:space-between;gap:1rem;margin:0.45rem 0;color:var(--cyber-neon-green);">
                                <span>Loyalty Discount</span>
                                <span>-<?php echo success_h(hivenest_money((float)$success_order['loyalty_discount_amount'], (string)$success_order['currency'])); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($success_order['promotion_code']) || (float)($success_order['promotion_discount_amount'] ?? 0) > 0): ?>
                            <div style="display:flex;justify-content:space-between;gap:1rem;margin:0.45rem 0;color:var(--cyber-neon-green);">
                                <span>Promotion (<?php echo success_h($success_order['promotion_code']); ?>)</span>
                                <span>-<?php echo success_h(hivenest_money((float)$success_order['promotion_discount_amount'], (string)$success_order['currency'])); ?></span>
                            </div>
                        <?php endif; ?>
                        <div style="display:flex;justify-content:space-between;gap:1rem;margin:0.8rem 0 0;padding-top:0.7rem;border-top:1px solid rgba(255,255,255,0.2);font-size:1.08rem;font-weight:700;color:var(--cyber-neon-pink);">
                            <span>Total Paid</span>
                            <span><?php echo success_h(hivenest_money((float)$success_order['total_amount'], (string)$success_order['currency'])); ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="success-action-grid">
                    <a class="success-action-card" href="<?php echo $success_order ? 'invoice.php?order=' . urlencode((string)$success_order['order_number']) : 'invoice.php'; ?>">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <h4>VIEW / DOWNLOAD INVOICE</h4>
                        <p>Open your paid invoice and save it as PDF</p>
                    </a>

                    <div class="success-action-card">
                        <i class="fas fa-envelope"></i>
                        <h4>CHECK YOUR EMAIL</h4>
                        <p>Paid order confirmation and item emails are sent to your inbox</p>
                    </div>
                    
                    <div class="success-action-card green">
                        <i class="fas fa-rocket"></i>
                        <h4>PROVISIONING PENDING</h4>
                        <p>Your order is awaiting provider/team provisioning</p>
                    </div>

                    <a class="success-action-card primary" href="https://cp.hivenest.co.za">
                        <i class="fas fa-user-circle"></i>
                        <h4>OPEN CLIENT PORTAL</h4>
                        <p>Manage services, invoices, support tickets and account details</p>
                    </a>

                    <a class="success-action-card" href="index.php">
                        <i class="fas fa-home"></i>
                        <h4>RETURN HOME</h4>
                        <p>Go back to the HiveNest storefront</p>
                    </a>
                    
                    <a class="success-action-card pink" href="contact.php">
                        <i class="fas fa-headset"></i>
                        <h4>24/7 SUPPORT</h4>
                        <p>Need help? Contact support about this order</p>
                    </a>
                </div>
            </div>
            
            <!-- What Happens Next -->
            <div class="cyber-card" style="max-width: 800px; margin: 3rem auto; padding: 2rem;">
                <h3 class="service-title" style="text-align: center; margin-bottom: 2rem;">
                    <i class="fas fa-tasks" style="margin-right: 0.5rem;"></i>
                    WHAT HAPPENS NEXT?
                </h3>
                
                <div style="display: grid; gap: 1.5rem;">
                    <div style="display: flex; gap: 1rem; align-items: start;">
                        <div style="width: 40px; height: 40px; background: rgba(0, 255, 255, 0.2); border: 2px solid var(--cyber-neon-cyan); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <strong style="color: var(--cyber-neon-cyan);">1</strong>
                        </div>
                        <div>
                            <h4 style="color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;">Order Confirmation</h4>
                            <p style="color: rgba(255, 255, 255, 0.8); margin: 0;">You'll receive an email confirmation with your order details and invoice within 5 minutes.</p>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; align-items: start;">
                        <div style="width: 40px; height: 40px; background: rgba(0, 255, 0, 0.2); border: 2px solid var(--cyber-neon-green); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <strong style="color: var(--cyber-neon-green);">2</strong>
                        </div>
                        <div>
                            <h4 style="color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;">Service Provisioning</h4>
                            <p style="color: rgba(255, 255, 255, 0.8); margin: 0;">Payment capture does not by itself register a domain. Your order will remain pending until provider provisioning completes and a provider order ID is recorded.</p>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; align-items: start;">
                        <div style="width: 40px; height: 40px; background: rgba(255, 0, 255, 0.2); border: 2px solid var(--cyber-neon-pink); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <strong style="color: var(--cyber-neon-pink);">3</strong>
                        </div>
                        <div>
                            <h4 style="color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;">Access Details</h4>
                            <p style="color: rgba(255, 255, 255, 0.8); margin: 0;">You'll receive separate emails with login credentials and setup instructions for each service.</p>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; align-items: start;">
                        <div style="width: 40px; height: 40px; background: rgba(255, 255, 0, 0.2); border: 2px solid #ffff00; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <strong style="color: #ffff00;">4</strong>
                        </div>
                        <div>
                            <h4 style="color: var(--cyber-neon-cyan); margin-bottom: 0.5rem;">You're All Set!</h4>
                            <p style="color: rgba(255, 255, 255, 0.8); margin: 0;">Once setup is complete, you can start using your services immediately. Our support team is available 24/7 if you need any assistance.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php include 'utilities/footer.php'; ?>

<?php include 'utilities/scripts.php'; ?>
</body>
</html>
