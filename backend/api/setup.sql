-- FreshChicken Database Setup
-- Run this SQL in phpMyAdmin or MySQL CLI

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS freshchicken CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE freshchicken;

-- Admins table
CREATE TABLE IF NOT EXISTS admins (
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
);

-- Admin sessions table
CREATE TABLE IF NOT EXISTS admin_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    session_token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
);

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(50),
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    discount_price DECIMAL(10, 2),
    unit VARCHAR(20) DEFAULT 'kg',
    stock INT DEFAULT 0,
    image VARCHAR(500),
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    mobile VARCHAR(15) NOT NULL UNIQUE,
    email VARCHAR(100),
    password VARCHAR(255),
    is_guest TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Addresses table
CREATE TABLE IF NOT EXISTS addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100),
    mobile VARCHAR(15),
    street VARCHAR(500),
    landmark VARCHAR(200),
    city VARCHAR(100),
    state VARCHAR(100),
    pincode VARCHAR(10),
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    order_id VARCHAR(20) NOT NULL UNIQUE,
    status ENUM('pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled') DEFAULT 'pending',
    subtotal DECIMAL(10, 2) NOT NULL,
    delivery_charge DECIMAL(10, 2) DEFAULT 0,
    packing_charge DECIMAL(10, 2) DEFAULT 0,
    total DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('cod', 'online', 'upi') DEFAULT 'cod',
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
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Order items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT,
    product_name VARCHAR(200) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Banners table
CREATE TABLE IF NOT EXISTS banners (
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
);

-- Popups table
CREATE TABLE IF NOT EXISTS popups (
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
);

-- Insert default admin (password: admin123)
INSERT INTO admins (name, username, email, password, role) VALUES
('Administrator', 'admin', 'admin@skbakers.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')
ON DUPLICATE KEY UPDATE name = name;

-- Insert sample categories
INSERT INTO categories (name, slug, icon, display_order) VALUES
('Fresh Chicken', 'fresh-chicken', 'fa-drumstick-bite', 1),
('Marinated', 'marinated', 'fa-fire', 2),
('Ready to Cook', 'ready-to-cook', 'fa-utensils', 3),
('Eggs', 'eggs', 'fa-egg', 4)
ON DUPLICATE KEY UPDATE name = name;

-- Insert sample products
INSERT INTO products (category_id, name, description, price, discount_price, unit, stock, image, is_active) VALUES
(1, 'Whole Chicken', 'Fresh whole chicken, cleaned and ready to cook', 280.00, 250.00, 'kg', 50, '', 1),
(1, 'Chicken Breast', 'Boneless chicken breast, tender and juicy', 350.00, NULL, 'kg', 30, '', 1),
(1, 'Chicken Drumstick', 'Fresh chicken drumsticks', 300.00, 280.00, 'kg', 40, '', 1),
(2, 'Tandoori Marinated', 'Tandoori spiced chicken, ready for BBQ', 400.00, NULL, 'kg', 20, '', 1),
(3, 'Chicken Momos', 'Pre-made chicken momos, just steam and serve', 180.00, 150.00, '12 pcs', 25, '', 1),
(4, 'Farm Eggs', 'Fresh farm eggs', 80.00, NULL, '12 pcs', 100, '', 1)
ON DUPLICATE KEY UPDATE name = name;

SELECT 'Database setup completed!' as Message;
