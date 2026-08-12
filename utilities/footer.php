<?php
// Site-wide assigned-product fallback. Dedicated product pages set the marker
// through loadAssignedPagePricingPlans(); older overview pages are handled here.
if (empty($GLOBALS['hivenest_disable_footer_products']) && empty($GLOBALS['hivenest_assigned_products_rendered'])) {
    require_once __DIR__ . '/dynamic_pricing.php';
    require_once __DIR__ . '/pricing-cards.php';
    $hivenest_footer_products = loadAssignedPagePricingPlans(null, 'addAssignedPageProductToCart');
    if (!empty($hivenest_footer_products)):
?>
<script>
function addAssignedPageProductToCart(packageId, price) {
    const item = {
        id: packageId,
        name: packageId.split('--').pop().replace(/-/g, ' ').toUpperCase(),
        price: Number(price),
        type: 'service'
    };
    if (window.shoppingCart && typeof window.shoppingCart.addItem === 'function') {
        window.shoppingCart.addItem(item);
    } else if (typeof window.addToCart === 'function') {
        window.addToCart(item);
    }
}
</script>
<section class="section assigned-page-products">
    <div class="container">
        <div class="text-center mb-8">
            <h2>AVAILABLE PACKAGES</h2>
            <p class="hero-subtitle">Choose the package that fits your requirements</p>
        </div>
        <?php echo renderPricingGrid($hivenest_footer_products); ?>
    </div>
</section>
<?php
    endif;
}
?>
<!-- Footer -->
    <footer style="background: var(--cyber-black); border-top: 1px solid rgba(255, 0, 255, 0.3); padding: 3rem 0 1rem;">
        <div class="container">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                <div>
                    <h4 style="color: var(--cyber-neon-pink); margin-bottom: 1rem;">HIVENEST MATRIX</h4>
                    <p style="color: rgba(255, 255, 255, 0.7);">
                        Building digital empires across parallel universes. Your gateway to infinite possibilities.
                    </p>
                </div>
                
                <div>
                    <h4 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">NEURAL NETWORK</h4>
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin: 0.5rem 0;"><a href="/main-services/domains.php" style="color: rgba(255, 255, 255, 0.7); text-decoration: none;">Neural Domains</a></li>
                        <li style="margin: 0.5rem 0;"><a href="/main-services/hosting.php" style="color: rgba(255, 255, 255, 0.7); text-decoration: none;">Quantum Hosting</a></li>
                        <li style="margin: 0.5rem 0;"><a href="/main-services/servers.php" style="color: rgba(255, 255, 255, 0.7); text-decoration: none;">Neural Servers</a></li>
                        <li style="margin: 0.5rem 0;"><a href="/hosting/cloud-hosting.php" style="color: rgba(255, 255, 255, 0.7); text-decoration: none;">Cloud Matrix</a></li>
                        <li style="margin: 0.5rem 0;"><a href="/main-services/email.php" style="color: rgba(255, 255, 255, 0.7); text-decoration: none;">Comm Arrays</a></li>
                        <li style="margin: 0.5rem 0;"><a href="/main-services/tools.php" style="color: rgba(255, 255, 255, 0.7); text-decoration: none;">Cyber Arsenal</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 style="color: var(--cyber-neon-green); margin-bottom: 1rem;">NEURAL GRAPHICS</h4>
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin: 0.5rem 0;"><a href="/branding/logo.php" style="color: rgba(255, 255, 255, 0.7); text-decoration: none;">Logo Design</a></li>
                        <li style="margin: 0.5rem 0;"><a href="/branding/signatures.php" style="color: rgba(255, 255, 255, 0.7); text-decoration: none;">Signatures</a></li>
                        <li style="margin: 0.5rem 0;"><a href="/branding/letterheads.php" style="color: rgba(255, 255, 255, 0.7); text-decoration: none;">Letterheads</a></li>
                        <li style="margin: 0.5rem 0;"><a href="/branding/business-cards.php" style="color: rgba(255, 255, 255, 0.7); text-decoration: none;">Business Cards</a></li>
                        <li style="margin: 0.5rem 0;"><a href="/branding/website-builder.php" style="color: rgba(255, 255, 255, 0.7); text-decoration: none;">Website Builder</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 style="color: var(--cyber-neon-orange); margin-bottom: 1rem;">DIGITAL PORTALS</h4>
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin: 0.5rem 0;"><a href="/domains/cyber-scan.php" style="color: rgba(255, 255, 255, 0.7); text-decoration: none;">Cyber Scan</a></li>
                        <li style="margin: 0.5rem 0;"><a href="/marketing/offers.php" style="color: rgba(255, 255, 255, 0.7); text-decoration: none;">Special Ops</a></li>
                        <li style="margin: 0.5rem 0;"><a href="/cart.php" style="color: rgba(255, 255, 255, 0.7); text-decoration: none;">Neural Cart</a></li>
                        <li style="margin: 0.5rem 0;"><a href="/login.php" style="color: rgba(255, 255, 255, 0.7); text-decoration: none;">Access Portal</a></li>
                    </ul>
                </div>
            </div>
            
            <div style="text-align: center; padding-top: 2rem; border-top: 1px solid rgba(255, 255, 255, 0.1);">
                <p style="color: rgba(255, 255, 255, 0.5);">&copy; <?php echo date('Y'); ?> HiveNest Matrix. All realities reserved. Broadcasting from the digital future.</p>
            </div>
        </div>
    </footer>
