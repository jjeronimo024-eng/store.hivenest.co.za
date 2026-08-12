<?php
// Pricing Cards - Reusable pricing table components
// Usage: Call renderPricingCard() function with parameters

function renderPricingCard($plan_name, $price, $period, $features, $cta_link, $cta_text, $featured = false, $onclick = null, $accent_color = '', $glow_color = '') {
    $featured_class = $featured ? ' featured' : '';
    $safe_accent = is_string($accent_color) && preg_match('/^#[0-9a-fA-F]{6}$/', $accent_color) ? $accent_color : '';
    $safe_glow = is_string($glow_color) && preg_match('/^#[0-9a-fA-F]{6}$/', $glow_color) ? $glow_color : $safe_accent;
    $featured_color = $safe_accent ?: ($featured ? 'var(--cyber-neon-green)' : 'var(--cyber-neon-cyan)');
    $card_style = $safe_glow
        ? ' style="border-color: ' . htmlspecialchars($safe_accent ?: $safe_glow, ENT_QUOTES) . '; box-shadow: 0 0 22px ' . htmlspecialchars($safe_glow, ENT_QUOTES) . '66, inset 0 0 18px ' . htmlspecialchars($safe_glow, ENT_QUOTES) . '18;"'
        : '';
    $numeric_price = null;
    if (is_numeric($price)) {
        $numeric_price = (float)$price;
    } elseif (is_string($price) && preg_match('/([0-9][0-9,]*(?:\.[0-9]+)?)/', $price, $price_match)) {
        $numeric_price = (float)str_replace(',', '', $price_match[1]);
    }
    $price_data = $numeric_price !== null
        ? ' data-usd-price="' . htmlspecialchars(number_format($numeric_price, 2, '.', ''), ENT_QUOTES) . '"'
        : '';
    
    ob_start();
    ?>
    <div class="pricing-card<?php echo $featured_class; ?>"<?php echo $card_style; ?>>
        <div class="pricing-plan" style="<?php echo $safe_accent ? 'color: ' . htmlspecialchars($safe_accent, ENT_QUOTES) . ';' : ''; ?>"><?php echo $plan_name; ?></div>
        <div class="pricing-amount"<?php echo $price_data; ?>><?php echo $price; ?><span style="font-size: 1rem;"><?php echo $period; ?></span></div>
        <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
            <?php foreach ($features as $feature): ?>
                <li style="margin: 0.5rem 0; color: <?php echo $featured_color; ?>;">
                    ◉ <?php echo $feature; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($onclick): ?>
            <button type="button" data-cart-once="true" onclick="<?php echo $onclick; ?>" class="btn add-to-cart-btn <?php echo $featured ? 'btn-secondary' : 'btn-primary'; ?>" style="width: 100%; cursor: pointer;">
                <?php echo $cta_text; ?>
            </button>
        <?php else: ?>
            <a href="<?php echo $cta_link; ?>" class="btn <?php echo $featured ? 'btn-secondary' : 'btn-primary'; ?>" style="width: 100%;">
                <?php echo $cta_text; ?>
            </a>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function renderPricingGrid($pricing_plans) {
    ob_start();
    ?>
    <div class="pricing-grid">
        <?php foreach ($pricing_plans as $plan): ?>
            <?php echo renderPricingCard(
                $plan['name'], 
                $plan['price'], 
                $plan['period'], 
                $plan['features'], 
                $plan['cta_link'], 
                $plan['cta_text'], 
                isset($plan['featured']) ? $plan['featured'] : false,
                isset($plan['onclick']) ? $plan['onclick'] : null,
                $plan['accent_color'] ?? '',
                $plan['glow_color'] ?? ''
            ); ?>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
?>
