<?php
// Page Header - Standard page header template with optional breadcrumbs
// Usage: Set variables before include: $page_title, $page_subtitle, $breadcrumbs
?>
<div class="text-center mb-8">
    <?php if(isset($breadcrumbs) && !empty($breadcrumbs)): ?>
        <nav style="margin-bottom: 2rem;">
            <ol style="display: flex; justify-content: center; list-style: none; padding: 0; gap: 1rem; color: rgba(255,255,255,0.7);">
                <?php foreach($breadcrumbs as $index => $crumb): ?>
                    <li style="display: flex; align-items: center;">
                        <?php if($index > 0): ?>
                            <span style="margin-right: 1rem; color: var(--cyber-neon-cyan);">&gt;</span>
                        <?php endif; ?>
                        <?php if(isset($crumb['url'])): ?>
                            <a href="<?php echo $crumb['url']; ?>" style="color: var(--cyber-neon-cyan); text-decoration: none;">
                                <?php echo $crumb['text']; ?>
                            </a>
                        <?php else: ?>
                            <span style="color: white; font-weight: 600;"><?php echo $crumb['text']; ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
    <?php endif; ?>
    
    <h2><?php echo isset($page_title) ? $page_title : 'Page Title'; ?></h2>
    <?php if(isset($page_subtitle)): ?>
        <p class="hero-subtitle"><?php echo $page_subtitle; ?></p>
    <?php endif; ?>
</div>