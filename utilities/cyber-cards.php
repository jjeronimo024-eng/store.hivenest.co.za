<?php
// Cyber Cards - Reusable service/feature cards
// Usage: Call renderCyberCard() function with parameters

function renderCyberCard($icon, $title, $description, $link = null, $link_text = null) {
    ob_start();
    ?>
    <div class="cyber-card">
        <i class="<?php echo $icon; ?> service-icon"></i>
        <h3 class="service-title"><?php echo $title; ?></h3>
        <p class="service-description">
            <?php echo $description; ?>
        </p>
        <?php if ($link && $link_text): ?>
            <div style="margin-top: 2rem;">
                <a href="<?php echo $link; ?>" class="btn btn-primary"><?php echo $link_text; ?></a>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function renderServiceCard($icon, $title, $description, $link = null, $link_text = null) {
    ob_start();
    ?>
    <div class="service-card">
        <i class="<?php echo $icon; ?> service-icon"></i>
        <h3 class="service-title"><?php echo $title; ?></h3>
        <p class="service-description">
            <?php echo $description; ?>
        </p>
        <?php if ($link && $link_text): ?>
            <div style="margin-top: 2rem;">
                <a href="<?php echo $link; ?>" class="btn btn-primary"><?php echo $link_text; ?></a>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// Render multiple cards from array
function renderCyberCardsGrid($cards, $type = 'cyber') {
    ob_start();
    ?>
    <div class="services-grid">
        <?php foreach ($cards as $card): ?>
            <?php if ($type === 'service'): ?>
                <?php echo renderServiceCard($card['icon'], $card['title'], $card['description'], 
                          isset($card['link']) ? $card['link'] : null, 
                          isset($card['link_text']) ? $card['link_text'] : null); ?>
            <?php else: ?>
                <?php echo renderCyberCard($card['icon'], $card['title'], $card['description'], 
                          isset($card['link']) ? $card['link'] : null, 
                          isset($card['link_text']) ? $card['link_text'] : null); ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
?>