<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();
$stmt = $db->prepare('UPDATE products SET image = "../assets/images/placeholders/product-placeholder.svg" WHERE id IN (4, 6)');
$stmt->execute();
echo "✅ Updated placeholder references to .svg\n";
?>
