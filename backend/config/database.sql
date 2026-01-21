-- FreshChicken Database Schema
-- MySQL Database for Fresh Chicken & Fish Delivery App

CREATE DATABASE IF NOT EXISTS freshchicken_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE freshchicken_db;

-- Categories Table
CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(255),
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products Table
CREATE TABLE IF NOT EXISTS products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    unit VARCHAR(50) DEFAULT 'kg',
    image VARCHAR(500),
    rating DECIMAL(3, 2) DEFAULT 0.00,
    reviews INT DEFAULT 0,
    stock INT DEFAULT 0,
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_category (category_id),
    INDEX idx_active (is_active),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mobile VARCHAR(15) UNIQUE NOT NULL,
    name VARCHAR(255),
    email VARCHAR(255),
    is_guest TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_mobile (mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Addresses Table
CREATE TABLE IF NOT EXISTS addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    street TEXT NOT NULL,
    landmark VARCHAR(255),
    pincode VARCHAR(10) NOT NULL,
    city VARCHAR(100) DEFAULT 'Bangalore',
    state VARCHAR(100) DEFAULT 'Karnataka',
    type ENUM('home', 'work', 'other') DEFAULT 'home',
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders Table
CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id VARCHAR(50) UNIQUE NOT NULL,
    user_id INT,
    address_id INT,
    subtotal DECIMAL(10, 2) NOT NULL,
    delivery_charge DECIMAL(10, 2) DEFAULT 0.00,
    packing_charge DECIMAL(10, 2) DEFAULT 10.00,
    total DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('upi', 'card', 'netbanking', 'cod') NOT NULL,
    delivery_type ENUM('standard', 'express') DEFAULT 'standard',
    status ENUM('pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (address_id) REFERENCES addresses(id) ON DELETE SET NULL,
    INDEX idx_order_id (order_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Items Table
CREATE TABLE IF NOT EXISTS order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cart Table
CREATE TABLE IF NOT EXISTS cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(255) NOT NULL,
    user_id INT,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_session (session_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('order', 'offer', 'system') DEFAULT 'system',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Sample Categories
INSERT INTO categories (name, slug, icon, display_order) VALUES
('Chicken', 'chicken', 'fa-drumstick-bite', 1),
('Fish', 'fish', 'fa-fish', 2),
('Offers', 'offers', 'fa-tags', 3),
('Combos', 'combos', 'fa-box', 4);

-- Insert Sample Products
INSERT INTO products (category_id, name, description, price, unit, image, rating, reviews, stock) VALUES
-- Chicken Products
(1, 'Whole Chicken', 'Farm fresh whole chicken, cleaned and dressed. Perfect for roasting or grilling. Rich in protein and essential nutrients.', 250, 'kg', 'https://images.unsplash.com/photo-1587593810167-a84920ea0781?w=400&h=300&fit=crop', 4.5, 234, 50),
(1, 'Chicken Curry Cut', 'Premium curry cut chicken pieces with bone. Ideal for curries, biryanis, and traditional recipes. Fresh and hygienically packed.', 280, 'kg', 'https://images.unsplash.com/photo-1604908176997-125f25cc6f3d?w=400&h=300&fit=crop', 4.7, 456, 100),
(1, 'Boneless Chicken', 'Tender boneless chicken breast and thigh pieces. Perfect for stir-fry, tikka, and quick cooking. High protein, low fat option.', 350, 'kg', 'https://images.unsplash.com/photo-1615937691194-97dbd3f3dc95?w=400&h=300&fit=crop', 4.8, 567, 75),
(1, 'Chicken Liver', 'Fresh chicken liver, rich in iron and vitamins. Popular in traditional recipes and stir-fries. Cleaned and ready to cook.', 180, 'kg', 'https://images.unsplash.com/photo-1598103442097-8b74394b95c6?w=400&h=300&fit=crop', 4.2, 123, 30),
(1, 'Chicken Legs', 'Juicy chicken drumsticks, perfect for grilling or frying. Kids favorite! Fresh from farm to your table.', 240, 'kg', 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=400&h=300&fit=crop', 4.6, 345, 60),
(1, 'Chicken Wings', 'Crispy chicken wings, ideal for appetizers and snacks. Perfect for parties and gatherings. Ready to marinate and cook.', 220, 'kg', 'https://images.unsplash.com/photo-1610057099443-fde8c4d50f91?w=400&h=300&fit=crop', 4.4, 267, 45),

-- Fish Products
(2, 'Seer Fish', 'Premium seer fish steaks, boneless and fresh. Rich in omega-3 fatty acids. Perfect for frying or grilling.', 600, 'kg', 'https://images.unsplash.com/photo-1544943910-4c1dc44aab44?w=400&h=300&fit=crop', 4.9, 189, 25),
(2, 'Pomfret', 'Silver pomfret, whole cleaned fish. Delicate flavor and tender texture. Ideal for steaming or frying.', 500, 'kg', 'https://images.unsplash.com/photo-1559548331-f9cb98001426?w=400&h=300&fit=crop', 4.7, 145, 30),
(2, 'Prawns', 'Large fresh prawns, deveined and cleaned. Sweet and succulent. Perfect for curries, biryanis, and grilling.', 800, 'kg', 'https://images.unsplash.com/photo-1565680018434-b513d5e5fd47?w=400&h=300&fit=crop', 4.8, 423, 40),
(2, 'Nethili / Anchovy', 'Small silver anchovies, cleaned and fresh. Traditional South Indian favorite. Rich in calcium and protein.', 180, 'kg', 'https://images.unsplash.com/photo-1534043464124-3be32fe000c9?w=400&h=300&fit=crop', 4.3, 98, 50),
(2, 'Rohu Fish', 'Fresh water rohu fish, cleaned and cut. Popular for curries and fries. Mild flavor and firm texture.', 280, 'kg', 'https://images.unsplash.com/photo-1599084993091-1cb5c0721cc6?w=400&h=300&fit=crop', 4.5, 167, 35),
(2, 'Sardines', 'Fresh sardines, small oily fish rich in omega-3. Perfect for frying or grilling. Cleaned and ready to cook.', 200, 'kg', 'https://images.unsplash.com/photo-1626200419199-391ae4be7a41?w=400&h=300&fit=crop', 4.4, 134, 55);
