<?php
// Page variables
$current_page = 'cart';
$page_title = 'Neural Cart - Shopping Cart | HiveNest Matrix';
$page_description = 'Review and manage your selected services in the HiveNest neural cart.';
$page_keywords = 'shopping cart, neural cart, order review';
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
<section class="hero">
    <img src="assets/images/heroes/hero-cart.jpg" alt="Neural Cart" class="hero-background">
    <div class="hero-content">
        <h1>NEURAL CART</h1>
        <p class="hero-subtitle">Your selected neural enhancements are ready for deployment</p>
    </div>
</section>

<!-- Cart Content -->
<section class="content-section">
    <div class="container">
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; align-items: start;">
            <!-- Cart Items (Left Column) -->
            <div>
                <h2 class="section-title" style="color: var(--cyber-neon-cyan); margin-bottom: 20px;">CART ITEMS</h2>
                <div id="cart-items"></div>
            </div>
            
            <!-- Order Summary (Right Column - Sticky) -->
            <div style="position: sticky; top: 100px;">
                <div class="cyber-card" style="padding: 25px; border: 2px solid var(--cyber-neon-cyan);">
                    <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 20px; font-size: 20px; text-transform: uppercase;">Order Summary</h3>
                    <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(0,255,255,0.2);">
                        <span style="color: rgba(255,255,255,0.8);">Subtotal:</span>
                        <span id="cart-total" data-usd-price="0" style="color: white; font-weight: 600;">$0.00</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 15px 0; margin-top: 10px; font-size: 22px; font-weight: bold; color: var(--cyber-neon-green);">
                        <span>Total:</span>
                        <span id="cart-total-final" data-usd-price="0">$0.00</span>
                    </div>
                    <button id="checkout-btn" class="btn btn-primary" onclick="proceedToCheckout()" style="width: 100%; margin-top: 20px; padding: 15px; font-size: 16px;">
                        <i class="fas fa-lock"></i> PROCEED TO CHECKOUT
                    </button>
                    <button class="btn btn-outline" onclick="clearCart()" style="width: 100%; margin-top: 10px; padding: 12px; border-color: var(--cyber-neon-pink); color: var(--cyber-neon-pink);">
                        <i class="fas fa-trash"></i> CLEAR CART
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Responsive cart layout */
@media (max-width: 1024px) {
    .content-section .container > div {
        grid-template-columns: 1fr !important;
    }
    .content-section .container > div > div:last-child {
        position: relative !important;
        top: 0 !important;
    }
}
</style>

<?php include 'utilities/footer.php'; ?>
<?php include 'utilities/scripts.php'; ?>

<script>
// Cart display logic - SIMPLE VERSION THAT WORKS
document.addEventListener('DOMContentLoaded', function() {
    console.log('Cart page loaded');
    
    if (typeof window.cart === 'undefined') {
        console.error('Cart system not loaded!');
        document.getElementById('cart-items').innerHTML = '<div class="cyber-card" style="padding: 30px; text-align: center;"><p style="color: #ff0064;">Error: Cart system not loaded</p></div>';
        return;
    }
    
    console.log('Cart items:', cart.items);
    console.log('Cart count:', cart.items.length);
    
    // Override cart display for this page
    cart.updateDisplay = function() {
        const container = document.getElementById('cart-items');
        const totalElement = document.getElementById('cart-total');
        const finalTotal = document.getElementById('cart-total-final');
        const checkoutBtn = document.getElementById('checkout-btn');
        const payableItems = this.items.filter(isPayableCartItem);
        const skippedCount = this.items.length - payableItems.length;
        
        if (!container) return;
        
        if (payableItems.length === 0) {
            container.innerHTML = '<div class="cyber-card" style="text-align: center; padding: 3rem;"><p style="color: rgba(255,255,255,0.8);">Your neural cart is empty. Browse our services to add items.</p><a href="index.php" class="btn btn-primary">EXPLORE SERVICES</a></div>';
            totalElement.dataset.usdPrice = '0';
            totalElement.textContent = '$0.00';
            if (finalTotal) {
                finalTotal.dataset.usdPrice = '0';
                finalTotal.textContent = '$0.00';
            }
            checkoutBtn.style.display = 'none';
            if (window.HiveNestCurrency) window.HiveNestCurrency.apply();
            return;
        }
        
        let html = '';
        if (skippedCount > 0) {
            html += '<div class="cyber-card" style="padding: 14px 18px; margin-bottom: 16px; border-color: rgba(255,165,0,0.45); color: var(--cyber-neon-orange);">';
            html += '<i class="fas fa-info-circle"></i> ' + skippedCount + ' saved/wishlist/non-checkout item(s) are not included in this order.';
            html += '</div>';
        }
        const parentItems = payableItems.filter(function(item) { return !item.parent_id; });
        const childItemsByParent = {};
        payableItems.filter(function(item) { return item.parent_id; }).forEach(function(child) {
            if (!childItemsByParent[child.parent_id]) childItemsByParent[child.parent_id] = [];
            childItemsByParent[child.parent_id].push(child);
        });
        
        parentItems.forEach(function(item) {
            // Determine icon based on product type/name
            let icon = '<i class="fas fa-cube"></i>'; // default
            const name = item.name.toLowerCase();
            if (name.includes('wordpress')) {
                icon = '<i class="fab fa-wordpress"></i>';
            } else if (name.includes('windows')) {
                icon = '<i class="fab fa-windows"></i>';
            } else if (name.includes('linux')) {
                icon = '<i class="fab fa-linux"></i>';
            } else if (name.includes('domain')) {
                icon = '<i class="fas fa-globe"></i>';
            } else if (name.includes('email') || name.includes('mail')) {
                icon = '<i class="fas fa-envelope"></i>';
            } else if (name.includes('ssl') || name.includes('security')) {
                icon = '<i class="fas fa-shield-alt"></i>';
            } else if (name.includes('hosting') || name.includes('server')) {
                icon = '<i class="fas fa-server"></i>';
            }
            
            const quantity = item.quantity || 1;
            const itemTotal = (item.price * quantity).toFixed(2);
            
            // Compact cart item design
            html += '<div style="display: flex; align-items: center; gap: 15px; padding: 15px; margin-bottom: 12px; background: rgba(0,255,255,0.05); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; transition: all 0.3s;" onmouseover="this.style.borderColor=\'var(--cyber-neon-cyan)\'" onmouseout="this.style.borderColor=\'rgba(0,255,255,0.3)\'">';
            
            // Icon (left)
            html += '<div style="font-size: 32px; color: var(--cyber-neon-cyan); min-width: 50px; text-align: center;">';
            html += icon;
            html += '</div>';
            
            // Product info (middle - flex grow)
            html += '<div style="flex: 1;">';
            html += '<h4 style="color: var(--cyber-neon-cyan); margin: 0 0 5px 0; font-size: 16px;">' + item.name + '</h4>';
            html += '<p style="color: rgba(255,255,255,0.6); margin: 0; font-size: 13px;">' + (item.description || item.name) + '</p>';
            const itemDomain = item.domain || item.domain_name || item.primary_domain || item.product_config?.domain || item.product_config?.domain_name || item.product_config?.primary_domain || '';
            const itemTermMonths = item.term_months || item.product_config?.term_months || '';
            if (itemDomain || itemTermMonths) {
                html += '<div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:7px;">';
                if (itemDomain) {
                    html += '<span style="display:inline-block; background:rgba(0,255,255,0.12); color:var(--cyber-neon-cyan); border:1px solid rgba(0,255,255,0.28); padding:2px 8px; border-radius:12px; font-size:12px;"><i class="fas fa-globe"></i> ' + escapeCartHtml(itemDomain) + '</span>';
                }
                if (itemTermMonths) {
                    html += '<span style="display:inline-block; background:rgba(255,0,255,0.12); color:var(--cyber-neon-pink); border:1px solid rgba(255,0,255,0.28); padding:2px 8px; border-radius:12px; font-size:12px;"><i class="fas fa-calendar-alt"></i> ' + escapeCartHtml(itemTermMonths) + ' month' + (Number(itemTermMonths) === 1 ? '' : 's') + '</span>';
                }
                html += '</div>';
            }
            if (quantity > 1) {
                html += '<span style="display: inline-block; background: rgba(0,255,0,0.2); color: var(--cyber-neon-green); padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-top: 5px;">Qty: ' + quantity + '</span>';
            }
            const bundleItems = getCartBundleItems(item);
            if (bundleItems.length > 0) {
                html += '<div style="margin-top:10px; padding:9px 10px; background:rgba(0,255,255,0.06); border:1px solid rgba(0,255,255,0.20); border-radius:8px;">';
                html += '<div style="color:var(--cyber-neon-cyan); font-size:12px; letter-spacing:1px; margin-bottom:6px;"><i class="fas fa-layer-group"></i> INCLUDES</div>';
                html += bundleItems.map(function(bundleItem) {
                    const bundleName = bundleItem.name || bundleItem.sku || 'Bundled service';
                    const bundleProvider = bundleItem.provider ? ' · ' + bundleItem.provider : '';
                    const bundleDomain = bundleItem.domain || bundleItem.domain_name || (bundleItem.requires_domain ? itemDomain : '');
                    const bundleQty = Number(bundleItem.quantity || 1);
                    let line = escapeCartHtml(bundleName) + (bundleQty > 1 ? ' x' + bundleQty : '') + escapeCartHtml(bundleProvider);
                    if (bundleDomain) line += ' · ' + escapeCartHtml(bundleDomain);
                    return '<div style="color:rgba(255,255,255,0.75); font-size:12px; margin:3px 0;"><i class="fas fa-check" style="color:var(--cyber-neon-green); margin-right:6px;"></i>' + line + '</div>';
                }).join('');
                html += '</div>';
            }
            html += '</div>';
            
            // Price and remove (right)
            html += '<div style="text-align: right; min-width: 120px;">';
            html += '<div data-usd-price="' + itemTotal + '" style="color: var(--cyber-neon-green); font-size: 20px; font-weight: bold; margin-bottom: 8px;">$' + itemTotal + '</div>';
            html += '<button onclick="removeFromCart(\'' + item.id + '\')" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px; border-color: var(--cyber-neon-pink); color: var(--cyber-neon-pink);">';
            html += '<i class="fas fa-trash"></i> Remove';
            html += '</button>';
            html += '</div>';
            
            html += '</div>';

            const childItems = childItemsByParent[item.id] || [];
            childItems.forEach(function(child) {
                const childQuantity = child.quantity || 1;
                const childTotal = (Number(child.price || 0) * childQuantity).toFixed(2);
                html += '<div style="display: flex; align-items: center; gap: 12px; padding: 12px 15px 12px 78px; margin: -6px 0 12px 0; background: rgba(255,0,255,0.06); border: 1px solid rgba(255,0,255,0.28); border-radius: 8px;">';
                html += '<div style="font-size: 22px; color: var(--cyber-neon-pink); min-width: 32px; text-align: center;"><i class="fas fa-user-shield"></i></div>';
                html += '<div style="flex: 1;">';
                html += '<h4 style="color: var(--cyber-neon-pink); margin: 0 0 4px 0; font-size: 14px;">' + child.name + '</h4>';
                html += '<p style="color: rgba(255,255,255,0.65); margin: 0; font-size: 12px;">' + (child.description || 'Optional add-on') + '</p>';
                html += '</div>';
                html += '<div style="text-align: right; min-width: 120px;">';
                html += '<div data-usd-price="' + childTotal + '" style="color: var(--cyber-neon-green); font-size: 17px; font-weight: bold; margin-bottom: 7px;">$' + childTotal + '</div>';
                html += '<button onclick="removeFromCart(\'' + child.id + '\')" class="btn btn-outline" style="padding: 5px 10px; font-size: 11px; border-color: var(--cyber-neon-pink); color: var(--cyber-neon-pink);">';
                html += '<i class="fas fa-trash"></i> Remove Add-on';
                html += '</button>';
                html += '</div>';
                html += '</div>';
            });
        });
        
        container.innerHTML = html;
        const total = payableItems.reduce(function(sum, item) {
            return sum + (Number(item.price || 0) * Number(item.quantity || 1));
        }, 0).toFixed(2);
        totalElement.dataset.usdPrice = total;
        totalElement.textContent = '$' + total;
        if (finalTotal) {
            finalTotal.dataset.usdPrice = total;
            finalTotal.textContent = '$' + total;
        }
        if (window.HiveNestCurrency) window.HiveNestCurrency.apply();
        checkoutBtn.style.display = 'block';
    };
    
    // Initial display
    cart.updateDisplay();
});

function isPayableCartItem(item) {
    if (!item || typeof item !== 'object') return false;
    const marker = [
        item.status,
        item.cart_section,
        item.section,
        item.list,
        item.type,
        item.category
    ].map(function(value) { return String(value || '').toLowerCase(); }).join('|');
    if (
        item.wishlist === true ||
        item.is_wishlist === true ||
        item.saved_for_later === true ||
        item.hidden === true ||
        marker.includes('wishlist') ||
        marker.includes('wish_list') ||
        marker.includes('saved_for_later') ||
        marker.includes('saved') ||
        marker.includes('hidden')
    ) {
        return false;
    }
    return Number.isFinite(Number(item.price)) && Number(item.price) > 0;
}

function getCartBundleItems(item) {
    const raw = item?.bundle_items || item?.product_config?.bundle_items || [];
    if (Array.isArray(raw)) return raw.filter(function(entry) { return entry && typeof entry === 'object'; });
    if (typeof raw === 'string' && raw.trim() !== '') {
        try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed.filter(function(entry) { return entry && typeof entry === 'object'; }) : [];
        } catch (error) {
            console.error('Invalid bundle_items JSON in cart item', error);
        }
    }
    return [];
}

function escapeCartHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value || '');
    return div.innerHTML;
}

// Helper functions
function removeFromCart(itemId) {
    if (typeof window.cart !== 'undefined') {
        cart.removeItem(itemId);
        cart.updateDisplay();
    }
}

function proceedToCheckout() {
    if (typeof window.cart !== 'undefined' && cart.items.filter(isPayableCartItem).length > 0) {
        window.location.href = 'checkout.php';
    } else {
        console.warn('Your cart is empty. Add items before checking out.');
    }
}

function clearCart() {
    if (typeof window.cart !== 'undefined') {
        cart.clearCart();
        cart.updateDisplay();
    }
}
</script>

</body>
</html>
