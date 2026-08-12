<?php
include_once '../utilities/breadcrumbs.php';
include_once '../utilities/seo-meta.php';
include_once '../utilities/dynamic_pricing.php';

$current_page = 'tools';
$page_title = 'Xcitium Backup - Cloud Data Protection | HiveNest Matrix';
$page_description = 'Xcitium cloud backup plans for secure data protection, retention and recovery.';
$page_keywords = 'xcitium backup, cloud backup, data recovery, managed backup, disaster recovery';
$breadcrumbs = [
    ['text'=>'Digital Arsenal','url'=>'../main-services/tools.php'],
    ['text'=>'Xcitium Backup','url'=>null],
];

$xcitium_fallback = [
    ['name'=>'BASIC','price'=>'$1.27','period'=>'/mo','features'=>['Cloud backup','Scheduled protection','Secure storage','Simple recovery'],'cta_link'=>'#','cta_text'=>'ADD TO CART','onclick'=>"addXcitiumToCart('basic', 1.27)",'featured'=>false],
    ['name'=>'DELUXE','price'=>'$2.91','period'=>'/mo','features'=>['Expanded backup capacity','Flexible schedules','Protected retention','Priority recovery tools'],'cta_link'=>'#','cta_text'=>'ADD TO CART','onclick'=>"addXcitiumToCart('deluxe', 2.91)",'featured'=>true],
    ['name'=>'PROFESSIONAL','price'=>'$5.01','period'=>'/mo','features'=>['Professional data protection','Advanced scheduling','Longer retention','Priority support'],'cta_link'=>'#','cta_text'=>'ADD TO CART','onclick'=>"addXcitiumToCart('professional', 5.01)",'featured'=>false],
    ['name'=>'PRO+','price'=>'$7.75','period'=>'/mo','features'=>['Advanced cloud backup','Business-grade recovery','Extended retention','Premium support'],'cta_link'=>'#','cta_text'=>'ADD TO CART','onclick'=>"addXcitiumToCart('pro-plus', 7.75)",'featured'=>false],
];

$xcitium_plans = loadProductPricingPlans([
    'product_id'=>34,
    'product_slug'=>'xcitium-backup',
    'cart_function'=>'addXcitiumToCart',
    'fallback_plans'=>$xcitium_fallback,
]);

$page_scripts = <<<'JAVASCRIPT'
function addXcitiumToCart(planId, planName, price) {
    if (price === undefined) {
        price = planName;
        planName = String(planId).replace(/-/g, ' ').toUpperCase();
    }
    if (!window.addToCart) {
        console.warn('Cart system not ready. Please refresh the page and try again.');
        return false;
    }
    return window.addToCart({
        id: 'xcitium-' + planId,
        name: 'Xcitium Backup: ' + planName,
        price: Number(price),
        type: 'backup'
    });
}
JAVASCRIPT;

$seo_config = [
    'title'=>$page_title,
    'description'=>$page_description,
    'keywords'=>$page_keywords,
    'image'=>'assets/images/heroes/hero-security-laptop.jpg',
    'url'=>'https://hivenest.co.za/tools/xcitium.php',
    'type'=>'service',
    'structured_data'=>generateStructuredData('service', [
        'name'=>'Xcitium Backup',
        'description'=>$page_description,
        'serviceType'=>'Cloud Backup Services'
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
$hero_title = '<span class="cyber-text">XCITIUM</span><br>CLOUD BACKUP';
$hero_subtitle = 'Protect business data with secure cloud backups, flexible retention and dependable recovery.';
$hero_image = '../assets/images/heroes/hero-security-laptop.jpg';
$hero_alt = 'Xcitium Cloud Backup';
include '../utilities/hero-minimal.php';

include '../utilities/pricing-cards.php';
$grid_title = 'XCITIUM BACKUP PLANS';
$grid_subtitle = 'Choose the protection level that fits your data';
$grid_content = renderPricingGrid($xcitium_plans);
include '../utilities/grid-section.php';

include '../utilities/cyber-cards.php';
$backup_features = [
    ['icon'=>'fas fa-cloud-upload-alt','title'=>'CLOUD BACKUP','description'=>'Send protected backup copies to secure cloud storage on a controlled schedule.'],
    ['icon'=>'fas fa-calendar-alt','title'=>'FLEXIBLE SCHEDULING','description'=>'Choose backup schedules that match your operational and recovery requirements.'],
    ['icon'=>'fas fa-history','title'=>'RETENTION CONTROL','description'=>'Maintain recovery points so accidental deletion and unwanted changes can be reversed.'],
    ['icon'=>'fas fa-undo','title'=>'DEPENDABLE RECOVERY','description'=>'Restore protected files and data when loss, corruption or disruption occurs.'],
    ['icon'=>'fas fa-lock','title'=>'SECURE STORAGE','description'=>'Protect backup data during transfer and while retained in cloud storage.'],
    ['icon'=>'fas fa-chart-line','title'=>'BACKUP MONITORING','description'=>'Track backup status and identify failed or missed protection jobs.'],
];
$grid_title = 'XCITIUM PROTECTION FEATURES';
$grid_subtitle = 'Practical backup and recovery for business data';
$grid_content = renderCyberCardsGrid($backup_features);
include '../utilities/grid-section.php';

$cta_title = 'READY TO PROTECT YOUR DATA?';
$cta_subtitle = 'Select an Xcitium plan and start building a reliable recovery strategy.';
$cta_buttons = '<a href="#" onclick="window.scrollTo({top:0,behavior:\'smooth\'});return false;" class="btn btn-primary">VIEW BACKUP PLANS</a>';
include '../utilities/cta-section.php';
?>

<?php include '../utilities/footer.php'; ?>
<?php include '../utilities/scripts.php'; ?>
</body>
</html>
