<?php
/**
 * Admin Combo Offers API
 * CRUD operations for combo offers
 * KPS Nursery Admin Panel
 */

date_default_timezone_set('Asia/Kolkata');

require_once 'middleware.php';

setCORSHeaders();

initStorage();
$sessionToken = getSessionToken();
$admin = requireAuth($GLOBALS['db'], $sessionToken);

$method = $_SERVER['REQUEST_METHOD'];

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
    error_log("Admin Combo Offers API Error: " . $e->getMessage());
    Response::serverError('Operation failed: ' . $e->getMessage());
}

/**
 * GET - List combo offers or get single
 */
function handleGet() {
    $db = $GLOBALS['db'];

    // Single combo
    if (isset($_GET['id'])) {
        $stmt = $db->prepare("SELECT * FROM combo_offers WHERE id = :id");
        $stmt->execute([':id' => $_GET['id']]);
        $combo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$combo) {
            Response::notFound('Combo offer not found');
        }

        $combo['product_ids'] = json_decode($combo['product_ids'], true) ?: [];

        // Fetch product details
        if (!empty($combo['product_ids'])) {
            $placeholders = implode(',', array_fill(0, count($combo['product_ids']), '?'));
            $stmt = $db->prepare("SELECT id, name, price, image FROM products WHERE id IN ($placeholders)");
            $stmt->execute($combo['product_ids']);
            $combo['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $combo['products'] = [];
        }

        Response::success($combo);
        return;
    }

    // List combos
    $where = [];
    $params = [];

    if (isset($_GET['status']) && $_GET['status'] !== 'all') {
        $where[] = "status = :status";
        $params[':status'] = $_GET['status'];
    }

    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $where[] = "title LIKE :search";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare("SELECT * FROM combo_offers $whereClause ORDER BY display_order ASC, id DESC");
    $stmt->execute($params);
    $combos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode product_ids JSON
    foreach ($combos as &$combo) {
        $combo['product_ids'] = json_decode($combo['product_ids'], true) ?: [];
    }

    Response::success($combos);
}

/**
 * POST - Create combo offer
 */
function handlePost($admin) {
    // Handle both JSON and FormData
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
    } else {
        $input = $_POST;
        if (isset($input['product_ids']) && is_string($input['product_ids'])) {
            $input['product_ids'] = json_decode($input['product_ids'], true);
        }
    }

    if (!isset($input['title']) || empty(trim($input['title']))) {
        Response::error('Title is required');
    }

    if (!isset($input['product_ids']) || empty($input['product_ids'])) {
        Response::error('At least one product is required');
    }

    $db = $GLOBALS['db'];

    // Handle image upload
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $imagePath = handleImageUpload($_FILES['image']);
    } elseif (isset($input['image']) && !empty($input['image'])) {
        $imagePath = $input['image'];
    }

    // Generate slug
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $input['title']), '-'));

    $originalPrice = floatval($input['original_price'] ?? 0);
    $offerPrice = floatval($input['offer_price'] ?? 0);
    $discountPercent = 0;
    if ($originalPrice > 0 && $offerPrice > 0) {
        $discountPercent = round((($originalPrice - $offerPrice) / $originalPrice) * 100, 2);
    }

    // Get max display order
    $stmt = $db->query("SELECT MAX(display_order) as max_order FROM combo_offers");
    $maxOrder = $stmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;

    $data = [
        ':title' => trim($input['title']),
        ':slug' => $slug,
        ':description' => isset($input['description']) ? trim($input['description']) : null,
        ':image' => $imagePath,
        ':product_ids' => json_encode(array_map('intval', $input['product_ids'])),
        ':original_price' => $originalPrice,
        ':offer_price' => $offerPrice,
        ':discount_percent' => $discountPercent,
        ':status' => isset($input['status']) ? $input['status'] : 'active',
        ':show_on_homepage' => isset($input['show_on_homepage']) ? ($input['show_on_homepage'] ? 1 : 0) : 1,
        ':display_order' => $maxOrder + 1,
        ':start_date' => (isset($input['start_date']) && $input['start_date'] !== '') ? $input['start_date'] : null,
        ':end_date' => (isset($input['end_date']) && $input['end_date'] !== '') ? $input['end_date'] : null
    ];

    $stmt = $db->prepare("
        INSERT INTO combo_offers (title, slug, description, image, product_ids, original_price, offer_price, discount_percent, status, show_on_homepage, display_order, start_date, end_date)
        VALUES (:title, :slug, :description, :image, :product_ids, :original_price, :offer_price, :discount_percent, :status, :show_on_homepage, :display_order, :start_date, :end_date)
    ");
    $stmt->execute($data);

    $comboId = $db->lastInsertId();
    logAdminActivity($db, $admin['id'], 'create', 'combo_offer', $comboId, null, $data);

    // Fetch created combo
    $stmt = $db->prepare("SELECT * FROM combo_offers WHERE id = :id");
    $stmt->execute([':id' => $comboId]);
    $combo = $stmt->fetch(PDO::FETCH_ASSOC);
    $combo['product_ids'] = json_decode($combo['product_ids'], true) ?: [];

    Response::success($combo, 'Combo offer created successfully');
}

/**
 * PUT - Update combo offer
 */
function handlePut($admin) {
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
    } else {
        $input = $_POST;
        if (isset($input['product_ids']) && is_string($input['product_ids'])) {
            $input['product_ids'] = json_decode($input['product_ids'], true);
        }
    }

    if (!isset($input['id'])) {
        Response::error('Combo offer ID is required');
    }

    $db = $GLOBALS['db'];

    // Get existing
    $stmt = $db->prepare("SELECT * FROM combo_offers WHERE id = :id");
    $stmt->execute([':id' => $input['id']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        Response::notFound('Combo offer not found');
    }

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $input['image'] = handleImageUpload($_FILES['image']);
    }

    // Build dynamic update
    $allowedFields = ['title', 'description', 'image', 'product_ids', 'original_price', 'offer_price', 'discount_percent', 'status', 'show_on_homepage', 'display_order', 'start_date', 'end_date'];
    $updates = [];
    $params = [':id' => $input['id']];
    $newValues = [];

    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $input)) {
            $value = $input[$field];
            if ($field === 'product_ids') {
                $value = json_encode(array_map('intval', is_array($value) ? $value : json_decode($value, true)));
            }
            if ($field === 'show_on_homepage') {
                $value = $value ? 1 : 0;
            }
            if (in_array($field, ['original_price', 'offer_price', 'discount_percent'])) {
                $value = floatval($value);
            }
            if (in_array($field, ['start_date', 'end_date']) && $value === '') {
                $value = null;
            }
            $updates[] = "$field = :$field";
            $params[":$field"] = $value;
            $newValues[$field] = $value;
        }
    }

    // Auto-calculate discount percent if prices changed
    if (isset($input['original_price']) || isset($input['offer_price'])) {
        $origPrice = floatval($input['original_price'] ?? $existing['original_price']);
        $offPrice = floatval($input['offer_price'] ?? $existing['offer_price']);
        if ($origPrice > 0 && $offPrice > 0) {
            $discPct = round((($origPrice - $offPrice) / $origPrice) * 100, 2);
            $updates[] = "discount_percent = :discount_percent";
            $params[':discount_percent'] = $discPct;
            $newValues['discount_percent'] = $discPct;
        }
    }

    // Auto-update slug if title changed
    if (isset($input['title'])) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $input['title']), '-'));
        $updates[] = "slug = :slug";
        $params[':slug'] = $slug;
    }

    if (empty($updates)) {
        Response::error('No fields to update');
    }

    $sql = "UPDATE combo_offers SET " . implode(', ', $updates) . " WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    logAdminActivity($db, $admin['id'], 'update', 'combo_offer', $input['id'], $existing, $newValues);

    // Fetch updated combo
    $stmt = $db->prepare("SELECT * FROM combo_offers WHERE id = :id");
    $stmt->execute([':id' => $input['id']]);
    $combo = $stmt->fetch(PDO::FETCH_ASSOC);
    $combo['product_ids'] = json_decode($combo['product_ids'], true) ?: [];

    Response::success($combo, 'Combo offer updated successfully');
}

/**
 * DELETE - Delete combo offer
 */
function handleDelete($admin) {
    $id = isset($_GET['id']) ? $_GET['id'] : null;

    if (!$id) {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = isset($input['id']) ? $input['id'] : null;
    }

    if (!$id) {
        Response::error('Combo offer ID is required');
    }

    $db = $GLOBALS['db'];

    $stmt = $db->prepare("SELECT * FROM combo_offers WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        Response::notFound('Combo offer not found');
    }

    $stmt = $db->prepare("DELETE FROM combo_offers WHERE id = :id");
    $stmt->execute([':id' => $id]);

    logAdminActivity($db, $admin['id'], 'delete', 'combo_offer', $id, $existing, null);

    Response::success(null, 'Combo offer deleted successfully');
}

/**
 * Handle image upload
 */
function handleImageUpload($file) {
    $uploadDir = __DIR__ . '/../../frontend/assets/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        Response::error('Invalid image type. Allowed: JPG, PNG, WebP, GIF');
    }

    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        Response::error('Image too large. Max 5MB.');
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        Response::error('Failed to upload image');
    }

    return '/frontend/assets/uploads/' . $filename;
}
?>
