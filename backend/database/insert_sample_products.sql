-- Insert Sample Products with Images
-- Run this after creating categories and organizing images

-- Get category IDs (you may need to adjust these based on your actual category IDs)
-- Assuming: category_id 1 = Chicken, category_id 2 = Fish

-- ============================================
-- CHICKEN PRODUCTS
-- ============================================

-- Whole Chicken
INSERT INTO products (category_id, name, description, price, unit, image, is_active, stock_quantity, rating, reviews, display_order)
VALUES (
    1,
    'Whole Chicken',
    'Farm fresh whole chicken, cleaned and ready to cook. Perfect for roasting or curry preparations.',
    280.00,
    'kg',
    '../assets/images/products/Whole-Chicken.jpg',
    1,
    50,
    4.5,
    128,
    1
) ON DUPLICATE KEY UPDATE
    image = '../assets/images/products/Whole-Chicken.jpg';

-- Boneless Chicken
INSERT INTO products (category_id, name, description, price, unit, image, is_active, stock_quantity, rating, reviews, display_order)
VALUES (
    1,
    'Boneless Chicken',
    'Premium boneless chicken breast, perfectly cut and cleaned. Ideal for grilling, frying, or curry.',
    350.00,
    'kg',
    '../assets/images/products/Boneless-Chicken.jpg',
    1,
    40,
    4.7,
    256,
    2
) ON DUPLICATE KEY UPDATE
    image = '../assets/images/products/Boneless-Chicken.jpg';

-- Chicken Curry Cut
INSERT INTO products (category_id, name, description, price, unit, image, is_active, stock_quantity, rating, reviews, display_order)
VALUES (
    1,
    'Chicken Curry Cut',
    'Chicken cut into medium pieces with bones, perfect for making delicious curries and gravies.',
    260.00,
    'kg',
    '../assets/images/products/Chicken Curry Cut.jpg',
    1,
    60,
    4.6,
    189,
    3
) ON DUPLICATE KEY UPDATE
    image = '../assets/images/products/Chicken Curry Cut.jpg';

-- Chicken Legs
INSERT INTO products (category_id, name, description, price, unit, image, is_active, stock_quantity, rating, reviews, display_order)
VALUES (
    1,
    'Chicken Legs',
    'Fresh chicken drumsticks, perfect for grilling, frying, or barbecue. Kids favorite!',
    240.00,
    'kg',
    '../assets/images/products/Chicken-Legs.jpg',
    1,
    45,
    4.4,
    145,
    4
) ON DUPLICATE KEY UPDATE
    image = '../assets/images/products/Chicken-Legs.jpg';

-- ============================================
-- FISH PRODUCTS
-- ============================================

-- Pomfret
INSERT INTO products (category_id, name, description, price, unit, image, is_active, stock_quantity, rating, reviews, display_order)
VALUES (
    2,
    'Pomfret',
    'Premium silver pomfret, fresh from the sea. Best for frying or steaming. Rich in omega-3.',
    450.00,
    'kg',
    '../assets/images/products/Pomfret.jpg',
    1,
    25,
    4.8,
    312,
    1
) ON DUPLICATE KEY UPDATE
    image = '../assets/images/products/Pomfret.jpg';

-- Prawns
INSERT INTO products (category_id, name, description, price, unit, image, is_active, stock_quantity, rating, reviews, display_order)
VALUES (
    2,
    'Prawns',
    'Large fresh prawns, cleaned and deveined. Perfect for prawn curry, biryani, or grilled preparations.',
    600.00,
    'kg',
    '../assets/images/products/Prawns.jpg',
    1,
    30,
    4.9,
    423,
    2
) ON DUPLICATE KEY UPDATE
    image = '../assets/images/products/Prawns.jpg';

-- Rohu Fish
INSERT INTO products (category_id, name, description, price, unit, image, is_active, stock_quantity, rating, reviews, display_order)
VALUES (
    2,
    'Rohu Fish',
    'Fresh Rohu fish, cut into medium slices. Great for curries and traditional fish preparations.',
    280.00,
    'kg',
    '../assets/images/products/Rohu-Fish.jpg',
    1,
    35,
    4.5,
    198,
    3
) ON DUPLICATE KEY UPDATE
    image = '../assets/images/products/Rohu-Fish.jpg';

-- Sardines
INSERT INTO products (category_id, name, description, price, unit, image, is_active, stock_quantity, rating, reviews, display_order)
VALUES (
    2,
    'Sardines',
    'Fresh sardines, small and flavorful. Rich in omega-3 and perfect for frying or curry.',
    180.00,
    'kg',
    '../assets/images/products/Sardines.jpg',
    1,
    40,
    4.3,
    156,
    4
) ON DUPLICATE KEY UPDATE
    image = '../assets/images/products/Sardines.jpg';

-- Seer Fish
INSERT INTO products (category_id, name, description, price, unit, image, is_active, stock_quantity, rating, reviews, display_order)
VALUES (
    2,
    'Seer Fish',
    'Premium seer fish (King Mackerel), boneless slices available. Excellent for grilling and frying.',
    550.00,
    'kg',
    '../assets/images/products/Seer-Fish.jpg',
    1,
    20,
    4.7,
    267,
    5
) ON DUPLICATE KEY UPDATE
    image = '../assets/images/products/Seer-Fish.jpg';

-- Nethili
INSERT INTO products (category_id, name, description, price, unit, image, is_active, stock_quantity, rating, reviews, display_order)
VALUES (
    2,
    'Nethili (Anchovies)',
    'Fresh nethili/anchovies, small and packed with flavor. Perfect for frying or making spicy preparations.',
    150.00,
    'kg',
    '../assets/images/products/Nethili.jpg',
    1,
    50,
    4.2,
    134,
    6
) ON DUPLICATE KEY UPDATE
    image = '../assets/images/products/Nethili.jpg';

-- Display results
SELECT 'Products inserted/updated successfully!' as message;
SELECT id, name, price, unit, image, rating, reviews FROM products ORDER BY category_id, display_order;
