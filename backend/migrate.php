<?php
/**
 * Database Migration Script
 * KPS Nursery - Run all database migrations
 */

// Database credentials
$host = '193.203.184.194';
$dbname = 'u282002960_kpsnursery';
$username = 'u282002960_kpsnursery';
$password = 'KpsNusery@123';
$charset = 'utf8mb4';

echo "==============================================\n";
echo "KPS Nursery Database Migration\n";
echo "==============================================\n\n";

try {
    // Connect to database
    echo "Connecting to database...\n";
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=$charset",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    echo "✓ Connected to database successfully!\n\n";

    // Migration SQL statements
    $migrations = [
        // 1. Create admins table
        "admins" => "CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100),
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'manager', 'staff') DEFAULT 'admin',
            is_active TINYINT(1) DEFAULT 1,
            last_login DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 2. Create admin_sessions table
        "admin_sessions" => "CREATE TABLE IF NOT EXISTS admin_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            session_token VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin (admin_id),
            INDEX idx_token (session_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 3. Create categories table
        "categories" => "CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            icon VARCHAR(50),
            image VARCHAR(500),
            display_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 4. Create products table
        "products" => "CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT,
            name VARCHAR(200) NOT NULL,
            description TEXT,
            price DECIMAL(10, 2) NOT NULL,
            discount_price DECIMAL(10, 2),
            unit VARCHAR(20) DEFAULT 'piece',
            stock INT DEFAULT 0,
            image VARCHAR(500),
            rating DECIMAL(3, 2) DEFAULT 0.00,
            reviews INT DEFAULT 0,
            display_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_category (category_id),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 5. Create users table
        "users" => "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100),
            mobile VARCHAR(15) NOT NULL UNIQUE,
            email VARCHAR(100),
            password VARCHAR(255),
            is_guest TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_mobile (mobile)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 6. Create addresses table
        "addresses" => "CREATE TABLE IF NOT EXISTS addresses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(100),
            mobile VARCHAR(15),
            street VARCHAR(500),
            landmark VARCHAR(200),
            city VARCHAR(100),
            state VARCHAR(100),
            pincode VARCHAR(10),
            type ENUM('home', 'work', 'other') DEFAULT 'home',
            is_default TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 7. Create orders table
        "orders" => "CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            order_id VARCHAR(20) NOT NULL UNIQUE,
            status ENUM('pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled') DEFAULT 'pending',
            subtotal DECIMAL(10, 2) NOT NULL,
            delivery_charge DECIMAL(10, 2) DEFAULT 0,
            packing_charge DECIMAL(10, 2) DEFAULT 0,
            total DECIMAL(10, 2) NOT NULL,
            payment_method ENUM('cod', 'online', 'upi', 'card', 'netbanking') DEFAULT 'cod',
            payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
            delivery_type VARCHAR(50) DEFAULT 'Standard',
            shipping_name VARCHAR(100),
            shipping_mobile VARCHAR(15),
            shipping_street VARCHAR(500),
            shipping_landmark VARCHAR(200),
            shipping_city VARCHAR(100),
            shipping_state VARCHAR(100),
            shipping_pincode VARCHAR(10),
            notes TEXT,
            cancelled_reason TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_order_id (order_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 8. Create order_items table
        "order_items" => "CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT,
            product_name VARCHAR(200) NOT NULL,
            price DECIMAL(10, 2) NOT NULL,
            quantity INT NOT NULL,
            subtotal DECIMAL(10, 2) NOT NULL,
            INDEX idx_order (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 9. Create cart table
        "cart" => "CREATE TABLE IF NOT EXISTS cart (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(255) NOT NULL,
            user_id INT,
            product_id INT NOT NULL,
            quantity INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_session (session_id),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 10. Create banners table
        "banners" => "CREATE TABLE IF NOT EXISTS banners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            image VARCHAR(500) NOT NULL,
            title VARCHAR(200),
            caption VARCHAR(500),
            redirect_type ENUM('none', 'product', 'category', 'external') DEFAULT 'none',
            redirect_url VARCHAR(500),
            start_date DATE,
            end_date DATE,
            display_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 11. Create popups table
        "popups" => "CREATE TABLE IF NOT EXISTS popups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            image VARCHAR(500),
            coupon_code VARCHAR(50),
            display_rule ENUM('first_load', 'every_visit', 'once_per_day', 'once_per_session') DEFAULT 'once_per_session',
            cta_text VARCHAR(100) DEFAULT 'Shop Now',
            cta_link VARCHAR(500),
            start_date DATE,
            end_date DATE,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 12. Create notifications table
        "notifications" => "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('order', 'offer', 'system') DEFAULT 'system',
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_read (is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 13. Create serviceable_areas table
        "serviceable_areas" => "CREATE TABLE IF NOT EXISTS serviceable_areas (
            id INT PRIMARY KEY AUTO_INCREMENT,
            area_name VARCHAR(255) NOT NULL,
            lat DECIMAL(10, 8) NOT NULL,
            lng DECIMAL(11, 8) NOT NULL,
            radius_km DECIMAL(5, 2) NOT NULL DEFAULT 5.00,
            delivery_time_minutes INT DEFAULT 30,
            delivery_charge DECIMAL(10, 2) DEFAULT 0.00,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_location (lat, lng),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 14. Create payment_orders table
        "payment_orders" => "CREATE TABLE IF NOT EXISTS payment_orders (
            id INT NOT NULL AUTO_INCREMENT,
            order_id INT NOT NULL,
            user_id INT NOT NULL,
            razorpay_order_id VARCHAR(255) NOT NULL,
            razorpay_payment_id VARCHAR(255) DEFAULT NULL,
            razorpay_signature VARCHAR(512) DEFAULT NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'INR',
            status ENUM('created','paid','failed','cancelled') DEFAULT 'created',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            paid_at TIMESTAMP NULL DEFAULT NULL,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY razorpay_order_id (razorpay_order_id),
            KEY order_id (order_id),
            KEY user_id (user_id),
            KEY status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 15. Create saved_payment_methods table
        "saved_payment_methods" => "CREATE TABLE IF NOT EXISTS saved_payment_methods (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            method_type ENUM('card','upi','netbanking','wallet') DEFAULT 'card',
            card_network VARCHAR(50) DEFAULT NULL,
            last_4_digits VARCHAR(4) DEFAULT NULL,
            is_default TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY is_default (is_default)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 16. Create combo_offers table
        "combo_offers" => "CREATE TABLE IF NOT EXISTS combo_offers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            slug VARCHAR(220),
            description TEXT,
            image VARCHAR(500),
            product_ids JSON NOT NULL,
            original_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            offer_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            discount_percent DECIMAL(5,2) DEFAULT 0,
            status ENUM('active','inactive') DEFAULT 'active',
            show_on_homepage TINYINT(1) DEFAULT 1,
            display_order INT DEFAULT 0,
            start_date DATE,
            end_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_homepage (show_on_homepage),
            INDEX idx_dates (start_date, end_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 17. Create payment_failures table
        "payment_failures" => "CREATE TABLE IF NOT EXISTS payment_failures (
            id INT NOT NULL AUTO_INCREMENT,
            order_id INT DEFAULT NULL,
            error_code VARCHAR(100) DEFAULT NULL,
            error_description TEXT,
            error_source VARCHAR(100) DEFAULT NULL,
            error_step VARCHAR(100) DEFAULT NULL,
            error_reason VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY error_code (error_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    // Run migrations
    echo "Running migrations...\n";
    echo "----------------------------------------------\n";

    foreach ($migrations as $table => $sql) {
        try {
            $pdo->exec($sql);
            echo "✓ Created table: $table\n";
        } catch (PDOException $e) {
            echo "✗ Error creating $table: " . $e->getMessage() . "\n";
        }
    }

    echo "\n----------------------------------------------\n";
    echo "Inserting default data...\n";
    echo "----------------------------------------------\n";

    // Insert default admin (password: admin123)
    $adminCheck = $pdo->query("SELECT id FROM admins WHERE username = 'admin'")->fetch();
    if (!$adminCheck) {
        $pdo->exec("INSERT INTO admins (name, username, email, password, role) VALUES
            ('Administrator', 'admin', 'admin@kpsnursery.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')");
        echo "✓ Inserted default admin user (username: admin, password: admin123)\n";
    } else {
        echo "○ Admin user already exists, skipping...\n";
    }

    // Insert sample categories for nursery
    $catCheck = $pdo->query("SELECT COUNT(*) as cnt FROM categories")->fetch();
    if ($catCheck['cnt'] == 0) {
        $pdo->exec("INSERT INTO categories (name, slug, icon, display_order) VALUES
            ('Indoor Plants', 'indoor-plants', 'fa-leaf', 1),
            ('Outdoor Plants', 'outdoor-plants', 'fa-tree', 2),
            ('Flowering Plants', 'flowering-plants', 'fa-seedling', 3),
            ('Succulents', 'succulents', 'fa-spa', 4),
            ('Pots & Planters', 'pots-planters', 'fa-archive', 5),
            ('Seeds', 'seeds', 'fa-circle', 6),
            ('Fertilizers', 'fertilizers', 'fa-flask', 7),
            ('Garden Tools', 'garden-tools', 'fa-tools', 8)");
        echo "✓ Inserted sample categories for nursery\n";
    } else {
        echo "○ Categories already exist, skipping...\n";
    }

    // Insert sample products
    $prodCheck = $pdo->query("SELECT COUNT(*) as cnt FROM products")->fetch();
    if ($prodCheck['cnt'] == 0) {
        $pdo->exec("INSERT INTO products (category_id, name, description, price, discount_price, unit, stock, image, rating, reviews, is_active) VALUES
            (1, 'Money Plant', 'Beautiful indoor money plant in a ceramic pot. Known for bringing good luck and prosperity.', 299.00, 249.00, 'piece', 50, '', 4.5, 128, 1),
            (1, 'Peace Lily', 'Elegant peace lily plant that purifies indoor air. Perfect for offices and homes.', 450.00, NULL, 'piece', 30, '', 4.7, 89, 1),
            (1, 'Snake Plant', 'Low maintenance snake plant. Excellent air purifier and very hardy.', 350.00, 299.00, 'piece', 40, '', 4.6, 156, 1),
            (2, 'Rose Plant', 'Beautiful red rose plant in pot. Blooms throughout the year with proper care.', 250.00, NULL, 'piece', 60, '', 4.8, 234, 1),
            (2, 'Hibiscus', 'Colorful hibiscus plant with large flowers. Perfect for garden decoration.', 180.00, 150.00, 'piece', 45, '', 4.4, 98, 1),
            (3, 'Marigold', 'Bright yellow marigold plants. Perfect for festivals and garden borders.', 80.00, NULL, 'piece', 100, '', 4.3, 67, 1),
            (4, 'Aloe Vera', 'Medicinal aloe vera plant. Great for skin care and indoor decoration.', 199.00, 149.00, 'piece', 70, '', 4.5, 145, 1),
            (4, 'Jade Plant', 'Lucky jade plant succulent. Symbol of prosperity and good fortune.', 350.00, NULL, 'piece', 35, '', 4.6, 112, 1),
            (5, 'Ceramic Pot Large', 'Premium ceramic pot with drainage hole. Perfect for medium plants.', 450.00, 399.00, 'piece', 25, '', 4.4, 56, 1),
            (5, 'Terracotta Pot Set', 'Set of 3 terracotta pots in different sizes. Traditional and eco-friendly.', 299.00, NULL, 'set', 40, '', 4.5, 78, 1)");
        echo "✓ Inserted sample products for nursery\n";
    } else {
        echo "○ Products already exist, skipping...\n";
    }

    echo "\n==============================================\n";
    echo "Migration completed successfully!\n";
    echo "==============================================\n\n";

    // Show table summary
    echo "Database Tables:\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "  - $table ($count rows)\n";
    }
    echo "\n";

} catch (PDOException $e) {
    echo "✗ Database Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
