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
$payment_method = $_GET['method'] ?? '';

if (empty($payment_reference) || empty($payment_method)) {
    header("Location: dashboard.php");
    exit();
}

// Get payment details
$payment = getPaymentByReference($conn, $payment_reference);
if (!$payment || $payment['user_id'] != $_SESSION['user_id']) {
    header("Location: dashboard.php");
    exit();
}

// Check if payment is expired
if (isPaymentExpired($payment)) {
    updatePaymentStatus($conn, $payment_reference, 'expired');
    header("Location: payment_fail.php?reason=expired");
    exit();
}

// Handle payment confirmation (simulation)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'confirm') {
        // Simulate successful payment
        $transaction_id = ($payment_method === 'telebirr' ? 'TB_' : 'CBE_') . time() . '_' . rand(1000, 9999);
        
        updatePaymentStatus($conn, $payment_reference, 'completed', $transaction_id);
        
        // Update order/booking status
        if ($payment['payment_type'] === 'order' && $payment['order_id']) {
            $conn->prepare("UPDATE pre_orders SET status = 'confirmed' WHERE id = ?")->execute([$payment['order_id']]);
        } elseif ($payment['payment_type'] === 'booking' && $payment['booking_id']) {
            $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?")->execute([$payment['booking_id']]);
        }
        
        header("Location: payment_success.php?ref=" . $payment_reference);
        exit();
    } elseif ($action === 'cancel') {
        updatePaymentStatus($conn, $payment_reference, 'cancelled');
        header("Location: payment_cancel.php?ref=" . $payment_reference);
        exit();
    }
}

$method_info = formatPaymentMethod($payment_method);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Processing Payment - Ethiopian Restaurant</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        
        .container { max-width: 500px; width: 90%; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); text-align: center; }
        
        .payment-icon { font-size: 4rem; color: #667eea; margin-bottom: 20px; }
        .title { color: #333; margin-bottom: 10px; }
        .subtitle { color: #666; margin-bottom: 30px; }
        
        .payment-info { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 30px; text-align: left; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .info-row:last-child { margin-bottom: 0; font-weight: bold; color: #667eea; }
        
        .simulation-notice { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 10px; margin-bottom: 30px; }
        .simulation-notice h4 { color: #856404; margin-bottom: 10px; }
        .simulation-notice p { color: #856404; font-size: 0.9rem; }
        
        .payment-actions { display: flex; gap: 15px; }
        .btn { flex: 1; padding: 15px; border: none; border-radius: 10px; font-size: 16px; cursor: pointer; transition: 0.3s; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        
        .timer { background: #e3f2fd; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .timer-text { color: #1976d2; font-weight: bold; }
        
        .payment-instructions { background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 10px; margin-bottom: 30px; }
        .payment-instructions h4 { color: #856404; margin-bottom: 15px; }
        .instruction-box { background: white; padding: 15px; border-radius: 8px; }
        .payment-number { font-size: 1.2rem; font-weight: bold; color: #667eea; margin: 10px 0; }
        
        @media (max-width: 768px) {
            .container { padding: 30px 20px; }
            .payment-actions { flex-direction: column; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="payment-icon">
        <i class="<?= $method_info['icon'] ?>"></i>
    </div>
    
    <h1 class="title">Processing <?= $method_info['text'] ?> Payment</h1>
    <p class="subtitle">Please complete your payment to confirm your <?= $payment['payment_type'] ?></p>
    
    <div class="timer">
        <div class="timer-text">
            <i class="fas fa-clock"></i> 
            Time remaining: <span id="countdown">3:00:00</span>
        </div>
    </div>
    
    <div class="payment-info">
        <div class="info-row">
            <span>Payment Reference:</span>
            <span><?= htmlspecialchars($payment_reference) ?></span>
        </div>
        <div class="info-row">
            <span>Payment Method:</span>
            <span><?= $method_info['text'] ?></span>
        </div>
        <div class="info-row">
            <span>Amount:</span>
            <span>ETB <?= number_format($payment['amount'], 2) ?></span>
        </div>
    </div>
    
    <div class="payment-instructions">
        <h4><i class="fas fa-mobile-alt"></i> Payment Instructions</h4>
        <?php if ($payment_method === 'telebirr'): ?>
            <div class="instruction-box">
                <p><strong>Send payment via TeleBirr to:</strong></p>
                <p class="payment-number">📱 <?= TELEBIRR_PHONE ?></p>
                <p><strong>Amount:</strong> ETB <?= number_format($payment['amount'], 2) ?></p>
                <p><strong>Reference:</strong> <?= htmlspecialchars($payment_reference) ?></p>
                <p><em>After sending payment, click "Confirm Payment" below</em></p>
            </div>
        <?php elseif ($payment_method === 'cbe'): ?>
            <div class="instruction-box">
                <p><strong>Transfer payment via CBE to:</strong></p>
                <p class="payment-number">🏛️ <?= CBE_ACCOUNT ?></p>
                <p><strong>Amount:</strong> ETB <?= number_format($payment['amount'], 2) ?></p>
                <p><strong>Reference:</strong> <?= htmlspecialchars($payment_reference) ?></p>
                <p><em>After transferring, click "Confirm Payment" below</em></p>
            </div>
        <?php endif; ?>
    </div>
    
    <form method="POST">
        <div class="payment-actions">
            <button type="submit" name="action" value="confirm" class="btn btn-success">
                <i class="fas fa-check"></i> Confirm Payment
            </button>
            <button type="submit" name="action" value="cancel" class="btn btn-danger">
                <i class="fas fa-times"></i> Cancel Payment
            </button>
        </div>
    </form>
</div>

<script>
// Countdown timer - calculate remaining time based on payment expiration
<?php 
$expires_timestamp = strtotime($payment['expires_at']);
$current_timestamp = time();
$remaining_seconds = max(0, $expires_timestamp - $current_timestamp);
?>
let timeLeft = <?= $remaining_seconds ?>; // Remaining seconds until expiration

function updateCountdown() {
    const hours = Math.floor(timeLeft / 3600);
    const minutes = Math.floor((timeLeft % 3600) / 60);
    const seconds = timeLeft % 60;
    
    document.getElementById('countdown').textContent = 
        hours.toString().padStart(2, '0') + ':' + 
        minutes.toString().padStart(2, '0') + ':' + 
        seconds.toString().padStart(2, '0');
    
    if (timeLeft <= 0) {
        // Payment expired
        alert('Payment session has expired. You will be redirected.');
        window.location.href = 'payment_fail.php?reason=expired';
        return;
    }
    
    timeLeft--;
}

// Update countdown every second
setInterval(updateCountdown, 1000);
updateCountdown(); // Initial call
</script>

</body>
</html>