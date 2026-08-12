<?php
/**
 * Database Helper for HiveNest
 * Fetches pricing and product data from MySQL database.
 *
 * DB credentials & connection now come from the central
 * /access/dbconfig.php (single source of truth across the whole app).
 */

require_once __DIR__ . '/../access/dbconfig.php';

class DatabaseHelper {
    private $conn;
    private $db_host;
    private $db_port;
    private $db_name;
    private $db_user;
    private $db_pass;
    private $db_type; // 'mysql' or 'sqlite'

    public function __construct() {
        // Hydrate the legacy properties from the central credentials store
        $c = hivenest_db_credentials();
        $this->db_host = $c['host'];
        $this->db_port = (int)$c['port'];
        $this->db_name = $c['dbname'];
        $this->db_user = $c['username'];
        $this->db_pass = $c['password'];
        $this->connect();
    }

    private function loadEnvConfig() {
        // Kept for backward compatibility — credentials are already loaded
        // centrally by /access/dbconfig.php in the constructor.
        $c = hivenest_db_credentials();
        $this->db_host = $c['host'];
        $this->db_port = (int)$c['port'];
        $this->db_name = $c['dbname'];
        $this->db_user = $c['username'];
        $this->db_pass = $c['password'];
    }

    private function connect() {
        // First try SQLite database (local development) if SQLite3 ext is loaded
        $sqlite_db = __DIR__ . '/../Backend/hivenest_development.db';
        if (file_exists($sqlite_db) && class_exists('SQLite3')) {
            try {
                $this->conn = new SQLite3($sqlite_db);
                $this->db_type = 'sqlite';
                return;
            } catch (Exception $e) {
                error_log("SQLite connection error: " . $e->getMessage());
            }
        }

        // Fallback to MySQL via central dbconfig (single source of truth)
        $this->conn = hivenest_db_mysqli();
        if ($this->conn instanceof mysqli) {
            $this->db_type = 'mysql';
        } else {
            $this->conn = null;
        }
    }
    
    /**
     * Get all active products with their prices
     */
    public function getAllProducts() {
        if (!$this->conn) {
            return $this->getFallbackProducts();
        }
        
        $query = "SELECT 
                    p.id,
                    p.name,
                    p.slug,
                    p.description,
                    p.short_description,
                    p.product_type,
                    p.billing_cycle,
                    p.base_price,
                    p.setup_fee,
                    p.features,
                    p.is_featured,
                    pc.name as category_name
                  FROM products p
                  LEFT JOIN product_categories pc ON p.category_id = pc.id
                  WHERE p.is_active = 1
                  ORDER BY p.sort_order ASC";
        
        $result = $this->conn->query($query);
        
        if (!$result) {
            error_log("Query failed: " . $this->conn->error);
            return $this->getFallbackProducts();
        }
        
        $products = [];
        while ($row = $result->fetch_assoc()) {
            // Parse JSON features if available
            if ($row['features']) {
                $row['features'] = json_decode($row['features'], true);
            }
            $products[] = $row;
        }
        
        return $products;
    }
    
    /**
     * Get products by type (hosting, domain, email, etc.)
     */
    public function getProductsByType($type) {
        if (!$this->conn) {
            $allProducts = $this->getFallbackProducts();
            return array_filter($allProducts, function($p) use ($type) {
                return $p['product_type'] === $type;
            });
        }
        
        if ($this->db_type === 'sqlite') {
            $query = "SELECT 
                        p.id,
                        p.name,
                        p.slug,
                        p.description,
                        p.short_description,
                        p.product_type,
                        p.billing_cycle,
                        p.base_price,
                        p.setup_fee,
                        p.features,
                        p.is_featured,
                        pc.name as category_name
                      FROM products p
                      LEFT JOIN product_categories pc ON p.category_id = pc.id
                      WHERE p.is_active = 1 AND p.product_type = :type
                      ORDER BY p.sort_order ASC, p.base_price ASC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':type', $type, SQLITE3_TEXT);
            $result = $stmt->execute();
            
            $products = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                if ($row['features']) {
                    $row['features'] = json_decode($row['features'], true);
                }
                $products[] = $row;
            }
            return $products;
        } else {
            // MySQL
            $stmt = $this->conn->prepare("SELECT 
                        p.id,
                        p.name,
                        p.slug,
                        p.description,
                        p.short_description,
                        p.product_type,
                        p.billing_cycle,
                        p.base_price,
                        p.setup_fee,
                        p.features,
                        p.is_featured,
                        pc.name as category_name
                      FROM products p
                      LEFT JOIN product_categories pc ON p.category_id = pc.id
                      WHERE p.is_active = 1 AND p.product_type = ?
                      ORDER BY p.sort_order ASC");
            
            $stmt->bind_param("s", $type);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $products = [];
            while ($row = $result->fetch_assoc()) {
                if ($row['features']) {
                    $row['features'] = json_decode($row['features'], true);
                }
                $products[] = $row;
            }
            
            return $products;
        }
    }
    
    /**
     * Get a single product by slug
     */
    public function getProductBySlug($slug) {
        if (!$this->conn) {
            $allProducts = $this->getFallbackProducts();
            foreach ($allProducts as $product) {
                if ($product['slug'] === $slug) {
                    return $product;
                }
            }
            return null;
        }
        
        $stmt = $this->conn->prepare("SELECT 
                    p.id,
                    p.name,
                    p.slug,
                    p.description,
                    p.short_description,
                    p.product_type,
                    p.billing_cycle,
                    p.base_price,
                    p.setup_fee,
                    p.features,
                    p.is_featured,
                    pc.name as category_name
                  FROM products p
                  LEFT JOIN product_categories pc ON p.category_id = pc.id
                  WHERE p.is_active = 1 AND p.slug = ?");
        
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if ($row['features']) {
                $row['features'] = json_decode($row['features'], true);
            }
            return $row;
        }
        
        return null;
    }
    
    /**
     * Format price for display
     */
    public function formatPrice($price, $billing_cycle = 'monthly') {
        $formatted_price = '$' . number_format($price, 2);
        
        switch ($billing_cycle) {
            case 'monthly':
                return $formatted_price . '/mo';
            case 'quarterly':
                return $formatted_price . '/3mo';
            case 'semi_annually':
                return $formatted_price . '/6mo';
            case 'annually':
                return $formatted_price . '/yr';
            case 'biennially':
                return $formatted_price . '/2yr';
            case 'triennially':
                return $formatted_price . '/3yr';
            default:
                return $formatted_price;
        }
    }
    
    /**
     * Fallback products if database is unavailable
     */
    private function getFallbackProducts() {
        return [
            [
                'id' => 1,
                'name' => 'Cyber Initiate Hosting',
                'slug' => 'cyber-initiate-hosting',
                'description' => 'Perfect entry-level hosting for beginners',
                'short_description' => 'Perfect for beginners',
                'product_type' => 'hosting',
                'billing_cycle' => 'monthly',
                'base_price' => 5.99,
                'setup_fee' => 0.00,
                'features' => ['1 Website', '10GB SSD Storage', '100GB Bandwidth', 'Free SSL', '1 Email Account'],
                'is_featured' => 1,
                'category_name' => 'Hosting'
            ],
            [
                'id' => 2,
                'name' => 'Digital Warrior Hosting',
                'slug' => 'digital-warrior-hosting',
                'description' => 'Ideal for growing businesses',
                'short_description' => 'Ideal for growing businesses',
                'product_type' => 'hosting',
                'billing_cycle' => 'monthly',
                'base_price' => 15.99,
                'setup_fee' => 0.00,
                'features' => ['5 Websites', '50GB SSD Storage', '500GB Bandwidth', 'Free SSL', '25 Email Accounts'],
                'is_featured' => 1,
                'category_name' => 'Hosting'
            ],
            [
                'id' => 3,
                'name' => 'Quantum Master Hosting',
                'slug' => 'quantum-master-hosting',
                'description' => 'Ultimate power and performance',
                'short_description' => 'Ultimate power',
                'product_type' => 'hosting',
                'billing_cycle' => 'monthly',
                'base_price' => 29.99,
                'setup_fee' => 0.00,
                'features' => ['Unlimited Websites', '200GB SSD Storage', 'Unlimited Bandwidth', 'Free SSL', 'Unlimited Email'],
                'is_featured' => 1,
                'category_name' => 'Hosting'
            ]
        ];
    }
    
    public function __destruct() {
        if ($this->conn) {
            if ($this->db_type === 'sqlite') {
                $this->conn->close();
            } elseif ($this->db_type === 'mysql') {
                $this->conn->close();
            }
        }
    }
}

// Create a global instance
$db_helper = new DatabaseHelper();
?>
