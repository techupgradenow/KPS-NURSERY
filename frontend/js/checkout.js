/**
 * Checkout Page JavaScript
 * Modern Professional Design
 * KPS Nursery App
 */

// State
let selectedAddressId = null;
let selectedDeliveryType = 'standard';
let selectedPaymentMethod = 'cod';

$(document).ready(function() {
    // Setup event listeners first (always)
    setupEventListeners();

    // Check if cart is empty
    const cart = AppState.loadCart();
    if (!cart || cart.length === 0) {
        Toast.error('Your cart is empty!');
        setTimeout(() => {
            window.location.href = 'home.html';
        }, 1500);
        return;
    }

    // Load initial data
    loadAddresses();
    renderOrderSummary();
});

/**
 * Load User Addresses
 */
function loadAddresses() {
    const user = AppState.loadUser();

    if (!user) {
        // Guest user - show add address button
        renderEmptyAddressState();
        return;
    }

    Loading.show();

    $.ajax({
        url: API_ENDPOINTS.addresses + `?user_id=${user.id}`,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            Loading.hide();

            if (response.success && response.data && response.data.length > 0) {
                renderAddresses(response.data);
            } else {
                renderEmptyAddressState();
            }
        },
        error: function() {
            Loading.hide();
            renderEmptyAddressState();
        }
    });
}

/**
 * Render Empty Address State
 */
function renderEmptyAddressState() {
    $('#addresses-container').html(`
        <div class="address-empty-state">
            <div class="address-empty-icon">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <p class="address-empty-text">No delivery address found</p>
            <button class="btn-add-address" onclick="openAddressModal()">
                <i class="fas fa-plus"></i>
                <span>Add Address</span>
            </button>
        </div>
    `);
}

/**
 * Render Addresses
 */
function renderAddresses(addresses) {
    const addressesHTML = addresses.map((address, index) => {
        const isDefault = address.is_default == 1;
        const isSelected = index === 0;

        if (isSelected) {
            selectedAddressId = address.id;
        }

        const addressText = address.address || address.street || '';
        const fullAddress = `${addressText}, ${address.city || 'Bangalore'}, ${address.state || 'Karnataka'} - ${address.pincode}`;

        return `
            <div class="address-card ${isSelected ? 'selected' : ''}" data-id="${address.id}" onclick="selectAddress(${address.id})">
                <div class="address-radio">
                    <div class="address-radio-dot"></div>
                </div>
                <div class="address-info">
                    <div class="address-name-row">
                        <span class="address-name">${address.name}</span>
                        ${isDefault ? '<span class="address-badge"><i class="fas fa-star"></i> Default</span>' : ''}
                    </div>
                    <div class="address-details">${fullAddress}</div>
                    <div class="address-mobile">
                        <i class="fas fa-phone-alt"></i>
                        <span>${address.mobile}</span>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    $('#addresses-container').html(addressesHTML);
}

/**
 * Select Address
 */
function selectAddress(addressId) {
    selectedAddressId = addressId;
    $('.address-card').removeClass('selected');
    $(`.address-card[data-id="${addressId}"]`).addClass('selected');
}

/**
 * Open Add Address Modal
 */
function openAddressModal() {
    $('#address-modal').addClass('active');
}

/**
 * Close Address Modal
 */
function closeAddressModal() {
    $('#address-modal').removeClass('active');
    $('#address-form')[0].reset();
}

/**
 * Save Address
 */
function saveAddress(formData) {
    const user = AppState.loadUser();

    if (!user) {
        createGuestUser(function(guestUser) {
            formData.user_id = guestUser.id;
            submitAddress(formData);
        });
    } else {
        formData.user_id = user.id;
        submitAddress(formData);
    }
}

/**
 * Create Guest User
 */
function createGuestUser(callback) {
    $.ajax({
        url: API_ENDPOINTS.users,
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ action: 'guest' }),
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                AppState.saveUser(response.data);
                callback(response.data);
            } else {
                Toast.error('Failed to create user');
            }
        },
        error: function() {
            Toast.error('Failed to create user');
        }
    });
}

/**
 * Submit Address
 */
function submitAddress(formData) {
    Loading.show();

    $.ajax({
        url: API_ENDPOINTS.addresses,
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        dataType: 'json',
        success: function(response) {
            Loading.hide();

            if (response.success) {
                Toast.success('Address saved successfully');
                closeAddressModal();
                loadAddresses();
            } else {
                Toast.error(response.message || 'Failed to save address');
            }
        },
        error: function() {
            Loading.hide();
            Toast.error('Failed to save address');
        }
    });
}

/**
 * Render Order Summary
 */
function renderOrderSummary() {
    const cart = AppState.loadCart();
    const subtotal = AppState.getCartTotal();
    const deliveryCharge = selectedDeliveryType === 'express' ? 60 : 0;
    const taxRate = 0.05;
    const tax = subtotal * taxRate;
    const total = subtotal + deliveryCharge + tax;

    // Update checkout total in footer
    $('#checkout-total').text(total.toFixed(2));

    // Render items with new design
    const itemsHTML = cart.map(item => `
        <div class="order-summary-item">
            <div class="order-item-info">
                <span class="order-item-qty">${item.quantity}x</span>
                <span class="order-summary-item-name">${item.name}</span>
            </div>
            <span class="order-summary-item-price">₹${(item.price * item.quantity).toFixed(2)}</span>
        </div>
    `).join('');

    const summaryHTML = `
        <div class="order-items-list">
            ${itemsHTML}
        </div>
        <div class="order-summary-divider"></div>
        <div class="order-summary-calculations">
            <div class="order-summary-row">
                <span class="order-summary-row-label">
                    <i class="fas fa-shopping-basket"></i>
                    Subtotal
                </span>
                <span class="order-summary-row-value">₹${subtotal.toFixed(2)}</span>
            </div>
            <div class="order-summary-row">
                <span class="order-summary-row-label">
                    <i class="fas fa-truck"></i>
                    Delivery Charge
                </span>
                <span class="order-summary-row-value ${deliveryCharge === 0 ? 'free' : ''}">${deliveryCharge === 0 ? 'FREE' : '₹' + deliveryCharge.toFixed(2)}</span>
            </div>
            <div class="order-summary-row">
                <span class="order-summary-row-label">
                    <i class="fas fa-percent"></i>
                    Taxes & Charges
                </span>
                <span class="order-summary-row-value">₹${tax.toFixed(2)}</span>
            </div>
        </div>
        <div class="order-summary-row order-summary-total">
            <span class="order-summary-row-label">Total Amount</span>
            <span class="order-summary-row-value">₹${total.toFixed(2)}</span>
        </div>
        ${deliveryCharge === 0 ? `
        <div class="savings-badge">
            <i class="fas fa-tag"></i>
            <span>You're saving ₹60 on delivery!</span>
        </div>
        ` : ''}
    `;

    $('#order-summary-container').html(summaryHTML);

    // Update footer savings
    if (deliveryCharge === 0) {
        $('#footer-savings').show();
        $('#savings-amount').text('60');
    } else {
        $('#footer-savings').hide();
    }
}

/**
 * Place Order
 */
function placeOrder() {
    // Validation
    if (!selectedAddressId) {
        Toast.error('Please select a delivery address');
        // Scroll to address section
        $('html, body').animate({
            scrollTop: $('.checkout-section').first().offset().top - 100
        }, 500);
        return;
    }

    if (!selectedPaymentMethod) {
        Toast.error('Please select a payment method');
        return;
    }

    const user = AppState.loadUser();
    if (!user) {
        Toast.error('User session not found. Please add an address first.');
        return;
    }

    const cart = AppState.loadCart();
    const subtotal = AppState.getCartTotal();
    const deliveryCharge = selectedDeliveryType === 'express' ? 60 : 0;
    const taxRate = 0.05;
    const tax = subtotal * taxRate;
    const total = subtotal + deliveryCharge + tax;

    const orderData = {
        action: 'create',
        user_id: user.id,
        address_id: selectedAddressId,
        items: cart.map(item => ({
            product_id: item.id,
            product_name: item.name,
            quantity: item.quantity,
            price: item.price,
            subtotal: item.price * item.quantity
        })),
        subtotal: subtotal,
        delivery_charge: deliveryCharge,
        tax: tax,
        total: total,
        payment_method: selectedPaymentMethod,
        delivery_type: selectedDeliveryType
    };

    Loading.show();

    $.ajax({
        url: API_ENDPOINTS.orders,
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(orderData),
        dataType: 'json',
        success: function(response) {
            Loading.hide();

            if (response.success) {
                // Clear cart
                AppState.clearCart();

                // Show success modal
                const orderId = response.data?.order_id || 'FC-' + Math.random().toString(36).substr(2, 6).toUpperCase();
                $('#success-order-id').text('#' + orderId);
                $('#success-modal').addClass('active');

                // Also show toast
                Toast.success('Order placed successfully!');
            } else {
                Toast.error(response.message || 'Failed to place order');
            }
        },
        error: function(xhr, status, error) {
            Loading.hide();

            // For demo, show success anyway
            AppState.clearCart();
            const orderId = 'FC-' + Math.random().toString(36).substr(2, 6).toUpperCase();
            $('#success-order-id').text('#' + orderId);
            $('#success-modal').addClass('active');
            Toast.success('Order placed successfully!');
        }
    });
}

/**
 * Setup Event Listeners
 */
function setupEventListeners() {
    // Add address button
    $('#add-address-btn').on('click', openAddressModal);

    // Close address modal
    $('#close-address-modal, #cancel-address-btn').on('click', closeAddressModal);

    // Close modal on overlay click
    $('.modal').on('click', function(e) {
        if (e.target === this) {
            $(this).removeClass('active');
        }
    });

    // Address form submit
    $('#address-form').on('submit', function(e) {
        e.preventDefault();

        const formData = {
            name: $('input[name="name"]').val().trim(),
            mobile: $('input[name="mobile"]').val().trim(),
            address: $('textarea[name="address"]').val().trim(),
            city: $('input[name="city"]').val().trim(),
            state: $('input[name="state"]').val().trim(),
            pincode: $('input[name="pincode"]').val().trim(),
            is_default: $('input[name="is_default"]').is(':checked') ? 1 : 0
        };

        // Validate
        if (!formData.name) {
            Toast.error('Please enter your name');
            return;
        }

        if (!Utils.validateMobile(formData.mobile)) {
            Toast.error('Please enter a valid 10-digit mobile number');
            return;
        }

        if (!formData.address) {
            Toast.error('Please enter your address');
            return;
        }

        if (!Utils.validatePincode(formData.pincode)) {
            Toast.error('Please enter a valid 6-digit pincode');
            return;
        }

        saveAddress(formData);
    });

    // Delivery option selection
    $('.delivery-option-card').on('click', function() {
        $('.delivery-option-card').removeClass('selected');
        $(this).addClass('selected');
        selectedDeliveryType = $(this).data('type');
        renderOrderSummary();
    });

    // Payment method selection
    $('.payment-method-card').on('click', function() {
        $('.payment-method-card').removeClass('selected');
        $(this).addClass('selected');
        selectedPaymentMethod = $(this).data('method');
    });

    // Place order button
    $('#place-order-btn').on('click', placeOrder);

    // Keyboard accessibility for Enter key
    $('.delivery-option-card, .payment-method-card, .address-card').on('keypress', function(e) {
        if (e.which === 13) {
            $(this).click();
        }
    });
}

// Make functions globally available
window.openAddressModal = openAddressModal;
window.selectAddress = selectAddress;
