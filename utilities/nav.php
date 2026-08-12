<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_cache_limiter('');
    @session_start(['read_and_close' => true]);
}
$nav_customer_signed_in = (int) ($_SESSION['customer_id'] ?? 0) > 0;
$nav_portal_url = 'https://cp.hivenest.co.za';
?>
<!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-container">
<?php 
            // Detect if we're in a subdirectory to adjust paths
            $script_path = $_SERVER['SCRIPT_NAME'];
            $is_subdirectory = (substr_count($script_path, '/') > 1);
            $base_path = $is_subdirectory ? '../' : '';
            ?>
            <a href="<?php echo $base_path; ?>index.php" class="navbar-brand">HIVENEST</a>
            
            <!-- Mobile Menu Toggle -->
            <button class="mobile-menu-toggle" aria-label="Toggle mobile menu">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>
            
            <!-- Desktop Navigation -->
            <ul class="navbar-nav">
                <li><a href="<?php echo $base_path; ?>index.php" class="<?php echo ($current_page == 'home') ? 'active' : ''; ?>">HOME</a></li>
                
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">NEURAL DOMAINS</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_path; ?>domains/register.php" class="dropdown-item">
                            <i class="fas fa-plus"></i> Register Domain
                        </a>
                        <a href="<?php echo $base_path; ?>domains/transfer.php" class="dropdown-item">
                            <i class="fas fa-exchange-alt"></i> Transfer Domain
                        </a>
                        <a href="<?php echo $base_path; ?>domains/name-suggestion.php" class="dropdown-item">
                            <i class="fas fa-lightbulb"></i> Name Generator
                        </a>
                        <a href="<?php echo $base_path; ?>domains/extensions.php" class="dropdown-item">
                            <i class="fas fa-star"></i> New Extensions
                        </a>

                    </div>
                </li>
                
                <li class="dropdown">
                    <a href="<?php echo $base_path; ?>domains/cyber-scan.php" class="dropdown-toggle">CYBER SCAN</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_path; ?>domains/whois.php" class="dropdown-item"><i class="fas fa-database"></i> WHOIS Registry</a>
                        <a href="<?php echo $base_path; ?>domains/dns-analysis.php" class="dropdown-item"><i class="fas fa-network-wired"></i> DNS Analysis</a>
                        <a href="<?php echo $base_path; ?>domains/site-analyzer.php" class="dropdown-item"><i class="fas fa-chart-line"></i> Site Analyzer</a>
                    </div>
                </li>
                
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">QUANTUM SERVERS</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_path; ?>hosting/wordpress.php" class="dropdown-item">
                            <i class="fab fa-wordpress"></i> WordPress Hosting
                        </a>
                        <a href="<?php echo $base_path; ?>hosting/windows.php" class="dropdown-item">
                            <i class="fab fa-windows"></i> Windows Shared
                        </a>
                        <a href="<?php echo $base_path; ?>hosting/linux-shared.php" class="dropdown-item">
                            <i class="fab fa-linux"></i> Linux Shared
                        </a>
                        <a href="<?php echo $base_path; ?>servers/windows.php" class="dropdown-item">
                            <i class="fas fa-server"></i> Windows Dedicated
                        </a>
                        <a href="<?php echo $base_path; ?>servers/linux-dedicated.php" class="dropdown-item">
                            <i class="fas fa-microchip"></i> Linux Dedicated
                        </a>
                    </div>
                </li>
                
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">DIGITAL ARSENAL</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_path; ?>tools/sslcert.php" class="dropdown-item">
                            <i class="fas fa-shield-alt"></i> SSL Certificates
                        </a>
                        <a href="<?php echo $base_path; ?>tools/sitelock.php" class="dropdown-item">
                            <i class="fas fa-lock"></i> SiteLock Security
                        </a>
                        <a href="<?php echo $base_path; ?>tools/xcitium.php" class="dropdown-item">
                            <i class="fas fa-database"></i> Xcitium Backup
                        </a>
                    </div>
                </li>
                
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">COMM ARRAYS</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_path; ?>email/google-workspace.php" class="dropdown-item">
                            <i class="fab fa-google"></i> Google Workspace
                        </a>
                        <a href="<?php echo $base_path; ?>email/enterprise.php" class="dropdown-item">
                            <i class="fas fa-building"></i> Enterprise Email
                        </a>
                        <a href="<?php echo $base_path; ?>email/cloud-mail.php" class="dropdown-item">
                            <i class="fas fa-cloud"></i> Cloud Mail
                        </a>
                    </div>
                </li>
                
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">NEURAL GRAPHICS</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_path; ?>branding/logo.php" class="dropdown-item">
                            <i class="fas fa-palette"></i> Logo Design
                        </a>
                        <a href="<?php echo $base_path; ?>branding/signatures.php" class="dropdown-item">
                            <i class="fas fa-signature"></i> Signatures
                        </a>
                        <a href="<?php echo $base_path; ?>branding/letterheads.php" class="dropdown-item">
                            <i class="fas fa-file-alt"></i> Letterheads
                        </a>
                        <a href="<?php echo $base_path; ?>branding/business-cards.php" class="dropdown-item">
                            <i class="fas fa-id-card"></i> Business Cards
                        </a>
                        <a href="<?php echo $base_path; ?>branding/website-builder.php" class="dropdown-item">
                            <i class="fas fa-code"></i> Website Builder
                        </a>
                    </div>
                </li>
                
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">MARKETING MATRIX</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_path; ?>marketing/seo.php" class="dropdown-item">
                            <i class="fas fa-search"></i> SEO Services
                        </a>
                        <a href="<?php echo $base_path; ?>marketing/google-marketing.php" class="dropdown-item">
                            <i class="fab fa-google"></i> Google Marketing
                        </a>
                        <a href="<?php echo $base_path; ?>marketing/social-media.php" class="dropdown-item">
                            <i class="fas fa-share-alt"></i> Social Media Marketing
                        </a>
                    </div>
                </li>
                
                <li><a href="<?php echo $base_path; ?>marketing/offers.php">SPECIAL OPS</a></li>
                <li><a href="<?php echo $base_path; ?>about.php" class="<?php echo ($current_page == 'about') ? 'active' : ''; ?>">ABOUT</a></li>
                <li><a href="<?php echo $base_path; ?>contact.php" class="<?php echo ($current_page == 'contact') ? 'active' : ''; ?>">CONTACT</a></li>
                <li class="currency-nav" title="Display currency; checkout remains USD">
                    <label class="sr-only" for="desktop-currency-select">Display currency</label>
                    <select id="desktop-currency-select" data-currency-select aria-label="Display currency">
                        <option value="USD">USD</option>
                        <option value="ZAR">ZAR</option>
                        <option value="EUR">EUR</option>
                        <option value="SGD">SGD</option>
                    </select>
                </li>
                <li><a href="<?php echo $base_path; ?>cart.php" class="cart-icon" title="Neural Cart" aria-label="Shopping cart">
                    <span class="cart-css-icon" aria-hidden="true"></span>
                    <span class="cart-count is-zero" id="cart-count"></span>
                </a></li>
                <?php if ($nav_customer_signed_in): ?>
                <li class="dropdown portal-dropdown">
                    <a href="<?php echo $nav_portal_url; ?>" class="btn btn-primary dropdown-toggle">ACCESS PORTAL</a>
                    <div class="dropdown-menu" style="right:0;left:auto;min-width:170px;">
                        <a href="<?php echo $base_path; ?>logout.php" class="dropdown-item" title="Sign out of the storefront session">
                            <i class="fas fa-sign-out-alt"></i> Sign Out
                        </a>
                    </div>
                </li>
                <?php else: ?>
                <li>
                    <a href="<?php echo $base_path; ?>auth.php" class="btn btn-primary">ACCESS PORTAL</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
