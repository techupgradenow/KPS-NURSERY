<?php
/**
 * Clear Banners, Orders, Categories from Database
 * Run once and delete this file
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/db.php';

try {
    $db = getDB();
    $results = [];

    // Clear Orders
    $stmt = $db->prepare("DELETE FROM orders");
    $stmt->execute();
    $results['orders'] = $stmt->rowCount();
    $db->exec("ALTER TABLE orders AUTO_INCREMENT = 1");

    // Clear Order Items (if exists)
    try {
        $stmt = $db->prepare("DELETE FROM order_items");
        $stmt->execute();
        $results['order_items'] = $stmt->rowCount();
        $db->exec("ALTER TABLE order_items AUTO_INCREMENT = 1");
    } catch (Exception $e) {
        $results['order_items'] = 'table not found';
    }

    // Clear Banners
    $stmt = $db->prepare("DELETE FROM banners");
    $stmt->execute();
    $results['banners'] = $stmt->rowCount();
    $db->exec("ALTER TABLE banners AUTO_INCREMENT = 1");

    // Clear Categories
    $stmt = $db->prepare("DELETE FROM categories");
    $stmt->execute();
    $results['categories'] = $stmt->rowCount();
    $db->exec("ALTER TABLE categories AUTO_INCREMENT = 1");

    // Clear Products (since categories are cleared)
    $stmt = $db->prepare("DELETE FROM products");
    $stmt->execute();
    $results['products'] = $stmt->rowCount();
    $db->exec("ALTER TABLE products AUTO_INCREMENT = 1");

    echo json_encode([
        'success' => true,
        'message' => 'All data cleared successfully!',
        'deleted' => $results
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
