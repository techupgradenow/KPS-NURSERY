-- Update Existing Products with Local Images
-- Run this in phpMyAdmin to replace Unsplash URLs with local images
-- Database: freshchicken_db

-- ============================================
-- CHICKEN PRODUCTS (Category ID: 1)
-- ============================================

-- ID 1: Whole Chicken
UPDATE products
SET image = '../assets/images/products/Whole-Chicken.jpg'
WHERE id = 1;

-- ID 2: Chicken Curry Cut
UPDATE products
SET image = '../assets/images/products/Chicken Curry Cut.jpg'
WHERE id = 2;

-- ID 3: Boneless Chicken
UPDATE products
SET image = '../assets/images/products/Boneless-Chicken.jpg'
WHERE id = 3;

-- ID 4: Chicken Liver (using placeholder - no image available)
UPDATE products
SET image = '../assets/images/placeholders/product-placeholder.png'
WHERE id = 4;

-- ID 5: Chicken Legs
UPDATE products
SET image = '../assets/images/products/Chicken-Legs.jpg'
WHERE id = 5;

-- ID 6: Chicken Wings (using placeholder - no image available)
UPDATE products
SET image = '../assets/images/placeholders/product-placeholder.png'
WHERE id = 6;

-- ============================================
-- FISH PRODUCTS (Category ID: 2)
-- ============================================

-- ID 7: Seer Fish
UPDATE products
SET image = '../assets/images/products/Seer-Fish.jpg'
WHERE id = 7;

-- ID 8: Pomfret
UPDATE products
SET image = '../assets/images/products/Pomfret.jpg'
WHERE id = 8;

-- ID 9: Prawns
UPDATE products
SET image = '../assets/images/products/Prawns.jpg'
WHERE id = 9;

-- ID 10: Nethili / Anchovy
UPDATE products
SET image = '../assets/images/products/Nethili.jpg'
WHERE id = 10;

-- ID 11: Rohu Fish
UPDATE products
SET image = '../assets/images/products/Rohu-Fish.jpg'
WHERE id = 11;

-- ID 12: Sardines
UPDATE products
SET image = '../assets/images/products/Sardines.jpg'
WHERE id = 12;

-- ============================================
-- VERIFY UPDATES
-- ============================================

SELECT
    id,
    name,
    image,
    CASE
        WHEN image LIKE '../assets/images/products/%' THEN '✅ Local Image'
        WHEN image LIKE '../assets/images/placeholders/%' THEN '⚠️ Placeholder'
        ELSE '❌ Still External URL'
    END as status
FROM products
ORDER BY category_id, id;

-- Display summary
SELECT
    '✅ Products updated successfully!' as message,
    COUNT(*) as total_products,
    SUM(CASE WHEN image LIKE '../assets/images/products/%' THEN 1 ELSE 0 END) as with_local_images,
    SUM(CASE WHEN image LIKE '../assets/images/placeholders/%' THEN 1 ELSE 0 END) as with_placeholders
FROM products;
