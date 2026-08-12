<?php
// Page variables
$current_page = 'auth';
$page_title = 'Neural Access - Login & Sign Up | HiveNest Matrix';
$page_description = 'Access your HiveNest account or create a new neural ID to join the digital revolution.';
$page_keywords = 'login, signup, access portal, user panel, cyberpunk login, hivenest account, create account, join matrix';

// Page-specific JavaScript
$page_scripts = "
function safeAuthReturn() {
    const target = new URLSearchParams(window.location.search).get('return') || '';
    if (!target) return '';
    try {
        const parsed = new URL(target, window.location.origin);
        if (parsed.origin === window.location.origin) {
            return parsed.pathname + parsed.search + parsed.hash;
        }
        if (parsed.protocol === 'https:' && parsed.hostname === 'cp.hivenest.co.za') {
            return parsed.href;
        }
    } catch (error) {
        console.error('Invalid authentication return URL:', error);
    }
    return '';
}

function updateAuthLocation(mode) {
    const params = new URLSearchParams(window.location.search);
    params.set('mode', mode);
    window.history.replaceState({}, '', 'auth.php?' + params.toString());
}

function setAuthStatus(form, message, type) {
    const status = form ? form.querySelector('[data-auth-status]') : null;
    if (!status) return;
    status.textContent = message || '';
    status.style.display = message ? 'block' : 'none';
    status.style.color = type === 'success' ? '#8dffb7' : '#ff9abb';
    status.style.background = type === 'success' ? 'rgba(16,185,129,.14)' : 'rgba(239,68,68,.14)';
    status.style.border = '1px solid ' + (type === 'success' ? 'rgba(16,185,129,.55)' : 'rgba(239,68,68,.55)');
    status.setAttribute('role', type === 'error' ? 'alert' : 'status');
}

// Auth form toggle functionality - Make it globally accessible
window.toggleAuthMode = function(mode) {
    const loginForm = document.getElementById('login-form');
    const signupForm = document.getElementById('signup-form');
    const loginTab = document.getElementById('login-tab');
    const signupTab = document.getElementById('signup-tab');
    const authTitle = document.getElementById('auth-title');
    const authSubtitle = document.getElementById('auth-subtitle');
    
    if (mode === 'login') {
        if (loginForm) loginForm.style.display = 'block';
        if (signupForm) signupForm.style.display = 'none';
        if (loginTab) loginTab.classList.add('active');
        if (signupTab) signupTab.classList.remove('active');
        if (authTitle) authTitle.innerHTML = '<i class=\'fas fa-lock\' style=\'margin-right: 1rem;\'></i>ACCESS PORTAL';
        if (authSubtitle) authSubtitle.textContent = 'Enter your neural credentials to access the HiveNest matrix';
        updateAuthLocation('login');
    } else {
        if (loginForm) loginForm.style.display = 'none';
        if (signupForm) signupForm.style.display = 'block';
        if (loginTab) loginTab.classList.remove('active');
        if (signupTab) signupTab.classList.add('active');
        if (authTitle) authTitle.innerHTML = '<i class=\'fas fa-user-plus\' style=\'margin-right: 1rem;\'></i>JOIN THE MATRIX';
        if (authSubtitle) authSubtitle.textContent = 'Create your neural ID and enter the digital revolution';
        updateAuthLocation('signup');
    }
};

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const mode = urlParams.get('mode');
    
    if (mode === 'signup') {
        window.toggleAuthMode('signup');
    } else {
        window.toggleAuthMode('login');
    }
    
    const loginFormEl = document.getElementById('login-form');
    if (loginFormEl) {
        let twoFactorChallenge = '';
        loginFormEl.addEventListener('submit', function(e) {
            e.preventDefault();
            setAuthStatus(this, '', 'error');
            
            const email = document.getElementById('login-email').value;
            const password = document.getElementById('login-password').value;
            const twoFactorCode = document.getElementById('login-two-factor-code')?.value || '';
            
            const submitBtn = this.querySelector('button[type=\'submit\']');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class=\'fas fa-spinner fa-spin\'></i> ACCESSING MATRIX...';
            submitBtn.disabled = true;
            
            const endpoint = twoFactorChallenge
                ? '/api/customer-auth.php?action=verify-2fa'
                : '/api/customer-auth.php?action=login';
            const payload = twoFactorChallenge
                ? {challenge_token: twoFactorChallenge, code: twoFactorCode}
                : {email, password};
            fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            }).then(async response => {
                const data = await response.json();
                if (response.ok && data.two_factor_required) {
                    twoFactorChallenge = data.challenge_token || '';
                    document.getElementById('login-credentials').style.display = 'none';
                    document.getElementById('login-two-factor').style.display = 'block';
                    document.getElementById('login-two-factor-code').required = true;
                    document.getElementById('login-two-factor-code').focus();
                    setAuthStatus(loginFormEl, data.message || 'Enter your authenticator code.', 'success');
                    return;
                }
                if (!response.ok || !data.authenticated) throw new Error(data.error || 'Login failed');
                window.location.href = safeAuthReturn() || 'https://cp.hivenest.co.za';
            }).catch(error => {
                console.error(error.message);
                setAuthStatus(loginFormEl, error.message || 'Login failed.', 'error');
            }).finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }

    const signupFormEl = document.getElementById('signup-form');
    if (signupFormEl) {
        signupFormEl.addEventListener('submit', function(e) {
            e.preventDefault();
            setAuthStatus(this, '', 'error');
            
            const firstName = document.getElementById('signup-first-name').value;
            const lastName = document.getElementById('signup-last-name').value;
            const email = document.getElementById('signup-email').value;
            const password = document.getElementById('signup-password').value;
            const confirm = document.getElementById('signup-confirm-password').value;
            
            if (password !== confirm) {
                setAuthStatus(signupFormEl, 'Passwords do not match.', 'error');
                return;
            }
            if (
                password.length < 12
                || !/[A-Z]/.test(password)
                || !/[a-z]/.test(password)
                || !/\d/.test(password)
                || !/[^A-Za-z0-9]/.test(password)
            ) {
                setAuthStatus(signupFormEl, 'Use at least 12 characters with uppercase, lowercase, a number, and a symbol.', 'error');
                return;
            }
            
            const submitBtn = this.querySelector('button[type=\'submit\']');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class=\'fas fa-spinner fa-spin\'></i> INITIALIZING MATRIX...';
            submitBtn.disabled = true;
            
            fetch('/api/customer-auth.php?action=register', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    email: email,
                    password: password,
                    first_name: firstName,
                    last_name: lastName,
                    company_name: document.getElementById('signup-company').value,
                    phone: document.getElementById('signup-phone').value,
                    address_line1: document.getElementById('signup-address1').value,
                    address_line2: document.getElementById('signup-address2').value,
                    city: document.getElementById('signup-city').value,
                    state: document.getElementById('signup-state').value,
                    postal_code: document.getElementById('signup-postal-code').value,
                    country: document.getElementById('signup-country').value,
                    country_code: document.getElementById('signup-country-code').value
                })
            }).then(async response => {
                const data = await response.json();
                if (!response.ok || !data.authenticated) throw new Error(data.error || 'Account creation failed');
                window.location.href = safeAuthReturn() || 'checkout.php';
            }).catch(error => {
                console.error(error.message);
                setAuthStatus(signupFormEl, error.message || 'Account creation failed.', 'error');
            }).finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
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

    <!-- Auth Section -->
    <section class="hero auth-hero">
        <img src="assets/images/heroes/hero-auth-portal.jpg" alt="Digital Access Portal" class="hero-background">
        
        <div class="hero-content auth-hero-content">
            <div class="container">
                <div class="auth-card" style="max-width: 600px; margin: 0 auto; background: rgba(0,0,0,0.8); padding: 3rem; border-radius: 16px; border: 1px solid rgba(0,255,255,0.3);">
                    <!-- Auth Tabs -->
                    <div style="display: flex; margin-bottom: 2rem; border-bottom: 1px solid rgba(255,255,255,0.2);">
                        <button id="login-tab" onclick="toggleAuthMode('login')" 
                            class="auth-tab active" 
                            style="flex: 1; padding: 15px; background: none; border: none; color: var(--cyber-neon-cyan); font-weight: 600; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: all 0.3s ease;">
                            <i class="fas fa-sign-in-alt" style="margin-right: 0.5rem;"></i>
                            LOGIN
                        </button>
                        <button id="signup-tab" onclick="toggleAuthMode('signup')" 
                            class="auth-tab" 
                            style="flex: 1; padding: 15px; background: none; border: none; color: rgba(255,255,255,0.6); font-weight: 600; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: all 0.3s ease;">
                            <i class="fas fa-user-plus" style="margin-right: 0.5rem;"></i>
                            SIGN UP
                        </button>
                    </div>
                    
                    <div class="text-center mb-8">
                        <h1 id="auth-title" style="color: var(--cyber-neon-cyan); margin-bottom: 1rem;">
                            <i class="fas fa-lock" style="margin-right: 1rem;"></i>
                            ACCESS PORTAL
                        </h1>
                        <p id="auth-subtitle" style="color: rgba(255,255,255,0.8);">
                            Enter your neural credentials to access the HiveNest matrix
                        </p>
                    </div>
                    
                    <!-- Login Form -->
                    <form id="login-form" style="display: block;">
                        <div id="login-credentials">
                        <div style="margin-bottom: 1.5rem;">
                            <label for="login-email" style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                                Neural ID (Email)
                            </label>
                            <input 
                                type="email" 
                                id="login-email" 
                                name="email" 
                                required
                                style="width: 100%; padding: 16px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; color: white; font-size: 1rem;"
                                placeholder="Enter your email address"
                            >
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <label for="login-password" style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                                Access Code (Password)
                            </label>
                            <input 
                                type="password" 
                                id="login-password" 
                                name="password" 
                                required
                                style="width: 100%; padding: 16px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; color: white; font-size: 1rem;"
                                placeholder="Enter your password"
                            >
                        </div>
                        </div>

                        <div id="login-two-factor" style="display:none;margin-bottom:1.5rem;">
                            <label for="login-two-factor-code" style="display:block;color:var(--cyber-neon-cyan);margin-bottom:.5rem;font-weight:600;">
                                Authenticator or Recovery Code
                            </label>
                            <input type="text" id="login-two-factor-code" inputmode="numeric" autocomplete="one-time-code"
                                style="width:100%;padding:16px;background:rgba(0,0,0,.5);border:1px solid rgba(0,255,255,.3);border-radius:8px;color:white;font-size:1rem;"
                                placeholder="6-digit code or recovery code">
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                            <label style="display: flex; align-items: center; color: rgba(255,255,255,0.8);">
                                <input type="checkbox" name="remember" style="margin-right: 0.5rem;">
                                Remember neural pattern
                            </label>
                            <a href="forgot-password.php" style="color: var(--cyber-neon-pink); text-decoration: none;">
                                Reset access code?
                            </a>
                        </div>
                        
                        <button 
                            type="submit" 
                            class="btn btn-primary" 
                            style="width: 100%; padding: 16px; font-size: 1.1rem;"
                        >
                            <i class="fas fa-sign-in-alt" style="margin-right: 0.5rem;"></i>
                            INITIALIZE ACCESS
                        </button>
                        <div data-auth-status style="display:none;margin-top:1rem;padding:12px;border-radius:8px;"></div>
                    </form>
                    
                    <!-- Signup Form -->
                    <form id="signup-form" style="display: none;">
                        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem; margin-bottom:1.5rem;">
                            <div>
                                <label for="signup-first-name" style="display:block;color:var(--cyber-neon-cyan);margin-bottom:.5rem;font-weight:600;">First Name</label>
                                <input type="text" id="signup-first-name" name="first_name" autocomplete="given-name" required style="width:100%;padding:16px;background:rgba(0,0,0,.5);border:1px solid rgba(0,255,255,.3);border-radius:8px;color:white;font-size:1rem;">
                            </div>
                            <div>
                                <label for="signup-last-name" style="display:block;color:var(--cyber-neon-cyan);margin-bottom:.5rem;font-weight:600;">Last Name</label>
                                <input type="text" id="signup-last-name" name="last_name" autocomplete="family-name" required style="width:100%;padding:16px;background:rgba(0,0,0,.5);border:1px solid rgba(0,255,255,.3);border-radius:8px;color:white;font-size:1rem;">
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <label for="signup-email" style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                                Neural ID (Email)
                            </label>
                            <input 
                                type="email" 
                                id="signup-email" 
                                name="email" 
                                required
                                style="width: 100%; padding: 16px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; color: white; font-size: 1rem;"
                                placeholder="Enter your email address"
                            >
                        </div>

                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:1.5rem;">
                            <div>
                                <label for="signup-company" style="display:block;color:var(--cyber-neon-cyan);margin-bottom:.5rem;font-weight:600;">Company (Optional)</label>
                                <input type="text" id="signup-company" autocomplete="organization" style="width:100%;padding:16px;background:rgba(0,0,0,.5);border:1px solid rgba(0,255,255,.3);border-radius:8px;color:white;font-size:1rem;">
                            </div>
                            <div>
                                <label for="signup-phone" style="display:block;color:var(--cyber-neon-cyan);margin-bottom:.5rem;font-weight:600;">Telephone</label>
                                <input type="tel" id="signup-phone" autocomplete="tel" required placeholder="+27..." style="width:100%;padding:16px;background:rgba(0,0,0,.5);border:1px solid rgba(0,255,255,.3);border-radius:8px;color:white;font-size:1rem;">
                            </div>
                        </div>

                        <div style="margin-bottom:1.5rem;">
                            <label for="signup-address1" style="display:block;color:var(--cyber-neon-cyan);margin-bottom:.5rem;font-weight:600;">Street Address</label>
                            <input type="text" id="signup-address1" autocomplete="address-line1" required style="width:100%;padding:16px;background:rgba(0,0,0,.5);border:1px solid rgba(0,255,255,.3);border-radius:8px;color:white;font-size:1rem;">
                        </div>
                        <div style="margin-bottom:1.5rem;">
                            <label for="signup-address2" style="display:block;color:var(--cyber-neon-cyan);margin-bottom:.5rem;font-weight:600;">Apartment, Suite or Unit (Optional)</label>
                            <input type="text" id="signup-address2" autocomplete="address-line2" style="width:100%;padding:16px;background:rgba(0,0,0,.5);border:1px solid rgba(0,255,255,.3);border-radius:8px;color:white;font-size:1rem;">
                        </div>

                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
                            <div><label for="signup-city" style="display:block;color:var(--cyber-neon-cyan);margin-bottom:.5rem;font-weight:600;">City</label><input type="text" id="signup-city" autocomplete="address-level2" required style="width:100%;padding:16px;background:rgba(0,0,0,.5);border:1px solid rgba(0,255,255,.3);border-radius:8px;color:white;font-size:1rem;"></div>
                            <div><label for="signup-state" style="display:block;color:var(--cyber-neon-cyan);margin-bottom:.5rem;font-weight:600;">Province / State</label><input type="text" id="signup-state" autocomplete="address-level1" required style="width:100%;padding:16px;background:rgba(0,0,0,.5);border:1px solid rgba(0,255,255,.3);border-radius:8px;color:white;font-size:1rem;"></div>
                            <div><label for="signup-postal-code" style="display:block;color:var(--cyber-neon-cyan);margin-bottom:.5rem;font-weight:600;">Postal Code</label><input type="text" id="signup-postal-code" autocomplete="postal-code" required style="width:100%;padding:16px;background:rgba(0,0,0,.5);border:1px solid rgba(0,255,255,.3);border-radius:8px;color:white;font-size:1rem;"></div>
                        </div>

                        <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-bottom:1.5rem;">
                            <div><label for="signup-country" style="display:block;color:var(--cyber-neon-cyan);margin-bottom:.5rem;font-weight:600;">Country</label><input type="text" id="signup-country" value="South Africa" autocomplete="country-name" required style="width:100%;padding:16px;background:rgba(0,0,0,.5);border:1px solid rgba(0,255,255,.3);border-radius:8px;color:white;font-size:1rem;"></div>
                            <div><label for="signup-country-code" style="display:block;color:var(--cyber-neon-cyan);margin-bottom:.5rem;font-weight:600;">Country Code</label><input type="text" id="signup-country-code" value="ZA" maxlength="3" autocomplete="country" required style="width:100%;padding:16px;background:rgba(0,0,0,.5);border:1px solid rgba(0,255,255,.3);border-radius:8px;color:white;font-size:1rem;text-transform:uppercase;"></div>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <label for="signup-password" style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                                Access Code (Password)
                            </label>
                            <input 
                                type="password" 
                                id="signup-password" 
                                name="password" 
                                minlength="12"
                                required
                                style="width: 100%; padding: 16px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; color: white; font-size: 1rem;"
                                placeholder="Create a strong password"
                            >
                            <small style="display:block;margin-top:.5rem;color:rgba(255,255,255,.65);">At least 12 characters with uppercase, lowercase, a number, and a symbol.</small>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <label for="signup-confirm-password" style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                                Confirm Access Code
                            </label>
                            <input 
                                type="password" 
                                id="signup-confirm-password" 
                                name="confirm-password" 
                                minlength="12"
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
                            style="width: 100%; padding: 16px; font-size: 1.1rem;"
                        >
                            <i class="fas fa-rocket" style="margin-right: 0.5rem;"></i>
                            INITIALIZE NEURAL ID
                        </button>
                        <div data-auth-status style="display:none;margin-top:1rem;padding:12px;border-radius:8px;"></div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Alternative Access Methods -->
    <section class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
        <div class="container">
            <div class="text-center mb-8">
                <h2>ALTERNATIVE ACCESS PROTOCOLS</h2>
                <p class="hero-subtitle">Multiple pathways to your digital realm</p>
            </div>
            
            <div class="services-grid">
                <div class="cyber-card">
                    <i class="fas fa-mobile-alt service-icon"></i>
                    <h3 class="service-title">MOBILE ACCESS</h3>
                    <p class="service-description">
                        Access your neural dashboard from any mobile device with quantum-encrypted connections.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-fingerprint service-icon"></i>
                    <h3 class="service-title">BIOMETRIC SCAN</h3>
                    <p class="service-description">
                        Advanced biometric authentication for enhanced security across all digital dimensions.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-qrcode service-icon"></i>
                    <h3 class="service-title">QUANTUM QR</h3>
                    <p class="service-description">
                        Two-factor authentication with quantum QR codes for maximum neural security protocols.
                    </p>
                </div>
            </div>
        </div>
    </section>

<?php include 'utilities/footer.php'; ?>

<?php include 'utilities/scripts.php'; ?>

<style>
.auth-hero {
    min-height: 100vh;
    height: auto;
    align-items: flex-start;
    padding: 100px 0 50px;
    box-sizing: border-box;
    overflow: hidden;
}

.auth-hero-content {
    width: 100%;
    box-sizing: border-box;
}

.auth-tab.active {
    color: var(--cyber-neon-cyan) !important;
    border-bottom: 2px solid var(--cyber-neon-cyan);
    text-shadow: 0 0 10px var(--cyber-neon-cyan);
}

.auth-tab:hover {
    color: var(--cyber-neon-pink) !important;
    text-shadow: 0 0 10px var(--cyber-neon-pink);
}

@media (max-width: 767px) {
    .auth-hero {
        padding: 90px 0 30px;
    }

    .auth-hero-content {
        padding-inline: 12px;
    }

    .auth-card {
        padding: 1.5rem !important;
    }
}
</style>

</body>
</html>
