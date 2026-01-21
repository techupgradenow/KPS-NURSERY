<?php
/**
 * Addresses API
 * Handle address management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/db.php';
require_once '../utils/Response.php';
require_once '../utils/Validator.php';

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            getAddresses($db);
            break;
        case 'POST':
            createAddress($db);
            break;
        case 'PUT':
            updateAddress($db);
            break;
        case 'DELETE':
            deleteAddress($db);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Addresses API Error: " . $e->getMessage());
    Response::serverError('Failed to process request');
}

function getAddresses($db) {
    $userId = isset($_GET['user_id']) ? $_GET['user_id'] : null;

    if (!$userId) {
        Response::error('User ID is required');
    }

    $stmt = $db->prepare("SELECT * FROM addresses WHERE user_id = :user_id ORDER BY is_default DESC, created_at DESC");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $addresses = $stmt->fetchAll();

    Response::success($addresses);
}

function createAddress($db) {
    $input = json_decode(file_get_contents('php://input'), true);

    $rules = [
        'user_id' => [['type' => 'required', 'label' => 'User ID']],
        'name' => [['type' => 'required', 'label' => 'Name']],
        'mobile' => [['type' => 'required'], ['type' => 'mobile']],
        'address' => [['type' => 'required', 'label' => 'Address']],
        'pincode' => [['type' => 'required'], ['type' => 'pincode']]
    ];

    $errors = Validator::validateFields($input, $rules);
    if (!empty($errors)) {
        Response::error('Validation failed', 400, $errors);
    }

    try {
        $db->beginTransaction();

        // If this is default address, unset others
        if (isset($input['is_default']) && $input['is_default']) {
            $stmt = $db->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $input['user_id']]);
        }

        $stmt = $db->prepare("
            INSERT INTO addresses (user_id, name, mobile, address, landmark, pincode, city, state, type, is_default)
            VALUES (:user_id, :name, :mobile, :address, :landmark, :pincode, :city, :state, :type, :is_default)
        ");

        $stmt->execute([
            ':user_id' => $input['user_id'],
            ':name' => $input['name'],
            ':mobile' => $input['mobile'],
            ':address' => $input['address'],
            ':landmark' => $input['landmark'] ?? '',
            ':pincode' => $input['pincode'],
            ':city' => $input['city'] ?? 'Bangalore',
            ':state' => $input['state'] ?? 'Karnataka',
            ':type' => $input['type'] ?? 'home',
            ':is_default' => $input['is_default'] ?? 0
        ]);

        $db->commit();
        Response::success(['id' => $db->lastInsertId()], 'Address created successfully', 201);

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Create Address Error: " . $e->getMessage());
        Response::serverError('Failed to create address');
    }
}

function updateAddress($db) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id'])) {
        Response::error('Address ID is required');
    }

    try {
        $db->beginTransaction();

        if (isset($input['is_default']) && $input['is_default']) {
            $stmt = $db->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $input['user_id']]);
        }

        $stmt = $db->prepare("
            UPDATE addresses
            SET name = :name, mobile = :mobile, address = :address,
                landmark = :landmark, pincode = :pincode, type = :type, is_default = :is_default
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $input['id'],
            ':name' => $input['name'],
            ':mobile' => $input['mobile'],
            ':address' => $input['address'],
            ':landmark' => $input['landmark'] ?? '',
            ':pincode' => $input['pincode'],
            ':type' => $input['type'] ?? 'home',
            ':is_default' => $input['is_default'] ?? 0
        ]);

        $db->commit();
        Response::success(null, 'Address updated successfully');

    } catch (Exception $e) {
        $db->rollBack();
        Response::serverError('Failed to update address');
    }
}

function deleteAddress($db) {
    $id = isset($_GET['id']) ? $_GET['id'] : null;

    if (!$id) {
        Response::error('Address ID is required');
    }

    $stmt = $db->prepare("DELETE FROM addresses WHERE id = :id");
    $stmt->execute([':id' => $id]);

    Response::success(null, 'Address deleted successfully');
}
?>
