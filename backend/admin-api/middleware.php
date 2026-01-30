<?php
/**
 * Admin API Middleware
 * Authentication and authorization helpers
 * KPS Nursery Admin Panel
 * Supports both database and file-based storage
 */

// Set timezone to match MySQL server
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/Response.php';

// File-based storage paths
define('ADMIN_DATA_DIR', __DIR__ . '/../data/');
define('ADMINS_FILE', ADMIN_DATA_DIR . 'admins.json');
define('SESSIONS_FILE', ADMIN_DATA_DIR . 'admin_sessions.json');

// Global variables for storage mode
$GLOBALS['useDatabase'] = false;
$GLOBALS['db'] = null;

/**
 * Initialize storage (database or file)
 */
function initStorage() {
    try {
        $db = @getDB();
        if ($db) {
            $test = @$db->query("SELECT 1 FROM admins LIMIT 1");
            if ($test !== false) {
                $GLOBALS['useDatabase'] = true;
                $GLOBALS['db'] = $db;
                return $db;
            }
        }
    } catch (Exception $e) {
        // Database not available
    }

    // Use file-based storage
    $GLOBALS['useDatabase'] = false;
    initFileStorage();
    return null;
}

function initFileStorage() {
    if (!is_dir(ADMIN_DATA_DIR)) {
        mkdir(ADMIN_DATA_DIR, 0777, true);
    }

    if (!file_exists(ADMINS_FILE)) {
        $defaultAdmin = [
            [
                'id' => 1,
                'username' => 'admin',
                'email' => 'admin@kpsnursery.com',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'name' => 'Super Admin',
                'role' => 'admin',
                'avatar' => null,
                'is_active' => 1,
                'last_login' => null,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
        file_put_contents(ADMINS_FILE, json_encode($defaultAdmin, JSON_PRETTY_PRINT));
    }

    if (!file_exists(SESSIONS_FILE)) {
        file_put_contents(SESSIONS_FILE, json_encode([], JSON_PRETTY_PRINT));
    }
}

function loadAdmins() {
    if (!file_exists(ADMINS_FILE)) return [];
    return json_decode(file_get_contents(ADMINS_FILE), true) ?: [];
}

function loadSessions() {
    if (!file_exists(SESSIONS_FILE)) return [];
    $sessions = json_decode(file_get_contents(SESSIONS_FILE), true) ?: [];

    // Auto-cleanup: Remove expired sessions
    $now = time();
    $validSessions = array_filter($sessions, function($s) use ($now) {
        return isset($s['expires_at']) && strtotime($s['expires_at']) > $now;
    });

    // Save if cleanup happened
    if (count($validSessions) < count($sessions)) {
        file_put_contents(SESSIONS_FILE, json_encode(array_values($validSessions), JSON_PRETTY_PRINT));
    }

    return array_values($validSessions);
}

function saveSessions($sessions) {
    // Filter out expired sessions before saving
    $now = time();
    $validSessions = array_filter($sessions, function($s) use ($now) {
        return isset($s['expires_at']) && strtotime($s['expires_at']) > $now;
    });
    file_put_contents(SESSIONS_FILE, json_encode(array_values($validSessions), JSON_PRETTY_PRINT));
}

function findAdminById($admins, $id) {
    foreach ($admins as $admin) {
        if ($admin['id'] == $id) return $admin;
    }
    return null;
}

function findSessionByToken($sessions, $token) {
    foreach ($sessions as $session) {
        if ($session['session_token'] === $token) return $session;
    }
    return null;
}

/**
 * Verify admin session and return admin data
 */
function requireAuth($db, $sessionToken, $requiredRole = null) {
    if (empty($sessionToken)) {
        Response::error('Authentication required', 401);
    }

    $useDatabase = $GLOBALS['useDatabase'];

    if ($useDatabase && $db) {
        // Database-based auth
        $stmt = $db->prepare("
            SELECT a.*
            FROM admin_sessions s
            JOIN admins a ON s.admin_id = a.id
            WHERE s.session_token = :token AND s.expires_at > NOW() AND a.is_active = 1
        ");
        $stmt->execute([':token' => $sessionToken]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            Response::error('Invalid or expired session', 401);
        }

        // Check role
        if ($requiredRole && $admin['role'] !== $requiredRole && $admin['role'] !== 'admin') {
            Response::error('Insufficient permissions', 403);
        }

        // Extend session
        $newExpiresAt = date('Y-m-d H:i:s', time() + 1800);
        $stmt = $db->prepare("UPDATE admin_sessions SET expires_at = :expires WHERE session_token = :token");
        $stmt->execute([':expires' => $newExpiresAt, ':token' => $sessionToken]);

        return $admin;
    } else {
        // File-based auth
        $sessions = loadSessions();
        $session = findSessionByToken($sessions, $sessionToken);

        if (!$session || strtotime($session['expires_at']) < time()) {
            Response::error('Invalid or expired session', 401);
        }

        $admins = loadAdmins();
        $admin = findAdminById($admins, $session['admin_id']);

        if (!$admin || !$admin['is_active']) {
            Response::error('Invalid or expired session', 401);
        }

        // Check role
        if ($requiredRole && $admin['role'] !== $requiredRole && $admin['role'] !== 'admin') {
            Response::error('Insufficient permissions', 403);
        }

        // Extend session
        foreach ($sessions as &$s) {
            if ($s['session_token'] === $sessionToken) {
                $s['expires_at'] = date('Y-m-d H:i:s', time() + 1800);
                break;
            }
        }
        saveSessions($sessions);

        return $admin;
    }
}

/**
 * Get session token from request headers or body
 */
function getSessionToken() {
    // Check Authorization header (case-insensitive)
    $headers = getallheaders();

    // Normalize header keys to lowercase for case-insensitive matching
    $normalizedHeaders = [];
    if ($headers) {
        foreach ($headers as $key => $value) {
            $normalizedHeaders[strtolower($key)] = $value;
        }
    }

    if (isset($normalizedHeaders['authorization'])) {
        $auth = $normalizedHeaders['authorization'];
        if (preg_match('/Bearer\s+(.+)$/i', $auth, $matches)) {
            return $matches[1];
        }
    }

    // Also check Apache-specific header
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'];
        if (preg_match('/Bearer\s+(.+)$/i', $auth, $matches)) {
            return $matches[1];
        }
    }

    // Check for redirect auth (CGI/FastCGI)
    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        if (preg_match('/Bearer\s+(.+)$/i', $auth, $matches)) {
            return $matches[1];
        }
    }

    // Check request body (for non-multipart requests)
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'multipart/form-data') === false) {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['session_token'])) {
            return $input['session_token'];
        }
    }

    // Check POST/GET parameter
    if (isset($_POST['session_token'])) {
        return $_POST['session_token'];
    }

    // Check query parameter
    if (isset($_GET['token'])) {
        return $_GET['token'];
    }

    return null;
}

/**
 * Log admin activity (only if database is available)
 */
function logAdminActivity($db, $adminId, $action, $entityType, $entityId = null, $oldValues = null, $newValues = null) {
    if (!$GLOBALS['useDatabase'] || !$db) {
        return; // Skip logging if no database
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
            VALUES (:admin_id, :action, :entity_type, :entity_id, :old_values, :new_values, :ip, :ua)
        ");
        $stmt->execute([
            ':admin_id' => $adminId,
            ':action' => $action,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':old_values' => $oldValues ? json_encode($oldValues) : null,
            ':new_values' => $newValues ? json_encode($newValues) : null,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Set CORS headers
 */
function setCORSHeaders() {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

/**
 * Get pagination parameters from request
 */
function getPagination($default_limit = 20) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : $default_limit;
    $offset = ($page - 1) * $limit;

    return [
        'page' => $page,
        'limit' => $limit,
        'offset' => $offset
    ];
}

/**
 * Format pagination response
 */
function paginatedResponse($data, $total, $pagination) {
    return [
        'data' => $data,
        'pagination' => [
            'current_page' => $pagination['page'],
            'per_page' => $pagination['limit'],
            'total' => $total,
            'total_pages' => ceil($total / $pagination['limit'])
        ]
    ];
}

// Initialize storage on include
initStorage();
?>
