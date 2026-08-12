<?php
// Page variables
$current_page = 'signup';
$page_title = 'Join Matrix - Create Account | HiveNest';
$page_description = 'Create your HiveNest account and join the digital revolution. Access cutting-edge hosting and digital services.';
$page_keywords = 'signup, create account, join matrix, hivenest registration, cyberpunk hosting';

// Page-specific JavaScript
$page_scripts = "
// Signup form handling
document.getElementById('signup-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Get form data
    const name = document.getElementById('name').value;
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm-password').value;
    
    // Validation
    if (password !== confirm) {
        console.warn('Neural patterns do not match. Please verify your access code.');
        return;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type=\"submit\"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class=\"fas fa-spinner fa-spin\"></i> INITIALIZING MATRIX...';
    submitBtn.disabled = true;
    
    // Simulate API call
    setTimeout(() => {
        if (name && email && password) {
            console.info('Neural ID created successfully! Welcome to the HiveNest Matrix.');
            window.location.href = 'login.php';
        } else {
            console.error('Matrix initialization failed. Please verify all neural parameters.');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    }, 2000);
});
";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'utilities/head.php'; ?>
</head>
<body>
<?php include 'utilities/nav.php'; ?>

<?php include 'utilities/mobile-menu.php'; ?>

    <!-- Signup Section -->
    <section class="section" style="background-image: url('assets/images/heroes/hero-security-circuit.jpg'); background-size: cover; background-position: center; background-attachment: fixed; position: relative; padding: 4rem 0;">
        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1;"></div>
        
        <div style="position: relative; z-index: 2;">
            <div class="container">
                <div style="max-width: 600px; margin: 0 auto; background: rgba(0,0,0,0.8); padding: 3rem; border-radius: 16px; border: 1px solid rgba(0,255,255,0.3);">
                    <div class="text-center mb-8">
                        <h1 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">
                            <i class="fas fa-user-plus" style="margin-right: 1rem;"></i>
                            JOIN THE MATRIX
                        </h1>
                        <p style="color: rgba(255,255,255,0.8);">
                            Create your neural ID and enter the digital revolution
                        </p>
                    </div>
                    
                    <form id="signup-form">
                        <div style="margin-bottom: 1.5rem;">
                            <label for="name" style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                                Neural Handle (Full Name)
                            </label>
                            <input 
                                type="text" 
                                id="name" 
                                name="name" 
                                required
                                style="width: 100%; padding: 16px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; color: white; font-size: 1rem;"
                                placeholder="Enter your full name"
                            >
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <label for="email" style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                                Neural ID (Email)
                            </label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                required
                                style="width: 100%; padding: 16px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; color: white; font-size: 1rem;"
                                placeholder="Enter your email address"
                            >
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <label for="password" style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                                Access Code (Password)
                            </label>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                required
                                style="width: 100%; padding: 16px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; color: white; font-size: 1rem;"
                                placeholder="Create a strong password"
                            >
                        </div>
                        
                        <div style="margin-bottom: 2rem;">
                            <label for="confirm-password" style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                                Confirm Access Code
                            </label>
                            <input 
                                type="password" 
                                id="confirm-password" 
                                name="confirm-password" 
                                required
                                style="width: 100%; padding: 16px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; color: white; font-size: 1rem;"
                                placeholder="Confirm your password"
                            >
                        </div>
                        
                        <div style="margin-bottom: 2rem;">
                            <label style="display: flex; align-items: center; color: rgba(255,255,255,0.8);">
                                <input type="checkbox" name="terms" required style="margin-right: 0.5rem;">
                                I accept the <a href="legal/terms-of-service.php" style="color: var(--cyber-neon-pink);">Neural Agreement</a> and <a href="legal/privacy-policy.php" style="color: var(--cyber-neon-pink);">Privacy Protocol</a>
                            </label>
                        </div>
                        
                        <button 
                            type="submit" 
                            class="btn btn-primary" 
                            style="width: 100%; padding: 16px; font-size: 1.1rem; margin-bottom: 1rem;"
                        >
                            <i class="fas fa-rocket" style="margin-right: 0.5rem;"></i>
                            INITIALIZE NEURAL ID
                        </button>
                        
                        <div style="text-align: center;">
                            <p style="color: rgba(255,255,255,0.8); margin-bottom: 1rem;">
                                Already have a neural ID?
                            </p>
                            <a href="login.php" class="btn btn-secondary" style="padding: 12px 24px;">
                                ACCESS PORTAL
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
        <div class="container">
            <div class="text-center mb-8">
                <h2>MATRIX MEMBERSHIP BENEFITS</h2>
                <p class="hero-subtitle">Unlock the full power of the digital revolution</p>
            </div>
            
            <div class="services-grid">
                <div class="cyber-card">
                    <i class="fas fa-crown service-icon"></i>
                    <h3 class="service-title">PREMIUM ACCESS</h3>
                    <p class="service-description">
                        Exclusive access to premium services, priority support, and advanced features.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-shield-alt service-icon"></i>
                    <h3 class="service-title">QUANTUM SECURITY</h3>
                    <p class="service-description">
                        Military-grade encryption and advanced security protocols for all your data.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-headset service-icon"></i>
                    <h3 class="service-title">24/7 NEURAL SUPPORT</h3>
                    <p class="service-description">
                        Round-the-clock expert support from our cyberpunk technical specialists.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-rocket service-icon"></i>
                    <h3 class="service-title">EARLY ACCESS</h3>
                    <p class="service-description">
                        First access to new features, services, and cutting-edge digital tools.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-coins service-icon"></i>
                    <h3 class="service-title">NEURAL REWARDS</h3>
                    <p class="service-description">
                        Earn points for every purchase and unlock exclusive discounts and bonuses.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-chart-line service-icon"></i>
                    <h3 class="service-title">ANALYTICS MATRIX</h3>
                    <p class="service-description">
                        Advanced analytics and insights to optimize your digital empire's performance.
                    </p>
                </div>
            </div>
        </div>
    </section>

<?php include 'utilities/footer.php'; ?>

<?php include 'utilities/scripts.php'; ?>
</body>
</html>
