<?php
/**
 * Admin Categories API
 * Category CRUD operations
 * SK Bakers Admin Panel
 */

require_once 'admin_middleware.php';

setCORSHeaders();

// Initialize storage
initStorage();
$sessionToken = getSessionToken();
$admin = requireAuth($GLOBALS['db'], $sessionToken);

$method = $_SERVER['REQUEST_METHOD'];

// File-based storage
define('DATA_DIR', __DIR__ . '/../data/');
define('CATEGORIES_FILE', DATA_DIR . 'categories.json');
define('PRODUCTS_FILE', DATA_DIR . 'products.json');

function loadCategories() {
    if (file_exists(CATEGORIES_FILE)) {
        $data = json_decode(file_get_contents(CATEGORIES_FILE), true);
        return $data ?: [];
    }
    return [];
}

function saveCategories($categories) {
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0777, true);
    }
    file_put_contents(CATEGORIES_FILE, json_encode(array_values($categories), JSON_PRETTY_PRINT));
}

function loadProducts() {
    if (file_exists(PRODUCTS_FILE)) {
        $data = json_decode(file_get_contents(PRODUCTS_FILE), true);
        return $data ?: [];
    }
    return [];
}

function getNextCategoryId($categories) {
    $maxId = 0;
    foreach ($categories as $cat) {
        if ($cat['id'] > $maxId) $maxId = $cat['id'];
    }
    return $maxId + 1;
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
    error_log("Admin Categories API Error: " . $e->getMessage());
    Response::serverError('Operation failed');
}

/**
 * GET - List categories or get single category
 */
function handleGet() {
    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        // Single category
        if (isset($_GET['id'])) {
            $stmt = $db->prepare("
                SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count
                FROM categories c
                WHERE c.id = :id
            ");
            $stmt->execute([':id' => $_GET['id']]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$category) {
                Response::notFound('Category not found');
            }

            Response::success($category);
        }

        // List categories
        $where = [];
        $params = [];

        // Filter by status
        if (isset($_GET['status'])) {
            $where[] = "c.is_active = :status";
            $params[':status'] = $_GET['status'] === 'active' ? 1 : 0;
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $db->prepare("
            SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count
            FROM categories c
            $whereClause
            ORDER BY c.display_order ASC, c.name ASC
        ");
        $stmt->execute($params);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success($categories);
    } else {
        // File-based storage
        $categories = loadCategories();
        $products = loadProducts();

        // Add product count to each category
        foreach ($categories as &$cat) {
            $count = 0;
            foreach ($products as $p) {
                if (isset($p['category_id']) && $p['category_id'] == $cat['id']) {
                    $count++;
                }
            }
            $cat['product_count'] = $count;
        }

        // Single category
        if (isset($_GET['id'])) {
            foreach ($categories as $cat) {
                if ($cat['id'] == $_GET['id']) {
                    Response::success($cat);
                }
            }
            Response::notFound('Category not found');
        }

        // Filter by status
        if (isset($_GET['status'])) {
            $isActive = $_GET['status'] === 'active' ? 1 : 0;
            $categories = array_filter($categories, function($c) use ($isActive) {
                return (isset($c['is_active']) ? $c['is_active'] : 1) == $isActive;
            });
        }

        // Sort by display_order, then name
        usort($categories, function($a, $b) {
            $orderA = isset($a['display_order']) ? $a['display_order'] : 0;
            $orderB = isset($b['display_order']) ? $b['display_order'] : 0;
            if ($orderA != $orderB) return $orderA - $orderB;
            return strcmp($a['name'], $b['name']);
        });

        Response::success(array_values($categories));
    }
}

/**
 * POST - Create category
 */
function handlePost($admin) {
    // Only admin can create categories
    if ($admin['role'] !== 'admin') {
        Response::error('Permission denied', 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['name']) || empty($input['name'])) {
        Response::error('Category name is required');
    }

    // Generate slug from name if not provided
    $name = trim($input['name']);
    $slug = isset($input['slug']) && !empty($input['slug'])
        ? trim($input['slug'])
        : strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));

    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        // Check if slug already exists
        $stmt = $db->prepare("SELECT id FROM categories WHERE slug = :slug");
        $stmt->execute([':slug' => $slug]);
        if ($stmt->fetch()) {
            Response::error('Category slug already exists');
        }

        // Get max display order
        $stmt = $db->query("SELECT MAX(display_order) as max_order FROM categories");
        $maxOrder = $stmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;

        $data = [
            'name' => $name,
            'slug' => $slug,
            'icon' => isset($input['icon']) ? trim($input['icon']) : null,
            'display_order' => isset($input['display_order']) ? intval($input['display_order']) : $maxOrder + 1,
            'is_active' => isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1
        ];

        $stmt = $db->prepare("
            INSERT INTO categories (name, slug, icon, display_order, is_active)
            VALUES (:name, :slug, :icon, :display_order, :is_active)
        ");

        $stmt->execute([
            ':name' => $data['name'],
            ':slug' => $data['slug'],
            ':icon' => $data['icon'],
            ':display_order' => $data['display_order'],
            ':is_active' => $data['is_active']
        ]);

        $categoryId = $db->lastInsertId();
        logAdminActivity($db, $admin['id'], 'create', 'category', $categoryId, null, $data);

        // Fetch created category
        $stmt = $db->prepare("SELECT * FROM categories WHERE id = :id");
        $stmt->execute([':id' => $categoryId]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        Response::success($category, 'Category created successfully');
    } else {
        // File-based storage
        $categories = loadCategories();

        // Check if slug already exists
        foreach ($categories as $cat) {
            if ($cat['slug'] === $slug) {
                Response::error('Category slug already exists');
            }
        }

        // Get max display order
        $maxOrder = 0;
        foreach ($categories as $cat) {
            if (isset($cat['display_order']) && $cat['display_order'] > $maxOrder) {
                $maxOrder = $cat['display_order'];
            }
        }

        $newCategory = [
            'id' => getNextCategoryId($categories),
            'name' => $name,
            'slug' => $slug,
            'icon' => isset($input['icon']) ? trim($input['icon']) : null,
            'display_order' => isset($input['display_order']) ? intval($input['display_order']) : $maxOrder + 1,
            'is_active' => isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $categories[] = $newCategory;
        saveCategories($categories);

        Response::success($newCategory, 'Category created successfully');
    }
}

/**
 * PUT - Update category
 */
function handlePut($admin) {
    // Only admin can update categories
    if ($admin['role'] !== 'admin') {
        Response::error('Permission denied', 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id'])) {
        Response::error('Category ID is required');
    }

    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        // Get existing category
        $stmt = $db->prepare("SELECT * FROM categories WHERE id = :id");
        $stmt->execute([':id' => $input['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            Response::notFound('Category not found');
        }

        // Build update query
        $allowedFields = ['name', 'slug', 'icon', 'display_order', 'is_active'];
        $updates = [];
        $params = [':id' => $input['id']];
        $newValues = [];

        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $value = $input[$field];
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

        // Check slug uniqueness if being updated
        if (isset($input['slug'])) {
            $stmt = $db->prepare("SELECT id FROM categories WHERE slug = :slug AND id != :id");
            $stmt->execute([':slug' => $input['slug'], ':id' => $input['id']]);
            if ($stmt->fetch()) {
                Response::error('Category slug already exists');
            }
        }

        $sql = "UPDATE categories SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        logAdminActivity($db, $admin['id'], 'update', 'category', $input['id'], $existing, $newValues);

        // Fetch updated category
        $stmt = $db->prepare("SELECT * FROM categories WHERE id = :id");
        $stmt->execute([':id' => $input['id']]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        Response::success($category, 'Category updated successfully');
    } else {
        // File-based storage
        $categories = loadCategories();
        $found = false;

        foreach ($categories as &$cat) {
            if ($cat['id'] == $input['id']) {
                $found = true;

                // Check slug uniqueness if being updated
                if (isset($input['slug'])) {
                    foreach ($categories as $other) {
                        if ($other['id'] != $input['id'] && $other['slug'] === $input['slug']) {
                            Response::error('Category slug already exists');
                        }
                    }
                }

                // Update fields
                if (isset($input['name'])) $cat['name'] = trim($input['name']);
                if (isset($input['slug'])) $cat['slug'] = trim($input['slug']);
                if (isset($input['icon'])) $cat['icon'] = trim($input['icon']);
                if (isset($input['display_order'])) $cat['display_order'] = intval($input['display_order']);
                if (isset($input['is_active'])) $cat['is_active'] = $input['is_active'] ? 1 : 0;
                $cat['updated_at'] = date('Y-m-d H:i:s');

                saveCategories($categories);
                Response::success($cat, 'Category updated successfully');
            }
        }

        if (!$found) {
            Response::notFound('Category not found');
        }
    }
}

/**
 * DELETE - Delete category
 */
function handleDelete($admin) {
    // Only admin can delete categories
    if ($admin['role'] !== 'admin') {
        Response::error('Permission denied', 403);
    }

    $id = isset($_GET['id']) ? $_GET['id'] : null;

    if (!$id) {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = isset($input['id']) ? $input['id'] : null;
    }

    if (!$id) {
        Response::error('Category ID is required');
    }

    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        // Get existing category
        $stmt = $db->prepare("SELECT * FROM categories WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            Response::notFound('Category not found');
        }

        // Check if category has products
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = :id");
        $stmt->execute([':id' => $id]);
        $productCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($productCount > 0) {
            Response::error("Cannot delete category with $productCount product(s). Please move or delete the products first.");
        }

        $stmt = $db->prepare("DELETE FROM categories WHERE id = :id");
        $stmt->execute([':id' => $id]);

        logAdminActivity($db, $admin['id'], 'delete', 'category', $id, $existing, null);

        Response::success(null, 'Category deleted successfully');
    } else {
        // File-based storage
        $categories = loadCategories();
        $products = loadProducts();
        $found = false;

        foreach ($categories as $index => $cat) {
            if ($cat['id'] == $id) {
                $found = true;

                // Check if category has products
                $productCount = 0;
                foreach ($products as $p) {
                    if (isset($p['category_id']) && $p['category_id'] == $id) {
                        $productCount++;
                    }
                }

                if ($productCount > 0) {
                    Response::error("Cannot delete category with $productCount product(s). Please move or delete the products first.");
                }

                array_splice($categories, $index, 1);
                saveCategories($categories);
                Response::success(null, 'Category deleted successfully');
            }
        }

        if (!$found) {
            Response::notFound('Category not found');
        }
    }
}
?>
