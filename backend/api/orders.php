<?php
/**
 * Orders API
 * Handle all order-related requests
 */

// Error handling - catch all errors
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Custom error handler
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/db.php';
require_once '../utils/Response.php';
require_once '../utils/Validator.php';

try {
    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];

    switch($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            handlePost($db);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Orders API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
    exit;
}

/**
 * Handle GET requests
 */
function handleGet($db) {
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';

    switch($action) {
        case 'list':
            getOrders($db);
            break;
        case 'detail':
            getOrderDetail($db);
            break;
        default:
            Response::error('Invalid action');
    }
}

/**
 * Handle POST requests
 */
function handlePost($db) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        Response::error('Invalid JSON data');
    }

    $action = isset($input['action']) ? $input['action'] : 'create';

    switch($action) {
        case 'create':
            createOrder($db, $input);
            break;
        default:
            Response::error('Invalid action');
    }
}

/**
 * Get orders list
 */
function getOrders($db) {
    $userId = isset($_GET['user_id']) ? $_GET['user_id'] : null;

    if (!$userId) {
        Response::error('User ID is required');
    }

    // Customer details are stored directly in orders table (no address_id join needed)
    $stmt = $db->prepare("
        SELECT o.*
        FROM orders o
        WHERE o.user_id = :user_id
        ORDER BY o.created_at DESC
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $orders = $stmt->fetchAll();

    // Get order items for each order
    foreach ($orders as &$order) {
        $stmt = $db->prepare("
            SELECT * FROM order_items WHERE order_id = :order_id
        ");
        $stmt->bindParam(':order_id', $order['id']);
        $stmt->execute();
        $order['items'] = $stmt->fetchAll();
    }

    Response::success($orders);
}

/**
 * Get order detail
 */
function getOrderDetail($db) {
    if (!isset($_GET['order_id'])) {
        Response::error('Order ID is required');
    }

    $orderId = $_GET['order_id'];

    // Customer details are stored directly in orders table
    // Only join users table for additional user info if needed
    $stmt = $db->prepare("
        SELECT o.*, u.name as user_name, u.mobile as user_mobile, u.email as user_email
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.order_id = :order_id
    ");
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    $order = $stmt->fetch();

    if (!$order) {
        Response::notFound('Order not found');
    }

    // Get order items
    $stmt = $db->prepare("
        SELECT * FROM order_items WHERE order_id = :order_id
    ");
    $stmt->bindParam(':order_id', $order['id']);
    $stmt->execute();
    $order['items'] = $stmt->fetchAll();

    Response::success($order);
}

/**
 * Create new order
 */
function createOrder($db, $data) {
    // Support both 'cart' and 'items' keys for backwards compatibility
    $cartItems = isset($data['items']) ? $data['items'] : (isset($data['cart']) ? $data['cart'] : null);

    // Validate required fields - customer details
    $hasCustomerDetails = !empty($data['customer_name']) && !empty($data['customer_mobile']) && !empty($data['customer_address']);

    if (!$hasCustomerDetails) {
        Response::error('Customer details (name, mobile, address) are required');
    }

    // Validate payment method and totals
    if (!isset($data['payment_method']) || empty($data['payment_method'])) {
        Response::error('Payment method is required');
    }
    if (!isset($data['total'])) {
        Response::error('Total is required');
    }

    // Validate cart is not empty
    if (!is_array($cartItems) || count($cartItems) === 0) {
        Response::error('Cart is empty');
    }

    try {
        // IMPORTANT: Run all schema migrations BEFORE starting transaction
        // ALTER TABLE causes implicit commit in MySQL which breaks transactions
        ensureOrdersTableColumns($db);
        ensureUsersTableColumns($db);

        // Begin transaction AFTER schema migrations
        $db->beginTransaction();

        // Generate order ID
        $orderId = 'SKB-' . strtoupper(substr(uniqid(), -6));

        // Get customer details
        $customerName = isset($data['customer_name']) ? trim($data['customer_name']) : null;
        $customerMobile = isset($data['customer_mobile']) ? trim($data['customer_mobile']) : null;
        $customerAddress = isset($data['customer_address']) ? trim($data['customer_address']) : null;

        // Get or create user based on mobile number
        $userId = null;
        if (isset($data['user_id']) && $data['user_id']) {
            $userId = $data['user_id'];
        }

        // Auto-create customer in users table if mobile provided
        if ($customerMobile) {

            // Check if user with this mobile already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE mobile = :mobile LIMIT 1");
            $stmt->execute([':mobile' => $customerMobile]);
            $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingUser) {
                // Update existing user with latest info
                $userId = $existingUser['id'];
                $stmt = $db->prepare("UPDATE users SET name = :name, address = :address, updated_at = NOW() WHERE id = :id");
                $stmt->execute([
                    ':name' => $customerName,
                    ':address' => $customerAddress,
                    ':id' => $userId
                ]);
            } else {
                // Create new user/customer
                $stmt = $db->prepare("
                    INSERT INTO users (name, mobile, address, role, status, is_active, created_at)
                    VALUES (:name, :mobile, :address, 'customer', 'active', 1, NOW())
                ");
                $stmt->execute([
                    ':name' => $customerName,
                    ':mobile' => $customerMobile,
                    ':address' => $customerAddress
                ]);
                $userId = $db->lastInsertId();
            }
        }
        $deliveryDate = isset($data['delivery_date']) ? $data['delivery_date'] : null;
        $deliveryTime = isset($data['delivery_time']) ? $data['delivery_time'] : null;

        // Insert order with customer details (address_id removed - not in table schema)
        $stmt = $db->prepare("
            INSERT INTO orders (
                order_id, user_id, customer_name, customer_mobile, customer_address,
                subtotal, delivery_charge, tax, discount, coupon_code,
                total, payment_method, delivery_type, delivery_date, delivery_time, status, created_at
            ) VALUES (
                :order_id, :user_id, :customer_name, :customer_mobile, :customer_address,
                :subtotal, :delivery_charge, :tax, :discount, :coupon_code,
                :total, :payment_method, :delivery_type, :delivery_date, :delivery_time, 'pending', NOW()
            )
        ");

        $stmt->execute([
            ':order_id' => $orderId,
            ':user_id' => $userId,
            ':customer_name' => $customerName,
            ':customer_mobile' => $customerMobile,
            ':customer_address' => $customerAddress,
            ':subtotal' => $data['subtotal'] ?? 0,
            ':delivery_charge' => $data['delivery_charge'] ?? 0,
            ':tax' => $data['tax'] ?? 0,
            ':discount' => $data['discount'] ?? 0,
            ':coupon_code' => $data['coupon_code'] ?? null,
            ':total' => $data['total'],
            ':payment_method' => $data['payment_method'],
            ':delivery_type' => $data['delivery_type'] ?? 'delivery',
            ':delivery_date' => $deliveryDate,
            ':delivery_time' => $deliveryTime
        ]);

        $orderDbId = $db->lastInsertId();

        // Insert order items
        $stmt = $db->prepare("
            INSERT INTO order_items (
                order_id, product_id, product_name, quantity, price, subtotal
            ) VALUES (
                :order_id, :product_id, :product_name, :quantity, :price, :subtotal
            )
        ");

        foreach ($cartItems as $item) {
            // Support both key formats
            $productId = isset($item['product_id']) ? $item['product_id'] : (isset($item['id']) ? $item['id'] : 0);
            $productName = isset($item['product_name']) ? $item['product_name'] : (isset($item['name']) ? $item['name'] : 'Unknown');
            $quantity = isset($item['quantity']) ? $item['quantity'] : 1;
            $price = isset($item['price']) ? $item['price'] : 0;
            $itemSubtotal = isset($item['subtotal']) ? $item['subtotal'] : ($price * $quantity);

            $stmt->execute([
                ':order_id' => $orderDbId,
                ':product_id' => $productId,
                ':product_name' => $productName,
                ':quantity' => $quantity,
                ':price' => $price,
                ':subtotal' => $itemSubtotal
            ]);
        }

        // Commit transaction
        $db->commit();

        Response::success([
            'order_id' => $orderId,
            'message' => 'Order placed successfully'
        ], 'Order created successfully', 201);

    } catch (Exception $e) {
        // Rollback on error if transaction is active
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Order Creation Error: " . $e->getMessage());
        Response::serverError('Failed to create order: ' . $e->getMessage());
    }
}

/**
 * Ensure orders table has all required columns (auto-migration)
 */
function ensureOrdersTableColumns($db) {
    try {
        // Get current columns
        $stmt = $db->query("SHOW COLUMNS FROM orders");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Add customer_name if missing
        if (!in_array('customer_name', $columns)) {
            $db->exec("ALTER TABLE orders ADD COLUMN customer_name VARCHAR(100) NULL AFTER user_id");
            error_log("Added customer_name column");
        }

        // Add customer_mobile if missing
        if (!in_array('customer_mobile', $columns)) {
            $db->exec("ALTER TABLE orders ADD COLUMN customer_mobile VARCHAR(15) NULL AFTER customer_name");
            error_log("Added customer_mobile column");
        }

        // Add customer_address if missing
        if (!in_array('customer_address', $columns)) {
            $db->exec("ALTER TABLE orders ADD COLUMN customer_address TEXT NULL AFTER customer_mobile");
            error_log("Added customer_address column");
        }

        // Add subtotal column if missing
        if (!in_array('subtotal', $columns)) {
            $db->exec("ALTER TABLE orders ADD COLUMN subtotal DECIMAL(10,2) DEFAULT 0 AFTER customer_address");
            error_log("Added subtotal column");
        }

        // Add delivery_charge column if missing
        if (!in_array('delivery_charge', $columns)) {
            $db->exec("ALTER TABLE orders ADD COLUMN delivery_charge DECIMAL(10,2) DEFAULT 0 AFTER subtotal");
            error_log("Added delivery_charge column");
        }

        // Add discount column if missing
        if (!in_array('discount', $columns)) {
            $db->exec("ALTER TABLE orders ADD COLUMN discount DECIMAL(10,2) DEFAULT 0 AFTER delivery_charge");
            error_log("Added discount column");
        }

        // Add tax column if missing
        if (!in_array('tax', $columns)) {
            $db->exec("ALTER TABLE orders ADD COLUMN tax DECIMAL(10,2) DEFAULT 0 AFTER discount");
            error_log("Added tax column");
        }

        // Add coupon_code column if missing
        if (!in_array('coupon_code', $columns)) {
            $db->exec("ALTER TABLE orders ADD COLUMN coupon_code VARCHAR(50) NULL AFTER tax");
            error_log("Added coupon_code column");
        }

        // Add delivery_date column if missing
        if (!in_array('delivery_date', $columns)) {
            $db->exec("ALTER TABLE orders ADD COLUMN delivery_date DATE NULL AFTER delivery_type");
            error_log("Added delivery_date column");
        }

        // Add delivery_time column if missing
        if (!in_array('delivery_time', $columns)) {
            $db->exec("ALTER TABLE orders ADD COLUMN delivery_time VARCHAR(50) NULL AFTER delivery_date");
            error_log("Added delivery_time column");
        }

    } catch (Exception $e) {
        error_log("Column migration error: " . $e->getMessage());
        // Continue anyway - columns might already exist
    }
}

/**
 * Ensure users table has all required columns for customer data (auto-migration)
 */
function ensureUsersTableColumns($db) {
    try {
        // Get current columns
        $stmt = $db->query("SHOW COLUMNS FROM users");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Add address column if missing
        if (!in_array('address', $columns)) {
            $db->exec("ALTER TABLE users ADD COLUMN address TEXT NULL AFTER mobile");
            error_log("Added address column to users table");
        }

        // Add role column if missing
        if (!in_array('role', $columns)) {
            $db->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'customer' AFTER address");
            error_log("Added role column to users table");
        }

        // Add status column if missing
        if (!in_array('status', $columns)) {
            $db->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active' AFTER role");
            error_log("Added status column to users table");
        }

        // Add is_active column if missing
        if (!in_array('is_active', $columns)) {
            $db->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER status");
            error_log("Added is_active column to users table");
        }

        // Add updated_at column if missing
        if (!in_array('updated_at', $columns)) {
            $db->exec("ALTER TABLE users ADD COLUMN updated_at DATETIME NULL AFTER created_at");
            error_log("Added updated_at column to users table");
        }

    } catch (Exception $e) {
        error_log("Users column migration error: " . $e->getMessage());
        // Continue anyway - columns might already exist
    }
}
?>
