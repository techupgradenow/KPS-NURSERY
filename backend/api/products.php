<?php
/**
 * Products API
 * Handle all product-related requests
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/db.php';
require_once '../utils/Response.php';

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// Ensure is_popular column exists
try {
    $stmt = $db->query("DESCRIBE products");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('is_popular', $columns)) {
        $db->exec("ALTER TABLE products ADD COLUMN is_popular TINYINT(1) DEFAULT 0 AFTER is_active");
    }
} catch (Exception $e) {
    // Column check failed, continue anyway
}

try {
    switch($method) {
        case 'GET':
            handleGet($db);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Products API Error: " . $e->getMessage());
    Response::serverError('Failed to process request');
}

/**
 * Handle GET requests
 */
function handleGet($db) {
    $action = isset($_GET['action']) ? $_GET['action'] : 'all';

    switch($action) {
        case 'all':
            getAllProducts($db);
            break;
        case 'category':
            getProductsByCategory($db);
            break;
        case 'detail':
            getProductDetail($db);
            break;
        case 'search':
            searchProducts($db);
            break;
        case 'popular':
            getPopularProducts($db);
            break;
        default:
            Response::error('Invalid action');
    }
}

/**
 * Normalize image path for frontend pages
 * Converts various path formats to consistent ../assets/ format
 */
function normalizeImagePath($path) {
    if (empty($path)) return '';

    // If it's a full URL, return as is
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return $path;
    }

    // Normalize path separators
    $path = str_replace('\\', '/', $path);

    // Extract filename from various path formats
    // Handle: ../../assets/uploads/x.png, ../assets/x.png, assets/uploads/x.png, etc.
    if (strpos($path, 'assets/uploads/') !== false) {
        $filename = substr($path, strrpos($path, 'assets/uploads/'));
        return '../' . $filename;
    }

    if (strpos($path, 'assets/') !== false) {
        $filename = substr($path, strrpos($path, 'assets/'));
        return '../' . $filename;
    }

    // If just a filename, assume it's in assets folder
    if (strpos($path, '/') === false) {
        return '../assets/' . $path;
    }

    return $path;
}

/**
 * Get all products
 */
function getAllProducts($db) {
    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name, c.slug as category_slug
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    $products = $stmt->fetchAll();

    // Normalize image paths
    foreach ($products as &$product) {
        $product['image'] = normalizeImagePath($product['image']);
    }

    Response::success($products);
}

/**
 * Get products by category
 */
function getProductsByCategory($db) {
    if (!isset($_GET['category'])) {
        Response::error('Category parameter is required');
    }

    $category = $_GET['category'];
    $categoryLower = strtolower($category);

    // Try to match by slug first (case-insensitive), then by name
    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name, c.slug as category_slug
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE (LOWER(c.slug) = ? OR LOWER(c.name) = ?) AND p.is_active = 1
        ORDER BY p.display_order ASC, p.created_at DESC
    ");
    $stmt->execute([$categoryLower, $categoryLower]);
    $products = $stmt->fetchAll();

    // Normalize image paths
    foreach ($products as &$product) {
        $product['image'] = normalizeImagePath($product['image']);
    }

    Response::success($products);
}

/**
 * Get product detail
 */
function getProductDetail($db) {
    if (!isset($_GET['id'])) {
        Response::error('Product ID is required');
    }

    $id = $_GET['id'];

    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name, c.slug as category_slug
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.id = :id AND p.is_active = 1
    ");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $product = $stmt->fetch();

    if (!$product) {
        Response::notFound('Product not found');
    }

    // Normalize image path
    $product['image'] = normalizeImagePath($product['image']);

    Response::success($product);
}

/**
 * Search products
 */
function searchProducts($db) {
    if (!isset($_GET['q']) || trim($_GET['q']) === '') {
        Response::error('Search query is required');
    }

    $searchTerm = '%' . trim($_GET['q']) . '%';

    try {
        $stmt = $db->prepare("
            SELECT p.*, c.name as category_name, c.slug as category_slug
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE (p.name LIKE ? OR p.description LIKE ?) AND p.is_active = 1
            ORDER BY p.name ASC
            LIMIT 20
        ");
        $stmt->execute([$searchTerm, $searchTerm]);
        $products = $stmt->fetchAll();

        // Normalize image paths
        foreach ($products as &$product) {
            $product['image'] = normalizeImagePath($product['image']);
        }

        Response::success($products);
    } catch (Exception $e) {
        error_log("Search Error: " . $e->getMessage());
        Response::serverError('Search failed: ' . $e->getMessage());
    }
}

/**
 * Get popular products (highest rating)
 */
function getPopularProducts($db) {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name, c.slug as category_slug
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1
        ORDER BY p.rating DESC, p.reviews DESC
        LIMIT :limit
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();

    // Normalize image paths
    foreach ($products as &$product) {
        $product['image'] = normalizeImagePath($product['image']);
    }

    Response::success($products);
}
?>
