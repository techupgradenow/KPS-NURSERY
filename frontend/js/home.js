/**
 * Home Page JavaScript
 * KPS Nursery App
 */

// Include common.js first

// Banner Slider State
let currentBannerIndex = 0;
let bannerSlideCount = 0;
let bannerAutoSlideInterval = null;
let bannersData = [];

// Track current selected category
let currentCategory = 'all';

$(document).ready(function() {
    // Load banners from API first
    loadBanners();

    // Load initial data
    loadCategories();
    loadAllProducts(); // Load all products initially

    // Setup event listeners
    setupEventListeners();
});

/**
 * Load Banners from API
 */
function loadBanners() {
    $.ajax({
        url: API_ENDPOINTS.banners,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data && response.data.length > 0) {
                // Filter only active banners
                bannersData = response.data.filter(b => b.is_active == 1);

                // Remove duplicates based on image URL
                const seenImages = new Set();
                bannersData = bannersData.filter(b => {
                    const imageKey = b.image || b.desktop_image || b.mobile_image || b.id;
                    if (seenImages.has(imageKey)) {
                        return false; // Skip duplicate
                    }
                    seenImages.add(imageKey);
                    return true;
                });

                if (bannersData.length > 0) {
                    renderBanners(bannersData);
                    initBannerSlider();
                } else {
                    showEmptyBannerState();
                }
            } else {
                showEmptyBannerState();
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to load banners:', error);
            showEmptyBannerState();
        }
    });
}

/**
 * Render Banners
 */
function renderBanners(banners) {
    const track = $('#banner-track');
    const dots = $('#banner-dots');

    // Clear existing content
    track.empty();
    dots.empty();

    banners.forEach((banner, index) => {
        // Get image URLs - use desktop_image and mobile_image, fallback to image
        const desktopImg = banner.desktop_image || banner.image || '';
        const mobileImg = banner.mobile_image || banner.image || desktopImg;

        // Generate placeholder if no images
        const placeholderColor = getPlaceholderColor(index);
        const placeholderDesktop = `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1920' height='600'%3E%3Crect fill='${encodeURIComponent(placeholderColor)}' width='1920' height='600'/%3E%3Ctext x='50%25' y='50%25' text-anchor='middle' dy='.3em' fill='white' font-size='48' font-family='Arial'%3E${encodeURIComponent(banner.title || 'Banner')}%3C/text%3E%3C/svg%3E`;
        const placeholderMobile = `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1080' height='1350'%3E%3Crect fill='${encodeURIComponent(placeholderColor)}' width='1080' height='1350'/%3E%3Ctext x='50%25' y='50%25' text-anchor='middle' dy='.3em' fill='white' font-size='48' font-family='Arial'%3E${encodeURIComponent(banner.title || 'Banner')}%3C/text%3E%3C/svg%3E`;

        const activeClass = index === 0 ? 'active' : '';

        // Build banner HTML with responsive images
        let bannerHTML = `<div class="banner-slide ${activeClass}" data-index="${index}">`;

        // Check if banner has a redirect URL
        const hasLink = banner.redirect_url && banner.redirect_url.trim() !== '';

        if (hasLink) {
            bannerHTML += `<a href="${banner.redirect_url}" ${banner.redirect_type === 'external' ? 'target="_blank" rel="noopener"' : ''}>`;
        }

        // Desktop image
        bannerHTML += `
            <img class="banner-img-desktop"
                 src="${desktopImg || placeholderDesktop}"
                 alt="${banner.title || 'Banner'}"
                 loading="${index === 0 ? 'eager' : 'lazy'}"
                 onerror="this.src='${placeholderDesktop}'">
        `;

        // Mobile image
        bannerHTML += `
            <img class="banner-img-mobile"
                 src="${mobileImg || placeholderMobile}"
                 alt="${banner.title || 'Banner'}"
                 loading="${index === 0 ? 'eager' : 'lazy'}"
                 onerror="this.src='${placeholderMobile}'">
        `;

        // Add content overlay if title or description exists
        if (banner.title || banner.description) {
            bannerHTML += `
                <div class="banner-content">
                    ${banner.title ? `<h3>${banner.title}</h3>` : ''}
                    ${banner.description ? `<p>${banner.description}</p>` : ''}
                </div>
            `;
        }

        if (hasLink) {
            bannerHTML += `</a>`;
        }

        bannerHTML += `</div>`;

        track.append(bannerHTML);

        // Add dot
        const dotActiveClass = index === 0 ? 'active' : '';
        dots.append(`<div class="banner-dot ${dotActiveClass}" data-index="${index}"></div>`);
    });

    bannerSlideCount = banners.length;
}

/**
 * Get placeholder color based on index
 */
function getPlaceholderColor(index) {
    const colors = ['#22c55e', '#3b82f6', '#f59e0b', '#ec4899', '#8b5cf6', '#14b8a6'];
    return colors[index % colors.length];
}

/**
 * Show empty banner state
 */
function showEmptyBannerState() {
    const track = $('#banner-track');
    const dots = $('#banner-dots');

    track.html(`
        <div class="banner-slide active">
            <div class="banner-empty">
                <i class="fas fa-leaf"></i>
                <h3>Welcome to KPS Nursery</h3>
                <p>Fresh plants & gardening supplies delivered to your doorstep</p>
            </div>
        </div>
    `);

    dots.empty();
    bannerSlideCount = 1;
}

/**
 * Initialize Banner Slider with Auto-scroll
 */
function initBannerSlider() {
    const slides = $('.banner-slide');
    const dots = $('.banner-dot');
    bannerSlideCount = slides.length;

    // Reset current index
    currentBannerIndex = 0;

    if (bannerSlideCount <= 1) {
        // Hide navigation for single banner
        $('#banner-prev, #banner-next').hide();
        return;
    }

    // Show navigation arrows
    $('#banner-prev, #banner-next').show();

    // Start auto-slide
    startBannerAutoSlide();

    // Dot click handlers (use event delegation for dynamic dots)
    $(document).off('click', '.banner-dot').on('click', '.banner-dot', function() {
        const index = parseInt($(this).data('index'));
        goToBanner(index);
        resetBannerAutoSlide();
    });

    // Navigation arrow handlers
    $('#banner-prev').off('click').on('click', function() {
        prevBanner();
        resetBannerAutoSlide();
    });

    $('#banner-next').off('click').on('click', function() {
        nextBanner();
        resetBannerAutoSlide();
    });

    // Touch/Swipe support for mobile
    let touchStartX = 0;
    let touchEndX = 0;
    let touchStartY = 0;
    let touchEndY = 0;
    let isSwiping = false;

    $('#banner-slider').off('touchstart touchmove touchend');

    $('#banner-slider').on('touchstart', function(e) {
        touchStartX = e.originalEvent.touches[0].clientX;
        touchStartY = e.originalEvent.touches[0].clientY;
        isSwiping = true;
    });

    $('#banner-slider').on('touchmove', function(e) {
        if (!isSwiping) return;
        touchEndX = e.originalEvent.touches[0].clientX;
        touchEndY = e.originalEvent.touches[0].clientY;
    });

    $('#banner-slider').on('touchend', function(e) {
        if (!isSwiping) return;
        isSwiping = false;

        touchEndX = e.originalEvent.changedTouches[0].clientX;
        touchEndY = e.originalEvent.changedTouches[0].clientY;
        handleBannerSwipe();
    });

    function handleBannerSwipe() {
        const swipeThreshold = 50;
        const diffX = touchStartX - touchEndX;
        const diffY = Math.abs(touchStartY - touchEndY);

        // Only handle horizontal swipes (not vertical scrolling)
        if (Math.abs(diffX) > swipeThreshold && Math.abs(diffX) > diffY) {
            if (diffX > 0) {
                // Swipe left - next slide
                nextBanner();
            } else {
                // Swipe right - previous slide
                prevBanner();
            }
            resetBannerAutoSlide();
        }
    }

    // Pause on hover (desktop)
    $('#banner-slider').off('mouseenter mouseleave');

    $('#banner-slider').on('mouseenter', function() {
        stopBannerAutoSlide();
    });

    $('#banner-slider').on('mouseleave', function() {
        startBannerAutoSlide();
    });

    // Keyboard navigation (optional)
    $(document).on('keydown', function(e) {
        if (!$('#banner-slider').is(':visible')) return;

        if (e.key === 'ArrowLeft') {
            prevBanner();
            resetBannerAutoSlide();
        } else if (e.key === 'ArrowRight') {
            nextBanner();
            resetBannerAutoSlide();
        }
    });
}

/**
 * Go to specific banner
 */
function goToBanner(index) {
    if (index < 0) index = bannerSlideCount - 1;
    if (index >= bannerSlideCount) index = 0;

    currentBannerIndex = index;

    // Update slides
    $('.banner-slide').removeClass('active');
    $('.banner-slide').eq(index).addClass('active');

    // Update dots
    $('.banner-dot').removeClass('active');
    $('.banner-dot').eq(index).addClass('active');
}

/**
 * Next banner
 */
function nextBanner() {
    goToBanner(currentBannerIndex + 1);
}

/**
 * Previous banner
 */
function prevBanner() {
    goToBanner(currentBannerIndex - 1);
}

/**
 * Start auto-slide
 */
function startBannerAutoSlide() {
    if (bannerAutoSlideInterval) return;
    bannerAutoSlideInterval = setInterval(nextBanner, 4000); // 4 seconds
}

/**
 * Stop auto-slide
 */
function stopBannerAutoSlide() {
    if (bannerAutoSlideInterval) {
        clearInterval(bannerAutoSlideInterval);
        bannerAutoSlideInterval = null;
    }
}

/**
 * Reset auto-slide timer
 */
function resetBannerAutoSlide() {
    stopBannerAutoSlide();
    startBannerAutoSlide();
}

/**
 * Load Categories from API
 */
function loadCategories() {
    $.ajax({
        url: API_ENDPOINTS.categories,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                renderCategories(response.data);
            }
        },
        error: function() {
            Toast.error('Failed to load categories');
        }
    });
}

/**
 * Render Categories
 */
function renderCategories(categories) {
    const container = $('#categories-container');
    container.empty();

    // Add "All" category as the first option (always active by default)
    const allCategoryHTML = `
        <div class="category-card active" data-category="all">
            <div class="category-icon">
                <i class="fas fa-th-large"></i>
            </div>
            <span class="category-name">All</span>
        </div>
    `;
    container.append(allCategoryHTML);

    // Add remaining categories from API
    categories.forEach((category) => {
        const categorySlug = category.slug || category.name.toLowerCase().replace(/\s+/g, '-');
        const categoryHTML = `
            <div class="category-card" data-category="${categorySlug}">
                <div class="category-icon">
                    <i class="fas ${category.icon}"></i>
                </div>
                <span class="category-name">${category.name}</span>
            </div>
        `;
        container.append(categoryHTML);
    });
}

/**
 * Load All Products (for initial display)
 */
function loadAllProducts() {
    Loading.show();

    $.ajax({
        url: API_ENDPOINTS.products + '?action=all',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            Loading.hide();
            if (response.success && response.data) {
                // Store all products for filtering
                window.allProducts = response.data;

                // Clear dynamic sections
                $('.dynamic-category-section').remove();

                // Get popular products
                const popularProducts = response.data.filter(p => p.is_popular);

                // Show Popular Items section
                if (popularProducts.length > 0) {
                    $('#popular-products').closest('.products-section').find('.section-title').text('Popular Items');
                    renderProducts(popularProducts, '#popular-products');
                } else {
                    // If no popular products, show first 6 products
                    $('#popular-products').closest('.products-section').find('.section-title').text('Featured Products');
                    renderProducts(response.data.slice(0, 6), '#popular-products');
                }

                // Group remaining products by category and render below
                renderCategorySections(response.data);
            }
        },
        error: function() {
            Loading.hide();
            Toast.error('Failed to load products');
        }
    });
}

/**
 * Render products grouped by category dynamically
 */
function renderCategorySections(products) {
    // Group products by category
    const categories = {};
    products.forEach(product => {
        const categoryName = product.category_name || 'Other';
        const categorySlug = product.category_slug || 'other';
        if (!categories[categorySlug]) {
            categories[categorySlug] = {
                name: categoryName,
                slug: categorySlug,
                products: []
            };
        }
        categories[categorySlug].products.push(product);
    });

    // Get the container to append sections after
    const mainSection = $('#popular-products').closest('.products-section');

    // Create section for each category with products
    Object.values(categories).forEach(category => {
        if (category.products.length > 0) {
            const sectionId = `category-${category.slug}-section`;
            const productsId = `category-${category.slug}-products`;

            const sectionHTML = `
                <section class="products-section dynamic-category-section" id="${sectionId}">
                    <h2 class="section-title">${category.name}</h2>
                    <div class="product-grid" id="${productsId}">
                    </div>
                </section>
            `;

            mainSection.after(sectionHTML);
            renderProducts(category.products, `#${productsId}`);
        }
    });
}

/**
 * Load Products by Category (when category is clicked)
 */
function loadProductsByCategory(category) {
    Loading.show();
    currentCategory = category;

    $.ajax({
        url: API_ENDPOINTS.products + `?action=category&category=${category}`,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            Loading.hide();
            if (response.success && response.data) {
                // Update section title
                $('#popular-products').closest('.products-section').find('.section-title').text(capitalizeFirst(category) + ' Products');

                // Show filtered products in the popular products section
                renderProducts(response.data, '#popular-products');

                // Hide dynamic category sections when filtering
                $('.dynamic-category-section').hide();

                // Scroll to products section
                $('html, body').animate({
                    scrollTop: $('#popular-products').offset().top - 100
                }, 300);
            }
        },
        error: function() {
            Loading.hide();
            Toast.error(`Failed to load ${category} products`);
        }
    });
}

/**
 * Show all products (reset filter)
 */
function showAllProducts() {
    currentCategory = 'all';

    // Reload all products (will set correct title based on popular products)
    loadAllProducts();
}

/**
 * Capitalize first letter
 */
function capitalizeFirst(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

/**
 * Render Products
 */
function renderProducts(products, containerSelector) {
    const container = $(containerSelector);
    container.empty();

    if (products.length === 0) {
        container.html('<p style="text-align: center; color: var(--gray); padding: 2rem;">No products found</p>');
        return;
    }

    products.forEach(product => {
        // Check if product is in cart
        const cartItem = AppState.cart.find(item => item.id == product.id);
        const quantityInCart = cartItem ? cartItem.quantity : 0;
        const isOutOfStock = product.stock <= 0;

        // Use getImagePath to normalize image path for both local and production
        const imageSrc = typeof getImagePath === 'function' ? getImagePath(product.image) : product.image;

        const productHTML = `
            <div class="product-card" data-id="${product.id}">
                <div class="product-image" onclick="viewProductDetail(${product.id})">
                    <img src="${imageSrc}"
                         alt="${product.name}"
                         onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22200%22%3E%3Crect fill=%22%23f3f4f6%22 width=%22200%22 height=%22200%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 fill=%22%239ca3af%22 font-size=%2214%22%3ENo Image%3C/text%3E%3C/svg%3E';">
                    ${isOutOfStock ? '<div class="out-of-stock-badge">Out of Stock</div>' : ''}
                </div>
                <div class="product-details">
                    <h3 class="product-name" onclick="viewProductDetail(${product.id})">${product.name}</h3>
                    <div class="product-rating">
                        ${renderStars(product.rating || 0)}
                        <span class="rating-count">(${product.reviews || 0})</span>
                    </div>
                    <div class="product-footer">
                        <div class="product-price">
                            <span class="price">₹${parseFloat(product.price).toFixed(2)}</span>
                            <span class="unit">/${product.unit || 'kg'}</span>
                        </div>
                        ${quantityInCart > 0 ? `
                            <div class="quantity-controls" data-product-id="${product.id}">
                                <button class="qty-btn minus" onclick="event.stopPropagation(); decreaseProductQty(${product.id})">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span class="qty-value">${quantityInCart}</span>
                                <button class="qty-btn plus" onclick="event.stopPropagation(); increaseProductQty(${product.id})">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        ` : `
                            <button class="add-to-cart-btn" onclick="event.stopPropagation(); addToCart(${product.id})" ${isOutOfStock ? 'disabled' : ''}>
                                <i class="fas fa-cart-plus"></i> Add
                            </button>
                        `}
                    </div>
                </div>
            </div>
        `;
        container.append(productHTML);
    });
}

/**
 * Render Star Rating
 */
function renderStars(rating) {
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 >= 0.5;
    let starsHTML = '';

    for (let i = 0; i < fullStars; i++) {
        starsHTML += '<i class="fas fa-star"></i>';
    }

    if (hasHalfStar) {
        starsHTML += '<i class="fas fa-star-half-alt"></i>';
    }

    const emptyStars = 5 - Math.ceil(rating);
    for (let i = 0; i < emptyStars; i++) {
        starsHTML += '<i class="far fa-star"></i>';
    }

    return starsHTML;
}

/**
 * Add Product to Cart
 */
function addToCart(productId) {
    Loading.show();

    // Get product details
    $.ajax({
        url: API_ENDPOINTS.products + `?action=detail&id=${productId}`,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            Loading.hide();

            if (response.success && response.data) {
                const product = response.data;
                AppState.addToCart(product, 1);
                Toast.success(`${product.name} added to cart!`);
                // Refresh the product card to show quantity controls
                refreshProductCard(productId);
            } else {
                Toast.error('Failed to add product');
            }
        },
        error: function() {
            Loading.hide();
            Toast.error('Failed to add product');
        }
    });
}

/**
 * Increase Product Quantity in Cart
 */
function increaseProductQty(productId) {
    const cartItem = AppState.cart.find(item => item.id == productId);
    if (cartItem) {
        AppState.updateQuantity(productId, cartItem.quantity + 1);
        updateProductQtyDisplay(productId, cartItem.quantity + 1);
    }
}

/**
 * Decrease Product Quantity in Cart
 */
function decreaseProductQty(productId) {
    const cartItem = AppState.cart.find(item => item.id == productId);
    if (cartItem) {
        if (cartItem.quantity <= 1) {
            AppState.removeFromCart(productId);
            refreshProductCard(productId);
            Toast.info('Item removed from cart');
        } else {
            AppState.updateQuantity(productId, cartItem.quantity - 1);
            updateProductQtyDisplay(productId, cartItem.quantity - 1);
        }
    }
}

/**
 * Update quantity display without full re-render
 */
function updateProductQtyDisplay(productId, newQty) {
    $(`.quantity-controls[data-product-id="${productId}"] .qty-value`).text(newQty);
}

/**
 * Refresh a single product card
 */
function refreshProductCard(productId) {
    // Reload products to refresh UI based on current category
    if (currentCategory === 'all') {
        loadAllProducts();
    } else {
        loadProductsByCategory(currentCategory);
    }
}

/**
 * View Product Detail
 */
function viewProductDetail(productId) {
    Loading.show();

    $.ajax({
        url: API_ENDPOINTS.products + `?action=detail&id=${productId}`,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            Loading.hide();

            if (response.success && response.data) {
                showProductDetailModal(response.data);
            } else {
                Toast.error('Product not found');
            }
        },
        error: function() {
            Loading.hide();
            Toast.error('Failed to load product details');
        }
    });
}

/**
 * Show Product Detail Modal
 */
function showProductDetailModal(product) {
    // Store current product for review submission
    window.currentProductId = product.id;

    // Use getImagePath to normalize image path
    const imageSrc = typeof getImagePath === 'function' ? getImagePath(product.image) : product.image;

    const modalHTML = `
        <div class="product-detail-image" style="background-image: url('${imageSrc}');" onclick="openImageLightbox('${imageSrc}')">
            <div class="image-zoom-hint"><i class="fas fa-search-plus"></i></div>
        </div>
        <div class="product-detail-info">
            <h2>${product.name}</h2>
            <div class="product-rating" onclick="scrollToReviews()">
                ${renderStars(product.rating || 0)}
                <span class="rating-count">(${product.reviews || 0} reviews)</span>
                <i class="fas fa-chevron-right rating-arrow"></i>
            </div>
            <div class="product-price">
                <span class="price-amount">₹${product.price}</span>
                <span class="price-unit">/${product.unit || 'kg'}</span>
            </div>
            <p class="product-description">${product.description || 'Freshly prepared with premium quality ingredients.'}</p>

            <div class="quantity-selector">
                <button class="quantity-btn" onclick="decreaseQuantity()">
                    <i class="fas fa-minus"></i>
                </button>
                <input type="number" id="product-quantity" value="1" min="1" readonly>
                <button class="quantity-btn" onclick="increaseQuantity()">
                    <i class="fas fa-plus"></i>
                </button>
            </div>

            <button class="btn btn-primary btn-block" onclick="addToCartFromModal(${product.id})">
                <i class="fas fa-cart-plus"></i> ADD TO CART
            </button>

            <!-- Customer Reviews Section -->
            <div class="reviews-section" id="reviews-section">
                <div class="reviews-header">
                    <h3><i class="fas fa-star"></i> Customer Reviews</h3>
                    <button class="btn-add-review" onclick="openAddReviewModal(${product.id}, '${product.name.replace(/'/g, "\\'")}')">
                        <i class="fas fa-edit"></i> Write Review
                    </button>
                </div>

                <!-- Rating Summary - Will be populated from API -->
                <div class="rating-summary" id="rating-summary">
                    <div class="rating-big">
                        <span class="rating-number">${product.rating || 0}</span>
                        <div class="rating-stars">${renderStars(product.rating || 0)}</div>
                        <span class="rating-total">${product.reviews || 0} reviews</span>
                    </div>
                    <div class="rating-bars" id="rating-bars">
                        <div class="rating-bar-row"><span>5</span><div class="rating-bar"><div class="rating-bar-fill" style="width: 0%"></div></div><span class="bar-count">0%</span></div>
                        <div class="rating-bar-row"><span>4</span><div class="rating-bar"><div class="rating-bar-fill" style="width: 0%"></div></div><span class="bar-count">0%</span></div>
                        <div class="rating-bar-row"><span>3</span><div class="rating-bar"><div class="rating-bar-fill" style="width: 0%"></div></div><span class="bar-count">0%</span></div>
                        <div class="rating-bar-row"><span>2</span><div class="rating-bar"><div class="rating-bar-fill" style="width: 0%"></div></div><span class="bar-count">0%</span></div>
                        <div class="rating-bar-row"><span>1</span><div class="rating-bar"><div class="rating-bar-fill" style="width: 0%"></div></div><span class="bar-count">0%</span></div>
                    </div>
                </div>

                <!-- Review List - Will be populated from API -->
                <div class="reviews-list" id="reviews-list">
                    <div class="loading-reviews">
                        <i class="fas fa-spinner fa-spin"></i> Loading reviews...
                    </div>
                </div>

                <button class="btn-view-all-reviews" onclick="Toast.info('All reviews displayed above')">
                    View All Reviews <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>
    `;

    $('#product-detail-content').html(modalHTML);
    $('#product-detail-modal').addClass('active').fadeIn(200);

    // Load reviews from API
    loadProductReviews(product.id);
}

/**
 * Load Reviews from API
 */
function loadProductReviews(productId) {
    $.ajax({
        url: API_ENDPOINTS.reviews + `?product_id=${productId}`,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                renderReviewsFromAPI(response.data.reviews, response.data.stats);
            } else {
                $('#reviews-list').html('<p class="no-reviews">No reviews yet. Be the first to review!</p>');
            }
        },
        error: function() {
            $('#reviews-list').html('<p class="no-reviews">No reviews yet. Be the first to review!</p>');
        }
    });
}

/**
 * Render Reviews from API data
 */
function renderReviewsFromAPI(reviews, stats) {
    // Update rating summary
    if (stats) {
        const total = stats.total_reviews || 0;
        $('.rating-summary .rating-number').text(stats.avg_rating.toFixed(1));
        $('.rating-summary .rating-stars').html(renderStars(stats.avg_rating));
        $('.rating-summary .rating-total').text(`${total} reviews`);

        // Update rating bars
        if (total > 0) {
            const dist = stats.rating_distribution;
            for (let i = 5; i >= 1; i--) {
                const count = dist[i] || 0;
                const percentage = Math.round((count / total) * 100);
                const $row = $('#rating-bars .rating-bar-row').eq(5 - i);
                $row.find('.rating-bar-fill').css('width', percentage + '%');
                $row.find('.bar-count').text(percentage + '%');
            }
        }
    }

    // Render reviews list
    if (!reviews || reviews.length === 0) {
        $('#reviews-list').html('<p class="no-reviews">No reviews yet. Be the first to review!</p>');
        return;
    }

    const reviewsHTML = reviews.map(review => `
        <div class="review-item" data-review-id="${review.id}">
            <div class="review-header">
                <div class="reviewer-avatar">${review.reviewer_name.charAt(0).toUpperCase()}</div>
                <div class="reviewer-info">
                    <span class="reviewer-name">${review.reviewer_name}</span>
                    <div class="review-meta">
                        <span class="review-stars">${renderStars(review.rating)}</span>
                        <span class="review-date">${review.time_ago}</span>
                    </div>
                </div>
            </div>
            <p class="review-comment">${review.comment}</p>
            <div class="review-actions">
                <button class="btn-helpful" onclick="markHelpful(${review.id}, this)">
                    <i class="far fa-thumbs-up"></i> Helpful (${review.helpful_count || 0})
                </button>
            </div>
        </div>
    `).join('');

    $('#reviews-list').html(reviewsHTML);
}

/**
 * Generate Sample Reviews for Product
 */
function generateSampleReviews(product) {
    const reviewTemplates = [
        {
            name: 'Rahul S.',
            avatar: 'R',
            rating: 5,
            date: '2 days ago',
            comment: 'Excellent quality! Very fresh and delivered on time. Will definitely order again.',
            helpful: 12
        },
        {
            name: 'Priya M.',
            avatar: 'P',
            rating: 4,
            date: '1 week ago',
            comment: 'Good quality product. Packaging was neat and hygienic. Slightly smaller portion than expected.',
            helpful: 8
        },
        {
            name: 'Amit K.',
            avatar: 'A',
            rating: 5,
            date: '2 weeks ago',
            comment: 'Best quality I\'ve found online. The freshness is unmatched. Highly recommended!',
            helpful: 15
        }
    ];

    return reviewTemplates.map(review => `
        <div class="review-item">
            <div class="review-header">
                <div class="reviewer-avatar">${review.avatar}</div>
                <div class="reviewer-info">
                    <span class="reviewer-name">${review.name}</span>
                    <div class="review-meta">
                        <span class="review-stars">${renderStars(review.rating)}</span>
                        <span class="review-date">${review.date}</span>
                    </div>
                </div>
            </div>
            <p class="review-comment">${review.comment}</p>
            <div class="review-actions">
                <button class="btn-helpful" onclick="markHelpful(this)">
                    <i class="far fa-thumbs-up"></i> Helpful (${review.helpful})
                </button>
            </div>
        </div>
    `).join('');
}

/**
 * Scroll to Reviews Section
 */
function scrollToReviews() {
    const reviewsSection = document.getElementById('reviews-section');
    if (reviewsSection) {
        reviewsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

/**
 * Mark Review as Helpful (API call)
 */
function markHelpful(reviewId, btn) {
    const $btn = $(btn);
    if ($btn.hasClass('marked')) {
        Toast.info('You already marked this as helpful');
        return;
    }

    $.ajax({
        url: API_ENDPOINTS.reviews + '/helpful',
        type: 'PUT',
        contentType: 'application/json',
        data: JSON.stringify({ review_id: reviewId }),
        success: function(response) {
            if (response.success) {
                $btn.addClass('marked');
                $btn.html(`<i class="fas fa-thumbs-up"></i> Helpful (${response.data.helpful_count})`);
                Toast.success('Thanks for your feedback!');
            }
        },
        error: function() {
            // Fallback to local update
            $btn.addClass('marked');
            const text = $btn.text();
            const count = parseInt(text.match(/\d+/)[0]) + 1;
            $btn.html(`<i class="fas fa-thumbs-up"></i> Helpful (${count})`);
            Toast.success('Thanks for your feedback!');
        }
    });
}

/**
 * Open Add Review Modal
 */
function openAddReviewModal(productId, productName) {
    const user = AppState.loadUser();

    const modalHTML = `
        <div class="add-review-modal" id="add-review-modal">
            <div class="add-review-content">
                <div class="add-review-header">
                    <h3>Write a Review</h3>
                    <button class="close-review-modal" onclick="closeAddReviewModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="add-review-body">
                    <p class="review-product-name">Reviewing: <strong>${productName}</strong></p>

                    <div class="rating-input">
                        <label>Your Rating</label>
                        <div class="star-rating-input" id="star-rating-input">
                            <i class="far fa-star" data-rating="1"></i>
                            <i class="far fa-star" data-rating="2"></i>
                            <i class="far fa-star" data-rating="3"></i>
                            <i class="far fa-star" data-rating="4"></i>
                            <i class="far fa-star" data-rating="5"></i>
                        </div>
                        <input type="hidden" id="review-rating" value="0">
                    </div>

                    <div class="review-input-group">
                        <label>Your Name</label>
                        <input type="text" id="reviewer-name" placeholder="Enter your name" value="${user?.name || ''}">
                    </div>

                    <div class="review-input-group">
                        <label>Your Review</label>
                        <textarea id="review-text" placeholder="Share your experience with this product..." rows="4"></textarea>
                    </div>

                    <button class="btn btn-primary btn-block" id="submit-review-btn" data-product-id="${productId}">
                        <i class="fas fa-paper-plane"></i> Submit Review
                    </button>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    $('#add-review-modal').remove();
    $('body').append(modalHTML);

    // Initialize star rating
    initStarRatingInput();

    setTimeout(() => {
        $('#add-review-modal').addClass('active');
    }, 10);
}

/**
 * Initialize Star Rating Input
 */
function initStarRatingInput() {
    // Use event delegation for star clicks
    $(document).off('click', '#star-rating-input i').on('click', '#star-rating-input i', function() {
        const rating = $(this).data('rating');
        console.log('Star clicked, rating:', rating);
        $('#review-rating').val(rating);

        $('#star-rating-input i').each(function() {
            const starRating = $(this).data('rating');
            if (starRating <= rating) {
                $(this).removeClass('far').addClass('fas');
            } else {
                $(this).removeClass('fas').addClass('far');
            }
        });
    });

    $(document).off('mouseenter', '#star-rating-input i').on('mouseenter', '#star-rating-input i', function() {
        const rating = $(this).data('rating');
        $('#star-rating-input i').each(function() {
            const starRating = $(this).data('rating');
            if (starRating <= rating) {
                $(this).addClass('hover');
            }
        });
    });

    $(document).off('mouseleave', '#star-rating-input i').on('mouseleave', '#star-rating-input i', function() {
        $('#star-rating-input i').removeClass('hover');
    });

    // Bind submit button click with event delegation
    $(document).off('click', '#submit-review-btn').on('click', '#submit-review-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const productId = $(this).data('product-id');
        console.log('Submit button clicked, productId:', productId);
        submitReview(productId);
    });
}

/**
 * Close Add Review Modal
 */
function closeAddReviewModal() {
    $('#add-review-modal').removeClass('active');
    setTimeout(() => {
        $('#add-review-modal').remove();
    }, 300);
}

/**
 * Submit Review (API call)
 */
function submitReview(productId) {
    console.log('submitReview called with productId:', productId);

    const rating = parseInt($('#review-rating').val()) || 0;
    const name = $('#reviewer-name').val().trim();
    const comment = $('#review-text').val().trim();

    console.log('Review data:', { rating, name, comment, commentLength: comment.length });

    if (rating === 0 || isNaN(rating)) {
        Toast.error('Please select a rating');
        return;
    }

    if (!name) {
        Toast.error('Please enter your name');
        return;
    }

    if (!comment) {
        Toast.error('Please write your review');
        return;
    }

    if (comment.length < 5) {
        Toast.error('Review must be at least 5 characters (currently ' + comment.length + ')');
        return;
    }

    // Show loading
    Loading.show();

    // Get user info if logged in
    const user = AppState.loadUser();

    // Submit review to API
    $.ajax({
        url: API_ENDPOINTS.reviews,
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            product_id: productId,
            rating: rating,
            comment: comment,
            reviewer_name: name,
            user_id: user?.id || null
        }),
        success: function(response) {
            Loading.hide();

            if (response.success) {
                Toast.success('Thank you! Your review has been submitted.');
                closeAddReviewModal();

                // Add the new review to the list (at the top)
                const newReviewHTML = `
                    <div class="review-item new-review" data-review-id="${response.data.review_id}">
                        <div class="review-header">
                            <div class="reviewer-avatar">${name.charAt(0).toUpperCase()}</div>
                            <div class="reviewer-info">
                                <span class="reviewer-name">${name}</span>
                                <div class="review-meta">
                                    <span class="review-stars">${renderStars(rating)}</span>
                                    <span class="review-date">Just now</span>
                                </div>
                            </div>
                        </div>
                        <p class="review-comment">${comment}</p>
                        <div class="review-actions">
                            <button class="btn-helpful" onclick="markHelpful(${response.data.review_id}, this)">
                                <i class="far fa-thumbs-up"></i> Helpful (0)
                            </button>
                        </div>
                    </div>
                `;

                // Remove "no reviews" message if present
                $('.no-reviews').remove();

                // Add new review at top
                $('#reviews-list').prepend(newReviewHTML);

                // Reload reviews to update stats
                loadProductReviews(productId);
            } else {
                Toast.error(response.message || 'Failed to submit review');
            }
        },
        error: function(xhr) {
            Loading.hide();
            const response = xhr.responseJSON;
            Toast.error(response?.message || 'Failed to submit review. Please try again.');
        }
    });
}

/**
 * Add to Cart from Modal
 */
function addToCartFromModal(productId) {
    const quantity = parseInt($('#product-quantity').val());

    Loading.show();

    $.ajax({
        url: API_ENDPOINTS.products + `?action=detail&id=${productId}`,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            Loading.hide();

            if (response.success && response.data) {
                const product = response.data;
                AppState.addToCart(product, quantity);
                Toast.success(`${product.name} (x${quantity}) added to cart!`);
                $('#product-detail-modal').fadeOut(200);
            }
        },
        error: function() {
            Loading.hide();
            Toast.error('Failed to add product');
        }
    });
}

/**
 * Increase/Decrease Quantity
 */
function increaseQuantity() {
    const input = $('#product-quantity');
    const currentValue = parseInt(input.val());
    input.val(currentValue + 1);
}

function decreaseQuantity() {
    const input = $('#product-quantity');
    const currentValue = parseInt(input.val());
    if (currentValue > 1) {
        input.val(currentValue - 1);
    }
}

/**
 * Open Image Lightbox
 */
function openImageLightbox(imageUrl) {
    $('#lightbox-image').attr('src', imageUrl);
    $('#image-lightbox').fadeIn(200);
}

/**
 * Search Products
 */
function searchProducts(query) {
    if (query.trim().length < 2) {
        $('#search-results').html(`
            <div class="search-empty">
                <i class="fas fa-search"></i>
                <div class="title">Search for products</div>
                <div class="subtitle">Start typing to find cakes, pastries, and more</div>
            </div>
        `);
        return;
    }

    // Show loading in results area
    $('#search-results').html(`
        <div class="search-empty">
            <i class="fas fa-spinner fa-spin"></i>
            <div class="title">Searching...</div>
            <div class="subtitle">Finding products for "${query}"</div>
        </div>
    `);

    $.ajax({
        url: API_ENDPOINTS.products + `?action=search&q=${encodeURIComponent(query)}`,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data && response.data.length > 0) {
                renderSearchResults(response.data);
            } else {
                $('#search-results').html(`
                    <div class="search-empty">
                        <i class="fas fa-box-open"></i>
                        <div class="title">No results found</div>
                        <div class="subtitle">Try searching for "cake" or "pastry"</div>
                    </div>
                `);
            }
        },
        error: function() {
            $('#search-results').html(`
                <div class="search-empty">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div class="title">Search failed</div>
                    <div class="subtitle">Please check your connection and try again</div>
                </div>
            `);
        }
    });
}

/**
 * Render Search Results
 */
function renderSearchResults(products) {
    const resultsHTML = products.map(product => {
        // Use getImagePath to normalize image path
        const imageUrl = typeof getImagePath === 'function' ? getImagePath(product.image) : (product.image || '../assets/placeholder.png');
        return `
            <div class="search-result-item" onclick="selectSearchProduct(${product.id})">
                <img src="${imageUrl}" alt="${product.name}" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2260%22 height=%2260%22%3E%3Crect fill=%22%23E2E8F0%22 width=%2260%22 height=%2260%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23A0AEC0%22 font-size=%2212%22%3ENo img%3C/text%3E%3C/svg%3E'">
                <div class="search-result-info">
                    <div class="search-result-name">${product.name}</div>
                    <div class="search-result-category">${product.category_name || 'Fresh Products'}</div>
                </div>
                <div class="search-result-price">₹${parseFloat(product.price).toFixed(0)}/${product.unit || 'kg'}</div>
            </div>
        `;
    }).join('');

    $('#search-results').html(resultsHTML);
}

/**
 * Handle search result selection
 */
function selectSearchProduct(productId) {
    // Close search modal
    $('#search-modal').removeClass('active').fadeOut(200);
    // Open product detail
    viewProductDetail(productId);
}

/**
 * Setup Event Listeners
 */
function setupEventListeners() {
    // Search button - open modal
    $('#search-btn').on('click', function() {
        $('#search-modal').addClass('active').css('display', 'flex');
        $('#search-input').val('').focus();
        // Reset search results to initial state
        $('#search-results').html(`
            <div class="search-empty">
                <i class="fas fa-search"></i>
                <div class="title">Search for products</div>
                <div class="subtitle">Start typing to find cakes, pastries, and more</div>
            </div>
        `);
    });

    // Close search modal
    $('#close-search-modal').on('click', function() {
        $('#search-modal').removeClass('active').fadeOut(200);
    });

    // Search input with debounce - use arrow function to preserve context
    let searchTimeout;
    $('#search-input').on('input', function() {
        const query = $(this).val();
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            searchProducts(query);
        }, 300);
    });

    // Search input - Enter key to search immediately
    $('#search-input').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(searchTimeout);
            const query = $(this).val();
            searchProducts(query);
        } else if (e.key === 'Escape') {
            $('#search-modal').removeClass('active').fadeOut(200);
        }
    });

    // Close search modal on overlay click
    $('#search-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).removeClass('active').fadeOut(200);
        }
    });

    // Category selection
    $(document).on('click', '.category-card', function() {
        const category = $(this).data('category');

        // If clicking already active category, do nothing
        if ($(this).hasClass('active')) {
            return;
        }

        $('.category-card').removeClass('active');
        $(this).addClass('active');

        // If "All" is selected, show all products
        if (category === 'all') {
            showAllProducts();
        } else {
            loadProductsByCategory(category);
        }
    });

    // Close product detail modal - use event delegation for dynamically loaded content
    $(document).on('click', '#close-product-detail', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('#product-detail-modal').removeClass('active').fadeOut(200);
    });

    // Close lightbox
    $('#close-lightbox').on('click', function() {
        $('#image-lightbox').fadeOut(200);
    });

    // Close modals on overlay click
    $('.modal').on('click', function(e) {
        if (e.target === this) {
            $(this).fadeOut(200);
        }
    });

    // Load promotional popup after a short delay
    setTimeout(() => {
        loadOfferPopup();
    }, 1500);
}

// ============================================
// OFFER POPUP FUNCTIONALITY
// ============================================

/**
 * Load and display offer popup based on display rules
 */
function loadOfferPopup() {
    $.ajax({
        url: API_ENDPOINTS.popups,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                const popup = response.data;
                if (shouldShowPopup(popup)) {
                    showOfferPopup(popup);
                }
            }
        },
        error: function(xhr, status, error) {
            console.log('No active popup available');
        }
    });
}

/**
 * Check if popup should be shown based on display_rule
 * @param {Object} popup - Popup data from API
 * @returns {boolean} - Whether to show the popup
 */
function shouldShowPopup(popup) {
    const popupId = popup.id;
    const displayRule = popup.display_rule || 'once_per_session';

    switch (displayRule) {
        case 'first_load':
            // Show only on very first visit ever
            const hasSeenFirstLoad = localStorage.getItem(`popup_first_${popupId}`);
            if (hasSeenFirstLoad) return false;
            localStorage.setItem(`popup_first_${popupId}`, 'true');
            return true;

        case 'every_visit':
            // Always show on every page load
            return true;

        case 'once_per_day':
            // Show once per day
            const today = new Date().toDateString();
            const lastShownDate = localStorage.getItem(`popup_day_${popupId}`);
            if (lastShownDate === today) return false;
            localStorage.setItem(`popup_day_${popupId}`, today);
            return true;

        case 'once_per_session':
        default:
            // Show once per browser session
            const hasSeenThisSession = sessionStorage.getItem(`popup_session_${popupId}`);
            if (hasSeenThisSession) return false;
            sessionStorage.setItem(`popup_session_${popupId}`, 'true');
            return true;
    }
}

/**
 * Display the offer popup - Image only with close button
 * @param {Object} popup - Popup data
 */
function showOfferPopup(popup) {
    // Only show if there's an image
    if (!popup.image) return;

    // Remove any existing popup
    $('#offer-popup-overlay').remove();

    const popupHTML = `
        <div class="offer-popup-overlay" id="offer-popup-overlay">
            <div class="offer-popup-container">
                <style>
                    .offer-popup-overlay {
                        position: fixed;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: rgba(0, 0, 0, 0.7);
                        backdrop-filter: blur(8px);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        z-index: 9999;
                        opacity: 0;
                        animation: popupFadeIn 0.3s ease forwards;
                    }
                    @keyframes popupFadeIn {
                        to { opacity: 1; }
                    }
                    @keyframes popupSlideIn {
                        from { opacity: 0; transform: scale(0.8) translateY(30px); }
                        to { opacity: 1; transform: scale(1) translateY(0); }
                    }
                    .offer-popup-container {
                        position: relative;
                        max-width: 400px;
                        width: 90%;
                        max-height: 90vh;
                        border-radius: 20px;
                        overflow: hidden;
                        box-shadow: 0 25px 80px rgba(0,0,0,0.3), 0 10px 30px rgba(0,0,0,0.2);
                        animation: popupSlideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
                    }
                    .offer-popup-close {
                        position: absolute;
                        top: 10px;
                        right: 10px;
                        width: 32px;
                        height: 32px;
                        border-radius: 50%;
                        border: none;
                        background: rgba(0,0,0,0.5);
                        color: #fff;
                        font-size: 16px;
                        cursor: pointer;
                        z-index: 10;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        transition: all 0.3s ease;
                    }
                    .offer-popup-close:hover {
                        background: rgba(0,0,0,0.7);
                        transform: scale(1.1);
                    }
                    .offer-popup-image {
                        width: 100%;
                        height: auto;
                        display: block;
                    }
                </style>

                <!-- Close Button -->
                <button class="offer-popup-close" onclick="closeOfferPopup()">
                    <i class="fas fa-times"></i>
                </button>

                <!-- Image Only -->
                <img src="${popup.image}" alt="Offer" class="offer-popup-image" onerror="closeOfferPopup()">
            </div>
        </div>
    `;

    $('body').append(popupHTML);

    // Close on overlay click
    $('#offer-popup-overlay').on('click', function(e) {
        if (e.target === this) {
            closeOfferPopup();
        }
    });

    // Close on ESC key
    $(document).on('keydown.offerPopup', function(e) {
        if (e.key === 'Escape') {
            closeOfferPopup();
        }
    });
}

/**
 * Close the offer popup
 */
function closeOfferPopup() {
    const $overlay = $('#offer-popup-overlay');
    $overlay.css('animation', 'popupFadeIn 0.2s ease reverse forwards');
    $overlay.find('.offer-popup-container').css('animation', 'popupSlideIn 0.2s ease reverse forwards');

    setTimeout(() => {
        $overlay.remove();
        $(document).off('keydown.offerPopup');
    }, 200);
}

/**
 * Handle popup CTA button click
 * @param {string} link - The link to navigate to
 */
function handlePopupCta(link) {
    closeOfferPopup();
    if (link && link.trim() !== '') {
        // Small delay to let popup close animation finish
        setTimeout(() => {
            window.location.href = link;
        }, 200);
    }
}
