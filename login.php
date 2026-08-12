<?php
declare(strict_types=1);

require_once __DIR__ . '/utilities/customer_session.php';
hivenest_customer_session_configure();
session_start();

// Redirect already-authenticated customers to their account
$sessionId = (int)($_SESSION['customer_id'] ?? 0);
if ($sessionId > 0) {
    require_once __DIR__ . '/access/dbconfig.php';
    $db = hivenest_db();
    if ($db) {
        $check = $db->prepare('SELECT id FROM customers WHERE id = :id AND status = \'active\' LIMIT 1');
        $check->execute(['id' => $sessionId]);
        if ($check->fetch()) {
            header('Location: /account.php');
            exit;
        }
    }
    hivenest_customer_session_destroy();
}

$page_title = 'Login - HiveNest';
$page_description = 'Sign in to your HiveNest account to manage domains, hosting, email and more.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES); ?>">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="stylesheet" href="/assets/css/main.css?v=20260702-2">
    <link rel="stylesheet" href="/assets/css/navigation.css?v=20260702-3">
    <link rel="stylesheet" href="/assets/fonts/fontawesome-free-7.1.0-web/css/all.min.css">
    <style>
        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 90px 20px 40px;
            position: relative;
        }
        .auth-card {
            background: rgba(26, 26, 26, 0.85);
            border: 1px solid rgba(0, 255, 255, 0.3);
            border-radius: 16px;
            padding: 2.5rem;
            width: 100%;
            max-width: 440px;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5), 0 0 20px rgba(0, 255, 255, 0.1);
            animation: fadeInUp 0.6s ease-out;
        }
        .auth-card .auth-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .auth-card .auth-logo a {
            font-family: var(--font-heading);
            font-size: 2rem;
            font-weight: 900;
            color: var(--cyber-neon-cyan);
            text-decoration: none;
            text-shadow: 0 0 15px var(--cyber-neon-cyan);
            letter-spacing: 2px;
        }
        .auth-card h1 {
            font-size: 1.6rem;
            text-align: center;
            margin-bottom: 0.5rem;
            color: #fff;
        }
        .auth-card .auth-subtitle {
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 14px;
            background: rgba(10, 10, 10, 0.8);
            border: 1px solid rgba(0, 255, 255, 0.25);
            border-radius: 8px;
            color: #fff;
            font-family: var(--font-primary);
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--cyber-neon-cyan);
            box-shadow: 0 0 12px rgba(0, 255, 255, 0.3);
        }
        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }
        .auth-card .btn-auth {
            width: 100%;
            padding: 14px;
            font-size: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: var(--cyber-gradient-main);
            color: var(--cyber-black);
            border: 1px solid var(--cyber-neon-cyan);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .auth-card .btn-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 30px var(--cyber-neon-cyan);
        }
        .auth-card .btn-auth:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .auth-error {
            background: rgba(255, 0, 100, 0.1);
            border: 1px solid rgba(255, 0, 100, 0.4);
            color: #ff6b9d;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
            display: none;
        }
        .auth-error.visible {
            display: block;
        }
        .auth-info {
            background: rgba(0, 255, 255, 0.08);
            border: 1px solid rgba(0, 255, 255, 0.3);
            color: var(--cyber-neon-cyan);
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
            display: none;
        }
        .auth-info.visible {
            display: block;
        }
        .twofa-section {
            display: none;
        }
        .twofa-section.visible {
            display: block;
        }
        .twofa-section input {
            letter-spacing: 4px;
            text-align: center;
            font-family: var(--font-cyber);
        }
        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.5);
        }
        .auth-footer a {
            color: var(--cyber-neon-cyan);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .auth-footer a:hover {
            text-shadow: 0 0 8px var(--cyber-neon-cyan);
        }
        .auth-divider {
            border: none;
            border-top: 1px solid rgba(0, 255, 255, 0.15);
            margin: 1.5rem 0;
        }
        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(0, 0, 0, 0.3);
            border-top-color: var(--cyber-black);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .password-toggle {
            position: relative;
        }
        .password-toggle button {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            font-size: 1rem;
            padding: 4px;
        }
        .password-toggle button:hover {
            color: var(--cyber-neon-cyan);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="/" class="navbar-brand">HIVE<span style="color: var(--cyber-neon-pink);">NEST</span></a>
            <div class="navbar-nav">
                <a href="/"><i class="fas fa-home"></i> Home</a>
                <a href="/signup.php"><i class="fas fa-user-plus"></i> Sign Up</a>
            </div>
        </div>
    </nav>

    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-logo">
                <a href="/">HIVE<span style="color: var(--cyber-neon-pink);">NEST</span></a>
            </div>
            <h1>Sign In</h1>
            <p class="auth-subtitle">Access your digital command center</p>

            <div id="error-message" class="auth-error" role="alert"></div>
            <div id="info-message" class="auth-info" role="status"></div>

            <form id="login-form" autocomplete="on" novalidate>
                <div id="credentials-section">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" autocomplete="email" required
                               placeholder="you@example.com" autofocus>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-toggle">
                            <input type="password" id="password" name="password" autocomplete="current-password" required
                                   placeholder="Enter your password">
                            <button type="button" id="toggle-password" tabindex="-1" aria-label="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="2fa-section" class="twofa-section">
                    <div class="form-group">
                        <label for="2fa-code">Authentication Code</label>
                        <input type="text" id="2fa-code" name="2fa-code" inputmode="numeric"
                               autocomplete="one-time-code" maxlength="10"
                               placeholder="000000" pattern="[A-Z0-9-]{5,10}">
                    </div>
                    <p style="font-size: 0.8rem; color: rgba(255,255,255,0.5); margin-bottom: 1rem;">
                        Enter the 6-digit code from your authenticator app or a recovery code.
                    </p>
                </div>

                <button type="submit" id="submit-btn" class="btn-auth">
                    <span id="btn-text">Sign In</span>
                </button>
            </form>

            <hr class="auth-divider">
            <div class="auth-footer">
                <a href="/request-password-reset.php">Forgot password?</a>
                &nbsp;&middot;&nbsp;
                <a href="/signup.php">Create an account</a>
            </div>
        </div>
    </div>

    <script>
        (function() {
            const form = document.getElementById('login-form');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const twoFaInput = document.getElementById('2fa-code');
            const twoFaSection = document.getElementById('2fa-section');
            const errorEl = document.getElementById('error-message');
            const infoEl = document.getElementById('info-message');
            const submitBtn = document.getElementById('submit-btn');
            const btnText = document.getElementById('btn-text');
            const togglePassword = document.getElementById('toggle-password');

            let challengeToken = null;
            let isLoading = false;

            function showError(msg) {
                errorEl.textContent = msg;
                errorEl.classList.add('visible');
                infoEl.classList.remove('visible');
            }
            function showInfo(msg) {
                infoEl.textContent = msg;
                infoEl.classList.add('visible');
                errorEl.classList.remove('visible');
            }
            function clearMessages() {
                errorEl.classList.remove('visible');
                infoEl.classList.remove('visible');
            }
            function setLoading(loading) {
                isLoading = loading;
                submitBtn.disabled = loading;
                btnText.textContent = loading ? 'Please wait...' : (challengeToken ? 'Verify' : 'Sign In');
                if (loading && !document.getElementById('btn-spinner')) {
                    const spinner = document.createElement('span');
                    spinner.id = 'btn-spinner';
                    spinner.className = 'spinner';
                    submitBtn.appendChild(spinner);
                } else {
                    const existing = document.getElementById('btn-spinner');
                    if (existing) existing.remove();
                }
            }

            togglePassword.addEventListener('click', function() {
                const icon = this.querySelector('i');
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.className = 'fas fa-eye-slash';
                } else {
                    passwordInput.type = 'password';
                    icon.className = 'fas fa-eye';
                }
            });

            form.addEventListener('submit', async function(event) {
                event.preventDefault();
                if (isLoading) return;
                clearMessages();
                setLoading(true);

                try {
                    const action = challengeToken ? 'verify-2fa' : 'login';
                    const body = challengeToken
                        ? { challenge_token: challengeToken, code: twoFaInput.value.trim() }
                        : { email: emailInput.value.trim(), password: passwordInput.value };

                    const response = await fetch('/api/customer-auth.php?action=' + action, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(body),
                    });
                    const result = await response.json();

                    if (response.status === 429) {
                        const retryAfter = result.retry_after || 60;
                        showError(result.error || 'Too many attempts. Please wait ' + retryAfter + ' seconds.');
                    } else if (result.two_factor_required) {
                        challengeToken = result.challenge_token;
                        twoFaSection.classList.add('visible');
                        btnText.textContent = 'Verify';
                        twoFaInput.focus();
                        showInfo(result.message || 'Enter your authenticator code.');
                    } else if (result.authenticated) {
                        window.location.href = '/account.php';
                    } else {
                        showError(result.error || 'Login failed. Check your credentials.');
                        if (challengeToken && response.status === 401) {
                            twoFaInput.value = '';
                            twoFaInput.focus();
                        }
                    }
                } catch (err) {
                    showError('Network error. Check your connection and try again.');
                } finally {
                    setLoading(false);
                }
            });
        })();
    </script>
</body>
</html>