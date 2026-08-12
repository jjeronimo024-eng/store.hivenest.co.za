<?php
// Breadcrumbs - Dynamic breadcrumb navigation
// Usage: Call renderBreadcrumbs() function with array of breadcrumbs

function renderBreadcrumbs($breadcrumbs, $separator = '>', $home_text = 'Home', $home_url = '/index.php') {
    if (empty($breadcrumbs)) {
        return '';
    }
    
    ob_start();
    ?>
    <nav aria-label="breadcrumb" style="margin-bottom: 2rem;">
        <ol style="display: flex; list-style: none; padding: 0; gap: 1rem; color: rgba(255,255,255,0.7); align-items: center; flex-wrap: wrap;">
            <!-- Home breadcrumb -->
            <li style="display: flex; align-items: center;">
                <a href="<?php echo $home_url; ?>" style="color: var(--cyber-neon-cyan); text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.textShadow='0 0 10px var(--cyber-neon-cyan)'" onmouseout="this.style.textShadow='none'">
                    <i class="fas fa-home" style="margin-right: 0.5rem;"></i>
                    <?php echo $home_text; ?>
                </a>
            </li>
            
            <?php foreach($breadcrumbs as $index => $crumb): ?>
                <li style="display: flex; align-items: center;">
                    <span style="margin: 0 0.5rem; color: var(--cyber-neon-pink); font-weight: bold;"><?php echo $separator; ?></span>
                    <?php if(isset($crumb['url']) && !empty($crumb['url'])): ?>
                        <a href="<?php echo $crumb['url']; ?>" style="color: var(--cyber-neon-cyan); text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.textShadow='0 0 10px var(--cyber-neon-cyan)'" onmouseout="this.style.textShadow='none'">
                            <?php echo $crumb['text']; ?>
                        </a>
                    <?php else: ?>
                        <span style="color: white; font-weight: 600; text-shadow: 0 0 5px rgba(255,255,255,0.5);">
                            <?php echo $crumb['text']; ?>
                        </span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>
    <?php
    return ob_get_clean();
}

// Auto-generate breadcrumbs from current URL
function generateBreadcrumbs($custom_titles = []) {
    $url_path = trim($_SERVER['REQUEST_URI'], '/');
    $path_parts = explode('/', $url_path);
    $breadcrumbs = [];
    $current_path = '';
    
    // Remove file extension and convert to readable format
    foreach($path_parts as $part) {
        if(empty($part)) continue;
        
        // Remove .php extension
        $clean_part = str_replace('.php', '', $part);
        
        // Build current path
        $current_path .= ($current_path ? '/' : '') . $part;
        
        // Generate readable title
        $title = isset($custom_titles[$clean_part]) 
            ? $custom_titles[$clean_part] 
            : ucwords(str_replace(['-', '_'], ' ', $clean_part));
        
        // Only add URL if not the current page (last item)
        $is_current = ($part === end($path_parts));
        
        $breadcrumbs[] = [
            'text' => $title,
            'url' => $is_current ? null : $current_path
        ];
    }
    
    return $breadcrumbs;
}

// Get section-specific breadcrumbs
function getSectionBreadcrumbs($section) {
    $breadcrumbs_map = [
        'domains' => [
            ['text' => 'Neural Domains', 'url' => '/main-services/domains.php'],
        ],
        'hosting' => [
            ['text' => 'Quantum Hosting', 'url' => '/main-services/hosting.php'],
        ],
        'servers' => [
            ['text' => 'Neural Servers', 'url' => '/main-services/servers.php'],
        ],
        'email' => [
            ['text' => 'Comm Arrays', 'url' => '/main-services/email.php'],
        ],
        'tools' => [
            ['text' => 'Digital Arsenal', 'url' => '/main-services/tools.php'],
        ],
        'branding' => [
            ['text' => 'Neural Graphics', 'url' => '/branding/logo.php'],
        ],
        'legal' => [
            ['text' => 'Legal Matrix', 'url' => '/legal/terms-of-service.php'],
        ],
        'pricing' => [
            ['text' => 'Power Levels', 'url' => '/pricing/hosting-plans.php'],
        ]
    ];
    
    return isset($breadcrumbs_map[$section]) ? $breadcrumbs_map[$section] : [];
}
?>