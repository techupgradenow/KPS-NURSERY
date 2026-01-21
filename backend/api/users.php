<?php
/**
 * Users API
 * Handle user authentication and management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/db.php';
require_once '../utils/Response.php';
require_once '../utils/Validator.php';

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = isset($input['action']) ? $input['action'] : 'login';

        switch($action) {
            case 'login':
                loginUser($db, $input);
                break;
            case 'guest':
                guestLogin($db);
                break;
            default:
                Response::error('Invalid action');
        }
    } else {
        Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Users API Error: " . $e->getMessage());
    Response::serverError('Failed to process request');
}

function loginUser($db, $data) {
    if (!isset($data['mobile']) || empty($data['mobile'])) {
        Response::error('Mobile number is required');
    }

    $mobileError = Validator::mobile($data['mobile']);
    if ($mobileError) {
        Response::error($mobileError);
    }

    // Check if user exists
    $stmt = $db->prepare("SELECT * FROM users WHERE mobile = :mobile");
    $stmt->execute([':mobile' => $data['mobile']]);
    $user = $stmt->fetch();

    if (!$user) {
        // Create new user
        $stmt = $db->prepare("
            INSERT INTO users (mobile, name, email, is_guest)
            VALUES (:mobile, :name, :email, 0)
        ");
        $stmt->execute([
            ':mobile' => $data['mobile'],
            ':name' => $data['name'] ?? 'User',
            ':email' => $data['email'] ?? null
        ]);

        $userId = $db->lastInsertId();

        $user = [
            'id' => $userId,
            'mobile' => $data['mobile'],
            'name' => $data['name'] ?? 'User',
            'email' => $data['email'] ?? null
        ];
    }

    Response::success($user, 'Login successful');
}

function guestLogin($db) {
    // Create guest user with random mobile
    $guestMobile = 'GUEST' . time();

    $stmt = $db->prepare("
        INSERT INTO users (mobile, name, is_guest)
        VALUES (:mobile, 'Guest User', 1)
    ");
    $stmt->execute([':mobile' => $guestMobile]);

    $userId = $db->lastInsertId();

    $user = [
        'id' => $userId,
        'mobile' => $guestMobile,
        'name' => 'Guest User',
        'is_guest' => true
    ];

    Response::success($user, 'Guest login successful');
}
?>
