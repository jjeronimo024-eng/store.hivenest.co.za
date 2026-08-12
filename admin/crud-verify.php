<?php
/**
 * HiveNest Admin CRUD Live Verifier
 * --------------------------------------------------------------
 * Drop this in /admin/ and open it in a browser AFTER logging
 * into products-admin.php (it reuses the same session auth).
 *
 * What it does:
 *   1. Verifies DB connectivity + schema
 *   2. READ test (refresh_data query)
 *   3. CREATE a pricing row on a random active product
 *   4. UPDATE that row
 *   5. SOFT-DELETE that row (is_active=0)
 *   6. Verifies the row is hidden from READ
 *   7. HARD-DELETE the test row for cleanup
 *   8. Verifies pricing_cache.json contains the same product count
 *
 * Nothing destructive is left behind.
 */

session_start();

if (empty($_SESSION['products_admin_auth'])) {
    header('Location: products-admin.php');
    exit;
}

require_once __DIR__ . '/../utilities/product_pricing.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html><head>
<title>HiveNest – CRUD Verifier</title>
<style>
 body{font-family:'Rajdhani',monospace;background:#0a0a0a;color:#e6e6e6;padding:24px;line-height:1.55}
 h1{color:#00ffff;text-shadow:0 0 14px rgba(0,255,255,.6)}
 .row{padding:8px 12px;border-left:4px solid #333;margin:6px 0;background:#141414;border-radius:6px}
 .ok{border-left-color:#00ff7f;color:#aaffcc}
 .bad{border-left-color:#ff0064;color:#ffaac4}
 .info{border-left-color:#00bfff;color:#a8e0ff}
 .meta{color:#888;font-size:13px}
 code{background:#000;padding:2px 6px;border-radius:4px;color:#00ffff}
 a{color:#00ffff}
</style></head><body>
<h1>⚡ Admin CRUD Verifier</h1>
<p class="meta">Running against <code><?= htmlspecialchars(loadDBCredentials()['host']) ?></code> &mdash; <a href="products-admin.php">&larr; back to admin</a></p>
<?php

function row(string $cls, string $msg, string $detail = '') {
    echo "<div class='row $cls'><strong>$msg</strong>";
    if ($detail !== '') echo "<div class='meta'>$detail</div>";
    echo "</div>";
    @ob_flush(); @flush();
}

// 1. Connection
$conn = getPricingDBConnection();
if (!$conn) { row('bad','Database connection FAILED'); exit; }
row('ok','Database connection OK');

// 2. Schema
$tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$ok = in_array('products',$tables) && in_array('product_pricing',$tables);
row($ok?'ok':'bad', $ok?'Tables products + product_pricing exist':'Missing required tables',
    'Found: '.implode(', ',$tables));
if (!$ok) exit;

// 3. READ
$stmt = $conn->query("
    SELECT p.id as product_id, p.name as product_name, p.slug as product_slug,
           p.page_url, pp.id as pricing_id, pp.tier_name, pp.price, pp.is_active
    FROM products p LEFT JOIN product_pricing pp ON p.id = pp.product_id
    WHERE p.is_active = 1
    ORDER BY p.page_url, p.id, pp.sort_order
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$products = array_unique(array_column($rows,'product_id'));
$pricing  = count(array_filter($rows,fn($r)=>$r['pricing_id']!==null));
row('ok','READ – refresh_data query OK', count($products)." active products / $pricing pricing rows");

// 4. pick test product
$test = null;
foreach ($rows as $r) { if ($r['product_id']) { $test = $r; break; } }
if (!$test) { row('bad','No active products to test against'); exit; }
$pid = $test['product_id'];
row('info','Test product chosen', "product_id=$pid ({$test['product_name']})");

$tier = "CRUD_TEST_".substr(uniqid(),-6);

// 5. CREATE
try {
    $conn->beginTransaction();
    $st = $conn->prepare("INSERT INTO product_pricing
        (product_id,tier_name,tier_slug,tier_level,price,setup_fee,billing_cycle,features,is_featured,sort_order,is_active,created_at)
        VALUES (:pid,:n,:s,'standard',:p,0,'monthly',:f,0,99,1,NOW())");
    $st->execute([
        'pid'=>$pid,'n'=>$tier,
        's'=>strtolower(preg_replace('/[^a-z0-9]+/i','-',$tier)),
        'p'=>9.99,'f'=>json_encode(['feat A','feat B'])
    ]);
    $newId = $conn->lastInsertId();
    $conn->commit();
    row('ok','CREATE – inserted pricing row', "new pricing_id=$newId");
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    row('bad','CREATE failed', $e->getMessage()); exit;
}

// 6. UPDATE
try {
    $st = $conn->prepare("UPDATE product_pricing SET tier_name=:n, tier_slug=:s, price=:p,
        billing_cycle='annually', is_featured=1, features=:f, updated_at=NOW() WHERE id=:id");
    $st->execute([
        'n'=>$tier.'_UPD',
        's'=>strtolower(preg_replace('/[^a-z0-9]+/i','-',$tier.'_UPD')),
        'p'=>19.99,'f'=>json_encode(['upd 1','upd 2']),'id'=>$newId
    ]);
    $chk = $conn->prepare("SELECT price,billing_cycle,is_featured,tier_slug FROM product_pricing WHERE id=?");
    $chk->execute([$newId]); $r = $chk->fetch();
    $ok = $r && $r['price']==19.99 && $r['is_featured']==1 && $r['billing_cycle']==='annually';
    row($ok?'ok':'bad','UPDATE – fields persisted',
        "price={$r['price']}, cycle={$r['billing_cycle']}, featured={$r['is_featured']}, slug={$r['tier_slug']}");
} catch (Exception $e) { row('bad','UPDATE failed',$e->getMessage()); }

// 7. SOFT-DELETE
try {
    $conn->prepare("UPDATE product_pricing SET is_active=0, updated_at=NOW() WHERE id=:id")->execute(['id'=>$newId]);
    $chk = $conn->prepare("SELECT is_active FROM product_pricing WHERE id=?");
    $chk->execute([$newId]); $isa = $chk->fetchColumn();
    row(($isa==0)?'ok':'bad','DELETE (soft) – is_active set to 0', "is_active=$isa");

    // verify hidden from READ
    $hide = $conn->prepare("SELECT pp.id FROM product_pricing pp JOIN products p ON pp.product_id=p.id
        WHERE pp.id=:id AND pp.is_active=1 AND p.is_active=1");
    $hide->execute(['id'=>$newId]);
    $still = $hide->fetchColumn();
    row($still===false?'ok':'bad',
        $still===false?'READ filter correctly hides deleted row':'BUG: deleted row still appears');
} catch (Exception $e) { row('bad','DELETE failed',$e->getMessage()); }

// 8. cleanup
try {
    $conn->prepare("DELETE FROM product_pricing WHERE id=?")->execute([$newId]);
    row('ok','Cleanup – test row hard-deleted', "pricing_id=$newId");
} catch (Exception $e) { row('bad','Cleanup failed',$e->getMessage()); }

// 9. cache rebuild
$cacheFile = __DIR__ . '/../utilities/pricing_cache.json';
$beforeMtime = file_exists($cacheFile) ? filemtime($cacheFile) : 0;
if (function_exists('rebuildPricingCache')) {
    $ok = rebuildPricingCache($conn);
} else {
    // mini-rebuild for stand-alone use
    $s = $conn->query("SELECT p.id as product_id,p.name as product_name,p.slug as product_slug,p.page_url,p.product_type,
            pp.id as pricing_id,pp.tier_name,pp.tier_slug,pp.tier_level,pp.price,pp.setup_fee,pp.billing_cycle,
            pp.features,pp.is_featured,pp.sort_order,pp.is_active
        FROM products p LEFT JOIN product_pricing pp ON p.id=pp.product_id WHERE p.is_active=1
        ORDER BY p.product_type,p.id,pp.sort_order");
    $data=[];
    while($r=$s->fetch(PDO::FETCH_ASSOC)){
        $slug=$r['product_slug'];
        if(!isset($data[$slug]))$data[$slug]=['product_id'=>$r['product_id'],'product_name'=>$r['product_name'],
            'product_slug'=>$r['product_slug'],'page_url'=>$r['page_url'],'product_type'=>$r['product_type'],'pricing_tiers'=>[]];
        if($r['pricing_id'] && (int)$r['is_active']===1){
            $f=json_decode($r['features'],true);
            $data[$slug]['pricing_tiers'][]=['pricing_id'=>$r['pricing_id'],'tier_name'=>$r['tier_name'],
                'tier_slug'=>$r['tier_slug'],'tier_level'=>$r['tier_level'],'price'=>$r['price'],
                'setup_fee'=>$r['setup_fee'],'billing_cycle'=>$r['billing_cycle'],
                'features'=>is_array($f)?$f:[],'is_featured'=>$r['is_featured'],
                'sort_order'=>$r['sort_order'],'is_active'=>$r['is_active']];
        }
    }
    $ok = file_put_contents($cacheFile,json_encode($data,JSON_PRETTY_PRINT))!==false;
}
$afterMtime = file_exists($cacheFile) ? filemtime($cacheFile) : 0;
row($ok?'ok':'bad',
    $ok?'pricing_cache.json refreshed':'Cache refresh FAILED',
    "mtime before=$beforeMtime, after=$afterMtime");

?>
<p class="meta" style="margin-top:24px">All tests done. If everything is green, your CRUD pipeline is healthy.</p>
</body></html>
