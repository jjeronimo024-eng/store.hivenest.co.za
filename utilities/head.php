<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo isset($page_description) ? $page_description : 'HiveNest - The Future of Digital Services. Cyberpunk-inspired hosting, domains, and digital solutions that break all the rules.'; ?>">
    <meta name="keywords" content="<?php echo isset($page_keywords) ? $page_keywords : 'futuristic hosting, cyberpunk web services, next-gen digital solutions'; ?>">
    
    <title><?php echo isset($page_title) ? $page_title : 'HiveNest - Digital Revolution Starts Here'; ?></title>
    
    <?php 
    // Check if current script is in a subdirectory - DEFINE THIS FIRST
    $script_path = $_SERVER['SCRIPT_NAME'];
    $path_segments = explode('/', trim($script_path, '/'));
    $is_subdirectory = count($path_segments) > 1; // More than just filename means we're in a subdirectory
    $css_path = $is_subdirectory ? '../assets/css/' : 'assets/css/';
    $fontawesome_path = $is_subdirectory ? '../assets/fonts/fontawesome-free-7.1.0-web/css/all.min.css' : 'assets/fonts/fontawesome-free-7.1.0-web/css/all.min.css';
    $favicon_path = $is_subdirectory ? '../favicon.ico' : 'favicon.ico';
    ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo $favicon_path; ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo $favicon_path; ?>">
    
    <!-- Preload critical CSS to prevent FOUC -->
    <link rel="preload" href="<?php echo $css_path; ?>main.css" as="style">
    <link rel="preload" href="<?php echo $css_path; ?>navigation.css" as="style">
    
    <!-- Load stylesheets -->
    <link rel="stylesheet" href="<?php echo $css_path; ?>main.css?v=20260702-2">
    <link rel="stylesheet" href="<?php echo $css_path; ?>navigation.css?v=20260702-3">
    
    <!-- Font Awesome (Local) -->
    <link rel="stylesheet" href="<?php echo $fontawesome_path; ?>">
