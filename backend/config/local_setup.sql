-- KPS Nursery - Local Development Database Setup
-- Run this script in phpMyAdmin (http://localhost/phpmyadmin) or MySQL Workbench

-- Create database
CREATE DATABASE IF NOT EXISTS kps_nursery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kps_nursery;

-- Categories Table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(100) DEFAULT 'fas fa-leaf',
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Products Table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    category_id INT NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    discount_price DECIMAL(10,2) DEFAULT NULL,
    unit VARCHAR(20) DEFAULT 'piece',
    stock INT DEFAULT 0,
    image VARCHAR(500),
    rating DECIMAL(3,2) DEFAULT 0,
    reviews INT DEFAULT 0,
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    is_popular TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    mobile VARCHAR(15) UNIQUE,
    email VARCHAR(255),
    address TEXT,
    is_guest TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    role VARCHAR(20) DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Addresses Table
CREATE TABLE IF NOT EXISTS addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    address TEXT NOT NULL,
    city VARCHAR(100),
    state VARCHAR(100),
    pincode VARCHAR(10),
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Orders Table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(50) UNIQUE NOT NULL,
    user_id INT,
    customer_name VARCHAR(100),
    customer_mobile VARCHAR(15),
    customer_address TEXT,
    address_id INT,
    subtotal DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0,
    tax DECIMAL(10,2) DEFAULT 0,
    delivery_fee DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    coupon_code VARCHAR(50),
    delivery_type VARCHAR(20) DEFAULT 'standard',
    delivery_date DATE,
    delivery_time VARCHAR(50),
    payment_method VARCHAR(50) DEFAULT 'cod',
    payment_status VARCHAR(20) DEFAULT 'pending',
    status VARCHAR(20) DEFAULT 'pending',
    cancelled_reason TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (address_id) REFERENCES addresses(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Order Items Table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- Banners Table
CREATE TABLE IF NOT EXISTS banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200),
    description VARCHAR(500),
    image VARCHAR(500),
    desktop_image VARCHAR(500),
    mobile_image VARCHAR(500),
    redirect_type VARCHAR(20) DEFAULT 'none',
    redirect_url VARCHAR(500),
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Popups Table
CREATE TABLE IF NOT EXISTS popups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    image VARCHAR(500),
    coupon_code VARCHAR(50),
    display_rule VARCHAR(50) DEFAULT 'once_per_session',
    cta_text VARCHAR(100) DEFAULT 'Shop Now',
    cta_link VARCHAR(500),
    start_date DATE,
    end_date DATE,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Admins Table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255),
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'admin',
    is_active TINYINT(1) DEFAULT 1,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Admin Sessions Table
CREATE TABLE IF NOT EXISTS admin_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Reviews Table
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT,
    rating INT NOT NULL,
    comment TEXT,
    reviewer_name VARCHAR(100) NOT NULL,
    reviewer_email VARCHAR(255),
    helpful_count INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_id (product_id),
    INDEX idx_rating (rating)
) ENGINE=InnoDB;

-- Insert default admin (password: admin123)
INSERT INTO admins (name, username, email, password, role) VALUES
('Administrator', 'admin', 'admin@kpsnursery.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin')
ON DUPLICATE KEY UPDATE id=id;

-- Insert sample categories for nursery
INSERT INTO categories (name, slug, icon, display_order, is_active) VALUES
('Indoor Plants', 'indoor-plants', 'fas fa-home', 1, 1),
('Outdoor Plants', 'outdoor-plants', 'fas fa-sun', 2, 1),
('Flowering Plants', 'flowering-plants', 'fas fa-spa', 3, 1),
('Succulents', 'succulents', 'fas fa-seedling', 4, 1),
('Fruit Plants', 'fruit-plants', 'fas fa-apple-alt', 5, 1),
('Herbs & Vegetables', 'herbs-vegetables', 'fas fa-leaf', 6, 1),
('Pots & Planters', 'pots-planters', 'fas fa-archive', 7, 1),
('Garden Tools', 'garden-tools', 'fas fa-tools', 8, 1)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Insert sample products
INSERT INTO products (name, category_id, description, price, discount_price, unit, stock, display_order, is_active, is_popular) VALUES
('Money Plant (Pothos)', 1, 'Easy to maintain indoor plant that purifies air. Perfect for beginners.', 199, 149, 'piece', 50, 1, 1, 1),
('Snake Plant', 1, 'Low maintenance succulent known for air purification. Ideal for offices and bedrooms.', 299, 249, 'piece', 40, 2, 1, 1),
('Peace Lily', 1, 'Beautiful flowering indoor plant. Excellent air purifier with elegant white blooms.', 399, NULL, 'piece', 30, 3, 1, 0),
('Hibiscus', 2, 'Vibrant flowering plant for outdoor gardens. Produces beautiful red flowers.', 249, 199, 'piece', 60, 1, 1, 1),
('Bougainvillea', 2, 'Colorful climbing plant perfect for fences and pergolas.', 349, NULL, 'piece', 45, 2, 1, 0),
('Rose Plant', 3, 'Classic garden rose plant. Available in multiple colors.', 199, 149, 'piece', 100, 1, 1, 1),
('Marigold', 3, 'Bright orange and yellow flowers. Great for garden borders.', 49, 39, 'piece', 200, 2, 1, 0),
('Echeveria', 4, 'Beautiful rosette-shaped succulent. Perfect for small spaces.', 149, NULL, 'piece', 75, 1, 1, 1),
('Aloe Vera', 4, 'Medicinal succulent with numerous health benefits.', 199, 149, 'piece', 80, 2, 1, 1),
('Lemon Tree', 5, 'Dwarf lemon plant suitable for containers. Bears fruit year-round.', 599, 499, 'piece', 25, 1, 1, 1)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Insert sample banner
INSERT INTO banners (title, description, desktop_image, mobile_image, redirect_type, display_order, is_active) VALUES
('Welcome to KPS Nursery', 'Fresh Plants & Garden Supplies', '', '', 'none', 1, 1)
ON DUPLICATE KEY UPDATE title=VALUES(title);

SELECT 'KPS Nursery local database setup complete!' AS Message;
