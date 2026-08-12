<!-- Scripts -->
    <?php 
    // Determine correct path for JavaScript files
    $script_path = $_SERVER['SCRIPT_NAME'];
    $path_segments = explode('/', trim($script_path, '/'));
    $is_subdirectory = count($path_segments) > 1;
    $js_path = $is_subdirectory ? '../assets/js/' : 'assets/js/';
    ?>
    <script src="<?php echo $js_path; ?>main.js"></script>
    <script src="<?php echo $js_path; ?>cart-neural.js?v=20260721-3"></script>
    <script src="<?php echo $js_path; ?>currency.js?v=20260728-1"></script>
    <script>
        // Enhanced scroll animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.animate-on-scroll').forEach(el => {
            observer.observe(el);
        });

        // Cyberpunk cursor effect
        document.addEventListener('mousemove', (e) => {
            const cursor = document.createElement('div');
            cursor.style.cssText = `
                position: fixed;
                top: ${e.clientY}px;
                left: ${e.clientX}px;
                width: 4px;
                height: 4px;
                background: var(--cyber-neon-cyan);
                border-radius: 50%;
                pointer-events: none;
                z-index: 10000;
                box-shadow: 0 0 10px var(--cyber-neon-cyan);
                animation: fade-out 0.5s ease-out forwards;
            `;
            document.body.appendChild(cursor);
            
            setTimeout(() => cursor.remove(), 500);
        });

        // Add fade-out animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fade-out {
                to {
                    opacity: 0;
                    transform: scale(2);
                }
            }
        `;
        document.head.appendChild(style);

        <?php 
        global $page_scripts;
        if (isset($page_scripts)): 
            // Page-specific scripts
            echo $page_scripts;
        endif; 
        ?>
    </script>
