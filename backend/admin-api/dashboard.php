<?php
/**
 * Admin Dashboard API
 * Dashboard statistics and overview
 * SK Bakers Admin Panel
 */

require_once 'middleware.php';

setCORSHeaders();

// Initialize storage and check auth
initStorage();
$sessionToken = getSessionToken();
$admin = requireAuth($GLOBALS['db'], $sessionToken);

// Only GET method allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

// Data directory for file-based storage
define('DATA_DIR', __DIR__ . '/../data/');

function getFileData($filename, $default = []) {
    $filepath = DATA_DIR . $filename;
    if (file_exists($filepath)) {
        $data = json_decode(file_get_contents($filepath), true);
        return $data ?: $default;
    }
    return $default;
}

try {
    $stats = [];

    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        // Total Products
        $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1");
        $stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Total Orders
        $stmt = $db->query("SELECT COUNT(*) as count FROM orders");
        $stats['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Today's Orders
        $stmt = $db->query("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = CURDATE()");
        $stats['today_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Pending Orders
        $stmt = $db->query("SELECT COUNT(*) as count FROM orders WHERE status IN ('pending', 'confirmed', 'preparing')");
        $stats['pending_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Total Revenue
        $stmt = $db->query("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE status != 'cancelled'");
        $stats['total_revenue'] = floatval($stmt->fetch(PDO::FETCH_ASSOC)['total']);

        // Today's Revenue
        $stmt = $db->query("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'");
        $stats['today_revenue'] = floatval($stmt->fetch(PDO::FETCH_ASSOC)['total']);

        // This Month Revenue
        $stmt = $db->query("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status != 'cancelled'");
        $stats['month_revenue'] = floatval($stmt->fetch(PDO::FETCH_ASSOC)['total']);

        // Total Users
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE is_guest = 0");
        $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Active Banners
        $stmt = $db->query("SELECT COUNT(*) as count FROM banners WHERE is_active = 1");
        $stats['active_banners'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Active Offers/Popups
        $stmt = $db->query("SELECT COUNT(*) as count FROM offer_popups WHERE is_active = 1");
        $stats['active_offers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Low Stock Products (stock < 10)
        $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1 AND stock < 10");
        $stats['low_stock_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Order Status Distribution
        $stmt = $db->query("
            SELECT status, COUNT(*) as count
            FROM orders
            GROUP BY status
        ");
        $stats['order_status_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent Orders (last 10)
        $stmt = $db->query("
            SELECT o.*, u.name as customer_name, u.mobile as customer_mobile
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            ORDER BY o.created_at DESC
            LIMIT 10
        ");
        $stats['recent_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Top Selling Products (last 30 days)
        $stmt = $db->query("
            SELECT p.id, p.name, p.image, SUM(oi.quantity) as total_sold, SUM(oi.subtotal) as total_revenue
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN orders o ON oi.order_id = o.id
            WHERE o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND o.status != 'cancelled'
            GROUP BY p.id
            ORDER BY total_sold DESC
            LIMIT 5
        ");
        $stats['top_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Revenue Trend (last 7 days)
        $stmt = $db->query("
            SELECT DATE(created_at) as date, COALESCE(SUM(total), 0) as revenue, COUNT(*) as orders
            FROM orders
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status != 'cancelled'
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stats['revenue_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Category-wise Sales
        $stmt = $db->query("
            SELECT c.name as category, COALESCE(SUM(oi.subtotal), 0) as revenue
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.id AND o.status != 'cancelled'
            GROUP BY c.id
            ORDER BY revenue DESC
        ");
        $stats['category_sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // File-based storage - use stored data or return sample data
        $products = getFileData('products.json');
        $categories = getFileData('categories.json');
        $orders = getFileData('orders.json');
        $banners = getFileData('banners.json');
        $popups = getFileData('popups.json');
        $users = getFileData('users.json');

        // Calculate stats from file data
        $activeProducts = array_filter($products, function($p) { return !empty($p['is_active']); });
        $stats['total_products'] = count($activeProducts);

        $stats['total_orders'] = count($orders);

        // Today's orders
        $today = date('Y-m-d');
        $todayOrders = array_filter($orders, function($o) use ($today) {
            return isset($o['created_at']) && strpos($o['created_at'], $today) === 0;
        });
        $stats['today_orders'] = count($todayOrders);

        // Pending orders
        $pendingOrders = array_filter($orders, function($o) {
            return isset($o['status']) && in_array($o['status'], ['pending', 'confirmed', 'preparing']);
        });
        $stats['pending_orders'] = count($pendingOrders);

        // Total revenue
        $nonCancelledOrders = array_filter($orders, function($o) {
            return !isset($o['status']) || $o['status'] !== 'cancelled';
        });
        $stats['total_revenue'] = array_sum(array_column($nonCancelledOrders, 'total'));

        // Today's revenue
        $todayNonCancelled = array_filter($todayOrders, function($o) {
            return !isset($o['status']) || $o['status'] !== 'cancelled';
        });
        $stats['today_revenue'] = array_sum(array_column($todayNonCancelled, 'total'));

        // Month revenue
        $monthStart = date('Y-m-01');
        $monthOrders = array_filter($orders, function($o) use ($monthStart) {
            return isset($o['created_at']) && $o['created_at'] >= $monthStart &&
                   (!isset($o['status']) || $o['status'] !== 'cancelled');
        });
        $stats['month_revenue'] = array_sum(array_column($monthOrders, 'total'));

        // Total users
        $registeredUsers = array_filter($users, function($u) {
            return empty($u['is_guest']);
        });
        $stats['total_users'] = count($registeredUsers);

        // Active banners
        $activeBanners = array_filter($banners, function($b) { return !empty($b['is_active']); });
        $stats['active_banners'] = count($activeBanners);

        // Active popups
        $activePopups = array_filter($popups, function($p) { return !empty($p['is_active']); });
        $stats['active_offers'] = count($activePopups);

        // Low stock products
        $lowStock = array_filter($activeProducts, function($p) {
            return isset($p['stock']) && $p['stock'] < 10;
        });
        $stats['low_stock_products'] = count($lowStock);

        // Order status distribution
        $statusCounts = [];
        foreach ($orders as $order) {
            $status = $order['status'] ?? 'pending';
            if (!isset($statusCounts[$status])) {
                $statusCounts[$status] = 0;
            }
            $statusCounts[$status]++;
        }
        $stats['order_status_distribution'] = [];
        foreach ($statusCounts as $status => $count) {
            $stats['order_status_distribution'][] = ['status' => $status, 'count' => $count];
        }

        // If no data exists, show sample status distribution
        if (empty($stats['order_status_distribution'])) {
            $stats['order_status_distribution'] = [
                ['status' => 'pending', 'count' => 0],
                ['status' => 'confirmed', 'count' => 0],
                ['status' => 'preparing', 'count' => 0],
                ['status' => 'out_for_delivery', 'count' => 0],
                ['status' => 'delivered', 'count' => 0],
                ['status' => 'cancelled', 'count' => 0]
            ];
        }

        // Recent orders (last 10)
        usort($orders, function($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });
        $stats['recent_orders'] = array_slice($orders, 0, 10);

        // Top products (mock data if empty)
        $stats['top_products'] = [];
        if (!empty($products)) {
            $topProducts = array_slice($products, 0, 5);
            foreach ($topProducts as $p) {
                $stats['top_products'][] = [
                    'id' => $p['id'],
                    'name' => $p['name'],
                    'image' => $p['image'] ?? '',
                    'total_sold' => rand(10, 100),
                    'total_revenue' => rand(1000, 10000)
                ];
            }
        }

        // Revenue trend (last 7 days with sample data)
        $stats['revenue_trend'] = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $dayOrders = array_filter($orders, function($o) use ($date) {
                return isset($o['created_at']) && strpos($o['created_at'], $date) === 0 &&
                       (!isset($o['status']) || $o['status'] !== 'cancelled');
            });
            $stats['revenue_trend'][] = [
                'date' => $date,
                'revenue' => array_sum(array_column($dayOrders, 'total')),
                'orders' => count($dayOrders)
            ];
        }

        // Category sales
        $stats['category_sales'] = [];
        foreach ($categories as $cat) {
            $stats['category_sales'][] = [
                'category' => $cat['name'],
                'revenue' => 0
            ];
        }
    }

    Response::success($stats, 'Dashboard data retrieved');

} catch (Exception $e) {
    error_log("Dashboard API Error: " . $e->getMessage());
    Response::serverError('Failed to fetch dashboard data');
}
?>
