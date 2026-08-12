<?php
// Alert System - System alerts and notifications
// Usage: Call showAlert() function with type and message

function showAlert($type, $message, $dismissible = true, $icon = null) {
    $alert_colors = [
        'success' => 'background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.5); color: #10b981;',
        'error' => 'background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.5); color: #ef4444;',
        'warning' => 'background: rgba(245, 158, 11, 0.2); border: 1px solid rgba(245, 158, 11, 0.5); color: #f59e0b;',
        'info' => 'background: rgba(0, 255, 255, 0.2); border: 1px solid rgba(0, 255, 255, 0.5); color: var(--cyber-neon-cyan);'
    ];
    
    $alert_icons = [
        'success' => 'fas fa-check-circle',
        'error' => 'fas fa-exclamation-circle',
        'warning' => 'fas fa-exclamation-triangle',
        'info' => 'fas fa-info-circle'
    ];
    
    $style = isset($alert_colors[$type]) ? $alert_colors[$type] : $alert_colors['info'];
    $icon_class = $icon ?: (isset($alert_icons[$type]) ? $alert_icons[$type] : $alert_icons['info']);
    
    ob_start();
    ?>
    <div class="cyber-alert cyber-alert-<?php echo $type; ?>" style="<?php echo $style; ?> padding: 1rem 1.5rem; border-radius: 8px; margin: 1rem 0; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <i class="<?php echo $icon_class; ?>" style="font-size: 1.2rem;"></i>
            <span style="font-weight: 500;"><?php echo $message; ?></span>
        </div>
        <?php if($dismissible): ?>
            <button class="alert-dismiss" style="background: none; border: none; color: inherit; font-size: 1.2rem; cursor: pointer; padding: 0.25rem;" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// JavaScript for auto-dismiss alerts
function getAlertScript() {
    return "
    <script>
    // Auto-dismiss alerts after 5 seconds
    document.querySelectorAll('.cyber-alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                if (alert.parentElement) {
                    alert.remove();
                }
            }, 300);
        }, 5000);
    });
    </script>
    ";
}
?>