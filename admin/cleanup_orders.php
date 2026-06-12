<?php
/**
 * Admin Manual Cleanup Tool
 * 
 * This page allows administrators to manually trigger cleanup of expired orders
 * and view cleanup statistics.
 */

session_start();
require_once "../connection.php";
require_once "../includes/payment_functions.php";

// Only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

$message = "";
$cleanup_results = null;

// Handle manual cleanup trigger
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_cleanup'])) {
    try {
        $start_time = microtime(true);
        
        // Run cleanup functions
        $expired_payments = cancelExpiredPayments($conn);
        $expired_orders = autoCleanupExpiredOrders($conn);
        
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        $total_cleaned = $expired_payments + $expired_orders;
        
        $cleanup_results = [
            'expired_payments' => $expired_payments,
            'expired_orders' => $expired_orders,
            'total_cleaned' => $total_cleaned,
            'execution_time' => $execution_time
        ];
        
        if ($total_cleaned > 0) {
            $message = "Cleanup completed successfully! Processed {$total_cleaned} expired items in {$execution_time}ms.";
        } else {
            $message = "Cleanup completed - no expired items found (execution time: {$execution_time}ms).";
        }
        
    } catch (Exception $e) {
        $message = "Cleanup failed: " . $e->getMessage();
    }
}

// Get current statistics
try {
    // Count pending unpaid orders
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM pre_orders po
        JOIN bookings b ON po.booking_id = b.id
        LEFT JOIN payments p ON po.id = p.order_id AND p.payment_type = 'order' AND p.status = 'completed'
        WHERE po.status IN ('pending', 'pending_payment')
        AND po.total_amount > 0
        AND p.id IS NULL
    ");
    $stmt->execute();
    $pending_unpaid_orders = $stmt->fetchColumn();
    
    // Count orders that will expire soon (within 1 hour)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM pre_orders po
        JOIN bookings b ON po.booking_id = b.id
        LEFT JOIN payments p ON po.id = p.order_id AND p.payment_type = 'order' AND p.status = 'completed'
        WHERE po.status IN ('pending', 'pending_payment')
        AND po.total_amount > 0
        AND p.id IS NULL
        AND TIMESTAMPDIFF(SECOND, po.created_at, NOW()) > 7200
        AND TIMESTAMPDIFF(SECOND, po.created_at, NOW()) <= 10800
    ");
    $stmt->execute();
    $expiring_soon_orders = $stmt->fetchColumn();
    
    // Count already expired orders (should be 0 if cleanup is working)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM pre_orders po
        JOIN bookings b ON po.booking_id = b.id
        LEFT JOIN payments p ON po.id = p.order_id AND p.payment_type = 'order' AND p.status = 'completed'
        WHERE po.status IN ('pending', 'pending_payment')
        AND po.total_amount > 0
        AND p.id IS NULL
        AND TIMESTAMPDIFF(SECOND, po.created_at, NOW()) > 10800
    ");
    $stmt->execute();
    $expired_orders = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $pending_unpaid_orders = 0;
    $expiring_soon_orders = 0;
    $expired_orders = 0;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Order Cleanup Management</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: Arial; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; padding: 20px; }
header { background: rgba(255,255,255,0.95); backdrop-filter: blur(15px); padding: 20px; border-radius: 15px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
header h1 { background: linear-gradient(45deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 2rem; }
.back-btn { background: linear-gradient(45deg, #4ecdc4, #44a08d); color: white; padding: 12px 20px; text-decoration: none; border-radius: 25px; transition: 0.3s; }
.back-btn:hover { transform: translateY(-2px); }
.container { max-width: 1000px; margin: 0 auto; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
.stat-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(15px); padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
.stat-number { font-size: 2.5rem; font-weight: bold; margin-bottom: 10px; }
.stat-label { color: #666; font-size: 1rem; }
.stat-pending { color: #ffa726; }
.stat-expiring { color: #ff6b6b; }
.stat-expired { color: #dc3545; }
.cleanup-section { background: rgba(255,255,255,0.95); backdrop-filter: blur(15px); padding: 30px; border-radius: 15px; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
.cleanup-title { font-size: 1.5rem; color: #333; margin-bottom: 20px; text-align: center; }
.cleanup-form { text-align: center; margin-bottom: 20px; }
.btn { background: linear-gradient(45deg, #667eea, #764ba2); color: white; padding: 15px 30px; border: none; border-radius: 25px; cursor: pointer; font-size: 16px; font-weight: 500; transition: 0.3s; }
.btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
.btn-danger { background: linear-gradient(45deg, #ff6b6b, #ee5a24); }
.message { padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; font-weight: 500; background: rgba(78,205,196,0.1); color: #4ecdc4; border: 2px solid rgba(78,205,196,0.3); }
.results-section { background: rgba(255,255,255,0.95); backdrop-filter: blur(15px); padding: 25px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
.results-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
.result-item { text-align: center; padding: 15px; background: rgba(102,126,234,0.1); border-radius: 10px; }
.result-number { font-size: 1.5rem; font-weight: bold; color: #667eea; }
.result-label { color: #666; font-size: 0.9rem; }
.info-section { background: rgba(255,255,255,0.95); backdrop-filter: blur(15px); padding: 25px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
.info-title { font-size: 1.3rem; color: #333; margin-bottom: 15px; }
.info-text { color: #666; line-height: 1.6; margin-bottom: 10px; }
</style>
</head>
<body>
<header>
    <h1><i class="fas fa-broom"></i> Order Cleanup Management</h1>
    <a href="dashboard.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</header>

<div class="container">
    <?php if ($message): ?>
        <div class="message"><i class="fas fa-info-circle"></i> <?= $message ?></div>
    <?php endif; ?>
    
    <!-- Current Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number stat-pending"><?= $pending_unpaid_orders ?></div>
            <div class="stat-label">Pending Unpaid Orders</div>
        </div>
        <div class="stat-card">
            <div class="stat-number stat-expiring"><?= $expiring_soon_orders ?></div>
            <div class="stat-label">Expiring Soon (< 1 hour)</div>
        </div>
        <div class="stat-card">
            <div class="stat-number stat-expired"><?= $expired_orders ?></div>
            <div class="stat-label">Expired Orders (Need Cleanup)</div>
        </div>
    </div>
    
    <!-- Manual Cleanup -->
    <div class="cleanup-section">
        <h2 class="cleanup-title">Manual Cleanup</h2>
        <div class="cleanup-form">
            <form method="POST">
                <button type="submit" name="run_cleanup" class="btn btn-danger">
                    <i class="fas fa-broom"></i> Run Cleanup Now
                </button>
            </form>
        </div>
        <p style="text-align: center; color: #666; font-size: 0.9rem;">
            This will cancel all orders that are unpaid after 3 hours and free up table reservations.
        </p>
    </div>
    
    <!-- Cleanup Results -->
    <?php if ($cleanup_results): ?>
    <div class="results-section">
        <h2 class="cleanup-title">Cleanup Results</h2>
        <div class="results-grid">
            <div class="result-item">
                <div class="result-number"><?= $cleanup_results['expired_payments'] ?></div>
                <div class="result-label">Expired Payments</div>
            </div>
            <div class="result-item">
                <div class="result-number"><?= $cleanup_results['expired_orders'] ?></div>
                <div class="result-label">Expired Orders</div>
            </div>
            <div class="result-item">
                <div class="result-number"><?= $cleanup_results['total_cleaned'] ?></div>
                <div class="result-label">Total Cleaned</div>
            </div>
            <div class="result-item">
                <div class="result-number"><?= $cleanup_results['execution_time'] ?>ms</div>
                <div class="result-label">Execution Time</div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Information -->
    <div class="info-section">
        <h2 class="info-title">Automatic Cleanup Information</h2>
        <div class="info-text">
            <strong>How it works:</strong>
        </div>
        <div class="info-text">
            • Orders have a 3-hour payment window from creation time
        </div>
        <div class="info-text">
            • After 3 hours without payment, orders and table reservations are automatically cancelled
        </div>
        <div class="info-text">
            • Cleanup runs automatically when users visit the "My Orders" page
        </div>
        <div class="info-text">
            • For best results, set up a cron job to run cleanup every 5-10 minutes
        </div>
        <div class="info-text">
            <strong>Cron job command:</strong><br>
            <code>*/5 * * * * /usr/bin/php <?= __DIR__ ?>/../cron/cleanup_expired_orders.php</code>
        </div>
    </div>
</div>

<script>
// Auto-refresh page every 30 seconds to show updated statistics
setTimeout(function() {
    location.reload();
}, 30000);
</script>

</body>
</html>