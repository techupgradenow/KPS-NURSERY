<?php
/**
 * Admin Banners API
 * Banner CRUD operations with drag-drop reordering
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
define('BANNERS_FILE', DATA_DIR . 'banners.json');

function loadBanners() {
    if (file_exists(BANNERS_FILE)) {
        $data = json_decode(file_get_contents(BANNERS_FILE), true);
        return $data ?: [];
    }
    return [];
}

function saveBanners($banners) {
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0777, true);
    }
    file_put_contents(BANNERS_FILE, json_encode(array_values($banners), JSON_PRETTY_PRINT));
}

function getNextBannerId($banners) {
    $maxId = 0;
    foreach ($banners as $b) {
        if ($b['id'] > $maxId) $maxId = $b['id'];
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
    error_log("Admin Banners API Error: " . $e->getMessage());
    Response::serverError('Operation failed');
}

/**
 * GET - List banners or get single banner
 */
function handleGet() {
    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        // Single banner
        if (isset($_GET['id'])) {
            $stmt = $db->prepare("SELECT * FROM banners WHERE id = :id");
            $stmt->execute([':id' => $_GET['id']]);
            $banner = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$banner) {
                Response::notFound('Banner not found');
            }

            Response::success($banner);
        }

        // List banners
        $where = [];
        $params = [];

        // Filter by status
        if (isset($_GET['status'])) {
            $where[] = "is_active = :status";
            $params[':status'] = $_GET['status'] === 'active' ? 1 : 0;
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $db->prepare("
            SELECT *
            FROM banners
            $whereClause
            ORDER BY display_order ASC, created_at DESC
        ");
        $stmt->execute($params);
        $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success($banners);
    } else {
        // File-based storage
        $banners = loadBanners();

        // Single banner
        if (isset($_GET['id'])) {
            foreach ($banners as $b) {
                if ($b['id'] == $_GET['id']) {
                    Response::success($b);
                }
            }
            Response::notFound('Banner not found');
        }

        // Filter by status
        if (isset($_GET['status'])) {
            $isActive = $_GET['status'] === 'active' ? 1 : 0;
            $banners = array_filter($banners, function($b) use ($isActive) {
                return (isset($b['is_active']) ? $b['is_active'] : 1) == $isActive;
            });
        }

        // Sort by display_order
        usort($banners, function($a, $b) {
            $orderA = isset($a['display_order']) ? $a['display_order'] : 999;
            $orderB = isset($b['display_order']) ? $b['display_order'] : 999;
            if ($orderA != $orderB) return $orderA - $orderB;
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });

        Response::success(array_values($banners));
    }
}

/**
 * POST - Create banner or reorder banners
 */
function handlePost($admin) {
    $input = json_decode(file_get_contents('php://input'), true);

    // Handle reorder action
    if (isset($input['action']) && $input['action'] === 'reorder') {
        handleReorder($admin, $input);
        return;
    }

    // Create new banner - accept desktop_image or image
    $image = isset($input['desktop_image']) ? $input['desktop_image'] : (isset($input['image']) ? $input['image'] : '');
    if (empty($image)) {
        Response::error('Banner image is required');
    }

    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        // Get max display order
        $stmt = $db->query("SELECT MAX(display_order) as max_order FROM banners");
        $maxOrder = $stmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;

        $data = [
            'title' => isset($input['title']) ? trim($input['title']) : null,
            'caption' => isset($input['caption']) ? trim($input['caption']) : (isset($input['description']) ? trim($input['description']) : null),
            'image' => trim($image),
            'redirect_type' => isset($input['redirect_type']) ? $input['redirect_type'] : 'none',
            'redirect_url' => isset($input['redirect_url']) ? trim($input['redirect_url']) : null,
            'display_order' => $maxOrder + 1,
            'is_active' => isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1,
            'start_date' => isset($input['start_date']) && $input['start_date'] !== '' ? $input['start_date'] : null,
            'end_date' => isset($input['end_date']) && $input['end_date'] !== '' ? $input['end_date'] : null
        ];

        $stmt = $db->prepare("
            INSERT INTO banners (title, caption, image, redirect_type, redirect_url, display_order, is_active, start_date, end_date)
            VALUES (:title, :caption, :image, :redirect_type, :redirect_url, :display_order, :is_active, :start_date, :end_date)
        ");

        $stmt->execute([
            ':title' => $data['title'],
            ':caption' => $data['caption'],
            ':image' => $data['image'],
            ':redirect_type' => $data['redirect_type'],
            ':redirect_url' => $data['redirect_url'],
            ':display_order' => $data['display_order'],
            ':is_active' => $data['is_active'],
            ':start_date' => $data['start_date'],
            ':end_date' => $data['end_date']
        ]);

        $bannerId = $db->lastInsertId();
        logAdminActivity($db, $admin['id'], 'create', 'banner', $bannerId, null, $data);

        // Fetch created banner
        $stmt = $db->prepare("SELECT * FROM banners WHERE id = :id");
        $stmt->execute([':id' => $bannerId]);
        $banner = $stmt->fetch(PDO::FETCH_ASSOC);

        Response::success($banner, 'Banner created successfully');
    } else {
        // File-based storage
        $banners = loadBanners();

        // Get max display order
        $maxOrder = 0;
        foreach ($banners as $b) {
            if (isset($b['display_order']) && $b['display_order'] > $maxOrder) {
                $maxOrder = $b['display_order'];
            }
        }

        $newBanner = [
            'id' => getNextBannerId($banners),
            'title' => isset($input['title']) ? trim($input['title']) : null,
            'caption' => isset($input['caption']) ? trim($input['caption']) : (isset($input['description']) ? trim($input['description']) : null),
            'image' => trim($image),
            'redirect_type' => isset($input['redirect_type']) ? $input['redirect_type'] : 'none',
            'redirect_url' => isset($input['redirect_url']) ? trim($input['redirect_url']) : null,
            'display_order' => $maxOrder + 1,
            'is_active' => isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1,
            'start_date' => isset($input['start_date']) && $input['start_date'] !== '' ? $input['start_date'] : null,
            'end_date' => isset($input['end_date']) && $input['end_date'] !== '' ? $input['end_date'] : null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $banners[] = $newBanner;
        saveBanners($banners);

        Response::success($newBanner, 'Banner created successfully');
    }
}

/**
 * Handle reorder action
 */
function handleReorder($admin, $input) {
    if (!isset($input['order']) || !is_array($input['order'])) {
        Response::error('Order array is required');
    }

    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        $db->beginTransaction();
        try {
            foreach ($input['order'] as $index => $bannerId) {
                $stmt = $db->prepare("UPDATE banners SET display_order = :order WHERE id = :id");
                $stmt->execute([':order' => $index + 1, ':id' => $bannerId]);
            }
            $db->commit();

            logAdminActivity($db, $admin['id'], 'reorder', 'banner', null, null, ['order' => $input['order']]);
            Response::success(null, 'Banners reordered successfully');
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    } else {
        // File-based storage
        $banners = loadBanners();

        foreach ($input['order'] as $index => $bannerId) {
            foreach ($banners as &$b) {
                if ($b['id'] == $bannerId) {
                    $b['display_order'] = $index + 1;
                    break;
                }
            }
        }

        saveBanners($banners);
        Response::success(null, 'Banners reordered successfully');
    }
}

/**
 * PUT - Update banner
 */
function handlePut($admin) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id'])) {
        Response::error('Banner ID is required');
    }

    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        // Get existing banner
        $stmt = $db->prepare("SELECT * FROM banners WHERE id = :id");
        $stmt->execute([':id' => $input['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            Response::notFound('Banner not found');
        }

        // Map desktop_image to image
        if (array_key_exists('desktop_image', $input) && !array_key_exists('image', $input)) {
            $input['image'] = $input['desktop_image'];
        }

        // Build update query
        $allowedFields = ['title', 'caption', 'image', 'redirect_type', 'redirect_url', 'display_order', 'is_active', 'start_date', 'end_date'];
        $updates = [];
        $params = [':id' => $input['id']];
        $newValues = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $value = $input[$field];
                if ($field === 'is_active') {
                    $value = $value ? 1 : 0;
                }
                if (in_array($field, ['start_date', 'end_date']) && $value === '') {
                    $value = null;
                }
                $updates[] = "$field = :$field";
                $params[":$field"] = $value;
                $newValues[$field] = $value;
            }
        }

        if (empty($updates)) {
            Response::error('No fields to update');
        }

        $sql = "UPDATE banners SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        logAdminActivity($db, $admin['id'], 'update', 'banner', $input['id'], $existing, $newValues);

        // Fetch updated banner
        $stmt = $db->prepare("SELECT * FROM banners WHERE id = :id");
        $stmt->execute([':id' => $input['id']]);
        $banner = $stmt->fetch(PDO::FETCH_ASSOC);

        Response::success($banner, 'Banner updated successfully');
    } else {
        // File-based storage
        $banners = loadBanners();
        $found = false;

        foreach ($banners as &$banner) {
            if ($banner['id'] == $input['id']) {
                $found = true;

                // Update fields
                if (array_key_exists('title', $input)) $banner['title'] = trim($input['title']);
                if (array_key_exists('caption', $input)) $banner['caption'] = trim($input['caption']);
                if (array_key_exists('image', $input)) $banner['image'] = trim($input['image']);
                if (array_key_exists('redirect_type', $input)) $banner['redirect_type'] = $input['redirect_type'];
                if (array_key_exists('redirect_url', $input)) $banner['redirect_url'] = trim($input['redirect_url']);
                if (array_key_exists('redirect_product_id', $input)) $banner['redirect_product_id'] = $input['redirect_product_id'] !== '' ? intval($input['redirect_product_id']) : null;
                if (array_key_exists('redirect_category_id', $input)) $banner['redirect_category_id'] = $input['redirect_category_id'] !== '' ? intval($input['redirect_category_id']) : null;
                if (array_key_exists('display_order', $input)) $banner['display_order'] = intval($input['display_order']);
                if (array_key_exists('is_active', $input)) $banner['is_active'] = $input['is_active'] ? 1 : 0;
                if (array_key_exists('start_date', $input)) $banner['start_date'] = $input['start_date'] !== '' ? $input['start_date'] : null;
                if (array_key_exists('end_date', $input)) $banner['end_date'] = $input['end_date'] !== '' ? $input['end_date'] : null;
                $banner['updated_at'] = date('Y-m-d H:i:s');

                saveBanners($banners);
                Response::success($banner, 'Banner updated successfully');
            }
        }

        if (!$found) {
            Response::notFound('Banner not found');
        }
    }
}

/**
 * DELETE - Delete banner
 */
function handleDelete($admin) {
    $id = isset($_GET['id']) ? $_GET['id'] : null;

    if (!$id) {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = isset($input['id']) ? $input['id'] : null;
    }

    if (!$id) {
        Response::error('Banner ID is required');
    }

    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        // Get existing banner
        $stmt = $db->prepare("SELECT * FROM banners WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            Response::notFound('Banner not found');
        }

        $stmt = $db->prepare("DELETE FROM banners WHERE id = :id");
        $stmt->execute([':id' => $id]);

        logAdminActivity($db, $admin['id'], 'delete', 'banner', $id, $existing, null);

        Response::success(null, 'Banner deleted successfully');
    } else {
        // File-based storage
        $banners = loadBanners();
        $found = false;

        foreach ($banners as $index => $banner) {
            if ($banner['id'] == $id) {
                $found = true;
                array_splice($banners, $index, 1);
                saveBanners($banners);
                Response::success(null, 'Banner deleted successfully');
            }
        }

        if (!$found) {
            Response::notFound('Banner not found');
        }
    }
}
?>
