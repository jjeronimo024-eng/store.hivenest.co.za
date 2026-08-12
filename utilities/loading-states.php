<?php
// Loading States - Loading animations and states
// Usage: Call renderLoader() or use CSS classes

function renderLoader($type = 'spinner', $message = 'Loading...', $size = 'medium') {
    $sizes = [
        'small' => '20px',
        'medium' => '40px',
        'large' => '60px'
    ];
    
    $loader_size = isset($sizes[$size]) ? $sizes[$size] : $sizes['medium'];
    
    ob_start();
    
    switch($type) {
        case 'spinner':
            ?>
            <div class="cyber-loader cyber-spinner" style="display: flex; flex-direction: column; align-items: center; gap: 1rem; padding: 2rem;">
                <div style="width: <?php echo $loader_size; ?>; height: <?php echo $loader_size; ?>; border: 3px solid rgba(0,255,255,0.3); border-top: 3px solid var(--cyber-neon-cyan); border-radius: 50%; animation: cyber-spin 1s linear infinite;"></div>
                <span style="color: var(--cyber-neon-cyan); font-weight: 500;"><?php echo $message; ?></span>
            </div>
            <?php
            break;
            
        case 'pulse':
            ?>
            <div class="cyber-loader cyber-pulse" style="display: flex; flex-direction: column; align-items: center; gap: 1rem; padding: 2rem;">
                <div style="width: <?php echo $loader_size; ?>; height: <?php echo $loader_size; ?>; background: var(--cyber-neon-cyan); border-radius: 50%; animation: cyber-pulse 1.5s ease-in-out infinite;"></div>
                <span style="color: var(--cyber-neon-cyan); font-weight: 500;"><?php echo $message; ?></span>
            </div>
            <?php
            break;
            
        case 'dots':
            ?>
            <div class="cyber-loader cyber-dots" style="display: flex; flex-direction: column; align-items: center; gap: 1rem; padding: 2rem;">
                <div style="display: flex; gap: 0.5rem;">
                    <div style="width: 12px; height: 12px; background: var(--cyber-neon-cyan); border-radius: 50%; animation: cyber-bounce 1.4s ease-in-out infinite both; animation-delay: -0.32s;"></div>
                    <div style="width: 12px; height: 12px; background: var(--cyber-neon-pink); border-radius: 50%; animation: cyber-bounce 1.4s ease-in-out infinite both; animation-delay: -0.16s;"></div>
                    <div style="width: 12px; height: 12px; background: var(--cyber-neon-green); border-radius: 50%; animation: cyber-bounce 1.4s ease-in-out infinite both;"></div>
                </div>
                <span style="color: var(--cyber-neon-cyan); font-weight: 500;"><?php echo $message; ?></span>
            </div>
            <?php
            break;
    }
    
    return ob_get_clean();
}

function getLoadingCSS() {
    return "
    <style>
    @keyframes cyber-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    @keyframes cyber-pulse {
        0%, 100% { 
            opacity: 1;
            transform: scale(1);
        }
        50% { 
            opacity: 0.5;
            transform: scale(0.8);
        }
    }
    
    @keyframes cyber-bounce {
        0%, 80%, 100% { 
            transform: scale(0);
            opacity: 0.5;
        }
        40% { 
            transform: scale(1);
            opacity: 1;
        }
    }
    
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        backdrop-filter: blur(5px);
    }
    </style>
    ";
}

function getLoadingScript() {
    return "
    <script>
    // Show loading overlay
    function showLoading(message = 'Loading...') {
        const overlay = document.createElement('div');
        overlay.className = 'loading-overlay';
        overlay.id = 'loading-overlay';
        overlay.innerHTML = `
            <div style='background: rgba(26, 26, 26, 0.9); padding: 3rem; border-radius: 16px; border: 1px solid rgba(0,255,255,0.3); text-align: center;'>
                <div style='width: 60px; height: 60px; border: 4px solid rgba(0,255,255,0.3); border-top: 4px solid var(--cyber-neon-cyan); border-radius: 50%; animation: cyber-spin 1s linear infinite; margin: 0 auto 1rem;'></div>
                <span style='color: var(--cyber-neon-cyan); font-weight: 500; font-size: 1.1rem;'>\${message}</span>
            </div>
        `;
        document.body.appendChild(overlay);
    }
    
    // Hide loading overlay
    function hideLoading() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.style.opacity = '0';
            setTimeout(() => {
                if (overlay.parentElement) {
                    overlay.remove();
                }
            }, 300);
        }
    }
    
    // Button loading state
    function setButtonLoading(button, loading = true, originalText = null) {
        if (loading) {
            button.dataset.originalText = button.innerHTML;
            button.innerHTML = '<i class=\"fas fa-spinner fa-spin\" style=\"margin-right: 0.5rem;\"></i>Processing...';
            button.disabled = true;
        } else {
            button.innerHTML = originalText || button.dataset.originalText || 'Submit';
            button.disabled = false;
        }
    }
    </script>
    ";
}
?>
