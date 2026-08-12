<?php
// Four Column Grid - Flexible four-column grid layouts for technical specs and feature lists
// Usage: Call renderFourColumnGrid() function with array of items

function renderFourColumnGrid($items) {
    ob_start();
    ?>
    <div class="services-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
        <?php foreach ($items as $item): ?>
            <div class="cyber-card">
                <i class="<?php echo $item['icon']; ?> service-icon"></i>
                <h3 class="service-title"><?php echo $item['title']; ?></h3>
                <?php if (isset($item['items']) && is_array($item['items'])): ?>
                    <ul style="list-style: none; padding: 0; text-align: left; margin-top: 1rem;">
                        <?php foreach ($item['items'] as $list_item): ?>
                            <li style="margin: 0.5rem 0; color: rgba(255,255,255,0.8); position: relative; padding-left: 1rem;">
                                <span style="position: absolute; left: 0; color: var(--cyber-neon-cyan);">◉</span>
                                <?php echo $list_item; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif (isset($item['description'])): ?>
                    <p class="service-description"><?php echo $item['description']; ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

function renderTwoColumnGrid($items) {
    ob_start();
    ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
        <?php foreach ($items as $item): ?>
            <div class="cyber-card">
                <i class="<?php echo $item['icon']; ?> service-icon"></i>
                <h3 class="service-title"><?php echo $item['title']; ?></h3>
                <?php if (isset($item['items']) && is_array($item['items'])): ?>
                    <ul style="list-style: none; padding: 0; text-align: left; margin-top: 1rem;">
                        <?php foreach ($item['items'] as $list_item): ?>
                            <li style="margin: 0.5rem 0; color: rgba(255,255,255,0.8); position: relative; padding-left: 1rem;">
                                <span style="position: absolute; left: 0; color: var(--cyber-neon-cyan);">◉</span>
                                <?php echo $list_item; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif (isset($item['description'])): ?>
                    <p class="service-description"><?php echo $item['description']; ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

function renderThreeColumnGrid($items) {
    ob_start();
    ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem;">
        <?php foreach ($items as $item): ?>
            <div class="cyber-card">
                <i class="<?php echo $item['icon']; ?> service-icon"></i>
                <h3 class="service-title"><?php echo $item['title']; ?></h3>
                <?php if (isset($item['items']) && is_array($item['items'])): ?>
                    <ul style="list-style: none; padding: 0; text-align: left; margin-top: 1rem;">
                        <?php foreach ($item['items'] as $list_item): ?>
                            <li style="margin: 0.5rem 0; color: rgba(255,255,255,0.8); position: relative; padding-left: 1rem;">
                                <span style="position: absolute; left: 0; color: var(--cyber-neon-cyan);">◉</span>
                                <?php echo $list_item; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif (isset($item['description'])): ?>
                    <p class="service-description"><?php echo $item['description']; ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
?>