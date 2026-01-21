<?php
/**
 * Admin Offer Popups API
 * Popup CRUD operations
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
define('POPUPS_FILE', DATA_DIR . 'popups.json');

function loadPopups() {
    if (file_exists(POPUPS_FILE)) {
        $data = json_decode(file_get_contents(POPUPS_FILE), true);
        return $data ?: [];
    }
    return [];
}

function savePopups($popups) {
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0777, true);
    }
    file_put_contents(POPUPS_FILE, json_encode(array_values($popups), JSON_PRETTY_PRINT));
}

function getNextPopupId($popups) {
    $maxId = 0;
    foreach ($popups as $p) {
        if ($p['id'] > $maxId) $maxId = $p['id'];
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
    error_log("Admin Popups API Error: " . $e->getMessage());
    Response::serverError('Operation failed');
}

/**
 * GET - List popups or get single popup
 */
function handleGet() {
    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        // Single popup
        if (isset($_GET['id'])) {
            $stmt = $db->prepare("SELECT * FROM offer_popups WHERE id = :id");
            $stmt->execute([':id' => $_GET['id']]);
            $popup = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$popup) {
                Response::notFound('Popup not found');
            }

            Response::success($popup);
        }

        // List popups
        $where = [];
        $params = [];

        // Filter by status
        if (isset($_GET['status'])) {
            $where[] = "is_active = :status";
            $params[':status'] = $_GET['status'] === 'active' ? 1 : 0;
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $db->prepare("
            SELECT p.*, a.name as created_by_name
            FROM offer_popups p
            LEFT JOIN admins a ON p.created_by = a.id
            $whereClause
            ORDER BY p.created_at DESC
        ");
        $stmt->execute($params);
        $popups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success($popups);
    } else {
        // File-based storage
        $popups = loadPopups();

        // Single popup
        if (isset($_GET['id'])) {
            foreach ($popups as $p) {
                if ($p['id'] == $_GET['id']) {
                    Response::success($p);
                }
            }
            Response::notFound('Popup not found');
        }

        // Filter by status
        if (isset($_GET['status'])) {
            $isActive = $_GET['status'] === 'active' ? 1 : 0;
            $popups = array_filter($popups, function($p) use ($isActive) {
                return (isset($p['is_active']) ? $p['is_active'] : 1) == $isActive;
            });
        }

        // Sort by created_at desc
        usort($popups, function($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });

        Response::success(array_values($popups));
    }
}

/**
 * POST - Create popup
 */
function handlePost($admin) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['title']) || empty($input['title'])) {
        Response::error('Title is required');
    }

    // Validate display_rule
    $validRules = ['first_load', 'every_visit', 'once_per_day', 'once_per_session'];
    $displayRule = isset($input['display_rule']) ? $input['display_rule'] : 'once_per_session';
    if (!in_array($displayRule, $validRules)) {
        Response::error('Invalid display rule');
    }

    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        $data = [
            'title' => trim($input['title']),
            'description' => isset($input['description']) ? trim($input['description']) : null,
            'image' => isset($input['image']) ? trim($input['image']) : null,
            'coupon_code' => isset($input['coupon_code']) ? trim($input['coupon_code']) : null,
            'cta_text' => isset($input['cta_text']) ? trim($input['cta_text']) : 'Shop Now',
            'cta_link' => isset($input['cta_link']) ? trim($input['cta_link']) : null,
            'display_rule' => $displayRule,
            'is_active' => isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1,
            'start_date' => isset($input['start_date']) && $input['start_date'] !== '' ? $input['start_date'] : null,
            'end_date' => isset($input['end_date']) && $input['end_date'] !== '' ? $input['end_date'] : null,
            'created_by' => $admin['id']
        ];

        $stmt = $db->prepare("
            INSERT INTO offer_popups (title, description, image, coupon_code, cta_text, cta_link, display_rule, is_active, start_date, end_date, created_by)
            VALUES (:title, :description, :image, :coupon_code, :cta_text, :cta_link, :display_rule, :is_active, :start_date, :end_date, :created_by)
        ");

        $stmt->execute([
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':image' => $data['image'],
            ':coupon_code' => $data['coupon_code'],
            ':cta_text' => $data['cta_text'],
            ':cta_link' => $data['cta_link'],
            ':display_rule' => $data['display_rule'],
            ':is_active' => $data['is_active'],
            ':start_date' => $data['start_date'],
            ':end_date' => $data['end_date'],
            ':created_by' => $data['created_by']
        ]);

        $popupId = $db->lastInsertId();
        logAdminActivity($db, $admin['id'], 'create', 'popup', $popupId, null, $data);

        // Fetch created popup
        $stmt = $db->prepare("SELECT * FROM offer_popups WHERE id = :id");
        $stmt->execute([':id' => $popupId]);
        $popup = $stmt->fetch(PDO::FETCH_ASSOC);

        Response::success($popup, 'Popup created successfully');
    } else {
        // File-based storage
        $popups = loadPopups();

        $newPopup = [
            'id' => getNextPopupId($popups),
            'title' => trim($input['title']),
            'description' => isset($input['description']) ? trim($input['description']) : null,
            'image' => isset($input['image']) ? trim($input['image']) : null,
            'coupon_code' => isset($input['coupon_code']) ? trim($input['coupon_code']) : null,
            'cta_text' => isset($input['cta_text']) ? trim($input['cta_text']) : 'Shop Now',
            'cta_link' => isset($input['cta_link']) ? trim($input['cta_link']) : null,
            'display_rule' => $displayRule,
            'is_active' => isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1,
            'start_date' => isset($input['start_date']) && $input['start_date'] !== '' ? $input['start_date'] : null,
            'end_date' => isset($input['end_date']) && $input['end_date'] !== '' ? $input['end_date'] : null,
            'created_by' => $admin['id'],
            'created_at' => date('Y-m-d H:i:s')
        ];

        $popups[] = $newPopup;
        savePopups($popups);

        Response::success($newPopup, 'Popup created successfully');
    }
}

/**
 * PUT - Update popup
 */
function handlePut($admin) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id'])) {
        Response::error('Popup ID is required');
    }

    // Validate display_rule if provided
    if (isset($input['display_rule'])) {
        $validRules = ['first_load', 'every_visit', 'once_per_day', 'once_per_session'];
        if (!in_array($input['display_rule'], $validRules)) {
            Response::error('Invalid display rule');
        }
    }

    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        // Get existing popup
        $stmt = $db->prepare("SELECT * FROM offer_popups WHERE id = :id");
        $stmt->execute([':id' => $input['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            Response::notFound('Popup not found');
        }

        // Build update query
        $allowedFields = ['title', 'description', 'image', 'coupon_code', 'cta_text', 'cta_link', 'display_rule', 'is_active', 'start_date', 'end_date'];
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

        $sql = "UPDATE offer_popups SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        logAdminActivity($db, $admin['id'], 'update', 'popup', $input['id'], $existing, $newValues);

        // Fetch updated popup
        $stmt = $db->prepare("SELECT * FROM offer_popups WHERE id = :id");
        $stmt->execute([':id' => $input['id']]);
        $popup = $stmt->fetch(PDO::FETCH_ASSOC);

        Response::success($popup, 'Popup updated successfully');
    } else {
        // File-based storage
        $popups = loadPopups();
        $found = false;

        foreach ($popups as &$popup) {
            if ($popup['id'] == $input['id']) {
                $found = true;

                // Update fields
                if (array_key_exists('title', $input)) $popup['title'] = trim($input['title']);
                if (array_key_exists('description', $input)) $popup['description'] = trim($input['description']);
                if (array_key_exists('image', $input)) $popup['image'] = trim($input['image']);
                if (array_key_exists('coupon_code', $input)) $popup['coupon_code'] = trim($input['coupon_code']);
                if (array_key_exists('cta_text', $input)) $popup['cta_text'] = trim($input['cta_text']);
                if (array_key_exists('cta_link', $input)) $popup['cta_link'] = trim($input['cta_link']);
                if (array_key_exists('display_rule', $input)) $popup['display_rule'] = $input['display_rule'];
                if (array_key_exists('is_active', $input)) $popup['is_active'] = $input['is_active'] ? 1 : 0;
                if (array_key_exists('start_date', $input)) $popup['start_date'] = $input['start_date'] !== '' ? $input['start_date'] : null;
                if (array_key_exists('end_date', $input)) $popup['end_date'] = $input['end_date'] !== '' ? $input['end_date'] : null;
                $popup['updated_at'] = date('Y-m-d H:i:s');

                savePopups($popups);
                Response::success($popup, 'Popup updated successfully');
            }
        }

        if (!$found) {
            Response::notFound('Popup not found');
        }
    }
}

/**
 * DELETE - Delete popup
 */
function handleDelete($admin) {
    $id = isset($_GET['id']) ? $_GET['id'] : null;

    if (!$id) {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = isset($input['id']) ? $input['id'] : null;
    }

    if (!$id) {
        Response::error('Popup ID is required');
    }

    if ($GLOBALS['useDatabase']) {
        $db = $GLOBALS['db'];

        // Get existing popup
        $stmt = $db->prepare("SELECT * FROM offer_popups WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            Response::notFound('Popup not found');
        }

        $stmt = $db->prepare("DELETE FROM offer_popups WHERE id = :id");
        $stmt->execute([':id' => $id]);

        logAdminActivity($db, $admin['id'], 'delete', 'popup', $id, $existing, null);

        Response::success(null, 'Popup deleted successfully');
    } else {
        // File-based storage
        $popups = loadPopups();
        $found = false;

        foreach ($popups as $index => $popup) {
            if ($popup['id'] == $id) {
                $found = true;
                array_splice($popups, $index, 1);
                savePopups($popups);
                Response::success(null, 'Popup deleted successfully');
            }
        }

        if (!$found) {
            Response::notFound('Popup not found');
        }
    }
}
?>
