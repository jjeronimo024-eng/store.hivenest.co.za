// HiveNest.co.za - Advanced Animations & Visual Effects
// Smooth, Professional Animation System

class AnimationEngine {
    constructor() {
        this.observers = new Map();
        this.animations = new Map();
        this.init();
    }

    init() {
        this.setupScrollAnimations();
        this.setupHoverEffects();
        this.setupCounters();
        this.setupProgressBars();
        this.setupParallax();
        this.setupTypewriter();
        this.setupParticles();
    }

    // Scroll-triggered animations
    setupScrollAnimations() {
        const animationOptions = {
            root: null,
            rootMargin: '0px 0px -100px 0px',
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const element = entry.target;
                    const animationType = element.dataset.animate || 'fadeInUp';
                    const delay = element.dataset.delay || 0;
                    
                    setTimeout(() => {
                        this.triggerAnimation(element, animationType);
                    }, delay);
                    
                    observer.unobserve(element);
                }
            });
        }, animationOptions);

        // Observe all elements with animation classes
        document.querySelectorAll('.animate-on-scroll, [data-animate]').forEach(el => {
            observer.observe(el);
        });

        this.observers.set('scroll', observer);
    }

    // Trigger specific animations
    triggerAnimation(element, type = 'fadeInUp') {
        element.style.opacity = '1';
        element.style.transform = 'none';
        
        switch (type) {
            case 'fadeInUp':
                element.style.animation = 'fadeInUp 0.8s ease-out forwards';
                break;
            case 'fadeInDown':
                element.style.animation = 'fadeInDown 0.8s ease-out forwards';
                break;
            case 'slideInLeft':
                element.style.animation = 'slideInLeft 0.8s ease-out forwards';
                break;
            case 'slideInRight':
                element.style.animation = 'slideInRight 0.8s ease-out forwards';
                break;
            case 'scaleIn':
                element.style.animation = 'scaleIn 0.6s ease-out forwards';
                break;
            case 'rotateIn':
                element.style.animation = 'rotateIn 0.8s ease-out forwards';
                break;
            case 'bounceIn':
                element.style.animation = 'bounceIn 1s ease-out forwards';
                break;
        }
    }

    // Advanced hover effects
    setupHoverEffects() {
        // Card hover effects with 3D transform
        document.querySelectorAll('.service-card, .pricing-card, .testimonial-card').forEach(card => {
            card.addEventListener('mouseenter', (e) => {
                this.addCardHoverEffect(e.target);
            });
            
            card.addEventListener('mouseleave', (e) => {
                this.removeCardHoverEffect(e.target);
            });
            
            // Add mouse move effect for subtle 3D
            card.addEventListener('mousemove', (e) => {
                this.addMouseMoveEffect(e, card);
            });
        });

        // Button ripple effect
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.createRippleEffect(e, btn);
            });
        });
    }

    // Card hover with 3D effect
    addCardHoverEffect(card) {
        card.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        card.style.transform = 'translateY(-8px) scale(1.02)';
        card.style.boxShadow = '0 25px 50px -12px rgba(0, 0, 0, 0.25)';
    }

    removeCardHoverEffect(card) {
        card.style.transform = 'translateY(0) scale(1)';
        card.style.boxShadow = '0 10px 15px -3px rgba(0, 0, 0, 0.1)';
    }

    // Mouse move 3D effect
    addMouseMoveEffect(e, card) {
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        
        const rotateX = (y - centerY) / 10;
        const rotateY = (centerX - x) / 10;
        
        card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-8px) scale(1.02)`;
    }

    // Ripple effect for buttons
    createRippleEffect(e, button) {
        const ripple = document.createElement('span');
        const rect = button.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        
        ripple.style.cssText = `
            position: absolute;
            top: ${y}px;
            left: ${x}px;
            width: ${size}px;
            height: ${size}px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        `;
        
        button.style.position = 'relative';
        button.style.overflow = 'hidden';
        button.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    // Animated counters
    setupCounters() {
        const counters = document.querySelectorAll('[data-counter]');
        
        counters.forEach(counter => {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.animateCounter(counter);
                        observer.unobserve(counter);
                    }
                });
            });
            
            observer.observe(counter);
        });
    }

    animateCounter(element) {
        const target = parseInt(element.dataset.counter);
        const duration = parseInt(element.dataset.duration) || 2000;
        const increment = target / (duration / 16);
        let current = 0;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            element.textContent = Math.floor(current).toLocaleString();
        }, 16);
    }

    // Progress bars animation
    setupProgressBars() {
        const progressBars = document.querySelectorAll('.progress-bar');
        
        progressBars.forEach(bar => {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const progress = bar.dataset.progress;
                        bar.style.width = `${progress}%`;
                        observer.unobserve(bar);
                    }
                });
            });
            
            observer.observe(bar);
        });
    }

    // Parallax scrolling effect
    setupParallax() {
        const parallaxElements = document.querySelectorAll('.parallax');
        
        if (parallaxElements.length === 0) return;
        
        const handleScroll = () => {
            const scrolled = window.pageYOffset;
            const rate = scrolled * -0.5;
            
            parallaxElements.forEach(element => {
                const speed = element.dataset.speed || 0.5;
                const yPos = -(scrolled * speed);
                element.style.transform = `translateY(${yPos}px)`;
            });
        };
        
        window.addEventListener('scroll', this.throttle(handleScroll, 16));
    }

    // Typewriter effect
    setupTypewriter() {
        const typewriters = document.querySelectorAll('.typewriter');
        
        typewriters.forEach(element => {
            const text = element.textContent;
            const speed = parseInt(element.dataset.speed) || 100;
            
            element.textContent = '';
            element.style.borderRight = '2px solid currentColor';
            
            let i = 0;
            const timer = setInterval(() => {
                if (i < text.length) {
                    element.textContent += text.charAt(i);
                    i++;
                } else {
                    clearInterval(timer);
                    // Remove cursor after typing is complete
                    setTimeout(() => {
                        element.style.borderRight = 'none';
                    }, 1000);
                }
            }, speed);
        });
    }

    // Particle system for hero background
    setupParticles() {
        const heroSection = document.querySelector('.hero');
        if (!heroSection) return;
        
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        canvas.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
            opacity: 0.1;
        `;
        
        heroSection.appendChild(canvas);
        
        // Resize canvas
        const resizeCanvas = () => {
            canvas.width = heroSection.offsetWidth;
            canvas.height = heroSection.offsetHeight;
        };
        
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);
        
        // Particle system
        const particles = [];
        const particleCount = 50;
        
        class Particle {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.vx = (Math.random() - 0.5) * 0.5;
                this.vy = (Math.random() - 0.5) * 0.5;
                this.size = Math.random() * 2 + 1;
                this.opacity = Math.random() * 0.5 + 0.2;
            }
            
            update() {
                this.x += this.vx;
                this.y += this.vy;
                
                if (this.x < 0) this.x = canvas.width;
                if (this.x > canvas.width) this.x = 0;
                if (this.y < 0) this.y = canvas.height;
                if (this.y > canvas.height) this.y = 0;
            }
            
            draw() {
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(255, 255, 255, ${this.opacity})`;
                ctx.fill();
            }
        }
        
        // Create particles
        for (let i = 0; i < particleCount; i++) {
            particles.push(new Particle());
        }
        
        // Animation loop
        const animate = () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            particles.forEach(particle => {
                particle.update();
                particle.draw();
            });
            
            // Draw connections
            particles.forEach((particle, i) => {
                particles.slice(i + 1).forEach(otherParticle => {
                    const dx = particle.x - otherParticle.x;
                    const dy = particle.y - otherParticle.y;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    
                    if (distance < 100) {
                        ctx.beginPath();
                        ctx.moveTo(particle.x, particle.y);
                        ctx.lineTo(otherParticle.x, otherParticle.y);
                        ctx.strokeStyle = `rgba(255, 255, 255, ${0.1 * (1 - distance / 100)})`;
                        ctx.lineWidth = 1;
                        ctx.stroke();
                    }
                });
            });
            
            requestAnimationFrame(animate);
        };
        
        animate();
    }

    // Utility: Throttle function
    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    // Add custom CSS animations
    injectAnimationCSS() {
        const style = document.createElement('style');
        style.textContent = `
            @keyframes scaleIn {
                from {
                    opacity: 0;
                    transform: scale(0.8);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }
            
            @keyframes rotateIn {
                from {
                    opacity: 0;
                    transform: rotate(-180deg) scale(0.8);
                }
                to {
                    opacity: 1;
                    transform: rotate(0deg) scale(1);
                }
            }
            
            @keyframes bounceIn {
                0% {
                    opacity: 0;
                    transform: scale(0.3);
                }
                50% {
                    transform: scale(1.1);
                }
                70% {
                    transform: scale(0.9);
                }
                100% {
                    opacity: 1;
                    transform: scale(1);
                }
            }
            
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            
            .animate-on-scroll {
                opacity: 0;
                transform: translateY(30px);
                transition: all 0.8s ease-out;
            }
            
            .progress-bar {
                width: 0%;
                height: 100%;
                background: linear-gradient(90deg, var(--accent-gold), var(--accent-light-gold));
                border-radius: inherit;
                transition: width 2s ease-out;
            }
            
            .typewriter {
                overflow: hidden;
                white-space: nowrap;
                animation: blink-caret 1s step-end infinite;
            }
            
            @keyframes blink-caret {
                from, to { border-color: transparent; }
                50% { border-color: currentColor; }
            }
        `;
        
        document.head.appendChild(style);
    }
}

// Initialize animation engine when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const animationEngine = new AnimationEngine();
    animationEngine.injectAnimationCSS();
    
    // Make it globally available
    window.animationEngine = animationEngine;
});

// Smooth page transitions
class PageTransitions {
    constructor() {
        this.setupTransitions();
    }
    
    setupTransitions() {
        // Add transition overlay
        const overlay = document.createElement('div');
        overlay.id = 'page-transition-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-navy), var(--primary-blue));
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        `;
        overlay.innerHTML = '<i class="fas fa-spinner fa-spin text-4xl"></i>';
        document.body.appendChild(overlay);
        
        // Handle all internal links
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a[href]');
            if (link && this.isInternalLink(link.href)) {
                e.preventDefault();
                this.transitionToPage(link.href);
            }
        });
    }
    
    isInternalLink(url) {
        const link = new URL(url, window.location.origin);
        return link.origin === window.location.origin && 
               !url.includes('#') && 
               !url.includes('mailto:') && 
               !url.includes('tel:');
    }
    
    transitionToPage(url) {
        const overlay = document.getElementById('page-transition-overlay');
        overlay.style.opacity = '1';
        overlay.style.visibility = 'visible';
        
        setTimeout(() => {
            window.location.href = url;
        }, 300);
    }
}

// Initialize page transitions
document.addEventListener('DOMContentLoaded', () => {
    new PageTransitions();
});

// Export for other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { AnimationEngine, PageTransitions };
}