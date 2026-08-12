<?php
$current_page = 'forgot-password';
$page_title = 'Reset Password | HiveNest Matrix';
$page_description = 'Request a secure HiveNest customer password reset link.';
$page_keywords = 'hivenest password reset, account recovery';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/utilities/head.php'; ?>
</head>
<body>
<?php include __DIR__ . '/utilities/nav.php'; ?>
<?php include __DIR__ . '/utilities/mobile-menu.php'; ?>

<section class="section" style="min-height:72vh;display:flex;align-items:center;">
    <div class="container">
        <div class="cyber-card" style="max-width:650px;margin:0 auto;">
            <h1 style="color:var(--cyber-neon-cyan);text-align:center;">RESET ACCESS CODE</h1>
            <p style="text-align:center;color:rgba(255,255,255,.75);">
                Enter your account email. If it matches an active account, we will send a one-time reset link.
            </p>
            <form id="forgot-password-form" style="margin-top:2rem;">
                <label for="recovery-email" style="display:block;color:var(--cyber-neon-cyan);margin-bottom:.5rem;">Email address</label>
                <input id="recovery-email" type="email" autocomplete="email" required
                       style="width:100%;padding:16px;background:rgba(0,0,0,.55);border:1px solid rgba(0,255,255,.35);border-radius:8px;color:#fff;">
                <button class="btn btn-primary" type="submit" style="width:100%;margin-top:1.25rem;">SEND RESET LINK</button>
                <div id="recovery-status" role="status" style="display:none;margin-top:1rem;padding:12px;border-radius:8px;"></div>
            </form>
            <p style="text-align:center;margin-top:1.5rem;"><a href="auth.php?mode=login">Return to login</a></p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/utilities/footer.php'; ?>
<?php include __DIR__ . '/utilities/scripts.php'; ?>
<script>
document.getElementById('forgot-password-form').addEventListener('submit', async function (event) {
    event.preventDefault();
    const button = this.querySelector('button[type="submit"]');
    const status = document.getElementById('recovery-status');
    button.disabled = true;
    status.style.display = 'none';
    try {
        const response = await fetch('/api/customer-auth.php?action=request-password-reset', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({email: document.getElementById('recovery-email').value})
        });
        const text = await response.text();
        let data = {};
        try { data = text ? JSON.parse(text) : {}; } catch (error) {}
        if (!response.ok) throw new Error(data.error || 'Recovery request failed.');
        status.textContent = data.message;
        status.style.color = '#8dffb7';
        status.style.background = 'rgba(16,185,129,.14)';
        status.style.border = '1px solid rgba(16,185,129,.55)';
        status.style.display = 'block';
        this.reset();
    } catch (error) {
        console.error(error);
        status.textContent = error instanceof Error ? error.message : 'Recovery request failed.';
        status.style.color = '#ff9abb';
        status.style.background = 'rgba(239,68,68,.14)';
        status.style.border = '1px solid rgba(239,68,68,.55)';
        status.style.display = 'block';
    } finally {
        button.disabled = false;
    }
});
</script>
</body>
</html>
