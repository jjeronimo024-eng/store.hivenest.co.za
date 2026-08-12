<!-- Mobile Menu Overlay -->
    <div class="mobile-menu-overlay">
        <div class="mobile-menu-content">
            <div class="mobile-menu-header">
                <span class="mobile-menu-title">NEURAL NETWORK</span>
                <button class="mobile-menu-close" aria-label="Close mobile menu">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
<?php 
            // Detect if we're in a subdirectory to adjust paths
            $script_path = $_SERVER['SCRIPT_NAME'];
            $is_subdirectory = (substr_count($script_path, '/') > 1);
            $base_path = $is_subdirectory ? '../' : '';
            ?>
            <div class="mobile-menu-body">
                <ul class="mobile-menu-list">
                    <li><a href="<?php echo $base_path; ?>index.php" class="mobile-menu-link <?php echo ($current_page == 'home') ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>HOME</span>
                    </a></li>
                    <li><a href="<?php echo $base_path; ?>main-services/domains.php" class="mobile-menu-link">
                        <i class="fas fa-globe"></i>
                        <span>NEURAL DOMAINS</span>
                    </a></li>
                    <li><a href="<?php echo $base_path; ?>domains/cyber-scan.php" class="mobile-menu-link">
                        <i class="fas fa-search"></i>
                        <span>CYBER SCAN</span>
                    </a></li>
                    <li><a href="<?php echo $base_path; ?>domains/whois.php" class="mobile-menu-link"><i class="fas fa-database"></i><span>— WHOIS REGISTRY</span></a></li>
                    <li><a href="<?php echo $base_path; ?>domains/dns-analysis.php" class="mobile-menu-link"><i class="fas fa-network-wired"></i><span>— DNS ANALYSIS</span></a></li>
                    <li><a href="<?php echo $base_path; ?>domains/site-analyzer.php" class="mobile-menu-link"><i class="fas fa-chart-line"></i><span>— SITE ANALYZER</span></a></li>
                    <li><a href="<?php echo $base_path; ?>main-services/hosting.php" class="mobile-menu-link">
                        <i class="fas fa-server"></i>
                        <span>QUANTUM SERVERS</span>
                    </a></li>
                    <li><a href="<?php echo $base_path; ?>main-services/tools.php" class="mobile-menu-link">
                        <i class="fas fa-shield-alt"></i>
                        <span>DIGITAL ARSENAL</span>
                    </a></li>
                    <li><a href="<?php echo $base_path; ?>main-services/email.php" class="mobile-menu-link">
                        <i class="fas fa-envelope"></i>
                        <span>COMM ARRAYS</span>
                    </a></li>
                    <li><a href="<?php echo $base_path; ?>branding/logo.php" class="mobile-menu-link">
                        <i class="fas fa-palette"></i>
                        <span>NEURAL GRAPHICS</span>
                    </a></li>
                    <li><a href="<?php echo $base_path; ?>marketing/seo.php" class="mobile-menu-link">
                        <i class="fas fa-chart-line"></i>
                        <span>MARKETING MATRIX</span>
                    </a></li>
                    <li><a href="<?php echo $base_path; ?>marketing/offers.php" class="mobile-menu-link">
                        <i class="fas fa-bolt"></i>
                        <span>SPECIAL OPS</span>
                    </a></li>
                    <li><a href="<?php echo $base_path; ?>about.php" class="mobile-menu-link <?php echo ($current_page == 'about') ? 'active' : ''; ?>">
                        <i class="fas fa-info-circle"></i>
                        <span>ABOUT</span>
                    </a></li>
                    <li><a href="<?php echo $base_path; ?>contact.php" class="mobile-menu-link <?php echo ($current_page == 'contact') ? 'active' : ''; ?>">
                        <i class="fas fa-comments"></i>
                        <span>CONTACT</span>
                    </a></li>
                </ul>
                
                <div class="mobile-menu-actions">
                    <label for="mobile-currency-select" style="display:block;color:var(--cyber-neon-cyan);margin-bottom:6px;">DISPLAY CURRENCY</label>
                    <select id="mobile-currency-select" data-currency-select class="mobile-menu-btn" style="width:100%;margin-bottom:15px;background:#0a0a0a;color:#fff;border:1px solid var(--cyber-neon-cyan);padding:12px;">
                        <option value="USD">USD — US Dollar</option>
                        <option value="ZAR">ZAR — South African Rand</option>
                        <option value="EUR">EUR — Euro</option>
                        <option value="SGD">SGD — Singapore Dollar</option>
                    </select>
                    <a href="<?php echo $base_path; ?>cart.php" class="btn btn-outline mobile-menu-btn" style="margin-bottom: 15px; position: relative;">
                        <span class="cart-css-icon" aria-hidden="true"></span>
                        NEURAL CART
                        <span class="cart-count is-zero" id="mobile-cart-count" style="position: absolute; top: -5px; right: -5px;"></span>
                    </a>
                    <?php if (!empty($nav_customer_signed_in)): ?>
                    <a href="https://cp.hivenest.co.za" class="btn btn-primary mobile-menu-btn">
                        <i class="fas fa-user-circle"></i>
                        ACCESS PORTAL
                    </a>
                    <a href="<?php echo $base_path; ?>logout.php" class="btn btn-outline mobile-menu-btn" style="margin-top:10px;">SIGN OUT</a>
                    <?php else: ?>
                    <a href="<?php echo $base_path; ?>auth.php" class="btn btn-primary mobile-menu-btn">
                        <i class="fas fa-rocket"></i>
                        ACCESS PORTAL
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
