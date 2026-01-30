/**
 * Common Utilities - Shared across all pages
 * KPS Nursery App
 */

// ============================================
// Environment Detection & API Configuration
// ============================================

// Detect environment based on hostname
const hostname = window.location.hostname;
const isLocalhost = hostname === 'localhost' ||
                    hostname === '127.0.0.1' ||
                    hostname.includes('192.168.') ||
                    hostname.includes('.local');

// Production domains (add all production domains here)
const PRODUCTION_DOMAINS = [
    'kpsnursery.upgradenow.in',
    'www.kpsnursery.upgradenow.in'
];

// Check if current hostname is a production domain
const isProduction = PRODUCTION_DOMAINS.some(domain => hostname.includes(domain));

// Set API base URL based on environment
const API_BASE = isLocalhost
    ? '../../backend/api/'  // Local development (relative path)
    : '/backend/api/';      // Production (absolute path from root)

// API Endpoints
const API_ENDPOINTS = {
    categories: API_BASE + 'categories.php',
    products: API_BASE + 'products.php',
    orders: API_BASE + 'orders.php',
    addresses: API_BASE + 'addresses.php',
    users: API_BASE + 'users.php',
    auth: API_BASE + 'auth.php',
    payment: API_BASE + 'payment.php',
    banners: API_BASE + 'index.php/banners',
    popups: API_BASE + 'index.php/popups',
    comboOffers: API_BASE + 'index.php/combo-offers',
    reviews: API_BASE + 'index.php/reviews'
};

// Environment info for debugging (can be removed in production)
const ENV = {
    isLocal: isLocalhost,
    isProduction: isProduction,
    hostname: hostname,
    apiBase: API_BASE
};

// Log environment for debugging
console.log(`[KPS Nursery] Environment: ${isLocalhost ? 'LOCAL' : 'PRODUCTION'}`);
console.log(`[KPS Nursery] Hostname: ${hostname}`);
console.log(`[KPS Nursery] API Base: ${API_BASE}`);

// ============================================
// Image Path Helper
// ============================================

/**
 * Normalize image paths for customer-facing pages
 * Handles paths from both local development and Hostinger production
 * @param {string} imagePath - The image path from database
 * @returns {string} - Normalized path for use in img src
 */
function getImagePath(imagePath) {
    if (!imagePath) return '../assets/placeholder.png';

    // Normalize path - convert backslashes to forward slashes
    let normalizedPath = imagePath.replace(/\\/g, '/');

    // If it's an absolute URL or data URL, return as is
    if (normalizedPath.startsWith('http') || normalizedPath.startsWith('data:')) {
        return normalizedPath;
    }

    // Handle Hostinger production absolute path format: /frontend/assets/uploads/filename.ext
    if (normalizedPath.startsWith('/frontend/assets/uploads/')) {
        const filename = normalizedPath.split('/frontend/assets/uploads/').pop();
        return '../assets/uploads/' + filename;
    }

    // Handle relative paths from admin uploads: ../assets/uploads/filename.ext
    // or ../../assets/uploads/filename.ext
    if (normalizedPath.includes('assets/uploads/')) {
        const filename = normalizedPath.split('assets/uploads/').pop();
        return '../assets/uploads/' + filename;
    }

    // Handle general assets paths
    if (normalizedPath.includes('assets/')) {
        const assetPath = normalizedPath.split('assets/').pop();
        return '../assets/' + assetPath;
    }

    // If it's just a filename with upload prefix
    if (normalizedPath.startsWith('product_') || normalizedPath.startsWith('banner_') || normalizedPath.startsWith('popup_')) {
        return '../assets/uploads/' + normalizedPath;
    }

    // Default - return as is
    return normalizedPath;
}

// Global State Management
const AppState = {
    cart: [],
    user: null,
    isLoggedIn: false,

    // Load cart from localStorage
    loadCart: function() {
        const cartData = localStorage.getItem('cart');
        this.cart = cartData ? JSON.parse(cartData) : [];
        this.updateCartBadge();
        return this.cart;
    },

    // Save cart to localStorage
    saveCart: function() {
        localStorage.setItem('cart', JSON.stringify(this.cart));
        this.updateCartBadge();
    },

    // Add item to cart
    addToCart: function(product, quantity = 1) {
        const productId = parseInt(product.id);
        const existingIndex = this.cart.findIndex(item => parseInt(item.id) == productId);

        if (existingIndex >= 0) {
            // Add to existing quantity
            this.cart[existingIndex].quantity += quantity;
        } else {
            this.cart.push({
                id: productId,
                name: product.name,
                price: parseFloat(product.price),
                quantity: quantity,
                image: product.image,
                unit: product.unit || 'kg',
                stock: product.stock || 0
            });
        }

        this.saveCart();
        return true;
    },

    // Remove item from cart
    removeFromCart: function(productId) {
        const idStr = String(productId);
        this.cart = this.cart.filter(item => String(item.id) !== idStr);
        this.saveCart();
    },

    // Update item quantity
    updateQuantity: function(productId, quantity) {
        const idStr = String(productId);
        const itemIndex = this.cart.findIndex(item => String(item.id) === idStr);
        if (itemIndex >= 0) {
            if (quantity <= 0) {
                this.removeFromCart(productId);
            } else {
                this.cart[itemIndex].quantity = quantity;
                this.saveCart();
            }
        }
    },

    // Get cart total
    getCartTotal: function() {
        return this.cart.reduce((total, item) => {
            return total + (item.price * item.quantity);
        }, 0);
    },

    // Get cart count
    getCartCount: function() {
        return this.cart.reduce((count, item) => count + item.quantity, 0);
    },

    // Update cart badge
    updateCartBadge: function() {
        const count = this.getCartCount();
        $('.cart-badge').text(count);

        if (count > 0) {
            $('.cart-badge').show();
        } else {
            $('.cart-badge').hide();
        }
    },

    // Clear cart
    clearCart: function() {
        this.cart = [];
        this.saveCart();
    },

    // Load user from localStorage
    loadUser: function() {
        const userData = localStorage.getItem('user');
        if (userData) {
            this.user = JSON.parse(userData);
            this.isLoggedIn = true;
        }
        return this.user;
    },

    // Save user to localStorage
    saveUser: function(user) {
        this.user = user;
        this.isLoggedIn = true;
        localStorage.setItem('user', JSON.stringify(user));
    },

    // Logout user
    logout: function() {
        this.user = null;
        this.isLoggedIn = false;
        localStorage.removeItem('user');
        localStorage.removeItem('cart');
        this.cart = [];
        this.updateCartBadge();
    },

    // ============================================
    // LOGIN/SIGNUP TEMPORARILY DISABLED
    // Uncomment the original code below when login is needed
    // ============================================

    // Check if user is logged in (not guest)
    // TEMPORARILY DISABLED - Always returns true
    isAuthenticated: function() {
        /* === ORIGINAL CODE - UNCOMMENT WHEN LOGIN IS ENABLED ===
        const user = this.loadUser();
        return user && user.id && user.mobile && !user.mobile.startsWith('GUEST');
        */
        return true; // Login disabled - always authenticated
    },

    // Require login - redirect to login page if not authenticated
    // TEMPORARILY DISABLED - Always returns true
    requireLogin: function(redirectUrl = null) {
        /* === ORIGINAL CODE - UNCOMMENT WHEN LOGIN IS ENABLED ===
        if (!this.isAuthenticated()) {
            if (redirectUrl) {
                sessionStorage.setItem('redirect_after_login', redirectUrl);
            } else {
                sessionStorage.setItem('redirect_after_login', window.location.href);
            }
            window.location.href = 'login.html';
            return false;
        }
        */
        return true; // Login disabled - always allow
    },

    // Redirect to login with message
    // TEMPORARILY DISABLED - Does nothing
    redirectToLogin: function(message = null) {
        /* === ORIGINAL CODE - UNCOMMENT WHEN LOGIN IS ENABLED ===
        if (message) {
            sessionStorage.setItem('login_message', message);
        }
        sessionStorage.setItem('redirect_after_login', window.location.href);
        window.location.href = 'login.html';
        */
        console.log('[KPS Nursery] Login redirect disabled');
    }
};

// Toast Notification System
const Toast = {
    show: function(message, type = 'info', duration = 3000) {
        const toastId = 'toast-' + Date.now();
        const iconMap = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        const toastHTML = `
            <div class="toast toast-${type}" id="${toastId}">
                <i class="fas ${iconMap[type]}"></i>
                <span>${message}</span>
            </div>
        `;

        $('#toast-container').append(toastHTML);

        // Fade in
        setTimeout(() => {
            $(`#${toastId}`).addClass('show');
        }, 10);

        // Fade out and remove
        setTimeout(() => {
            $(`#${toastId}`).removeClass('show');
            setTimeout(() => {
                $(`#${toastId}`).remove();
            }, 300);
        }, duration);
    },

    success: function(message) {
        this.show(message, 'success');
    },

    error: function(message) {
        this.show(message, 'error');
    },

    warning: function(message) {
        this.show(message, 'warning');
    },

    info: function(message) {
        this.show(message, 'info');
    }
};

// Loading Overlay
const Loading = {
    show: function() {
        $('#loading-overlay').fadeIn(200);
    },

    hide: function() {
        $('#loading-overlay').fadeOut(200);
    }
};

// Utility Functions
const Utils = {
    // Format price
    formatPrice: function(price) {
        return '₹' + parseFloat(price).toFixed(2);
    },

    // Format number
    formatNumber: function(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    },

    // Debounce function
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    // Validate mobile number
    validateMobile: function(mobile) {
        const regex = /^[0-9]{10}$/;
        return regex.test(mobile);
    },

    // Validate email
    validateEmail: function(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    },

    // Validate pincode
    validatePincode: function(pincode) {
        const regex = /^[0-9]{6}$/;
        return regex.test(pincode);
    }
};

// Initialize common functionality
$(document).ready(function() {
    // Load cart and user
    AppState.loadCart();
    AppState.loadUser();

    // Handle offline/online events
    window.addEventListener('offline', function() {
        $('#offline-indicator').fadeIn();
    });

    window.addEventListener('online', function() {
        $('#offline-indicator').fadeOut();
    });

    // Check initial connection status
    if (!navigator.onLine) {
        $('#offline-indicator').show();
    }
});
