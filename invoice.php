<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/utilities/order_notifications.php';

$orderNumber = trim((string)($_GET['order'] ?? ''));
$printMode = strtolower(trim((string)($_GET['mode'] ?? ''))) === 'pdf';
$order = null;
$error = '';
$invoiceAdminView = false;

if ($orderNumber === '') {
    $error = 'Missing invoice order number.';
} else {
    $db = hivenest_db();
    if (!$db) {
        $error = 'Invoice database is unavailable.';
    } else {
        $order = hivenest_fetch_order_for_email($db, $orderNumber);
        $sessionCustomerId = (int)($_SESSION['customer_id'] ?? 0);
        $customerOwnsOrder = $order && (int)$order['customer_id'] === $sessionCustomerId;
        if ($order && !$customerOwnsOrder && !empty($_COOKIE['HIVENEST_ADMIN'])) {
            session_write_close();
            session_name('HIVENEST_ADMIN');
            session_start();
            require_once __DIR__ . '/utilities/admin_auth.php';
            if (isAdminAuthenticated()) {
                $admin = currentAdmin();
                $adminId = (int)($admin['id'] ?? 0);
                if ($adminId > 0) {
                    $adminStmt = $db->prepare('SELECT COUNT(*) FROM admin_users WHERE id=:id AND is_active=1');
                    $adminStmt->execute(['id' => $adminId]);
                    $invoiceAdminView = (int)$adminStmt->fetchColumn() === 1;
                }
            }
        }
        if (!$order || (!$customerOwnsOrder && !$invoiceAdminView)) {
            $order = null;
            $error = 'Invoice unavailable. Please sign in to the customer account that placed this order.';
        }
    }
}

function invoice_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$current_page = 'invoice';
$page_title = 'Paid Invoice | HiveNest Matrix';
$page_description = 'View your HiveNest paid invoice.';
$page_keywords = 'hivenest invoice, paid invoice';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/utilities/head.php'; ?>
<?php if ($printMode && $order): ?>
<script>
window.addEventListener('load', () => {
    setTimeout(() => window.print(), 350);
});
</script>
<?php endif; ?>
<style>
.invoice-shell {
    max-width: 960px;
    margin: 8rem auto 3rem;
    padding: 2rem;
}
.invoice-panel {
    background: rgba(0, 0, 0, 0.72);
    border: 1px solid rgba(0, 255, 255, 0.35);
    border-radius: 12px;
    padding: 2rem;
}
.invoice-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    justify-content: flex-end;
    margin-bottom: 1.5rem;
}
.invoice-header {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    border-bottom: 1px solid rgba(0, 255, 255, 0.25);
    padding-bottom: 1.5rem;
    margin-bottom: 1.5rem;
}
.invoice-title {
    color: var(--cyber-neon-cyan);
    margin: 0 0 0.5rem;
}
.paid-badge {
    display: inline-block;
    padding: 0.45rem 0.9rem;
    border-radius: 999px;
    color: var(--cyber-neon-green);
    border: 1px solid var(--cyber-neon-green);
    background: rgba(0, 255, 0, 0.12);
    font-weight: 700;
    letter-spacing: 0.08em;
}
.invoice-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1.5rem;
}
.invoice-table th,
.invoice-table td {
    padding: 0.9rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
    text-align: left;
}
.invoice-table th {
    color: var(--cyber-neon-cyan);
}
.invoice-total {
    display: grid;
    gap: 0.5rem;
    max-width: 340px;
    margin-left: auto;
    margin-top: 1.5rem;
}
.invoice-total div {
    display: flex;
    justify-content: space-between;
}
.invoice-total .grand {
    color: var(--cyber-neon-pink);
    font-size: 1.25rem;
    font-weight: 700;
    border-top: 1px solid rgba(255, 255, 255, 0.18);
    padding-top: 0.75rem;
}
@media print {
    @page { margin: 14mm; }
    body { background: #fff !important; color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    nav, .navbar, .mobile-menu, footer, .invoice-actions, .admin-view-notice { display: none !important; }
    .invoice-shell { margin: 0; max-width: none; padding: 0; }
    .invoice-panel { border: none; background: #fff !important; color: #000; padding: 0; }
    .invoice-title { color: #000 !important; }
    .invoice-header { border-bottom-color: #00a9a9 !important; }
    .invoice-table th { color: #000 !important; }
    .invoice-table th,
    .invoice-table td { border-bottom-color: rgba(0, 0, 0, 0.16) !important; }
    .paid-badge { color: #008000 !important; border-color: #008000 !important; background: rgba(0, 128, 0, 0.08) !important; }
    .invoice-total .grand { color: #c000c0 !important; border-top-color: rgba(0, 0, 0, 0.22) !important; }
    h3[style] { color: #00a9a9 !important; }
}
</style>
</head>
<body>
<?php include __DIR__ . '/utilities/nav.php'; ?>
<?php include __DIR__ . '/utilities/mobile-menu.php'; ?>

<main class="invoice-shell">
    <div class="invoice-panel">
        <?php if ($error): ?>
            <h1 class="invoice-title">Invoice unavailable</h1>
            <p style="color: rgba(255,255,255,0.82);"><?php echo invoice_h($error); ?></p>
            <a href="auth.php?mode=login&return=<?php echo urlencode('invoice.php?order=' . $orderNumber); ?>" class="btn btn-primary">Sign in</a>
        <?php else: ?>
            <?php if ($invoiceAdminView): ?>
                <div class="admin-view-notice" style="margin-bottom:1rem;padding:0.8rem 1rem;border:1px solid rgba(255,193,7,0.55);border-radius:8px;background:rgba(255,193,7,0.1);color:#ffd76a;">
                    Staff invoice view — access granted through your active HiveNest administrator session.
                </div>
            <?php endif; ?>
            <div class="invoice-actions">
                <a class="btn btn-outline" href="<?php echo $invoiceAdminView ? 'https://crm.hivenest.co.za/orders/index.html' : 'order-success.php?order=' . urlencode((string)$order['order_number']); ?>">
                    <i class="fas fa-arrow-left" style="margin-right: 0.5rem;"></i>
                    <?php echo $invoiceAdminView ? 'BACK TO CRM ORDERS' : 'BACK TO PAYMENT SUCCESSFUL'; ?>
                </a>
                <a class="btn btn-primary" href="invoice.php?order=<?php echo urlencode((string)$order['order_number']); ?>&mode=pdf" target="_blank" rel="noopener">
                    <i class="fas fa-file-pdf" style="margin-right: 0.5rem;"></i>
                    DOWNLOAD PDF
                </a>
                <button type="button" class="btn btn-outline" onclick="window.print()">
                    <i class="fas fa-print" style="margin-right: 0.5rem;"></i>
                    PRINT / SAVE
                </button>
            </div>

            <div class="invoice-header">
                <div>
                    <h1 class="invoice-title">HiveNest Paid Invoice</h1>
                    <p style="margin: 0; color: rgba(255,255,255,0.75);">Order #<?php echo invoice_h($order['order_number']); ?></p>
                    <p style="margin: 0.4rem 0 0; color: rgba(255,255,255,0.75);">Paid: <?php echo invoice_h($order['processed_at'] ?: $order['created_at']); ?></p>
                </div>
                <div style="text-align: right;">
                    <span class="paid-badge">PAID</span>
                    <p style="margin: 0.75rem 0 0; color: rgba(255,255,255,0.75);">Reference: <?php echo invoice_h($order['payment_reference']); ?></p>
                </div>
            </div>

            <section style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem;">
                <div>
                    <h3 style="color: var(--cyber-neon-cyan);">Billed To</h3>
                    <p>
                        <?php echo invoice_h(hivenest_customer_display_name($order)); ?><br>
                        <?php echo invoice_h($order['email']); ?><br>
                        <?php echo invoice_h($order['address_line1']); ?><br>
                        <?php if (!empty($order['address_line2'])) echo invoice_h($order['address_line2']) . '<br>'; ?>
                        <?php echo invoice_h(trim((string)$order['city'] . ' ' . (string)$order['state'] . ' ' . (string)$order['postal_code'])); ?><br>
                        <?php echo invoice_h($order['country']); ?>
                    </p>
                </div>
                <div>
                    <h3 style="color: var(--cyber-neon-cyan);">HiveNest</h3>
                    <p>
                        HiveNest Matrix<br>
                        orders@hivenest.co.za<br>
                        support@hivenest.co.za
                    </p>
                </div>
            </section>

            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Domain</th>
                        <th>Cycle</th>
                        <th>Qty</th>
                        <th>Unit</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                    <tr>
                        <td>
                            <?php echo invoice_h($item['product_name']); ?>
                            <?php $bundleItems = hivenest_order_item_bundle_items($item); ?>
                            <?php if ($bundleItems): ?>
                                <div style="margin-top:0.65rem; padding:0.65rem 0.75rem; border-left:3px solid var(--cyber-neon-cyan); background:rgba(0,255,255,0.07);">
                                    <div style="color:var(--cyber-neon-cyan); font-size:0.78rem; letter-spacing:0.08em; margin-bottom:0.35rem;">INCLUDES</div>
                                    <?php foreach ($bundleItems as $bundleItem): ?>
                                        <?php
                                            $bundleLine = $bundleItem['name'];
                                            if ((int)$bundleItem['quantity'] > 1) $bundleLine .= ' x' . (int)$bundleItem['quantity'];
                                            if ($bundleItem['domain'] !== '') $bundleLine .= ' · ' . $bundleItem['domain'];
                                            if ($bundleItem['term_months'] !== '') $bundleLine .= ' · ' . $bundleItem['term_months'] . ' month' . ($bundleItem['term_months'] === '1' ? '' : 's');
                                        ?>
                                        <div style="font-size:0.85rem; color:rgba(255,255,255,0.75);">✓ <?php echo invoice_h($bundleLine); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo invoice_h($item['domain_name'] ?: '-'); ?></td>
                        <td><?php echo invoice_h($item['billing_cycle']); ?></td>
                        <td><?php echo (int)$item['quantity']; ?></td>
                        <td><?php echo invoice_h(hivenest_money((float)$item['unit_price'] + (float)$item['setup_fee'], (string)$order['currency'])); ?></td>
                        <td><?php echo invoice_h(hivenest_money((float)$item['line_total'], (string)$order['currency'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="invoice-total">
                <div><span>Subtotal</span><span><?php echo invoice_h(hivenest_money((float)$order['subtotal'], (string)$order['currency'])); ?></span></div>
                <div><span>Tax</span><span><?php echo invoice_h(hivenest_money((float)$order['tax_amount'], (string)$order['currency'])); ?></span></div>
                <div><span>Loyalty Discount</span><span><?php echo invoice_h(hivenest_money((float)($order['loyalty_discount_amount'] ?? $order['discount_amount']), (string)$order['currency'])); ?></span></div>
                <?php if (!empty($order['promotion_code']) || (float)($order['promotion_discount_amount'] ?? 0) > 0): ?>
                    <div>
                        <span>Promotion (<?php echo invoice_h($order['promotion_code']); ?>)</span>
                        <span><?php echo invoice_h(hivenest_money((float)$order['promotion_discount_amount'], (string)$order['currency'])); ?></span>
                    </div>
                <?php endif; ?>
                <div class="grand"><span>Total Paid</span><span><?php echo invoice_h(hivenest_money((float)$order['total_amount'], (string)$order['currency'])); ?></span></div>
                <?php if (!empty($order['display_currency'])
                    && $order['display_total_amount'] !== null
                    && strtoupper((string)$order['display_currency']) !== strtoupper((string)$order['currency'])): ?>
                    <div>
                        <span>Display total at order time</span>
                        <span><?php echo invoice_h(hivenest_money((float)$order['display_total_amount'], (string)$order['display_currency'])); ?></span>
                    </div>
                    <div style="font-size:0.78rem; opacity:0.72;">
                        <span>Captured exchange rate</span>
                        <span>1 USD = <?php echo invoice_h(number_format((float)$order['display_exchange_rate'], 8, '.', '')); ?> <?php echo invoice_h($order['display_currency']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/utilities/footer.php'; ?>
<?php include __DIR__ . '/utilities/scripts.php'; ?>
</body>
</html>
