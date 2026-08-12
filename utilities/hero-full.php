<?php
// Hero Full - Homepage style with cyber orb and buttons
// Usage: Set variables before include: $hero_title, $hero_subtitle, $hero_buttons, $hero_image, $hero_alt
?>
<section class="hero">
    <img src="<?php echo isset($hero_image) ? $hero_image : 'assets/images/heroes/hero-cyberpunk-main.jpg'; ?>" 
         alt="<?php echo isset($hero_alt) ? $hero_alt : 'Cyberpunk Future'; ?>" 
         class="hero-background">
    
    <div class="hero-content">
        <div class="hero-text">
            <h1 class="hero-title">
                <?php echo isset($hero_title) ? $hero_title : 'DIGITAL<br><span class="cyber-text">REVOLUTION</span><br>STARTS HERE'; ?>
            </h1>
            <p class="hero-subtitle">
                <?php echo isset($hero_subtitle) ? $hero_subtitle : 'Break free from ordinary hosting. Enter the future of digital services where cutting-edge technology meets unlimited possibilities.'; ?>
            </p>
            <?php if(isset($hero_buttons)): ?>
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                    <?php echo $hero_buttons; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if(isset($show_cyber_orb) && $show_cyber_orb): ?>
            <div class="hero-visual">
                <div class="cyber-orb"></div>
            </div>
        <?php endif; ?>
    </div>
</section>