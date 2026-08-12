<?php
// CTA Section - Reusable call-to-action sections
// Usage: Set variables before include: $cta_title, $cta_subtitle, $cta_buttons, $cta_background
?>
<section class="section" <?php if(isset($cta_background)): ?>style="<?php echo $cta_background; ?>"<?php endif; ?>>
    <div class="container text-center">
        <h2><?php echo isset($cta_title) ? $cta_title : 'READY TO TRANSCEND REALITY?'; ?></h2>
        <p class="hero-subtitle mb-8">
            <?php echo isset($cta_subtitle) ? $cta_subtitle : 'Join the digital revolution. Break free from ordinary. Enter the HiveNest matrix.'; ?>
        </p>
        <div style="display: flex; gap: 2rem; justify-content: center; flex-wrap: wrap;">
            <?php echo isset($cta_buttons) ? $cta_buttons : '<a href="/contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">INITIALIZE SEQUENCE</a>'; ?>
        </div>
    </div>
</section>