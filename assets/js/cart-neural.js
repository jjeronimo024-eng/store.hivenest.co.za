// HiveNest Neural Cart System
// Unified shopping cart compatible with NeuralCart on cart.php
// Uses localStorage with 'neuralCart' key for consistency

class NeuralCartSystem {
    constructor() {
        this.items = this.loadCart();
        this.updateDisplay();
    }

    // Add item to cart
    addItem(item) {
        // Validate item structure
        const normalizedPrice = Number(item.price);
        if (!item.id || !item.name || !Number.isFinite(normalizedPrice)) {
            console.error('Invalid item structure:', item);
            this.showNotification('This item has an invalid price and was not added.', 'error');
            return false;
        }

        const existingItem = this.items.find(i => i.id === item.id);
        
        if (existingItem) {
            this.showNotification('This item is already in your cart.', 'info');
            return false;
        } else {
            this.items.push({
                id: item.id,
                name: item.name,
                description: item.description || item.name, // Ensure description exists
                price: normalizedPrice,
                type: item.type || 'service',
                quantity: item.quantity || 1,
                category: item.category || 'service',
                allows_addons: item.allows_addons || false,
                domain: item.domain || null,
                domain_name: item.domain_name || item.domain || null,
                primary_domain: item.primary_domain || item.domain || null,
                domain_option: item.domain_option || null,
                tld: item.tld || null,
                parent_product: item.parent_product || null,
                parent_id: item.parent_id || null,
                billing_cycle: item.billing_cycle || null,
                term_months: item.term_months || null,
                monthly_price: item.monthly_price || null,
                bundle_items: Array.isArray(item.bundle_items) ? item.bundle_items : null,
                product_config: item.product_config || null
            });
        }
        
        this.saveCart();
        this.updateDisplay();
        this.showNotification('Item added to neural cart!');
        return true;
    }

    // Remove item from cart
    removeItem(itemId) {
        // Remove the item
        this.items = this.items.filter(item => item.id !== itemId);
        
        // Also remove child addons if parent is removed
        this.items = this.items.filter(item => item.parent_id !== itemId);
        
        this.saveCart();
        this.updateDisplay();
    }

    // Update item quantity
    updateQuantity(itemId, quantity) {
        const item = this.items.find(i => i.id === itemId);
        if (item) {
            item.quantity = Math.max(1, parseInt(quantity));
            this.saveCart();
            this.updateDisplay();
        }
    }

    // Add addon to existing cart item
    addAddon(parentId, addonType, addonName, addonPrice, domain) {
        const addon = {
            id: addonType + '_' + domain.replace(/\./g, '_'),
            name: addonName + ': ' + domain,
            description: this.getAddonDescription(addonType),
            category: 'domain_addon',
            type: addonType,
            price: parseFloat(addonPrice),
            quantity: 1,
            domain: domain,
            parent_id: parentId,
            parent_product: 'domain'
        };
        
        // Check if addon already exists
        const existingIndex = this.items.findIndex(item => item.id === addon.id);
        if (existingIndex < 0) {
            this.items.push(addon);
            this.saveCart();
            this.updateDisplay();
            this.showNotification('Addon added to cart!');
        }
    }

    // Remove addon from cart
    removeAddon(addonId) {
        this.items = this.items.filter(item => item.id !== addonId);
        this.saveCart();
        this.updateDisplay();
    }

    // Get description for addon type
    getAddonDescription(addonType) {
        const descriptions = {
            'domain_privacy': 'WHOIS privacy protection (1 year)',
            'domain-privacy': 'WHOIS privacy protection (1 year)',
            'domain_extend': 'Extend domain by 1 year',
            'domain-extend': 'Extend domain by 1 year',
            'ssl': 'SSL certificate (1 year)',
            'ssl-basic': 'SSL certificate (1 year)'
        };
        return descriptions[addonType] || 'Addon service';
    }

    // Get available addons for a parent item (requires availableAddonsDB to be set)
    getAvailableAddons(parentItem, existingAddons) {
        // Check if availableAddonsDB is defined globally
        if (typeof availableAddonsDB === 'undefined') {
            return [];
        }
        
        return availableAddonsDB.filter(addon => {
            const appliesToTypes = JSON.parse(addon.applies_to_product_types || '[]');
            const appliesToProduct = appliesToTypes.includes(parentItem.parent_product);
            const notAlreadyAdded = !existingAddons.find(existing => 
                existing.type === addon.addon_type || existing.type === addon.addon_slug
            );
            return appliesToProduct && notAlreadyAdded;
        }).map(addon => ({
            type: addon.addon_slug,
            name: addon.addon_name,
            description: addon.description,
            price: parseFloat(addon.price)
        }));
    }

    // Clear all items
    clearCart() {
        this.items = [];
        this.saveCart();
        this.updateDisplay();
        this.showNotification('Cart cleared', 'info');
    }

    // Calculate totals
    getSubtotal() {
        return this.items
            .filter(item => this.isPayableItem(item))
            .reduce((total, item) => total + (Number(item.price || 0) * Number(item.quantity || 1)), 0);
    }

    getTax(rate = 0) {
        return this.getSubtotal() * rate;
    }

    getTotal() {
        return this.getSubtotal() + this.getTax();
    }

    getItemCount() {
        return this.items
            .filter(item => this.isPayableItem(item))
            .reduce((count, item) => count + Number(item.quantity || 1), 0);
    }

    isPayableItem(item) {
        if (!item || typeof item !== 'object') return false;
        const marker = [
            item.status,
            item.cart_section,
            item.section,
            item.list,
            item.type,
            item.category
        ].map(value => String(value || '').toLowerCase()).join('|');
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

    // Storage operations
    saveCart() {
        localStorage.setItem('neuralCart', JSON.stringify(this.items));
        // Trigger storage event for other tabs/windows
        window.dispatchEvent(new Event('cartUpdated'));
    }

    loadCart() {
        try {
            const stored = localStorage.getItem('neuralCart');
            if (!stored) return [];
            
            const items = JSON.parse(stored);
            
            // Migrate old cart items to include missing properties
            const normalized = items.map(item => ({
                id: item.id,
                name: item.name,
                description: item.description || item.name,
                price: Number(item.price),
                type: item.type || 'service',
                quantity: item.quantity || 1,
                category: item.category || 'service',
                allows_addons: item.allows_addons || false,
                domain: item.domain || null,
                domain_name: item.domain_name || item.domain || null,
                primary_domain: item.primary_domain || item.domain || null,
                domain_option: item.domain_option || null,
                tld: item.tld || null,
                parent_product: item.parent_product || null,
                parent_id: item.parent_id || null,
                billing_cycle: item.billing_cycle || null,
                term_months: item.term_months || null,
                monthly_price: item.monthly_price || null,
                bundle_items: Array.isArray(item.bundle_items) ? item.bundle_items : null,
                product_config: item.product_config || null
            })).filter(item => item.id && item.name && Number.isFinite(item.price));

            // Remove malformed legacy entries (for example old domain buttons
            // that stored a TLD string in the price field).
            if (normalized.length !== items.length) {
                localStorage.setItem('neuralCart', JSON.stringify(normalized));
            }
            return normalized;
        } catch (error) {
            console.error('Error loading cart:', error);
            return [];
        }
    }

    // UI Updates
    updateDisplay() {
        this.updateCartBadge();
    }

    updateCartBadge() {
        const count = this.getItemCount();
        const badges = document.querySelectorAll('[id="cart-count"], [id="mobile-cart-count"], .cart-count, .cart-badge');
        
        badges.forEach(badge => {
            if (badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.classList.add('has-items');
                    badge.classList.remove('is-zero');
                } else {
                    badge.textContent = '';
                    badge.classList.remove('has-items');
                    badge.classList.add('is-zero');
                }
            }
        });
    }

    // Notification system
    showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        const colors = {
            success: 'rgba(0, 255, 255, 0.95)',
            error: 'rgba(255, 0, 100, 0.95)',
            info: 'rgba(100, 100, 255, 0.95)'
        };
        
        notification.style.cssText = `
            position: fixed;
            top: 90px;
            right: 20px;
            background: ${colors[type] || colors.success};
            color: #000;
            padding: 15px 25px;
            border-radius: 8px;
            font-weight: 600;
            z-index: 10000;
            box-shadow: 0 0 20px ${colors[type] || colors.success};
            animation: slideInFromRight 0.3s ease-out, fadeOut 0.3s ease-in 2.7s forwards;
            font-family: 'Rajdhani', sans-serif;
        `;
        notification.innerHTML = `<i class="fas fa-check-circle" style="margin-right: 10px;"></i>${message}`;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 3000);
    }

    // Format currency
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }
}

// Initialize cart system when DOM is ready
let neuralCart;

function initializeCart() {
    neuralCart = new NeuralCartSystem();
    
    // Make globally accessible
    window.neuralCart = neuralCart;
    window.cart = neuralCart; // Alias for cart.php compatibility
    
    // Backward compatibility with old ShoppingCart interface
    window.shoppingCart = {
        addItem: (item) => neuralCart.addItem(item),
        removeItem: (id) => neuralCart.removeItem(id),
        updateQuantity: (id, qty) => neuralCart.updateQuantity(id, qty),
        clearCart: () => neuralCart.clearCart(),
        getItems: () => neuralCart.items,
        getTotal: () => neuralCart.getTotal()
    };
    
    // Simple addToCart function
    window.addToCart = function(idOrItem, price, name, type) {
        let item;
        
        if (typeof idOrItem === 'object') {
            item = idOrItem;
        } else {
            item = {
                id: idOrItem,
                name: name || idOrItem,
                price: parseFloat(price),
                type: type || 'service'
            };
        }
        
        return neuralCart.addItem(item);
    };
    
    // Listen for cart updates from other tabs
    window.addEventListener('storage', (e) => {
        if (e.key === 'neuralCart') {
            neuralCart.items = neuralCart.loadCart();
            neuralCart.updateDisplay();
        }
    });
    
    window.addEventListener('cartUpdated', () => {
        neuralCart.items = neuralCart.loadCart();
        neuralCart.updateDisplay();
    });
    
    // Only log in development mode (remove in production)
    if (window.location.hostname === 'localhost' || window.location.hostname.includes('127.0.0.1')) {
        console.log('Neural Cart System initialized successfully');
    }
}

// Initialize immediately or on DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeCart);
} else {
    initializeCart();
}

function hivenestCartCountSnapshot() {
    if (window.neuralCart && typeof window.neuralCart.getItemCount === 'function') {
        return window.neuralCart.getItemCount();
    }

    if (window.cart && typeof window.cart.getItemCount === 'function') {
        return window.cart.getItemCount();
    }

    try {
        const stored = JSON.parse(localStorage.getItem('neuralCart') || '[]');
        return Array.isArray(stored)
            ? stored.filter(item => Number.isFinite(Number(item.price)) && Number(item.price) > 0).length
            : 0;
    } catch (error) {
        console.warn('Unable to read cart count snapshot:', error);
        return null;
    }
}

function hivenestResetCartButton(button) {
    if (!button) return;

    button.disabled = false;
    button.dataset.cartClicked = 'false';
    button.removeAttribute('aria-disabled');

    if (button.dataset.originalText) {
        button.textContent = button.dataset.originalText;
    }
}

window.hivenestResetCartButton = hivenestResetCartButton;
window.hivenestResetLastCartButton = function() {
    hivenestResetCartButton(window.hivenestLastCartButton);
};

// Prevent double-clicks and repeated taps on every shared pricing CTA.
document.addEventListener('click', function(event) {
    const button = event.target.closest('button');
    if (!button) return;

    const inlineAction = button.getAttribute('onclick') || '';
    const isCartButton = button.dataset.cartOnce === 'true' || /\badd\w*ToCart(?:AndCheckout)?\b/i.test(inlineAction);
    if (!isCartButton) return;
    const cartCountBefore = hivenestCartCountSnapshot();

    if (button.dataset.cartClicked === 'true') {
        event.preventDefault();
        event.stopImmediatePropagation();
        return;
    }

    window.hivenestLastCartButton = button;
    button.dataset.cartClicked = 'true';
    button.setAttribute('aria-disabled', 'true');
    button.dataset.originalText = button.textContent.trim();
    button.textContent = 'ADDING...';
    // Defer the disabled property until the current inline click handler has run.
    queueMicrotask(function() {
        button.disabled = true;
    });

    // If the inline handler rejects the click (for example Quantum Servers
    // requiring a primary domain first), no item is added. Re-enable the CTA so
    // the customer can enter the domain and select the package again.
    setTimeout(function() {
        if (button.dataset.cartClicked !== 'true') return;

        const cartCountAfter = hivenestCartCountSnapshot();
        if (cartCountBefore !== null && cartCountAfter !== null && cartCountAfter <= cartCountBefore) {
            hivenestResetCartButton(button);
        } else {
            button.textContent = 'ADDED TO CART';
        }
    }, 250);
}, true);

// Add required animations
if (!document.querySelector('#cart-animations')) {
    const style = document.createElement('style');
    style.id = 'cart-animations';
    style.textContent = `
        @keyframes slideInFromRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }
    `;
    document.head.appendChild(style);
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { NeuralCartSystem };
}
