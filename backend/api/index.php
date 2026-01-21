<?php
/**
 * Simple PHP Router for Admin API
 * Works with PHP 8.0+
 */

// Suppress PHP errors from being output as HTML - convert to JSON instead
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Custom error handler to return JSON
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Custom exception handler to return JSON
set_exception_handler(function($exception) {
    // Clear any buffered output
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $exception->getMessage(),
        'debug' => [
            'file' => basename($exception->getFile()),
            'line' => $exception->getLine()
        ]
    ]);
    exit;
});

// Start output buffering to catch any stray output
ob_start();

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database configuration - KPS Nursery
// Detect if running on Hostinger or local
$serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
$isHostinger = strpos($serverName, 'kpsnursery') !== false ||
               strpos($serverName, 'upgradenow.in') !== false ||
               strpos($serverName, 'hostinger') !== false;
$isLocal = ($serverName === 'localhost' || $serverName === '127.0.0.1');

// Database configuration based on environment
if ($isHostinger) {
    // Production - Hostinger server
    $dbConfig = [
        'host' => 'localhost',
        'dbname' => 'u282002960_kpsnursery',
        'username' => 'u282002960_kpsnursery',
        'password' => 'KpsNusery@123'
    ];
} else {
    // Local development - XAMPP MySQL
    $dbConfig = [
        'host' => 'localhost',
        'dbname' => 'kps_nursery',
        'username' => 'root',
        'password' => ''
    ];
}

// Connect to database
try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Get request info
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Remove base path - handle both local and production paths
// Local: /kps-nursery/backend/api/index.php/admin/...
// Production: /backend/api/index.php/admin/...
$basePaths = [
    '/kps-nursery/backend/api/index.php',       // Local XAMPP KPS Nursery
    '/chicken-shop-app/backend/api/index.php',  // Local XAMPP (legacy)
    '/backend/api/index.php',                    // Production
    '/kps-nursery/backend/api',                  // Local without index.php
    '/chicken-shop-app/backend/api',             // Local without index.php (legacy)
    '/backend/api'                               // Production without index.php
];

foreach ($basePaths as $basePath) {
    if (strpos($path, $basePath) === 0) {
        $path = substr($path, strlen($basePath));
        break;
    }
}

$path = rtrim($path, '/');
if (empty($path)) $path = '/';

// Get request body
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Get authorization token
$token = null;
$headers = getallheaders();
if (isset($headers['Authorization'])) {
    $token = str_replace('Bearer ', '', $headers['Authorization']);
}

// Response helper
function respond($data, $code = 200) {
    // Clear any buffered output to ensure clean JSON response
    ob_end_clean();
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Authentication helper
function requireAuth($pdo, $token) {
    if (!$token) {
        respond(['success' => false, 'message' => 'Authentication required'], 401);
    }

    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare("SELECT * FROM admin_sessions WHERE session_token = ? AND expires_at > NOW()");
    $stmt->execute([$tokenHash]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        respond(['success' => false, 'message' => 'Invalid or expired session'], 401);
    }

    // Extend session
    $pdo->prepare("UPDATE admin_sessions SET expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = ?")
        ->execute([$session['id']]);

    return $session['admin_id'];
}

// Routes
$routes = [
    'GET' => [],
    'POST' => [],
    'PUT' => [],
    'DELETE' => []
];

// === RUN MIGRATIONS ON STARTUP ===
// Add discount_price column if missing
try {
    $cols = $pdo->query("SHOW COLUMNS FROM products LIKE 'discount_price'")->fetchAll();
    if (count($cols) === 0) {
        $pdo->exec("ALTER TABLE products ADD COLUMN discount_price DECIMAL(10,2) DEFAULT NULL AFTER price");
    }
} catch (Exception $e) {
    // Ignore migration errors
}

// === DEBUG: Check products table structure ===
if ($path === '/admin/debug/products-table' && $method === 'GET') {
    try {
        $stmt = $pdo->query("DESCRIBE products");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        respond(['success' => true, 'columns' => $columns]);
    } catch (Exception $e) {
        respond(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

// === AUTH ROUTES ===
if ($path === '/admin/auth/login' && $method === 'POST') {
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin || !password_verify($password, $admin['password'])) {
        respond(['success' => false, 'message' => 'Invalid credentials'], 401);
    }

    // Create session
    $sessionToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $sessionToken);

    $stmt = $pdo->prepare("INSERT INTO admin_sessions (admin_id, session_token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
    $stmt->execute([$admin['id'], $tokenHash]);

    // Update last login
    $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);

    respond([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'session_token' => $sessionToken,
            'admin' => [
                'id' => $admin['id'],
                'name' => $admin['name'],
                'username' => $admin['username'],
                'email' => $admin['email'],
                'role' => $admin['role']
            ]
        ]
    ]);
}

if ($path === '/admin/auth/verify' && $method === 'POST') {
    $adminId = requireAuth($pdo, $token);

    $stmt = $pdo->prepare("SELECT id, name, username, email, role FROM admins WHERE id = ?");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    respond([
        'success' => true,
        'data' => ['admin' => $admin]
    ]);
}

if ($path === '/admin/auth/logout' && $method === 'POST') {
    if ($token) {
        $tokenHash = hash('sha256', $token);
        $pdo->prepare("DELETE FROM admin_sessions WHERE session_token = ?")->execute([$tokenHash]);
    }
    respond(['success' => true, 'message' => 'Logged out']);
}

// === DASHBOARD ===
if ($path === '/admin/dashboard' && $method === 'GET') {
    requireAuth($pdo, $token);

    // Get stats - return directly without nested 'stats' object
    $data = [];

    // Total orders
    $stmt = $pdo->query("SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as revenue FROM orders");
    $orderStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $data['total_orders'] = (int)$orderStats['total'];
    $data['total_revenue'] = (float)$orderStats['revenue'];

    // Total customers (total_users for frontend compatibility)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $data['total_users'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['total_customers'] = $data['total_users'];

    // Total products
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
    $data['total_products'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Pending orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
    $data['pending_orders'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Today's stats
    $stmt = $pdo->query("SELECT COUNT(*) as orders, COALESCE(SUM(total), 0) as revenue FROM orders WHERE DATE(created_at) = CURDATE()");
    $today = $stmt->fetch(PDO::FETCH_ASSOC);
    $data['today_orders'] = (int)$today['orders'];
    $data['today_revenue'] = (float)$today['revenue'];

    // Low stock products
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE stock <= 10 AND stock > 0");
    $data['low_stock_products'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Recent orders - use customer_name from orders table if available
    $stmt = $pdo->query("SELECT o.*,
        COALESCE(o.customer_name, u.name) as customer_name,
        COALESCE(o.customer_mobile, u.mobile) as customer_mobile
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC
        LIMIT 5");
    $data['recent_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top selling products
    $stmt = $pdo->query("SELECT p.id, p.name, p.image,
        COALESCE(SUM(oi.quantity), 0) as total_sold,
        COALESCE(SUM(oi.subtotal), 0) as total_revenue
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        GROUP BY p.id
        ORDER BY total_sold DESC
        LIMIT 5");
    $data['top_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Order status distribution
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status ORDER BY count DESC");
    $data['order_status_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respond([
        'success' => true,
        'data' => $data
    ]);
}

// === CATEGORIES ===
if (preg_match('#^/admin/categories(?:/(\d+))?$#', $path, $matches)) {
    $id = $matches[1] ?? null;

    if ($method === 'GET') {
        requireAuth($pdo, $token);

        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            respond(['success' => true, 'data' => $category]);
        } else {
            $stmt = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count FROM categories c ORDER BY display_order, name");
            respond(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
    }

    if ($method === 'POST') {
        requireAuth($pdo, $token);

        $name = $input['name'] ?? '';
        $slug = $input['slug'] ?: strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        $icon = $input['icon'] ?? '';
        $displayOrder = $input['display_order'] ?? 0;
        $isActive = $input['is_active'] ?? true;

        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, display_order, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $icon, $displayOrder, $isActive ? 1 : 0]);

        respond(['success' => true, 'message' => 'Category created', 'data' => ['id' => $pdo->lastInsertId()]]);
    }

    if ($method === 'PUT') {
        requireAuth($pdo, $token);

        $id = $input['id'] ?? $id;
        $name = $input['name'] ?? '';
        $slug = $input['slug'] ?: strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        $icon = $input['icon'] ?? '';
        $displayOrder = $input['display_order'] ?? 0;
        $isActive = $input['is_active'] ?? true;

        $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, icon = ?, display_order = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$name, $slug, $icon, $displayOrder, $isActive ? 1 : 0, $id]);

        respond(['success' => true, 'message' => 'Category updated']);
    }

    if ($method === 'DELETE' && $id) {
        requireAuth($pdo, $token);

        // Check for products
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            respond(['success' => false, 'message' => 'Cannot delete category with products'], 400);
        }

        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
        respond(['success' => true, 'message' => 'Category deleted']);
    }
}

// === PRODUCTS SCHEMA MIGRATION ===
// Auto-add missing columns if they don't exist
function ensureProductsSchema($pdo) {
    try {
        $stmt = $pdo->query("DESCRIBE products");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Add discount_price column if missing
        if (!in_array('discount_price', $columns)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN discount_price DECIMAL(10,2) DEFAULT NULL AFTER price");
        }

        // Add is_popular column if missing
        if (!in_array('is_popular', $columns)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN is_popular TINYINT(1) DEFAULT 0 AFTER is_active");
        }
    } catch (Exception $e) {
        // Table might not exist or other issue - just log and continue
        error_log("Products schema check failed: " . $e->getMessage());
    }
}

// === PRODUCTS ===
if (preg_match('#^/admin/products(?:/(\d+))?$#', $path, $matches)) {
    $id = $matches[1] ?? null;

    // Ensure schema is up to date
    ensureProductsSchema($pdo);

    if ($method === 'GET') {
        requireAuth($pdo, $token);

        if ($id) {
            $stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
            $stmt->execute([$id]);
            respond(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        } else {
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 20;
            $search = $_GET['search'] ?? '';
            $categoryId = $_GET['category_id'] ?? '';
            $status = $_GET['status'] ?? '';

            $offset = ($page - 1) * $limit;
            $where = [];
            $params = [];

            if ($search) {
                $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            if ($categoryId) {
                $where[] = "p.category_id = ?";
                $params[] = $categoryId;
            }
            if ($status) {
                $where[] = "p.is_active = ?";
                $params[] = $status === 'active' ? 1 : 0;
            }

            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            // Count total
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p $whereClause");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            // Get data
            $stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id $whereClause ORDER BY p.display_order, p.name LIMIT $limit OFFSET $offset");
            $stmt->execute($params);

            respond([
                'success' => true,
                'data' => [
                    'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                    'pagination' => [
                        'current_page' => (int)$page,
                        'total_pages' => ceil($total / $limit),
                        'total' => (int)$total,
                        'per_page' => (int)$limit
                    ]
                ]
            ]);
        }
    }

    if ($method === 'POST') {
        requireAuth($pdo, $token);

        // Check which columns exist
        $existingCols = [];
        try {
            $colsResult = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
            $existingCols = $colsResult;
        } catch (Exception $e) {
            $existingCols = [];
        }

        $hasPopular = in_array('is_popular', $existingCols);
        $hasDiscountPrice = in_array('discount_price', $existingCols);

        // Build dynamic query based on existing columns
        $columns = ['name', 'category_id', 'description', 'price', 'unit', 'stock', 'display_order', 'image', 'is_active'];
        $values = [
            $input['name'],
            $input['category_id'],
            $input['description'] ?? '',
            $input['price'],
            $input['unit'] ?? 'kg',
            $input['stock'] ?? 0,
            $input['display_order'] ?? 0,
            $input['image'] ?? '',
            ($input['is_active'] ?? true) ? 1 : 0
        ];

        if ($hasDiscountPrice) {
            $columns[] = 'discount_price';
            $values[] = $input['discount_price'] ?: null;
        }

        if ($hasPopular) {
            $columns[] = 'is_popular';
            $values[] = ($input['is_popular'] ?? false) ? 1 : 0;
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList = implode(', ', $columns);

        $stmt = $pdo->prepare("INSERT INTO products ($columnList) VALUES ($placeholders)");
        $stmt->execute($values);

        respond(['success' => true, 'message' => 'Product created', 'data' => ['id' => $pdo->lastInsertId()]]);
    }

    if ($method === 'PUT') {
        requireAuth($pdo, $token);

        $id = $input['id'] ?? $id;

        // Check which columns exist
        $existingCols = [];
        try {
            $colsResult = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
            $existingCols = $colsResult;
        } catch (Exception $e) {
            $existingCols = [];
        }

        $hasPopular = in_array('is_popular', $existingCols);
        $hasDiscountPrice = in_array('discount_price', $existingCols);

        // Build dynamic query based on existing columns
        $setParts = ['name = ?', 'category_id = ?', 'description = ?', 'price = ?', 'unit = ?', 'stock = ?', 'display_order = ?', 'image = ?', 'is_active = ?'];
        $values = [
            $input['name'],
            $input['category_id'],
            $input['description'] ?? '',
            $input['price'],
            $input['unit'] ?? 'kg',
            $input['stock'] ?? 0,
            $input['display_order'] ?? 0,
            $input['image'] ?? '',
            ($input['is_active'] ?? true) ? 1 : 0
        ];

        if ($hasDiscountPrice) {
            $setParts[] = 'discount_price = ?';
            $values[] = $input['discount_price'] ?: null;
        }

        if ($hasPopular) {
            $setParts[] = 'is_popular = ?';
            $values[] = ($input['is_popular'] ?? false) ? 1 : 0;
        }

        // Add ID at the end for WHERE clause
        $values[] = $id;

        $setClause = implode(', ', $setParts);
        $stmt = $pdo->prepare("UPDATE products SET $setClause WHERE id = ?");
        $stmt->execute($values);

        respond(['success' => true, 'message' => 'Product updated']);
    }

    if ($method === 'DELETE' && $id) {
        requireAuth($pdo, $token);
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
        respond(['success' => true, 'message' => 'Product deleted']);
    }
}

// === ORDERS SCHEMA MIGRATION ===
function ensureOrdersSchema($pdo) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM orders");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('cancelled_reason', $columns)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN cancelled_reason TEXT NULL AFTER status");
        }
        if (!in_array('tax', $columns)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN tax DECIMAL(10,2) DEFAULT 0 AFTER discount");
        }
        if (!in_array('coupon_code', $columns)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN coupon_code VARCHAR(50) NULL AFTER tax");
        }
        if (!in_array('customer_name', $columns)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN customer_name VARCHAR(100) NULL AFTER user_id");
        }
        if (!in_array('customer_mobile', $columns)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN customer_mobile VARCHAR(15) NULL AFTER customer_name");
        }
        if (!in_array('customer_address', $columns)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN customer_address TEXT NULL AFTER customer_mobile");
        }
        if (!in_array('delivery_date', $columns)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_date DATE NULL AFTER delivery_type");
        }
        if (!in_array('delivery_time', $columns)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_time VARCHAR(50) NULL AFTER delivery_date");
        }
    } catch (Exception $e) {
        // Columns might already exist
    }
}

// === ORDERS ===
if (preg_match('#^/admin/orders(?:/(\d+))?$#', $path, $matches)) {
    $id = $matches[1] ?? null;

    // Ensure schema is up to date
    ensureOrdersSchema($pdo);

    if ($method === 'GET') {
        requireAuth($pdo, $token);

        if ($id) {
            // Use COALESCE to get customer details from orders table first, then from users table
            $stmt = $pdo->prepare("SELECT o.*,
                COALESCE(o.customer_name, u.name) as customer_name,
                COALESCE(o.customer_mobile, u.mobile) as customer_mobile,
                COALESCE(o.customer_address, '') as customer_address,
                u.email as customer_email
                FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
            $stmt->execute([$id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get items
            $itemStmt = $pdo->prepare("SELECT oi.*, p.name as product_name, p.image as product_image FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
            $itemStmt->execute([$id]);
            $order['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

            respond(['success' => true, 'data' => $order]);
        } else {
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 20;
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';

            $offset = ($page - 1) * $limit;
            $where = [];
            $params = [];

            if ($search) {
                $where[] = "(o.order_id LIKE ? OR o.customer_name LIKE ? OR o.customer_mobile LIKE ? OR u.name LIKE ? OR u.mobile LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            if ($status) {
                $where[] = "o.status = ?";
                $params[] = $status;
            }

            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o LEFT JOIN users u ON o.user_id = u.id $whereClause");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT o.*,
                COALESCE(o.customer_name, u.name) as customer_name,
                COALESCE(o.customer_mobile, u.mobile) as customer_mobile,
                (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
                FROM orders o LEFT JOIN users u ON o.user_id = u.id $whereClause ORDER BY o.created_at DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);

            respond([
                'success' => true,
                'data' => [
                    'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                    'pagination' => [
                        'current_page' => (int)$page,
                        'total_pages' => ceil($total / $limit),
                        'total' => (int)$total
                    ]
                ]
            ]);
        }
    }

    if ($method === 'PUT') {
        requireAuth($pdo, $token);

        $id = $input['id'];
        $status = $input['status'];
        $reason = $input['cancelled_reason'] ?? null;

        $stmt = $pdo->prepare("UPDATE orders SET status = ?, cancelled_reason = ? WHERE id = ?");
        $stmt->execute([$status, $reason, $id]);

        respond(['success' => true, 'message' => 'Order updated']);
    }
}

// === CUSTOMERS ===
if (preg_match('#^/admin/customers(?:/(\d+))?$#', $path, $matches)) {
    $id = $matches[1] ?? null;

    if ($method === 'GET') {
        requireAuth($pdo, $token);

        if ($id) {
            $stmt = $pdo->prepare("SELECT u.*,
                (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count,
                (SELECT COALESCE(SUM(total), 0) FROM orders WHERE user_id = u.id) as total_spent
                FROM users u WHERE u.id = ?");
            $stmt->execute([$id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get addresses
            $addrStmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ?");
            $addrStmt->execute([$id]);
            $customer['addresses'] = $addrStmt->fetchAll(PDO::FETCH_ASSOC);

            // Get recent orders
            $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
            $orderStmt->execute([$id]);
            $customer['recent_orders'] = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

            respond(['success' => true, 'data' => $customer]);
        } else {
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 20;
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';

            $offset = ($page - 1) * $limit;
            $where = ["(u.role = 'customer' OR u.role IS NULL)"]; // Only show customers, not admins
            $params = [];

            if ($search) {
                $where[] = "(u.name LIKE ? OR u.mobile LIKE ? OR u.email LIKE ? OR u.address LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            if ($status) {
                // Support both is_active column and status column
                $where[] = "(u.is_active = ? OR u.status = ?)";
                $params[] = $status === 'active' ? 1 : 0;
                $params[] = $status;
            }

            $whereClause = 'WHERE ' . implode(' AND ', $where);

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u $whereClause");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT u.*, u.address as customer_address,
                (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count,
                (SELECT COALESCE(SUM(total), 0) FROM orders WHERE user_id = u.id) as total_spent
                FROM users u $whereClause ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);

            respond([
                'success' => true,
                'data' => [
                    'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                    'pagination' => [
                        'current_page' => (int)$page,
                        'total_pages' => ceil($total / $limit),
                        'total' => (int)$total
                    ]
                ]
            ]);
        }
    }

    if ($method === 'PUT') {
        requireAuth($pdo, $token);

        $id = $input['id'];
        $isActive = $input['is_active'] ?? true;

        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->execute([$isActive ? 1 : 0, $id]);

        respond(['success' => true, 'message' => 'Customer updated']);
    }
}

// === BANNERS SCHEMA MIGRATION ===
// Auto-add missing columns to banners table if they don't exist
function ensureBannersSchema($pdo) {
    // Check existing columns
    $stmt = $pdo->query("DESCRIBE banners");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Add desktop_image if missing
    if (!in_array('desktop_image', $columns)) {
        $pdo->exec("ALTER TABLE banners ADD COLUMN desktop_image VARCHAR(500) AFTER title");
    }

    // Add mobile_image if missing
    if (!in_array('mobile_image', $columns)) {
        $pdo->exec("ALTER TABLE banners ADD COLUMN mobile_image VARCHAR(500) AFTER desktop_image");
    }

    // Add description if missing (use caption data if exists)
    if (!in_array('description', $columns)) {
        if (in_array('caption', $columns)) {
            $pdo->exec("ALTER TABLE banners CHANGE caption description VARCHAR(500)");
        } else {
            $pdo->exec("ALTER TABLE banners ADD COLUMN description VARCHAR(500) AFTER mobile_image");
        }
    }
}

// === PUBLIC BANNERS (for home page) ===
if ($path === '/banners' && $method === 'GET') {
    // Ensure schema is up to date
    ensureBannersSchema($pdo);

    // Public endpoint - returns only active banners for home page display
    $stmt = $pdo->query("SELECT id, title, description, desktop_image, mobile_image, image, redirect_type, redirect_url, display_order, is_active FROM banners WHERE is_active = 1 ORDER BY display_order");
    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalize image fields - use desktop_image/mobile_image or fall back to image
    foreach ($banners as &$banner) {
        if (empty($banner['desktop_image']) && !empty($banner['image'])) {
            $banner['desktop_image'] = $banner['image'];
        }
        if (empty($banner['mobile_image']) && !empty($banner['image'])) {
            $banner['mobile_image'] = $banner['image'];
        }
    }

    respond(['success' => true, 'data' => $banners]);
}

// === ADMIN BANNERS ===
if (preg_match('#^/admin/banners(?:/(\d+))?$#', $path, $matches)) {
    $id = $matches[1] ?? null;

    // Ensure schema is up to date
    ensureBannersSchema($pdo);

    if ($method === 'GET') {
        requireAuth($pdo, $token);

        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM banners WHERE id = ?");
            $stmt->execute([$id]);
            $banner = $stmt->fetch(PDO::FETCH_ASSOC);
            // Normalize image fields
            if ($banner) {
                if (empty($banner['desktop_image']) && !empty($banner['image'])) {
                    $banner['desktop_image'] = $banner['image'];
                }
                if (empty($banner['mobile_image']) && !empty($banner['image'])) {
                    $banner['mobile_image'] = $banner['image'];
                }
            }
            respond(['success' => true, 'data' => $banner]);
        } else {
            $stmt = $pdo->query("SELECT * FROM banners ORDER BY display_order");
            $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Normalize image fields
            foreach ($banners as &$banner) {
                if (empty($banner['desktop_image']) && !empty($banner['image'])) {
                    $banner['desktop_image'] = $banner['image'];
                }
                if (empty($banner['mobile_image']) && !empty($banner['image'])) {
                    $banner['mobile_image'] = $banner['image'];
                }
            }
            respond(['success' => true, 'data' => $banners]);
        }
    }

    if ($method === 'POST') {
        requireAuth($pdo, $token);

        // Check for reorder action
        if (isset($input['action']) && $input['action'] === 'reorder') {
            $order = $input['order'];
            foreach ($order as $index => $bannerId) {
                $pdo->prepare("UPDATE banners SET display_order = ? WHERE id = ?")->execute([$index, $bannerId]);
            }
            respond(['success' => true, 'message' => 'Order updated']);
        }

        // Get max display order
        $stmt = $pdo->query("SELECT COALESCE(MAX(display_order), 0) + 1 FROM banners");
        $nextOrder = $stmt->fetchColumn();

        $stmt = $pdo->prepare("INSERT INTO banners (title, description, desktop_image, mobile_image, image, redirect_type, redirect_url, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $input['title'] ?? '',
            $input['description'] ?? '',
            $input['desktop_image'] ?? '',
            $input['mobile_image'] ?? '',
            $input['desktop_image'] ?? '', // Also set legacy image field
            $input['redirect_type'] ?? 'none',
            $input['redirect_url'] ?? '',
            $nextOrder,
            ($input['is_active'] ?? true) ? 1 : 0
        ]);

        respond(['success' => true, 'message' => 'Banner created', 'data' => ['id' => $pdo->lastInsertId()]]);
    }

    if ($method === 'PUT') {
        requireAuth($pdo, $token);

        $id = $input['id'] ?? $id;
        $stmt = $pdo->prepare("UPDATE banners SET title = ?, description = ?, desktop_image = ?, mobile_image = ?, image = ?, redirect_type = ?, redirect_url = ?, is_active = ? WHERE id = ?");
        $stmt->execute([
            $input['title'] ?? '',
            $input['description'] ?? '',
            $input['desktop_image'] ?? '',
            $input['mobile_image'] ?? '',
            $input['desktop_image'] ?? '', // Also update legacy image field
            $input['redirect_type'] ?? 'none',
            $input['redirect_url'] ?? '',
            ($input['is_active'] ?? true) ? 1 : 0,
            $id
        ]);

        respond(['success' => true, 'message' => 'Banner updated']);
    }

    if ($method === 'DELETE' && $id) {
        requireAuth($pdo, $token);
        $pdo->prepare("DELETE FROM banners WHERE id = ?")->execute([$id]);
        respond(['success' => true, 'message' => 'Banner deleted']);
    }
}

// === PUBLIC POPUPS (for home page) ===
if ($path === '/popups' && $method === 'GET') {
    // Public endpoint - returns only active popups
    $stmt = $pdo->query("SELECT id, title, description, image, coupon_code, display_rule, cta_text, cta_link FROM popups WHERE is_active = 1 AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE()) ORDER BY created_at DESC LIMIT 1");
    $popup = $stmt->fetch(PDO::FETCH_ASSOC);
    respond(['success' => true, 'data' => $popup]);
}

// === ADMIN POPUPS ===
if (preg_match('#^/admin/popups(?:/(\d+))?$#', $path, $matches)) {
    $id = $matches[1] ?? null;

    if ($method === 'GET') {
        requireAuth($pdo, $token);

        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM popups WHERE id = ?");
            $stmt->execute([$id]);
            respond(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        } else {
            $stmt = $pdo->query("SELECT * FROM popups ORDER BY created_at DESC");
            respond(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
    }

    if ($method === 'POST') {
        requireAuth($pdo, $token);

        $stmt = $pdo->prepare("INSERT INTO popups (title, description, image, coupon_code, display_rule, cta_text, cta_link, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $input['title'],
            $input['description'] ?? '',
            $input['image'] ?? '',
            $input['coupon_code'] ?? '',
            $input['display_rule'] ?? 'once_per_session',
            $input['cta_text'] ?? 'Shop Now',
            $input['cta_link'] ?? '',
            ($input['start_date'] ?? '') ?: null,
            ($input['end_date'] ?? '') ?: null,
            ($input['is_active'] ?? true) ? 1 : 0
        ]);

        respond(['success' => true, 'message' => 'Popup created', 'data' => ['id' => $pdo->lastInsertId()]]);
    }

    if ($method === 'PUT') {
        requireAuth($pdo, $token);

        $id = $input['id'] ?? $id;
        $stmt = $pdo->prepare("UPDATE popups SET title = ?, description = ?, image = ?, coupon_code = ?, display_rule = ?, cta_text = ?, cta_link = ?, start_date = ?, end_date = ?, is_active = ? WHERE id = ?");
        $stmt->execute([
            $input['title'],
            $input['description'] ?? '',
            $input['image'] ?? '',
            $input['coupon_code'] ?? '',
            $input['display_rule'] ?? 'once_per_session',
            $input['cta_text'] ?? 'Shop Now',
            $input['cta_link'] ?? '',
            ($input['start_date'] ?? '') ?: null,
            ($input['end_date'] ?? '') ?: null,
            ($input['is_active'] ?? true) ? 1 : 0,
            $id
        ]);

        respond(['success' => true, 'message' => 'Popup updated']);
    }

    if ($method === 'DELETE' && $id) {
        requireAuth($pdo, $token);
        $pdo->prepare("DELETE FROM popups WHERE id = ?")->execute([$id]);
        respond(['success' => true, 'message' => 'Popup deleted']);
    }
}

// === MENU ITEMS SCHEMA ===
function ensureMenuItemsSchema($pdo) {
    // Create menu_items table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        category VARCHAR(100) NOT NULL,
        type ENUM('veg', 'non-veg') DEFAULT 'veg',
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        price2 DECIMAL(10,2) DEFAULT NULL,
        display_order INT DEFAULT 0,
        is_special TINYINT(1) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Create menu_categories table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL UNIQUE,
        icon VARCHAR(100) DEFAULT 'fas fa-utensils',
        display_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

// === MENU CATEGORIES ===
if (preg_match('#^/admin/menu-categories(?:/(\d+))?$#', $path, $matches)) {
    $id = $matches[1] ?? null;

    ensureMenuItemsSchema($pdo);

    if ($method === 'GET') {
        requireAuth($pdo, $token);

        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM menu_categories WHERE id = ?");
            $stmt->execute([$id]);
            respond(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        } else {
            $stmt = $pdo->query("SELECT mc.*, (SELECT COUNT(*) FROM menu_items WHERE category = mc.slug) as item_count FROM menu_categories mc ORDER BY mc.display_order, mc.name");
            respond(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
    }

    if ($method === 'POST') {
        requireAuth($pdo, $token);

        $name = $input['name'] ?? '';
        $slug = $input['slug'] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        $icon = $input['icon'] ?? 'fas fa-utensils';
        $displayOrder = $input['display_order'] ?? 0;

        $stmt = $pdo->prepare("INSERT INTO menu_categories (name, slug, icon, display_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $icon, $displayOrder]);

        respond(['success' => true, 'message' => 'Category created', 'data' => ['id' => $pdo->lastInsertId()]]);
    }

    if ($method === 'PUT') {
        requireAuth($pdo, $token);

        $id = $input['id'] ?? $id;
        $stmt = $pdo->prepare("UPDATE menu_categories SET name = ?, slug = ?, icon = ?, display_order = ?, is_active = ? WHERE id = ?");
        $stmt->execute([
            $input['name'],
            $input['slug'] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $input['name'])),
            $input['icon'] ?? 'fas fa-utensils',
            $input['display_order'] ?? 0,
            ($input['is_active'] ?? true) ? 1 : 0,
            $id
        ]);

        respond(['success' => true, 'message' => 'Category updated']);
    }

    if ($method === 'DELETE' && $id) {
        requireAuth($pdo, $token);

        // Check for items
        $stmt = $pdo->prepare("SELECT slug FROM menu_categories WHERE id = ?");
        $stmt->execute([$id]);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cat) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM menu_items WHERE category = ?");
            $stmt->execute([$cat['slug']]);
            if ($stmt->fetchColumn() > 0) {
                respond(['success' => false, 'message' => 'Cannot delete category with items'], 400);
            }
        }

        $pdo->prepare("DELETE FROM menu_categories WHERE id = ?")->execute([$id]);
        respond(['success' => true, 'message' => 'Category deleted']);
    }
}

// === MENU ITEMS ===
if (preg_match('#^/admin/menu-items(?:/(\d+))?$#', $path, $matches)) {
    $id = $matches[1] ?? null;

    ensureMenuItemsSchema($pdo);

    if ($method === 'GET') {
        requireAuth($pdo, $token);

        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ?");
            $stmt->execute([$id]);
            respond(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        } else {
            $category = $_GET['category'] ?? '';
            $type = $_GET['type'] ?? '';
            $search = $_GET['search'] ?? '';

            $where = [];
            $params = [];

            if ($category) {
                $where[] = "category = ?";
                $params[] = $category;
            }
            if ($type) {
                $where[] = "type = ?";
                $params[] = $type;
            }
            if ($search) {
                $where[] = "name LIKE ?";
                $params[] = "%$search%";
            }

            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $stmt = $pdo->prepare("SELECT * FROM menu_items $whereClause ORDER BY display_order, name");
            $stmt->execute($params);
            respond(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
    }

    if ($method === 'POST') {
        requireAuth($pdo, $token);

        $stmt = $pdo->prepare("INSERT INTO menu_items (name, category, type, description, price, price2, display_order, is_special, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $input['name'],
            $input['category'],
            $input['type'] ?? 'veg',
            $input['description'] ?? '',
            $input['price'],
            $input['price2'] ?: null,
            $input['display_order'] ?? 0,
            ($input['is_special'] ?? false) ? 1 : 0,
            ($input['is_active'] ?? true) ? 1 : 0
        ]);

        respond(['success' => true, 'message' => 'Menu item created', 'data' => ['id' => $pdo->lastInsertId()]]);
    }

    if ($method === 'PUT') {
        requireAuth($pdo, $token);

        $id = $input['id'] ?? $id;
        $stmt = $pdo->prepare("UPDATE menu_items SET name = ?, category = ?, type = ?, description = ?, price = ?, price2 = ?, display_order = ?, is_special = ?, is_active = ? WHERE id = ?");
        $stmt->execute([
            $input['name'],
            $input['category'],
            $input['type'] ?? 'veg',
            $input['description'] ?? '',
            $input['price'],
            $input['price2'] ?: null,
            $input['display_order'] ?? 0,
            ($input['is_special'] ?? false) ? 1 : 0,
            ($input['is_active'] ?? true) ? 1 : 0,
            $id
        ]);

        respond(['success' => true, 'message' => 'Menu item updated']);
    }

    if ($method === 'DELETE' && $id) {
        requireAuth($pdo, $token);
        $pdo->prepare("DELETE FROM menu_items WHERE id = ?")->execute([$id]);
        respond(['success' => true, 'message' => 'Menu item deleted']);
    }
}

// === MENU ITEMS BULK UPDATE ===
if ($path === '/admin/menu-items/bulk-update' && $method === 'PUT') {
    requireAuth($pdo, $token);
    ensureMenuItemsSchema($pdo);

    $items = $input['items'] ?? [];
    $updated = 0;

    foreach ($items as $item) {
        $id = $item['id'] ?? null;
        if (!$id) continue;

        $fields = [];
        $values = [];

        if (isset($item['price'])) {
            $fields[] = 'price = ?';
            $values[] = $item['price'];
        }
        if (isset($item['price2'])) {
            $fields[] = 'price2 = ?';
            $values[] = $item['price2'] ?: null;
        }
        if (isset($item['is_active'])) {
            $fields[] = 'is_active = ?';
            $values[] = $item['is_active'] ? 1 : 0;
        }

        if (!empty($fields)) {
            $values[] = $id;
            $stmt = $pdo->prepare("UPDATE menu_items SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->execute($values);
            $updated++;
        }
    }

    respond(['success' => true, 'message' => "$updated items updated"]);
}

// === PUBLIC MENU (for menu page) ===
if ($path === '/menu' && $method === 'GET') {
    ensureMenuItemsSchema($pdo);

    // Get all active menu items grouped by category
    $stmt = $pdo->query("SELECT * FROM menu_items WHERE is_active = 1 ORDER BY display_order, name");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get categories
    $catStmt = $pdo->query("SELECT * FROM menu_categories WHERE is_active = 1 ORDER BY display_order, name");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    respond([
        'success' => true,
        'data' => [
            'items' => $items,
            'categories' => $categories
        ]
    ]);
}

// === IMAGE UPLOAD ===
if (preg_match('#^/admin/upload(?:/(\w+))?#', $path, $uploadMatches)) {
    if ($method === 'POST') {
        requireAuth($pdo, $token);

        // Get upload type from URL or POST data (product, banner, popup)
        $uploadType = $uploadMatches[1] ?? ($_POST['type'] ?? 'product');
        $validTypes = ['product', 'banner', 'popup'];
        if (!in_array($uploadType, $validTypes)) {
            $uploadType = 'product';
        }

        // Check if file was uploaded
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
                UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
                UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
                UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
            ];
            $error = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
            respond(['success' => false, 'message' => $errorMessages[$error] ?? 'Upload failed'], 400);
        }

        $file = $_FILES['image'];

        // Sanitize original filename - remove any path components
        $originalName = basename($file['name']);

        // Validate file type using MIME type detection
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            respond(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP'], 400);
        }

        // Validate file extension matches MIME type
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($extension, $allowedExtensions)) {
            respond(['success' => false, 'message' => 'Invalid file extension'], 400);
        }

        // Validate file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            respond(['success' => false, 'message' => 'File too large. Maximum 5MB allowed'], 400);
        }

        // Detect environment and set appropriate upload directory
        $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $isHostinger = strpos($serverName, 'skbakers.in') !== false ||
                       strpos($serverName, 'hostinger') !== false;

        if ($isHostinger) {
            // Hostinger production path - public_html is the root
            $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/home/u282002960/public_html';
            $uploadDir = $documentRoot . '/frontend/assets/uploads/';
            $imageUrlBase = '/frontend/assets/uploads/';
        } else {
            // Local XAMPP development path
            $baseDir = realpath(__DIR__ . '/../../frontend/assets');
            if (!$baseDir) {
                $baseDir = __DIR__ . '/../../frontend/assets';
            }
            $uploadDir = $baseDir . '/uploads/';
            $imageUrlBase = '../assets/uploads/';
        }

        // Create uploads directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                respond(['success' => false, 'message' => 'Failed to create upload directory: ' . $uploadDir], 500);
            }
        }

        // Check directory is writable
        if (!is_writable($uploadDir)) {
            respond(['success' => false, 'message' => 'Upload directory is not writable: ' . $uploadDir], 500);
        }

        // Generate unique filename with type prefix for organization
        $uniqueId = time() . '_' . bin2hex(random_bytes(8));
        $filename = $uploadType . '_' . $uniqueId . '.' . $extension;
        $filepath = $uploadDir . $filename;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Return appropriate URL based on environment
            $imageUrl = $imageUrlBase . $filename;

            respond([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'url' => $imageUrl,
                    'filename' => $filename,
                    'type' => $uploadType
                ]
            ]);
        } else {
            respond(['success' => false, 'message' => 'Failed to save uploaded file to: ' . $filepath], 500);
        }
    }
}

// === REVIEWS TABLE MIGRATION ===
function ensureReviewsTable($pdo) {
    try {
        // Check if reviews table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'reviews'");
        if ($stmt->rowCount() === 0) {
            // Create reviews table
            $pdo->exec("
                CREATE TABLE reviews (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    product_id INT NOT NULL,
                    user_id INT NULL,
                    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
                    comment TEXT NULL,
                    reviewer_name VARCHAR(100) NOT NULL,
                    reviewer_email VARCHAR(255) NULL,
                    helpful_count INT DEFAULT 0,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_product_id (product_id),
                    INDEX idx_user_id (user_id),
                    INDEX idx_rating (rating),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            error_log("Created reviews table");
        }
    } catch (Exception $e) {
        error_log("Reviews table migration error: " . $e->getMessage());
    }
}

// === REVIEWS API ===
if (preg_match('#^/reviews(?:/(\d+))?$#', $path, $matches) || $path === '/reviews/helpful') {
    // Ensure reviews table exists
    ensureReviewsTable($pdo);

    $reviewId = $matches[1] ?? null;

    // GET /reviews?product_id={id} - Get reviews for a product
    if ($method === 'GET') {
        $productId = $_GET['product_id'] ?? null;

        if (!$productId) {
            respond(['success' => false, 'message' => 'Product ID is required'], 400);
        }

        // Get reviews for product
        $stmt = $pdo->prepare("
            SELECT r.*,
                   DATE_FORMAT(r.created_at, '%Y-%m-%d') as review_date
            FROM reviews r
            WHERE r.product_id = ? AND r.is_active = 1
            ORDER BY r.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$productId]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get rating statistics
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total_reviews,
                COALESCE(AVG(rating), 0) as avg_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1
            FROM reviews
            WHERE product_id = ? AND is_active = 1
        ");
        $stmt->execute([$productId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Format time ago for each review
        foreach ($reviews as &$review) {
            $review['time_ago'] = getTimeAgo($review['created_at']);
        }

        respond([
            'success' => true,
            'data' => [
                'reviews' => $reviews,
                'stats' => [
                    'total_reviews' => (int)$stats['total_reviews'],
                    'avg_rating' => round((float)$stats['avg_rating'], 1),
                    'rating_distribution' => [
                        5 => (int)$stats['rating_5'],
                        4 => (int)$stats['rating_4'],
                        3 => (int)$stats['rating_3'],
                        2 => (int)$stats['rating_2'],
                        1 => (int)$stats['rating_1']
                    ]
                ]
            ]
        ]);
    }

    // POST /reviews - Submit a new review
    if ($method === 'POST' && $path === '/reviews') {
        $productId = $input['product_id'] ?? null;
        $rating = $input['rating'] ?? null;
        $comment = $input['comment'] ?? '';
        $reviewerName = $input['reviewer_name'] ?? '';
        $reviewerEmail = $input['reviewer_email'] ?? null;
        $userId = $input['user_id'] ?? null;

        // Validate required fields
        if (!$productId) {
            respond(['success' => false, 'message' => 'Product ID is required'], 400);
        }
        if (!$rating || $rating < 1 || $rating > 5) {
            respond(['success' => false, 'message' => 'Rating must be between 1 and 5'], 400);
        }
        if (empty(trim($reviewerName))) {
            respond(['success' => false, 'message' => 'Reviewer name is required'], 400);
        }
        if (strlen(trim($comment)) < 5) {
            respond(['success' => false, 'message' => 'Comment must be at least 5 characters'], 400);
        }

        // Check if product exists
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        if (!$stmt->fetch()) {
            respond(['success' => false, 'message' => 'Product not found'], 404);
        }

        try {
            $pdo->beginTransaction();

            // Insert review
            $stmt = $pdo->prepare("
                INSERT INTO reviews (product_id, user_id, rating, comment, reviewer_name, reviewer_email)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $productId,
                $userId,
                $rating,
                trim($comment),
                trim($reviewerName),
                $reviewerEmail
            ]);
            $reviewId = $pdo->lastInsertId();

            // Update product average rating and review count
            $stmt = $pdo->prepare("
                UPDATE products
                SET rating = (
                    SELECT COALESCE(AVG(rating), 0)
                    FROM reviews
                    WHERE product_id = ? AND is_active = 1
                ),
                reviews = (
                    SELECT COUNT(*)
                    FROM reviews
                    WHERE product_id = ? AND is_active = 1
                )
                WHERE id = ?
            ");
            $stmt->execute([$productId, $productId, $productId]);

            $pdo->commit();

            respond([
                'success' => true,
                'message' => 'Review submitted successfully',
                'data' => ['review_id' => $reviewId]
            ], 201);

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Review submission error: " . $e->getMessage());
            respond(['success' => false, 'message' => 'Failed to submit review'], 500);
        }
    }

    // PUT /reviews/helpful - Mark a review as helpful
    if ($method === 'PUT' && $path === '/reviews/helpful') {
        $reviewId = $input['review_id'] ?? null;

        if (!$reviewId) {
            respond(['success' => false, 'message' => 'Review ID is required'], 400);
        }

        $stmt = $pdo->prepare("UPDATE reviews SET helpful_count = helpful_count + 1 WHERE id = ? AND is_active = 1");
        $stmt->execute([$reviewId]);

        if ($stmt->rowCount() > 0) {
            // Get updated count
            $stmt = $pdo->prepare("SELECT helpful_count FROM reviews WHERE id = ?");
            $stmt->execute([$reviewId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            respond([
                'success' => true,
                'message' => 'Marked as helpful',
                'data' => ['helpful_count' => (int)$result['helpful_count']]
            ]);
        } else {
            respond(['success' => false, 'message' => 'Review not found'], 404);
        }
    }

    // DELETE /reviews/{id} - Delete a review (admin only)
    if ($method === 'DELETE' && $reviewId) {
        requireAuth($pdo, $token);

        $stmt = $pdo->prepare("SELECT product_id FROM reviews WHERE id = ?");
        $stmt->execute([$reviewId]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$review) {
            respond(['success' => false, 'message' => 'Review not found'], 404);
        }

        $pdo->beginTransaction();

        // Delete review
        $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([$reviewId]);

        // Update product stats
        $productId = $review['product_id'];
        $stmt = $pdo->prepare("
            UPDATE products
            SET rating = COALESCE((
                SELECT AVG(rating) FROM reviews WHERE product_id = ? AND is_active = 1
            ), 0),
            reviews = (
                SELECT COUNT(*) FROM reviews WHERE product_id = ? AND is_active = 1
            )
            WHERE id = ?
        ");
        $stmt->execute([$productId, $productId, $productId]);

        $pdo->commit();

        respond(['success' => true, 'message' => 'Review deleted']);
    }
}

// Helper function to format time ago
function getTimeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 6) return floor($diff->d / 7) . ' week' . (floor($diff->d / 7) > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

// 404 for unmatched routes
respond(['success' => false, 'message' => 'Not found: ' . $path], 404);
