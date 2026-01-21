<?php
/**
 * Payment API - Razorpay Integration
 * Handle payment order creation, verification, and management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/db.php';
require_once '../utils/Response.php';

// Razorpay Configuration
// IMPORTANT: Replace with your actual Razorpay credentials
$RAZORPAY_CONFIG = [
    'key_id' => 'rzp_test_XXXXXXXXXXXX',  // Replace with your Key ID
    'key_secret' => 'XXXXXXXXXXXXXXXXXXXXXXXX',  // Replace with your Key Secret
    'currency' => 'INR'
];

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch($action) {
        case 'create_order':
            createRazorpayOrder($db, $RAZORPAY_CONFIG);
            break;
        case 'verify':
            verifyPayment($db, $RAZORPAY_CONFIG);
            break;
        case 'save_method':
            savePaymentMethod($db);
            break;
        case 'get_methods':
            getPaymentMethods($db);
            break;
        case 'delete_method':
            deletePaymentMethod($db);
            break;
        case 'log_failure':
            logPaymentFailure($db);
            break;
        default:
            Response::error('Invalid action', 400);
    }
} catch (Exception $e) {
    error_log("Payment API Error: " . $e->getMessage());
    Response::serverError('Payment processing failed');
}

/**
 * Create Razorpay Order
 */
function createRazorpayOrder($db, $config) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['amount']) || !isset($input['order_id'])) {
        Response::error('Amount and order ID are required');
    }

    $amount = intval($input['amount']) * 100; // Convert to paise
    $currency = $input['currency'] ?? $config['currency'];
    $order_id = $input['order_id'];
    $user_id = $input['user_id'] ?? null;

    try {
        // Create Razorpay order using cURL
        $url = 'https://api.razorpay.com/v1/orders';

        $data = [
            'amount' => $amount,
            'currency' => $currency,
            'receipt' => 'order_' . $order_id,
            'notes' => $input['notes'] ?? []
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $config['key_id'] . ':' . $config['key_secret']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $razorpayOrder = json_decode($response, true);

            // Save payment order in database
            $stmt = $db->prepare("
                INSERT INTO payment_orders (order_id, user_id, razorpay_order_id, amount, currency, status, created_at)
                VALUES (:order_id, :user_id, :razorpay_order_id, :amount, :currency, 'created', NOW())
            ");
            $stmt->execute([
                ':order_id' => $order_id,
                ':user_id' => $user_id,
                ':razorpay_order_id' => $razorpayOrder['id'],
                ':amount' => $amount / 100,
                ':currency' => $currency
            ]);

            Response::success([
                'razorpay_order_id' => $razorpayOrder['id'],
                'amount' => $razorpayOrder['amount'],
                'currency' => $razorpayOrder['currency']
            ], 'Order created successfully');
        } else {
            error_log("Razorpay API Error: " . $response);
            Response::error('Failed to create payment order', 500);
        }
    } catch (Exception $e) {
        error_log("Create Order Error: " . $e->getMessage());
        Response::serverError('Failed to create payment order');
    }
}

/**
 * Verify Payment Signature
 */
function verifyPayment($db, $config) {
    $input = json_decode(file_get_contents('php://input'), true);

    $requiredFields = ['razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature', 'order_id'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field])) {
            Response::error("$field is required");
        }
    }

    try {
        // Verify signature
        $razorpayOrderId = $input['razorpay_order_id'];
        $razorpayPaymentId = $input['razorpay_payment_id'];
        $razorpaySignature = $input['razorpay_signature'];

        $generatedSignature = hash_hmac(
            'sha256',
            $razorpayOrderId . '|' . $razorpayPaymentId,
            $config['key_secret']
        );

        if ($generatedSignature === $razorpaySignature) {
            // Signature verified - Payment is genuine
            $orderId = $input['order_id'];
            $userId = $input['user_id'] ?? null;
            $amount = $input['amount'] ?? 0;

            // Update payment order status
            $stmt = $db->prepare("
                UPDATE payment_orders
                SET razorpay_payment_id = :payment_id,
                    razorpay_signature = :signature,
                    status = 'paid',
                    paid_at = NOW()
                WHERE razorpay_order_id = :order_id
            ");
            $stmt->execute([
                ':payment_id' => $razorpayPaymentId,
                ':signature' => $razorpaySignature,
                ':order_id' => $razorpayOrderId
            ]);

            // Update main order status
            $stmt = $db->prepare("
                UPDATE orders
                SET payment_status = 'paid',
                    payment_id = :payment_id,
                    payment_method = 'razorpay',
                    updated_at = NOW()
                WHERE id = :order_id
            ");
            $stmt->execute([
                ':payment_id' => $razorpayPaymentId,
                ':order_id' => $orderId
            ]);

            Response::success([
                'verified' => true,
                'payment_id' => $razorpayPaymentId,
                'order_id' => $orderId
            ], 'Payment verified successfully');
        } else {
            // Invalid signature
            error_log("Invalid payment signature for order: " . $razorpayOrderId);

            // Mark as failed
            $stmt = $db->prepare("
                UPDATE payment_orders
                SET status = 'failed',
                    updated_at = NOW()
                WHERE razorpay_order_id = :order_id
            ");
            $stmt->execute([':order_id' => $razorpayOrderId]);

            Response::error('Payment verification failed', 400);
        }
    } catch (Exception $e) {
        error_log("Verify Payment Error: " . $e->getMessage());
        Response::serverError('Payment verification failed');
    }
}

/**
 * Save Payment Method for Future Use
 */
function savePaymentMethod($db) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['user_id'])) {
        Response::error('User ID is required');
    }

    try {
        $paymentMethod = $input['payment_method'] ?? [];

        $stmt = $db->prepare("
            INSERT INTO saved_payment_methods (user_id, method_type, last_4_digits, card_network, is_default, created_at)
            VALUES (:user_id, :method_type, :last_4_digits, :card_network, :is_default, NOW())
        ");
        $stmt->execute([
            ':user_id' => $input['user_id'],
            ':method_type' => $paymentMethod['type'] ?? 'card',
            ':last_4_digits' => $paymentMethod['last4'] ?? null,
            ':card_network' => $paymentMethod['network'] ?? null,
            ':is_default' => $input['is_default'] ?? 0
        ]);

        Response::success(['id' => $db->lastInsertId()], 'Payment method saved');
    } catch (Exception $e) {
        error_log("Save Payment Method Error: " . $e->getMessage());
        Response::serverError('Failed to save payment method');
    }
}

/**
 * Get Saved Payment Methods
 */
function getPaymentMethods($db) {
    $userId = $_GET['user_id'] ?? null;

    if (!$userId) {
        Response::error('User ID is required');
    }

    try {
        $stmt = $db->prepare("
            SELECT * FROM saved_payment_methods
            WHERE user_id = :user_id
            ORDER BY is_default DESC, created_at DESC
        ");
        $stmt->execute([':user_id' => $userId]);
        $methods = $stmt->fetchAll();

        Response::success($methods);
    } catch (Exception $e) {
        error_log("Get Payment Methods Error: " . $e->getMessage());
        Response::serverError('Failed to load payment methods');
    }
}

/**
 * Delete Saved Payment Method
 */
function deletePaymentMethod($db) {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        Response::error('Payment method ID is required');
    }

    try {
        $stmt = $db->prepare("DELETE FROM saved_payment_methods WHERE id = :id");
        $stmt->execute([':id' => $id]);

        Response::success(null, 'Payment method deleted');
    } catch (Exception $e) {
        error_log("Delete Payment Method Error: " . $e->getMessage());
        Response::serverError('Failed to delete payment method');
    }
}

/**
 * Log Payment Failure
 */
function logPaymentFailure($db) {
    $input = json_decode(file_get_contents('php://input'), true);

    try {
        $stmt = $db->prepare("
            INSERT INTO payment_failures (order_id, error_code, error_description, error_source, error_step, error_reason, created_at)
            VALUES (:order_id, :error_code, :error_description, :error_source, :error_step, :error_reason, NOW())
        ");
        $stmt->execute([
            ':order_id' => $input['order_id'] ?? null,
            ':error_code' => $input['code'] ?? null,
            ':error_description' => $input['description'] ?? null,
            ':error_source' => $input['source'] ?? null,
            ':error_step' => $input['step'] ?? null,
            ':error_reason' => $input['reason'] ?? null
        ]);

        Response::success(null, 'Failure logged');
    } catch (Exception $e) {
        error_log("Log Failure Error: " . $e->getMessage());
        Response::success(null, 'Failed to log failure');
    }
}
?>
