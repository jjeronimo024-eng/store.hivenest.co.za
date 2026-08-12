// HiveNest.co.za - Shopping Cart System
// Advanced Shopping Cart for ResellerClub Integration

class ShoppingCartAdvanced {
    constructor() {
        this.items = this.loadCart();
        this.currency = 'USD';
        this.taxRate = 0.15; // 15% VAT for South Africa
        this.init();
    }

    init() {
        this.createCartUI();
        this.updateCartDisplay();
        this.setupEventListeners();
        this.loadSavedCustomerInfo();
    }

    // Create cart UI elements
    createCartUI() {
        // Create cart icon in navbar
        this.createCartIcon();
        
        // Create cart sidebar
        this.createCartSidebar();
        
        // Create cart modal for checkout
        this.createCheckoutModal();
    }

    createCartIcon() {
        const navbar = document.querySelector('.navbar-nav');
        if (!navbar) return;

        const cartIcon = document.createElement('li');
        cartIcon.innerHTML = `
            <a href="#" id="cart-toggle" class="relative">
                <i class="fas fa-shopping-cart text-lg"></i>
                <span id="cart-badge" class="cart-badge absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-content-center">0</span>
            </a>
        `;
        
        navbar.insertBefore(cartIcon, navbar.lastElementChild);
    }

    createCartSidebar() {
        const cartSidebar = document.createElement('div');
        cartSidebar.id = 'cart-sidebar';
        cartSidebar.className = 'cart-sidebar';
        cartSidebar.innerHTML = `
            <div class="cart-header">
                <h3>Your Cart</h3>
                <button id="cart-close" class="cart-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="cart-content">
                <div id="cart-items" class="cart-items"></div>
                <div class="cart-summary">
                    <div class="cart-totals">
                        <div class="total-line">
                            <span>Subtotal:</span>
                            <span id="cart-subtotal">$0.00</span>
                        </div>
                        <div class="total-line">
                            <span>Tax (15%):</span>
                            <span id="cart-tax">$0.00</span>
                        </div>
                        <div class="total-line total-final">
                            <span>Total:</span>
                            <span id="cart-total">$0.00</span>
                        </div>
                    </div>
                    <div class="cart-actions">
                        <button id="checkout-btn" class="btn btn-primary w-full">
                            <i class="fas fa-credit-card mr-2"></i>
                            Proceed to Checkout
                        </button>
                        <button id="clear-cart" class="btn btn-outline w-full mt-2">
                            Clear Cart
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(cartSidebar);
        this.addCartSidebarStyles();
    }

    createCheckoutModal() {
        const modal = document.createElement('div');
        modal.id = 'checkout-modal';
        modal.className = 'checkout-modal';
        modal.innerHTML = `
            <div class="modal-backdrop"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Checkout</h2>
                    <button id="modal-close" class="modal-close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="checkout-form">
                        <div class="checkout-steps">
                            <div class="step active" data-step="1">
                                <span class="step-number">1</span>
                                <span class="step-title">Customer Information</span>
                            </div>
                            <div class="step" data-step="2">
                                <span class="step-number">2</span>
                                <span class="step-title">Payment Details</span>
                            </div>
                            <div class="step" data-step="3">
                                <span class="step-number">3</span>
                                <span class="step-title">Review & Complete</span>
                            </div>
                        </div>

                        <!-- Step 1: Customer Information -->
                        <div class="checkout-step-content" data-content="1">
                            <h3>Customer Information</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="first-name">First Name *</label>
                                    <input type="text" id="first-name" name="firstName" required>
                                </div>
                                <div class="form-group">
                                    <label for="last-name">Last Name *</label>
                                    <input type="text" id="last-name" name="lastName" required>
                                </div>
                                <div class="form-group full-width">
                                    <label for="email">Email Address *</label>
                                    <input type="email" id="email" name="email" required>
                                </div>
                                <div class="form-group">
                                    <label for="phone">Phone Number *</label>
                                    <input type="tel" id="phone" name="phone" required>
                                </div>
                                <div class="form-group">
                                    <label for="company">Company Name</label>
                                    <input type="text" id="company" name="company">
                                </div>
                                <div class="form-group full-width">
                                    <label for="address">Address *</label>
                                    <input type="text" id="address" name="address" required>
                                </div>
                                <div class="form-group">
                                    <label for="city">City *</label>
                                    <input type="text" id="city" name="city" required>
                                </div>
                                <div class="form-group">
                                    <label for="country">Country *</label>
                                    <select id="country" name="country" required>
                                        <option value="ZA">South Africa</option>
                                        <option value="US">United States</option>
                                        <option value="GB">United Kingdom</option>
                                        <option value="AU">Australia</option>
                                        <option value="CA">Canada</option>
                                    </select>
                                </div>
                            </div>
                            <div class="step-actions">
                                <button type="button" class="btn btn-primary" onclick="cart.nextStep()">
                                    Next Step <i class="fas fa-arrow-right ml-2"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Payment Details -->
                        <div class="checkout-step-content" data-content="2" style="display: none;">
                            <h3>Payment Information</h3>
                            <div class="payment-methods">
                                <div class="payment-method active" data-method="card">
                                    <i class="fas fa-credit-card"></i>
                                    <span>Credit/Debit Card</span>
                                </div>
                                <div class="payment-method" data-method="paypal">
                                    <i class="fab fa-paypal"></i>
                                    <span>PayPal</span>
                                </div>
                            </div>
                            
                            <div id="card-payment" class="payment-form">
                                <div class="form-group">
                                    <label for="card-number">Card Number *</label>
                                    <input type="text" id="card-number" name="cardNumber" placeholder="1234 5678 9012 3456" required>
                                </div>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="expiry">Expiry Date *</label>
                                        <input type="text" id="expiry" name="expiry" placeholder="MM/YY" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="cvv">CVV *</label>
                                        <input type="text" id="cvv" name="cvv" placeholder="123" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="card-name">Name on Card *</label>
                                    <input type="text" id="card-name" name="cardName" required>
                                </div>
                            </div>
                            
                            <div id="paypal-payment" class="payment-form" style="display: none;">
                                <div id="paypal-button-container"></div>
                            </div>
                            
                            <div class="step-actions">
                                <button type="button" class="btn btn-outline" onclick="cart.previousStep()">
                                    <i class="fas fa-arrow-left mr-2"></i> Previous
                                </button>
                                <button type="button" class="btn btn-primary" onclick="cart.nextStep()">
                                    Review Order <i class="fas fa-arrow-right ml-2"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 3: Review & Complete -->
                        <div class="checkout-step-content" data-content="3" style="display: none;">
                            <h3>Order Review</h3>
                            <div id="order-summary" class="order-summary"></div>
                            
                            <div class="terms-acceptance">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="accept-terms" required>
                                    <span class="checkmark"></span>
                                    I agree to the <a href="legal/terms-of-service.html" target="_blank">Terms of Service</a> and <a href="legal/privacy-policy.html" target="_blank">Privacy Policy</a>
                                </label>
                            </div>
                            
                            <div class="step-actions">
                                <button type="button" class="btn btn-outline" onclick="cart.previousStep()">
                                    <i class="fas fa-arrow-left mr-2"></i> Previous
                                </button>
                                <button type="submit" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-check mr-2"></i> Complete Order
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        this.addCheckoutModalStyles();
    }

    // Event listeners
    setupEventListeners() {
        // Cart toggle
        document.addEventListener('click', (e) => {
            if (e.target.closest('#cart-toggle')) {
                e.preventDefault();
                this.toggleCart();
            }
            
            if (e.target.closest('#cart-close')) {
                this.closeCart();
            }
            
            if (e.target.closest('#checkout-btn')) {
                this.openCheckout();
            }
            
            if (e.target.closest('#clear-cart')) {
                this.clearCart();
            }
            
            if (e.target.closest('#modal-close')) {
                this.closeCheckout();
            }
            
            if (e.target.classList.contains('modal-backdrop')) {
                this.closeCheckout();
            }
        });

        // Payment method selection
        document.addEventListener('click', (e) => {
            if (e.target.closest('.payment-method')) {
                this.selectPaymentMethod(e.target.closest('.payment-method'));
            }
        });

        // Form submission
        document.addEventListener('submit', (e) => {
            if (e.target.id === 'checkout-form') {
                e.preventDefault();
                this.processOrder();
            }
        });
    }

    // Cart operations
    addItem(product) {
        const existingItem = this.items.find(item => item.id === product.id);
        
        if (existingItem) {
            existingItem.quantity += 1;
        } else {
            this.items.push({
                id: product.id,
                name: product.name,
                price: product.price,
                category: product.category,
                period: product.period || 'monthly',
                quantity: 1,
                features: product.features || []
            });
        }
        
        this.saveCart();
        this.updateCartDisplay();
        this.showNotification('Item added to cart!', 'success');
    }

    removeItem(productId) {
        this.items = this.items.filter(item => item.id !== productId);
        this.saveCart();
        this.updateCartDisplay();
    }

    updateQuantity(productId, quantity) {
        const item = this.items.find(item => item.id === productId);
        if (item) {
            item.quantity = Math.max(0, quantity);
            if (item.quantity === 0) {
                this.removeItem(productId);
            } else {
                this.saveCart();
                this.updateCartDisplay();
            }
        }
    }

    clearCart() {
        this.items = [];
        this.saveCart();
        this.updateCartDisplay();
        this.showNotification('Cart cleared', 'info');
    }

    // Calculations
    getSubtotal() {
        return this.items.reduce((total, item) => total + (item.price * item.quantity), 0);
    }

    getTax() {
        return this.getSubtotal() * this.taxRate;
    }

    getTotal() {
        return this.getSubtotal() + this.getTax();
    }

    getItemCount() {
        return this.items.reduce((count, item) => count + item.quantity, 0);
    }

    // UI Updates
    updateCartDisplay() {
        this.updateCartBadge();
        this.updateCartItems();
        this.updateCartTotals();
    }

    updateCartBadge() {
        const badge = document.getElementById('cart-badge');
        if (badge) {
            const count = this.getItemCount();
            badge.textContent = count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }
    }

    updateCartItems() {
        const cartItems = document.getElementById('cart-items');
        if (!cartItems) return;

        if (this.items.length === 0) {
            cartItems.innerHTML = `
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">Your cart is empty</p>
                    <a href="#services" class="btn btn-primary mt-4">Browse Services</a>
                </div>
            `;
            return;
        }

        cartItems.innerHTML = this.items.map(item => `
            <div class="cart-item" data-id="${item.id}">
                <div class="item-info">
                    <h4>${item.name}</h4>
                    <p class="item-price">${this.formatCurrency(item.price)}/${item.period}</p>
                </div>
                <div class="item-controls">
                    <div class="quantity-controls">
                        <button onclick="cart.updateQuantity('${item.id}', ${item.quantity - 1})">-</button>
                        <span>${item.quantity}</span>
                        <button onclick="cart.updateQuantity('${item.id}', ${item.quantity + 1})">+</button>
                    </div>
                    <button class="remove-item" onclick="cart.removeItem('${item.id}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');
    }

    updateCartTotals() {
        const subtotal = document.getElementById('cart-subtotal');
        const tax = document.getElementById('cart-tax');
        const total = document.getElementById('cart-total');

        if (subtotal) subtotal.textContent = this.formatCurrency(this.getSubtotal());
        if (tax) tax.textContent = this.formatCurrency(this.getTax());
        if (total) total.textContent = this.formatCurrency(this.getTotal());
    }

    // Cart UI controls
    toggleCart() {
        const sidebar = document.getElementById('cart-sidebar');
        sidebar.classList.toggle('open');
    }

    closeCart() {
        const sidebar = document.getElementById('cart-sidebar');
        sidebar.classList.remove('open');
    }

    // Checkout process
    openCheckout() {
        if (this.items.length === 0) {
            this.showNotification('Your cart is empty', 'error');
            return;
        }
        
        const modal = document.getElementById('checkout-modal');
        modal.classList.add('active');
        this.updateOrderSummary();
    }

    closeCheckout() {
        const modal = document.getElementById('checkout-modal');
        modal.classList.remove('active');
        this.resetCheckoutSteps();
    }

    nextStep() {
        const currentStep = document.querySelector('.step.active');
        const currentContent = document.querySelector('.checkout-step-content[style*="block"]') || 
                              document.querySelector('.checkout-step-content:not([style*="none"])');
        
        if (!this.validateCurrentStep()) return;
        
        const nextStepNumber = parseInt(currentStep.dataset.step) + 1;
        const nextStep = document.querySelector(`.step[data-step="${nextStepNumber}"]`);
        const nextContent = document.querySelector(`.checkout-step-content[data-content="${nextStepNumber}"]`);
        
        if (nextStep && nextContent) {
            currentStep.classList.remove('active');
            currentContent.style.display = 'none';
            
            nextStep.classList.add('active');
            nextContent.style.display = 'block';
            
            if (nextStepNumber === 3) {
                this.updateOrderSummary();
            }
        }
    }

    previousStep() {
        const currentStep = document.querySelector('.step.active');
        const currentContent = document.querySelector('.checkout-step-content[style*="block"]') || 
                              document.querySelector('.checkout-step-content:not([style*="none"])');
        
        const prevStepNumber = parseInt(currentStep.dataset.step) - 1;
        const prevStep = document.querySelector(`.step[data-step="${prevStepNumber}"]`);
        const prevContent = document.querySelector(`.checkout-step-content[data-content="${prevStepNumber}"]`);
        
        if (prevStep && prevContent) {
            currentStep.classList.remove('active');
            currentContent.style.display = 'none';
            
            prevStep.classList.add('active');
            prevContent.style.display = 'block';
        }
    }

    validateCurrentStep() {
        const currentStep = document.querySelector('.step.active');
        const stepNumber = parseInt(currentStep.dataset.step);
        
        switch (stepNumber) {
            case 1:
                return this.validateCustomerInfo();
            case 2:
                return this.validatePaymentInfo();
            case 3:
                return this.validateTerms();
            default:
                return true;
        }
    }

    validateCustomerInfo() {
        const required = ['first-name', 'last-name', 'email', 'phone', 'address', 'city', 'country'];
        let isValid = true;
        
        required.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (!field.value.trim()) {
                field.classList.add('error');
                isValid = false;
            } else {
                field.classList.remove('error');
            }
        });
        
        if (!isValid) {
            this.showNotification('Please fill in all required fields', 'error');
        }
        
        return isValid;
    }

    validatePaymentInfo() {
        const activeMethod = document.querySelector('.payment-method.active');
        
        if (activeMethod.dataset.method === 'card') {
            const required = ['card-number', 'expiry', 'cvv', 'card-name'];
            let isValid = true;
            
            required.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (!field.value.trim()) {
                    field.classList.add('error');
                    isValid = false;
                } else {
                    field.classList.remove('error');
                }
            });
            
            if (!isValid) {
                this.showNotification('Please fill in all payment details', 'error');
            }
            
            return isValid;
        }
        
        return true; // PayPal validation handled separately
    }

    validateTerms() {
        const termsCheckbox = document.getElementById('accept-terms');
        if (!termsCheckbox.checked) {
            this.showNotification('Please accept the terms and conditions', 'error');
            return false;
        }
        return true;
    }

    selectPaymentMethod(method) {
        document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('active'));
        method.classList.add('active');
        
        const cardForm = document.getElementById('card-payment');
        const paypalForm = document.getElementById('paypal-payment');
        
        if (method.dataset.method === 'card') {
            cardForm.style.display = 'block';
            paypalForm.style.display = 'none';
        } else {
            cardForm.style.display = 'none';
            paypalForm.style.display = 'block';
        }
    }

    updateOrderSummary() {
        const summary = document.getElementById('order-summary');
        if (!summary) return;
        
        summary.innerHTML = `
            <div class="order-items">
                ${this.items.map(item => `
                    <div class="order-item">
                        <div class="item-details">
                            <h4>${item.name}</h4>
                            <p>Quantity: ${item.quantity}</p>
                            <p>${this.formatCurrency(item.price)}/${item.period}</p>
                        </div>
                        <div class="item-total">
                            ${this.formatCurrency(item.price * item.quantity)}
                        </div>
                    </div>
                `).join('')}
            </div>
            <div class="order-totals">
                <div class="total-line">
                    <span>Subtotal:</span>
                    <span>${this.formatCurrency(this.getSubtotal())}</span>
                </div>
                <div class="total-line">
                    <span>Tax (15%):</span>
                    <span>${this.formatCurrency(this.getTax())}</span>
                </div>
                <div class="total-line final">
                    <span><strong>Total:</strong></span>
                    <span><strong>${this.formatCurrency(this.getTotal())}</strong></span>
                </div>
            </div>
        `;
    }

    resetCheckoutSteps() {
        document.querySelectorAll('.step').forEach(step => step.classList.remove('active'));
        document.querySelectorAll('.checkout-step-content').forEach(content => {
            content.style.display = 'none';
        });
        
        document.querySelector('.step[data-step="1"]').classList.add('active');
        document.querySelector('.checkout-step-content[data-content="1"]').style.display = 'block';
    }

    // Order processing
    async processOrder() {
        try {
            this.showNotification('Processing your order...', 'info');
            
            const orderData = this.collectOrderData();
            
            // Submit to backend
            const response = await window.hiveNestAPI.submitOrder(orderData);
            
            if (response.success) {
                this.showNotification('Order placed successfully! Check your email for confirmation.', 'success');
                this.clearCart();
                this.closeCheckout();
                
                // Redirect to thank you page
                setTimeout(() => {
                    window.location.href = 'thank-you.html';
                }, 2000);
            } else {
                throw new Error(response.message || 'Order processing failed');
            }
            
        } catch (error) {
            console.error('Order processing error:', error);
            this.showNotification('Failed to process order. Please try again.', 'error');
        }
    }

    collectOrderData() {
        const form = document.getElementById('checkout-form');
        const formData = new FormData(form);
        
        return {
            customer: Object.fromEntries(formData.entries()),
            items: this.items,
            totals: {
                subtotal: this.getSubtotal(),
                tax: this.getTax(),
                total: this.getTotal()
            },
            paymentMethod: document.querySelector('.payment-method.active').dataset.method,
            currency: this.currency
        };
    }

    // Utility functions
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: this.currency
        }).format(amount);
    }

    saveCart() {
        localStorage.setItem('hivenest_cart', JSON.stringify(this.items));
    }

    loadCart() {
        try {
            return JSON.parse(localStorage.getItem('hivenest_cart') || '[]');
        } catch {
            return [];
        }
    }

    loadSavedCustomerInfo() {
        try {
            const saved = JSON.parse(localStorage.getItem('hivenest_customer_info') || '{}');
            Object.keys(saved).forEach(key => {
                const field = document.getElementById(key);
                if (field) field.value = saved[key];
            });
        } catch {}
    }

    saveCustomerInfo() {
        const form = document.getElementById('checkout-form');
        const formData = new FormData(form);
        const customerInfo = Object.fromEntries(formData.entries());
        localStorage.setItem('hivenest_customer_info', JSON.stringify(customerInfo));
    }

    showNotification(message, type = 'info') {
        if (window.hiveNestApp) {
            window.hiveNestApp.showMessage(type, message);
        } else {
            console.warn(message);
        }
    }

    // Styles injection
    addCartSidebarStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .cart-sidebar {
                position: fixed;
                top: 0;
                right: -400px;
                width: 400px;
                height: 100vh;
                background: white;
                box-shadow: -5px 0 15px rgba(0,0,0,0.1);
                z-index: 10000;
                transition: right 0.3s ease;
                display: flex;
                flex-direction: column;
            }
            
            .cart-sidebar.open { right: 0; }
            
            .cart-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1.5rem;
                border-bottom: 1px solid #e5e7eb;
            }
            
            .cart-close {
                background: none;
                border: none;
                font-size: 1.5rem;
                cursor: pointer;
                color: #6b7280;
            }
            
            .cart-content {
                flex: 1;
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }
            
            .cart-items {
                flex: 1;
                overflow-y: auto;
                padding: 1rem;
            }
            
            .cart-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1rem;
                border-bottom: 1px solid #f3f4f6;
            }
            
            .cart-summary {
                padding: 1.5rem;
                border-top: 1px solid #e5e7eb;
                background: #f9fafb;
            }
            
            .empty-cart {
                text-align: center;
                padding: 3rem 1rem;
            }
            
            @media (max-width: 480px) {
                .cart-sidebar { width: 100vw; right: -100vw; }
            }
        `;
        document.head.appendChild(style);
    }

    addCheckoutModalStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .checkout-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 10001;
                display: none;
            }
            
            .checkout-modal.active { display: flex; }
            
            .modal-backdrop {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
            }
            
            .modal-content {
                position: relative;
                margin: auto;
                width: 90%;
                max-width: 800px;
                max-height: 90vh;
                background: white;
                border-radius: 12px;
                overflow: hidden;
                display: flex;
                flex-direction: column;
            }
            
            .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1.5rem;
                border-bottom: 1px solid #e5e7eb;
            }
            
            .modal-body {
                flex: 1;
                overflow-y: auto;
                padding: 2rem;
            }
            
            .checkout-steps {
                display: flex;
                justify-content: center;
                margin-bottom: 2rem;
            }
            
            .step {
                display: flex;
                align-items: center;
                margin: 0 1rem;
                opacity: 0.5;
            }
            
            .step.active { opacity: 1; }
            
            .step-number {
                width: 30px;
                height: 30px;
                border-radius: 50%;
                background: #e5e7eb;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 0.5rem;
                font-weight: bold;
            }
            
            .step.active .step-number {
                background: var(--primary-blue);
                color: white;
            }
            
            .form-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }
            
            .form-group.full-width {
                grid-column: 1 / -1;
            }
            
            .payment-methods {
                display: flex;
                gap: 1rem;
                margin-bottom: 2rem;
            }
            
            .payment-method {
                flex: 1;
                padding: 1rem;
                border: 2px solid #e5e7eb;
                border-radius: 8px;
                text-align: center;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            
            .payment-method.active {
                border-color: var(--primary-blue);
                background: rgba(59, 130, 246, 0.1);
            }
            
            .step-actions {
                display: flex;
                gap: 1rem;
                justify-content: flex-end;
                margin-top: 2rem;
                padding-top: 2rem;
                border-top: 1px solid #e5e7eb;
            }
            
            @media (max-width: 768px) {
                .form-grid { grid-template-columns: 1fr; }
                .payment-methods { flex-direction: column; }
                .step-actions { flex-direction: column-reverse; }
            }
        `;
        document.head.appendChild(style);
    }
}

// Initialize shopping cart when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.cart = new ShoppingCartAdvanced();
});

// Export for other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ShoppingCartAdvanced };
}
