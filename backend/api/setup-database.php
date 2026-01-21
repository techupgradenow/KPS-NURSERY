<?php
/**
 * Database Setup - Creates all required tables
 * Run once and delete this file
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$dbHost = '193.203.184.194';
$dbName = 'u282002960_kidai';
$dbUser = 'u282002960_kidai';
$dbPass = 'Ufcrxq9iwYXuXuyr';

try {
    $db = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $results = [];

    // 1. Create admins table
    $db->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(100),
        email VARCHAR(100),
        role VARCHAR(50) DEFAULT 'admin',
        is_active TINYINT(1) DEFAULT 1,
        last_login DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $results[] = 'admins table created';

    // 2. Create admin_sessions table
    $db->exec("CREATE TABLE IF NOT EXISTS admin_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        session_token VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
    )");
    $results[] = 'admin_sessions table created';

    // 3. Create categories table
    $db->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100),
        icon VARCHAR(100),
        display_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $results[] = 'categories table created';

    // 4. Create products table
    $db->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        category_id INT,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        discount_price DECIMAL(10,2),
        unit VARCHAR(50) DEFAULT 'piece',
        stock INT DEFAULT 0,
        display_order INT DEFAULT 0,
        image VARCHAR(500),
        is_active TINYINT(1) DEFAULT 1,
        is_popular TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    )");
    $results[] = 'products table created';

    // 5. Create users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        mobile VARCHAR(15) UNIQUE,
        email VARCHAR(100),
        address TEXT,
        role VARCHAR(50) DEFAULT 'customer',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $results[] = 'users table created';

    // 6. Create addresses table
    $db->exec("CREATE TABLE IF NOT EXISTS addresses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        label VARCHAR(50),
        address_line TEXT,
        city VARCHAR(100),
        pincode VARCHAR(10),
        is_default TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $results[] = 'addresses table created';

    // 7. Create orders table
    $db->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(50) UNIQUE,
        user_id INT,
        customer_name VARCHAR(100),
        customer_mobile VARCHAR(15),
        customer_address TEXT,
        subtotal DECIMAL(10,2),
        discount DECIMAL(10,2) DEFAULT 0,
        tax DECIMAL(10,2) DEFAULT 0,
        coupon_code VARCHAR(50),
        total DECIMAL(10,2),
        status VARCHAR(50) DEFAULT 'pending',
        cancelled_reason TEXT,
        payment_method VARCHAR(50),
        payment_status VARCHAR(50) DEFAULT 'pending',
        delivery_type VARCHAR(50),
        delivery_date DATE,
        delivery_time VARCHAR(50),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
    $results[] = 'orders table created';

    // 8. Create order_items table
    $db->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT,
        product_name VARCHAR(200),
        quantity INT DEFAULT 1,
        price DECIMAL(10,2),
        subtotal DECIMAL(10,2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
    )");
    $results[] = 'order_items table created';

    // 9. Create banners table
    $db->exec("CREATE TABLE IF NOT EXISTS banners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200),
        description VARCHAR(500),
        image VARCHAR(500),
        desktop_image VARCHAR(500),
        mobile_image VARCHAR(500),
        redirect_type VARCHAR(50) DEFAULT 'none',
        redirect_url VARCHAR(500),
        display_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $results[] = 'banners table created';

    // 10. Create popups table
    $db->exec("CREATE TABLE IF NOT EXISTS popups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200),
        description TEXT,
        image VARCHAR(500),
        coupon_code VARCHAR(50),
        display_rule VARCHAR(50) DEFAULT 'once_per_session',
        cta_text VARCHAR(100),
        cta_link VARCHAR(500),
        start_date DATE,
        end_date DATE,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $results[] = 'popups table created';

    // Create default admin user
    $username = 'admin';
    $password = 'admin123';
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare("SELECT id FROM admins WHERE username = ?");
    $stmt->execute([$username]);

    if (!$stmt->fetch()) {
        $stmt = $db->prepare("INSERT INTO admins (username, password, name, email, role, is_active) VALUES (?, ?, 'Administrator', 'admin@skbakers.in', 'super_admin', 1)");
        $stmt->execute([$username, $hashedPassword]);
        $results[] = 'Admin user created';
    } else {
        $results[] = 'Admin user already exists';
    }

    echo json_encode([
        'success' => true,
        'message' => 'Database setup completed!',
        'tables_created' => $results,
        'admin_credentials' => [
            'username' => 'admin',
            'password' => 'admin123'
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
