<?php
// Grid Section - Reusable section with header and grid content
// Usage: Set variables before include: $grid_title, $grid_subtitle, $grid_content, $grid_background
?>
<section class="section" <?php if(isset($grid_background)): ?>style="<?php echo $grid_background; ?>"<?php endif; ?>>
    <div class="container">
        <?php if(isset($grid_title)): ?>
            <div class="text-center mb-8">
                <h2><?php echo $grid_title; ?></h2>
                <?php if(isset($grid_subtitle)): ?>
                    <p class="hero-subtitle"><?php echo $grid_subtitle; ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php echo isset($grid_content) ? $grid_content : ''; ?>
    </div>
</section>