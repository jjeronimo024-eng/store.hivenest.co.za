<?php
declare(strict_types=1);

/**
 * Two-Factor Authentication Enrollment Page
 *
 * Uses the /api/customers/two-factor API endpoint which:
 * - Generates and encrypts the TOTP secret in the session (not the DB)
 * - Verifies the TOTP code before storing the secret
 * - Generates and displays one-time recovery codes
 *
 * The QR code is rendered client-side so the secret never leaves the browser.
 */

require_once __DIR__ . '/utilities/customer_session.php';
hivenest_customer_session_configure();
session_start();
require_once __DIR__ . '/access/dbconfig.php';

// Validate the customer session before showing the enrollment page
$session = hivenest_customer_session_status(true);
if (!$session['authenticated']) {
    header('Location: /login.php');
    exit;
}

// Check if 2FA is already enabled
$db = hivenest_db();
$twoFactorEnabled = false;
if ($db) {
    $check = $db->prepare('SELECT two_factor_enabled FROM customers WHERE id = :id LIMIT 1');
    $check->execute(['id' => $session['customer_id']]);
    $row = $check->fetch(PDO::FETCH_ASSOC);
    $twoFactorEnabled = $row && (int)$row['two_factor_enabled'] === 1;
}

// Get CSRF token for API calls
$csrfToken = hivenest_customer_csrf_token();

$page_title = 'Two-Factor Authentication - HiveNest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Set up two-factor authentication to secure your HiveNest account.">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="stylesheet" href="/assets/css/main.css?v=20260702-2">
    <link rel="stylesheet" href="/assets/css/navigation.css?v=20260702-3">
    <link rel="stylesheet" href="/assets/fonts/fontawesome-free-7.1.0-web/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 90px 20px 40px;
        }
        .auth-card {
            background: rgba(26, 26, 26, 0.85);
            border: 1px solid rgba(0, 255, 255, 0.3);
            border-radius: 16px;
            padding: 2.5rem;
            width: 100%;
            max-width: 520px;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5), 0 0 20px rgba(0, 255, 255, 0.1);
            animation: fadeInUp 0.6s ease-out;
        }
        .auth-card .auth-logo { text-align: center; margin-bottom: 1rem; }
        .auth-card .auth-logo a {
            font-family: var(--font-heading);
            font-size: 1.8rem; font-weight: 900;
            color: var(--cyber-neon-cyan);
            text-decoration: none;
            text-shadow: 0 0 15px var(--cyber-neon-cyan);
            letter-spacing: 2px;
        }
        .auth-card h1 { font-size: 1.5rem; text-align: center; margin-bottom: 0.5rem; }
        .auth-card .auth-subtitle {
            text-align: center; color: rgba(255,255,255,0.6);
            font-size: 0.9rem; margin-bottom: 2rem;
        }
        .step-indicator {
            display: flex; justify-content: center; gap: 8px; margin-bottom: 2rem;
        }
        .step-dot {
            width: 32px; height: 32px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem; font-weight: 700;
            border: 2px solid rgba(0,255,255,0.2);
            background: rgba(10,10,10,0.5);
            color: rgba(255,255,255,0.4);
            transition: all 0.3s ease;
        }
        .step-dot.active {
            border-color: var(--cyber-neon-cyan);
            color: var(--cyber-neon-cyan);
            box-shadow: 0 0 12px rgba(0,255,255,0.4);
        }
        .step-dot.done {
            border-color: var(--cyber-neon-green);
            color: var(--cyber-neon-green);
            background: rgba(0,255,0,0.1);
        }
        .step-section { display: none; }
        .step-section.visible { display: block; animation: fadeInUp 0.4s ease-out; }
        .qr-container {
            text-align: center; margin: 1.5rem 0;
        }
        .qr-container #qrcode {
            display: inline-block;
            padding: 16px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(0,255,255,0.2);
        }
        .secret-display {
            background: rgba(10,10,10,0.8);
            border: 1px solid rgba(0,255,255,0.25);
            border-radius: 8px;
            padding: 12px 14px;
            margin: 1rem 0;
            font-family: var(--font-cyber);
            font-size: 1.1rem;
            color: var(--cyber-neon-cyan);
            text-align: center;
            letter-spacing: 2px;
            word-break: break-all;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .secret-display:hover {
            border-color: var(--cyber-neon-cyan);
            box-shadow: 0 0 12px rgba(0,255,255,0.3);
        }
        .secret-display::after {
            content: '\f0c5';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            margin-left: 8px;
            opacity: 0.5;
            font-size: 0.85rem;
        }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block; font-size: 0.85rem; font-weight: 600;
            color: rgba(255,255,255,0.8); margin-bottom: 0.4rem;
            text-transform: uppercase; letter-spacing: 1px;
        }
        .form-group input {
            width: 100%; padding: 12px 14px;
            background: rgba(10,10,10,0.8);
            border: 1px solid rgba(0,255,255,0.25);
            border-radius: 8px; color: #fff;
            font-family: var(--font-primary); font-size: 1rem;
            transition: all 0.3s ease;
        }
        .form-group input:focus {
            outline: none; border-color: var(--cyber-neon-cyan);
            box-shadow: 0 0 12px rgba(0,255,255,0.3);
        }
        .code-input {
            letter-spacing: 4px; text-align: center;
            font-family: var(--font-cyber);
        }
        .btn-auth {
            width: 100%; padding: 14px; font-size: 1rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1px;
            background: var(--cyber-gradient-main); color: var(--cyber-black);
            border: 1px solid var(--cyber-neon-cyan); border-radius: 8px;
            cursor: pointer; transition: all 0.3s ease;
            box-shadow: 0 0 15px rgba(0,255,255,0.3);
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-auth:hover { transform: translateY(-2px); box-shadow: 0 0 30px var(--cyber-neon-cyan); }
        .btn-auth:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .btn-secondary {
            background: transparent; color: var(--cyber-neon-cyan);
            border: 1px solid var(--cyber-neon-cyan);
        }
        .btn-secondary:hover { background: rgba(0,255,255,0.1); }
        .auth-error {
            background: rgba(255,0,100,0.1); border: 1px solid rgba(255,0,100,0.4);
            color: #ff6b9d; padding: 10px 14px; border-radius: 8px;
            font-size: 0.85rem; margin-bottom: 1rem; display: none;
        }
        .auth-error.visible { display: block; }
        .recovery-codes {
            background: rgba(10,10,10,0.8);
            border: 1px solid rgba(0,255,0,0.3);
            border-radius: 8px; padding: 1rem;
            margin: 1rem 0;
        }
        .recovery-codes h3 {
            font-size: 0.9rem; color: var(--cyber-neon-green);
            margin-bottom: 0.5rem; text-align: center;
        }
        .recovery-codes ul {
            list-style: none; display: grid;
            grid-template-columns: 1fr 1fr; gap: 6px;
        }
        .recovery-codes li {
            font-family: var(--font-cyber); font-size: 0.95rem;
            color: var(--cyber-neon-green); text-align: center;
            padding: 4px; background: rgba(0,255,0,0.05);
            border-radius: 4px;
        }
        .recovery-warning {
            background: rgba(255,181,71,0.1);
            border: 1px solid rgba(255,181,71,0.4);
            color: var(--cyber-neon-orange);
            padding: 10px 14px; border-radius: 8px;
            font-size: 0.85rem; margin: 1rem 0;
        }
        .recovery-warning i { margin-right: 6px; }
        .auth-footer {
            text-align: center; margin-top: 1.5rem;
            font-size: 0.85rem; color: rgba(255,255,255,0.5);
        }
        .auth-footer a {
            color: var(--cyber-neon-cyan); text-decoration: none;
            transition: all 0.3s ease;
        }
        .auth-footer a:hover { text-shadow: 0 0 8px var(--cyber-neon-cyan); }
        .spinner {
            width: 16px; height: 16px;
            border: 2px solid rgba(0,0,0,0.3);
            border-top-color: var(--cyber-black);
            border-radius: 50%; animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .already-enabled {
            text-align: center; padding: 2rem 0;
        }
        .already-enabled i {
            font-size: 3rem; color: var(--cyber-neon-green);
            text-shadow: 0 0 20px var(--cyber-neon-green);
            margin-bottom: 1rem; display: block;
        }
        .already-enabled p {
            color: rgba(255,255,255,0.7); margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="/" class="navbar-brand">HIVE<span style="color: var(--cyber-neon-pink);">NEST</span></a>
            <div class="navbar-nav">
                <a href="/account.php"><i class="fas fa-user"></i> Account</a>
                <a href="/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-logo">
                <a href="/">HIVE<span style="color: var(--cyber-neon-pink);">NEST</span></a>
            </div>

<?php if ($twoFactorEnabled): ?>
            <div class="already-enabled">
                <i class="fas fa-shield-check"></i>
                <h1>2FA is Active</h1>
                <p>Two-factor authentication is already enabled on your account.</p>
                <a href="/account.php" class="btn-auth btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Account
                </a>
            </div>
<?php else: ?>
            <h1>Secure Your Account</h1>
            <p class="auth-subtitle">Set up two-factor authentication</p>

            <div class="step-indicator">
                <div class="step-dot active" id="step-1-dot">1</div>
                <div class="step-dot" id="step-2-dot">2</div>
                <div class="step-dot" id="step-3-dot">3</div>
            </div>

            <div id="error-message" class="auth-error" role="alert"></div>

            <!-- Step 1: Scan QR Code -->
            <div id="step-1" class="step-section visible">
                <div class="qr-container">
                    <div id="qrcode"></div>
                </div>
                <p style="text-align: center; font-size: 0.85rem; color: rgba(255,255,255,0.6); margin-bottom: 1rem;">
                    Scan with Google Authenticator, Authy, or 1Password.
                </p>
                <div class="secret-display" id="secret-display" title="Click to copy">
                    <span id="secret-text"></span>
                </div>
                <p style="text-align: center; font-size: 0.8rem; color: rgba(255,255,255,0.4); margin-bottom: 1.5rem;">
                    Or enter this key manually if you can't scan the code.
                </p>
                <button type="button" id="next-to-verify" class="btn-auth">
                    <i class="fas fa-arrow-right"></i> Continue
                </button>
            </div>

            <!-- Step 2: Verify Code -->
            <div id="step-2" class="step-section">
                <div class="form-group">
                    <label for="verify-code">Enter Verification Code</label>
                    <input type="text" id="verify-code" class="code-input" inputmode="numeric"
                           maxlength="6" placeholder="000000" pattern="[0-9]{6}">
                </div>
                <p style="font-size: 0.8rem; color: rgba(255,255,255,0.5); margin-bottom: 1rem;">
                    Enter the 6-digit code shown in your authenticator app.
                </p>
                <button type="button" id="verify-btn" class="btn-auth">
                    <span id="verify-btn-text">Verify & Enable</span>
                </button>
                <button type="button" id="back-to-1" class="btn-auth btn-secondary" style="margin-top: 0.5rem;">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
            </div>

            <!-- Step 3: Recovery Codes -->
            <div id="step-3" class="step-section">
                <div class="recovery-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Save these recovery codes now. You won't see them again. Each code can be used once if you lose access to your authenticator.
                </div>
                <div class="recovery-codes">
                    <h3><i class="fas fa-key"></i> Recovery Codes</h3>
                    <ul id="recovery-codes-list"></ul>
                </div>
                <button type="button" id="download-codes" class="btn-auth btn-secondary" style="margin-bottom: 0.5rem;">
                    <i class="fas fa-download"></i> Download Codes
                </button>
                <a href="/account.php" class="btn-auth">
                    <i class="fas fa-check"></i> Done
                </a>
            </div>

            <div class="auth-footer">
                <a href="/account.php">Back to Account</a>
            </div>
<?php endif; ?>
        </div>
    </div>

    <script>
        (function() {
            const csrfToken = <?php echo json_encode($csrfToken); ?>;
            let pendingSecret = null;
            let pendingOtpauth = null;

            const step1 = document.getElementById('step-1');
            const step2 = document.getElementById('step-2');
            const step3 = document.getElementById('step-3');
            const step1Dot = document.getElementById('step-1-dot');
            const step2Dot = document.getElementById('step-2-dot');
            const step3Dot = document.getElementById('step-3-dot');
            const errorEl = document.getElementById('error-message');
            const secretText = document.getElementById('secret-text');
            const secretDisplay = document.getElementById('secret-display');
            const verifyCode = document.getElementById('verify-code');
            const verifyBtn = document.getElementById('verify-btn');
            const verifyBtnText = document.getElementById('verify-btn-text');

            function showError(msg) {
                errorEl.textContent = msg;
                errorEl.classList.add('visible');
            }
            function clearError() {
                errorEl.classList.remove('visible');
            }
            function showStep(n) {
                [step1, step2, step3].forEach(s => s.classList.remove('visible'));
                [step1Dot, step2Dot, step3Dot].forEach(d => { d.classList.remove('active', 'done'); });
                if (n === 1) { step1.classList.add('visible'); step1Dot.classList.add('active'); }
                if (n === 2) { step1Dot.classList.add('done'); step2.classList.add('visible'); step2Dot.classList.add('active'); }
                if (n === 3) { step1Dot.classList.add('done'); step2Dot.classList.add('done'); step3.classList.add('visible'); step3Dot.classList.add('active'); }
            }
            function setLoading(btn, btnText, loading, loadingText) {
                btn.disabled = loading;
                btnText.textContent = loading ? loadingText : btnText.dataset.original || btnText.textContent;
                if (loading && !btn.querySelector('.spinner')) {
                    const spinner = document.createElement('span');
                    spinner.className = 'spinner';
                    btn.appendChild(spinner);
                } else {
                    const existing = btn.querySelector('.spinner');
                    if (existing) existing.remove();
                }
            }

            async function startEnrollment() {
                try {
                    const response = await fetch('/api/customers/two-factor', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken,
                        },
                        body: JSON.stringify({ action: 'start' }),
                    });
                    const result = await response.json();
                    if (!response.ok) {
                        showError(result.error || 'Could not start enrollment.');
                        return false;
                    }
                    pendingSecret = result.secret;
                    pendingOtpauth = result.otpauth_uri;

                    // Render QR code client-side
                    const qrEl = document.getElementById('qrcode');
                    qrEl.innerHTML = '';
                    new QRCode(qrEl, {
                        text: pendingOtpauth,
                        width: 200,
                        height: 200,
                        colorDark: '#000000',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.M,
                    });
                    secretText.textContent = pendingSecret;
                    return true;
                } catch (err) {
                    showError('Network error. Please try again.');
                    return false;
                }
            }

            // Start enrollment on page load
            startEnrollment();

            // Copy secret on click
            secretDisplay.addEventListener('click', function() {
                navigator.clipboard.writeText(pendingSecret || '').then(function() {
                    const original = secretText.textContent;
                    secretText.textContent = 'Copied!';
                    setTimeout(function() { secretText.textContent = original; }, 1500);
                });
            });

            // Step 1 -> Step 2
            document.getElementById('next-to-verify').addEventListener('click', function() {
                clearError();
                showStep(2);
                verifyCode.focus();
            });

            // Step 2 -> Step 1
            document.getElementById('back-to-1').addEventListener('click', function() {
                clearError();
                showStep(1);
            });

            // Verify code
            verifyBtnText.dataset.original = verifyBtnText.textContent;
            verifyBtn.addEventListener('click', async function() {
                clearError();
                const code = verifyCode.value.trim();
                if (!/^\d{6}$/.test(code)) {
                    showError('Enter the 6-digit code from your authenticator app.');
                    return;
                }
                setLoading(verifyBtn, verifyBtnText, true, 'Verifying...');
                try {
                    const response = await fetch('/api/customers/two-factor', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken,
                        },
                        body: JSON.stringify({ action: 'confirm', code: code }),
                    });
                    const result = await response.json();
                    if (!response.ok) {
                        showError(result.error || 'Verification failed.');
                        verifyCode.value = '';
                        verifyCode.focus();
                    } else if (result.enabled && result.recovery_codes) {
                        const list = document.getElementById('recovery-codes-list');
                        list.innerHTML = '';
                        result.recovery_codes.forEach(function(code) {
                            const li = document.createElement('li');
                            li.textContent = code;
                            list.appendChild(li);
                        });
                        showStep(3);
                    }
                } catch (err) {
                    showError('Network error. Please try again.');
                } finally {
                    setLoading(verifyBtn, verifyBtnText, false);
                }
            });

            // Download recovery codes
            document.getElementById('download-codes').addEventListener('click', function() {
                const codes = [];
                document.querySelectorAll('#recovery-codes-list li').forEach(function(li) {
                    codes.push(li.textContent);
                });
                if (codes.length === 0) return;
                const text = 'HiveNest Two-Factor Recovery Codes\n' +
                    'Generated: ' + new Date().toISOString() + '\n' +
                    'Keep these codes safe. Each can be used once.\n\n' +
                    codes.map(function(c, i) { return (i + 1) + '. ' + c; }).join('\n');
                const blob = new Blob([text], { type: 'text/plain' });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'hivenest-recovery-codes.txt';
                a.click();
                URL.revokeObjectURL(a.href);
            });
        })();
    </script>
</body>
</html>