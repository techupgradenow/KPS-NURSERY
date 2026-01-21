/**
 * Payment Methods Management
 * Add this to profile.js
 */

// Open Payment Methods Modal
function openPaymentMethodsModal() {
    const user = AppState.loadUser();

    if (!user || !user.id) {
        Toast.error('Please login first');
        return;
    }

    // Show modal (create if doesn't exist)
    if ($('#payment-methods-modal').length === 0) {
        createPaymentMethodsModal();
    }

    $('#payment-methods-modal').fadeIn(200);
    loadPaymentMethods();
}

// Create Payment Methods Modal
function createPaymentMethodsModal() {
    const modalHTML = `
        <div class="modal" id="payment-methods-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Payment Methods</h3>
                    <button class="close-modal" id="close-payment-methods">&times;</button>
                </div>
                <div class="modal-body">
                    <div style="background: #FFF9E6; border: 1px solid #FFD700; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: start; gap: 0.75rem;">
                            <i class="fas fa-info-circle" style="color: #FF6A2A; font-size: 1.25rem; margin-top: 0.125rem;"></i>
                            <div style="flex: 1;">
                                <strong style="color: #2D3436;">Razorpay Secure Checkout</strong>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem; color: #666;">
                                    We use Razorpay for secure payment processing. Your payment methods are saved securely by Razorpay.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div id="payment-methods-list">
                        <!-- Payment methods will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    `;
    $('body').append(modalHTML);

    // Close handler
    $('#close-payment-methods').on('click', function() {
        $('#payment-methods-modal').fadeOut(200);
    });
}

// Load Payment Methods
function loadPaymentMethods() {
    if (typeof loadSavedPaymentMethods !== 'function') {
        $('#payment-methods-list').html('<p style="text-align: center; padding: 2rem; color: var(--gray);">Payment methods feature requires Razorpay integration</p>');
        return;
    }

    Loading.show();

    loadSavedPaymentMethods(function(methods) {
        Loading.hide();
        renderPaymentMethods(methods);
    });
}

// Render Payment Methods
function renderPaymentMethods(methods) {
    if (!methods || methods.length === 0) {
        $('#payment-methods-list').html(`
            <div style="text-align: center; padding: 2rem; color: var(--gray);">
                <i class="fas fa-credit-card fa-3x" style="margin-bottom: 1rem; opacity: 0.3;"></i>
                <div style="font-weight: 600; margin-bottom: 0.5rem;">No saved payment methods</div>
                <div style="font-size: 0.875rem;">Your payment methods will be saved when you make a purchase</div>
            </div>
        `);
        return;
    }

    const methodsHTML = methods.map(method => {
        const iconMap = {
            'card': 'fa-credit-card',
            'upi': 'fa-mobile-alt',
            'netbanking': 'fa-university',
            'wallet': 'fa-wallet'
        };

        const networkColors = {
            'visa': '#1A1F71',
            'mastercard': '#EB001B',
            'rupay': '#097939',
            'amex': '#006FCF'
        };

        const icon = iconMap[method.method_type] || 'fa-credit-card';
        const color = networkColors[method.card_network?.toLowerCase()] || '#666';

        return `
            <div class="payment-method-card" style="border: 1px solid var(--border); border-radius: var(--radius); padding: 1rem; margin-bottom: 1rem; position: relative;">
                ${method.is_default == 1 ? '<div style="position: absolute; top: 0.5rem; right: 0.5rem; background: var(--success); color: white; padding: 0.25rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem;">Default</div>' : ''}
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <i class="fas ${icon}" style="font-size: 1.5rem; color: ${color};"></i>
                    <div style="flex: 1;">
                        <div style="font-weight: 600; text-transform: uppercase;">${method.card_network || method.method_type}</div>
                        <div style="font-size: 0.875rem; color: var(--gray); margin-top: 0.25rem;">
                            ${method.last_4_digits ? '•••• ' + method.last_4_digits : 'Saved payment method'}
                        </div>
                    </div>
                    <button class="btn btn-sm btn-danger" onclick="deletePaymentMethod(${method.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    }).join('');

    $('#payment-methods-list').html(methodsHTML);
}

// Delete Payment Method
function deletePaymentMethod(methodId) {
    if (!confirm('Are you sure you want to delete this payment method?')) {
        return;
    }

    Loading.show();

    $.ajax({
        url: API_ENDPOINTS.payment + '?action=delete_method&id=' + methodId,
        type: 'DELETE',
        dataType: 'json',
        success: function(response) {
            Loading.hide();
            if (response.success) {
                Toast.success('Payment method deleted');
                loadPaymentMethods();
            } else {
                Toast.error('Failed to delete payment method');
            }
        },
        error: function() {
            Loading.hide();
            Toast.error('Failed to delete payment method');
        }
    });
}
