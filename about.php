<?php
// Page variables
$current_page = 'about';
$page_title = 'About HiveNest Matrix - Digital Evolution Story';
$page_description = 'About HiveNest Matrix - Learn about our digital evolution journey and the cyberpunk future of hosting services.';
$page_keywords = 'about hivenest, cyberpunk hosting, digital evolution, neural network technology';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'utilities/head.php'; ?>
</head>
<body>
<?php include 'utilities/nav.php'; ?>

<?php include 'utilities/mobile-menu.php'; ?>

    <!-- Hero Section -->
    <section class="hero" style="min-height: 70vh;">
        <img src="assets/images/heroes/hero-about-team.jpg" alt="About Matrix" class="hero-background">
        
        <div class="hero-content" style="grid-template-columns: 1fr; text-align: center;">
            <div class="hero-text">
                <h1 class="hero-title">
                    OUR<br>
                    <span class="cyber-text">DIGITAL DNA</span>
                </h1>
                <p class="hero-subtitle">
                    Born in the digital underground, forged in quantum code, evolved beyond reality.
                </p>
            </div>
        </div>
    </section>

    <!-- About Content -->
    <section class="section">
        <div class="container">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: start;">
                <div class="animate-on-scroll">
                    <h2>THE ORIGIN PROTOCOL</h2>
                    
                    <p style="font-size: 1.3rem; line-height: 1.8; color: var(--cyber-neon-green); margin-bottom: 2rem;">
                        In 2019, a group of digital rebels decided that ordinary hosting wasn't enough. 
                        We broke the code, shattered limitations, and entered uncharted dimensions.
                    </p>
                    
                    <p style="font-size: 1.2rem; line-height: 1.8; color: rgba(255, 255, 255, 0.8); margin-bottom: 2rem;">
                        HiveNest Matrix was born from quantum algorithms and cyberpunk dreams. We've transcended 
                        conventional hosting to create digital realities that exist beyond imagination.
                    </p>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 3rem;">
                        <div class="cyber-card" style="text-align: center; padding: 1.5rem;">
                            <div style="font-size: 3rem; color: var(--cyber-neon-pink); font-family: var(--font-cyber);">5000+</div>
                            <p style="color: var(--cyber-neon-cyan); text-transform: uppercase;">Digital Pioneers</p>
                        </div>
                        <div class="cyber-card" style="text-align: center; padding: 1.5rem;">
                            <div style="font-size: 3rem; color: var(--cyber-neon-green); font-family: var(--font-cyber);">99.99%</div>
                            <p style="color: var(--cyber-neon-cyan); text-transform: uppercase;">Reality Uptime</p>
                        </div>
                    </div>
                </div>
                
                <div class="animate-on-scroll" style="position: relative;">
                    <div style="position: relative;">
                        <img src="assets/images/heroes/hero-about-team.jpg" alt="Digital Team" style="width: 100%; border-radius: 20px; filter: hue-rotate(45deg) contrast(1.2);">
                        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(45deg, rgba(255, 0, 255, 0.2) 0%, rgba(0, 255, 255, 0.2) 100%); border-radius: 20px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Core Values Matrix -->
    <section id="values" class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
        <div class="container">
            <div class="text-center mb-8">
                <h2>CORE PROTOCOLS</h2>
                <p class="hero-subtitle">The digital DNA that drives our matrix</p>
            </div>
            
            <div class="services-grid">
                <div class="cyber-card">
                    <i class="fas fa-brain service-icon"></i>
                    <h3 class="service-title">QUANTUM INNOVATION</h3>
                    <p class="service-description">
                        We don't follow trends - we create them. Our neural networks predict tomorrow's technology today.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-infinity service-icon"></i>
                    <h3 class="service-title">INFINITE POSSIBILITIES</h3>
                    <p class="service-description">
                        Every project opens new dimensions. We believe in pushing beyond limits to achieve the impossible.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-rocket service-icon"></i>
                    <h3 class="service-title">VELOCITY OBSESSION</h3>
                    <p class="service-description">
                        Speed isn't just performance - it's survival. We operate at quantum velocity across all realities.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-shield-alt service-icon"></i>
                    <h3 class="service-title">FORTRESS SECURITY</h3>
                    <p class="service-description">
                        Your digital assets exist in an impenetrable quantum fortress protected by AI sentinels.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-users service-icon"></i>
                    <h3 class="service-title">COLLECTIVE CONSCIOUSNESS</h3>
                    <p class="service-description">
                        We're not just a team - we're a hive mind working toward digital evolution for all.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-globe service-icon"></i>
                    <h3 class="service-title">REALITY BREAKING</h3>
                    <p class="service-description">
                        We don't just serve customers worldwide - we connect parallel digital universes.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="section">
        <div class="container text-center">
            <h2>READY TO JOIN THE MATRIX?</h2>
            <p class="hero-subtitle mb-8">
                Become part of the digital revolution. Break free from ordinary. Enter our reality.
            </p>
            <div style="display: flex; gap: 2rem; justify-content: center; flex-wrap: wrap;">
                <a href="contact.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 20px 40px;">INITIALIZE CONNECTION</a>
            </div>
        </div>
    </section>

<?php include 'utilities/footer.php'; ?>

<?php include 'utilities/scripts.php'; ?>
</body>
</html>