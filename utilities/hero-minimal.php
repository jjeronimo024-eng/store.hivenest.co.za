<?php
// Hero Minimal - About/Contact style centered hero
// Usage: Set variables before include: $hero_title, $hero_subtitle, $hero_image, $hero_alt
?>
<section class="hero" style="min-height: 70vh;">
    <img src="<?php echo isset($hero_image) ? $hero_image : 'assets/images/heroes/hero-about-team.jpg'; ?>" 
         alt="<?php echo isset($hero_alt) ? $hero_alt : 'Matrix Background'; ?>" 
         class="hero-background">
    
    <div class="hero-content" style="grid-template-columns: 1fr; text-align: center;">
        <div class="hero-text">
            <h1 class="hero-title">
                <?php echo isset($hero_title) ? $hero_title : 'OUR<br><span class="cyber-text">DIGITAL DNA</span>'; ?>
            </h1>
            <p class="hero-subtitle">
                <?php echo isset($hero_subtitle) ? $hero_subtitle : 'Born in the digital underground, forged in quantum code, evolved beyond reality.'; ?>
            </p>
        </div>
    </div>
</section>