/**
 * Orders Page JavaScript
 * KPS Nursery App
 */

// Include common.js first

$(document).ready(function() {
    // Load orders
    loadOrders();

    // Setup event listeners
    setupEventListeners();
});

/**
 * Load User Orders
 */
function loadOrders() {
    const user = AppState.loadUser();

    if (!user) {
        $('#orders-container').empty();
        $('#empty-orders-message').show();
        return;
    }

    Loading.show();

    $.ajax({
        url: API_ENDPOINTS.orders + `?action=list&user_id=${user.id}`,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            Loading.hide();

            if (response.success && response.data && response.data.length > 0) {
                renderOrders(response.data);
            } else {
                $('#orders-container').empty();
                $('#empty-orders-message').show();
            }
        },
        error: function() {
            Loading.hide();
            Toast.error('Failed to load orders');
        }
    });
}

/**
 * Render Orders
 */
function renderOrders(orders) {
    $('#empty-orders-message').hide();

    const ordersHTML = orders.map(order => {
        const statusClass = getStatusClass(order.status);
        const statusIcon = getStatusIcon(order.status);

        return `
            <div class="order-card" onclick="viewOrderDetail(${order.id})">
                <div class="order-header">
                    <div>
                        <h4>Order #${order.id}</h4>
                        <p class="order-date">${formatDate(order.created_at)}</p>
                    </div>
                    <div class="order-status ${statusClass}">
                        <i class="${statusIcon}"></i> ${order.status}
                    </div>
                </div>
                <div class="order-items">
                    <p>${order.item_count} item(s)</p>
                </div>
                <div class="order-footer">
                    <div class="order-total">₹${parseFloat(order.total).toFixed(2)}</div>
                    <div class="order-actions">
                        <button class="btn btn-sm btn-outline">View Details</button>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    $('#orders-container').html(ordersHTML);
}

/**
 * Get Status Class
 */
function getStatusClass(status) {
    const classMap = {
        'pending': 'status-pending',
        'confirmed': 'status-confirmed',
        'preparing': 'status-preparing',
        'out_for_delivery': 'status-out-for-delivery',
        'delivered': 'status-delivered',
        'cancelled': 'status-cancelled'
    };
    return classMap[status] || 'status-pending';
}

/**
 * Get Status Icon
 */
function getStatusIcon(status) {
    const iconMap = {
        'pending': 'fas fa-clock',
        'confirmed': 'fas fa-check-circle',
        'preparing': 'fas fa-utensils',
        'out_for_delivery': 'fas fa-truck',
        'delivered': 'fas fa-check-double',
        'cancelled': 'fas fa-times-circle'
    };
    return iconMap[status] || 'fas fa-clock';
}

/**
 * Format Date
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    const options = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return date.toLocaleDateString('en-IN', options);
}

/**
 * View Order Detail
 */
function viewOrderDetail(orderId) {
    Loading.show();

    $.ajax({
        url: API_ENDPOINTS.orders + `?action=detail&order_id=${orderId}`,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            Loading.hide();

            if (response.success && response.data) {
                showOrderDetailModal(response.data);
            } else {
                Toast.error('Order not found');
            }
        },
        error: function() {
            Loading.hide();
            Toast.error('Failed to load order details');
        }
    });
}

/**
 * Show Order Detail Modal
 */
function showOrderDetailModal(order) {
    const statusClass = getStatusClass(order.status);
    const statusIcon = getStatusIcon(order.status);

    const itemsHTML = order.items.map(item => `
        <div class="order-detail-item">
            <div class="item-info">
                <h4>${item.product_name}</h4>
                <p>Quantity: ${item.quantity} × ₹${parseFloat(item.price).toFixed(2)}</p>
            </div>
            <div class="item-total">₹${(item.quantity * parseFloat(item.price)).toFixed(2)}</div>
        </div>
    `).join('');

    const modalHTML = `
        <div class="order-detail">
            <div class="order-detail-header">
                <h3>Order #${order.id}</h3>
                <div class="order-status ${statusClass}">
                    <i class="${statusIcon}"></i> ${order.status}
                </div>
            </div>

            <div class="order-detail-section">
                <h4><i class="fas fa-calendar"></i> Order Date</h4>
                <p>${formatDate(order.created_at)}</p>
            </div>

            <div class="order-detail-section">
                <h4><i class="fas fa-map-marker-alt"></i> Delivery Address</h4>
                <p>${order.delivery_address || 'Address not available'}</p>
            </div>

            <div class="order-detail-section">
                <h4><i class="fas fa-box"></i> Order Items</h4>
                ${itemsHTML}
            </div>

            <div class="order-detail-section">
                <h4><i class="fas fa-receipt"></i> Bill Summary</h4>
                <div class="bill-summary">
                    <div class="bill-row">
                        <span>Subtotal</span>
                        <span>₹${parseFloat(order.subtotal || order.total).toFixed(2)}</span>
                    </div>
                    <div class="bill-row">
                        <span>Delivery Charge</span>
                        <span>${order.delivery_charge ? '₹' + parseFloat(order.delivery_charge).toFixed(2) : 'FREE'}</span>
                    </div>
                    <div class="bill-row bill-total">
                        <span>Total Amount</span>
                        <span>₹${parseFloat(order.total).toFixed(2)}</span>
                    </div>
                </div>
            </div>

            <div class="order-detail-section">
                <h4><i class="fas fa-credit-card"></i> Payment Method</h4>
                <p>${order.payment_method.toUpperCase()}</p>
            </div>
        </div>
    `;

    $('#order-detail-content').html(modalHTML);
    $('#order-detail-modal').fadeIn(200);
}

/**
 * Setup Event Listeners
 */
function setupEventListeners() {
    // Close order detail modal
    $('#close-order-detail').on('click', function() {
        $('#order-detail-modal').fadeOut(200);
    });

    // Close modal on overlay click
    $('.modal').on('click', function(e) {
        if (e.target === this) {
            $(this).fadeOut(200);
        }
    });
}
