# Image Directory Structure - FreshChicken App

This document explains where to place different types of images in the application.

## Directory Structure

```
frontend/assets/images/
├── banners/           # Banner/slider images for home page
├── categories/        # Category icon images
├── products/          # Product images (chicken, fish, etc.)
├── placeholders/      # Placeholder images for fallbacks
└── README.md         # This file
```

## Image Placement Guidelines

### 1. Banner Images (frontend/assets/images/banners/)
**Purpose**: Homepage banner slider images
**Recommended Size**: 800x200px or 1200x300px
**Format**: JPG, PNG, or WebP
**Naming Convention**:
- `banner-1.jpg`
- `banner-2.jpg`
- `banner-chicken-offer.jpg`
- `banner-fish-special.jpg`

**Example files to add**:
- `banner-fresh-chicken.jpg` - Main chicken promotion banner
- `banner-fresh-fish.jpg` - Main fish promotion banner
- `banner-weekly-deals.jpg` - Weekly deals banner
- `banner-new-arrivals.jpg` - New products banner

**Usage in code**: Referenced in `home.html` banner slider section

---

### 2. Product Images (frontend/assets/images/products/)
**Purpose**: Product photos for chicken, fish, and other items
**Recommended Size**: 400x400px (square) or 400x300px
**Format**: JPG, PNG, or WebP
**Naming Convention**:
- `chicken-breast.jpg`
- `fish-salmon.jpg`
- `chicken-drumstick.jpg`

**Recommended products to add images for**:

**Chicken Products**:
- `chicken-breast.jpg` - Boneless chicken breast
- `chicken-drumstick.jpg` - Chicken drumsticks
- `chicken-wings.jpg` - Chicken wings
- `chicken-thigh.jpg` - Chicken thighs
- `chicken-whole.jpg` - Whole chicken
- `chicken-mince.jpg` - Minced chicken

**Fish Products**:
- `fish-salmon.jpg` - Salmon fillet
- `fish-tuna.jpg` - Tuna
- `fish-mackerel.jpg` - Mackerel
- `fish-prawns.jpg` - Fresh prawns
- `fish-crab.jpg` - Crab
- `fish-pomfret.jpg` - Pomfret

**Usage in code**:
- Product images are loaded via `IMAGE_CONFIG.getProductImage()` in `common.js`
- Database stores image filename in `products` table

---

### 3. Category Images (frontend/assets/images/categories/)
**Purpose**: Icon images for category cards
**Recommended Size**: 100x100px or 150x150px
**Format**: PNG (with transparency) or SVG
**Naming Convention**:
- `category-chicken.png`
- `category-fish.png`
- `category-seafood.png`

**Example files to add**:
- `category-chicken.png` - Chicken category icon
- `category-fish.png` - Fish category icon
- `category-seafood.png` - Seafood category icon
- `category-eggs.png` - Eggs category icon

**Usage in code**: Referenced in categories API or category rendering

---

### 4. Placeholder Images (frontend/assets/images/placeholders/)
**Purpose**: Fallback images when actual product images are missing
**Files already present**:
- `product-placeholder.svg` - Default product placeholder

**Additional placeholders you can add**:
- `banner-placeholder.jpg` - Banner placeholder
- `category-placeholder.png` - Category placeholder
- `user-avatar-placeholder.svg` - User profile placeholder

---

## How to Add Images

### Method 1: Direct File Copy
1. Navigate to the appropriate directory
2. Copy your image files to the correct folder
3. Ensure filenames follow the naming convention
4. Update database if needed (for product images)

### Method 2: Via Database (Product Images)
1. Place image in `frontend/assets/images/products/`
2. Update the `products` table in database:
   ```sql
   UPDATE products
   SET image = 'chicken-breast.jpg'
   WHERE name = 'Chicken Breast';
   ```

### Method 3: Via HTML (Banner Images)
1. Place image in `frontend/assets/images/banners/`
2. Update `home.html` banner slider section:
   ```html
   <img src="../assets/images/banners/banner-1.jpg" alt="Banner">
   ```

---

## Image Optimization Tips

1. **Compression**: Use tools like TinyPNG or ImageOptim to compress images
2. **Format**:
   - Use JPG for photos (smaller file size)
   - Use PNG for images with transparency
   - Use WebP for better compression (modern browsers)
3. **Sizing**: Don't upload images larger than needed
4. **Naming**: Use lowercase, hyphens instead of spaces
5. **Alt Text**: Always provide meaningful alt text in HTML

---

## Current Image Configuration

The app uses `IMAGE_CONFIG` in `common.js` to manage image paths:

```javascript
const IMAGE_CONFIG = {
    productsPath: '../assets/images/products/',
    placeholderPath: '../assets/images/placeholders/product-placeholder.svg',

    getProductImage: function(imageName) {
        if (!imageName) {
            return this.placeholderPath;
        }
        return this.productsPath + imageName;
    }
};
```

---

## Quick Checklist for Adding Images

- [ ] Create/obtain high-quality images
- [ ] Optimize images for web (compress)
- [ ] Rename files following naming convention
- [ ] Place files in correct directory
- [ ] Update database (if product images)
- [ ] Test image loading in browser
- [ ] Add fallback/placeholder handling

---

## Support

If images are not loading:
1. Check file path is correct
2. Verify filename matches database entry
3. Check file permissions
4. Clear browser cache
5. Check browser console for errors

---

Last Updated: December 17, 2024
Version: 2.1
