<?php
session_start();
require_once "../connection.php";
require_once "../includes/payment_functions.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Create payments table if it doesn't exist
createPaymentsTable($conn);

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Customer';
$error = "";
$success = "";

// Get payment details from URL parameters
$payment_type = $_GET['type'] ?? '';
$order_id = $_GET['order_id'] ?? null;
$booking_id = $_GET['booking_id'] ?? null;
$amount = floatval($_GET['amount'] ?? 0);

// Validate payment request
if (!in_array($payment_type, ['order', 'booking', 'deposit']) || $amount <= 0) {
    header("Location: dashboard.php");
    exit();
}

// Get order or booking details
$item_details = null;
if ($payment_type === 'order' && $order_id) {
    $stmt = $conn->prepare("
        SELECT po.*, b.booking_datetime, b.meal_type, rt.table_label 
        FROM pre_orders po 
        JOIN bookings b ON po.booking_id = b.id 
        JOIN restaurant_tables rt ON b.table_id = rt.id 
        WHERE po.id = ? AND b.user_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $item_details = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($payment_type === 'booking' && $booking_id) {
    $stmt = $conn->prepare("
        SELECT b.*, rt.table_label 
        FROM bookings b 
        JOIN restaurant_tables rt ON b.table_id = rt.id 
        WHERE b.id = ? AND b.user_id = ?
    ");
    $stmt->execute([$booking_id, $user_id]);
    $item_details = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$item_details) {
    header("Location: dashboard.php");
    exit();
}

// Handle "Not Now" button click
if (isset($_POST['skip_payment'])) {
    // Update order/booking status to indicate payment is pending
    if ($payment_type === 'order' && $order_id) {
        $conn->prepare("UPDATE pre_orders SET status = 'pending_payment' WHERE id = ?")->execute([$order_id]);
    } elseif ($payment_type === 'booking' && $booking_id) {
        $conn->prepare("UPDATE bookings SET status = 'pending_payment' WHERE id = ?")->execute([$booking_id]);
    }
    
    // Redirect to orders/bookings page with message
    $_SESSION['payment_message'] = "Payment skipped. You have " . (PAYMENT_TIMEOUT_MINUTES / 60) . " hours to complete payment before automatic cancellation.";
    header("Location: " . ($payment_type === 'order' ? 'my_orders.php' : 'my_bookings.php'));
    exit();
}

// Handle payment method selection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'] ?? '';
    $phone_number = trim($_POST['phone_number'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    
    if (empty($payment_method)) {
        $error = "Please select a payment method.";
    } else {
        // Create payment record with payment method
        $payment = createPayment($conn, $user_id, $amount, $payment_type, $order_id, $booking_id);
        
        if ($payment) {
            // Update payment method (the createPayment function creates with 'pending' method, so we need to update it)
            $stmt = $conn->prepare("UPDATE payments SET payment_method = ? WHERE payment_reference = ?");
            $stmt->execute([$payment_method, $payment['payment_reference']]);
            
            // Process payment based on method
            switch ($payment_method) {
                case 'telebirr':
                    if (empty($phone_number)) {
                        $error = "Please enter your TeleBirr phone number.";
                    } else {
                        $description = $payment_type === 'order' ? "Order #$order_id Payment" : "Booking #$booking_id Payment";
                        $result = initiateTeleBirrPayment($payment['payment_reference'], $amount, $phone_number, $description);
                        
                        if ($result['success']) {
                            header("Location: " . $result['payment_url']);
                            exit();
                        } else {
                            $error = "Failed to initiate TeleBirr payment: " . $result['message'];
                        }
                    }
                    break;
                    
                case 'cbe':
                    if (empty($account_number)) {
                        $error = "Please enter your CBE account number.";
                    } else {
                        $description = $payment_type === 'order' ? "Order #$order_id Payment" : "Booking #$booking_id Payment";
                        $result = initiateCBEPayment($payment['payment_reference'], $amount, $account_number, $description);
                        
                        if ($result['success']) {
                            header("Location: " . $result['payment_url']);
                            exit();
                        } else {
                            $error = "Failed to initiate CBE payment: " . $result['message'];
                        }
                    }
                    break;
                    
                case 'cash':
                    $result = processCashPayment($payment['payment_reference']);
                    updatePaymentStatus($conn, $payment['payment_reference'], 'pending', $result['transaction_id']);
                    
                    // Update order/booking status
                    if ($payment_type === 'order') {
                        $conn->prepare("UPDATE pre_orders SET status = 'confirmed' WHERE id = ?")->execute([$order_id]);
                    } elseif ($payment_type === 'booking') {
                        $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?")->execute([$booking_id]);
                    }
                    
                    header("Location: payment_success.php?ref=" . $payment['payment_reference']);
                    exit();
                    break;
                    
                default:
                    $error = "Invalid payment method selected.";
            }
        } else {
            $error = "Failed to create payment record. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment - Ethiopian Restaurant</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100vh; }
        
        header { background: rgba(255,255,255,0.95); padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        nav a { color: #333; text-decoration: none; margin: 0 10px; padding: 8px 15px; border-radius: 20px; transition: 0.3s; }
        nav a:hover { background: #667eea; color: white; }
        .logout-btn { background: #ff6b6b; color: white !important; }
        
        .container { max-width: 600px; margin: 50px auto; background: white; padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .title { text-align: center; color: #333; margin-bottom: 30px; }
        
        .payment-summary { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 30px; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .summary-total { font-weight: bold; font-size: 1.2rem; color: #667eea; border-top: 2px solid #667eea; padding-top: 10px; margin-top: 10px; }
        
        .payment-methods { margin-bottom: 30px; }
        .method-option { border: 2px solid #ddd; border-radius: 10px; padding: 20px; margin-bottom: 15px; cursor: pointer; transition: 0.3s; }
        .method-option:hover { border-color: #667eea; }
        .method-option.selected { border-color: #667eea; background: rgba(102, 126, 234, 0.1); }
        .method-option input[type="radio"] { display: none; }
        .method-header { display: flex; align-items: center; margin-bottom: 10px; }
        .method-icon { font-size: 1.5rem; margin-right: 15px; width: 30px; }
        .method-name { font-weight: bold; font-size: 1.1rem; }
        .method-description { color: #666; font-size: 0.9rem; }
        
        .payment-details { display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd; }
        .payment-details.active { display: block; }
        
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .form-input { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 10px; font-size: 16px; }
        .form-input:focus { border-color: #667eea; outline: none; }
        
        .submit-btn { width: 100%; padding: 15px; background: #667eea; color: white; border: none; border-radius: 10px; font-size: 16px; cursor: pointer; transition: 0.3s; }
        .submit-btn:hover { background: #5a6fd8; }
        .submit-btn:disabled { background: #ccc; cursor: not-allowed; }
        
        .not-now-btn { 
            width: 100%; 
            padding: 12px; 
            background: transparent; 
            color: #667eea; 
            border: 2px solid #667eea; 
            border-radius: 10px; 
            font-size: 14px; 
            cursor: pointer; 
            transition: 0.3s; 
        }
        .not-now-btn:hover { 
            background: #667eea; 
            color: white; 
        }
        
        .message { padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .back-link { display: inline-block; margin-top: 20px; color: #667eea; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        
        @media (max-width: 768px) {
            .container { margin: 20px; padding: 20px; }
            .summary-row { flex-direction: column; gap: 5px; }
        }
    </style>
</head>
<body>

<header>
    <h2>Welcome, <?php echo htmlspecialchars($user_name); ?></h2>
    <nav>
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="View_Tables.php"><i class="fas fa-chair"></i> Tables</a>
        <a href="view_menu.php"><i class="fas fa-utensils"></i> Food</a>
        <a href="view_drink_menu.php"><i class="fas fa-cocktail"></i> Drinks</a>
        <a class="logout-btn" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</header>

<div class="container">
    <h1 class="title">
        <i class="fas fa-credit-card"></i>
        Payment Method
    </h1>
    
    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if (isset($_POST['payment_method']) && !empty($_POST['payment_method'])): ?>
        <div style="background: #e3f2fd; padding: 10px; border-radius: 5px; margin-bottom: 20px; font-size: 12px;">
            <strong>Debug Info:</strong><br>
            Selected Method: <?= htmlspecialchars($_POST['payment_method']) ?><br>
            Phone Number: <?= htmlspecialchars($_POST['phone_number'] ?? 'Not provided') ?><br>
            Account Number: <?= htmlspecialchars($_POST['account_number'] ?? 'Not provided') ?><br>
            Payment Type: <?= htmlspecialchars($payment_type) ?><br>
            Amount: ETB <?= number_format($amount, 2) ?><br>
        </div>
    <?php endif; ?>
    
    <div class="payment-summary">
        <h3><i class="fas fa-receipt"></i> Payment Summary</h3>
        
        <?php if ($payment_type === 'order'): ?>
            <div class="summary-row">
                <span>Order ID:</span>
                <span>#<?= $order_id ?></span>
            </div>
            <div class="summary-row">
                <span>Table:</span>
                <span><?= htmlspecialchars($item_details['table_label']) ?></span>
            </div>
            <div class="summary-row">
                <span>Date & Time:</span>
                <span><?= date('M j, Y g:i A', strtotime($item_details['booking_datetime'])) ?></span>
            </div>
        <?php elseif ($payment_type === 'booking'): ?>
            <div class="summary-row">
                <span>Booking ID:</span>
                <span>#<?= $booking_id ?></span>
            </div>
            <div class="summary-row">
                <span>Table:</span>
                <span><?= htmlspecialchars($item_details['table_label']) ?></span>
            </div>
            <div class="summary-row">
                <span>Date & Time:</span>
                <span><?= date('M j, Y g:i A', strtotime($item_details['booking_datetime'])) ?></span>
            </div>
            <div class="summary-row">
                <span>Guests:</span>
                <span><?= $item_details['guests'] ?> people</span>
            </div>
        <?php endif; ?>
        
        <div class="summary-row summary-total">
            <span>Total Amount:</span>
            <span>ETB <?= number_format($amount, 2) ?></span>
        </div>
    </div>
    
    <form method="POST" id="paymentForm">
        <div class="payment-methods">
            <h3><i class="fas fa-wallet"></i> Select Payment Method</h3>
            
            <?php if (ENABLE_TELEBIRR): ?>
            <div class="method-option" onclick="selectPaymentMethod('telebirr')">
                <input type="radio" name="payment_method" value="telebirr" id="telebirr">
                <div class="method-header">
                    <div class="method-icon" style="color: #ff6b35;">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div>
                        <div class="method-name">TeleBirr</div>
                        <div class="method-description">Pay with your TeleBirr mobile wallet</div>
                    </div>
                </div>
                <div class="payment-details" id="telebirr-details">
                    <div class="form-group">
                        <label class="form-label">TeleBirr Phone Number</label>
                        <input type="tel" name="phone_number" class="form-input" placeholder="09xxxxxxxx" pattern="[0-9]{10}">
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (ENABLE_CBE): ?>
            <div class="method-option" onclick="selectPaymentMethod('cbe')">
                <input type="radio" name="payment_method" value="cbe" id="cbe">
                <div class="method-header">
                    <div class="method-icon" style="color: #2c5aa0;">
                        <i class="fas fa-university"></i>
                    </div>
                    <div>
                        <div class="method-name">CBE Bank</div>
                        <div class="method-description">Pay with Commercial Bank of Ethiopia</div>
                    </div>
                </div>
                <div class="payment-details" id="cbe-details">
                    <div class="form-group">
                        <label class="form-label">CBE Account Number</label>
                        <input type="text" name="account_number" class="form-input" placeholder="Enter your CBE account number">
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (ENABLE_CASH_ON_ARRIVAL): ?>
            <div class="method-option" onclick="selectPaymentMethod('cash')">
                <input type="radio" name="payment_method" value="cash" id="cash">
                <div class="method-header">
                    <div class="method-icon" style="color: #28a745;">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div>
                        <div class="method-name">Cash on Arrival</div>
                        <div class="method-description">Pay when you arrive at the restaurant</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <button type="submit" class="submit-btn" id="submitBtn" disabled>
            <i class="fas fa-lock"></i> Proceed to Payment
        </button>
        
        <div style="margin-top: 15px; text-align: center;">
            <button type="button" class="not-now-btn" onclick="skipPaymentNow()">
                <i class="fas fa-clock"></i> Not Now - Pay Later
            </button>
        </div>
    </form>
    
    <a href="<?= $payment_type === 'order' ? 'my_orders.php' : 'my_bookings.php' ?>" class="back-link">
        ← Back to <?= $payment_type === 'order' ? 'Orders' : 'Bookings' ?>
    </a>
</div>

<script>
let selectedMethod = null;

function selectPaymentMethod(method) {
    // Remove previous selection
    document.querySelectorAll('.method-option').forEach(option => {
        option.classList.remove('selected');
    });
    
    document.querySelectorAll('.payment-details').forEach(details => {
        details.classList.remove('active');
    });
    
    // Select new method
    selectedMethod = method;
    document.getElementById(method).checked = true;
    document.querySelector(`[onclick="selectPaymentMethod('${method}')"]`).classList.add('selected');
    
    // Show payment details if needed
    const details = document.getElementById(method + '-details');
    if (details) {
        details.classList.add('active');
    }
    
    // Enable submit button
    document.getElementById('submitBtn').disabled = false;
    
    // Update button text
    const submitBtn = document.getElementById('submitBtn');
    if (method === 'cash') {
        submitBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Cash Payment';
    } else {
        submitBtn.innerHTML = '<i class="fas fa-lock"></i> Proceed to Payment';
    }
}

// Form validation
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    if (!selectedMethod) {
        e.preventDefault();
        alert('Please select a payment method.');
        return;
    }
    
    if (selectedMethod === 'telebirr') {
        const phoneNumber = document.querySelector('input[name="phone_number"]').value;
        if (!phoneNumber || phoneNumber.length < 10) {
            e.preventDefault();
            alert('Please enter a valid TeleBirr phone number (10 digits).');
            return;
        }
        // Validate Ethiopian phone number format
        if (!/^09[0-9]{8}$/.test(phoneNumber)) {
            e.preventDefault();
            alert('Please enter a valid Ethiopian phone number starting with 09.');
            return;
        }
    }
    
    if (selectedMethod === 'cbe') {
        const accountNumber = document.querySelector('input[name="account_number"]').value;
        if (!accountNumber || accountNumber.length < 10) {
            e.preventDefault();
            alert('Please enter a valid CBE account number (at least 10 digits).');
            return;
        }
        // Validate account number format (numbers only)
        if (!/^[0-9]+$/.test(accountNumber)) {
            e.preventDefault();
            alert('Account number should contain only numbers.');
            return;
        }
    }
});

// Handle "Not Now" button
function skipPaymentNow() {
    const confirmMessage = `Are you sure you want to skip payment now?\n\n` +
                          `⏰ You will have <?= PAYMENT_TIMEOUT_MINUTES / 60 ?> hours to complete payment\n` +
                          `❌ Your order will be automatically cancelled if not paid within this time\n` +
                          `💡 You can pay later from your orders page\n\n` +
                          `Click OK to skip payment, or Cancel to stay on this page.`;
    
    if (confirm(confirmMessage)) {
        // Create a hidden form to submit the skip payment request
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const skipInput = document.createElement('input');
        skipInput.type = 'hidden';
        skipInput.name = 'skip_payment';
        skipInput.value = '1';
        
        form.appendChild(skipInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

</body>
</html>