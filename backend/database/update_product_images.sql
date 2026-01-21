-- Update Product Images with Correct Paths
-- Run this in phpMyAdmin after organizing images

-- Chicken Products
UPDATE products SET image = '../assets/images/products/Whole-Chicken.jpg'
WHERE name = 'Whole Chicken' OR name LIKE '%Whole Chicken%';

UPDATE products SET image = '../assets/images/products/Boneless-Chicken.jpg'
WHERE name = 'Boneless Chicken' OR name LIKE '%Boneless%';

UPDATE products SET image = '../assets/images/products/Chicken Curry Cut.jpg'
WHERE name = 'Chicken Curry Cut' OR name LIKE '%Curry Cut%';

UPDATE products SET image = '../assets/images/products/Chicken-Legs.jpg'
WHERE name = 'Chicken Legs' OR name LIKE '%Chicken Leg%' OR name LIKE '%Drumstick%';

-- Fish Products
UPDATE products SET image = '../assets/images/products/Pomfret.jpg'
WHERE name = 'Pomfret' OR name LIKE '%Pomfret%';

UPDATE products SET image = '../assets/images/products/Prawns.jpg'
WHERE name = 'Prawns' OR name LIKE '%Prawn%' OR name LIKE '%Shrimp%';

UPDATE products SET image = '../assets/images/products/Rohu-Fish.jpg'
WHERE name = 'Rohu Fish' OR name LIKE '%Rohu%';

UPDATE products SET image = '../assets/images/products/Sardines.jpg'
WHERE name = 'Sardines' OR name LIKE '%Sardine%';

UPDATE products SET image = '../assets/images/products/Seer-Fish.jpg'
WHERE name = 'Seer Fish' OR name LIKE '%Seer%';

UPDATE products SET image = '../assets/images/products/Nethili.jpg'
WHERE name = 'Nethili' OR name LIKE '%Nethili%' OR name LIKE '%Anchov%';

-- Verify the updates
SELECT id, name, image FROM products ORDER BY category_id, name;
