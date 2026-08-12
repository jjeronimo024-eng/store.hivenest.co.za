<?php
// Page variables
$current_page = 'contact';
$page_title = 'Contact HiveNest Matrix - Digital Command Center';
$page_description = 'Contact HiveNest Matrix - Connect with our digital warriors across multiple dimensions. Get support, sales, and neural guidance.';
$page_keywords = 'contact hivenest, cyberpunk support, digital contact, neural network support';

// Page-specific JavaScript (form handler is in main.js)
$page_scripts = "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'utilities/head.php'; ?>
<link rel="stylesheet" href="assets/css/live-chat.css">
</head>
<body>
<?php include 'utilities/nav.php'; ?>

<?php include 'utilities/mobile-menu.php'; ?>

    <!-- Hero Section -->
    <section class="hero" style="min-height: 70vh;">
        <img src="assets/images/heroes/hero-contact.jpg" alt="Contact Matrix" class="hero-background">
        
        <div class="hero-content" style="grid-template-columns: 1fr; text-align: center;">
            <div class="hero-text">
                <h1 class="hero-title">
                    INITIALIZE<br>
                    <span class="cyber-text">NEURAL LINK</span>
                </h1>
                <p class="hero-subtitle">
                    Connect with our digital warriors across multiple dimensions. Get support, sales, and neural guidance.
                </p>
            </div>
        </div>
    </section>

    <!-- Contact Form -->
    <section class="section">
        <div class="container">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: start;">
                <div>
                    <h2>SEND NEURAL TRANSMISSION</h2>
                    <p class="hero-subtitle" style="margin-bottom: 2rem;">
                        Send us a message and we'll respond within the neural network timeframe.
                    </p>
                    
                    <form id="contact-form" class="contact-form" style="background: rgba(0,0,0,0.6); padding: 2rem; border-radius: 16px; border: 1px solid rgba(0,255,255,0.3);">
                        <div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden;">
                            <label for="website_url">Leave this field empty</label>
                            <input id="website_url" type="text" name="website_url" tabindex="-1" autocomplete="off">
                        </div>
                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                                Neural Identity
                            </label>
                            <input 
                                type="text" 
                                name="name" 
                                placeholder="Your name" 
                                style="width: 100%; padding: 16px; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white;"
                                required
                            >
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                                Digital Address
                            </label>
                            <input 
                                type="email" 
                                name="email" 
                                placeholder="your@email.com" 
                                style="width: 100%; padding: 16px; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white;"
                                required
                            >
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                                Subject Protocol
                            </label>
                            <select name="subject" style="width: 100%; padding: 16px; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white;">
                                <option value="general">General Inquiry</option>
                                <option value="sales">Sales Request</option>
                                <option value="support">Technical Support</option>
                                <option value="billing">Billing Question</option>
                                <option value="partnership">Partnership Proposal</option>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 2rem;">
                            <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                                Neural Message
                            </label>
                            <textarea 
                                name="message" 
                                rows="6" 
                                placeholder="Your message..." 
                                style="width: 100%; padding: 16px; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white; resize: vertical;"
                                required
                            ></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 16px; font-size: 1.1rem;">
                            <i class="fas fa-rocket" style="margin-right: 0.5rem;"></i>
                            TRANSMIT MESSAGE
                        </button>
                        
                        <div id="form-result" style="margin-top: 1rem; padding: 1rem; border-radius: 8px; display: none;"></div>
                    </form>
                </div>
                
                <div>
                    <h2>NEURAL COORDINATES</h2>
                    <p class="hero-subtitle" style="margin-bottom: 2rem;">
                        Multiple contact channels across different dimensions.
                    </p>
                    
                    <div class="services-grid" style="grid-template-columns: 1fr; gap: 1.5rem;">
                        <div class="cyber-card">
                            <i class="fas fa-comments service-icon"></i>
                            <h3 class="service-title">LIVE NEURAL CHAT</h3>
                            <p class="service-description">
                                Real-time communication with our digital warriors. 
                                Available 24/7 across all dimensions.
                            </p>
                            <a href="#" id="start-live-chat" class="btn btn-outline" style="margin-top: 1rem;">START CHAT</a>
                        </div>
                        
                        <div class="cyber-card">
                            <i class="fas fa-envelope service-icon"></i>
                            <h3 class="service-title">EMAIL PORTAL</h3>
                            <p class="service-description">
                                Direct email communication for detailed inquiries and support requests.
                            </p>
                            <a href="mailto:support@hivenest.co.za" class="btn btn-outline" style="margin-top: 1rem;">SEND EMAIL</a>
                        </div>
                        
                        <div class="cyber-card">
                            <i class="fas fa-phone service-icon"></i>
                            <h3 class="service-title">VOICE LINK</h3>
                            <p class="service-description">
                                Direct voice communication for urgent matters and complex discussions.
                            </p>
                            <a href="tel:+27123456789" class="btn btn-outline" style="margin-top: 1rem;">CALL NOW</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Support Center -->
    <section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
        <div class="container">
            <div class="text-center mb-8">
                <h2>NEURAL SUPPORT CENTER</h2>
                <p class="hero-subtitle">24/7 support across all digital dimensions</p>
            </div>
            
            <div class="services-grid">
                <div class="cyber-card">
                    <i class="fas fa-headset service-icon"></i>
                    <h3 class="service-title">TECHNICAL SUPPORT</h3>
                    <p class="service-description">
                        Expert technical assistance for hosting, domains, and digital services.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-shopping-cart service-icon"></i>
                    <h3 class="service-title">SALES CONSULTATION</h3>
                    <p class="service-description">
                        Get personalized recommendations for your digital expansion needs.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-credit-card service-icon"></i>
                    <h3 class="service-title">BILLING SUPPORT</h3>
                    <p class="service-description">
                        Assistance with billing, payments, and account management.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-tools service-icon"></i>
                    <h3 class="service-title">MIGRATION SERVICES</h3>
                    <p class="service-description">
                        Free migration assistance for moving your digital assets to our matrix.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <div class="live-chat-modal" id="live-chat-modal" hidden>
        <section class="live-chat-window" role="dialog" aria-modal="true" aria-labelledby="live-chat-title">
            <header class="live-chat-head">
                <strong id="live-chat-title"><i class="fas fa-comments"></i> HIVENEST LIVE SUPPORT</strong>
                <button class="live-chat-close" type="button" data-chat-close aria-label="Close chat window">&times;</button>
            </header>
            <div id="live-chat-start-view" class="live-chat-body">
                <p>Enter your details and message. The CRM support queue will alert an available agent.</p>
                <form id="live-chat-start-form" class="live-chat-form">
                    <input name="website_url" type="text" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-10000px">
                    <input name="name" type="text" maxlength="150" autocomplete="name" placeholder="Your name" required>
                    <input name="email" type="email" maxlength="255" autocomplete="email" placeholder="Email address" required>
                    <select name="subject"><option value="general">General inquiry</option><option value="sales">Sales</option><option value="support">Technical support</option><option value="billing">Billing</option></select>
                    <textarea name="message" maxlength="4000" placeholder="How can we help?" required></textarea>
                    <button class="btn btn-primary" type="submit">JOIN SUPPORT QUEUE</button>
                </form>
            </div>
            <div id="live-chat-conversation" hidden>
                <div class="live-chat-status" id="live-chat-status" data-state="waiting">Waiting for a support agent · 00:00</div>
                <div class="live-chat-messages" id="live-chat-messages" aria-live="polite"></div>
                <form class="live-chat-compose" id="live-chat-compose">
                    <input name="message" maxlength="4000" autocomplete="off" placeholder="Type your message…" required>
                    <button class="btn btn-primary btn-sm" type="submit">SEND</button>
                </form>
                <div class="live-chat-actions">
                    <button class="btn btn-outline btn-sm" id="live-chat-end" type="button">END CHAT</button>
                    <button class="btn btn-outline btn-sm" id="live-chat-new" type="button">NEW CHAT</button>
                </div>
            </div>
            <div class="live-chat-error live-chat-body" id="live-chat-error" role="status" aria-live="polite"></div>
        </section>
    </div>

<?php include 'utilities/footer.php'; ?>

<?php include 'utilities/scripts.php'; ?>
<script src="assets/js/live-chat.js"></script>
</body>
</html>
