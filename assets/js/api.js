// HiveNest.co.za - API Integration
// Backend Communication & ResellerClub Integration

class HiveNestAPI {
    constructor() {
        this.baseURL = this.getBackendURL();
        this.apiURL = `${this.baseURL}/api`;
    }

    getBackendURL() {
        // Use the current domain for API calls
        return window.location.origin;
    }

    // Generic API request method
    async request(endpoint, options = {}) {
        const url = `${this.apiURL}${endpoint}`;
        const config = {
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        };

        try {
            const response = await fetch(url, config);
            
            const text = await response.text();
            let payload = {};
            if (text) {
                try { payload = JSON.parse(text); }
                catch (error) { throw new Error('The API returned an invalid response.'); }
            }
            if (!response.ok) throw new Error(payload.error || `HTTP error! status: ${response.status}`);
            return payload;
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    }

    // Contact form submission
    async submitContactForm(formData) {
        return this.request('/contact', {
            method: 'POST',
            body: JSON.stringify(formData)
        });
    }

    // Newsletter subscription
    async subscribeNewsletter(email) {
        return this.request('/newsletter/subscribe', {
            method: 'POST',
            body: JSON.stringify({ email })
        });
    }

    // Customer registration
    async registerCustomer(customerData) {
        return this.request('/customer-auth.php?action=register', {
            method: 'POST',
            body: JSON.stringify(customerData)
        });
    }

    // Customer login
    async loginCustomer(email, password) {
        return this.request('/customer-auth.php?action=login', {
            method: 'POST',
            body: JSON.stringify({ email, password })
        });
    }

    // Get hosting plans
    async getHostingPlans() {
        return this.request('/hosting/plans');
    }

    // Get domain pricing
    async getDomainPricing() {
        return this.request('/domains/pricing');
    }

    // Check domain availability
    async checkDomainAvailability(domain) {
        return this.request('/domains/check', {
            method: 'POST',
            body: JSON.stringify({ domain })
        });
    }

    // Get SSL certificates
    async getSSLCertificates() {
        return this.request('/ssl/certificates');
    }

    // Submit order
    async submitOrder(orderData) {
        return this.request('/orders', {
            method: 'POST',
            body: JSON.stringify(orderData)
        });
    }

    // Get customer orders
    async getCustomerOrders(customerId) {
        return this.request(`/customers/${customerId}/orders`);
    }

    // Get customer services
    async getCustomerServices(customerId) {
        return this.request(`/customers/${customerId}/services`);
    }

    // Create support ticket
    async createSupportTicket(ticketData) {
        return this.request('/support/tickets', {
            method: 'POST',
            body: JSON.stringify(ticketData)
        });
    }

    // Get support tickets
    async getSupportTickets(customerId) {
        return this.request(`/customers/${customerId}/tickets`);
    }
}

// ResellerClub Integration (Placeholder for now)
class ResellerClubAPI {
    constructor() {
        this.baseURL = 'https://test.httpapi.com/api'; // Test environment
        this.isDemoMode = true; // Set to false when real API keys are available
    }

    // Check domain availability
    async checkDomainAvailability(domain, tlds = ['com', 'net', 'org']) {
        if (this.isDemoMode) {
            // Return demo data
            return {
                domain: domain,
                available: Math.random() > 0.5,
                price: 12.99,
                currency: 'USD',
                tlds: tlds.map(tld => ({
                    tld: tld,
                    available: Math.random() > 0.5,
                    price: 12.99 + Math.random() * 10
                }))
            };
        }

        // Real API call would go here
        try {
            const response = await fetch(`${this.baseURL}/domains/available.json`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'auth-userid': 'YOUR_USER_ID',
                    'api-key': 'YOUR_API_KEY',
                    'domain-name': domain,
                    'tlds': tlds.join(',')
                })
            });

            return await response.json();
        } catch (error) {
            console.error('Domain check failed:', error);
            throw error;
        }
    }

    // Register domain
    async registerDomain(domainData) {
        if (this.isDemoMode) {
            return {
                success: true,
                domain: domainData.domain,
                order_id: `demo_${Date.now()}`,
                message: 'Domain registered successfully (Demo Mode)'
            };
        }

        // Real API call would go here
        // Implementation depends on ResellerClub API documentation
    }

    // Create hosting account
    async createHostingAccount(hostingData) {
        if (this.isDemoMode) {
            return {
                success: true,
                account_id: `demo_${Date.now()}`,
                username: `user_${hostingData.domain.split('.')[0]}`,
                password: 'temp_password_123',
                cpanel_url: `https://cpanel.${hostingData.domain}`,
                message: 'Hosting account created successfully (Demo Mode)'
            };
        }

        // Real API call would go here
    }

    // Create email account
    async createEmailAccount(emailData) {
        if (this.isDemoMode) {
            return {
                success: true,
                email: emailData.email,
                password: emailData.password,
                storage: emailData.storage || '5GB',
                message: 'Email account created successfully (Demo Mode)'
            };
        }

        // Real API call would go here
    }

    // Get SSL certificate
    async getSSLCertificate(sslData) {
        if (this.isDemoMode) {
            return {
                success: true,
                certificate_id: `ssl_${Date.now()}`,
                domain: sslData.domain,
                type: sslData.type || 'basic',
                valid_until: new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString(),
                message: 'SSL certificate issued successfully (Demo Mode)'
            };
        }

        // Real API call would go here
    }
}

// Service pricing and plans
class ServiceManager {
    constructor() {
        this.hostingPlans = [
            {
                id: 'starter',
                name: 'Starter Hosting',
                price: 4.99,
                period: 'monthly',
                features: [
                    '1 Website',
                    '10 GB SSD Storage',
                    'Unlimited Bandwidth',
                    'Free SSL Certificate',
                    '1 Email Account',
                    '24/7 Support'
                ],
                popular: false
            },
            {
                id: 'business',
                name: 'Business Hosting',
                price: 14.99,
                period: 'monthly',
                features: [
                    '10 Websites',
                    '50 GB SSD Storage',
                    'Unlimited Bandwidth',
                    'Free SSL Certificate',
                    '25 Email Accounts',
                    'Free Domain (1 Year)',
                    'WordPress Toolkit',
                    '24/7 Priority Support'
                ],
                popular: true
            },
            {
                id: 'enterprise',
                name: 'Enterprise Hosting',
                price: 29.99,
                period: 'monthly',
                features: [
                    'Unlimited Websites',
                    '200 GB SSD Storage',
                    'Unlimited Bandwidth',
                    'Free SSL Certificate',
                    'Unlimited Email Accounts',
                    'Free Domain (1 Year)',
                    'WordPress Toolkit',
                    'Daily Backups',
                    'Advanced Security',
                    '24/7 Priority Support'
                ],
                popular: false
            }
        ];

        this.domainPricing = [
            { tld: '.com', price: 12.99, renewal: 14.99 },
            { tld: '.net', price: 13.99, renewal: 15.99 },
            { tld: '.org', price: 13.99, renewal: 15.99 },
            { tld: '.co.za', price: 89.00, renewal: 89.00 },
            { tld: '.info', price: 11.99, renewal: 13.99 },
            { tld: '.biz', price: 12.99, renewal: 14.99 }
        ];

        this.sslCertificates = [
            {
                id: 'basic',
                name: 'Basic SSL',
                price: 9.99,
                period: 'yearly',
                features: [
                    'Single Domain',
                    '256-bit Encryption',
                    'Trust Seal',
                    'Browser Compatibility',
                    '$1,000 Warranty'
                ]
            },
            {
                id: 'wildcard',
                name: 'Wildcard SSL',
                price: 49.99,
                period: 'yearly',
                features: [
                    'Unlimited Subdomains',
                    '256-bit Encryption',
                    'Trust Seal',
                    'Browser Compatibility',
                    '$10,000 Warranty'
                ]
            }
        ];
    }

    getHostingPlans() {
        return this.hostingPlans;
    }

    getDomainPricing() {
        return this.domainPricing;
    }

    getSSLCertificates() {
        return this.sslCertificates;
    }

    getHostingPlan(planId) {
        return this.hostingPlans.find(plan => plan.id === planId);
    }

    getDomainPrice(tld) {
        const domain = this.domainPricing.find(d => d.tld === tld);
        return domain ? domain.price : null;
    }

    getSSLCertificate(certId) {
        return this.sslCertificates.find(cert => cert.id === certId);
    }
}

// Initialize API services
window.hiveNestAPI = new HiveNestAPI();
window.resellerClubAPI = new ResellerClubAPI();
window.serviceManager = new ServiceManager();

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { HiveNestAPI, ResellerClubAPI, ServiceManager };
}
