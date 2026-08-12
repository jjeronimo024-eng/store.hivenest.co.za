// HiveNest.co.za - Main JavaScript
// Modern, Professional, Interactive Features

class HiveNestApp {
    constructor() {
        this.init();
    }

    init() {
        this.setupNavigation();
        this.setupScrollEffects();
        this.setupAnimations();
        this.setupForms();
        this.setupMobileMenu();
        this.setupSmoothScrolling();
        this.setupLazyLoading();
    }

    // Navigation Effects
    setupNavigation() {
        const navbar = document.querySelector('.navbar');
        const navLinks = document.querySelectorAll('.navbar-nav a');

        // Navbar scroll effect
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Active link highlighting
        const sections = document.querySelectorAll('section[id]');
        const observerOptions = {
            root: null,
            rootMargin: '-50% 0px -50% 0px',
            threshold: 0
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    navLinks.forEach(link => {
                        link.classList.remove('active');
                        if (link.getAttribute('href') === `#${entry.target.id}`) {
                            link.classList.add('active');
                        }
                    });
                    
                    // Update mobile menu active state
                    const mobileLinks = document.querySelectorAll('.mobile-menu-link');
                    mobileLinks.forEach(link => {
                        link.classList.remove('active');
                        if (link.getAttribute('href') === `#${entry.target.id}`) {
                            link.classList.add('active');
                        }
                    });
                }
            });
        }, observerOptions);

        sections.forEach(section => {
            observer.observe(section);
        });
    }

    // Scroll-triggered animations
    setupScrollEffects() {
        const animatedElements = document.querySelectorAll('.animate-on-scroll');
        
        const observerOptions = {
            root: null,
            rootMargin: '0px',
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fadeInUp');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        animatedElements.forEach(element => {
            observer.observe(element);
        });
    }

    // Page load animations
    setupAnimations() {
        // Stagger animations for cards
        const cards = document.querySelectorAll('.service-card, .pricing-card, .testimonial-card');
        cards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.classList.add('animate-on-scroll');
        });

        // Hero text animation
        const heroTitle = document.querySelector('.hero-title');
        const heroSubtitle = document.querySelector('.hero-subtitle');
        const heroCta = document.querySelector('.hero-cta');

        if (heroTitle) {
            setTimeout(() => heroTitle.classList.add('animate-fadeInUp'), 200);
        }
        if (heroSubtitle) {
            setTimeout(() => heroSubtitle.classList.add('animate-fadeInUp'), 400);
        }
        if (heroCta) {
            setTimeout(() => heroCta.classList.add('animate-fadeInUp'), 600);
        }
    }

    // Form handling
    setupForms() {
        // Only target contact forms, not all forms on the page
        // This allows other forms (domain search, scan forms, etc.) to handle their own submissions
        const forms = document.querySelectorAll('.contact-form, #contact-form, form[action*="contact"]');
        
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleFormSubmit(form);
            });

            // Real-time validation
            const inputs = form.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.addEventListener('blur', () => {
                    this.validateField(input);
                });
            });
        });
    }

    // Handle form submissions
    async handleFormSubmit(form) {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        // Show loading state
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        submitButton.textContent = 'Sending...';
        submitButton.disabled = true;

        try {
            // Send to backend API
            const response = await fetch('/api/contact', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            const responseText = await response.text();
            let result = {};
            if (responseText) {
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Contact API returned an invalid response:', parseError);
                }
            }

            if (response.ok) {
                this.showFormMessage(form, 'success', result.message || 'Thank you! Your message has been sent successfully.');
                form.reset();
            } else {
                throw new Error(result.error || 'Failed to send message');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            this.showFormMessage(form, 'error', error.message || 'Sorry, there was an error sending your message. Please try again.');
        } finally {
            // Reset button state
            submitButton.textContent = originalText;
            submitButton.disabled = false;
        }
    }

    showFormMessage(form, type, message) {
        const result = form.querySelector('#form-result, [data-form-result]');
        if (!result) return;
        result.textContent = message;
        result.style.display = 'block';
        result.style.color = type === 'success' ? '#8dffb7' : '#ff9abb';
        result.style.background = type === 'success' ? 'rgba(16,185,129,.14)' : 'rgba(239,68,68,.14)';
        result.style.border = `1px solid ${type === 'success' ? 'rgba(16,185,129,.55)' : 'rgba(239,68,68,.55)'}`;
        result.setAttribute('role', type === 'error' ? 'alert' : 'status');
        result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Field validation
    validateField(field) {
        const value = field.value.trim();
        const fieldName = field.name;
        let isValid = true;
        let errorMessage = '';

        // Remove existing error messages
        this.clearFieldError(field);

        // Validation rules
        switch (fieldName) {
            case 'email':
                if (!this.isValidEmail(value)) {
                    isValid = false;
                    errorMessage = 'Please enter a valid email address';
                }
                break;
            case 'phone':
                if (value && !this.isValidPhone(value)) {
                    isValid = false;
                    errorMessage = 'Please enter a valid phone number';
                }
                break;
            case 'name':
            case 'first_name':
            case 'last_name':
                if (value.length < 2) {
                    isValid = false;
                    errorMessage = 'Name must be at least 2 characters long';
                }
                break;
            case 'message':
                if (value.length < 10) {
                    isValid = false;
                    errorMessage = 'Message must be at least 10 characters long';
                }
                break;
        }

        if (!isValid) {
            this.showFieldError(field, errorMessage);
        }

        return isValid;
    }

    // Validation helpers
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    isValidPhone(phone) {
        const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
        return phoneRegex.test(phone.replace(/\s/g, ''));
    }

    // Error display
    showFieldError(field, message) {
        field.classList.add('error');
        field.style.borderColor = '#ef4444';
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'form-error';
        errorDiv.textContent = message;
        field.parentNode.appendChild(errorDiv);
    }

    clearFieldError(field) {
        field.classList.remove('error');
        field.style.borderColor = '';
        
        const existingError = field.parentNode.querySelector('.form-error');
        if (existingError) {
            existingError.remove();
        }
    }

    // Show success/error messages
    showMessage(type, message) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message message-${type}`;
        messageDiv.textContent = message;
        messageDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            animation: slideInRight 0.3s ease-out;
            background: ${type === 'success' ? '#10b981' : '#ef4444'};
        `;

        document.body.appendChild(messageDiv);

        // Auto remove after 5 seconds
        setTimeout(() => {
            messageDiv.style.animation = 'slideInRight 0.3s ease-out reverse';
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.parentNode.removeChild(messageDiv);
                }
            }, 300);
        }, 5000);
    }

    // Enhanced Mobile Menu with Cyberpunk Animations
    setupMobileMenu() {
        const toggle = document.querySelector('.mobile-menu-toggle');
        const overlay = document.querySelector('.mobile-menu-overlay');
        const closeBtn = document.querySelector('.mobile-menu-close');
        const menuLinks = document.querySelectorAll('.mobile-menu-link');

        // Open/Close mobile menu with toggle button
        if (toggle) {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const isOpen = overlay.classList.contains('active');
                
                if (isOpen) {
                    this.closeMobileMenu();
                } else {
                    this.openMobileMenu();
                }
            });
        }

        // Close mobile menu
        const closeMobileMenu = () => {
            this.closeMobileMenu();
        };

        if (closeBtn) {
            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                closeMobileMenu();
            });
        }

        // Close menu when clicking outside (on overlay itself)
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                closeMobileMenu();
            }
        });

        // Close menu when clicking nav links
        menuLinks.forEach(link => {
            link.addEventListener('click', () => {
                closeMobileMenu();
            });
        });

        // Escape key to close menu
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && overlay.classList.contains('active')) {
                closeMobileMenu();
            }
        });

        // Touch gestures for mobile
        let touchStartX = 0;
        let touchStartY = 0;

        overlay.addEventListener('touchstart', (e) => {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
        });

        overlay.addEventListener('touchmove', (e) => {
            if (!touchStartX || !touchStartY) return;

            const touchEndX = e.touches[0].clientX;
            const touchEndY = e.touches[0].clientY;
            const diffX = touchStartX - touchEndX;
            const diffY = touchStartY - touchEndY;

            // Swipe left to close
            if (Math.abs(diffX) > Math.abs(diffY) && diffX < -50) {
                closeMobileMenu();
            }
        });

        overlay.addEventListener('touchend', () => {
            touchStartX = 0;
            touchStartY = 0;
        });
    }

    // Open mobile menu
    openMobileMenu() {
        const toggle = document.querySelector('.mobile-menu-toggle');
        const overlay = document.querySelector('.mobile-menu-overlay');
        
        toggle.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        this.animateMobileMenuItems();
    }

    // Close mobile menu
    closeMobileMenu() {
        const toggle = document.querySelector('.mobile-menu-toggle');
        const overlay = document.querySelector('.mobile-menu-overlay');
        
        toggle.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Animate mobile menu items with cyberpunk effect
    animateMobileMenuItems() {
        const menuItems = document.querySelectorAll('.mobile-menu-link');
        const actionButtons = document.querySelectorAll('.mobile-menu-btn');
        
        // Animate menu items with stagger effect
        menuItems.forEach((item, index) => {
            item.style.transform = 'translateX(-50px)';
            item.style.opacity = '0';
            
            setTimeout(() => {
                item.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                item.style.transform = 'translateX(0)';
                item.style.opacity = '1';
            }, 100 * index);
        });

        // Animate action buttons
        actionButtons.forEach((button, index) => {
            button.style.transform = 'translateY(30px)';
            button.style.opacity = '0';
            
            setTimeout(() => {
                button.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                button.style.transform = 'translateY(0)';
                button.style.opacity = '1';
            }, 300 + (100 * index));
        });
    }

    // Smooth scrolling for anchor links
    setupSmoothScrolling() {
        const anchorLinks = document.querySelectorAll('a[href^="#"]');
        
        anchorLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = link.getAttribute('href').substring(1);
                const targetElement = document.getElementById(targetId);
                
                if (targetElement) {
                    const offsetTop = targetElement.offsetTop - 80; // Account for fixed navbar
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            });
        });
    }

    // Lazy loading for images
    setupLazyLoading() {
        const images = document.querySelectorAll('img[data-src]');
        
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    imageObserver.unobserve(img);
                }
            });
        });

        images.forEach(img => {
            imageObserver.observe(img);
        });
    }

    // Cyberpunk glitch effect for text
    addGlitchEffect(element) {
        const text = element.textContent;
        const glitchChars = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        let iterations = 0;
        const maxIterations = 10;

        const glitchInterval = setInterval(() => {
            element.textContent = text.split('')
                .map((char, index) => {
                    if (index < iterations) {
                        return text[index];
                    }
                    return glitchChars[Math.floor(Math.random() * glitchChars.length)];
                })
                .join('');

            iterations++;
            
            if (iterations > maxIterations) {
                clearInterval(glitchInterval);
                element.textContent = text;
            }
        }, 50);
    }

    // Matrix rain effect (optional cyberpunk enhancement)
    initMatrixRain() {
        const canvas = document.createElement('canvas');
        canvas.style.position = 'fixed';
        canvas.style.top = '0';
        canvas.style.left = '0';
        canvas.style.width = '100%';
        canvas.style.height = '100%';
        canvas.style.pointerEvents = 'none';
        canvas.style.zIndex = '-1';
        canvas.style.opacity = '0.1';
        document.body.appendChild(canvas);

        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const matrix = "ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789@#$%^&*()*&^%+-/~{[|`]}".split("");
        const font_size = 14;
        const columns = canvas.width / font_size;
        const drops = [];

        for (let x = 0; x < columns; x++) {
            drops[x] = 1;
        }

        const draw = () => {
            ctx.fillStyle = 'rgba(0, 0, 0, 0.04)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            ctx.fillStyle = '#00ff00';
            ctx.font = font_size + 'px arial';
            
            for (let i = 0; i < drops.length; i++) {
                const text = matrix[Math.floor(Math.random() * matrix.length)];
                ctx.fillText(text, i * font_size, drops[i] * font_size);
                
                if (drops[i] * font_size > canvas.height && Math.random() > 0.975) {
                    drops[i] = 0;
                }
                drops[i]++;
            }
        };

        // Only run matrix rain on desktop
        if (window.innerWidth > 768) {
            setInterval(draw, 35);
        }
    }

    // Utility: Get current page
    getCurrentPage() {
        return window.location.pathname.split('/').pop() || 'index.html';
    }

    // Utility: Format currency
    formatCurrency(amount, currency = 'USD') {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency
        }).format(amount);
    }

    // Utility: Debounce function
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Shopping Cart functionality (for future ResellerClub integration)
class ShoppingCart {
    constructor() {
        this.items = this.loadCart();
        this.updateCartDisplay();
    }

    addItem(product) {
        const existingItem = this.items.find(item => item.id === product.id);
        
        if (existingItem) {
            existingItem.quantity += 1;
        } else {
            this.items.push({
                ...product,
                quantity: 1
            });
        }
        
        this.saveCart();
        this.updateCartDisplay();
        this.showCartNotification('Item added to cart!');
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

    getTotal() {
        return this.items.reduce((total, item) => total + (item.price * item.quantity), 0);
    }

    getItemCount() {
        return this.items.reduce((count, item) => count + item.quantity, 0);
    }

    loadCart() {
        try {
            return JSON.parse(localStorage.getItem('hivenest_cart') || '[]');
        } catch {
            return [];
        }
    }

    saveCart() {
        localStorage.setItem('hivenest_cart', JSON.stringify(this.items));
    }

    updateCartDisplay() {
        const cartCounts = document.querySelectorAll('.cart-count');
        const cartTotal = document.querySelector('.cart-total');
        const count = this.getItemCount();

        cartCounts.forEach(el => {
            if (count > 0) {
                el.textContent = count;
                el.classList.add('has-items');
                el.classList.remove('is-zero');
            } else {
                el.textContent = '';
                el.classList.remove('has-items');
                el.classList.add('is-zero');
            }
        });

        if (cartTotal) {
            cartTotal.textContent = this.formatCurrency(this.getTotal());
        }
    }

    showCartNotification(message) {
        // Use the main app's message system
        if (window.hiveNestApp) {
            window.hiveNestApp.showMessage('success', message);
        }
    }

    formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Initialize main app
    window.hiveNestApp = new HiveNestApp();
    
    // Don't initialize shopping cart - cart-neural.js handles this now
    
    // Add smooth loading effect
    document.body.style.opacity = '0';
    document.body.style.transition = 'opacity 0.3s ease';
    
    setTimeout(() => {
        document.body.style.opacity = '1';
    }, 100);

    // Optional: Initialize matrix rain effect
    // window.hiveNestApp.initMatrixRain();
});

// Export for other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { HiveNestApp, ShoppingCart };
}
