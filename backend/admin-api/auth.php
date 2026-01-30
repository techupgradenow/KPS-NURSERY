<?php
/**
 * Admin Authentication API
 * Handle admin login, logout, session management
 * SK Bakers Admin Panel
 * Supports both database and file-based storage
 */

date_default_timezone_set('Asia/Kolkata');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../utils/Response.php';
require_once '../config/db.php';

// Session timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 1800);

// File-based storage paths
define('ADMIN_DATA_DIR', __DIR__ . '/../data/');
define('ADMINS_FILE', ADMIN_DATA_DIR . 'admins.json');
define('SESSIONS_FILE', ADMIN_DATA_DIR . 'admin_sessions.json');

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Get request body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    Response::error('Invalid request');
}

$action = $input['action'];

// Try database connection, fallback to file storage
$db = null;
$useDatabase = false;

try {
    $db = @getDB();
    if ($db) {
        // Check if admins table exists
        $test = @$db->query("SELECT 1 FROM admins LIMIT 1");
        if ($test !== false) {
            $useDatabase = true;
        }
    }
} catch (Exception $e) {
    $useDatabase = false;
}

// Initialize file storage if needed
if (!$useDatabase) {
    initFileStorage();
}

try {
    switch ($action) {
        case 'login':
            handleLogin($input, $db, $useDatabase);
            break;
        case 'logout':
            handleLogout($input, $db, $useDatabase);
            break;
        case 'verify_session':
            verifySession($input, $db, $useDatabase);
            break;
        case 'forgot_password':
            handleForgotPassword($input, $db, $useDatabase);
            break;
        case 'reset_password':
            handleResetPassword($input, $db, $useDatabase);
            break;
        case 'change_password':
            handleChangePassword($input, $db, $useDatabase);
            break;
        default:
            Response::error('Invalid action');
    }
} catch (Exception $e) {
    error_log("Admin Auth API Error: " . $e->getMessage());
    Response::serverError('Authentication failed');
}

// ============================================
// File Storage Functions
// ============================================

function initFileStorage() {
    // Create data directory if not exists
    if (!is_dir(ADMIN_DATA_DIR)) {
        mkdir(ADMIN_DATA_DIR, 0777, true);
    }

    // Initialize admins file with default admin
    if (!file_exists(ADMINS_FILE)) {
        $defaultAdmin = [
            [
                'id' => 1,
                'username' => 'admin',
                'email' => 'admin@skbakers.com',
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

    // Initialize sessions file
    if (!file_exists(SESSIONS_FILE)) {
        file_put_contents(SESSIONS_FILE, json_encode([], JSON_PRETTY_PRINT));
    }
}

function loadAdmins() {
    if (!file_exists(ADMINS_FILE)) {
        return [];
    }
    return json_decode(file_get_contents(ADMINS_FILE), true) ?: [];
}

function saveAdmins($admins) {
    file_put_contents(ADMINS_FILE, json_encode($admins, JSON_PRETTY_PRINT));
}

function loadSessions() {
    if (!file_exists(SESSIONS_FILE)) {
        return [];
    }
    return json_decode(file_get_contents(SESSIONS_FILE), true) ?: [];
}

function saveSessions($sessions) {
    file_put_contents(SESSIONS_FILE, json_encode($sessions, JSON_PRETTY_PRINT));
}

function findAdminByUsername($admins, $username) {
    foreach ($admins as $admin) {
        if ($admin['username'] === $username || $admin['email'] === $username) {
            return $admin;
        }
    }
    return null;
}

function findAdminById($admins, $id) {
    foreach ($admins as $admin) {
        if ($admin['id'] == $id) {
            return $admin;
        }
    }
    return null;
}

function findSessionByToken($sessions, $token) {
    foreach ($sessions as $session) {
        if ($session['session_token'] === $token) {
            return $session;
        }
    }
    return null;
}

// ============================================
// API Handler Functions
// ============================================

function handleLogin($input, $db, $useDatabase) {
    if (!isset($input['username']) || empty($input['username'])) {
        Response::error('Username is required');
    }

    if (!isset($input['password']) || empty($input['password'])) {
        Response::error('Password is required');
    }

    $username = trim($input['username']);
    $password = $input['password'];

    if ($useDatabase) {
        // Database login
        $field = filter_var($username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $stmt = $db->prepare("SELECT * FROM admins WHERE $field = :username AND is_active = 1");
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // File-based login
        $admins = loadAdmins();
        $admin = findAdminByUsername($admins, $username);
        if ($admin && !$admin['is_active']) {
            $admin = null;
        }
    }

    if (!$admin) {
        Response::error('Invalid credentials');
    }

    // Verify password
    if (!password_verify($password, $admin['password'])) {
        Response::error('Invalid credentials');
    }

    // Generate session token
    $sessionToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);

    if ($useDatabase) {
        // Store session in database
        $stmt = $db->prepare("
            INSERT INTO admin_sessions (admin_id, session_token, ip_address, user_agent, expires_at)
            VALUES (:admin_id, :token, :ip, :ua, :expires)
        ");
        $stmt->execute([
            ':admin_id' => $admin['id'],
            ':token' => $sessionToken,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':expires' => $expiresAt
        ]);

        // Update last login
        $stmt = $db->prepare("UPDATE admins SET last_login = NOW() WHERE id = :id");
        $stmt->execute([':id' => $admin['id']]);
    } else {
        // Store session in file
        $sessions = loadSessions();
        $sessions[] = [
            'id' => count($sessions) + 1,
            'admin_id' => $admin['id'],
            'session_token' => $sessionToken,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s')
        ];
        saveSessions($sessions);

        // Update last login in file
        $admins = loadAdmins();
        foreach ($admins as &$a) {
            if ($a['id'] == $admin['id']) {
                $a['last_login'] = date('Y-m-d H:i:s');
                break;
            }
        }
        saveAdmins($admins);
    }

    // Remove sensitive data
    unset($admin['password']);
    unset($admin['password_reset_token']);
    unset($admin['password_reset_expires']);

    Response::success([
        'admin' => $admin,
        'session_token' => $sessionToken,
        'expires_at' => $expiresAt
    ], 'Login successful');
}

function handleLogout($input, $db, $useDatabase) {
    if (!isset($input['session_token']) || empty($input['session_token'])) {
        Response::error('Session token is required');
    }

    $sessionToken = $input['session_token'];

    if ($useDatabase) {
        $stmt = $db->prepare("DELETE FROM admin_sessions WHERE session_token = :token");
        $stmt->execute([':token' => $sessionToken]);
    } else {
        $sessions = loadSessions();
        $sessions = array_filter($sessions, function($s) use ($sessionToken) {
            return $s['session_token'] !== $sessionToken;
        });
        saveSessions(array_values($sessions));
    }

    Response::success(null, 'Logged out successfully');
}

function verifySession($input, $db, $useDatabase) {
    if (!isset($input['session_token']) || empty($input['session_token'])) {
        Response::error('Session token is required', 401);
    }

    $sessionToken = $input['session_token'];

    if ($useDatabase) {
        $stmt = $db->prepare("
            SELECT s.*, a.*
            FROM admin_sessions s
            JOIN admins a ON s.admin_id = a.id
            WHERE s.session_token = :token AND s.expires_at > NOW() AND a.is_active = 1
        ");
        $stmt->execute([':token' => $sessionToken]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            Response::error('Invalid or expired session', 401);
        }

        // Extend session
        $newExpiresAt = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
        $stmt = $db->prepare("UPDATE admin_sessions SET expires_at = :expires WHERE session_token = :token");
        $stmt->execute([':expires' => $newExpiresAt, ':token' => $sessionToken]);
    } else {
        // File-based session verification
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

        $result = $admin;

        // Extend session
        $newExpiresAt = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
        foreach ($sessions as &$s) {
            if ($s['session_token'] === $sessionToken) {
                $s['expires_at'] = $newExpiresAt;
                break;
            }
        }
        saveSessions($sessions);
    }

    // Remove sensitive data
    unset($result['password']);
    unset($result['password_reset_token']);
    unset($result['password_reset_expires']);
    unset($result['session_token']);

    Response::success([
        'admin' => $result,
        'expires_at' => $newExpiresAt
    ], 'Session valid');
}

function handleForgotPassword($input, $db, $useDatabase) {
    Response::success(null, 'If this email exists, a password reset link has been sent');
}

function handleResetPassword($input, $db, $useDatabase) {
    Response::error('Password reset via token is not available in demo mode');
}

function handleChangePassword($input, $db, $useDatabase) {
    if (!isset($input['session_token']) || empty($input['session_token'])) {
        Response::error('Session token is required', 401);
    }

    if (!isset($input['current_password']) || empty($input['current_password'])) {
        Response::error('Current password is required');
    }

    if (!isset($input['new_password']) || empty($input['new_password'])) {
        Response::error('New password is required');
    }

    $sessionToken = $input['session_token'];

    if ($useDatabase) {
        $stmt = $db->prepare("
            SELECT a.* FROM admin_sessions s
            JOIN admins a ON s.admin_id = a.id
            WHERE s.session_token = :token AND s.expires_at > NOW()
        ");
        $stmt->execute([':token' => $sessionToken]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $sessions = loadSessions();
        $session = findSessionByToken($sessions, $sessionToken);
        if (!$session || strtotime($session['expires_at']) < time()) {
            Response::error('Invalid session', 401);
        }
        $admins = loadAdmins();
        $admin = findAdminById($admins, $session['admin_id']);
    }

    if (!$admin) {
        Response::error('Invalid session', 401);
    }

    if (!password_verify($input['current_password'], $admin['password'])) {
        Response::error('Current password is incorrect');
    }

    if (strlen($input['new_password']) < 8) {
        Response::error('New password must be at least 8 characters');
    }

    $hashedPassword = password_hash($input['new_password'], PASSWORD_DEFAULT);

    if ($useDatabase) {
        $stmt = $db->prepare("UPDATE admins SET password = :password WHERE id = :id");
        $stmt->execute([':password' => $hashedPassword, ':id' => $admin['id']]);
    } else {
        $admins = loadAdmins();
        foreach ($admins as &$a) {
            if ($a['id'] == $admin['id']) {
                $a['password'] = $hashedPassword;
                break;
            }
        }
        saveAdmins($admins);
    }

    Response::success(null, 'Password changed successfully');
}
?>
