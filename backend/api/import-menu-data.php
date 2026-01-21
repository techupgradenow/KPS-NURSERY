<?php
/**
 * Import Menu Data to Database
 * Complete SK Bakers Menu Card - All Items
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Database connection
$dbHost = '193.203.184.194';
$dbName = 'u282002960_kidai';
$dbUser = 'u282002960_kidai';
$dbPass = 'Ufcrxq9iwYXuXuyr';

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Create tables if they don't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL UNIQUE,
        icon VARCHAR(100) DEFAULT 'fas fa-utensils',
        display_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        category VARCHAR(100) NOT NULL,
        type ENUM('veg', 'non-veg') DEFAULT 'veg',
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        price2 DECIMAL(10,2) DEFAULT NULL,
        display_order INT DEFAULT 0,
        is_special TINYINT(1) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Clear existing data
    $pdo->exec("DELETE FROM menu_items");
    $pdo->exec("DELETE FROM menu_categories");

    // Categories data - Complete from menu card
    $categories = [
        ['name' => 'Soups', 'slug' => 'soups', 'icon' => 'fas fa-mug-hot', 'display_order' => 1],
        ['name' => 'Salads', 'slug' => 'salads', 'icon' => 'fas fa-seedling', 'display_order' => 2],
        ['name' => 'Veg Starters', 'slug' => 'veg-starters', 'icon' => 'fas fa-pepper-hot', 'display_order' => 3],
        ['name' => 'Momos', 'slug' => 'momos', 'icon' => 'fas fa-cloud', 'display_order' => 4],
        ['name' => 'Non Veg Starters', 'slug' => 'non-veg-starters', 'icon' => 'fas fa-drumstick-bite', 'display_order' => 5],
        ['name' => 'Sandwich - Veg', 'slug' => 'sandwich-veg', 'icon' => 'fas fa-bread-slice', 'display_order' => 6],
        ['name' => 'Sandwich - Non Veg', 'slug' => 'sandwich-nonveg', 'icon' => 'fas fa-bread-slice', 'display_order' => 7],
        ['name' => 'Bread Omelette', 'slug' => 'bread-omelette', 'icon' => 'fas fa-egg', 'display_order' => 8],
        ['name' => 'Pasta - Veg', 'slug' => 'pasta-veg', 'icon' => 'fas fa-utensils', 'display_order' => 9],
        ['name' => 'Pasta - Non Veg', 'slug' => 'pasta-nonveg', 'icon' => 'fas fa-utensils', 'display_order' => 10],
        ['name' => 'Burger - Veg', 'slug' => 'burger-veg', 'icon' => 'fas fa-burger', 'display_order' => 11],
        ['name' => 'Burger - Non Veg', 'slug' => 'burger-nonveg', 'icon' => 'fas fa-burger', 'display_order' => 12],
        ['name' => 'Pizza - Veg', 'slug' => 'pizza-veg', 'icon' => 'fas fa-pizza-slice', 'display_order' => 13],
        ['name' => 'Pizza - Non Veg', 'slug' => 'pizza-nonveg', 'icon' => 'fas fa-pizza-slice', 'display_order' => 14],
        ['name' => 'Half and Half Pizza', 'slug' => 'pizza-half', 'icon' => 'fas fa-pizza-slice', 'display_order' => 15],
        ['name' => 'Wraps', 'slug' => 'wraps', 'icon' => 'fas fa-hotdog', 'display_order' => 16],
        ['name' => 'Milk Shakes', 'slug' => 'milkshakes', 'icon' => 'fas fa-glass-water', 'display_order' => 17],
        ['name' => 'Waffles', 'slug' => 'waffles', 'icon' => 'fas fa-cookie', 'display_order' => 18],
        ['name' => 'Mojito', 'slug' => 'mojito', 'icon' => 'fas fa-martini-glass-citrus', 'display_order' => 19],
        ['name' => 'Fresh Juices', 'slug' => 'juices', 'icon' => 'fas fa-lemon', 'display_order' => 20],
        ['name' => 'Falooda', 'slug' => 'falooda', 'icon' => 'fas fa-ice-cream', 'display_order' => 21],
        ['name' => 'Tres Leches', 'slug' => 'tresleches', 'icon' => 'fas fa-cake-candles', 'display_order' => 22],
        ['name' => 'Desserts', 'slug' => 'desserts', 'icon' => 'fas fa-cookie-bite', 'display_order' => 23],
        ['name' => 'Cold Beverages', 'slug' => 'cold-beverages', 'icon' => 'fas fa-glass-water', 'display_order' => 24],
        ['name' => 'Hot Beverages', 'slug' => 'hot-beverages', 'icon' => 'fas fa-mug-hot', 'display_order' => 25],
        ['name' => 'Hot Chocolates', 'slug' => 'hot-chocolates', 'icon' => 'fas fa-mug-saucer', 'display_order' => 26]
    ];

    // Insert categories
    $catStmt = $pdo->prepare("INSERT INTO menu_categories (name, slug, icon, display_order) VALUES (?, ?, ?, ?)");
    foreach ($categories as $cat) {
        $catStmt->execute([$cat['name'], $cat['slug'], $cat['icon'], $cat['display_order']]);
    }

    // Menu items data - Complete from menu card images
    $menuItems = [
        // ============ SOUPS ============
        ['name' => 'Creamy Pumpkin Soup', 'category' => 'soups', 'type' => 'veg', 'price' => 120],
        ['name' => 'Cream of Chicken Soup', 'category' => 'soups', 'type' => 'non-veg', 'price' => 150],
        ['name' => 'Cream of Broccoli Soup', 'category' => 'soups', 'type' => 'veg', 'price' => 140],
        ['name' => 'Cream of Mushroom Soup', 'category' => 'soups', 'type' => 'veg', 'price' => 130],

        // ============ SALADS ============
        ['name' => 'Watermelon Feta Salad', 'category' => 'salads', 'type' => 'veg', 'price' => 130],
        ['name' => 'Mexican Sweet Corn Salad', 'category' => 'salads', 'type' => 'veg', 'price' => 150],
        ['name' => 'Russian Salad', 'category' => 'salads', 'type' => 'veg', 'price' => 200],
        ['name' => 'Crunchy Veg Salad', 'category' => 'salads', 'type' => 'veg', 'price' => 110],

        // ============ VEG STARTERS ============
        ['name' => 'French Fries', 'category' => 'veg-starters', 'type' => 'veg', 'price' => 70],
        ['name' => 'Peri Peri Fries', 'category' => 'veg-starters', 'type' => 'veg', 'price' => 80],
        ['name' => 'Peri Peri Cheesy Fries', 'category' => 'veg-starters', 'type' => 'veg', 'price' => 120],
        ['name' => 'Crispy Smiley', 'category' => 'veg-starters', 'type' => 'veg', 'price' => 70],
        ['name' => 'Veg Nuggets', 'category' => 'veg-starters', 'type' => 'veg', 'price' => 80],
        ['name' => 'Potato Cheese Balls', 'category' => 'veg-starters', 'type' => 'veg', 'price' => 100],
        ['name' => 'Peri Peri Cheese Stuffed Mushroom', 'category' => 'veg-starters', 'type' => 'veg', 'price' => 140],
        ['name' => 'Garlic Toast', 'category' => 'veg-starters', 'type' => 'veg', 'price' => 80],
        ['name' => 'Cheese Garlic Toast', 'category' => 'veg-starters', 'type' => 'veg', 'price' => 120],

        // ============ MOMOS ============
        ['name' => 'Mixed Veg Momos', 'category' => 'momos', 'type' => 'veg', 'price' => 110],
        ['name' => 'Mushroom Momos', 'category' => 'momos', 'type' => 'veg', 'price' => 120],
        ['name' => 'Paneer Momos', 'category' => 'momos', 'type' => 'veg', 'price' => 120],
        ['name' => 'Chicken Momos', 'category' => 'momos', 'type' => 'non-veg', 'price' => 140],

        // ============ NON VEG STARTERS ============
        ['name' => 'Chicken Popcorn', 'category' => 'non-veg-starters', 'type' => 'non-veg', 'price' => 140],
        ['name' => 'Chicken Nuggets', 'category' => 'non-veg-starters', 'type' => 'non-veg', 'price' => 120],
        ['name' => 'Crispy Fried Chicken', 'category' => 'non-veg-starters', 'type' => 'non-veg', 'price' => 150],
        ['name' => 'Peri Peri Chicken Strips', 'category' => 'non-veg-starters', 'type' => 'non-veg', 'price' => 180],
        ['name' => 'Barbeque Chicken Wings', 'category' => 'non-veg-starters', 'type' => 'non-veg', 'price' => 220],
        ['name' => 'Chicken Cheese Balls', 'category' => 'non-veg-starters', 'type' => 'non-veg', 'price' => 130],
        ['name' => 'Japan Chicken', 'category' => 'non-veg-starters', 'type' => 'non-veg', 'price' => 170],
        ['name' => 'Chicken Omelette', 'category' => 'non-veg-starters', 'type' => 'non-veg', 'price' => 130],

        // ============ SANDWICH - VEG ============
        ['name' => 'Classic Veg Sandwich', 'category' => 'sandwich-veg', 'type' => 'veg', 'price' => 130],
        ['name' => 'Paneer Sandwich', 'category' => 'sandwich-veg', 'type' => 'veg', 'price' => 150],
        ['name' => 'Bombay Veg Sandwich', 'category' => 'sandwich-veg', 'type' => 'veg', 'price' => 160],
        ['name' => 'Grilled Cheese Sandwich', 'category' => 'sandwich-veg', 'type' => 'veg', 'price' => 150],

        // ============ SANDWICH - NON VEG ============
        ['name' => 'Classic Chicken Sandwich', 'category' => 'sandwich-nonveg', 'type' => 'non-veg', 'price' => 140],
        ['name' => 'Fried Chicken Sandwich', 'category' => 'sandwich-nonveg', 'type' => 'non-veg', 'price' => 150],
        ['name' => 'Grilled Chicken Sandwich', 'category' => 'sandwich-nonveg', 'type' => 'non-veg', 'price' => 150],
        ['name' => 'Spicy Egg Sandwich', 'category' => 'sandwich-nonveg', 'type' => 'non-veg', 'price' => 130],

        // ============ BREAD OMELETTE ============
        ['name' => 'Classic Bread Omelette', 'category' => 'bread-omelette', 'type' => 'non-veg', 'price' => 80],
        ['name' => 'Masala Bread Omelette', 'category' => 'bread-omelette', 'type' => 'non-veg', 'price' => 90],
        ['name' => 'Cheese Bread Omelette', 'category' => 'bread-omelette', 'type' => 'non-veg', 'price' => 100],

        // ============ PASTA - VEG ============
        ['name' => 'Creamy Pesto Sauce Pasta', 'category' => 'pasta-veg', 'type' => 'veg', 'price' => 260],
        ['name' => 'Creamy Pink Sauce Pasta', 'category' => 'pasta-veg', 'type' => 'veg', 'price' => 240],
        ['name' => 'Alfredo Mixed Veg Pasta', 'category' => 'pasta-veg', 'type' => 'veg', 'price' => 240],
        ['name' => 'Arrabbiata Veg Pasta', 'category' => 'pasta-veg', 'type' => 'veg', 'price' => 220],

        // ============ PASTA - NON VEG ============
        ['name' => 'Creamy Pesto Sauce Pasta', 'category' => 'pasta-nonveg', 'type' => 'non-veg', 'price' => 280],
        ['name' => 'Creamy Pink Sauce Pasta', 'category' => 'pasta-nonveg', 'type' => 'non-veg', 'price' => 250],
        ['name' => 'Alfredo Chicken Pasta', 'category' => 'pasta-nonveg', 'type' => 'non-veg', 'price' => 250],
        ['name' => 'Arabiatta Chicken Pasta', 'category' => 'pasta-nonveg', 'type' => 'non-veg', 'price' => 240],

        // ============ BURGER - VEG ============
        ['name' => 'Classic Veg Burger', 'category' => 'burger-veg', 'type' => 'veg', 'price' => 130],
        ['name' => 'Grilled Paneer Burger', 'category' => 'burger-veg', 'type' => 'veg', 'price' => 150],

        // ============ BURGER - NON VEG ============
        ['name' => 'Crispy Fried Chicken Burger', 'category' => 'burger-nonveg', 'type' => 'non-veg', 'price' => 150],
        ['name' => 'Juicy Chicken Burger', 'category' => 'burger-nonveg', 'type' => 'non-veg', 'price' => 190],

        // ============ PIZZA - VEG (with two prices: Regular/Medium) ============
        ['name' => 'Classic Veg Pizza', 'category' => 'pizza-veg', 'type' => 'veg', 'price' => 220, 'price2' => 270],
        ['name' => 'Spicy Mushroom Pizza', 'category' => 'pizza-veg', 'type' => 'veg', 'price' => 260, 'price2' => 320],
        ['name' => 'Sweet Corn Pizza', 'category' => 'pizza-veg', 'type' => 'veg', 'price' => 260, 'price2' => 320],
        ['name' => 'Margherita Pizza', 'category' => 'pizza-veg', 'type' => 'veg', 'price' => 220, 'price2' => 280],
        ['name' => 'Spicy Paneer Pizza', 'category' => 'pizza-veg', 'type' => 'veg', 'price' => 280, 'price2' => 360],
        ['name' => 'Cheesy Mushroom Pizza', 'category' => 'pizza-veg', 'type' => 'veg', 'price' => 260, 'price2' => 330],

        // ============ PIZZA - NON VEG (with two prices) ============
        ['name' => 'Classic Chicken Pizza', 'category' => 'pizza-nonveg', 'type' => 'non-veg', 'price' => 270, 'price2' => 340],
        ['name' => 'Crispy Chicken Pizza', 'category' => 'pizza-nonveg', 'type' => 'non-veg', 'price' => 310, 'price2' => 390],
        ['name' => 'Spicy Chicken Pizza', 'category' => 'pizza-nonveg', 'type' => 'non-veg', 'price' => 280, 'price2' => 350],
        ['name' => 'Barbeque Chicken Pizza', 'category' => 'pizza-nonveg', 'type' => 'non-veg', 'price' => 290, 'price2' => 360],
        ['name' => 'Peri Peri Chicken Pizza', 'category' => 'pizza-nonveg', 'type' => 'non-veg', 'price' => 290, 'price2' => 350],

        // ============ HALF AND HALF PIZZA ============
        ['name' => 'Chicken and Paneer', 'category' => 'pizza-half', 'type' => 'non-veg', 'price' => 280, 'price2' => 350],
        ['name' => 'Chicken and Sweet Corn', 'category' => 'pizza-half', 'type' => 'non-veg', 'price' => 280, 'price2' => 350],
        ['name' => 'Chicken and Mushroom', 'category' => 'pizza-half', 'type' => 'non-veg', 'price' => 280, 'price2' => 360],

        // ============ WRAPS ============
        ['name' => 'Super Veg Wrap', 'category' => 'wraps', 'type' => 'veg', 'price' => 90],
        ['name' => 'Crispy Chicken Wrap', 'category' => 'wraps', 'type' => 'non-veg', 'price' => 140],
        ['name' => 'Paneer Wrap', 'category' => 'wraps', 'type' => 'veg', 'price' => 110],
        ['name' => 'Aloo Tikki Wrap', 'category' => 'wraps', 'type' => 'veg', 'price' => 120],

        // ============ MILK SHAKES ============
        ['name' => 'Vanilla Milkshake', 'category' => 'milkshakes', 'type' => 'veg', 'price' => 110],
        ['name' => 'Strawberry Milk Shake', 'category' => 'milkshakes', 'type' => 'veg', 'price' => 120],
        ['name' => 'Mango Milk Shake', 'category' => 'milkshakes', 'type' => 'veg', 'price' => 120],
        ['name' => 'Butterscotch Milk Shake', 'category' => 'milkshakes', 'type' => 'veg', 'price' => 120],
        ['name' => 'Chocolate Milk Shake', 'category' => 'milkshakes', 'type' => 'veg', 'price' => 120],
        ['name' => 'Oreo Milk Shake', 'category' => 'milkshakes', 'type' => 'veg', 'price' => 130],
        ['name' => 'Brownie Milkshake', 'category' => 'milkshakes', 'type' => 'veg', 'price' => 160],
        ['name' => 'Red Velvet Milk Shake', 'category' => 'milkshakes', 'type' => 'veg', 'price' => 160],
        ['name' => 'Lotus Biscoff Milk Shake', 'category' => 'milkshakes', 'type' => 'veg', 'price' => 180, 'is_special' => 1],
        ['name' => 'Avocado Milkshake', 'category' => 'milkshakes', 'type' => 'veg', 'price' => 200, 'is_special' => 1],

        // ============ WAFFLES ============
        ['name' => 'Dark Chocolate Waffle', 'category' => 'waffles', 'type' => 'veg', 'price' => 110],
        ['name' => 'White Chocolate Waffle', 'category' => 'waffles', 'type' => 'veg', 'price' => 110],
        ['name' => 'Oreo Waffle', 'category' => 'waffles', 'type' => 'veg', 'price' => 130],
        ['name' => 'Triple Chocolate Waffle', 'category' => 'waffles', 'type' => 'veg', 'price' => 140],
        ['name' => 'Double Chocolate Waffle', 'category' => 'waffles', 'type' => 'veg', 'price' => 130],
        ['name' => 'Lotus Biscoff Waffle', 'category' => 'waffles', 'type' => 'veg', 'price' => 120],
        ['name' => 'Banana Caramel Waffle', 'category' => 'waffles', 'type' => 'veg', 'price' => 120],
        ['name' => 'Peanut Butter Waffle', 'category' => 'waffles', 'type' => 'veg', 'price' => 130],
        ['name' => 'Nutella Waffle', 'category' => 'waffles', 'type' => 'veg', 'price' => 130],

        // ============ MOJITO ============
        ['name' => 'Lemon Mint Mojito', 'category' => 'mojito', 'type' => 'veg', 'price' => 100],
        ['name' => 'Blue Curacao Mojito', 'category' => 'mojito', 'type' => 'veg', 'price' => 100],
        ['name' => 'Green Apple Mojito', 'category' => 'mojito', 'type' => 'veg', 'price' => 100],
        ['name' => 'Watermelon Mojito', 'category' => 'mojito', 'type' => 'veg', 'price' => 100],

        // ============ FRESH JUICES ============
        ['name' => 'Lemon Juice', 'category' => 'juices', 'type' => 'veg', 'price' => 60],
        ['name' => 'Lemon Mint Juice', 'category' => 'juices', 'type' => 'veg', 'price' => 65],
        ['name' => 'Lemon Soda', 'category' => 'juices', 'type' => 'veg', 'price' => 70],
        ['name' => 'Muskmelon Juice', 'category' => 'juices', 'type' => 'veg', 'price' => 100],
        ['name' => 'Watermelon Juice', 'category' => 'juices', 'type' => 'veg', 'price' => 80],

        // ============ FALOODA ============
        ['name' => 'Royal Falooda', 'category' => 'falooda', 'type' => 'veg', 'price' => 150],
        ['name' => 'Rose Falooda', 'category' => 'falooda', 'type' => 'veg', 'price' => 160],
        ['name' => 'Strawberry Crush Falooda', 'category' => 'falooda', 'type' => 'veg', 'price' => 150],
        ['name' => 'Chocolate Falooda', 'category' => 'falooda', 'type' => 'veg', 'price' => 170],

        // ============ TRES LECHES ============
        ['name' => 'Vanilla Saffron Tres Leches', 'category' => 'tresleches', 'type' => 'veg', 'price' => 180],
        ['name' => 'Rosemilk Tres Leches', 'category' => 'tresleches', 'type' => 'veg', 'price' => 170],
        ['name' => 'Rasmalai Tres Leches', 'category' => 'tresleches', 'type' => 'veg', 'price' => 180],
        ['name' => 'Chocolate Tres Leches', 'category' => 'tresleches', 'type' => 'veg', 'price' => 200],

        // ============ DESSERTS ============
        ['name' => 'Brownie with Ice Cream', 'category' => 'desserts', 'type' => 'veg', 'price' => 130],
        ['name' => 'Sizzling Brownie with Ice Cream', 'category' => 'desserts', 'type' => 'veg', 'price' => 150, 'is_special' => 1],

        // ============ COLD BEVERAGES ============
        ['name' => 'Tender Coconut Payasam', 'category' => 'cold-beverages', 'type' => 'veg', 'price' => 100],
        ['name' => 'Cold Coffee', 'category' => 'cold-beverages', 'type' => 'veg', 'price' => 100],
        ['name' => 'Spiced Butter Milk', 'category' => 'cold-beverages', 'type' => 'veg', 'price' => 70],

        // ============ HOT BEVERAGES ============
        ['name' => 'Coffee', 'category' => 'hot-beverages', 'type' => 'veg', 'price' => 30],
        ['name' => 'Hazelnut Coffee', 'category' => 'hot-beverages', 'type' => 'veg', 'price' => 60],
        ['name' => 'Tea', 'category' => 'hot-beverages', 'type' => 'veg', 'price' => 25],
        ['name' => 'Elachi Tea', 'category' => 'hot-beverages', 'type' => 'veg', 'price' => 30],
        ['name' => 'Strawberry Tea', 'category' => 'hot-beverages', 'type' => 'veg', 'price' => 30],

        // ============ HOT CHOCOLATES ============
        ['name' => 'Classic Hot Chocolate', 'category' => 'hot-chocolates', 'type' => 'veg', 'price' => 100],
        ['name' => 'Nutella Hot Chocolate', 'category' => 'hot-chocolates', 'type' => 'veg', 'price' => 130],
        ['name' => 'Spiced Hot Chocolate', 'category' => 'hot-chocolates', 'type' => 'veg', 'price' => 110],
        ['name' => 'Mocha Hot Chocolate', 'category' => 'hot-chocolates', 'type' => 'veg', 'price' => 120],
        ['name' => 'Dark Hot Chocolate', 'category' => 'hot-chocolates', 'type' => 'veg', 'price' => 120]
    ];

    // Insert menu items
    $itemStmt = $pdo->prepare("INSERT INTO menu_items (name, category, type, price, price2, is_special, display_order) VALUES (?, ?, ?, ?, ?, ?, ?)");

    $displayOrder = 1;
    foreach ($menuItems as $item) {
        $itemStmt->execute([
            $item['name'],
            $item['category'],
            $item['type'],
            $item['price'],
            $item['price2'] ?? null,
            $item['is_special'] ?? 0,
            $displayOrder++
        ]);
    }

    $totalCategories = count($categories);
    $totalItems = count($menuItems);

    echo json_encode([
        'success' => true,
        'message' => "Menu data imported successfully!",
        'data' => [
            'categories_imported' => $totalCategories,
            'items_imported' => $totalItems
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
