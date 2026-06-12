<?php
session_start();
require_once "../connection.php";
require_once "../includes/payment_functions.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Customer';

// Get user payments
$payments = getUserPayments($conn, $user_id, 20);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment History - Ethiopian Restaurant</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100vh; }
        
        header { background: rgba(255,255,255,0.95); padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        nav a { color: #333; text-decoration: none; margin: 0 10px; padding: 8px 15px; border-radius: 20px; transition: 0.3s; }
        nav a:hover { background: #667eea; color: white; }
        .logout-btn { background: #ff6b6b; color: white !important; }
        
        .container { max-width: 1000px; margin: 50px auto; background: white; padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .title { text-align: center; color: #333; margin-bottom: 30px; }
        
        .payments-grid { display: grid; gap: 20px; }
        .payment-card { border: 1px solid #ddd; border-radius: 15px; padding: 20px; transition: 0.3s; }
        .payment-card:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        
        .payment-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .payment-ref { font-weight: bold; color: #333; }
        .payment-date { color: #666; font-size: 0.9rem; }
        
        .payment-details { margin-bottom: 15px; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .detail-label { color: #666; }
        .detail-value { font-weight: 500; }
        
        .payment-footer { display: flex; justify-content: space-between; align-items: center; }
        .payment-amount { font-size: 1.2rem; font-weight: bold; color: #667eea; }
        
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-expired { background: #e2e3e5; color: #495057; }
        
        .method-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; margin-left: 10px; }
        .method-telebirr { background: rgba(255, 107, 53, 0.1); color: #ff6b35; }
        .method-cbe { background: rgba(44, 90, 160, 0.1); color: #2c5aa0; }
        .method-cash { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        
        .empty-state { text-align: center; padding: 60px 20px; color: #666; }
        .empty-state i { font-size: 4rem; margin-bottom: 20px; color: #ddd; }
        
        .back-link { display: inline-block; margin-top: 20px; color: #667eea; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        
        @media (max-width: 768px) {
            .container { margin: 20px; padding: 20px; }
            .payment-header, .payment-footer { flex-direction: column; gap: 10px; text-align: center; }
            .detail-row { flex-direction: column; gap: 2px; }
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
        <i class="fas fa-history"></i>
        Payment History
    </h1>
    
    <?php if (empty($payments)): ?>
        <div class="empty-state">
            <i class="fas fa-receipt"></i>
            <h3>No Payment History</h3>
            <p>You haven't made any payments yet.</p>
            <p>When you place orders or book tables with payment, they will appear here.</p>
        </div>
    <?php else: ?>
        <div class="payments-grid">
            <?php foreach ($payments as $payment): 
                $status_info = formatPaymentStatus($payment['status']);
                $method_info = formatPaymentMethod($payment['payment_method']);
            ?>
                <div class="payment-card">
                    <div class="payment-header">
                        <div class="payment-ref"><?= htmlspecialchars($payment['payment_reference']) ?></div>
                        <div class="payment-date"><?= date('M j, Y g:i A', strtotime($payment['created_at'])) ?></div>
                    </div>
                    
                    <div class="payment-details">
                        <div class="detail-row">
                            <span class="detail-label">Type:</span>
                            <span class="detail-value"><?= htmlspecialchars($payment['payment_for']) ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Payment Method:</span>
                            <span class="detail-value">
                                <i class="<?= $method_info['icon'] ?>"></i> 
                                <?= $method_info['text'] ?>
                            </span>
                        </div>
                        
                        <?php if ($payment['transaction_id']): ?>
                        <div class="detail-row">
                            <span class="detail-label">Transaction ID:</span>
                            <span class="detail-value"><?= htmlspecialchars($payment['transaction_id']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="payment-footer">
                        <div class="payment-amount">ETB <?= number_format($payment['amount'], 2) ?></div>
                        <div>
                            <span class="status-badge <?= $status_info['class'] ?>">
                                <i class="<?= $status_info['icon'] ?>"></i> 
                                <?= $status_info['text'] ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
</div>

</body>
</html>