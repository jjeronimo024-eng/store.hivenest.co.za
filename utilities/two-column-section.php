<?php
// Two Column Section - Flexible two-column layouts
// Usage: Set variables before include: $column1_content, $column2_content, $section_background, $reverse_columns
?>
<section class="section" <?php if(isset($section_background)): ?>style="<?php echo $section_background; ?>"<?php endif; ?>>
    <div class="container">
        <div style="display: grid; grid-template-columns: <?php echo (isset($reverse_columns) && $reverse_columns) ? '1fr 1fr' : '1fr 1fr'; ?>; gap: 4rem; align-items: start;">
            <?php if(isset($reverse_columns) && $reverse_columns): ?>
                <div class="animate-on-scroll">
                    <?php echo isset($column2_content) ? $column2_content : ''; ?>
                </div>
                <div class="animate-on-scroll">
                    <?php echo isset($column1_content) ? $column1_content : ''; ?>
                </div>
            <?php else: ?>
                <div class="animate-on-scroll">
                    <?php echo isset($column1_content) ? $column1_content : ''; ?>
                </div>
                <div class="animate-on-scroll">
                    <?php echo isset($column2_content) ? $column2_content : ''; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php
// Function to render two column lists from array data
function renderTwoColumnLists($columns) {
    ob_start();
    ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
        <?php foreach ($columns as $column): ?>
            <div class="cyber-card">
                <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 1.5rem;"><?php echo $column['title']; ?></h3>
                <ul style="list-style: none; padding: 0;">
                    <?php foreach ($column['items'] as $item): ?>
                        <li style="margin: 0.75rem 0; color: rgba(255,255,255,0.8); padding-left: 1.5rem; position: relative;">
                            <i class="fas fa-check" style="position: absolute; left: 0; top: 0.2rem; color: var(--cyber-neon-green);"></i>
                            <?php echo $item; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

// Function to render simple two column content
function renderTwoColumnContent($column1_title, $column1_content, $column2_title, $column2_content) {
    ob_start();
    ?>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: start;">
        <div class="animate-on-scroll">
            <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 1.5rem;"><?php echo $column1_title; ?></h3>
            <div style="color: rgba(255,255,255,0.8); line-height: 1.6;">
                <?php echo $column1_content; ?>
            </div>
        </div>
        <div class="animate-on-scroll">
            <h3 style="color: var(--cyber-neon-cyan); margin-bottom: 1.5rem;"><?php echo $column2_title; ?></h3>
            <div style="color: rgba(255,255,255,0.8); line-height: 1.6;">
                <?php echo $column2_content; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>