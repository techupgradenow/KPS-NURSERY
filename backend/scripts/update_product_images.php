<?php
/**
 * Update Product Images Script
 * Updates all products to use local images instead of Unsplash URLs
 */

require_once '../config/db.php';

$db = getDB();

// Mapping of product IDs to local image paths
$imageMapping = [
    // Chicken Products
    1 => '../assets/images/products/Whole-Chicken.jpg',
    2 => '../assets/images/products/Chicken Curry Cut.jpg',
    3 => '../assets/images/products/Boneless-Chicken.jpg',
    4 => '../assets/images/placeholders/product-placeholder.png', // No image available
    5 => '../assets/images/products/Chicken-Legs.jpg',
    6 => '../assets/images/placeholders/product-placeholder.png', // No image available

    // Fish Products
    7 => '../assets/images/products/Seer-Fish.jpg',
    8 => '../assets/images/products/Pomfret.jpg',
    9 => '../assets/images/products/Prawns.jpg',
    10 => '../assets/images/products/Nethili.jpg',
    11 => '../assets/images/products/Rohu-Fish.jpg',
    12 => '../assets/images/products/Sardines.jpg'
];

$updated = 0;
$failed = 0;

echo "Starting product image update...\n\n";

foreach ($imageMapping as $productId => $imagePath) {
    try {
        $stmt = $db->prepare("UPDATE products SET image = :image WHERE id = :id");
        $stmt->bindParam(':image', $imagePath);
        $stmt->bindParam(':id', $productId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo "✅ Updated product ID $productId → $imagePath\n";
            $updated++;
        } else {
            echo "❌ Failed to update product ID $productId\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "❌ Error updating product ID $productId: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n========================================\n";
echo "Update Complete!\n";
echo "========================================\n";
echo "✅ Successfully updated: $updated products\n";
if ($failed > 0) {
    echo "❌ Failed: $failed products\n";
}
echo "========================================\n\n";

// Verify the updates
echo "Verifying updates...\n\n";

$stmt = $db->prepare("SELECT id, name, image FROM products ORDER BY id");
$stmt->execute();
$products = $stmt->fetchAll();

foreach ($products as $product) {
    $status = '✅';
    if (strpos($product['image'], 'unsplash.com') !== false) {
        $status = '❌ Still external';
    } elseif (strpos($product['image'], 'placeholder') !== false) {
        $status = '⚠️ Placeholder';
    }

    echo sprintf("%-4s ID: %-2d %-25s %s\n",
        $status,
        $product['id'],
        substr($product['name'], 0, 25),
        basename($product['image'])
    );
}

echo "\n========================================\n";
echo "All products updated successfully!\n";
echo "Visit: http://localhost/chicken-shop-app/frontend/pages/home.html\n";
echo "========================================\n";
?>
