<?php
// Page variables
$current_page = 'legal';
$page_title = 'Privacy Policy - HiveNest';
$page_description = 'HiveNest Privacy Policy - How we collect, use, and protect your personal information.';
$page_keywords = 'privacy policy, data protection, personal information, cyberpunk privacy';

// SEO configuration
$seo_config = [
    'title' => $page_title,
    'description' => $page_description,
    'keywords' => $page_keywords,
    'image' => 'assets/images/heroes/hero-security-circuit.jpg',
    'url' => 'https://hivenest.co.za/legal/privacy-policy.php',
    'type' => 'legal'
];

// Breadcrumbs
$breadcrumbs = [
    ['text' => 'Privacy Policy', 'url' => null]
];
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
        <img src="assets/images/heroes/hero-security-circuit.jpg" alt="Privacy Policy" class="hero-background">
        
        <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title">
                    PRIVACY<br>
                    <span class="cyber-text">POLICY</span><br>
                    NEURAL DATA
                </h1>
                <p class="hero-subtitle">
                    Your digital privacy is our quantum priority. Understanding how we protect 
                    your neural data across all dimensions of the matrix.
                </p>
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                    <a href="#policy" class="btn btn-primary">READ POLICY</a>
                    <a href="../contact.php" class="btn btn-secondary">CONTACT US</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Content -->
    <section id="policy" class="section">
        <div class="container">
            <div class="text-center mb-8">
                <h2>PRIVACY POLICY</h2>
                <p class="hero-subtitle">Last updated: December 2024</p>
            </div>
            
            <div class="cyber-card" style="max-width: 1000px; margin: 0 auto;">
                <div class="service-card">
                    <h3 class="service-title">1. INFORMATION WE COLLECT</h3>
                    <p class="service-description">
                        We collect information you provide directly to us, such as when you create an account, use our services, 
                        or contact us for support.
                    </p>
                    <h4 style="color: var(--cyber-neon-cyan); margin: 1rem 0;">Personal Information</h4>
                    <ul style="color: rgba(255, 255, 255, 0.8); margin-left: 2rem;">
                        <li>Name and contact information</li>
                        <li>Email address and phone number</li>
                        <li>Billing and payment information</li>
                        <li>Company information</li>
                    </ul>
                    <h4 style="color: var(--cyber-neon-cyan); margin: 1rem 0;">Technical Information</h4>
                    <ul style="color: rgba(255, 255, 255, 0.8); margin-left: 2rem;">
                        <li>IP address and device information</li>
                        <li>Browser type and version</li>
                        <li>Usage data and analytics</li>
                        <li>Cookies and tracking technologies</li>
                    </ul>
                </div>

                <div class="service-card">
                    <h3 class="service-title">2. HOW WE USE YOUR INFORMATION</h3>
                    <p class="service-description">We use the information we collect to:</p>
                    <ul style="color: rgba(255, 255, 255, 0.8); margin-left: 2rem;">
                        <li>Provide, maintain, and improve our services</li>
                        <li>Process transactions and send related information</li>
                        <li>Send you technical notices and support messages</li>
                        <li>Respond to your comments and questions</li>
                        <li>Communicate about products, services, and events</li>
                        <li>Monitor and analyze trends and usage</li>
                        <li>Detect, investigate, and prevent fraudulent activities</li>
                    </ul>
                </div>

                <div class="service-card">
                    <h3 class="service-title">3. INFORMATION SHARING</h3>
                    <p class="service-description">
                        We do not sell, trade, or otherwise transfer your personal information to third parties without your consent, 
                        except as described in this policy.
                    </p>
                    <h4 style="color: var(--cyber-neon-cyan); margin: 1rem 0;">We may share information:</h4>
                    <ul style="color: rgba(255, 255, 255, 0.8); margin-left: 2rem;">
                        <li>With service providers who assist us in providing our services</li>
                        <li>To comply with legal obligations or protect our rights</li>
                        <li>In connection with a merger, acquisition, or sale of assets</li>
                        <li>With your consent or at your direction</li>
                    </ul>
                </div>

                <div class="service-card">
                    <h3 class="service-title">4. DATA SECURITY</h3>
                    <p class="service-description">
                        We implement appropriate technical and organizational measures to protect your personal information against 
                        unauthorized access, alteration, disclosure, or destruction.
                    </p>
                    <ul style="color: rgba(255, 255, 255, 0.8); margin-left: 2rem;">
                        <li>SSL encryption for data transmission</li>
                        <li>Regular security assessments and updates</li>
                        <li>Access controls and employee training</li>
                        <li>Secure data storage and backup procedures</li>
                    </ul>
                </div>

                <div class="service-card">
                    <h3 class="service-title">5. YOUR RIGHTS</h3>
                    <p class="service-description">You have the right to:</p>
                    <ul style="color: rgba(255, 255, 255, 0.8); margin-left: 2rem;">
                        <li>Access and receive a copy of your personal information</li>
                        <li>Rectify inaccurate or incomplete information</li>
                        <li>Delete your personal information</li>
                        <li>Restrict or object to processing</li>
                        <li>Data portability</li>
                        <li>Withdraw consent at any time</li>
                    </ul>
                </div>

                <div class="service-card">
                    <h3 class="service-title">6. COOKIES AND TRACKING</h3>
                    <p class="service-description">
                        We use cookies and similar tracking technologies to collect and use personal information about you.
                    </p>
                    <h4 style="color: var(--cyber-neon-cyan); margin: 1rem 0;">Types of cookies we use:</h4>
                    <ul style="color: rgba(255, 255, 255, 0.8); margin-left: 2rem;">
                        <li>Essential cookies for website functionality</li>
                        <li>Performance cookies for analytics</li>
                        <li>Functional cookies for enhanced features</li>
                        <li>Marketing cookies for personalized content</li>
                    </ul>
                </div>

                <div class="service-card">
                    <h3 class="service-title">7. CONTACT US</h3>
                    <p class="service-description">
                        If you have any questions about this privacy policy, please contact us:
                    </p>
                    <div style="background: rgba(255, 255, 255, 0.1); padding: 1.5rem; border-radius: 8px; margin-top: 1rem;">
                        <p style="color: rgba(255, 255, 255, 0.8);"><strong>Email:</strong> privacy@hivenest.co.za</p>
                        <p style="color: rgba(255, 255, 255, 0.8);"><strong>Phone:</strong> +27 123 456 789</p>
                        <p style="color: rgba(255, 255, 255, 0.8);"><strong>Address:</strong> HiveNest Digital Services</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php include '../utilities/footer.php'; ?>

<?php include '../utilities/scripts.php'; ?>
</body>
</html>