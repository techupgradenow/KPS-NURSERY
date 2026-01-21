<?php
/**
 * Clear All Products from Database
 * Run once and delete this file
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/db.php';

try {
    $db = getDB();

    // Delete all products
    $stmt = $db->prepare("DELETE FROM products");
    $stmt->execute();
    $deletedCount = $stmt->rowCount();

    // Reset auto-increment
    $db->exec("ALTER TABLE products AUTO_INCREMENT = 1");

    echo json_encode([
        'success' => true,
        'message' => "Successfully deleted $deletedCount products",
        'deleted_count' => $deletedCount
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
