<?php
// Newsletter Form - Newsletter signup form component
// Usage: Include this file where you want the newsletter form

function renderNewsletterForm($title = null, $subtitle = null, $placeholder = null, $button_text = null, $form_id = 'newsletter-form') {
    $title = $title ?: 'JOIN THE NEURAL NETWORK';
    $subtitle = $subtitle ?: 'Get exclusive updates, special offers, and cyberpunk insights delivered to your digital inbox.';
    $placeholder = $placeholder ?: 'Enter your email to join the matrix...';
    $button_text = $button_text ?: 'INITIALIZE CONNECTION';
    
    ob_start();
    ?>
    <div class="newsletter-signup" style="background: linear-gradient(135deg, rgba(0, 255, 255, 0.1) 0%, rgba(255, 0, 255, 0.1) 100%); padding: 2.5rem; border-radius: 16px; border: 1px solid rgba(0, 255, 255, 0.3); text-align: center; margin: 2rem 0;">
        <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 1rem; font-family: var(--font-heading); text-transform: uppercase; letter-spacing: 1px;">
            <?php echo htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8'); ?>
        </h3>
        <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 2rem; font-size: 1.1rem; line-height: 1.6;">
            <?php echo htmlspecialchars((string)$subtitle, ENT_QUOTES, 'UTF-8'); ?>
        </p>
        
        <form id="<?php echo $form_id; ?>" class="newsletter-form" style="max-width: 500px; margin: 0 auto;">
            <?php echo csrfInput(); ?>
            <?php echo honeypotField(); ?>
            
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 250px;">
                    <input 
                        type="email" 
                        name="email" 
                        placeholder="<?php echo htmlspecialchars($placeholder); ?>"
                        style="width: 100%; padding: 16px; border-radius: 8px; border: 1px solid rgba(0, 255, 255, 0.3); background: rgba(0, 0, 0, 0.5); color: white; font-size: 1rem;"
                        required
                    >
                    <div class="error-message" style="color: #ef4444; font-size: 0.9rem; margin-top: 0.5rem; display: none;"></div>
                </div>
                
                <button type="submit" class="btn btn-primary" style="padding: 16px 24px; font-size: 1rem; white-space: nowrap;">
                    <i class="fas fa-paper-plane" style="margin-right: 0.5rem;"></i>
                    <?php echo htmlspecialchars((string)$button_text, ENT_QUOTES, 'UTF-8'); ?>
                </button>
            </div>
            
            <div class="form-result" style="margin-top: 1rem; padding: 1rem; border-radius: 8px; display: none;"></div>
            
            <p style="color: rgba(255, 255, 255, 0.6); font-size: 0.9rem; margin-top: 1rem;">
                <i class="fas fa-lock" style="margin-right: 0.5rem;"></i>
                Your data is encrypted and protected. Unsubscribe anytime.
            </p>
        </form>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('<?php echo $form_id; ?>');
        if (form) {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                const resultDiv = this.querySelector('.form-result');
                const errorDiv = this.querySelector('.error-message');
                
                // Clear previous messages
                resultDiv.style.display = 'none';
                errorDiv.style.display = 'none';
                
                // Check honeypot
                if (formData.get('website_url')) {
                    return; // Spam bot detected
                }
                
                // Show loading
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 0.5rem;"></i>Processing...';
                submitBtn.disabled = true;
                
                try {
                    const response = await fetch('/api/newsletter/subscribe', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const responseText = await response.text();
                    let result = {};
                    try {
                        result = responseText ? JSON.parse(responseText) : {};
                    } catch (parseError) {
                        throw new Error('Newsletter service returned an invalid response.');
                    }
                    
                    if (response.ok && result.success) {
                        resultDiv.style.display = 'block';
                        resultDiv.style.background = 'rgba(16, 185, 129, 0.2)';
                        resultDiv.style.border = '1px solid rgba(16, 185, 129, 0.5)';
                        resultDiv.style.color = '#10b981';
                        resultDiv.textContent = result.message || 'Check your inbox to confirm your subscription.';
                        form.reset();
                    } else {
                        throw new Error(result.message || 'Subscription failed');
                    }
                } catch (error) {
                    resultDiv.style.display = 'block';
                    resultDiv.style.background = 'rgba(239, 68, 68, 0.2)';
                    resultDiv.style.border = '1px solid rgba(239, 68, 68, 0.5)';
                    resultDiv.style.color = '#ef4444';
                    resultDiv.textContent = error instanceof Error
                        ? error.message
                        : 'Subscription failed. Please try again.';
                } finally {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            });
        }
    });
    </script>
    <?php
    return ob_get_clean();
}

// Compact newsletter form for sidebars/footers
function renderCompactNewsletterForm($placeholder = 'Your email...', $button_text = 'Join') {
    ob_start();
    ?>
    <form class="compact-newsletter-form" style="display: flex; gap: 0.5rem; max-width: 300px;">
        <?php echo csrfInput(); ?>
        <input 
            type="email" 
            name="email" 
            placeholder="<?php echo htmlspecialchars($placeholder); ?>"
            style="flex: 1; padding: 12px; border-radius: 6px; border: 1px solid rgba(0, 255, 255, 0.3); background: rgba(0, 0, 0, 0.5); color: white; font-size: 0.9rem;"
            required
        >
        <button type="submit" class="btn btn-primary" style="padding: 12px 16px; font-size: 0.9rem;">
            <?php echo htmlspecialchars((string)$button_text, ENT_QUOTES, 'UTF-8'); ?>
        </button>
    </form>
    <?php
    return ob_get_clean();
}
?>
