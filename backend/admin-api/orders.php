<?php
/**
 * Admin Orders API
 * Order management and status updates
 * SK Bakers Admin Panel
 */

require_once 'middleware.php';

setCORSHeaders();

// Initialize storage
initStorage();
$sessionToken = getSessionToken();
$admin = requireAuth($GLOBALS['db'], $sessionToken);

$method = $_SERVER['REQUEST_METHOD'];

// File-based storage
define('DATA_DIR', __DIR__ . '/../data/');
define('ORDERS_FILE', DATA_DIR . 'orders.json');
define('USERS_FILE', DATA_DIR . 'users.json');

function loadOrders() {
    if (file_exists(ORDERS_FILE)) {
        $data = json_decode(file_get_contents(ORDERS_FILE), true);
        return $data ?: [];
    }
    return [];
}

function saveOrders($orders) {
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0777, true);
    }
    file_put_contents(ORDERS_FILE, json_encode(array_values($orders), JSON_PRETTY_PRINT));
}

function loadUsers() {
    if (file_exists(USERS_FILE)) {
        $data = json_decode(file_get_contents(USERS_FILE), true);
        return $data ?: [];
    }
    return [];
}

function getUserInfo($users, $userId) {
    foreach ($users as $u) {
        if ($u['id'] == $userId) {
            return [
                'customer_name' => $u['name'] ?? 'Guest',
                'customer_mobile' => $u['mobile'] ?? '',
                'customer_email' => $u['email'] ?? ''
            ];
        }
    }
    return ['customer_name' => 'Guest', 'customer_mobile' => '', 'customer_email' => ''];
}

try {
    switch ($method) {
        case 'GET':
            handleGet();
            break;
        case 'PUT':
            handlePut($admin);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Admin Orders API Error: " . $e->getMessage());
    Response::serverError('Operation failed');
}

/**
 * GET - List orders or get single order
 */
function handleGet() {
    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        // Single order with details
        if (isset($_GET['id'])) {
            $stmt = $db->prepare("
                SELECT o.*,
                       u.name as customer_name, u.mobile as customer_mobile, u.email as customer_email,
                       a.street as shipping_street, a.landmark as shipping_landmark,
                       a.city as shipping_city, a.state as shipping_state, a.pincode as shipping_pincode,
                       a.name as shipping_name, a.mobile as shipping_mobile, a.type as address_type
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                LEFT JOIN addresses a ON o.address_id = a.id
                WHERE o.id = :id
            ");
            $stmt->execute([':id' => $_GET['id']]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                Response::notFound('Order not found');
            }

            // Get order items
            $stmt = $db->prepare("
                SELECT oi.*, p.image as product_image
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = :order_id
            ");
            $stmt->execute([':order_id' => $order['id']]);
            $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get order timeline (from activity logs)
            $stmt = $db->prepare("
                SELECT action, new_values, created_at, a.name as admin_name
                FROM admin_activity_logs l
                LEFT JOIN admins a ON l.admin_id = a.id
                WHERE entity_type = 'order' AND entity_id = :order_id
                ORDER BY created_at ASC
            ");
            $stmt->execute([':order_id' => $order['id']]);
            $order['timeline'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::success($order);
        }

        // List orders with pagination and filters
        $pagination = getPagination(20);
        $where = [];
        $params = [];

        // Filter by status
        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $where[] = "o.status = :status";
            $params[':status'] = $_GET['status'];
        }

        // Filter by payment status
        if (isset($_GET['payment_status']) && $_GET['payment_status'] !== '') {
            $where[] = "o.payment_status = :payment_status";
            $params[':payment_status'] = $_GET['payment_status'];
        }

        // Filter by date range
        if (isset($_GET['from_date']) && $_GET['from_date'] !== '') {
            $where[] = "DATE(o.created_at) >= :from_date";
            $params[':from_date'] = $_GET['from_date'];
        }

        if (isset($_GET['to_date']) && $_GET['to_date'] !== '') {
            $where[] = "DATE(o.created_at) <= :to_date";
            $params[':to_date'] = $_GET['to_date'];
        }

        // Filter by today's orders
        if (isset($_GET['today']) && $_GET['today'] === '1') {
            $where[] = "DATE(o.created_at) = CURDATE()";
        }

        // Search by order_id or customer name/mobile
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $where[] = "(o.order_id LIKE :search OR u.name LIKE :search OR u.mobile LIKE :search)";
            $params[':search'] = '%' . $_GET['search'] . '%';
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        // Get total count
        $stmt = $db->prepare("
            SELECT COUNT(*) as total
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            $whereClause
        ");
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Get orders
        $orderBy = isset($_GET['sort']) ? $_GET['sort'] : 'o.created_at DESC';
        $allowedSorts = ['o.created_at ASC', 'o.created_at DESC', 'o.total ASC', 'o.total DESC', 'o.status ASC', 'o.status DESC'];
        if (!in_array($orderBy, $allowedSorts)) {
            $orderBy = 'o.created_at DESC';
        }

        $stmt = $db->prepare("
            SELECT o.*,
                   u.name as customer_name, u.mobile as customer_mobile,
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            $whereClause
            ORDER BY $orderBy
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success(paginatedResponse($orders, $total, $pagination));
    } else {
        // File-based storage
        $orders = loadOrders();
        $users = loadUsers();

        // Add customer info to orders
        foreach ($orders as &$order) {
            $userInfo = getUserInfo($users, $order['user_id'] ?? 0);
            $order = array_merge($order, $userInfo);
            $order['item_count'] = isset($order['items']) ? count($order['items']) : 0;
        }

        // Single order
        if (isset($_GET['id'])) {
            foreach ($orders as $o) {
                if ($o['id'] == $_GET['id']) {
                    $o['timeline'] = []; // No timeline in file-based storage
                    Response::success($o);
                }
            }
            Response::notFound('Order not found');
        }

        // Apply filters
        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $status = $_GET['status'];
            $orders = array_filter($orders, function($o) use ($status) {
                return isset($o['status']) && $o['status'] === $status;
            });
        }

        if (isset($_GET['payment_status']) && $_GET['payment_status'] !== '') {
            $paymentStatus = $_GET['payment_status'];
            $orders = array_filter($orders, function($o) use ($paymentStatus) {
                return isset($o['payment_status']) && $o['payment_status'] === $paymentStatus;
            });
        }

        if (isset($_GET['from_date']) && $_GET['from_date'] !== '') {
            $fromDate = $_GET['from_date'];
            $orders = array_filter($orders, function($o) use ($fromDate) {
                return isset($o['created_at']) && substr($o['created_at'], 0, 10) >= $fromDate;
            });
        }

        if (isset($_GET['to_date']) && $_GET['to_date'] !== '') {
            $toDate = $_GET['to_date'];
            $orders = array_filter($orders, function($o) use ($toDate) {
                return isset($o['created_at']) && substr($o['created_at'], 0, 10) <= $toDate;
            });
        }

        if (isset($_GET['today']) && $_GET['today'] === '1') {
            $today = date('Y-m-d');
            $orders = array_filter($orders, function($o) use ($today) {
                return isset($o['created_at']) && strpos($o['created_at'], $today) === 0;
            });
        }

        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = strtolower($_GET['search']);
            $orders = array_filter($orders, function($o) use ($search) {
                $orderId = strtolower($o['order_id'] ?? '');
                $name = strtolower($o['customer_name'] ?? '');
                $mobile = strtolower($o['customer_mobile'] ?? '');
                return strpos($orderId, $search) !== false ||
                       strpos($name, $search) !== false ||
                       strpos($mobile, $search) !== false;
            });
        }

        // Sort by created_at desc
        usort($orders, function($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });

        // Pagination
        $total = count($orders);
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
        $offset = ($page - 1) * $limit;

        $orders = array_slice(array_values($orders), $offset, $limit);

        $pagination = [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ];

        Response::success([
            'data' => $orders,
            'pagination' => $pagination
        ]);
    }
}

/**
 * PUT - Update order status
 */
function handlePut($admin) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id'])) {
        Response::error('Order ID is required');
    }

    // Validate status if provided
    if (isset($input['status'])) {
        $validStatuses = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];
        if (!in_array($input['status'], $validStatuses)) {
            Response::error('Invalid status');
        }
    }

    // Validate payment_status if provided
    if (isset($input['payment_status'])) {
        $validPaymentStatuses = ['pending', 'paid', 'failed', 'refunded'];
        if (!in_array($input['payment_status'], $validPaymentStatuses)) {
            Response::error('Invalid payment status');
        }
    }

    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        // Get existing order
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = :id");
        $stmt->execute([':id' => $input['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            Response::notFound('Order not found');
        }

        // Build update query
        $allowedFields = ['status', 'payment_status', 'notes'];
        $updates = [];
        $params = [':id' => $input['id']];
        $newValues = [];

        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $value = $input[$field];
                $updates[] = "$field = :$field";
                $params[":$field"] = $value;
                $newValues[$field] = $value;
            }
        }

        // Handle cancellation
        if (isset($input['status']) && $input['status'] === 'cancelled') {
            // Staff can only cancel pending orders
            if ($admin['role'] === 'staff' && !in_array($existing['status'], ['pending', 'confirmed'])) {
                Response::error('Staff can only cancel pending or confirmed orders', 403);
            }

            $updates[] = "cancelled_at = NOW()";
            if (isset($input['cancelled_reason'])) {
                $updates[] = "cancelled_reason = :cancelled_reason";
                $params[':cancelled_reason'] = $input['cancelled_reason'];
                $newValues['cancelled_reason'] = $input['cancelled_reason'];
            }
        }

        if (empty($updates)) {
            Response::error('No fields to update');
        }

        // Validate status transition
        if (isset($input['status'])) {
            // Define valid transitions
            $validTransitions = [
                'pending' => ['confirmed', 'cancelled'],
                'confirmed' => ['preparing', 'cancelled'],
                'preparing' => ['out_for_delivery', 'cancelled'],
                'out_for_delivery' => ['delivered', 'cancelled'],
                'delivered' => [], // No further transitions
                'cancelled' => [] // No further transitions
            ];

            if (!in_array($input['status'], $validTransitions[$existing['status']]) && $input['status'] !== $existing['status']) {
                Response::error("Cannot change status from {$existing['status']} to {$input['status']}");
            }
        }

        $sql = "UPDATE orders SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        logAdminActivity($db, $admin['id'], 'update', 'order', $input['id'], $existing, $newValues);

        // Fetch updated order
        $stmt = $db->prepare("
            SELECT o.*, u.name as customer_name, u.mobile as customer_mobile
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id = :id
        ");
        $stmt->execute([':id' => $input['id']]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        Response::success($order, 'Order updated successfully');
    } else {
        // File-based storage
        $orders = loadOrders();
        $users = loadUsers();
        $found = false;

        foreach ($orders as &$order) {
            if ($order['id'] == $input['id']) {
                $found = true;

                // Validate status transition
                if (isset($input['status'])) {
                    $currentStatus = $order['status'] ?? 'pending';
                    $validTransitions = [
                        'pending' => ['confirmed', 'cancelled'],
                        'confirmed' => ['preparing', 'cancelled'],
                        'preparing' => ['out_for_delivery', 'cancelled'],
                        'out_for_delivery' => ['delivered', 'cancelled'],
                        'delivered' => [],
                        'cancelled' => []
                    ];

                    if (!in_array($input['status'], $validTransitions[$currentStatus]) && $input['status'] !== $currentStatus) {
                        Response::error("Cannot change status from {$currentStatus} to {$input['status']}");
                    }

                    // Staff can only cancel pending orders
                    if ($input['status'] === 'cancelled' && $admin['role'] === 'staff' && !in_array($currentStatus, ['pending', 'confirmed'])) {
                        Response::error('Staff can only cancel pending or confirmed orders', 403);
                    }
                }

                // Update fields
                if (isset($input['status'])) {
                    $order['status'] = $input['status'];
                    if ($input['status'] === 'cancelled') {
                        $order['cancelled_at'] = date('Y-m-d H:i:s');
                        if (isset($input['cancelled_reason'])) {
                            $order['cancelled_reason'] = $input['cancelled_reason'];
                        }
                    }
                }
                if (isset($input['payment_status'])) $order['payment_status'] = $input['payment_status'];
                if (isset($input['notes'])) $order['notes'] = $input['notes'];
                $order['updated_at'] = date('Y-m-d H:i:s');

                // Add customer info
                $userInfo = getUserInfo($users, $order['user_id'] ?? 0);
                $order = array_merge($order, $userInfo);

                saveOrders($orders);
                Response::success($order, 'Order updated successfully');
            }
        }

        if (!$found) {
            Response::notFound('Order not found');
        }
    }
}
?>
