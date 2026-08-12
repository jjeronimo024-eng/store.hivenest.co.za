<?php
// Page variables
$current_page = 'servers';
$page_title = 'Neural Servers - Dedicated Infrastructure | HiveNest';
$page_description = 'Neural Servers - Dedicated server architecture with direct neural processing connections. Linux and Windows servers.';
$page_keywords = 'dedicated servers, neural servers, linux servers, windows servers, cyberpunk hosting';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-domain-server-blue.jpg',
    'url' => 'https://hivenest.co.za/main-services/servers.php',
    'type' => 'service'
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Neural Servers', 'url' => null]
];

// Page-specific JavaScript
$page_scripts = "
function selectServerPlan(planName, price) {
    if (window.shoppingCart) {
        window.shoppingCart.addItem({
            id: 'server-' + planName.toLowerCase().replace(' ', '-'),
            name: 'Server Plan: ' + planName,
            price: price,
            type: 'server'
        });
    }
    
    }
}
";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include '../utilities/head.php'; ?>
</head>
<body>
<?php include '../utilities/nav.php'; ?>

<?php include '../utilities/mobile-menu.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        <img src="assets/images/heroes/hero-domain-server-blue.jpg" alt="Neural Server Architecture" class="hero-background">
        
        <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title">
                    NEURAL<br>
                    <span class="cyber-text">SERVERS</span><br>
                    UNLIMITED POWER
                </h1>
                <p class="hero-subtitle">
                    Dedicated server architecture with direct neural processing connections. 
                    Linux and Windows servers with complete root access and unlimited power.
                </p>
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                    <a href="#plans" class="btn btn-primary">DEPLOY POWER</a>
                    <a href="#specs" class="btn btn-secondary">VIEW SPECS</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Server Types -->
    <section class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>SERVER MATRICES</h2>
                <p class="hero-subtitle">Choose your dedicated processing dimension</p>
            </div>
            
            <div class="services-grid">
                <div class="cyber-card">
                    <i class="fab fa-linux service-icon" style="color: var(--cyber-neon-orange); font-size: 4rem;"></i>
                    <h3 class="service-title">LINUX NEURAL CORE</h3>
                    <p class="service-description">
                        Pure Linux power with complete root access. Ubuntu, CentOS, Debian configurations 
                        with unlimited customization protocols.
                    </p>
                    <div style="margin: 1rem 0;">
                        <div style="color: var(--cyber-neon-orange); font-weight: bold;">Starting at $179/mo</div>
                    </div>
                    <a href="../servers/linux-dedicated.php" class="btn btn-primary">ACCESS LINUX CORE</a>
                </div>
                
                <div class="cyber-card">
                    <i class="fab fa-microsoft service-icon" style="color: var(--cyber-neon-cyan); font-size: 4rem;"></i>
                    <h3 class="service-title">WINDOWS NEURAL HUB</h3>
                    <p class="service-description">
                        Enterprise Windows Server infrastructure with Active Directory, Exchange, 
                        and SQL Server integration capabilities.
                    </p>
                    <div style="margin: 1rem 0;">
                        <div style="color: var(--cyber-neon-cyan); font-weight: bold;">Starting at $199/mo</div>
                    </div>
                    <a href="../servers/windows.php" class="btn btn-primary">DEPLOY WINDOWS HUB</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Server Specifications -->
    <section id="specs" class="section" style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);">
        <div class="container">
            <div class="text-center mb-8">
                <h2>NEURAL SPECIFICATIONS</h2>
                <p class="hero-subtitle">Raw computational power across multiple dimensions</p>
            </div>
            
            <div class="pricing-grid">
                <!-- Entry Server -->
                <div class="pricing-card">
                    <div class="pricing-plan">NEURAL ENTRY</div>
                    <div class="pricing-amount">$179<span style="font-size: 1rem;">/mo</span></div>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Intel Xeon 4-Core Processor</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ 16GB DDR4 Neural RAM</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ 1TB SSD Quantum Storage</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ 1Gbps Neural Network</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Unlimited Data Transfer</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Full Root Access</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-cyan);">◉ Linux/Windows Choice</li>
                    </ul>
                    <a href="../contact.php" class="btn btn-primary" style="width: 100%;">DEPLOY ENTRY</a>
                </div>

                <!-- Pro Server (Featured) -->
                <div class="pricing-card featured">
                    <div class="pricing-plan">NEURAL PRO</div>
                    <div class="pricing-amount">$349<span style="font-size: 1rem;">/mo</span></div>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Intel Xeon 8-Core Processor</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ 64GB DDR4 Neural RAM</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ 2TB SSD Quantum Storage</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ 10Gbps Neural Network</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Unlimited Data Transfer</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Full Root Access</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ IPMI Remote Console</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-green);">◉ Priority Neural Support</li>
                    </ul>
                    <a href="../contact.php" class="btn btn-secondary" style="width: 100%;">ASCEND TO PRO</a>
                </div>

                <!-- Enterprise Server -->
                <div class="pricing-card">
                    <div class="pricing-plan">NEURAL ENTERPRISE</div>
                    <div class="pricing-amount">$699<span style="font-size: 1rem;">/mo</span></div>
                    <ul style="list-style: none; padding: 0; text-align: left; margin: 2rem 0;">
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Dual Intel Xeon 16-Core</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ 128GB DDR4 Neural RAM</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ 4TB SSD Quantum Array</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ 10Gbps Redundant Network</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Unlimited Everything</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Full Hardware Control</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Dedicated Neural Guardian</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Custom Configuration</li>
                        <li style="margin: 0.5rem 0; color: var(--cyber-neon-pink);">◉ Reality-Level SLA</li>
                    </ul>
                    <a href="../contact.php" class="btn btn-primary" style="width: 100%;">ACHIEVE ENTERPRISE</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Server Features -->
    <section class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>NEURAL CAPABILITIES</h2>
                <p class="hero-subtitle">Advanced server features across all processing dimensions</p>
            </div>
            
            <div class="services-grid">
                <div class="cyber-card">
                    <i class="fas fa-microchip service-icon"></i>
                    <h3 class="service-title">QUANTUM PROCESSORS</h3>
                    <p class="service-description">
                        Latest Intel Xeon processors with quantum-level performance and neural network optimization.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-memory service-icon"></i>
                    <h3 class="service-title">NEURAL MEMORY</h3>
                    <p class="service-description">
                        DDR4 ECC RAM modules with error correction and infinite expandability protocols.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-hdd service-icon"></i>
                    <h3 class="service-title">QUANTUM STORAGE</h3>
                    <p class="service-description">
                        Enterprise SSD arrays with RAID configurations and reality-backup redundancy.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-network-wired service-icon"></i>
                    <h3 class="service-title">NEURAL NETWORK</h3>
                    <p class="service-description">
                        High-speed network connections with redundant uplinks and quantum-level routing.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-shield-virus service-icon"></i>
                    <h3 class="service-title">CYBER DEFENSE</h3>
                    <p class="service-description">
                        Advanced DDoS protection and intrusion detection across all dimensional boundaries.
                    </p>
                </div>
                
                <div class="cyber-card">
                    <i class="fas fa-headset service-icon"></i>
                    <h3 class="service-title">NEURAL GUARDIAN</h3>
                    <p class="service-description">
                        24/7 expert support with direct neural interface and instant response protocols.
                    </p>
                </div>
            </div>
        </div>
    </section>

<?php include '../utilities/footer.php'; ?>

<?php include '../utilities/scripts.php'; ?>
</body>
</html>