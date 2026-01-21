<?php
/**
 * Categories API
 * Handle all category-related requests
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/db.php';
require_once '../utils/Response.php';

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        getCategories($db);
    } else {
        Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Categories API Error: " . $e->getMessage());
    Response::serverError('Failed to process request');
}

/**
 * Get all active categories
 */
function getCategories($db) {
    $stmt = $db->prepare("
        SELECT id, name, slug, icon, display_order
        FROM categories
        WHERE is_active = 1
        ORDER BY display_order ASC, name ASC
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll();

    Response::success($categories);
}
?>
