<?php
session_start();
require_once "../connection.php";
require_once "../includes/payment_functions.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_name = $_SESSION['name'] ?? 'Customer';
$payment_reference = $_GET['ref'] ?? '';

if (empty($payment_reference)) {
    header("Location: dashboard.php");
    exit();
}

// Get payment details
$payment = getPaymentByReference($conn, $payment_reference);
if (!$payment || $payment['user_id'] != $_SESSION['user_id']) {
    header("Location: dashboard.php");
    exit();
}

// Get related order or booking details
$item_details = null;
if ($payment['payment_type'] === 'order' && $payment['order_id']) {
    $stmt = $conn->prepare("
        SELECT po.*, b.booking_datetime, b.meal_type, rt.table_label 
        FROM pre_orders po 
        JOIN bookings b ON po.booking_id = b.id 
        JOIN restaurant_tables rt ON b.table_id = rt.id 
        WHERE po.id = ?
    ");
    $stmt->execute([$payment['order_id']]);
    $item_details = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($payment['payment_type'] === 'booking' && $payment['booking_id']) {
    $stmt = $conn->prepare("
        SELECT b.*, rt.table_label 
        FROM bookings b 
        JOIN restaurant_tables rt ON b.table_id = rt.id 
        WHERE b.id = ?
    ");
    $stmt->execute([$payment['booking_id']]);
    $item_details = $stmt->fetch(PDO::FETCH_ASSOC);
}

$payment_method_info = formatPaymentMethod($payment['payment_method']);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment Successful - Ethiopian Restaurant</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #28a745, #20c997); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        
        .container { max-width: 600px; width: 90%; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); text-align: center; }
        
        .success-icon { font-size: 5rem; color: #28a745; margin-bottom: 20px; animation: bounce 1s ease-in-out; }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        
        .title { color: #333; margin-bottom: 10px; }
        .subtitle { color: #666; margin-bottom: 30px; }
        
        .payment-details { background: #f8f9fa; padding: 25px; border-radius: 15px; margin-bottom: 30px; text-align: left; }
        .details-header { text-align: center; margin-bottom: 20px; }
        .details-header h3 { color: #333; }
        
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 12px; padding: 8px 0; border-bottom: 1px solid #eee; }
        .detail-row:last-child { border-bottom: none; margin-bottom: 0; }
        .detail-label { font-weight: 500; color: #555; }
        .detail-value { font-weight: bold; color: #333; }
        
        .amount-row { background: rgba(40, 167, 69, 0.1); padding: 15px; border-radius: 10px; margin-top: 15px; }
        .amount-row .detail-value { color: #28a745; font-size: 1.2rem; }
        
        .next-steps { background: #e3f2fd; padding: 20px; border-radius: 15px; margin-bottom: 30px; text-align: left; }
        .next-steps h4 { color: #1976d2; margin-bottom: 15px; text-align: center; }
        .next-steps ul { list-style: none; padding: 0; }
        .next-steps li { margin-bottom: 10px; padding-left: 25px; position: relative; }
        .next-steps li:before { content: '✓'; position: absolute; left: 0; color: #28a745; font-weight: bold; }
        
        .action-buttons { display: flex; gap: 15px; flex-wrap: wrap; }
        .btn { flex: 1; min-width: 200px; padding: 15px 20px; border: none; border-radius: 10px; font-size: 16px; cursor: pointer; transition: 0.3s; text-decoration: none; display: inline-block; text-align: center; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5a6fd8; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        
        @media (max-width: 768px) {
            .container { padding: 30px 20px; }
            .detail-row { flex-direction: column; gap: 5px; }
            .action-buttons { flex-direction: column; }
            .btn { min-width: auto; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="success-icon">
        <i class="fas fa-check-circle"></i>
    </div>
    
    <h1 class="title">Payment Successful!</h1>
    <p class="subtitle">Your payment has been processed successfully</p>
    
    <div class="payment-details">
        <div class="details-header">
            <h3><i class="fas fa-receipt"></i> Payment Receipt</h3>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Payment Reference:</span>
            <span class="detail-value"><?= htmlspecialchars($payment_reference) ?></span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Transaction ID:</span>
            <span class="detail-value"><?= htmlspecialchars($payment['transaction_id']) ?></span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Payment Method:</span>
            <span class="detail-value">
                <i class="<?= $payment_method_info['icon'] ?>"></i> 
                <?= $payment_method_info['text'] ?>
            </span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Payment Date:</span>
            <span class="detail-value"><?= date('M j, Y g:i A', strtotime($payment['updated_at'])) ?></span>
        </div>
        
        <?php if ($item_details): ?>
        <div class="detail-row">
            <span class="detail-label"><?= ucfirst($payment['payment_type']) ?> ID:</span>
            <span class="detail-value">#<?= $payment['payment_type'] === 'order' ? $payment['order_id'] : $payment['booking_id'] ?></span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Table:</span>
            <span class="detail-value"><?= htmlspecialchars($item_details['table_label']) ?></span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Date & Time:</span>
            <span class="detail-value"><?= date('M j, Y g:i A', strtotime($item_details['booking_datetime'])) ?></span>
        </div>
        <?php endif; ?>
        
        <div class="detail-row amount-row">
            <span class="detail-label">Amount Paid:</span>
            <span class="detail-value">ETB <?= number_format($payment['amount'], 2) ?></span>
        </div>
    </div>
    
    <div class="next-steps">
        <h4><i class="fas fa-list-check"></i> What's Next?</h4>
        <ul>
            <?php if ($payment['payment_type'] === 'order'): ?>
                <li>Your order has been confirmed and sent to our kitchen</li>
                <li>Our chefs will start preparing your food</li>
                <li>Your food will be ready when you arrive at the restaurant</li>
                <li>Please arrive on time for your table reservation</li>
            <?php elseif ($payment['payment_type'] === 'booking'): ?>
                <li>Your table reservation has been confirmed</li>
                <li>Please arrive on time for your reservation</li>
                <li>You can now pre-order food to save time</li>
                <li>Enjoy authentic Ethiopian cuisine!</li>
            <?php endif; ?>
            <li>Keep this receipt for your records</li>
        </ul>
    </div>
    
    <div class="action-buttons">
        <?php if ($payment['payment_type'] === 'order'): ?>
            <a href="my_orders.php" class="btn btn-primary">
                <i class="fas fa-list"></i> View My Orders
            </a>
        <?php else: ?>
            <a href="my_bookings.php" class="btn btn-primary">
                <i class="fas fa-calendar"></i> View My Bookings
            </a>
        <?php endif; ?>
        
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-home"></i> Back to Dashboard
        </a>
    </div>
</div>

<script>
// Auto-redirect after 30 seconds
setTimeout(function() {
    if (confirm('Would you like to return to the dashboard?')) {
        window.location.href = 'dashboard.php';
    }
}, 30000);
</script>

</body>
</html>