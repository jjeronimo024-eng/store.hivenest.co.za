<?php
// Tabs - Tabbed content sections component
// Usage: Call renderTabs() function with tabs array

function renderTabs($tabs, $tab_id = 'tabs', $default_active = 0) {
    if (empty($tabs)) {
        return '';
    }
    
    ob_start();
    ?>
    <div id="<?php echo $tab_id; ?>" class="cyber-tabs">
        <!-- Tab Navigation -->
        <div class="tab-nav" style="display: flex; border-bottom: 2px solid rgba(0, 255, 255, 0.3); margin-bottom: 2rem; overflow-x: auto;">
            <?php foreach ($tabs as $index => $tab): ?>
                <button 
                    class="tab-button <?php echo $index === $default_active ? 'active' : ''; ?>" 
                    onclick="switchTab('<?php echo $tab_id; ?>', <?php echo $index; ?>)"
                    style="
                        background: none; 
                        border: none; 
                        color: rgba(255, 255, 255, 0.7); 
                        padding: 1rem 2rem; 
                        font-size: 1rem; 
                        font-weight: 600; 
                        text-transform: uppercase; 
                        letter-spacing: 1px;
                        cursor: pointer; 
                        transition: all 0.3s ease;
                        border-bottom: 3px solid transparent;
                        white-space: nowrap;
                        font-family: var(--font-heading);
                    "
                    data-tab="<?php echo $index; ?>"
                >
                    <?php if (!empty($tab['icon'])): ?>
                        <i class="<?php echo $tab['icon']; ?>" style="margin-right: 0.5rem;"></i>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($tab['title']); ?>
                </button>
            <?php endforeach; ?>
        </div>
        
        <!-- Tab Content -->
        <div class="tab-content">
            <?php foreach ($tabs as $index => $tab): ?>
                <div 
                    class="tab-pane <?php echo $index === $default_active ? 'active' : ''; ?>"
                    data-tab-pane="<?php echo $index; ?>"
                    style="<?php echo $index === $default_active ? 'display: block;' : 'display: none;'; ?> animation: fadeInUp 0.4s ease-out;"
                >
                    <?php echo $tab['content']; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <style>
    .tab-button.active {
        color: var(--cyber-neon-cyan) !important;
        border-bottom-color: var(--cyber-neon-cyan) !important;
        text-shadow: 0 0 10px var(--cyber-neon-cyan);
    }
    
    .tab-button:hover:not(.active) {
        color: var(--cyber-neon-pink) !important;
        border-bottom-color: var(--cyber-neon-pink) !important;
    }
    
    .tab-nav {
        scrollbar-width: thin;
        scrollbar-color: var(--cyber-neon-cyan) transparent;
    }
    
    .tab-nav::-webkit-scrollbar {
        height: 4px;
    }
    
    .tab-nav::-webkit-scrollbar-track {
        background: transparent;
    }
    
    .tab-nav::-webkit-scrollbar-thumb {
        background: var(--cyber-neon-cyan);
        border-radius: 2px;
    }
    </style>
    
    <script>
    function switchTab(tabId, targetIndex) {
        // Get all tab elements
        const tabContainer = document.getElementById(tabId);
        const tabButtons = tabContainer.querySelectorAll('.tab-button');
        const tabPanes = tabContainer.querySelectorAll('.tab-pane');
        
        // Remove active class from all buttons and panes
        tabButtons.forEach((button, index) => {
            button.classList.remove('active');
            tabPanes[index].classList.remove('active');
            tabPanes[index].style.display = 'none';
        });
        
        // Add active class to target button and pane
        tabButtons[targetIndex].classList.add('active');
        tabPanes[targetIndex].classList.add('active');
        tabPanes[targetIndex].style.display = 'block';
        
        // Trigger animation
        tabPanes[targetIndex].style.animation = 'none';
        setTimeout(() => {
            tabPanes[targetIndex].style.animation = 'fadeInUp 0.4s ease-out';
        }, 10);
    }
    </script>
    <?php
    return ob_get_clean();
}

// Vertical tabs variation
function renderVerticalTabs($tabs, $tab_id = 'vertical-tabs', $default_active = 0) {
    if (empty($tabs)) {
        return '';
    }
    
    ob_start();
    ?>
    <div id="<?php echo $tab_id; ?>" class="cyber-vertical-tabs" style="display: grid; grid-template-columns: 250px 1fr; gap: 2rem; align-items: start;">
        <!-- Vertical Tab Navigation -->
        <div class="vertical-tab-nav" style="display: flex; flex-direction: column; border-right: 2px solid rgba(0, 255, 255, 0.3);">
            <?php foreach ($tabs as $index => $tab): ?>
                <button 
                    class="vertical-tab-button <?php echo $index === $default_active ? 'active' : ''; ?>" 
                    onclick="switchTab('<?php echo $tab_id; ?>', <?php echo $index; ?>)"
                    style="
                        background: none; 
                        border: none; 
                        color: rgba(255, 255, 255, 0.7); 
                        padding: 1rem 1.5rem; 
                        font-size: 1rem; 
                        font-weight: 600; 
                        text-transform: uppercase; 
                        letter-spacing: 1px;
                        cursor: pointer; 
                        transition: all 0.3s ease;
                        border-right: 3px solid transparent;
                        text-align: left;
                        font-family: var(--font-heading);
                        margin-bottom: 0.5rem;
                        border-radius: 8px 0 0 8px;
                    "
                    data-tab="<?php echo $index; ?>"
                >
                    <?php if (!empty($tab['icon'])): ?>
                        <i class="<?php echo $tab['icon']; ?>" style="margin-right: 0.5rem;"></i>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($tab['title']); ?>
                </button>
            <?php endforeach; ?>
        </div>
        
        <!-- Vertical Tab Content -->
        <div class="vertical-tab-content">
            <?php foreach ($tabs as $index => $tab): ?>
                <div 
                    class="vertical-tab-pane <?php echo $index === $default_active ? 'active' : ''; ?>"
                    data-tab-pane="<?php echo $index; ?>"
                    style="<?php echo $index === $default_active ? 'display: block;' : 'display: none;'; ?> animation: fadeInUp 0.4s ease-out;"
                >
                    <?php echo $tab['content']; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <style>
    .vertical-tab-button.active {
        color: var(--cyber-neon-cyan) !important;
        border-right-color: var(--cyber-neon-cyan) !important;
        background: rgba(0, 255, 255, 0.1) !important;
        text-shadow: 0 0 10px var(--cyber-neon-cyan);
    }
    
    .vertical-tab-button:hover:not(.active) {
        color: var(--cyber-neon-pink) !important;
        background: rgba(255, 0, 255, 0.05) !important;
    }
    
    @media (max-width: 768px) {
        .cyber-vertical-tabs {
            grid-template-columns: 1fr !important;
            gap: 1rem !important;
        }
        
        .vertical-tab-nav {
            border-right: none !important;
            border-bottom: 2px solid rgba(0, 255, 255, 0.3) !important;
            flex-direction: row !important;
            overflow-x: auto;
        }
        
        .vertical-tab-button {
            border-right: none !important;
            border-bottom: 3px solid transparent !important;
            border-radius: 8px 8px 0 0 !important;
            white-space: nowrap;
        }
    }
    </style>
    <?php
    return ob_get_clean();
}
?>