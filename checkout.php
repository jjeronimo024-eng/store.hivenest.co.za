<?php
/**
 * Secure Checkout Page - PayPal Integration
 * 
 * Following PayPal's official integration guide:
 * https://developer.paypal.com/docs/checkout/standard/integrate/
 * 
 * Features:
 * - PayPal JavaScript SDK integration
 * - Client-side order creation
 * - Server-side payment capture
 * - Error handling and user feedback
 * - App Switch support for mobile
 * - Customizable button styling
 */

require_once __DIR__ . '/utilities/customer_session.php';
hivenest_customer_session_configure();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/access/dbconfig.php';
$checkout_customer_id = (int) ($_SESSION['customer_id'] ?? 0);
$checkout_authenticated = $checkout_customer_id > 0;
$checkout_customer_email = (string) ($_SESSION['customer_email'] ?? '');
$checkout_email_verified = false;
$checkout_loyalty_discount = 0.0;
$checkout_loyalty_tier = 1;
$checkout_invoice_number = trim((string)($_GET['invoice'] ?? ''));
$checkout_invoice = null;

if ($checkout_authenticated) {
    $checkout_email_verified = (int) ($_SESSION['customer_email_verified'] ?? 0) === 1;
    $checkout_db = hivenest_db();
    if ($checkout_db) {
        $checkout_stmt = $checkout_db->prepare('SELECT email,email_verified FROM customers WHERE id = :id AND status = "active" LIMIT 1');
        $checkout_stmt->execute(['id' => $checkout_customer_id]);
        $checkout_customer = $checkout_stmt->fetch();
        if ($checkout_customer) {
            $checkout_customer_email = (string) $checkout_customer['email'];
            $checkout_email_verified = (int) $checkout_customer['email_verified'] === 1;
            $_SESSION['customer_email'] = $checkout_customer_email;
            $_SESSION['customer_email_verified'] = $checkout_email_verified ? 1 : 0;
            try {
                require_once __DIR__ . '/utilities/customer_loyalty.php';
                $checkout_loyalty = hivenest_customer_loyalty($checkout_db, $checkout_customer_id, false);
                $checkout_loyalty_discount = (float)($checkout_loyalty['discount_percent'] ?? 0);
                $checkout_loyalty_tier = (int)($checkout_loyalty['tier'] ?? 1);
            } catch (Throwable $checkout_loyalty_error) {
                error_log('Checkout loyalty preview failed: ' . $checkout_loyalty_error->getMessage());
            }
            if ($checkout_invoice_number !== '') {
                $invoiceStmt = $checkout_db->prepare("
                    SELECT id, order_number, payment_status, order_status, subtotal,
                           tax_amount, discount_amount, total_amount, currency
                    FROM orders
                    WHERE customer_id = :customer_id
                      AND order_number = :order_number
                      AND payment_status IN ('pending', 'failed')
                      AND order_status NOT IN ('cancelled', 'refunded')
                    LIMIT 1
                ");
                $invoiceStmt->execute([
                    'customer_id' => $checkout_customer_id,
                    'order_number' => $checkout_invoice_number,
                ]);
                $invoiceOrder = $invoiceStmt->fetch();
                if ($invoiceOrder && strtoupper((string)$invoiceOrder['currency']) === 'USD') {
                    $invoiceItemsStmt = $checkout_db->prepare("
                        SELECT product_name, domain_name, quantity, line_total, billing_cycle
                        FROM order_items
                        WHERE order_id = :order_id
                        ORDER BY id ASC
                    ");
                    $invoiceItemsStmt->execute(['order_id' => (int)$invoiceOrder['id']]);
                    $checkout_invoice = [
                        'order_number' => (string)$invoiceOrder['order_number'],
                        'subtotal' => (float)$invoiceOrder['subtotal'],
                        'tax_amount' => (float)$invoiceOrder['tax_amount'],
                        'discount_amount' => (float)$invoiceOrder['discount_amount'],
                        'total' => (float)$invoiceOrder['total_amount'],
                        'currency' => 'USD',
                        'items' => array_map(static function (array $item): array {
                            $quantity = max(1, (int)$item['quantity']);
                            return [
                                'id' => 'invoice-line-' . md5((string)$item['product_name'] . (string)$item['domain_name']),
                                'name' => (string)$item['product_name'],
                                'domain' => $item['domain_name'] !== null ? (string)$item['domain_name'] : '',
                                'quantity' => $quantity,
                                'price' => round((float)$item['line_total'] / $quantity, 2),
                                'billing_cycle' => (string)$item['billing_cycle'],
                            ];
                        }, $invoiceItemsStmt->fetchAll() ?: []),
                    ];
                }
            }
        }
    }
}

$checkout_can_pay = $checkout_authenticated && $checkout_email_verified;
$checkout_can_pay_js = $checkout_can_pay ? 'true' : 'false';
$checkout_csrf_token = $checkout_authenticated ? hivenest_customer_csrf_token() : '';

// Page variables
$current_page = 'checkout';
$page_title = 'Secure Checkout - Complete Your Order | HiveNest Matrix';
$page_description = 'Complete your digital transformation with secure checkout powered by quantum encryption.';
$page_keywords = 'checkout, secure payment, order completion, hivenest checkout';

// Load only the public PayPal client ID from the protected environment file.
// PAYPAL_CLIENT_SECRET is used exclusively by /api/paypal.php.
$paypal_values = [];
$paypal_env_file = __DIR__ . '/Backend/.env';
$paypal_lines = is_readable($paypal_env_file) ? (@file($paypal_env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [];
foreach ($paypal_lines as $paypal_line) {
    $paypal_line = trim($paypal_line);
    if ($paypal_line === '' || $paypal_line[0] === '#' || strpos($paypal_line, '=') === false) continue;
    [$paypal_key, $paypal_value] = explode('=', $paypal_line, 2);
    $paypal_values[trim($paypal_key)] = trim(trim($paypal_value), "\"'");
}
$paypal_client_id = getenv('PAYPAL_CLIENT_ID') ?: ($paypal_values['PAYPAL_CLIENT_ID'] ?? '');
$paypal_mode = getenv('PAYPAL_MODE') ?: ($paypal_values['PAYPAL_MODE'] ?? 'sandbox');
$buyer_country = 'ZA';
$currency = 'USD';
// Use current domain for backend API (adjust path as needed for your server setup)
$backend_api_url = '//' . $_SERVER['HTTP_HOST'];
$checkout_invoice_json = json_encode(
    $checkout_invoice,
    JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?: 'null';
$checkout_invoice_requested_js = $checkout_invoice_number !== '' ? 'true' : 'false';

// Page-specific JavaScript
$page_scripts = <<<JAVASCRIPT
/**
 * PayPal Checkout Integration - Updated Version
 * Using new PayPal SDK configuration
 */
class PayPalCheckout {
    constructor() {
        this.rawCart = JSON.parse(localStorage.getItem('neuralCart') || '[]');
        this.cart = this.rawCart.filter(item => this.isPayableCartItem(item));
        this.invoice = {$checkout_invoice_json};
        this.invoiceRequested = {$checkout_invoice_requested_js};
        this.invoiceNumber = this.invoice ? String(this.invoice.order_number || '') : '';
        if (this.invoice && Array.isArray(this.invoice.items)) {
            this.cart = this.invoice.items;
        }
        this.backendApiUrl = '{$backend_api_url}';
        this.csrfToken = '{$checkout_csrf_token}';
        this.paypalOrderId = null;
        this.loyaltyDiscountPercent = {$checkout_loyalty_discount};
        this.loyaltyTier = {$checkout_loyalty_tier};
        this.promoCode = '';
        this.serverTotal = null;
        
        // Redirect to cart if empty
        if (this.invoiceRequested && !this.invoiceNumber) {
            this.cart = [];
            this.displayOrderSummary();
            this.showMessage('error', 'This invoice is unavailable, already paid, cancelled, or does not belong to this account.');
            return;
        }

        if (this.cart.length === 0 && !this.invoiceNumber) {
            window.location.href = 'cart.php';
            return;
        }
        
        if (!{$checkout_can_pay_js}) {
            this.displayOrderSummary();
            return;
        }

        // Initialize checkout
        this.displayOrderSummary();
        if (this.invoice) {
            this.applyServerPricing({
                subtotal: this.invoice.subtotal,
                loyalty_discount_amount: this.invoice.discount_amount,
                promotion_discount_amount: 0,
                discount_percent: 0,
                loyalty_tier: this.loyaltyTier,
                total: this.invoice.total
            });
            const promotionBox = document.querySelector('.promotion-box');
            if (promotionBox) promotionBox.style.display = 'none';
        }
        this.loadPayPalSDK();
    }
    
    /**
     * Display order summary from cart
     */
    displayOrderSummary() {
        const summaryContainer = document.getElementById('order-summary-items');
        const subtotalElement = document.getElementById('order-subtotal');
        const taxElement = document.getElementById('order-tax');
        const discountElement = document.getElementById('order-discount');
        const discountLabelElement = document.getElementById('order-discount-label');
        const promotionRow = document.getElementById('order-promotion-row');
        const totalElement = document.getElementById('order-total');
        
        let subtotal = 0;
        
        summaryContainer.innerHTML = this.cart.map(item => {
            const quantity = Number(item.quantity || 1);
            const itemTotal = Number(item.price || 0) * quantity;
            subtotal += itemTotal;
            
            return '<div class="order-item" data-testid="order-item-' + item.id + '">' +
                '<div>' +
                '<h5 class="item-name">' + this.escapeHtml(item.name) + '</h5>' +
                '<p class="item-details">Quantity: ' + quantity + '</p>' +
                '</div>' +
                '<div class="item-price">' +
                '<p data-usd-price="' + itemTotal.toFixed(2) + '">$' + itemTotal.toFixed(2) + '</p>' +
                '</div>' +
                '</div>';
        }).join('');
        
        const tax = subtotal * 0.00; // Configure tax rate as needed
        const discount = subtotal * (this.loyaltyDiscountPercent / 100);
        const total = subtotal + tax - discount;
        
        subtotalElement.dataset.usdPrice = subtotal.toFixed(2);
        taxElement.dataset.usdPrice = tax.toFixed(2);
        discountElement.dataset.usdPrice = discount.toFixed(2);
        discountElement.dataset.currencySign = '-1';
        totalElement.dataset.usdPrice = total.toFixed(2);
        subtotalElement.textContent = '$' + subtotal.toFixed(2);
        taxElement.textContent = '$' + tax.toFixed(2);
        discountElement.textContent = '-$' + discount.toFixed(2);
        promotionRow.style.display = 'none';
        discountLabelElement.textContent = `Loyalty Discount (Tier \${this.loyaltyTier} · \${this.loyaltyDiscountPercent.toFixed(0)}%):`;
        totalElement.textContent = '$' + total.toFixed(2);
        if (window.HiveNestCurrency) window.HiveNestCurrency.apply();
    }

    applyServerPricing(pricing) {
        if (!pricing || typeof pricing !== 'object') return;
        this.loyaltyDiscountPercent = Number(pricing.discount_percent || 0);
        this.loyaltyTier = Number(pricing.loyalty_tier || 1);
        this.serverTotal = Number(pricing.total || 0);
        const subtotalElement = document.getElementById('order-subtotal');
        const discountElement = document.getElementById('order-discount');
        const promotionRow = document.getElementById('order-promotion-row');
        const promotionLabel = document.getElementById('order-promotion-label');
        const promotionDiscount = document.getElementById('order-promotion-discount');
        const totalElement = document.getElementById('order-total');
        subtotalElement.dataset.usdPrice = Number(pricing.subtotal || 0).toFixed(2);
        discountElement.dataset.usdPrice = Number(pricing.loyalty_discount_amount || 0).toFixed(2);
        discountElement.dataset.currencySign = '-1';
        totalElement.dataset.usdPrice = Number(pricing.total || 0).toFixed(2);
        subtotalElement.textContent = '$' + Number(pricing.subtotal || 0).toFixed(2);
        discountElement.textContent = '-$' + Number(pricing.loyalty_discount_amount || 0).toFixed(2);
        document.getElementById('order-discount-label').textContent =
            `Loyalty Discount (Tier \${this.loyaltyTier} · \${this.loyaltyDiscountPercent.toFixed(0)}%):`;
        const promotionAmount = Number(pricing.promotion_discount_amount || 0);
        const promotionCode = pricing.promotion && pricing.promotion.code
            ? String(pricing.promotion.code)
            : '';
        promotionRow.style.display = promotionAmount > 0 ? 'flex' : 'none';
        promotionLabel.textContent = promotionCode ? 'Promotion (' + promotionCode + '):' : 'Promotion:';
        promotionDiscount.dataset.usdPrice = promotionAmount.toFixed(2);
        promotionDiscount.dataset.currencySign = '-1';
        promotionDiscount.textContent = '-$' + promotionAmount.toFixed(2);
        totalElement.textContent = '$' + Number(pricing.total || 0).toFixed(2);
        if (window.HiveNestCurrency) window.HiveNestCurrency.apply();
    }

    checkoutCartPayload() {
        return this.cart.map(item => ({
            id: item.id,
            name: item.name,
            quantity: (item.quantity || 1).toString(),
            description: item.description || item.name,
            type: item.type || '',
            domain: item.domain || item.domain_name || item.primary_domain || '',
            domain_name: item.domain_name || item.domain || item.primary_domain || '',
            primary_domain: item.primary_domain || item.domain || item.domain_name || '',
            domain_option: item.domain_option || '',
            term_months: item.term_months || item.product_config?.term_months || '',
            monthly_price: item.monthly_price || item.product_config?.monthly_price || '',
            bundle_items: item.bundle_items || item.product_config?.bundle_items || null,
            product_config: item.product_config || null,
            tld: item.tld || '',
            category: item.category || '',
            status: item.status || '',
            cart_section: item.cart_section || ''
        }));
    }

    async applyPromotion() {
        if (this.invoiceNumber) return;
        const input = document.getElementById('promotion-code');
        const button = document.getElementById('apply-promotion-btn');
        const feedback = document.getElementById('promotion-feedback');
        const requestedCode = String(input.value || '').trim().toUpperCase();
        const previousCode = this.promoCode;
        input.value = requestedCode;
        button.disabled = true;
        feedback.className = 'promotion-feedback';
        feedback.textContent = requestedCode ? 'Checking promotion code…' : 'Removing promotion code…';

        try {
            const response = await fetch('/api/paypal.php?action=quote', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken
                },
                body: JSON.stringify({
                    cart: this.checkoutCartPayload(),
                    promo_code: requestedCode
                })
            });
            const responseText = await response.text();
            let result = {};
            try {
                result = responseText ? JSON.parse(responseText) : {};
            } catch (parseError) {
                throw new Error('The checkout server returned an invalid response.');
            }
            if (!response.ok || result.error) {
                throw new Error(result.error || 'The promotion code could not be applied.');
            }

            this.promoCode = requestedCode;
            this.applyServerPricing(result.pricing || result);
            feedback.className = 'promotion-feedback success';
            feedback.textContent = requestedCode
                ? 'Promotion code ' + requestedCode + ' applied.'
                : 'Promotion code removed.';
            button.textContent = requestedCode ? 'UPDATE CODE' : 'APPLY CODE';
        } catch (error) {
            this.promoCode = previousCode;
            input.value = previousCode;
            feedback.className = 'promotion-feedback error';
            feedback.textContent = this.friendlyCheckoutError(error.message);
        } finally {
            button.disabled = false;
        }
    }
    
    /**
     * Load PayPal JavaScript SDK with new configuration
     */
    loadPayPalSDK() {
        const script = document.createElement('script');
        
        // New SDK configuration matching provided code
        const sdkConfig = {
            'client-id': '{$paypal_client_id}',
            'currency': '{$currency}',
            'components': 'buttons',
            'enable-funding': 'card',
            'disable-funding': 'venmo,paylater'
        };
        if ('{$paypal_mode}' === 'sandbox') sdkConfig['buyer-country'] = '{$buyer_country}';
        const sdkParams = new URLSearchParams(sdkConfig);
        
        script.src = 'https://www.paypal.com/sdk/js?' + sdkParams.toString();
        script.async = true;
        script.setAttribute('data-sdk-integration-source', 'developer-studio');
        
        script.onload = () => {
            console.log('✅ PayPal SDK loaded successfully');
            this.renderPayPalButtons();
        };
        
        script.onerror = () => {
            console.error('❌ Failed to load PayPal SDK');
            this.showMessage('error', 'Failed to load PayPal. Please refresh the page.');
        };
        
        document.head.appendChild(script);
    }
    
    /**
     * Render PayPal Buttons with new style configuration
     */
    renderPayPalButtons() {
        if (typeof paypal === 'undefined') {
            console.error('PayPal SDK not available');
            return;
        }
        
        const self = this;
        
        // PayPal Buttons configuration with new style
        const paypalButtons = window.paypal.Buttons({
            style: {
                shape: 'rect',
                layout: 'vertical',
                color: 'black',
                label: 'paypal'
            },
            
            message: {
                amount: this.calculateTotal()
            },
            
            /**
             * Create Order - Updated implementation
             */
            async createOrder() {
                try {
                    console.log('📝 Creating PayPal order...');
                    self.showMessage('info', 'Preparing your order...', true);
                    
                    // The server re-verifies every item and calculates all discounts.
                    const cartItems = self.checkoutCartPayload();
                    
                    // Call backend to create PayPal order
                    const response = await fetch('/api/paypal.php?action=create-order', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': self.csrfToken
                        },
                        body: JSON.stringify({
                            cart: self.invoiceNumber ? [] : cartItems,
                            invoice_number: self.invoiceNumber,
                            promo_code: self.promoCode
                        })
                    });

                    const responseText = await response.text();
                    let orderData = {};
                    try {
                        orderData = responseText ? JSON.parse(responseText) : {};
                    } catch (parseError) {
                        throw new Error('Checkout server returned an invalid response. HTTP ' + response.status + '. Please check the PHP error log.');
                    }

                    if (orderData.id) {
                        self.paypalOrderId = orderData.id;
                        self.applyServerPricing(orderData.pricing);
                        console.log('✅ PayPal order created: ' + orderData.id);
                        self.showMessage('success', 'Order created! Redirecting to PayPal...', true);
                        return orderData.id;
                    }
                    
                    const errorDetail = orderData?.details?.[0];
                    const rawErrorMessage = orderData?.error || (errorDetail
                        ? errorDetail.issue + ' ' + errorDetail.description + ' (' + orderData.debug_id + ')'
                        : JSON.stringify(orderData));
                    const errorMessage = self.friendlyCheckoutError(rawErrorMessage);

                    throw new Error(errorMessage);
                    
                } catch (error) {
                    console.error('❌ Create order error:', error);
                    self.showMessage('error', 'Could not initiate PayPal Checkout. ' + self.friendlyCheckoutError(error.message));
                    throw error;
                }
            },
            
            /**
             * On Approve - Updated implementation with better error handling
             */
            async onApprove(data, actions) {
                try {
                    console.log('💳 Payment approved, capturing...');
                    self.showMessage('info', 'Processing your payment...', true);
                    
                    // Call backend to capture payment
                    const response = await fetch(
                        '/api/paypal.php?action=capture',
                        {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': self.csrfToken
                            },
                            body: JSON.stringify({order_id: data.orderID})
                        }
                    );

                    const responseText = await response.text();
                    let orderData = {};
                    try {
                        orderData = responseText ? JSON.parse(responseText) : {};
                    } catch (parseError) {
                        throw new Error('Checkout server returned an invalid response. HTTP ' + response.status + '. Please check the PHP error log.');
                    }
                    
                    // Three cases to handle:
                    //   (1) Recoverable INSTRUMENT_DECLINED -> call actions.restart()
                    //   (2) Other non-recoverable errors -> Show a failure message
                    //   (3) Successful transaction -> Show confirmation or thank you message

                    const errorDetail = orderData?.details?.[0];

                    if (errorDetail?.issue === 'INSTRUMENT_DECLINED') {
                        // (1) Recoverable INSTRUMENT_DECLINED -> call actions.restart()
                        return actions.restart();
                    } else if (errorDetail) {
                        // (2) Other non-recoverable errors -> Show a failure message
                        throw new Error(
                            errorDetail.description + ' (' + orderData.debug_id + ')'
                        );
                    } else if (!orderData.purchase_units) {
                        throw new Error(JSON.stringify(orderData));
                    } else {
                        // (3) Successful transaction -> Show confirmation or thank you message
                        const transaction =
                            orderData?.purchase_units?.[0]?.payments?.captures?.[0] ||
                            orderData?.purchase_units?.[0]?.payments?.authorizations?.[0];
                        
                        console.log('✅ Payment captured successfully');
                        console.log('Capture result', orderData, JSON.stringify(orderData, null, 2));
                        
                        // Clear cart
                        if (!self.invoiceNumber) localStorage.removeItem('neuralCart');
                        
                        // Show success message
                        self.resultMessage(
                            'Transaction ' + transaction.status + ': ' + transaction.id + '<br>' +
                            '<br>See console for all available details'
                        );
                        
                        // Redirect to success page
                        setTimeout(() => {
                            window.location.href = 'order-success.php?order=' + encodeURIComponent(orderData.hivenest_order_number || data.orderID);
                        }, 2000);
                    }
                    
                } catch (error) {
                    console.error('❌ Payment capture error:', error);
                    self.showMessage('error', 'Sorry, your transaction could not be processed. ' + error.message);
                }
            },
            
            /**
             * Handle payment cancellation
             */
            onCancel: function(data) {
                console.log('⚠️ Payment cancelled by user');
                self.showMessage('warning', 'Payment was cancelled. You can try again when ready.');
            },
            
            /**
             * Handle errors
             */
            onError: function(err) {
                console.error('❌ PayPal error:', err);
                let message = err && err.message ? err.message : String(err || 'Unknown PayPal error');
                try {
                    const parsed = JSON.parse(message);
                    if (parsed.error) message = parsed.error;
                } catch (e) {}
                self.showMessage('error', 'PayPal checkout could not start: ' + message);
            }
            
        });
        
        paypalButtons.render('#paypal-button-container');
        console.log('✅ PayPal buttons rendered');
    }
    
    /**
     * Calculate total from cart
     */
    calculateTotal() {
        if (Number.isFinite(this.serverTotal)) return this.serverTotal;
        const subtotal = this.cart.reduce((total, item) => total + (Number(item.price || 0) * Number(item.quantity || 1)), 0);
        return subtotal * (1 - (this.loyaltyDiscountPercent / 100));
    }

    isPayableCartItem(item) {
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
    
    /**
     * Show status message to user
     */
    showMessage(type, message, showSpinner = false) {
        const statusElement = document.getElementById('payment-status');
        
        const icons = {
            info: '<i class="fas fa-info-circle"></i>',
            success: '<i class="fas fa-check-circle"></i>',
            warning: '<i class="fas fa-exclamation-triangle"></i>',
            error: '<i class="fas fa-times-circle"></i>'
        };
        
        const spinner = showSpinner ? '<i class="fas fa-spinner fa-spin"></i>' : '';
        const safeMessage = this.escapeHtml(message || '');
        
        statusElement.innerHTML = '<div class="alert alert-' + type + '" data-testid="payment-status-' + type + '">' +
            (spinner || icons[type] || '') +
            '<span>' + safeMessage + '</span>' +
            '</div>';
    }

    /**
     * Make backend checkout validation errors useful to the customer.
     */
    friendlyCheckoutError(message) {
        const text = String(message || 'PayPal checkout could not start.');
        const lower = text.toLowerCase();
        if (lower.includes('please go back to the cart or special ops page')
            || lower.includes('please choose another domain or use the name generator')
            || lower.includes('send the domain to support for feedback')) {
            return text;
        }
        if (lower.includes('requires a domain before checkout')) {
            return text + ' Please go back to the cart or Special Ops page, enter the domain for that package, then try checkout again.';
        }
        if (lower.includes('domain is not available for registration')) {
            return text + ' Please choose another domain or use the name generator.';
        }
        if (lower.includes('could not verify live domain availability')) {
            return text + ' If it keeps happening, send the domain to support for feedback.';
        }
        return text;
    }
    
    /**
     * Display result message in the result container
     */
    resultMessage(message) {
        const container = document.querySelector('#result-message');
        if (container) {
            container.innerHTML = message;
        }
        this.showMessage('success', message);
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

async function resendVerificationEmail(button) {
    const original = button.innerHTML;
    const status = document.getElementById('verification-resend-status');
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> SENDING...';
    if (status) {
        status.className = 'payment-message info';
        status.innerHTML = 'Creating a fresh verification link...';
    }
    try {
        const response = await fetch('/api/email-verification.php?action=resend', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '{$checkout_csrf_token}'
            },
            body: JSON.stringify({})
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Could not send verification email.');
        if (data.verified) window.location.reload();
        if (status) {
            status.className = data.sent ? 'payment-message success' : 'payment-message warning';
            status.textContent = data.message || 'Verification email sent.';
        }
    } catch (error) {
        if (status) {
            status.className = 'payment-message error';
            status.textContent = error.message;
        }
    } finally {
        button.innerHTML = original;
        button.disabled = false;
    }
}

async function checkVerificationStatus(button) {
    const original = button.innerHTML;
    const status = document.getElementById('verification-resend-status');
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> CHECKING...';
    if (status) {
        status.className = 'payment-message info';
        status.textContent = 'Checking verification status...';
    }
    try {
        const response = await fetch('/api/email-verification.php?action=status', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '{$checkout_csrf_token}'
            },
            body: JSON.stringify({})
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Could not check verification status.');
        if (data.verified) {
            if (status) {
                status.className = 'payment-message success';
                status.textContent = 'Email verified. Reloading checkout...';
            }
            window.location.reload();
            return;
        }
        if (status) {
            status.className = 'payment-message warning';
            status.textContent = data.message || 'Email is not verified yet.';
        }
    } catch (error) {
        if (status) {
            status.className = 'payment-message error';
            status.textContent = error.message;
        }
    } finally {
        button.innerHTML = original;
        button.disabled = false;
    }
}

// Initialize checkout when DOM is ready
let checkout;
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        checkout = new PayPalCheckout();
        window.checkout = checkout;
    });
} else {
    checkout = new PayPalCheckout();
    window.checkout = checkout;
}
JAVASCRIPT;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'utilities/head.php'; ?>
<style>
/* Checkout Layout */
.checkout-container {
    display: grid;
    grid-template-columns: 1fr 450px;
    gap: 3rem;
    align-items: start;
    margin-top: 3rem;
}

@media (max-width: 968px) {
    .checkout-container {
        grid-template-columns: 1fr;
    }
}

/* Order Items */
.order-item {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.order-item:last-child {
    border-bottom: none;
}

.item-name {
    color: var(--cyber-neon-cyan);
    margin: 0 0 4px 0;
    font-size: 1rem;
}

.item-details {
    color: rgba(255,255,255,0.6);
    margin: 0;
    font-size: 0.9rem;
}

.item-price p {
    color: var(--cyber-neon-green);
    font-weight: bold;
    margin: 0;
    text-align: right;
}

/* Alert Messages */
.alert {
    padding: 1rem;
    border-radius: 8px;
    margin: 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert i {
    font-size: 1.2rem;
}

.alert-info {
    background: rgba(0, 255, 255, 0.1);
    border: 1px solid rgba(0, 255, 255, 0.3);
    color: var(--cyber-neon-cyan);
}

.alert-success {
    background: rgba(0, 255, 0, 0.1);
    border: 1px solid rgba(0, 255, 0, 0.3);
    color: var(--cyber-neon-green);
}

.alert-warning {
    background: rgba(255, 255, 0, 0.1);
    border: 1px solid rgba(255, 255, 0, 0.3);
    color: #ffff00;
}

.alert-error {
    background: rgba(255, 0, 255, 0.1);
    border: 1px solid rgba(255, 0, 255, 0.3);
    color: var(--cyber-neon-pink);
}

/* PayPal Button Container */
#paypal-button-container {
    margin-top: 1.5rem;
    min-height: 200px;
}

/* Security Badge */
.security-badge {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255,255,255,0.2);
    color: rgba(255,255,255,0.7);
}

.security-badge i {
    color: var(--cyber-neon-green);
    font-size: 1.5rem;
}

/* Order Summary Totals */
.order-totals {
    border-top: 2px solid rgba(0,255,255,0.3);
    padding-top: 1rem;
    margin-top: 1rem;
}

.order-totals .total-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}

.order-totals .grand-total {
    font-size: 1.3rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255,255,255,0.2);
}

.currency-charge-notice {
    margin: 0 0 1rem;
    padding: 0.7rem;
    color: rgba(255,255,255,0.72);
    background: rgba(0,255,255,0.06);
    border: 1px solid rgba(0,255,255,0.18);
    border-radius: 6px;
    font-size: 0.78rem;
    line-height: 1.45;
}

.promotion-box {
    margin: 0 0 1.25rem;
    padding: 1rem;
    border: 1px solid rgba(0,255,255,0.25);
    border-radius: 8px;
    background: rgba(0,255,255,0.04);
}

.promotion-controls {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 0.65rem;
}

.promotion-controls input {
    min-width: 0;
    padding: 0.75rem;
    color: #fff;
    background: rgba(0,0,0,0.45);
    border: 1px solid rgba(0,255,255,0.45);
    border-radius: 6px;
    text-transform: uppercase;
}

.promotion-controls button {
    padding: 0.75rem 1rem;
}

.promotion-controls button:disabled {
    cursor: wait;
    opacity: 0.55;
}

.promotion-feedback {
    min-height: 1.2rem;
    margin: 0.55rem 0 0;
    color: rgba(255,255,255,0.7);
    font-size: 0.78rem;
}

.promotion-feedback.success { color: var(--cyber-neon-green); }
.promotion-feedback.error { color: var(--cyber-neon-pink); }

@media (max-width: 560px) {
    .promotion-controls { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<?php include 'utilities/nav.php'; ?>
<?php include 'utilities/mobile-menu.php'; ?>

<!-- Hero Section -->
<section class="hero">
    <img src="assets/images/heroes/hero-cart.jpg" alt="Secure Checkout" class="hero-background">
    
    <div class="hero-content">
        <div class="hero-text">
            <h1 class="hero-title">
                <span class="cyber-text">SECURE</span><br>
                CHECKOUT
            </h1>
            <p class="hero-subtitle">
                Complete your digital transformation with quantum-encrypted payment processing.
            </p>
        </div>
    </div>
</section>

<!-- Checkout Section -->
<section class="section">
    <div class="container">
        <div class="text-center mb-8">
            <h2 data-testid="checkout-heading">COMPLETE YOUR ORDER</h2>
            <p class="hero-subtitle">Secure payment powered by PayPal</p>
        </div>
        
        <div class="checkout-container">
            <!-- Payment Method Column -->
            <div>
                <div class="cyber-card" style="margin-bottom: 2rem;">
                    <h3 class="service-title" style="margin-bottom: 1.5rem;">
                        <i class="fas fa-credit-card" style="margin-right: 0.5rem;"></i>
                        PAYMENT METHOD
                    </h3>
                    
                    <!-- Payment Status Messages -->
                    <div id="payment-status" data-testid="payment-status"></div>
                    
                    <?php if ($checkout_can_pay): ?>
                    <div class="payment-message info" data-testid="signed-in-customer">
                        Signed in as <strong><?php echo htmlspecialchars($checkout_customer_email ?: ('Customer #' . $checkout_customer_id), ENT_QUOTES, 'UTF-8'); ?></strong>.
                        <a href="logout.php" style="color:inherit;text-decoration:underline;">Not you? Sign out</a>
                    </div>
                    <!-- PayPal Button Container -->
                    <div id="paypal-button-container" data-testid="paypal-button-container"></div>
                    <?php elseif ($checkout_authenticated): ?>
                    <div class="payment-message info" data-testid="email-verification-required">
                        <strong>Email verification required before payment.</strong><br>
                        Signed in as <strong><?php echo htmlspecialchars($checkout_customer_email ?: ('Customer #' . $checkout_customer_id), ENT_QUOTES, 'UTF-8'); ?></strong>.
                        Please verify your email address, then return to checkout. Your cart will remain saved in this browser.
                    </div>
                    <div style="display:grid; gap:1rem; margin-top:1.5rem;">
                        <button type="button" class="btn btn-primary" onclick="resendVerificationEmail(this)">
                            <i class="fas fa-envelope" style="margin-right:0.5rem;"></i>
                            RESEND VERIFICATION EMAIL
                        </button>
                        <button type="button" class="btn btn-outline" onclick="checkVerificationStatus(this)">
                            <i class="fas fa-sync-alt" style="margin-right:0.5rem;"></i>
                            I VERIFIED — CHECK STATUS
                        </button>
                        <div id="verification-resend-status"></div>
                        <a class="btn btn-secondary" href="logout.php">SIGN OUT</a>
                    </div>
                    <?php else: ?>
                    <div class="payment-message info" data-testid="account-required">
                        <strong>A HiveNest account is required before payment.</strong><br>
                        Create an account or sign in first. Your cart will remain saved in this browser.
                    </div>
                    <div style="display:grid; gap:1rem; margin-top:1.5rem;">
                        <a class="btn btn-primary" href="auth.php?mode=signup&amp;return=checkout.php">CREATE ACCOUNT</a>
                        <a class="btn btn-secondary" href="auth.php?mode=login&amp;return=checkout.php">SIGN IN</a>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Result Message -->
                    <p id="result-message" style="margin-top: 1rem; padding: 1rem; border-radius: 8px;"></p>
                    
                    <!-- Security Badge -->
                    <div class="security-badge">
                        <i class="fas fa-shield-alt"></i>
                        <p style="margin: 0; font-size: 0.9rem;">
                            Your payment information is encrypted and secure. We never store your payment details.
                        </p>
                    </div>
                </div>
                
                <!-- Back to Cart Button -->
                <div style="text-align: center;">
                    <a href="cart.php" class="btn btn-outline" data-testid="back-to-cart-btn">
                        <i class="fas fa-arrow-left" style="margin-right: 0.5rem;"></i>
                        BACK TO CART
                    </a>
                </div>
            </div>
            
            <!-- Order Summary Column -->
            <div class="cyber-card">
                <h3 class="service-title" style="margin-bottom: 1.5rem;">
                    <i class="fas fa-file-invoice" style="margin-right: 0.5rem;"></i>
                    ORDER SUMMARY
                </h3>
                
                <!-- Order Items -->
                <div id="order-summary-items" data-testid="order-summary-items" style="margin-bottom: 1.5rem;">
                    <!-- Items populated by JavaScript -->
                </div>

                <div class="promotion-box">
                    <label for="promotion-code" style="display:block; margin-bottom:0.55rem; color:var(--cyber-neon-cyan);">
                        PROMOTION CODE
                    </label>
                    <div class="promotion-controls">
                        <input id="promotion-code" type="text" maxlength="50" autocomplete="off" placeholder="ENTER CODE">
                        <button id="apply-promotion-btn" type="button" class="btn btn-outline" onclick="window.checkout.applyPromotion()">
                            APPLY CODE
                        </button>
                    </div>
                    <p id="promotion-feedback" class="promotion-feedback" aria-live="polite"></p>
                </div>
                
                <!-- Order Totals -->
                <div class="order-totals">
                    <p class="currency-charge-notice">
                        Display conversions are indicative. PayPal charges this order in USD.
                    </p>
                    <div class="total-row">
                        <span style="color: rgba(255,255,255,0.8);">Subtotal:</span>
                        <span id="order-subtotal" data-testid="order-subtotal" data-usd-price="0" style="color: var(--cyber-neon-green); font-weight: bold;">$0.00</span>
                    </div>
                    <div class="total-row">
                        <span style="color: rgba(255,255,255,0.8);">Tax:</span>
                        <span id="order-tax" data-testid="order-tax" data-usd-price="0" style="color: var(--cyber-neon-green);">$0.00</span>
                    </div>
                    <div class="total-row">
                        <span id="order-discount-label" style="color: rgba(255,255,255,0.8);">Loyalty Discount:</span>
                        <span id="order-discount" data-testid="order-discount" data-usd-price="0" data-currency-sign="-1" style="color: var(--cyber-neon-green);">-$0.00</span>
                    </div>
                    <div class="total-row" id="order-promotion-row" style="display:none;">
                        <span id="order-promotion-label" style="color: rgba(255,255,255,0.8);">Promotion:</span>
                        <span id="order-promotion-discount" data-testid="order-promotion-discount" data-usd-price="0" data-currency-sign="-1" style="color: var(--cyber-neon-green);">-$0.00</span>
                    </div>
                    <div class="total-row grand-total">
                        <span style="color: var(--cyber-neon-cyan); font-weight: bold;">Total:</span>
                        <span id="order-total" data-testid="order-total" data-usd-price="0" style="color: var(--cyber-neon-pink); font-weight: bold;">$0.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Trust Badges Section -->
<section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
    <div class="container">
        <div class="services-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
            <div style="text-align: center;">
                <i class="fas fa-lock" style="font-size: 3rem; color: var(--cyber-neon-green); margin-bottom: 1rem;"></i>
                <h4 style="color: var(--cyber-neon-cyan);">256-bit Encryption</h4>
            </div>
            <div style="text-align: center;">
                <i class="fab fa-paypal" style="font-size: 3rem; color: var(--cyber-neon-cyan); margin-bottom: 1rem;"></i>
                <h4 style="color: var(--cyber-neon-cyan);">PayPal Secure</h4>
            </div>
            <div style="text-align: center;">
                <i class="fas fa-shield-alt" style="font-size: 3rem; color: var(--cyber-neon-pink); margin-bottom: 1rem;"></i>
                <h4 style="color: var(--cyber-neon-cyan);">PCI Compliant</h4>
            </div>
            <div style="text-align: center;">
                <i class="fas fa-undo" style="font-size: 3rem; color: var(--cyber-neon-green); margin-bottom: 1rem;"></i>
                <h4 style="color: var(--cyber-neon-cyan);">Money-back Guarantee</h4>
            </div>
        </div>
    </div>
</section>

<?php include 'utilities/footer.php'; ?>
<?php include 'utilities/scripts.php'; ?>
</body>
</html>
