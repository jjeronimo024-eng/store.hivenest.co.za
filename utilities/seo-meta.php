<?php
// SEO Meta - Advanced SEO meta tags including Open Graph and Twitter Cards
// Usage: Set variables before include: $seo_title, $seo_description, $seo_image, $seo_url, etc.

function renderSEOMeta($config = []) {
    // Default configuration
    $defaults = [
        'title' => 'HiveNest - Digital Revolution Starts Here',
        'description' => 'HiveNest - The Future of Digital Services. Cyberpunk-inspired hosting, domains, and digital solutions that break all the rules.',
        'keywords' => 'futuristic hosting, cyberpunk web services, next-gen digital solutions, domains, web hosting',
        'image' => 'assets/images/heroes/hero-cyberpunk-main.jpg',
        'url' => 'https://hivenest.co.za',
        'type' => 'website',
        'site_name' => 'HiveNest Matrix',
        'twitter_handle' => '@hivenestmatrix',
        'locale' => 'en_US',
        'author' => 'HiveNest Matrix',
        'robots' => 'index, follow'
    ];
    
    $seo = array_merge($defaults, $config);
    
    ob_start();
    ?>
    <!-- Basic SEO Meta Tags -->
    <title><?php echo htmlspecialchars($seo['title']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($seo['description']); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($seo['keywords']); ?>">
    <meta name="author" content="<?php echo htmlspecialchars($seo['author']); ?>">
    <meta name="robots" content="<?php echo htmlspecialchars($seo['robots']); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($seo['url']); ?>">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo htmlspecialchars($seo['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($seo['description']); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($seo['image']); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($seo['url']); ?>">
    <meta property="og:type" content="<?php echo htmlspecialchars($seo['type']); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($seo['site_name']); ?>">
    <meta property="og:locale" content="<?php echo htmlspecialchars($seo['locale']); ?>">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="<?php echo htmlspecialchars($seo['twitter_handle']); ?>">
    <meta name="twitter:creator" content="<?php echo htmlspecialchars($seo['twitter_handle']); ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($seo['title']); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($seo['description']); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($seo['image']); ?>">
    
    <!-- Additional Meta Tags -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <meta name="theme-color" content="#00ffff">
    <meta name="msapplication-navbutton-color" content="#00ffff">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <?php if(isset($seo['structured_data'])): ?>
    <!-- Structured Data / Schema.org -->
    <script type="application/ld+json">
    <?php echo json_encode($seo['structured_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>
    </script>
    <?php endif; ?>
    
    <?php
    // Detect if we're in a subdirectory to adjust paths
    $script_path = $_SERVER['SCRIPT_NAME'];
    $is_subdirectory = (substr_count($script_path, '/') > 1);
    $css_path = $is_subdirectory ? '../assets/css/' : 'assets/css/';
    ?>
    
    <!-- Preload critical resources -->
    <link rel="preload" href="<?php echo $css_path; ?>main.css" as="style">
    <link rel="preload" href="<?php echo $css_path; ?>fonts.css" as="style">
    
    <!-- Load stylesheets -->
    <link rel="stylesheet" href="<?php echo $css_path; ?>main.css">
    <link rel="stylesheet" href="<?php echo $css_path; ?>navigation.css">
    <?php
    return ob_get_clean();
}

// Generate structured data for different page types
function generateStructuredData($type, $data = []) {
    $base_org = [
        "@context" => "https://schema.org",
        "@type" => "Organization",
        "name" => "HiveNest Matrix",
        "url" => "https://hivenest.co.za",
        "logo" => "https://hivenest.co.za/assets/images/logo.png",
        "description" => "Leading provider of cyberpunk-inspired hosting, domains, and digital solutions",
        "contactPoint" => [
            "@type" => "ContactPoint",
            "telephone" => "+27-123-456-789",
            "contactType" => "customer service",
            "email" => "support@hivenest.co.za"
        ],
        "sameAs" => [
            "https://twitter.com/hivenestmatrix",
            "https://facebook.com/hivenestmatrix"
        ]
    ];
    
    switch($type) {
        case 'website':
            return [
                "@context" => "https://schema.org",
                "@type" => "WebSite",
                "name" => $data['name'] ?? "HiveNest Matrix",
                "url" => $data['url'] ?? "https://hivenest.co.za",
                "description" => $data['description'] ?? "Digital revolution starts here",
                "publisher" => $base_org
            ];
            
        case 'service':
            return [
                "@context" => "https://schema.org",
                "@type" => "Service",
                "name" => $data['name'] ?? "Web Hosting Services",
                "provider" => $base_org,
                "description" => $data['description'] ?? "Professional web hosting and digital services",
                "serviceType" => $data['serviceType'] ?? "Web Hosting"
            ];
            
        case 'article':
            return [
                "@context" => "https://schema.org",
                "@type" => "Article",
                "headline" => $data['headline'] ?? "HiveNest Matrix News",
                "author" => $base_org,
                "publisher" => $base_org,
                "datePublished" => $data['datePublished'] ?? date('c'),
                "description" => $data['description'] ?? "Latest news from HiveNest Matrix"
            ];
            
        default:
            return $base_org;
    }
}
?>