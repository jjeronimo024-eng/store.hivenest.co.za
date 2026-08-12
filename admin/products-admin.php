<?php
/**
 * HiveNest Product Pricing Management - Cyberpunk Edition
 * Hardened admin panel with full product + pricing CRUD.
 */

require_once __DIR__ . '/../utilities/admin_auth.php';
requireAdminAuth();
$admin = currentAdmin();

// Include database utilities
require_once '../utilities/product_pricing.php';

/**
 * Rebuild /utilities/pricing_cache.json from the live DB.
 * Front-end pages read this cache via pricing_simple.php, so it MUST be
 * refreshed after every CREATE / UPDATE / DELETE.
 */
function rebuildPricingCache(PDO $conn): bool {
    try {
        $stmt = $conn->query("
            SELECT
                p.id as product_id, p.name as product_name, p.slug as product_slug,
                p.page_url, p.product_type,
                pp.id as pricing_id, pp.tier_name, pp.tier_slug, pp.tier_level,
                pp.price, pp.setup_fee, pp.billing_cycle, pp.features,
                pp.is_featured, pp.sort_order, pp.is_active
            FROM products p
            LEFT JOIN product_pricing pp ON p.id = pp.product_id
            WHERE p.is_active = 1
            ORDER BY p.product_type, p.id, pp.sort_order
        ");

        $products_data = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $slug = $row['product_slug'];
            if (!isset($products_data[$slug])) {
                $products_data[$slug] = [
                    'product_id'   => $row['product_id'],
                    'product_name' => $row['product_name'],
                    'product_slug' => $row['product_slug'],
                    'page_url'     => $row['page_url'],
                    'product_type' => $row['product_type'],
                    'pricing_tiers' => []
                ];
            }
            if ($row['pricing_id'] && (int)$row['is_active'] === 1) {
                $features = json_decode($row['features'], true);
                $products_data[$slug]['pricing_tiers'][] = [
                    'pricing_id'   => $row['pricing_id'],
                    'tier_name'    => $row['tier_name'],
                    'tier_slug'    => $row['tier_slug'],
                    'tier_level'   => $row['tier_level'],
                    'price'        => $row['price'],
                    'setup_fee'    => $row['setup_fee'],
                    'billing_cycle'=> $row['billing_cycle'],
                    'features'     => is_array($features) ? $features : [],
                    'is_featured'  => $row['is_featured'],
                    'sort_order'   => $row['sort_order'],
                    'is_active'    => $row['is_active']
                ];
            }
        }

        $cache_file = __DIR__ . '/../utilities/pricing_cache.json';
        return file_put_contents($cache_file, json_encode($products_data, JSON_PRETTY_PRINT)) !== false;
    } catch (Exception $e) {
        error_log("rebuildPricingCache failed: " . $e->getMessage());
        return false;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    // CSRF: every state-changing action requires a valid token. `refresh_data`
    // is read-only but we still verify to make the boundary uniform.
    verifyCsrfOrDie($_POST['csrf_token'] ?? '');

    $conn = getPricingDBConnection();
    $response = ['success' => false, 'message' => 'Unknown action'];
    
    switch ($_POST['ajax_action']) {
        case 'refresh_data':
            // Fetch all products grouped by page (include inactive products too
            // so admins can re-enable them)
            if ($conn) {
                try {
                    $stmt = $conn->query("
                        SELECT 
                            p.id as product_id,
                            p.name as product_name,
                            p.slug as product_slug,
                            p.page_url,
                            p.product_type,
                            p.is_active as product_active,
                            pp.id as pricing_id,
                            pp.tier_name,
                            pp.tier_slug,
                            pp.tier_level,
                            pp.price,
                            pp.setup_fee,
                            pp.billing_cycle,
                            pp.features,
                            pp.is_featured,
                            pp.sort_order,
                            pp.is_active
                        FROM products p
                        LEFT JOIN product_pricing pp ON p.id = pp.product_id
                        ORDER BY p.is_active DESC, p.page_url, p.id, pp.sort_order
                    ");
                    
                    $products_by_page = [];
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $page = $row['page_url'] ?: 'Uncategorized';
                        
                        if (!isset($products_by_page[$page])) {
                            $products_by_page[$page] = [];
                        }
                        
                        $slug = $row['product_slug'];
                        if (!isset($products_by_page[$page][$slug])) {
                            $products_by_page[$page][$slug] = [
                                'product_id' => $row['product_id'],
                                'product_name' => $row['product_name'],
                                'product_slug' => $row['product_slug'],
                                'product_type' => $row['product_type'],
                                'product_active' => (int)$row['product_active'],
                                'packages' => []
                            ];
                        }
                        
                        if ($row['pricing_id']) {
                            $features = json_decode($row['features'], true);
                            $products_by_page[$page][$slug]['packages'][] = [
                                'pricing_id' => $row['pricing_id'],
                                'tier_name' => $row['tier_name'],
                                'tier_slug' => $row['tier_slug'],
                                'price' => $row['price'],
                                'setup_fee' => $row['setup_fee'],
                                'billing_cycle' => $row['billing_cycle'],
                                'features' => is_array($features) ? $features : [],
                                'is_featured' => $row['is_featured']
                            ];
                        }
                    }
                    
                    $response = [
                        'success' => true,
                        'data' => $products_by_page,
                        'message' => 'Data refreshed successfully'
                    ];
                } catch (Exception $e) {
                    $response['message'] = 'Error: ' . $e->getMessage();
                }
            } else {
                $response['message'] = 'Database connection failed';
            }
            break;
            
        case 'save_package':
            if ($conn) {
                try {
                    $data = json_decode($_POST['package_data'], true);
                    
                    $conn->beginTransaction();
                    
                    if (isset($data['pricing_id']) && $data['pricing_id']) {
                        // Update existing — also refresh tier_slug so it stays in
                        // sync with tier_name (cart code uses tier_slug as the key).
                        $stmt = $conn->prepare("
                            UPDATE product_pricing 
                            SET tier_name = :tier_name,
                                tier_slug = :tier_slug,
                                price = :price,
                                setup_fee = :setup_fee,
                                billing_cycle = :billing_cycle,
                                features = :features,
                                is_featured = :is_featured,
                                updated_at = NOW()
                            WHERE id = :pricing_id
                        ");
                        $stmt->execute([
                            'tier_name' => $data['tier_name'],
                            'tier_slug' => strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($data['tier_name']))),
                            'price' => $data['price'],
                            'setup_fee' => $data['setup_fee'] ?? 0,
                            'billing_cycle' => $data['billing_cycle'],
                            'features' => json_encode($data['features']),
                            'is_featured' => $data['is_featured'] ? 1 : 0,
                            'pricing_id' => $data['pricing_id']
                        ]);
                    } else {
                        // Insert new
                        $stmt = $conn->prepare("
                            INSERT INTO product_pricing 
                            (product_id, tier_name, tier_slug, tier_level, price, setup_fee, billing_cycle, features, is_featured, sort_order, is_active, created_at)
                            VALUES (:product_id, :tier_name, :tier_slug, :tier_level, :price, :setup_fee, :billing_cycle, :features, :is_featured, :sort_order, 1, NOW())
                        ");
                        $stmt->execute([
                            'product_id' => $data['product_id'],
                            'tier_name' => $data['tier_name'],
                            'tier_slug' => strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($data['tier_name']))),
                            'tier_level' => $data['tier_level'] ?? 'standard',
                            'price' => $data['price'],
                            'setup_fee' => $data['setup_fee'] ?? 0,
                            'billing_cycle' => $data['billing_cycle'],
                            'features' => json_encode($data['features']),
                            'is_featured' => $data['is_featured'] ? 1 : 0,
                            'sort_order' => $data['sort_order'] ?? 99
                        ]);
                    }
                    
                    $conn->commit();

                    // Keep the front-end cache in sync
                    $cache_ok = rebuildPricingCache($conn);
                    $response = [
                        'success' => true,
                        'message' => 'Package saved successfully' . ($cache_ok ? ' (cache refreshed)' : ' (cache refresh FAILED)'),
                        'cache_refreshed' => $cache_ok
                    ];
                } catch (Exception $e) {
                    if ($conn->inTransaction()) $conn->rollBack();
                    $response['message'] = 'Error: ' . $e->getMessage();
                }
            }
            break;
            
        case 'delete_package':
            if ($conn && isset($_POST['pricing_id'])) {
                try {
                    $stmt = $conn->prepare("UPDATE product_pricing SET is_active = 0, updated_at = NOW() WHERE id = :id");
                    $stmt->execute(['id' => $_POST['pricing_id']]);

                    // Keep the front-end cache in sync
                    $cache_ok = rebuildPricingCache($conn);
                    $response = [
                        'success' => true,
                        'message' => 'Package deleted' . ($cache_ok ? ' (cache refreshed)' : ' (cache refresh FAILED)'),
                        'cache_refreshed' => $cache_ok
                    ];
                } catch (Exception $e) {
                    $response['message'] = 'Error: ' . $e->getMessage();
                }
            }
            break;

        // ===== Products-table CRUD =====
        case 'create_product':
            if ($conn) {
                try {
                    $name = trim((string)($_POST['name'] ?? ''));
                    if ($name === '') { throw new Exception('Product name is required'); }

                    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
                    if ($slug === '') { throw new Exception('Invalid slug generated'); }

                    $product_type = $_POST['product_type'] ?? 'hosting';
                    $allowed_types = ['domain','hosting','email','security','design','server','ssl','backup'];
                    if (!in_array($product_type, $allowed_types, true)) {
                        throw new Exception('Invalid product type');
                    }

                    // Map type → default category (best-effort)
                    $type_to_category = [
                        'domain' => 1, 'hosting' => 2, 'email' => 3, 'security' => 4,
                        'server' => 5, 'design' => 6, 'ssl' => 4, 'backup' => 4
                    ];
                    $category_id = (int)($_POST['category_id'] ?? $type_to_category[$product_type] ?? 2);

                    $page_url    = trim((string)($_POST['page_url'] ?? '')) ?: null;
                    $base_price  = (float)($_POST['base_price'] ?? 0);
                    $description = trim((string)($_POST['description'] ?? ''));

                    // Ensure slug uniqueness — append -2, -3 ... until free
                    $base_slug = $slug; $i = 2;
                    while (true) {
                        $check = $conn->prepare("SELECT 1 FROM products WHERE slug = :s LIMIT 1");
                        $check->execute(['s' => $slug]);
                        if (!$check->fetchColumn()) break;
                        $slug = $base_slug . '-' . $i++;
                    }

                    $stmt = $conn->prepare("
                        INSERT INTO products
                          (uuid, category_id, name, slug, page_url, description, short_description,
                           product_type, service_type, billing_cycle, base_price, setup_fee,
                           features, is_active, is_featured, sort_order, min_quantity, max_quantity)
                        VALUES
                          (UUID(), :category_id, :name, :slug, :page_url, :description, :short_description,
                           :product_type, 'recurring', 'monthly', :base_price, 0.00,
                           '[]', 1, 0, 99, 1, 1)
                    ");
                    $stmt->execute([
                        'category_id'       => $category_id,
                        'name'              => $name,
                        'slug'              => $slug,
                        'page_url'          => $page_url,
                        'description'       => $description,
                        'short_description' => mb_substr($description, 0, 160),
                        'product_type'      => $product_type,
                        'base_price'        => $base_price,
                    ]);
                    $new_id = (int)$conn->lastInsertId();

                    $cache_ok = rebuildPricingCache($conn);
                    $response = [
                        'success' => true,
                        'product_id' => $new_id,
                        'message' => "Product '{$name}' created (id {$new_id})" . ($cache_ok ? ' (cache refreshed)' : ''),
                        'cache_refreshed' => $cache_ok,
                    ];
                } catch (Throwable $e) {
                    $response['message'] = 'Error: ' . $e->getMessage();
                }
            }
            break;

        case 'rename_product':
            if ($conn) {
                try {
                    $product_id = (int)($_POST['product_id'] ?? 0);
                    $name       = trim((string)($_POST['name'] ?? ''));
                    if ($product_id <= 0 || $name === '') {
                        throw new Exception('product_id and name are required');
                    }
                    // Optional: page_url & description may also be updated together
                    $set = ['name = :name', 'updated_at = NOW()'];
                    $bind = ['name' => $name, 'id' => $product_id];

                    if (array_key_exists('page_url', $_POST)) {
                        $set[] = 'page_url = :page_url';
                        $bind['page_url'] = trim((string)$_POST['page_url']) ?: null;
                    }
                    if (array_key_exists('description', $_POST)) {
                        $set[] = 'description = :description';
                        $bind['description'] = trim((string)$_POST['description']);
                    }

                    $sql = "UPDATE products SET " . implode(', ', $set) . " WHERE id = :id";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($bind);

                    $cache_ok = rebuildPricingCache($conn);
                    $response = [
                        'success' => true,
                        'message' => 'Product renamed' . ($cache_ok ? ' (cache refreshed)' : ''),
                        'cache_refreshed' => $cache_ok,
                    ];
                } catch (Throwable $e) {
                    $response['message'] = 'Error: ' . $e->getMessage();
                }
            }
            break;

        case 'disable_product':
        case 'enable_product':
            if ($conn) {
                try {
                    $product_id = (int)($_POST['product_id'] ?? 0);
                    if ($product_id <= 0) {
                        throw new Exception('product_id is required');
                    }
                    $new_active = ($_POST['ajax_action'] === 'enable_product') ? 1 : 0;
                    $stmt = $conn->prepare("UPDATE products SET is_active = :a, updated_at = NOW() WHERE id = :id");
                    $stmt->execute(['a' => $new_active, 'id' => $product_id]);

                    $cache_ok = rebuildPricingCache($conn);
                    $response = [
                        'success' => true,
                        'message' => $new_active ? 'Product enabled' : 'Product disabled',
                        'cache_refreshed' => $cache_ok,
                    ];
                } catch (Throwable $e) {
                    $response['message'] = 'Error: ' . $e->getMessage();
                }
            }
            break;
    }
    
    echo json_encode($response);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HiveNest - Product Pricing Matrix</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --cyber-black: #0a0a0a;
            --cyber-dark: #1a1a1a;
            --cyber-neon-cyan: #00ffff;
            --cyber-neon-pink: #ff0064;
            --cyber-neon-green: #00ff00;
            --cyber-neon-orange: #ff9800;
            --cyber-purple: #764ba2;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Rajdhani', sans-serif;
            background: linear-gradient(135deg, var(--cyber-black) 0%, var(--cyber-dark) 100%);
            color: #ffffff;
            min-height: 100vh;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
            border: 2px solid var(--cyber-neon-cyan);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .header h1 {
            font-size: 36px;
            color: var(--cyber-neon-cyan);
            text-shadow: 0 0 20px rgba(0, 255, 255, 0.8);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 16px;
            margin-top: 5px;
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border: 2px solid;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-transform: uppercase;
            font-family: 'Rajdhani', sans-serif;
        }
        
        .btn-refresh {
            background: rgba(0, 255, 0, 0.1);
            border-color: var(--cyber-neon-green);
            color: var(--cyber-neon-green);
        }
        
        .btn-refresh:hover {
            background: var(--cyber-neon-green);
            color: var(--cyber-black);
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.5);
        }
        
        .btn-save {
            background: rgba(0, 255, 255, 0.1);
            border-color: var(--cyber-neon-cyan);
            color: var(--cyber-neon-cyan);
        }
        
        .btn-save:hover {
            background: var(--cyber-neon-cyan);
            color: var(--cyber-black);
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.5);
        }
        
        .btn-logout {
            background: rgba(255, 0, 100, 0.1);
            border-color: var(--cyber-neon-pink);
            color: var(--cyber-neon-pink);
        }
        
        .btn-logout:hover {
            background: var(--cyber-neon-pink);
            color: white;
            box-shadow: 0 0 20px rgba(255, 0, 100, 0.5);
        }
        
        .page-section {
            background: rgba(26, 26, 26, 0.8);
            border: 2px solid rgba(0, 255, 255, 0.3);
            border-radius: 12px;
            margin-bottom: 30px;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .page-section:hover {
            border-color: var(--cyber-neon-cyan);
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.2);
        }
        
        .page-header {
            background: linear-gradient(135deg, rgba(0, 255, 255, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid rgba(0, 255, 255, 0.3);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .page-header:hover {
            background: linear-gradient(135deg, rgba(0, 255, 255, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
        }
        
        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--cyber-neon-cyan);
            text-shadow: 0 0 15px rgba(0, 255, 255, 0.5);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-count {
            background: rgba(255, 0, 100, 0.2);
            border: 1px solid var(--cyber-neon-pink);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            color: var(--cyber-neon-pink);
        }
        
        .btn-add {
            background: rgba(0, 255, 0, 0.1);
            border: 2px solid var(--cyber-neon-green);
            color: var(--cyber-neon-green);
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-add:hover {
            background: var(--cyber-neon-green);
            color: var(--cyber-black);
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.5);
        }
        
        .packages-container {
            padding: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .package-card {
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(0, 255, 255, 0.3);
            border-radius: 10px;
            padding: 20px;
            transition: all 0.3s;
            position: relative;
        }
        
        .package-card:hover {
            border-color: var(--cyber-neon-cyan);
            box-shadow: 0 0 25px rgba(0, 255, 255, 0.3);
            transform: translateY(-5px);
        }
        
        .package-card.featured {
            border-color: var(--cyber-neon-pink);
            background: rgba(255, 0, 100, 0.05);
        }
        
        .package-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .package-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--cyber-neon-cyan);
            text-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
        }
        
        .package-featured {
            background: var(--cyber-neon-pink);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .package-price {
            font-size: 32px;
            font-weight: 700;
            color: var(--cyber-neon-green);
            text-shadow: 0 0 15px rgba(0, 255, 0, 0.5);
            margin-bottom: 10px;
        }
        
        .package-cycle {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .package-features {
            list-style: none;
            margin-bottom: 20px;
        }
        
        .package-features li {
            padding: 6px 0;
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .package-features li:before {
            content: "▸";
            color: var(--cyber-neon-green);
            font-weight: bold;
        }
        
        .package-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-edit, .btn-delete {
            flex: 1;
            padding: 10px;
            border: 2px solid;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            text-align: center;
        }
        
        .btn-edit {
            background: rgba(0, 255, 255, 0.1);
            border-color: var(--cyber-neon-cyan);
            color: var(--cyber-neon-cyan);
        }
        
        .btn-edit:hover {
            background: var(--cyber-neon-cyan);
            color: var(--cyber-black);
        }
        
        .btn-delete {
            background: rgba(255, 0, 100, 0.1);
            border-color: var(--cyber-neon-pink);
            color: var(--cyber-neon-pink);
        }
        
        .btn-delete:hover {
            background: var(--cyber-neon-pink);
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--cyber-dark);
            border: 2px solid var(--cyber-neon-cyan);
            border-radius: 15px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 0 50px rgba(0, 255, 255, 0.5);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .modal-title {
            font-size: 28px;
            color: var(--cyber-neon-cyan);
            text-shadow: 0 0 15px rgba(0, 255, 255, 0.5);
        }
        
        .btn-close {
            background: none;
            border: none;
            color: var(--cyber-neon-pink);
            font-size: 28px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-close:hover {
            color: white;
            text-shadow: 0 0 15px rgba(255, 0, 100, 0.8);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            color: var(--cyber-neon-cyan);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px;
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(0, 255, 255, 0.3);
            border-radius: 6px;
            color: white;
            font-size: 15px;
            font-family: 'Rajdhani', sans-serif;
            transition: all 0.3s;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--cyber-neon-cyan);
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.3);
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-checkbox input {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: var(--cyber-neon-cyan);
            color: var(--cyber-black);
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 25px rgba(0, 255, 255, 0.5);
        }
        
        .loading {
            text-align: center;
            padding: 60px;
            font-size: 24px;
            color: var(--cyber-neon-cyan);
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 20px 30px;
            border-radius: 10px;
            font-weight: 600;
            z-index: 2000;
            animation: slideIn 0.3s ease-out;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
        }
        
        .notification.success {
            background: rgba(0, 255, 0, 0.2);
            border: 2px solid var(--cyber-neon-green);
            color: var(--cyber-neon-green);
        }
        
        .notification.error {
            background: rgba(255, 0, 100, 0.2);
            border: 2px solid var(--cyber-neon-pink);
            color: var(--cyber-neon-pink);
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .collapsed .packages-container {
            display: none;
        }
        
        .toggle-icon {
            transition: transform 0.3s;
        }
        
        .collapsed .toggle-icon {
            transform: rotate(-90deg);
        }
        
        @media (max-width: 768px) {
            .packages-container {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .modal-content {
                padding: 25px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div>
            <h1>
                <i class="fas fa-microchip"></i>
                PRODUCT PRICING MATRIX
            </h1>
            <div class="header-subtitle">
                Centralized pricing control for all neural services
                · Signed in as <strong style="color:#00ffff;"><?php echo htmlspecialchars($admin['username'] ?? 'admin', ENT_QUOTES); ?></strong>
                (<?php echo htmlspecialchars($admin['role'] ?? '', ENT_QUOTES); ?>)
            </div>
        </div>
        <div class="header-actions">
            <button class="btn btn-refresh" onclick="refreshData()" data-testid="refresh-btn">
                <i class="fas fa-sync-alt"></i>
                REFRESH FROM DB
            </button>
            <button class="btn btn-save" onclick="openCreateProductModal()" data-testid="new-product-btn">
                <i class="fas fa-plus-circle"></i>
                NEW PRODUCT
            </button>
            <a href="system-test.php" class="btn btn-refresh" data-testid="health-check-btn">
                <i class="fas fa-heartbeat"></i>
                SYSTEM HEALTH
            </a>
            <a href="?logout" class="btn btn-logout" data-testid="logout-btn">
                <i class="fas fa-power-off"></i>
                LOGOUT
            </a>
        </div>
    </div>

    <!-- CSRF token embedded for every fetch() call -->
    <script>
        window.HIVENEST_CSRF = <?php echo json_encode(csrfToken()); ?>;
    </script>

    <!-- Loading State -->
    <div id="loading" class="loading">
        <i class="fas fa-spinner fa-spin" style="font-size: 48px; margin-bottom: 20px;"></i>
        <div>LOADING NEURAL DATA...</div>
    </div>

    <!-- Content Container -->
    <div id="content" style="display: none;">
        <!-- Pages will be dynamically populated here -->
    </div>

    <!-- Edit/Add Package Modal -->
    <div id="packageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">ADD PACKAGE</h2>
                <button class="btn-close" onclick="closeModal()">&times;</button>
            </div>
            
            <form id="packageForm" onsubmit="savePackage(event)">
                <input type="hidden" id="package_id" name="pricing_id">
                <input type="hidden" id="product_id" name="product_id">

                <div class="form-group" id="productSelectGroup" style="display:none;">
                    <label class="form-label">Product</label>
                    <select class="form-select" id="product_select" name="product_select"></select>
                </div>

                <div class="form-group">
                    <label class="form-label">Package Name</label>
                    <input type="text" class="form-input" id="tier_name" name="tier_name" required placeholder="e.g., CYBER INITIATE">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Price ($)</label>
                    <input type="number" step="0.01" class="form-input" id="price" name="price" required placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Setup Fee ($)</label>
                    <input type="number" step="0.01" class="form-input" id="setup_fee" name="setup_fee" value="0.00">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Billing Cycle</label>
                    <select class="form-select" id="billing_cycle" name="billing_cycle" required>
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="annually">Annually</option>
                        <option value="one_time">One Time</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Features (one per line)</label>
                    <textarea class="form-textarea" id="features" name="features" placeholder="Feature 1&#10;Feature 2&#10;Feature 3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" id="is_featured" name="is_featured">
                        <span style="color: var(--cyber-neon-pink); font-weight: 600;">FEATURED PACKAGE</span>
                    </label>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> SAVE PACKAGE
                </button>
            </form>
        </div>
    </div>

    <!-- Create / Rename Product Modal -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="productModalTitle">CREATE PRODUCT</h2>
                <button class="btn-close" onclick="closeProductModal()">&times;</button>
            </div>
            <form id="productForm" onsubmit="saveProduct(event)" data-testid="product-form">
                <input type="hidden" id="prod_id" name="product_id">
                <input type="hidden" id="prod_mode" name="mode" value="create">

                <div class="form-group">
                    <label class="form-label">Product Name</label>
                    <input type="text" class="form-input" id="prod_name" name="name" required
                           placeholder="e.g., WordPress Hosting" data-testid="product-name-input">
                </div>

                <div class="form-group" id="prodTypeGroup">
                    <label class="form-label">Product Type</label>
                    <select class="form-select" id="prod_type" name="product_type">
                        <option value="hosting">Hosting</option>
                        <option value="domain">Domain</option>
                        <option value="email">Email</option>
                        <option value="security">Security</option>
                        <option value="server">Server</option>
                        <option value="design">Design / Branding</option>
                        <option value="ssl">SSL</option>
                        <option value="backup">Backup</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Page URL <span style="color:#5a7a86;">(optional)</span></label>
                    <input type="text" class="form-input" id="prod_page_url" name="page_url"
                           placeholder="/hosting/wordpress.php">
                </div>

                <div class="form-group" id="prodBasePriceGroup">
                    <label class="form-label">Base Price ($)</label>
                    <input type="number" step="0.01" class="form-input" id="prod_base_price"
                           name="base_price" value="0.00">
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="form-textarea" id="prod_description" name="description"
                              placeholder="Short product description shown on cards"></textarea>
                </div>

                <button type="submit" class="btn-submit" data-testid="product-save-btn">
                    <i class="fas fa-save"></i> <span id="prod_submit_label">CREATE PRODUCT</span>
                </button>
            </form>
        </div>
    </div>

    <script>
        let allData = {};
        let currentEditProduct = null;
        // Lookup maps (avoid embedding JSON in HTML attributes — fragile + XSS-risk)
        const packageMap = {};   // pricing_id -> { pkg, product_id }
        const productMap = {};   // product_id -> { product_name, product_slug, page_name }

        // Always send CSRF token + JSON-decode response (auto-handles 403s)
        async function postAjax(action, fields = {}) {
            const fd = new FormData();
            fd.append('ajax_action', action);
            fd.append('csrf_token', window.HIVENEST_CSRF || '');
            for (const [k, v] of Object.entries(fields)) {
                fd.append(k, v == null ? '' : v);
            }
            const res = await fetch('products-admin.php', { method: 'POST', body: fd });
            try {
                return await res.json();
            } catch (e) {
                return { success: false, message: 'Server returned invalid response (HTTP ' + res.status + ')' };
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            refreshData();

            // Delegated click handlers for edit / delete / product CRUD buttons
            document.getElementById('content').addEventListener('click', function(e) {
                // Product rename button (must check before generic .btn-edit)
                const renameBtn = e.target.closest('.btn-product-rename');
                if (renameBtn) {
                    e.stopPropagation();
                    openRenameProductModal(
                        renameBtn.dataset.productId,
                        renameBtn.dataset.productName,
                        renameBtn.dataset.pageUrl
                    );
                    return;
                }
                // Product enable/disable button (must check before generic .btn-delete)
                const toggleBtn = e.target.closest('.btn-product-toggle');
                if (toggleBtn) {
                    e.stopPropagation();
                    toggleProductActive(
                        toggleBtn.dataset.productId,
                        toggleBtn.dataset.active === '1'
                    );
                    return;
                }
                const editBtn = e.target.closest('.btn-edit');
                if (editBtn) {
                    const pricingId = editBtn.dataset.pricingId;
                    const entry = packageMap[pricingId];
                    if (entry) editPackage(entry.pkg, entry.product_id);
                    return;
                }
                const delBtn = e.target.closest('.btn-delete');
                if (delBtn) {
                    deletePackage(delBtn.dataset.pricingId);
                    return;
                }
                const addBtn = e.target.closest('.btn-add');
                if (addBtn) {
                    e.stopPropagation();
                    openAddModal(addBtn.dataset.pageName);
                    return;
                }
                const pageHeader = e.target.closest('.page-header');
                if (pageHeader && !e.target.closest('.btn-add')) {
                    togglePage(pageHeader.dataset.pageName);
                }
            });
        });
        
        // Refresh data from database
        async function refreshData() {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('content').style.display = 'none';

            try {
                const result = await postAjax('refresh_data');

                if (result.success) {
                    allData = result.data;
                    // Reset lookup maps
                    Object.keys(packageMap).forEach(k => delete packageMap[k]);
                    Object.keys(productMap).forEach(k => delete productMap[k]);
                    renderPages();
                    showNotification('Data refreshed successfully!', 'success');
                } else {
                    showNotification('Error: ' + result.message, 'error');
                }
            } catch (error) {
                showNotification('Failed to load data: ' + error.message, 'error');
            } finally {
                document.getElementById('loading').style.display = 'none';
                document.getElementById('content').style.display = 'block';
            }
        }
        
        // Render all pages
        function renderPages() {
            const content = document.getElementById('content');
            content.innerHTML = '';
            
            // Sort pages alphabetically
            const sortedPages = Object.keys(allData).sort();
            
            sortedPages.forEach(pageName => {
                const products = allData[pageName];
                const totalPackages = Object.values(products).reduce((sum, prod) => sum + prod.packages.length, 0);
                const pageId = pageName.replace(/[^a-z0-9]/gi, '-');
                
                // Cache products for this page in productMap
                Object.values(products).forEach(p => {
                    productMap[p.product_id] = {
                        product_id: p.product_id,
                        product_name: p.product_name,
                        product_slug: p.product_slug,
                        page_name: pageName
                    };
                });
                
                const pageSection = document.createElement('div');
                pageSection.className = 'page-section';
                pageSection.id = `page-${pageId}`;
                
                pageSection.innerHTML = `
                    <div class="page-header" data-page-name="${escapeAttr(pageName)}">
                        <div class="page-title">
                            <i class="fas fa-chevron-down toggle-icon"></i>
                            ${formatPageName(pageName)}
                            <span class="page-count">${totalPackages} packages</span>
                        </div>
                        <button class="btn-add" data-page-name="${escapeAttr(pageName)}">
                            <i class="fas fa-plus"></i>
                            ADD PACKAGE
                        </button>
                    </div>
                    <div class="packages-container" id="packages-${pageId}">
                        ${renderProducts(products)}
                    </div>
                `;
                
                content.appendChild(pageSection);
            });
        }

        // Render products grouped (each product gets its own controls row)
        function renderProducts(products) {
            const productList = Object.values(products);
            if (productList.length === 0) {
                return '<div style="padding: 30px; text-align: center; color: rgba(255,255,255,0.5);">No products on this page</div>';
            }
            return productList.map(product => {
                const active = (product.product_active === undefined || product.product_active == 1);
                return `
                    <div class="product-group" style="grid-column:1/-1; border:1px solid rgba(0,255,255,0.25); border-radius:10px; padding:18px; margin-bottom:14px; background:rgba(0,0,0,0.35); ${active ? '' : 'opacity:0.55;'}">
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px;">
                            <div>
                                <div style="font-size:18px; font-weight:700; color:#00ffff; letter-spacing:1px;">
                                    ${escapeHtml(product.product_name)}
                                    ${active ? '' : '<span style="margin-left:10px;font-size:11px;background:#ff0064;color:#fff;padding:2px 8px;border-radius:10px;">DISABLED</span>'}
                                </div>
                                <div style="font-size:12px; color:rgba(255,255,255,0.5); margin-top:4px;">
                                    slug: ${escapeHtml(product.product_slug)} · id: ${product.product_id} · type: ${escapeHtml(product.product_type || '-')}
                                </div>
                            </div>
                            <div style="display:flex; gap:8px;">
                                <button class="btn-edit btn-product-rename" data-testid="rename-product-${product.product_id}"
                                        data-product-id="${product.product_id}"
                                        data-product-name="${escapeAttr(product.product_name)}"
                                        data-page-url="${escapeAttr(Object.keys(allData).find(k => allData[k][product.product_slug]) || '')}"
                                        style="padding:8px 14px; min-width:auto;">
                                    <i class="fas fa-pen"></i> RENAME
                                </button>
                                <button class="btn-delete btn-product-toggle" data-testid="toggle-product-${product.product_id}"
                                        data-product-id="${product.product_id}"
                                        data-active="${active ? '1' : '0'}"
                                        style="padding:8px 14px; min-width:auto;">
                                    <i class="fas fa-${active ? 'ban' : 'check'}"></i> ${active ? 'DISABLE' : 'ENABLE'}
                                </button>
                            </div>
                        </div>
                        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(310px, 1fr)); gap:14px;">
                            ${renderPackagesForProduct(product)}
                        </div>
                    </div>
                `;
            }).join('');
        }

        function renderPackagesForProduct(product) {
            if (!product.packages || product.packages.length === 0) {
                return '<div style="padding:20px; text-align:center; color:rgba(255,255,255,0.4); border:1px dashed rgba(0,255,255,0.2); border-radius:8px;">No packages yet — use ADD PACKAGE above</div>';
            }
            return product.packages.map(pkg => {
                packageMap[pkg.pricing_id] = { pkg: pkg, product_id: product.product_id };
                return `
                    <div class="package-card ${pkg.is_featured == 1 ? 'featured' : ''}">
                        <div class="package-header">
                            <div>
                                <div class="package-name">${escapeHtml(pkg.tier_name)}</div>
                            </div>
                            ${pkg.is_featured == 1 ? '<div class="package-featured">FEATURED</div>' : ''}
                        </div>
                        <div class="package-price">$${parseFloat(pkg.price).toFixed(2)}</div>
                        <div class="package-cycle">${formatBillingCycle(pkg.billing_cycle)}</div>
                        <ul class="package-features">
                            ${(pkg.features || []).map(f => `<li>${escapeHtml(f)}</li>`).join('')}
                        </ul>
                        <div class="package-actions">
                            <button class="btn-edit" data-pricing-id="${pkg.pricing_id}">
                                <i class="fas fa-edit"></i> EDIT
                            </button>
                            <button class="btn-delete" data-pricing-id="${pkg.pricing_id}">
                                <i class="fas fa-trash"></i> DELETE
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        // Toggle page collapse
        function togglePage(pageName) {
            const pageId = `page-${pageName.replace(/[^a-z0-9]/gi, '-')}`;
            const section = document.getElementById(pageId);
            if (section) section.classList.toggle('collapsed');
        }
        
        // Open add modal — supports pages with multiple products via dropdown
        function openAddModal(pageName) {
            const products = allData[pageName];
            if (!products) {
                showNotification('Page not found', 'error');
                return;
            }
            const productList = Object.values(products);
            if (productList.length === 0) {
                showNotification('No products on this page', 'error');
                return;
            }
            
            document.getElementById('modalTitle').textContent = 'ADD NEW PACKAGE';
            document.getElementById('packageForm').reset();
            document.getElementById('package_id').value = '';
            
            const selectGroup = document.getElementById('productSelectGroup');
            const select = document.getElementById('product_select');
            select.innerHTML = '';
            productList.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.product_id;
                opt.textContent = `${p.product_name} (#${p.product_id})`;
                select.appendChild(opt);
            });
            // Show the dropdown only when there are multiple choices to avoid clutter
            selectGroup.style.display = productList.length > 1 ? 'block' : 'none';
            select.value = productList[0].product_id;
            document.getElementById('product_id').value = productList[0].product_id;
            select.onchange = () => {
                document.getElementById('product_id').value = select.value;
                currentEditProduct = select.value;
            };
            currentEditProduct = productList[0].product_id;
            
            document.getElementById('packageModal').classList.add('active');
        }
        
        // Edit package
        function editPackage(pkg, productId) {
            document.getElementById('modalTitle').textContent = 'EDIT PACKAGE';
            document.getElementById('package_id').value = pkg.pricing_id;
            document.getElementById('product_id').value = productId;
            document.getElementById('tier_name').value = pkg.tier_name;
            document.getElementById('price').value = pkg.price;
            document.getElementById('setup_fee').value = pkg.setup_fee || 0;
            document.getElementById('billing_cycle').value = pkg.billing_cycle;
            document.getElementById('features').value = (pkg.features || []).join('\n');
            document.getElementById('is_featured').checked = pkg.is_featured == 1;
            // Edit doesn't allow changing the product, so hide the selector
            document.getElementById('productSelectGroup').style.display = 'none';
            currentEditProduct = productId;
            
            document.getElementById('packageModal').classList.add('active');
        }
        
        // Close modal
        function closeModal() {
            document.getElementById('packageModal').classList.remove('active');
            document.getElementById('packageForm').reset();
        }
        
        // Save package
        async function savePackage(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const features = formData.get('features').split('\n').filter(f => f.trim() !== '');
            
            const packageData = {
                pricing_id: formData.get('pricing_id') || null,
                product_id: formData.get('product_id'),
                tier_name: formData.get('tier_name'),
                price: parseFloat(formData.get('price')),
                setup_fee: parseFloat(formData.get('setup_fee')) || 0,
                billing_cycle: formData.get('billing_cycle'),
                features: features,
                is_featured: formData.get('is_featured') ? true : false,
                sort_order: 99
            };
            
            try {
                const result = await postAjax('save_package', { package_data: JSON.stringify(packageData) });

                if (result.success) {
                    showNotification('Package saved successfully!', 'success');
                    closeModal();
                    refreshData();
                } else {
                    showNotification('Error: ' + result.message, 'error');
                }
            } catch (error) {
                showNotification('Failed to save: ' + error.message, 'error');
            }
        }
        
        // Delete package
        async function deletePackage(pricingId) {
            try {
                const result = await postAjax('delete_package', { pricing_id: pricingId });

                if (result.success) {
                    showNotification('Package deleted!', 'success');
                    refreshData();
                } else {
                    showNotification('Error: ' + result.message, 'error');
                }
            } catch (error) {
                showNotification('Failed to delete: ' + error.message, 'error');
            }
        }

        // ===== Products-table CRUD =====
        function openCreateProductModal() {
            document.getElementById('productModalTitle').textContent = 'CREATE PRODUCT';
            document.getElementById('prod_submit_label').textContent = 'CREATE PRODUCT';
            document.getElementById('productForm').reset();
            document.getElementById('prod_id').value = '';
            document.getElementById('prod_mode').value = 'create';
            document.getElementById('prodTypeGroup').style.display = 'block';
            document.getElementById('prodBasePriceGroup').style.display = 'block';
            document.getElementById('productModal').classList.add('active');
        }

        function openRenameProductModal(productId, currentName, pageUrl) {
            document.getElementById('productModalTitle').textContent = 'RENAME / EDIT PRODUCT';
            document.getElementById('prod_submit_label').textContent = 'SAVE CHANGES';
            document.getElementById('productForm').reset();
            document.getElementById('prod_id').value = productId;
            document.getElementById('prod_mode').value = 'rename';
            document.getElementById('prod_name').value = currentName || '';
            document.getElementById('prod_page_url').value = pageUrl || '';
            // Type / base price aren't editable here — hide to keep the UI focused
            document.getElementById('prodTypeGroup').style.display = 'none';
            document.getElementById('prodBasePriceGroup').style.display = 'none';
            document.getElementById('productModal').classList.add('active');
        }

        function closeProductModal() {
            document.getElementById('productModal').classList.remove('active');
            document.getElementById('productForm').reset();
        }

        async function saveProduct(event) {
            event.preventDefault();
            const mode = document.getElementById('prod_mode').value;
            const fields = {
                name:        document.getElementById('prod_name').value,
                page_url:    document.getElementById('prod_page_url').value,
                description: document.getElementById('prod_description').value,
            };
            try {
                let result;
                if (mode === 'create') {
                    fields.product_type = document.getElementById('prod_type').value;
                    fields.base_price   = document.getElementById('prod_base_price').value || '0';
                    result = await postAjax('create_product', fields);
                } else {
                    fields.product_id = document.getElementById('prod_id').value;
                    result = await postAjax('rename_product', fields);
                }
                if (result.success) {
                    showNotification(result.message || 'Saved', 'success');
                    closeProductModal();
                    refreshData();
                } else {
                    showNotification('Error: ' + result.message, 'error');
                }
            } catch (e) {
                showNotification('Failed: ' + e.message, 'error');
            }
        }

        async function toggleProductActive(productId, currentlyActive) {
            const action = currentlyActive ? 'disable_product' : 'enable_product';
            try {
                const result = await postAjax(action, { product_id: productId });
                if (result.success) {
                    showNotification(result.message, 'success');
                    refreshData();
                } else {
                    showNotification('Error: ' + result.message, 'error');
                }
            } catch (e) {
                showNotification('Failed: ' + e.message, 'error');
            }
        }
        
        // Format page name
        function formatPageName(pageName) {
            if (!pageName || pageName === 'Uncategorized') {
                return '🌐 UNCATEGORIZED';
            }
            
            // Extract filename from path
            const fileName = pageName.split('/').pop().replace('.php', '');
            
            // Convert to title case
            return fileName
                .split('-')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ')
                .toUpperCase();
        }
        
        // Format billing cycle
        function formatBillingCycle(cycle) {
            const map = {
                'monthly': 'Per Month',
                'quarterly': 'Per Quarter',
                'annually': 'Per Year',
                'one_time': 'One-Time Payment'
            };
            return map[cycle] || cycle;
        }
        
        // Show notification
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i> ${message}`;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
        
        // Close modal on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });

        // ---- HTML escaping helpers (prevent XSS / broken markup from user data) ----
        function escapeHtml(s) {
            if (s === null || s === undefined) return '';
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
        function escapeAttr(s) { return escapeHtml(s); }
    </script>
</body>
</html>
