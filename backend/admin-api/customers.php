<?php
/**
 * Admin Customers API
 * Customer management
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
define('USERS_FILE', DATA_DIR . 'users.json');
define('ORDERS_FILE', DATA_DIR . 'orders.json');
define('ADDRESSES_FILE', DATA_DIR . 'addresses.json');

function loadUsers() {
    if (file_exists(USERS_FILE)) {
        $data = json_decode(file_get_contents(USERS_FILE), true);
        return $data ?: [];
    }
    return [];
}

function saveUsers($users) {
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0777, true);
    }
    file_put_contents(USERS_FILE, json_encode(array_values($users), JSON_PRETTY_PRINT));
}

function loadOrders() {
    if (file_exists(ORDERS_FILE)) {
        $data = json_decode(file_get_contents(ORDERS_FILE), true);
        return $data ?: [];
    }
    return [];
}

function loadAddresses() {
    if (file_exists(ADDRESSES_FILE)) {
        $data = json_decode(file_get_contents(ADDRESSES_FILE), true);
        return $data ?: [];
    }
    return [];
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
    error_log("Admin Customers API Error: " . $e->getMessage());
    Response::serverError('Operation failed');
}

/**
 * GET - List customers or get single customer
 */
function handleGet() {
    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        // Single customer with details
        if (isset($_GET['id'])) {
            $stmt = $db->prepare("
                SELECT u.*,
                       (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count,
                       (SELECT COALESCE(SUM(total), 0) FROM orders WHERE user_id = u.id AND status != 'cancelled') as total_spent
                FROM users u
                WHERE u.id = :id
            ");
            $stmt->execute([':id' => $_GET['id']]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                Response::notFound('Customer not found');
            }

            // Get addresses
            $stmt = $db->prepare("SELECT * FROM addresses WHERE user_id = :user_id ORDER BY is_default DESC");
            $stmt->execute([':user_id' => $customer['id']]);
            $customer['addresses'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get recent orders
            $stmt = $db->prepare("
                SELECT id, order_id, total, status, created_at
                FROM orders
                WHERE user_id = :user_id
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $stmt->execute([':user_id' => $customer['id']]);
            $customer['recent_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Remove sensitive data
            unset($customer['password']);
            unset($customer['otp']);
            unset($customer['otp_expires']);

            Response::success($customer);
        }

        // List customers with pagination and filters
        $pagination = getPagination(20);
        $where = ["u.is_guest = 0"];
        $params = [];

        // Filter by status
        if (isset($_GET['status'])) {
            $where[] = "u.is_active = :status";
            $params[':status'] = $_GET['status'] === 'active' ? 1 : 0;
        }

        // Search
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $where[] = "(u.name LIKE :search OR u.mobile LIKE :search OR u.email LIKE :search)";
            $params[':search'] = '%' . $_GET['search'] . '%';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Get total count
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM users u $whereClause");
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Get customers
        $stmt = $db->prepare("
            SELECT u.*,
                   (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count,
                   (SELECT COALESCE(SUM(total), 0) FROM orders WHERE user_id = u.id AND status != 'cancelled') as total_spent
            FROM users u
            $whereClause
            ORDER BY u.created_at DESC
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Remove sensitive data
        foreach ($customers as &$c) {
            unset($c['password']);
            unset($c['otp']);
            unset($c['otp_expires']);
        }

        Response::success(paginatedResponse($customers, $total, $pagination));
    } else {
        // File-based storage
        $users = loadUsers();
        $orders = loadOrders();
        $addresses = loadAddresses();

        // Filter out guests and add order stats
        $customers = [];
        foreach ($users as $user) {
            if (!empty($user['is_guest'])) continue;

            // Calculate order stats
            $orderCount = 0;
            $totalSpent = 0;
            $recentOrders = [];

            foreach ($orders as $order) {
                if (isset($order['user_id']) && $order['user_id'] == $user['id']) {
                    $orderCount++;
                    if (!isset($order['status']) || $order['status'] !== 'cancelled') {
                        $totalSpent += ($order['total'] ?? 0);
                    }
                    $recentOrders[] = $order;
                }
            }

            // Sort recent orders and take top 5
            usort($recentOrders, function($a, $b) {
                return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
            });
            $recentOrders = array_slice($recentOrders, 0, 5);

            // Get user addresses
            $userAddresses = array_filter($addresses, function($addr) use ($user) {
                return isset($addr['user_id']) && $addr['user_id'] == $user['id'];
            });

            $user['order_count'] = $orderCount;
            $user['total_spent'] = $totalSpent;
            $user['recent_orders'] = $recentOrders;
            $user['addresses'] = array_values($userAddresses);

            // Remove sensitive data
            unset($user['password']);
            unset($user['otp']);
            unset($user['otp_expires']);

            $customers[] = $user;
        }

        // Single customer
        if (isset($_GET['id'])) {
            foreach ($customers as $c) {
                if ($c['id'] == $_GET['id']) {
                    Response::success($c);
                }
            }
            Response::notFound('Customer not found');
        }

        // Apply filters
        if (isset($_GET['status'])) {
            $isActive = $_GET['status'] === 'active' ? 1 : 0;
            $customers = array_filter($customers, function($c) use ($isActive) {
                return (isset($c['is_active']) ? $c['is_active'] : 1) == $isActive;
            });
        }

        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = strtolower($_GET['search']);
            $customers = array_filter($customers, function($c) use ($search) {
                $name = strtolower($c['name'] ?? '');
                $mobile = strtolower($c['mobile'] ?? '');
                $email = strtolower($c['email'] ?? '');
                return strpos($name, $search) !== false ||
                       strpos($mobile, $search) !== false ||
                       strpos($email, $search) !== false;
            });
        }

        // Sort by created_at desc
        usort($customers, function($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });

        // Pagination
        $total = count($customers);
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
        $offset = ($page - 1) * $limit;

        $customers = array_slice(array_values($customers), $offset, $limit);

        // Remove detailed data for list view
        foreach ($customers as &$c) {
            unset($c['recent_orders']);
            unset($c['addresses']);
        }

        $pagination = [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ];

        Response::success([
            'data' => $customers,
            'pagination' => $pagination
        ]);
    }
}

/**
 * PUT - Update customer (activate/deactivate)
 */
function handlePut($admin) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id'])) {
        Response::error('Customer ID is required');
    }

    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        // Get existing customer
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $input['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            Response::notFound('Customer not found');
        }

        // Only allow updating is_active for now
        if (!isset($input['is_active'])) {
            Response::error('No fields to update');
        }

        $isActive = $input['is_active'] ? 1 : 0;

        $stmt = $db->prepare("UPDATE users SET is_active = :is_active WHERE id = :id");
        $stmt->execute([':is_active' => $isActive, ':id' => $input['id']]);

        logAdminActivity($db, $admin['id'], 'update', 'customer', $input['id'], $existing, ['is_active' => $isActive]);

        // Fetch updated customer
        $stmt = $db->prepare("SELECT id, name, mobile, email, is_active, created_at FROM users WHERE id = :id");
        $stmt->execute([':id' => $input['id']]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        Response::success($customer, 'Customer updated successfully');
    } else {
        // File-based storage
        $users = loadUsers();
        $found = false;

        foreach ($users as &$user) {
            if ($user['id'] == $input['id']) {
                $found = true;

                if (isset($input['is_active'])) {
                    $user['is_active'] = $input['is_active'] ? 1 : 0;
                }
                $user['updated_at'] = date('Y-m-d H:i:s');

                saveUsers($users);

                // Return customer without sensitive data
                $result = [
                    'id' => $user['id'],
                    'name' => $user['name'] ?? '',
                    'mobile' => $user['mobile'] ?? '',
                    'email' => $user['email'] ?? '',
                    'is_active' => $user['is_active'],
                    'created_at' => $user['created_at'] ?? ''
                ];

                Response::success($result, 'Customer updated successfully');
            }
        }

        if (!$found) {
            Response::notFound('Customer not found');
        }
    }
}
?>
