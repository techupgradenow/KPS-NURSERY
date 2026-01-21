/**
 * Cart Page JavaScript
 * Modern Swiggy/Zomato-style Cart with Payment Integration
 * KPS Nursery App
 */

// Cart State
const CartState = {
    items: [],
    selectedPayment: 'upi',
    appliedCoupon: null,
    discount: 0,
    taxRate: 0.05, // 5% tax
    deliveryFee: 0,
    itemToRemove: null
};

// Payment Method Labels
const PaymentLabels = {
    'upi': 'UPI',
    'card': 'Debit/Credit Card',
    'netbanking': 'Net Banking',
    'cod': 'Cash on Delivery'
};

// Initialize Cart
$(document).ready(function() {
    initializeCart();
    setupEventListeners();
    loadCustomerDetails();
    setupCustomerDetailsListeners();
    initDeliveryDatePicker();
});

/**
 * Initialize Delivery Date Picker with min date as today
 */
function initDeliveryDatePicker() {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const minDate = `${yyyy}-${mm}-${dd}`;

    // Set minimum date to today
    $('#delivery-date').attr('min', minDate);

    // Set default date to today
    $('#delivery-date').val(minDate);

    // Add change listeners for validation feedback
    $('#delivery-date').on('change', function() {
        const val = $(this).val();
        if (val) {
            $(this).addClass('valid').removeClass('error');
            $('#delivery-date-error').removeClass('show');
        }
    });

    // Time picker dropdowns validation
    $('#delivery-hour, #delivery-minute, #delivery-ampm').on('change', function() {
        const hour = $('#delivery-hour').val();
        const minute = $('#delivery-minute').val();
        if (hour && minute) {
            $('#delivery-hour, #delivery-minute, #delivery-ampm').addClass('valid').removeClass('error');
            $('#delivery-time-error').removeClass('show');
        }
    });
}

/**
 * Initialize Cart from localStorage
 */
function initializeCart() {
    CartState.items = AppState.loadCart();
    
    // Enhance cart items with full product details from database
    if (CartState.items.length > 0) {
        enhanceCartItems();
    } else {
        renderCart();
        updateBillDetails();
    }
}

/**
 * Enhance cart items with full product details
 */
function enhanceCartItems() {
    const productIds = CartState.items.map(item => item.id).join(',');
    
    // Fetch product details for all items in cart
    $.ajax({
        url: API_ENDPOINTS.products + '?action=all',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                // Update cart items with full product details
                CartState.items = CartState.items.map(cartItem => {
                    const product = response.data.find(p => p.id == cartItem.id);
                    if (product) {
                        return {
                            ...cartItem,
                            unit: product.unit || 'kg',
                            stock: product.stock || 0,
                            image: product.image || cartItem.image
                        };
                    }
                    return cartItem;
                });
                
                // Save enhanced cart
                AppState.cart = CartState.items;
                AppState.saveCart();
            }
            
            renderCart();
            updateBillDetails();
        },
        error: function() {
            // If API fails, render with existing data
            renderCart();
            updateBillDetails();
        }
    });
}

/**
 * Render Cart Items
 */
function renderCart() {
    const container = $('#cart-items-container');
    const emptyMessage = $('#empty-cart-message');
    const billSection = $('#bill-section');
    const couponSection = $('#coupon-section');
    const paymentSection = $('#payment-section');
    const customerDetailsSection = $('#customer-details-section');
    const checkoutFooter = $('#checkout-footer');
    const checkoutBtn = $('#checkout-btn');

    if (CartState.items.length === 0) {
        container.empty();
        emptyMessage.show();
        billSection.hide();
        couponSection.hide();
        paymentSection.hide();
        customerDetailsSection.hide();
        checkoutFooter.hide();
        checkoutBtn.prop('disabled', true);
        return;
    }

    emptyMessage.hide();
    billSection.show();
    couponSection.show();
    paymentSection.show();
    customerDetailsSection.show();
    checkoutFooter.show();
    checkoutBtn.prop('disabled', false);

    const cartHTML = CartState.items.map(item => createCartItemHTML(item)).join('');
    container.html(cartHTML);
}

/**
 * Create Cart Item HTML
 */
function createCartItemHTML(item) {
    const subtotal = (item.price * item.quantity).toFixed(2);
    const isOutOfStock = item.stock !== undefined && item.stock <= 0;
    const unit = item.unit || 'kg';

    return `
        <div class="cart-item-card ${isOutOfStock ? 'out-of-stock' : ''}" data-id="${item.id}">
            <div class="cart-item-content">
                <div class="cart-item-image-wrapper">
                    <img src="${item.image}"
                         alt="${item.name}"
                         class="cart-item-image"
                         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%23f3f4f6%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%22 y=%2255%22 text-anchor=%22middle%22 fill=%22%239ca3af%22 font-size=%2212%22%3ENo Image%3C/text%3E%3C/svg%3E'">
                    ${isOutOfStock ? '<div class="stock-badge">Out of Stock</div>' : ''}
                </div>

                <div class="cart-item-details">
                    <div class="cart-item-header">
                        <h3 class="cart-item-name">${item.name}</h3>
                        <button class="cart-item-delete" onclick="confirmRemoveItem(${item.id})" title="Remove item">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>

                    <div class="cart-item-unit-price">₹${parseFloat(item.price).toFixed(2)}<span class="unit-text">/${unit}</span></div>

                    <div class="cart-item-price-row">
                        <div class="quantity-selector">
                            <button class="qty-btn" onclick="updateQuantity(${item.id}, ${item.quantity - 1})" ${item.quantity <= 1 ? 'disabled' : ''}>
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="qty-value">${item.quantity}</span>
                            <button class="qty-btn" onclick="updateQuantity(${item.id}, ${item.quantity + 1})">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="cart-item-subtotal">₹${subtotal}</div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

/**
 * Update Quantity
 */
function updateQuantity(productId, newQuantity) {
    if (newQuantity <= 0) {
        confirmRemoveItem(productId);
        return;
    }

    // Update in AppState
    AppState.updateQuantity(productId, newQuantity);

    // Reload cart state
    CartState.items = AppState.loadCart();

    // Animate the quantity change
    const card = $(`.cart-item-card[data-id="${productId}"]`);
    card.addClass('updating');

    setTimeout(() => {
        renderCart();
        updateBillDetails();
        card.removeClass('updating');
    }, 150);

    showToast('Cart updated', 'info');
}

/**
 * Confirm Remove Item
 */
function confirmRemoveItem(productId) {
    CartState.itemToRemove = productId;
    $('#remove-modal').addClass('active');
}

/**
 * Remove Item from Cart
 */
function removeItem(productId) {
    // Animate removal
    const card = $(`.cart-item-card[data-id="${productId}"]`);
    card.css({
        'transform': 'translateX(100%)',
        'opacity': '0'
    });

    setTimeout(() => {
        AppState.removeFromCart(productId);
        CartState.items = AppState.loadCart();
        renderCart();
        updateBillDetails();
        showToast('Item removed from cart', 'success');
    }, 300);
}

/**
 * Clear Entire Cart
 */
function clearCart() {
    AppState.clearCart();
    CartState.items = [];
    CartState.appliedCoupon = null;
    CartState.discount = 0;
    renderCart();
    updateBillDetails();
    showToast('Cart cleared', 'success');
}

/**
 * Update Bill Details
 */
function updateBillDetails() {
    const subtotal = AppState.getCartTotal();
    const deliveryFee = CartState.deliveryFee;
    const tax = subtotal * CartState.taxRate;
    const discount = CartState.discount;
    const grandTotal = subtotal + deliveryFee + tax - discount;

    // Update bill section
    $('#item-total').text(`₹${subtotal.toFixed(2)}`);

    if (deliveryFee > 0) {
        $('#delivery-fee').html(`₹${deliveryFee.toFixed(2)}`);
    } else {
        $('#delivery-fee').html('<span class="free-badge">FREE</span>');
    }

    $('#tax-amount').text(`₹${tax.toFixed(2)}`);

    if (discount > 0) {
        $('#discount-row').show();
        $('#discount-amount').text(`-₹${discount.toFixed(2)}`);
    } else {
        $('#discount-row').hide();
    }

    $('#grand-total').text(`₹${grandTotal.toFixed(2)}`);
    $('#checkout-total').text(`₹${grandTotal.toFixed(2)}`);

    // Show/hide delivery time info
    if (CartState.items.length > 0) {
        $('#delivery-time-info').show();
        const estimatedTime = CartState.selectedPayment === 'cod' ? '45-60 mins' : '30-45 mins';
        $('#estimated-time').text(estimatedTime);
    } else {
        $('#delivery-time-info').hide();
    }

    // Update button text with amount
    if (CartState.items.length > 0) {
        const formattedTotal = grandTotal.toFixed(2);
        $('#checkout-btn .btn-text').text(`Proceed to Pay ₹${formattedTotal}`);
        $('#checkout-btn').prop('disabled', false);
    } else {
        $('#checkout-btn .btn-text').text('Proceed to Pay');
        $('#checkout-btn').prop('disabled', true);
    }
}

/**
 * Setup Event Listeners
 */
function setupEventListeners() {
    // Clear cart button
    $('#clear-cart-btn').on('click', function() {
        if (CartState.items.length > 0) {
            $('#clear-modal').addClass('active');
        }
    });

    // Remove item modal
    $('#cancel-remove').on('click', function() {
        $('#remove-modal').removeClass('active');
        CartState.itemToRemove = null;
    });

    $('#confirm-remove').on('click', function() {
        if (CartState.itemToRemove) {
            removeItem(CartState.itemToRemove);
            CartState.itemToRemove = null;
        }
        $('#remove-modal').removeClass('active');
    });

    // Clear cart modal
    $('#cancel-clear').on('click', function() {
        $('#clear-modal').removeClass('active');
    });

    $('#confirm-clear').on('click', function() {
        clearCart();
        $('#clear-modal').removeClass('active');
    });

    // Payment method selection
    $('.payment-option').on('click', function() {
        $('.payment-option').removeClass('selected');
        $(this).addClass('selected');
        CartState.selectedPayment = $(this).data('method');
        updateBillDetails(); // Update delivery time based on payment method
    });

    // Coupon button
    $('#apply-coupon-btn, .coupon-card').on('click', function(e) {
        if (!$(e.target).hasClass('remove-coupon')) {
            $('#coupon-modal').addClass('active');
        }
    });

    // Close coupon modal
    $('#close-coupon').on('click', function() {
        $('#coupon-modal').removeClass('active');
    });

    // Apply coupon from input
    $('#apply-coupon').on('click', function() {
        const code = $('#coupon-code').val().trim().toUpperCase();
        if (code) {
            applyCoupon(code);
        }
    });

    // Apply coupon from available list
    $('.coupon-item .btn-apply-small').on('click', function() {
        const code = $(this).closest('.coupon-item').data('code');
        applyCoupon(code);
    });

    // Checkout button
    $('#checkout-btn').on('click', function() {
        if (CartState.items.length === 0) {
            showToast('Your cart is empty', 'error');
            return;
        }
        // Validate customer details from the form
        if (!validateCustomerDetailsForm()) {
            showToast('Please fill in all customer details', 'error');
            // Scroll to customer details section
            $('html, body').animate({
                scrollTop: $('#customer-details-section').offset().top - 80
            }, 300);
            return;
        }
        // Save customer details before proceeding
        saveCustomerDetails();
        // Proceed to order summary
        showOrderSummary();
    });

    // Close order summary modal
    $('#close-summary').on('click', function() {
        $('#order-summary-modal').removeClass('active');
    });

    // Place order button
    $('#place-order-btn').on('click', function() {
        placeOrder();
    });

    // Close modals on overlay click
    $('.modal-overlay').on('click', function(e) {
        if (e.target === this) {
            $(this).removeClass('active');
        }
    });

    // Enter key for coupon input
    $('#coupon-code').on('keypress', function(e) {
        if (e.which === 13) {
            $('#apply-coupon').click();
        }
    });
}

/**
 * Apply Coupon Code
 */
function applyCoupon(code) {
    const subtotal = AppState.getCartTotal();
    let discount = 0;
    let valid = false;

    // Validate coupon codes
    switch(code) {
        case 'FIRST50':
            discount = Math.min(subtotal * 0.5, 100); // 50% off max ₹100
            valid = true;
            break;
        case 'FRESH20':
            if (subtotal >= 200) {
                discount = 20; // Flat ₹20 off
                valid = true;
            } else {
                showToast('Order should be above ₹200', 'error');
                return;
            }
            break;
        default:
            showToast('Invalid coupon code', 'error');
            return;
    }

    if (valid) {
        CartState.appliedCoupon = code;
        CartState.discount = discount;

        // Update coupon card UI
        $('.coupon-card').addClass('applied');
        $('.coupon-card .coupon-label').html(`<span class="coupon-applied-code">${code}</span> applied`);
        $('.coupon-card .coupon-hint').html(`You save ₹${discount.toFixed(2)} <span class="remove-coupon" onclick="removeCoupon()">(Remove)</span>`);

        updateBillDetails();
        $('#coupon-modal').removeClass('active');
        showToast(`Coupon applied! You save ₹${discount.toFixed(2)}`, 'success');
    }
}

/**
 * Remove Applied Coupon
 */
function removeCoupon() {
    CartState.appliedCoupon = null;
    CartState.discount = 0;

    // Reset coupon card UI
    $('.coupon-card').removeClass('applied');
    $('.coupon-card .coupon-label').text('Apply Coupon');
    $('.coupon-card .coupon-hint').text('Save more on your order');

    updateBillDetails();
    showToast('Coupon removed', 'info');
}

/**
 * Show Order Summary Modal
 */
function showOrderSummary() {
    const subtotal = AppState.getCartTotal();
    const tax = subtotal * CartState.taxRate;
    const grandTotal = subtotal + CartState.deliveryFee + tax - CartState.discount;

    // Populate summary items
    const itemsHTML = CartState.items.map(item => `
        <div class="summary-item">
            <div>
                <span class="summary-item-name">${item.name}</span>
                <span class="summary-item-qty"> x ${item.quantity}</span>
            </div>
            <span class="summary-item-price">₹${(item.price * item.quantity).toFixed(2)}</span>
        </div>
    `).join('');

    $('#summary-items').html(itemsHTML);
    $('#summary-item-total').text(`₹${subtotal.toFixed(2)}`);
    $('#summary-delivery').text(CartState.deliveryFee > 0 ? `₹${CartState.deliveryFee.toFixed(2)}` : 'FREE');
    $('#summary-tax').text(`₹${tax.toFixed(2)}`);
    $('#summary-grand-total').text(`₹${grandTotal.toFixed(2)}`);
    $('#summary-payment-method').text(PaymentLabels[CartState.selectedPayment]);

    $('#order-summary-modal').addClass('active');
}

/**
 * Place Order
 */
function placeOrder() {
    showLoading();

    const subtotal = AppState.getCartTotal();
    const tax = subtotal * CartState.taxRate;
    const grandTotal = subtotal + CartState.deliveryFee + tax - CartState.discount;

    // Get customer details from the form
    const customerName = $('#customer-name').val().trim();
    const customerMobile = $('#customer-mobile').val().trim();
    const customerAddress = $('#customer-address').val().trim();
    const deliveryDate = $('#delivery-date').val();

    // Get time from dropdowns and format as "HH:MM AM/PM"
    const hour = $('#delivery-hour').val();
    const minute = $('#delivery-minute').val();
    const ampm = $('#delivery-ampm').val();
    const deliveryTime = (hour && minute) ? `${hour}:${minute} ${ampm}` : '';

    const orderData = {
        // Customer details
        customer_name: customerName,
        customer_mobile: customerMobile,
        customer_address: customerAddress,
        // Delivery date and time
        delivery_date: deliveryDate,
        delivery_time: deliveryTime,
        // Order items
        items: CartState.items.map(item => ({
            product_id: item.id,
            product_name: item.name,
            quantity: item.quantity,
            price: item.price,
            subtotal: item.price * item.quantity
        })),
        subtotal: subtotal,
        delivery_charge: CartState.deliveryFee,
        tax: tax,
        discount: CartState.discount,
        coupon_code: CartState.appliedCoupon,
        total: grandTotal,
        payment_method: CartState.selectedPayment
    };

    // Send order to backend using API_ENDPOINTS
    console.log('Placing order with data:', orderData);
    console.log('API URL:', API_ENDPOINTS.orders);

    $.ajax({
        url: API_ENDPOINTS.orders,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'create',
            ...orderData
        }),
        success: function(response, textStatus, xhr) {
            hideLoading();
            console.log('Order response:', response);
            console.log('Status:', xhr.status);

            // Double check - success handler can be called for non-200 in some jQuery versions
            if (xhr.status >= 400) {
                console.error('Server returned error status:', xhr.status);
                showToast('Server error. Please try again.', 'error');
                return;
            }

            if (response && response.success === true) {
                // Clear cart
                AppState.clearCart();
                CartState.items = [];
                CartState.appliedCoupon = null;
                CartState.discount = 0;

                // Close modal and show success
                $('#order-summary-modal').removeClass('active');

                const orderId = response.data?.order_id || 'Order';
                showToast(`${orderId} placed successfully!`, 'success');

                // Redirect to order confirmation or home after delay
                setTimeout(() => {
                    window.location.href = 'home.html';
                }, 2000);
            } else {
                console.error('Order failed:', response);
                showToast(response?.message || 'Failed to place order', 'error');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('Order API error:', status, error);
            console.error('Status code:', xhr.status);
            console.error('Response:', xhr.responseText);

            // Show actual error - don't fake success
            let errorMsg = 'Failed to place order. Please try again.';
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.message) {
                    errorMsg = response.message;
                }
            } catch(e) {
                console.error('Could not parse error response');
            }

            showToast(errorMsg, 'error');
        }
    });
}

/**
 * Show Loading Overlay
 */
function showLoading() {
    $('#loading-overlay').addClass('active');
}

/**
 * Hide Loading Overlay
 */
function hideLoading() {
    $('#loading-overlay').removeClass('active');
}

/**
 * Show Toast Notification
 */
function showToast(message, type = 'info') {
    const toast = $(`
        <div class="toast ${type}">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
    `);

    $('#toast-container').append(toast);

    setTimeout(() => {
        toast.css({
            'opacity': '0',
            'transform': 'translateY(20px)'
        });
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ============================================
// USER DETAILS VALIDATION BEFORE CHECKOUT
// ============================================

/**
 * Check if user has completed their profile details before checkout
 */
function checkUserDetailsBeforeCheckout() {
    const user = AppState.loadUser();

    // Check if user has required details
    const hasName = user && user.name && user.name.trim() !== '' && !user.name.startsWith('Guest');
    const hasMobile = user && user.mobile && user.mobile.trim() !== '' && !user.mobile.startsWith('GUEST');
    const hasAddress = user && user.address && user.address.trim() !== '';

    if (!hasName || !hasMobile || !hasAddress) {
        // Show user details modal
        showUserDetailsModal(user);
    } else {
        // User details are complete, proceed to order summary
        showOrderSummary();
    }
}

/**
 * Show User Details Modal
 */
function showUserDetailsModal(existingUser) {
    // Remove existing modal if any
    $('#user-details-modal').remove();

    const user = existingUser || {};
    const displayName = (user.name && !user.name.startsWith('Guest')) ? user.name : '';
    const displayMobile = (user.mobile && !user.mobile.startsWith('GUEST')) ? user.mobile : '';
    const displayAddress = user.address || '';

    const modalHTML = `
        <div class="modal-overlay active" id="user-details-modal">
            <div class="modal-container user-details-modal">
                <style>
                    .user-details-modal {
                        max-width: 420px;
                        width: 90%;
                        background: #fff;
                        border-radius: 20px;
                        overflow: hidden;
                        animation: slideUp 0.3s ease;
                    }
                    @keyframes slideUp {
                        from { opacity: 0; transform: translateY(30px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                    .user-details-header {
                        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
                        padding: 24px;
                        text-align: center;
                        color: white;
                    }
                    .user-details-header i {
                        font-size: 48px;
                        margin-bottom: 12px;
                        opacity: 0.9;
                    }
                    .user-details-header h3 {
                        margin: 0;
                        font-size: 20px;
                        font-weight: 600;
                    }
                    .user-details-header p {
                        margin: 8px 0 0;
                        font-size: 14px;
                        opacity: 0.9;
                    }
                    .user-details-body {
                        padding: 24px;
                    }
                    .detail-input-group {
                        margin-bottom: 18px;
                    }
                    .detail-input-group label {
                        display: block;
                        font-size: 13px;
                        font-weight: 600;
                        color: #374151;
                        margin-bottom: 8px;
                    }
                    .detail-input-group label .required {
                        color: #ef4444;
                    }
                    .detail-input-group input,
                    .detail-input-group textarea {
                        width: 100%;
                        padding: 14px 16px;
                        border: 2px solid #e5e7eb;
                        border-radius: 12px;
                        font-size: 15px;
                        font-family: inherit;
                        transition: all 0.2s ease;
                        outline: none;
                        box-sizing: border-box;
                    }
                    .detail-input-group input:focus,
                    .detail-input-group textarea:focus {
                        border-color: #22c55e;
                        box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
                    }
                    .detail-input-group input.error,
                    .detail-input-group textarea.error {
                        border-color: #ef4444;
                    }
                    .detail-input-group textarea {
                        resize: vertical;
                        min-height: 80px;
                    }
                    .detail-input-group .input-icon {
                        position: relative;
                    }
                    .detail-input-group .input-icon input {
                        padding-left: 46px;
                    }
                    .detail-input-group .input-icon i {
                        position: absolute;
                        left: 16px;
                        top: 50%;
                        transform: translateY(-50%);
                        color: #9ca3af;
                        font-size: 16px;
                    }
                    .detail-error {
                        color: #ef4444;
                        font-size: 12px;
                        margin-top: 6px;
                        display: none;
                    }
                    .user-details-footer {
                        padding: 0 24px 24px;
                        display: flex;
                        gap: 12px;
                    }
                    .btn-details-cancel {
                        flex: 1;
                        padding: 14px;
                        border: 2px solid #e5e7eb;
                        border-radius: 12px;
                        background: #fff;
                        color: #6b7280;
                        font-size: 15px;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.2s ease;
                    }
                    .btn-details-cancel:hover {
                        background: #f3f4f6;
                    }
                    .btn-details-save {
                        flex: 2;
                        padding: 14px;
                        border: none;
                        border-radius: 12px;
                        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
                        color: white;
                        font-size: 15px;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.2s ease;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 8px;
                    }
                    .btn-details-save:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 4px 15px rgba(34, 197, 94, 0.4);
                    }
                    .privacy-note {
                        font-size: 11px;
                        color: #9ca3af;
                        text-align: center;
                        margin-top: 16px;
                        padding: 0 24px;
                    }
                    .privacy-note i {
                        margin-right: 4px;
                    }
                </style>

                <div class="user-details-header">
                    <i class="fas fa-user-edit"></i>
                    <h3>Complete Your Details</h3>
                    <p>We need your information for delivery</p>
                </div>

                <div class="user-details-body">
                    <div class="detail-input-group">
                        <label>Full Name <span class="required">*</span></label>
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" id="detail-name" placeholder="Enter your full name" value="${displayName}" maxlength="50">
                        </div>
                        <div class="detail-error" id="name-error">Please enter your name</div>
                    </div>

                    <div class="detail-input-group">
                        <label>Mobile Number <span class="required">*</span></label>
                        <div class="input-icon">
                            <i class="fas fa-phone"></i>
                            <input type="tel" id="detail-mobile" placeholder="Enter 10-digit mobile number" value="${displayMobile}" maxlength="10" pattern="[0-9]{10}">
                        </div>
                        <div class="detail-error" id="mobile-error">Please enter a valid 10-digit mobile number</div>
                    </div>

                    <div class="detail-input-group">
                        <label>Delivery Address <span class="required">*</span></label>
                        <textarea id="detail-address" placeholder="Enter your complete delivery address (House No, Street, Area, City, Pincode)" maxlength="200">${displayAddress}</textarea>
                        <div class="detail-error" id="address-error">Please enter your delivery address</div>
                    </div>
                </div>

                <div class="user-details-footer">
                    <button class="btn-details-cancel" onclick="closeUserDetailsModal()">Cancel</button>
                    <button class="btn-details-save" onclick="saveUserDetailsAndCheckout()">
                        <i class="fas fa-check"></i>
                        Save & Continue
                    </button>
                </div>

                <p class="privacy-note">
                    <i class="fas fa-lock"></i>
                    Your information is secure and will only be used for delivery
                </p>
            </div>
        </div>
    `;

    $('body').append(modalHTML);

    // Focus on first empty field
    if (!displayName) {
        $('#detail-name').focus();
    } else if (!displayMobile) {
        $('#detail-mobile').focus();
    } else if (!displayAddress) {
        $('#detail-address').focus();
    }

    // Add input validation on blur
    $('#detail-name').on('blur', function() {
        validateField('name', $(this).val());
    });

    $('#detail-mobile').on('blur', function() {
        validateField('mobile', $(this).val());
    });

    $('#detail-address').on('blur', function() {
        validateField('address', $(this).val());
    });

    // Only allow numbers in mobile field
    $('#detail-mobile').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
}

/**
 * Validate a single field
 */
function validateField(field, value) {
    let isValid = true;

    switch(field) {
        case 'name':
            isValid = value.trim().length >= 2;
            if (!isValid) {
                $('#detail-name').addClass('error');
                $('#name-error').show();
            } else {
                $('#detail-name').removeClass('error');
                $('#name-error').hide();
            }
            break;

        case 'mobile':
            isValid = /^[0-9]{10}$/.test(value);
            if (!isValid) {
                $('#detail-mobile').addClass('error');
                $('#mobile-error').show();
            } else {
                $('#detail-mobile').removeClass('error');
                $('#mobile-error').hide();
            }
            break;

        case 'address':
            isValid = value.trim().length >= 10;
            if (!isValid) {
                $('#detail-address').addClass('error');
                $('#address-error').show();
            } else {
                $('#detail-address').removeClass('error');
                $('#address-error').hide();
            }
            break;
    }

    return isValid;
}

/**
 * Close User Details Modal
 */
function closeUserDetailsModal() {
    $('#user-details-modal').removeClass('active');
    setTimeout(() => {
        $('#user-details-modal').remove();
    }, 300);
}

/**
 * Save User Details and Proceed to Checkout
 */
function saveUserDetailsAndCheckout() {
    const name = $('#detail-name').val().trim();
    const mobile = $('#detail-mobile').val().trim();
    const address = $('#detail-address').val().trim();

    // Validate all fields
    const isNameValid = validateField('name', name);
    const isMobileValid = validateField('mobile', mobile);
    const isAddressValid = validateField('address', address);

    if (!isNameValid || !isMobileValid || !isAddressValid) {
        showToast('Please fill in all required fields correctly', 'error');
        return;
    }

    // Get existing user or create new one
    let user = AppState.loadUser() || {};

    // Update user details
    user.name = name;
    user.mobile = mobile;
    user.address = address;

    // Generate an ID if user doesn't have one
    if (!user.id) {
        user.id = 'USER_' + Date.now();
    }

    // Save user to localStorage
    AppState.saveUser(user);

    // Close modal and show order summary
    closeUserDetailsModal();
    showToast('Details saved successfully!', 'success');

    // Small delay before showing order summary
    setTimeout(() => {
        showOrderSummary();
    }, 300);
}

// ============================================
// CUSTOMER DETAILS FORM FUNCTIONS
// ============================================

/**
 * Load saved customer details into form
 */
function loadCustomerDetails() {
    const user = AppState.loadUser();
    if (user) {
        if (user.name && !user.name.startsWith('Guest')) {
            $('#customer-name').val(user.name);
        }
        if (user.mobile && !user.mobile.startsWith('GUEST')) {
            $('#customer-mobile').val(user.mobile);
        }
        if (user.address) {
            $('#customer-address').val(user.address);
        }
    }
}

/**
 * Setup event listeners for customer details inputs
 */
function setupCustomerDetailsListeners() {
    // Only allow numbers in mobile field
    $('#customer-mobile').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // Validate on blur
    $('#customer-name').on('blur', function() {
        validateCustomerField('name', $(this).val());
    });

    $('#customer-mobile').on('blur', function() {
        validateCustomerField('mobile', $(this).val());
    });

    $('#customer-address').on('blur', function() {
        validateCustomerField('address', $(this).val());
    });

    // Remove error state on focus
    $('#customer-name, #customer-mobile, #customer-address').on('focus', function() {
        $(this).removeClass('error');
        $(this).siblings('.input-error').removeClass('show');
    });
}

/**
 * Validate a single customer field
 */
function validateCustomerField(field, value) {
    let isValid = true;
    const trimmedValue = value.trim();

    switch(field) {
        case 'name':
            isValid = trimmedValue.length >= 2;
            if (!isValid) {
                $('#customer-name').addClass('error');
                $('#customer-name-error').addClass('show');
            } else {
                $('#customer-name').removeClass('error').addClass('valid');
                $('#customer-name-error').removeClass('show');
            }
            break;

        case 'mobile':
            isValid = /^[0-9]{10}$/.test(trimmedValue);
            if (!isValid) {
                $('#customer-mobile').addClass('error');
                $('#customer-mobile-error').addClass('show');
            } else {
                $('#customer-mobile').removeClass('error').addClass('valid');
                $('#customer-mobile-error').removeClass('show');
            }
            break;

        case 'address':
            isValid = trimmedValue.length >= 10;
            if (!isValid) {
                $('#customer-address').addClass('error');
                $('#customer-address-error').addClass('show');
            } else {
                $('#customer-address').removeClass('error').addClass('valid');
                $('#customer-address-error').removeClass('show');
            }
            break;
    }

    return isValid;
}

/**
 * Validate the entire customer details form
 */
function validateCustomerDetailsForm() {
    const name = $('#customer-name').val().trim();
    const mobile = $('#customer-mobile').val().trim();
    const address = $('#customer-address').val().trim();
    const deliveryDate = $('#delivery-date').val();

    // Get time from dropdowns
    const deliveryHour = $('#delivery-hour').val();
    const deliveryMinute = $('#delivery-minute').val();

    const isNameValid = validateCustomerField('name', name);
    const isMobileValid = validateCustomerField('mobile', mobile);
    const isAddressValid = validateCustomerField('address', address);

    // Validate delivery date
    let isDateValid = true;
    if (!deliveryDate) {
        $('#delivery-date').addClass('error').removeClass('valid');
        $('#delivery-date-error').addClass('show');
        isDateValid = false;
    } else {
        // Check if date is today or in the future
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const selectedDate = new Date(deliveryDate);
        if (selectedDate < today) {
            $('#delivery-date').addClass('error').removeClass('valid');
            $('#delivery-date-error').text('Please select today or a future date').addClass('show');
            isDateValid = false;
        } else {
            $('#delivery-date').addClass('valid').removeClass('error');
            $('#delivery-date-error').removeClass('show');
        }
    }

    // Validate delivery time (hour and minute dropdowns)
    let isTimeValid = true;
    if (!deliveryHour || !deliveryMinute) {
        $('#delivery-hour, #delivery-minute, #delivery-ampm').addClass('error').removeClass('valid');
        $('#delivery-time-error').addClass('show');
        isTimeValid = false;
    } else {
        $('#delivery-hour, #delivery-minute, #delivery-ampm').addClass('valid').removeClass('error');
        $('#delivery-time-error').removeClass('show');
    }

    return isNameValid && isMobileValid && isAddressValid && isDateValid && isTimeValid;
}

/**
 * Save customer details to localStorage
 */
function saveCustomerDetails() {
    const name = $('#customer-name').val().trim();
    const mobile = $('#customer-mobile').val().trim();
    const address = $('#customer-address').val().trim();

    // Get existing user or create new one
    let user = AppState.loadUser() || {};

    // Update user details
    user.name = name;
    user.mobile = mobile;
    user.address = address;

    // Generate an ID if user doesn't have one
    if (!user.id) {
        user.id = 'USER_' + Date.now();
    }

    // Save user to localStorage
    AppState.saveUser(user);

    // Update location display to show the new address
    if (typeof window.refreshLocationDisplay === 'function') {
        window.refreshLocationDisplay();
    }
}

// Make functions globally available
window.updateQuantity = updateQuantity;
window.confirmRemoveItem = confirmRemoveItem;
window.removeCoupon = removeCoupon;
window.closeUserDetailsModal = closeUserDetailsModal;
window.saveUserDetailsAndCheckout = saveUserDetailsAndCheckout;
window.validateCustomerDetailsForm = validateCustomerDetailsForm;
window.saveCustomerDetails = saveCustomerDetails;
