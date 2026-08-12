<?php
// Image Gallery - Image galleries and portfolios component
// Usage: Call renderImageGallery() function with images array

function renderImageGallery($images, $gallery_id = 'gallery', $type = 'grid', $columns = 3) {
    if (empty($images)) {
        return '';
    }
    
    $grid_columns = [
        2 => 'grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));',
        3 => 'grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));',
        4 => 'grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));',
        5 => 'grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));'
    ];
    
    $grid_style = isset($grid_columns[$columns]) ? $grid_columns[$columns] : $grid_columns[3];
    
    ob_start();
    ?>
    <div id="<?php echo $gallery_id; ?>" class="cyber-gallery cyber-gallery-<?php echo $type; ?>">
        <?php if ($type === 'grid'): ?>
            <div class="gallery-grid" style="display: grid; <?php echo $grid_style; ?> gap: 1.5rem;">
                <?php foreach ($images as $index => $image): ?>
                    <div class="gallery-item" style="position: relative; overflow: hidden; border-radius: 12px; border: 1px solid rgba(0, 255, 255, 0.3); cursor: pointer; transition: all 0.3s ease;" onclick="openLightbox('<?php echo $gallery_id; ?>', <?php echo $index; ?>)">
                        <img src="<?php echo htmlspecialchars($image['src']); ?>" 
                             alt="<?php echo htmlspecialchars($image['alt'] ?? ''); ?>" 
                             style="width: 100%; height: 250px; object-fit: cover; transition: transform 0.3s ease;">
                        
                        <div class="gallery-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(45deg, rgba(0, 255, 255, 0.1) 0%, rgba(255, 0, 255, 0.1) 100%); opacity: 0; transition: opacity 0.3s ease; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-expand-alt" style="color: white; font-size: 2rem; text-shadow: 0 0 10px rgba(255, 255, 255, 0.8);"></i>
                        </div>
                        
                        <?php if (!empty($image['title'])): ?>
                            <div class="gallery-caption" style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0, 0, 0, 0.8)); color: white; padding: 1rem; transform: translateY(100%); transition: transform 0.3s ease;">
                                <h4 style="color: var(--cyber-neon-cyan); margin: 0 0 0.5rem 0; font-size: 1.1rem;"><?php echo htmlspecialchars($image['title']); ?></h4>
                                <?php if (!empty($image['description'])): ?>
                                    <p style="margin: 0; font-size: 0.9rem; color: rgba(255, 255, 255, 0.8);"><?php echo htmlspecialchars($image['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Lightbox Modal -->
    <div id="<?php echo $gallery_id; ?>-lightbox" class="lightbox-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.95); z-index: 10000; backdrop-filter: blur(10px);">
        <div class="lightbox-content" style="display: flex; align-items: center; justify-content: center; height: 100%; padding: 20px;">
            <button class="lightbox-close" onclick="closeLightbox('<?php echo $gallery_id; ?>')" style="position: absolute; top: 20px; right: 20px; background: none; border: none; color: white; font-size: 2rem; cursor: pointer; z-index: 10001; padding: 10px; transition: all 0.3s ease;">
                <i class="fas fa-times"></i>
            </button>
            
            <button class="lightbox-prev" onclick="prevImage('<?php echo $gallery_id; ?>')" style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); background: rgba(0, 255, 255, 0.2); border: 1px solid rgba(0, 255, 255, 0.5); color: var(--cyber-neon-cyan); font-size: 1.5rem; cursor: pointer; padding: 15px 20px; border-radius: 8px; transition: all 0.3s ease;">
                <i class="fas fa-chevron-left"></i>
            </button>
            
            <button class="lightbox-next" onclick="nextImage('<?php echo $gallery_id; ?>')" style="position: absolute; right: 20px; top: 50%; transform: translateY(-50%); background: rgba(0, 255, 255, 0.2); border: 1px solid rgba(0, 255, 255, 0.5); color: var(--cyber-neon-cyan); font-size: 1.5rem; cursor: pointer; padding: 15px 20px; border-radius: 8px; transition: all 0.3s ease;">
                <i class="fas fa-chevron-right"></i>
            </button>
            
            <div class="lightbox-image-container" style="max-width: 90%; max-height: 90%; text-align: center;">
                <img id="<?php echo $gallery_id; ?>-lightbox-image" src="" alt="" style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 8px; box-shadow: 0 0 50px rgba(0, 255, 255, 0.3);">
                
                <div id="<?php echo $gallery_id; ?>-lightbox-caption" class="lightbox-caption" style="margin-top: 1rem; color: white; max-width: 600px; margin-left: auto; margin-right: auto;">
                    <h3 style="color: var(--cyber-neon-cyan); margin: 0 0 0.5rem 0;"></h3>
                    <p style="color: rgba(255, 255, 255, 0.8); margin: 0;"></p>
                </div>
            </div>
            
            <div class="lightbox-counter" style="position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); color: var(--cyber-neon-cyan); font-family: var(--font-heading);">
                <span id="<?php echo $gallery_id; ?>-counter">1</span> / <?php echo count($images); ?>
            </div>
        </div>
    </div>
    
    <style>
    .gallery-item:hover img {
        transform: scale(1.05);
    }
    
    .gallery-item:hover .gallery-overlay {
        opacity: 1;
    }
    
    .gallery-item:hover .gallery-caption {
        transform: translateY(0);
    }
    
    .lightbox-close:hover,
    .lightbox-prev:hover,
    .lightbox-next:hover {
        background: rgba(0, 255, 255, 0.4) !important;
        text-shadow: 0 0 10px var(--cyber-neon-cyan);
    }
    
    @media (max-width: 768px) {
        .lightbox-prev,
        .lightbox-next {
            display: none;
        }
        
        .gallery-grid {
            grid-template-columns: 1fr !important;
        }
    }
    </style>
    
    <script>
    const galleries = {};
    
    // Initialize gallery
    galleries['<?php echo $gallery_id; ?>'] = {
        images: <?php echo json_encode($images); ?>,
        currentIndex: 0
    };
    
    function openLightbox(galleryId, index) {
        const gallery = galleries[galleryId];
        const lightbox = document.getElementById(galleryId + '-lightbox');
        const img = document.getElementById(galleryId + '-lightbox-image');
        const caption = document.getElementById(galleryId + '-lightbox-caption');
        const counter = document.getElementById(galleryId + '-counter');
        
        gallery.currentIndex = index;
        
        // Set image and caption
        img.src = gallery.images[index].src;
        img.alt = gallery.images[index].alt || '';
        
        if (gallery.images[index].title) {
            caption.querySelector('h3').textContent = gallery.images[index].title;
            caption.querySelector('p').textContent = gallery.images[index].description || '';
            caption.style.display = 'block';
        } else {
            caption.style.display = 'none';
        }
        
        counter.textContent = index + 1;
        
        // Show lightbox
        lightbox.style.display = 'block';
        document.body.style.overflow = 'hidden';
        
        // Keyboard navigation
        document.addEventListener('keydown', handleKeyPress);
    }
    
    function closeLightbox(galleryId) {
        const lightbox = document.getElementById(galleryId + '-lightbox');
        lightbox.style.display = 'none';
        document.body.style.overflow = '';
        document.removeEventListener('keydown', handleKeyPress);
    }
    
    function nextImage(galleryId) {
        const gallery = galleries[galleryId];
        gallery.currentIndex = (gallery.currentIndex + 1) % gallery.images.length;
        openLightbox(galleryId, gallery.currentIndex);
    }
    
    function prevImage(galleryId) {
        const gallery = galleries[galleryId];
        gallery.currentIndex = (gallery.currentIndex - 1 + gallery.images.length) % gallery.images.length;
        openLightbox(galleryId, gallery.currentIndex);
    }
    
    function handleKeyPress(e) {
        const galleryId = '<?php echo $gallery_id; ?>';
        switch(e.key) {
            case 'Escape':
                closeLightbox(galleryId);
                break;
            case 'ArrowLeft':
                prevImage(galleryId);
                break;
            case 'ArrowRight':
                nextImage(galleryId);
                break;
        }
    }
    </script>
    <?php
    return ob_get_clean();
}

// Simple image grid without lightbox
function renderSimpleImageGrid($images, $columns = 3) {
    if (empty($images)) {
        return '';
    }
    
    $grid_columns = [
        2 => 'grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));',
        3 => 'grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));',
        4 => 'grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));'
    ];
    
    $grid_style = isset($grid_columns[$columns]) ? $grid_columns[$columns] : $grid_columns[3];
    
    ob_start();
    ?>
    <div class="simple-image-grid" style="display: grid; <?php echo $grid_style; ?> gap: 1.5rem;">
        <?php foreach ($images as $image): ?>
            <div class="image-item" style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(0, 255, 255, 0.3);">
                <img src="<?php echo htmlspecialchars($image['src']); ?>" 
                     alt="<?php echo htmlspecialchars($image['alt'] ?? ''); ?>" 
                     style="width: 100%; height: 200px; object-fit: cover;">
                
                <?php if (!empty($image['title']) || !empty($image['description'])): ?>
                    <div class="image-caption" style="padding: 1rem; background: rgba(0, 0, 0, 0.8);">
                        <?php if (!empty($image['title'])): ?>
                            <h4 style="color: var(--cyber-neon-cyan); margin: 0 0 0.5rem 0;"><?php echo htmlspecialchars($image['title']); ?></h4>
                        <?php endif; ?>
                        <?php if (!empty($image['description'])): ?>
                            <p style="color: rgba(255, 255, 255, 0.8); margin: 0; font-size: 0.9rem;"><?php echo htmlspecialchars($image['description']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
?>