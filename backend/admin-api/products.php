<?php
/**
 * Admin Products API
 * Product CRUD operations
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
define('PRODUCTS_FILE', DATA_DIR . 'products.json');
define('CATEGORIES_FILE', DATA_DIR . 'categories.json');
define('ORDERS_FILE', DATA_DIR . 'orders.json');

function loadProducts() {
    if (file_exists(PRODUCTS_FILE)) {
        $data = json_decode(file_get_contents(PRODUCTS_FILE), true);
        return $data ?: [];
    }
    return [];
}

function saveProducts($products) {
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0777, true);
    }
    file_put_contents(PRODUCTS_FILE, json_encode(array_values($products), JSON_PRETTY_PRINT));
}

function loadCategories() {
    if (file_exists(CATEGORIES_FILE)) {
        $data = json_decode(file_get_contents(CATEGORIES_FILE), true);
        return $data ?: [];
    }
    return [];
}

function loadOrders() {
    if (file_exists(ORDERS_FILE)) {
        $data = json_decode(file_get_contents(ORDERS_FILE), true);
        return $data ?: [];
    }
    return [];
}

function getNextProductId($products) {
    $maxId = 0;
    foreach ($products as $p) {
        if ($p['id'] > $maxId) $maxId = $p['id'];
    }
    return $maxId + 1;
}

function getCategoryName($categories, $categoryId) {
    foreach ($categories as $cat) {
        if ($cat['id'] == $categoryId) {
            return $cat['name'];
        }
    }
    return 'Unknown';
}

try {
    switch ($method) {
        case 'GET':
            handleGet();
            break;
        case 'POST':
            handlePost($admin);
            break;
        case 'PUT':
            handlePut($admin);
            break;
        case 'DELETE':
            handleDelete($admin);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Admin Products API Error: " . $e->getMessage());
    Response::serverError('Operation failed');
}

/**
 * GET - List products or get single product
 */
function handleGet() {
    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        // Single product
        if (isset($_GET['id'])) {
            $stmt = $db->prepare("
                SELECT p.*, c.name as category_name, c.slug as category_slug
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE p.id = :id
            ");
            $stmt->execute([':id' => $_GET['id']]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                Response::notFound('Product not found');
            }

            Response::success($product);
        }

        // List products with pagination and filters
        $pagination = getPagination(20);
        $where = [];
        $params = [];

        // Filter by category
        if (isset($_GET['category_id']) && $_GET['category_id'] !== '') {
            $where[] = "p.category_id = :category_id";
            $params[':category_id'] = $_GET['category_id'];
        }

        // Filter by status
        if (isset($_GET['status'])) {
            $where[] = "p.is_active = :status";
            $params[':status'] = $_GET['status'] === 'active' ? 1 : 0;
        }

        // Search
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $where[] = "(p.name LIKE :search OR p.description LIKE :search)";
            $params[':search'] = '%' . $_GET['search'] . '%';
        }

        // Low stock filter
        if (isset($_GET['low_stock']) && $_GET['low_stock'] === '1') {
            $where[] = "p.stock < 10";
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        // Get total count
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM products p $whereClause");
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Get products
        $orderBy = isset($_GET['sort']) ? $_GET['sort'] : 'p.created_at DESC';
        $allowedSorts = ['p.name ASC', 'p.name DESC', 'p.price ASC', 'p.price DESC', 'p.stock ASC', 'p.stock DESC', 'p.created_at ASC', 'p.created_at DESC'];
        if (!in_array($orderBy, $allowedSorts)) {
            $orderBy = 'p.created_at DESC';
        }

        $stmt = $db->prepare("
            SELECT p.*, c.name as category_name, c.slug as category_slug
            FROM products p
            JOIN categories c ON p.category_id = c.id
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

        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success(paginatedResponse($products, $total, $pagination));
    } else {
        // File-based storage
        $products = loadProducts();
        $categories = loadCategories();

        // Add category name to each product
        foreach ($products as &$product) {
            $product['category_name'] = getCategoryName($categories, $product['category_id'] ?? 0);
        }

        // Single product
        if (isset($_GET['id'])) {
            foreach ($products as $p) {
                if ($p['id'] == $_GET['id']) {
                    Response::success($p);
                }
            }
            Response::notFound('Product not found');
        }

        // Apply filters
        if (isset($_GET['category_id']) && $_GET['category_id'] !== '') {
            $catId = $_GET['category_id'];
            $products = array_filter($products, function($p) use ($catId) {
                return isset($p['category_id']) && $p['category_id'] == $catId;
            });
        }

        if (isset($_GET['status'])) {
            $isActive = $_GET['status'] === 'active' ? 1 : 0;
            $products = array_filter($products, function($p) use ($isActive) {
                return (isset($p['is_active']) ? $p['is_active'] : 1) == $isActive;
            });
        }

        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = strtolower($_GET['search']);
            $products = array_filter($products, function($p) use ($search) {
                $name = strtolower($p['name'] ?? '');
                $desc = strtolower($p['description'] ?? '');
                return strpos($name, $search) !== false || strpos($desc, $search) !== false;
            });
        }

        if (isset($_GET['low_stock']) && $_GET['low_stock'] === '1') {
            $products = array_filter($products, function($p) {
                return isset($p['stock']) && $p['stock'] < 10;
            });
        }

        // Sort
        usort($products, function($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });

        // Pagination
        $total = count($products);
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
        $offset = ($page - 1) * $limit;

        $products = array_slice(array_values($products), $offset, $limit);

        $pagination = [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ];

        Response::success([
            'data' => $products,
            'pagination' => $pagination
        ]);
    }
}

/**
 * POST - Create new product
 */
function handlePost($admin) {
    // Only admin can create products
    if ($admin['role'] !== 'admin') {
        Response::error('Permission denied', 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $required = ['name', 'category_id', 'price'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            Response::error("$field is required");
        }
    }

    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        // Validate category exists
        $stmt = $db->prepare("SELECT id FROM categories WHERE id = :id");
        $stmt->execute([':id' => $input['category_id']]);
        if (!$stmt->fetch()) {
            Response::error('Invalid category');
        }

        // Prepare data
        $data = [
            'category_id' => $input['category_id'],
            'name' => trim($input['name']),
            'description' => isset($input['description']) ? trim($input['description']) : null,
            'price' => floatval($input['price']),
            'discount_price' => isset($input['discount_price']) && $input['discount_price'] !== '' ? floatval($input['discount_price']) : null,
            'unit' => isset($input['unit']) ? trim($input['unit']) : 'kg',
            'image' => isset($input['image']) ? trim($input['image']) : null,
            'images' => isset($input['images']) ? json_encode($input['images']) : null,
            'stock' => isset($input['stock']) ? intval($input['stock']) : 0,
            'rating' => isset($input['rating']) ? floatval($input['rating']) : 0,
            'reviews' => isset($input['reviews']) ? intval($input['reviews']) : 0,
            'display_order' => isset($input['display_order']) ? intval($input['display_order']) : 0,
            'is_active' => isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1
        ];

        $stmt = $db->prepare("
            INSERT INTO products (category_id, name, description, price, discount_price, unit, image, images, stock, rating, reviews, display_order, is_active)
            VALUES (:category_id, :name, :description, :price, :discount_price, :unit, :image, :images, :stock, :rating, :reviews, :display_order, :is_active)
        ");

        $stmt->execute([
            ':category_id' => $data['category_id'],
            ':name' => $data['name'],
            ':description' => $data['description'],
            ':price' => $data['price'],
            ':discount_price' => $data['discount_price'],
            ':unit' => $data['unit'],
            ':image' => $data['image'],
            ':images' => $data['images'],
            ':stock' => $data['stock'],
            ':rating' => $data['rating'],
            ':reviews' => $data['reviews'],
            ':display_order' => $data['display_order'],
            ':is_active' => $data['is_active']
        ]);

        $productId = $db->lastInsertId();

        // Log activity
        logAdminActivity($db, $admin['id'], 'create', 'product', $productId, null, $data);

        // Fetch created product
        $stmt = $db->prepare("
            SELECT p.*, c.name as category_name
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.id = :id
        ");
        $stmt->execute([':id' => $productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        Response::success($product, 'Product created successfully');
    } else {
        // File-based storage
        $products = loadProducts();
        $categories = loadCategories();

        // Validate category exists
        $categoryExists = false;
        foreach ($categories as $cat) {
            if ($cat['id'] == $input['category_id']) {
                $categoryExists = true;
                break;
            }
        }
        if (!$categoryExists) {
            Response::error('Invalid category');
        }

        $newProduct = [
            'id' => getNextProductId($products),
            'category_id' => intval($input['category_id']),
            'name' => trim($input['name']),
            'description' => isset($input['description']) ? trim($input['description']) : null,
            'price' => floatval($input['price']),
            'discount_price' => isset($input['discount_price']) && $input['discount_price'] !== '' ? floatval($input['discount_price']) : null,
            'unit' => isset($input['unit']) ? trim($input['unit']) : 'kg',
            'image' => isset($input['image']) ? trim($input['image']) : null,
            'images' => isset($input['images']) ? $input['images'] : null,
            'stock' => isset($input['stock']) ? intval($input['stock']) : 0,
            'rating' => isset($input['rating']) ? floatval($input['rating']) : 0,
            'reviews' => isset($input['reviews']) ? intval($input['reviews']) : 0,
            'display_order' => isset($input['display_order']) ? intval($input['display_order']) : 0,
            'is_active' => isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $newProduct['category_name'] = getCategoryName($categories, $newProduct['category_id']);

        $products[] = $newProduct;
        saveProducts($products);

        Response::success($newProduct, 'Product created successfully');
    }
}

/**
 * PUT - Update product
 */
function handlePut($admin) {
    // Only admin can update products
    if ($admin['role'] !== 'admin') {
        Response::error('Permission denied', 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id'])) {
        Response::error('Product ID is required');
    }

    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        // Get existing product
        $stmt = $db->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute([':id' => $input['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            Response::notFound('Product not found');
        }

        // Build update query dynamically
        $allowedFields = ['category_id', 'name', 'description', 'price', 'discount_price', 'unit', 'image', 'images', 'stock', 'rating', 'reviews', 'display_order', 'is_active'];
        $updates = [];
        $params = [':id' => $input['id']];
        $newValues = [];

        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $value = $input[$field];
                if ($field === 'images' && is_array($value)) {
                    $value = json_encode($value);
                }
                if ($field === 'is_active') {
                    $value = $value ? 1 : 0;
                }
                $updates[] = "$field = :$field";
                $params[":$field"] = $value;
                $newValues[$field] = $value;
            }
        }

        if (empty($updates)) {
            Response::error('No fields to update');
        }

        $sql = "UPDATE products SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        // Log activity
        logAdminActivity($db, $admin['id'], 'update', 'product', $input['id'], $existing, $newValues);

        // Fetch updated product
        $stmt = $db->prepare("
            SELECT p.*, c.name as category_name
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.id = :id
        ");
        $stmt->execute([':id' => $input['id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        Response::success($product, 'Product updated successfully');
    } else {
        // File-based storage
        $products = loadProducts();
        $categories = loadCategories();
        $found = false;

        foreach ($products as &$product) {
            if ($product['id'] == $input['id']) {
                $found = true;

                // Update fields
                if (isset($input['category_id'])) $product['category_id'] = intval($input['category_id']);
                if (isset($input['name'])) $product['name'] = trim($input['name']);
                if (isset($input['description'])) $product['description'] = trim($input['description']);
                if (isset($input['price'])) $product['price'] = floatval($input['price']);
                if (isset($input['discount_price'])) $product['discount_price'] = $input['discount_price'] !== '' ? floatval($input['discount_price']) : null;
                if (isset($input['unit'])) $product['unit'] = trim($input['unit']);
                if (isset($input['image'])) $product['image'] = trim($input['image']);
                if (isset($input['images'])) $product['images'] = $input['images'];
                if (isset($input['stock'])) $product['stock'] = intval($input['stock']);
                if (isset($input['rating'])) $product['rating'] = floatval($input['rating']);
                if (isset($input['reviews'])) $product['reviews'] = intval($input['reviews']);
                if (isset($input['display_order'])) $product['display_order'] = intval($input['display_order']);
                if (isset($input['is_active'])) $product['is_active'] = $input['is_active'] ? 1 : 0;
                $product['updated_at'] = date('Y-m-d H:i:s');

                $product['category_name'] = getCategoryName($categories, $product['category_id']);

                saveProducts($products);
                Response::success($product, 'Product updated successfully');
            }
        }

        if (!$found) {
            Response::notFound('Product not found');
        }
    }
}

/**
 * DELETE - Delete or disable product
 */
function handleDelete($admin) {
    // Only admin can delete products
    if ($admin['role'] !== 'admin') {
        Response::error('Permission denied', 403);
    }

    $id = isset($_GET['id']) ? $_GET['id'] : null;

    if (!$id) {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = isset($input['id']) ? $input['id'] : null;
    }

    if (!$id) {
        Response::error('Product ID is required');
    }

    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        // Get existing product
        $stmt = $db->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            Response::notFound('Product not found');
        }

        // Check if product has orders
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM order_items WHERE product_id = :id");
        $stmt->execute([':id' => $id]);
        $orderCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($orderCount > 0) {
            // Soft delete - just disable the product
            $stmt = $db->prepare("UPDATE products SET is_active = 0 WHERE id = :id");
            $stmt->execute([':id' => $id]);
            logAdminActivity($db, $admin['id'], 'disable', 'product', $id, $existing, ['is_active' => 0]);
            Response::success(null, 'Product disabled (has order history)');
        } else {
            // Hard delete
            $stmt = $db->prepare("DELETE FROM products WHERE id = :id");
            $stmt->execute([':id' => $id]);
            logAdminActivity($db, $admin['id'], 'delete', 'product', $id, $existing, null);
            Response::success(null, 'Product deleted successfully');
        }
    } else {
        // File-based storage
        $products = loadProducts();
        $orders = loadOrders();
        $found = false;

        foreach ($products as $index => &$product) {
            if ($product['id'] == $id) {
                $found = true;

                // Check if product has orders
                $hasOrders = false;
                foreach ($orders as $order) {
                    if (isset($order['items'])) {
                        foreach ($order['items'] as $item) {
                            if (isset($item['product_id']) && $item['product_id'] == $id) {
                                $hasOrders = true;
                                break 2;
                            }
                        }
                    }
                }

                if ($hasOrders) {
                    // Soft delete
                    $product['is_active'] = 0;
                    $product['updated_at'] = date('Y-m-d H:i:s');
                    saveProducts($products);
                    Response::success(null, 'Product disabled (has order history)');
                } else {
                    // Hard delete
                    array_splice($products, $index, 1);
                    saveProducts($products);
                    Response::success(null, 'Product deleted successfully');
                }
            }
        }

        if (!$found) {
            Response::notFound('Product not found');
        }
    }
}
?>
