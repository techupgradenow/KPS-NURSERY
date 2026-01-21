/**
 * Razorpay Payment Integration
 * KPS Nursery App
 */

// Razorpay Configuration
const RAZORPAY_CONFIG = {
    key: 'rzp_test_XXXXXXXXXXXX', // Replace with your Razorpay Key ID
    currency: 'INR',
    name: 'KPS Nursery',
    description: 'Plants & Gardening Supplies Delivery',
    image: '../assets/logo.png',
    theme: {
        color: '#2E7D32'
    }
};

/**
 * Initialize Razorpay Payment
 * @param {Object} orderData - Order details
 * @param {Function} onSuccess - Success callback
 * @param {Function} onFailure - Failure callback
 */
function initRazorpayPayment(orderData, onSuccess, onFailure) {
    // Validate order data
    if (!orderData || !orderData.amount) {
        Toast.error('Invalid order data');
        return;
    }

    // Show loading
    Loading.show();

    // Create order on backend first
    createRazorpayOrder(orderData, function(response) {
        Loading.hide();

        if (response.success && response.razorpay_order_id) {
            // Open Razorpay checkout
            openRazorpayCheckout(response.razorpay_order_id, orderData, onSuccess, onFailure);
        } else {
            Toast.error(response.message || 'Failed to create payment order');
            if (onFailure) onFailure(response);
        }
    });
}

/**
 * Create Razorpay Order on Backend
 */
function createRazorpayOrder(orderData, callback) {
    $.ajax({
        url: API_ENDPOINTS.payment + '?action=create_order',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            amount: orderData.amount,
            currency: RAZORPAY_CONFIG.currency,
            order_id: orderData.order_id,
            user_id: orderData.user_id,
            notes: orderData.notes || {}
        }),
        dataType: 'json',
        success: function(response) {
            callback(response);
        },
        error: function(xhr) {
            console.error('Create order error:', xhr);
            callback({
                success: false,
                message: 'Failed to create payment order'
            });
        }
    });
}

/**
 * Open Razorpay Checkout Modal
 */
function openRazorpayCheckout(razorpayOrderId, orderData, onSuccess, onFailure) {
    const user = AppState.loadUser();

    const options = {
        key: RAZORPAY_CONFIG.key,
        amount: orderData.amount * 100, // Amount in paise
        currency: RAZORPAY_CONFIG.currency,
        name: RAZORPAY_CONFIG.name,
        description: RAZORPAY_CONFIG.description,
        image: RAZORPAY_CONFIG.image,
        order_id: razorpayOrderId,

        // Prefill customer details
        prefill: {
            name: user?.name || '',
            email: user?.email || '',
            contact: user?.mobile || ''
        },

        // Notes
        notes: {
            order_id: orderData.order_id,
            user_id: orderData.user_id
        },

        // Theme
        theme: RAZORPAY_CONFIG.theme,

        // Payment success handler
        handler: function(response) {
            handlePaymentSuccess(response, orderData, onSuccess);
        },

        // Modal settings
        modal: {
            ondismiss: function() {
                Toast.info('Payment cancelled');
                if (onFailure) {
                    onFailure({ cancelled: true });
                }
            },
            confirm_close: true,
            escape: true,
            animation: true
        }
    };

    try {
        const rzp = new Razorpay(options);

        // Handle payment failure
        rzp.on('payment.failed', function(response) {
            handlePaymentFailure(response, orderData, onFailure);
        });

        // Open checkout
        rzp.open();
    } catch (error) {
        console.error('Razorpay initialization error:', error);
        Toast.error('Payment gateway error. Please try again.');
        if (onFailure) onFailure({ error: error.message });
    }
}

/**
 * Handle Payment Success
 */
function handlePaymentSuccess(razorpayResponse, orderData, callback) {
    Loading.show();

    const paymentData = {
        razorpay_order_id: razorpayResponse.razorpay_order_id,
        razorpay_payment_id: razorpayResponse.razorpay_payment_id,
        razorpay_signature: razorpayResponse.razorpay_signature,
        order_id: orderData.order_id,
        user_id: orderData.user_id,
        amount: orderData.amount
    };

    // Verify payment on backend
    $.ajax({
        url: API_ENDPOINTS.payment + '?action=verify',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(paymentData),
        dataType: 'json',
        success: function(response) {
            Loading.hide();

            if (response.success) {
                Toast.success('Payment successful!');

                // Update order status
                updateOrderPaymentStatus(orderData.order_id, 'paid', razorpayResponse.razorpay_payment_id);

                // Call success callback
                if (callback) {
                    callback({
                        success: true,
                        payment_id: razorpayResponse.razorpay_payment_id,
                        order_id: orderData.order_id
                    });
                }
            } else {
                Toast.error('Payment verification failed');
                if (callback) callback({ success: false, verified: false });
            }
        },
        error: function(xhr) {
            Loading.hide();
            console.error('Payment verification error:', xhr);
            Toast.error('Payment verification failed');
            if (callback) callback({ success: false, error: 'Verification failed' });
        }
    });
}

/**
 * Handle Payment Failure
 */
function handlePaymentFailure(response, orderData, callback) {
    console.error('Payment failed:', response);

    const errorData = {
        code: response.error.code,
        description: response.error.description,
        source: response.error.source,
        step: response.error.step,
        reason: response.error.reason,
        order_id: orderData.order_id
    };

    // Log failure on backend
    $.ajax({
        url: API_ENDPOINTS.payment + '?action=log_failure',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(errorData),
        dataType: 'json'
    });

    // Show user-friendly error message
    let errorMessage = 'Payment failed. ';
    switch (response.error.code) {
        case 'BAD_REQUEST_ERROR':
            errorMessage += 'Invalid payment details.';
            break;
        case 'GATEWAY_ERROR':
            errorMessage += 'Payment gateway error. Please try again.';
            break;
        case 'NETWORK_ERROR':
            errorMessage += 'Network error. Please check your connection.';
            break;
        case 'SERVER_ERROR':
            errorMessage += 'Server error. Please try again later.';
            break;
        default:
            errorMessage += response.error.description || 'Please try again.';
    }

    Toast.error(errorMessage);

    // Update order status
    updateOrderPaymentStatus(orderData.order_id, 'failed', null);

    // Call failure callback
    if (callback) {
        callback({
            success: false,
            error: errorData
        });
    }
}

/**
 * Update Order Payment Status
 */
function updateOrderPaymentStatus(orderId, status, paymentId) {
    $.ajax({
        url: API_ENDPOINTS.orders,
        type: 'PUT',
        contentType: 'application/json',
        data: JSON.stringify({
            id: orderId,
            payment_status: status,
            payment_id: paymentId,
            payment_method: 'razorpay'
        }),
        dataType: 'json',
        success: function(response) {
            console.log('Order payment status updated:', status);
        },
        error: function(xhr) {
            console.error('Failed to update order status:', xhr);
        }
    });
}

/**
 * Process Checkout Payment
 * Main function to call from checkout page
 */
function processCheckoutPayment(orderDetails, successCallback) {
    const user = AppState.loadUser();

    if (!user || !user.id) {
        Toast.error('Please login to continue');
        return;
    }

    // Prepare order data
    const orderData = {
        order_id: orderDetails.id,
        user_id: user.id,
        amount: orderDetails.total_amount,
        notes: {
            items_count: orderDetails.items?.length || 0,
            delivery_address: orderDetails.delivery_address || '',
            order_date: new Date().toISOString()
        }
    };

    // Initialize payment
    initRazorpayPayment(
        orderData,
        function(response) {
            // Success
            if (successCallback) {
                successCallback(response);
            } else {
                // Default: redirect to order confirmation
                window.location.href = `order-confirmation.html?order_id=${orderDetails.id}&payment_id=${response.payment_id}`;
            }
        },
        function(error) {
            // Failure
            console.error('Payment failed:', error);
            // Stay on checkout page
        }
    );
}

/**
 * Save Payment Method (for future payments)
 */
function savePaymentMethod(paymentDetails) {
    const user = AppState.loadUser();

    if (!user || !user.id) {
        return;
    }

    $.ajax({
        url: API_ENDPOINTS.payment + '?action=save_method',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            user_id: user.id,
            payment_method: paymentDetails
        }),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                console.log('Payment method saved');
            }
        },
        error: function(xhr) {
            console.error('Failed to save payment method:', xhr);
        }
    });
}

/**
 * Load Saved Payment Methods
 */
function loadSavedPaymentMethods(callback) {
    const user = AppState.loadUser();

    if (!user || !user.id) {
        callback([]);
        return;
    }

    $.ajax({
        url: API_ENDPOINTS.payment + '?action=get_methods&user_id=' + user.id,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                callback(response.data);
            } else {
                callback([]);
            }
        },
        error: function(xhr) {
            console.error('Failed to load payment methods:', xhr);
            callback([]);
        }
    });
}

// Export functions if using modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initRazorpayPayment,
        processCheckoutPayment,
        savePaymentMethod,
        loadSavedPaymentMethods
    };
}
