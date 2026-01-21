<?php
/**
 * Banner System Setup
 * Creates/Updates the banners table with desktop and mobile image support
 */

header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<html><head><title>Banner System Setup</title>";
echo "<style>
    body { font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; background: #f0fdf4; max-width: 800px; margin: 0 auto; }
    h1 { color: #166534; }
    .success { color: #22c55e; font-weight: bold; }
    .error { color: #dc2626; font-weight: bold; }
    .step { margin: 10px 0; padding: 12px; background: white; border-radius: 8px; border-left: 4px solid #22c55e; }
    pre { background: #1f2937; color: #f9fafb; padding: 15px; border-radius: 8px; overflow-x: auto; }
</style></head><body>";

echo "<h1>🖼️ Banner System Setup</h1>";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=freshchicken_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<div class='step success'>✅ Connected to database</div>";

    // Check if banners table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'banners'");
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        // Create new banners table
        $pdo->exec("
            CREATE TABLE banners (
                id INT PRIMARY KEY AUTO_INCREMENT,
                title VARCHAR(255),
                description TEXT,
                desktop_image VARCHAR(500) NOT NULL,
                mobile_image VARCHAR(500) NOT NULL,
                redirect_type ENUM('none', 'product', 'category', 'external') DEFAULT 'none',
                redirect_url VARCHAR(500),
                display_order INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_active (is_active),
                INDEX idx_order (display_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<div class='step success'>✅ Created new banners table with desktop/mobile image support</div>";
    } else {
        // Check existing columns
        $stmt = $pdo->query("DESCRIBE banners");
        $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

        // Add missing columns
        $columnsToAdd = [
            'desktop_image' => "VARCHAR(500) AFTER description",
            'mobile_image' => "VARCHAR(500) AFTER desktop_image",
            'description' => "TEXT AFTER title"
        ];

        foreach ($columnsToAdd as $col => $def) {
            if (!in_array($col, $columns)) {
                try {
                    $pdo->exec("ALTER TABLE banners ADD COLUMN $col $def");
                    echo "<div class='step success'>✅ Added column: $col</div>";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                        echo "<div class='step error'>❌ Error adding $col: " . $e->getMessage() . "</div>";
                    }
                }
            }
        }

        // Migrate old 'image' column to 'desktop_image' if needed
        if (in_array('image', $columns) && in_array('desktop_image', $columns)) {
            $pdo->exec("UPDATE banners SET desktop_image = image WHERE desktop_image IS NULL OR desktop_image = ''");
            $pdo->exec("UPDATE banners SET mobile_image = image WHERE mobile_image IS NULL OR mobile_image = ''");
            echo "<div class='step success'>✅ Migrated existing images to desktop_image and mobile_image</div>";
        }

        // Rename 'caption' to 'description' if exists
        if (in_array('caption', $columns) && !in_array('description', $columns)) {
            $pdo->exec("ALTER TABLE banners CHANGE caption description TEXT");
            echo "<div class='step success'>✅ Renamed 'caption' column to 'description'</div>";
        }

        echo "<div class='step'>✅ Banners table structure verified</div>";
    }

    // Show current table structure
    echo "<h2>Current Table Structure:</h2>";
    $stmt = $pdo->query("DESCRIBE banners");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    foreach ($columns as $col) {
        echo sprintf("%-20s %-30s %s\n", $col['Field'], $col['Type'], $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL');
    }
    echo "</pre>";

    // Count existing banners
    $stmt = $pdo->query("SELECT COUNT(*) FROM banners");
    $count = $stmt->fetchColumn();
    echo "<div class='step'>📊 Current banners: <strong>$count</strong></div>";

    echo "<div class='step success' style='margin-top: 20px;'>";
    echo "<h3>🎉 Setup Complete!</h3>";
    echo "<p><a href='../../frontend/pages/admin/banners.html'>Go to Banner Management →</a></p>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div class='step error'>❌ Database Error: " . $e->getMessage() . "</div>";
}

echo "</body></html>";
?>
