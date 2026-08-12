<?php
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/seo-meta.php';
include_once '../utilities/dynamic_pricing.php';

$current_page = 'tools';
$page_title = 'SSL Certificates - Secure Your Website | HiveNest Matrix';
$page_description = 'SSL certificates for encrypted websites, customer trust and secure transactions.';
$page_keywords = 'ssl certificate, premium ssl, wildcard ssl, ev ssl, website encryption';
$breadcrumbs = [
    ['text'=>'Digital Arsenal','url'=>'../main-services/tools.php'],
    ['text'=>'SSL Certificates','url'=>null],
];

$ssl_fallback = [
    ['name'=>'STANDARD SSL','price'=>'$0.60','period'=>'/mo','features'=>['Domain validation','Strong encryption','Browser compatibility','Website trust indicator'],'cta_link'=>'#','cta_text'=>'ADD TO CART','onclick'=>"addSSLToCart('standard-ssl', 0.60)",'featured'=>false],
    ['name'=>'PREMIUM SSL','price'=>'$3.04','period'=>'/mo','features'=>['Enhanced validation','Strong encryption','Premium trust protection','Business website coverage'],'cta_link'=>'#','cta_text'=>'ADD TO CART','onclick'=>"addSSLToCart('premium-ssl', 3.04)",'featured'=>true],
    ['name'=>'WILDCARD SSL','price'=>'$4.55','period'=>'/mo','features'=>['Protects unlimited subdomains','Strong encryption','One certificate to manage','Browser compatibility'],'cta_link'=>'#','cta_text'=>'ADD TO CART','onclick'=>"addSSLToCart('wildcard-ssl', 4.55)",'featured'=>false],
    ['name'=>'EV SSL','price'=>'$9.88','period'=>'/mo','features'=>['Extended validation','Highest identity assurance','Business verification','Premium customer trust'],'cta_link'=>'#','cta_text'=>'ADD TO CART','onclick'=>"addSSLToCart('ev-ssl', 9.88)",'featured'=>false],
];

$ssl_plans = loadProductPricingPlans([
    'product_id'=>33,
    'product_slug'=>'ssl-certificates',
    'cart_function'=>'addSSLToCart',
    'fallback_plans'=>$ssl_fallback,
]);

$page_scripts = <<<'JAVASCRIPT'
function addSSLToCart(planId, planName, price) {
    if (price === undefined) {
        price = planName;
        planName = String(planId).replace(/-/g, ' ').toUpperCase();
    }
    if (!window.addToCart) {
        console.warn('Cart system not ready. Please refresh the page and try again.');
        return false;
    }
    return window.addToCart({
        id: 'ssl-' + planId,
        name: 'SSL Certificate: ' + planName,
        price: Number(price),
        type: 'ssl'
    });
}
JAVASCRIPT;

$seo_config = [
    'title'=>$page_title,
    'description'=>$page_description,
    'keywords'=>$page_keywords,
    'image'=>'assets/images/heroes/hero-security-padlock.jpg',
    'url'=>'https://hivenest.co.za/tools/sslcert.php',
    'type'=>'service',
    'structured_data'=>generateStructuredData('service', [
        'name'=>'SSL Certificates',
        'description'=>$page_description,
        'serviceType'=>'SSL Certificate Services'
    ])
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include '../utilities/head.php'; echo renderSEOMeta($seo_config); ?>
</head>
<body>
<?php include '../utilities/nav.php'; ?>
<?php include '../utilities/mobile-menu.php'; ?>

<?php
$hero_title = '<span class="cyber-text">SSL</span><br>CERTIFICATES';
$hero_subtitle = 'Encrypt customer connections, protect sensitive information and build trust with the right SSL certificate.';
$hero_image = '../assets/images/heroes/hero-security-padlock.jpg';
$hero_alt = 'SSL Certificate Security';
include '../utilities/hero-minimal.php';

include '../utilities/pricing-cards.php';
$grid_title = 'SSL CERTIFICATE PLANS';
$grid_subtitle = 'Choose the right encryption and validation level';
$grid_content = renderPricingGrid($ssl_plans);
include '../utilities/grid-section.php';

include '../utilities/cyber-cards.php';
$ssl_features = [
    ['icon'=>'fas fa-lock','title'=>'ENCRYPTED CONNECTIONS','description'=>'Protect information exchanged between visitors and your website using strong transport encryption.'],
    ['icon'=>'fas fa-check-circle','title'=>'BROWSER TRUST','description'=>'Display a secure connection indicator in compatible browsers and reduce security warnings.'],
    ['icon'=>'fas fa-shield-alt','title'=>'IDENTITY VALIDATION','description'=>'Select domain, business or extended validation according to the assurance your website needs.'],
    ['icon'=>'fas fa-sitemap','title'=>'SUBDOMAIN COVERAGE','description'=>'Wildcard certificates can secure multiple subdomains under one primary domain.'],
    ['icon'=>'fas fa-sync','title'=>'RENEWAL MANAGEMENT','description'=>'Track certificate expiry and renew protection before service interruption.'],
    ['icon'=>'fas fa-headset','title'=>'INSTALLATION SUPPORT','description'=>'Get help with certificate validation, installation and compatibility issues.'],
];
$grid_title = 'SSL SECURITY BENEFITS';
$grid_subtitle = 'Essential protection for websites and online transactions';
$grid_content = renderCyberCardsGrid($ssl_features);
include '../utilities/grid-section.php';

$cta_title = 'READY TO SECURE YOUR WEBSITE?';
$cta_subtitle = 'Choose an SSL certificate and protect your visitors today.';
$cta_buttons = '<a href="#" onclick="window.scrollTo({top:0,behavior:\'smooth\'});return false;" class="btn btn-primary">VIEW SSL PLANS</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
