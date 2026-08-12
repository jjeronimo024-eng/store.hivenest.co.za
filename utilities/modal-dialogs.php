<?php
// Modal Dialogs - Modal popup dialogs
// Usage: Call renderModal() function with parameters

function renderModal($modal_id, $title, $content, $footer = null, $size = 'medium') {
    $sizes = [
        'small' => 'max-width: 400px;',
        'medium' => 'max-width: 600px;',
        'large' => 'max-width: 800px;',
        'xlarge' => 'max-width: 1200px;'
    ];
    
    $modal_width = isset($sizes[$size]) ? $sizes[$size] : $sizes['medium'];
    
    ob_start();
    ?>
    <div id="<?php echo $modal_id; ?>" class="cyber-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); z-index: 10000; backdrop-filter: blur(5px);">
        <div class="cyber-modal-dialog" style="display: flex; justify-content: center; align-items: center; height: 100%; padding: 20px;">
            <div class="cyber-modal-content" style="<?php echo $modal_width; ?> width: 100%; background: rgba(26, 26, 26, 0.95); border: 1px solid rgba(0, 255, 255, 0.5); border-radius: 16px; overflow: hidden; position: relative; animation: modalSlideIn 0.3s ease-out;">
                
                <!-- Modal Header -->
                <div class="cyber-modal-header" style="padding: 1.5rem 2rem; border-bottom: 1px solid rgba(0, 255, 255, 0.3); display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="color: var(--cyber-neon-cyan); margin: 0; font-family: var(--font-heading); text-transform: uppercase; letter-spacing: 1px;">
                        <?php echo $title; ?>
                    </h3>
                    <button class="cyber-modal-close" onclick="closeModal('<?php echo $modal_id; ?>')" style="background: none; border: none; color: var(--cyber-neon-pink); font-size: 1.5rem; cursor: pointer; padding: 0.5rem; transition: all 0.3s ease;" aria-label="Close modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <!-- Modal Body -->
                <div class="cyber-modal-body" style="padding: 2rem; max-height: 70vh; overflow-y: auto;">
                    <?php echo $content; ?>
                </div>
                
                <?php if($footer): ?>
                <!-- Modal Footer -->
                <div class="cyber-modal-footer" style="padding: 1.5rem 2rem; border-top: 1px solid rgba(0, 255, 255, 0.3); display: flex; justify-content: flex-end; gap: 1rem;">
                    <?php echo $footer; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <style>
    @keyframes modalSlideIn {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    .cyber-modal-close:hover {
        color: var(--cyber-neon-cyan) !important;
        text-shadow: 0 0 10px var(--cyber-neon-cyan);
        transform: scale(1.1);
    }
    </style>
    <?php
    return ob_get_clean();
}

function getModalScript() {
    return <<<'HTML'
    <script>
    // Open modal
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Close on backdrop click
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal(modalId);
                }
            });
            
            // Close on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal(modalId);
                }
            });
        }
    }
    
    // Close modal
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }
    
    // Confirm dialog
    function showConfirm(title, message, onConfirm, onCancel) {
        const modalId = 'confirm-modal-' + Date.now();
        const footer = `
            <button class='btn btn-outline' onclick='closeModal("${modalId}"); ${onCancel ? onCancel + '();' : ''}'>
                Cancel
            </button>
            <button class='btn btn-primary' onclick='closeModal("${modalId}"); ${onConfirm}();'>
                Confirm
            </button>
        `;
        
        const modalHtml = `
            <div id="${modalId}" class="cyber-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.8);z-index:10000;backdrop-filter:blur(5px);">
                <div class="cyber-modal-dialog" style="display:flex;justify-content:center;align-items:center;height:100%;padding:20px;">
                    <div class="cyber-modal-content" style="max-width:600px;width:100%;background:rgba(26,26,26,.95);border:1px solid rgba(0,255,255,.5);border-radius:16px;overflow:hidden;">
                        <div class="cyber-modal-header" style="padding:1.5rem 2rem;border-bottom:1px solid rgba(0,255,255,.3);display:flex;justify-content:space-between;align-items:center;">
                            <h3 style="color:var(--cyber-neon-cyan);margin:0;">${title}</h3>
                            <button class="cyber-modal-close" onclick="closeModal('${modalId}')" aria-label="Close modal">&times;</button>
                        </div>
                        <div class="cyber-modal-body" style="padding:2rem;max-height:70vh;overflow-y:auto;"><p>${message}</p></div>
                        <div class="cyber-modal-footer" style="padding:1.5rem 2rem;border-top:1px solid rgba(0,255,255,.3);display:flex;justify-content:flex-end;gap:1rem;">${footer}</div>
                    </div>
                </div>
            </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        openModal(modalId);
    }
    </script>
HTML;
}
?>
