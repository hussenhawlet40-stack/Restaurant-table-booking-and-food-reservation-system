
<?php
session_start();
require_once "../connection.php";
require_once "../includes/payment_functions.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = "";

// Check for session message
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Handle delete pre-order request
if (isset($_GET['delete_preorder'])) {
    $preorder_id = intval($_GET['delete_preorder']);
    try {
        // Delete pre-order items first
        $stmt1 = $conn->prepare("DELETE FROM pre_order_items WHERE pre_order_id = ?");
        $stmt1->execute([$preorder_id]);
        
        // Delete payments related to this pre-order
        $stmt2 = $conn->prepare("DELETE FROM payments WHERE order_id = ? AND payment_type = 'order'");
        $stmt2->execute([$preorder_id]);
        
        // Delete the pre-order
        $stmt3 = $conn->prepare("DELETE FROM pre_orders WHERE id = ?");
        $stmt3->execute([$preorder_id]);
        
        $_SESSION['message'] = "Pre-order deleted successfully!";
        header("Location: view_preorders.php");
        exit;
    } catch (PDOException $e) {
        $message = "Error deleting pre-order: " . $e->getMessage();
    }
}

// Fetch all pre-orders with booking, table, user info, payment status, and timing
try {
    $stmt = $conn->query("
        SELECT po.id AS preorder_id, po.booking_id, 
               COALESCE(b.booking_datetime, CONCAT(b.booking_date, ' ', b.booking_time)) as booking_datetime,
               b.booking_date, b.booking_time, b.status as booking_status,
               u.name AS user_name, u.email AS user_email,
               t.table_label, t.location, t.capacity,
               po.total_amount, po.status as order_status, po.created_at,
               p.payment_reference, p.status as payment_status, p.payment_method, 
               p.expires_at as payment_expires_at, p.created_at as payment_created_at,
               ko.preparation_status, ko.chef_notes, ko.started_at as prep_started_at, ko.completed_at as prep_completed_at,
               chef.name as chef_name,
               TIMESTAMPDIFF(SECOND, po.created_at, NOW()) as order_age_seconds
        FROM pre_orders po
        JOIN bookings b ON po.booking_id = b.id
        JOIN users u ON b.user_id = u.id
        JOIN restaurant_tables t ON b.table_id = t.id
        LEFT JOIN payments p ON po.id = p.order_id AND p.payment_type = 'order'
        LEFT JOIN kitchen_orders ko ON po.id = ko.pre_order_id
        LEFT JOIN users chef ON ko.chef_id = chef.id
        ORDER BY po.created_at DESC, po.id DESC
    ");
    $pre_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback query if some columns don't exist
    try {
        $stmt = $conn->query("
            SELECT po.id AS preorder_id, po.booking_id, 
                   b.booking_date, b.booking_time, b.status as booking_status,
                   CONCAT(b.booking_date, ' ', b.booking_time) as booking_datetime,
                   u.name AS user_name, u.email AS user_email,
                   t.table_label, t.location, t.capacity,
                   po.total_amount, po.created_at,
                   'pending' as order_status,
                   NULL as payment_reference, NULL as payment_status, NULL as payment_method,
                   NULL as payment_expires_at, NULL as payment_created_at,
                   NULL as preparation_status, NULL as chef_notes, NULL as prep_started_at, NULL as prep_completed_at,
                   NULL as chef_name,
                   TIMESTAMPDIFF(SECOND, po.created_at, NOW()) as order_age_seconds
            FROM pre_orders po
            JOIN bookings b ON po.booking_id = b.id
            JOIN users u ON b.user_id = u.id
            JOIN restaurant_tables t ON b.table_id = t.id
            ORDER BY po.created_at DESC, po.id DESC
        ");
        $pre_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {
        $pre_orders = [];
        $message = "Error loading pre-orders: " . $e2->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>View Pre-Orders</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
}

header { 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px); 
    padding: 20px 30px; 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    box-shadow: 0 4px 20px rgba(0,0,0,0.1); 
    position: sticky; 
    top: 0; 
    z-index: 100; 
}
header h1 { 
    background: linear-gradient(45deg, #667eea, #764ba2); 
    -webkit-background-clip: text; 
    -webkit-text-fill-color: transparent; 
    font-size: 2rem; 
    display: flex; 
    align-items: center; 
    gap: 10px; 
}
.back-btn { 
    background: linear-gradient(45deg, #4ecdc4, #44a08d); 
    color: white; 
    padding: 12px 20px; 
    text-decoration: none; 
    border-radius: 25px; 
    font-weight: 500; 
    transition: 0.3s; 
    box-shadow: 0 4px 15px rgba(78,205,196,0.3); 
}
.back-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(78,205,196,0.4); }

.container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }

.message { 
    background: rgba(78,205,196,0.1); 
    color: #4ecdc4; 
    border: 2px solid rgba(78,205,196,0.3); 
    padding: 15px; 
    border-radius: 15px; 
    margin-bottom: 30px; 
    text-align: center; 
    font-weight: 500; 
    backdrop-filter: blur(10px); 
}

.preorders-grid { 
    display: grid; 
    gap: 30px; 
}

.preorder-card { 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px); 
    border-radius: 20px; 
    padding: 30px; 
    box-shadow: 0 10px 40px rgba(0,0,0,0.1); 
    transition: all 0.3s ease; 
    border: 1px solid rgba(255,255,255,0.2); 
}
.preorder-card:hover { 
    transform: translateY(-5px); 
    box-shadow: 0 20px 60px rgba(0,0,0,0.15); 
}

.preorder-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: 25px; 
    padding-bottom: 20px; 
    border-bottom: 2px solid rgba(102,126,234,0.1); 
    flex-wrap: wrap; 
    gap: 15px; 
}
.preorder-info { 
    display: flex; 
    flex-wrap: wrap; 
    gap: 20px; 
    align-items: center; 
}
.info-item { 
    display: flex; 
    align-items: center; 
    gap: 8px; 
    color: #666; 
    font-weight: 500; 
}
.info-item i { color: #667eea; }
.info-value { color: #333; font-weight: 600; }

.delete-btn { 
    background: linear-gradient(45deg, #f44336, #d32f2f); 
    color: white; 
    padding: 10px 20px; 
    text-decoration: none; 
    border-radius: 25px; 
    font-weight: 500; 
    transition: 0.3s; 
    box-shadow: 0 4px 15px rgba(244,67,54,0.3); 
    display: flex; 
    align-items: center; 
    gap: 8px; 
}
.delete-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(244,67,54,0.4); }

.items-table { 
    width: 100%; 
    border-collapse: collapse; 
    margin-top: 20px; 
    border-radius: 15px; 
    overflow: hidden; 
    box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
}
.items-table th { 
    background: linear-gradient(45deg, #667eea, #764ba2); 
    color: white; 
    padding: 15px; 
    text-align: left; 
    font-weight: 600; 
}
.items-table td { 
    padding: 15px; 
    border-bottom: 1px solid rgba(0,0,0,0.05); 
    background: rgba(255,255,255,0.8); 
}
.items-table tr:hover td { background: rgba(102,126,234,0.05); }

.food-img { 
    width: 60px; 
    height: 60px; 
    object-fit: cover; 
    border-radius: 10px; 
    box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
    transition: transform 0.3s ease;
}
.food-img:hover {
    transform: scale(1.1);
}
.no-image { 
    width: 60px; 
    height: 60px; 
    background: linear-gradient(45deg, #f0f0f0, #e0e0e0); 
    border-radius: 10px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    color: #999; 
    font-size: 1.2rem; 
}

.quantity-badge {
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.9rem;
}

/* Status Badges */
.status-badge {
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

/* Order Status Badges */
.order-status-pending { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
.order-status-pending_payment { background: #ffeaa7; color: #856404; border: 1px solid #fdcb6e; }
.order-status-confirmed { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.order-status-preparing { background: #cce5ff; color: #004085; border: 1px solid #b3d7ff; }
.order-status-ready { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
.order-status-cancelled { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

/* Payment Status Badges */
.payment-status-pending { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
.payment-status-completed { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.payment-status-failed { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.payment-status-cancelled { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.payment-status-expired { background: #e2e3e5; color: #6c757d; border: 1px solid #d6d8db; }

/* Kitchen Status Badges */
.kitchen-status-pending { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
.kitchen-status-in_progress { background: #cce5ff; color: #004085; border: 1px solid #b3d7ff; }
.kitchen-status-ready { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
.kitchen-status-served { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

/* Status Section Styling */
.status-section {
    background: rgba(102,126,234,0.05);
    padding: 20px;
    border-radius: 15px;
    margin: 20px 0;
    border: 1px solid rgba(102,126,234,0.1);
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    background: rgba(255,255,255,0.8);
    border-radius: 10px;
    border: 1px solid rgba(0,0,0,0.05);
}

.status-label {
    font-weight: 600;
    color: #666;
    display: flex;
    align-items: center;
    gap: 8px;
}

.timing-info {
    background: rgba(255,193,7,0.1);
    padding: 15px;
    border-radius: 10px;
    margin: 15px 0;
    border: 1px solid rgba(255,193,7,0.2);
}

.timing-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.timing-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: rgba(255,255,255,0.7);
    border-radius: 8px;
    font-size: 0.9rem;
}

.expired-warning {
    background: linear-gradient(45deg, #f8d7da, #fab1a0);
    color: #721c24;
    padding: 10px 15px;
    border-radius: 10px;
    margin: 10px 0;
    border: 2px solid #dc3545;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.countdown-timer {
    font-family: 'Courier New', monospace;
    font-weight: bold;
    color: #667eea;
}

.total-row { 
    background: linear-gradient(45deg, rgba(102,126,234,0.1), rgba(118,75,162,0.1)) !important; 
    font-weight: 600; 
}
.total-row td { 
    border-bottom: none; 
    font-size: 1.1rem; 
    padding: 20px 15px;
}

.no-items { 
    text-align: center; 
    padding: 40px; 
    color: #666; 
    font-style: italic; 
    background: rgba(255,193,7,0.1);
    border-radius: 15px;
    border: 2px dashed rgba(255,193,7,0.3);
}
.no-items i {
    font-size: 2rem;
    margin-bottom: 10px;
    color: #ffc107;
}

.no-preorders { 
    text-align: center; 
    padding: 80px 40px; 
    background: rgba(255,255,255,0.95); 
    border-radius: 20px; 
    backdrop-filter: blur(15px); 
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}
.no-preorders i { 
    font-size: 4rem; 
    color: #ccc; 
    margin-bottom: 20px; 
}
.no-preorders h3 { 
    color: #666; 
    margin-bottom: 10px; 
    font-size: 1.5rem;
}
.no-preorders p { 
    color: #999; 
    font-size: 1.1rem;
}

/* Enhanced responsive design */
@media (max-width: 768px) { 
    header { 
        flex-direction: column; 
        text-align: center; 
        gap: 15px; 
        padding: 15px 20px;
    }
    header h1 {
        font-size: 1.5rem;
    }
    .preorder-header { 
        flex-direction: column; 
        align-items: flex-start; 
    }
    .preorder-info { 
        flex-direction: column; 
        align-items: flex-start; 
        gap: 10px; 
        width: 100%;
    }
    .info-item {
        width: 100%;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    .delete-btn {
        width: 100%;
        justify-content: center;
        margin-top: 15px;
    }
    .items-table { 
        font-size: 0.9rem; 
    }
    .items-table th, .items-table td { 
        padding: 10px 8px; 
    }
    .food-img, .no-image { 
        width: 50px; 
        height: 50px; 
    }
    .container {
        padding: 0 15px;
    }
    .preorder-card {
        padding: 20px;
    }
    .status-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    .timing-grid {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .status-badge {
        font-size: 0.75rem;
        padding: 4px 8px;
    }
}

@media (max-width: 480px) {
    .items-table th, .items-table td {
        padding: 8px 4px;
        font-size: 0.8rem;
    }
    .food-img, .no-image {
        width: 40px;
        height: 40px;
    }
    .quantity-badge {
        padding: 2px 6px;
        font-size: 0.8rem;
    }
    .status-section, .timing-info {
        padding: 15px;
    }
    .status-item, .timing-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
}
</style>
</head>
<body>

<header>
    <h1><i class="fas fa-shopping-cart"></i> Pre-Orders Management</h1>
    <a href="dashboard.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</header>

<div class="container">
    <?php if (!empty($message)): ?>
        <div class="message">
            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
        </div>
    <?php endif; ?>

<div class="preorders-grid">
        <?php if (empty($pre_orders)): ?>
            <div class="no-preorders">
                <i class="fas fa-shopping-cart"></i>
                <h3>No Pre-Orders Found</h3>
                <p>There are currently no pre-orders in the system.</p>
            </div>
        <?php else: ?>
            <?php foreach($pre_orders as $po): ?>
                <div class="preorder-card">
                    <div class="preorder-header">
                        <div class="preorder-info">
                            <div class="info-item">
                                <i class="fas fa-hashtag"></i>
                                <span>Pre-Order ID:</span>
                                <span class="info-value">#<?php echo $po['preorder_id']; ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-receipt"></i>
                                <span>Booking:</span>
                                <span class="info-value">#<?php echo $po['booking_id']; ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-user"></i>
                                <span>Customer:</span>
                                <span class="info-value"><?php echo htmlspecialchars($po['user_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-table"></i>
                                <span>Table:</span>
                                <span class="info-value">
                                    <?php echo htmlspecialchars($po['table_label']); ?>
                                    (<?php echo $po['capacity']; ?> seats)
                                </span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>Location:</span>
                                <span class="info-value"><?php echo htmlspecialchars($po['location']); ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-calendar"></i>
                                <span>Booking Date:</span>
                                <span class="info-value">
                                    <?php 
                                    if (!empty($po['booking_datetime'])) {
                                        echo date('M d, Y g:i A', strtotime($po['booking_datetime']));
                                    } else {
                                        echo date('M d, Y', strtotime($po['booking_date'])) . ' at ' . date('g:i A', strtotime($po['booking_time']));
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-shopping-cart"></i>
                                <span>Ordered On:</span>
                                <span class="info-value"><?php echo date('M d, Y g:i A', strtotime($po['created_at'])); ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-money-bill-wave"></i>
                                <span>Total Amount:</span>
                                <span class="info-value" style="color: #4caf50; font-weight: 700;">
                                    ETB <?php echo number_format($po['total_amount'], 2); ?>
                                </span>
                            </div>
                        </div>
                        <a class="delete-btn" href="?delete_preorder=<?php echo $po['preorder_id']; ?>" 
                           onclick="return confirm('⚠️ Delete this pre-order permanently?\n\nThis will remove:\n• Pre-Order #<?php echo $po['preorder_id']; ?>\n• All ordered items\n• Payment information\n\nThis action cannot be undone!')">
                            <i class="fas fa-trash"></i> Delete Order
                        </a>
                    </div>

                    <!-- Comprehensive Status Section -->
                    <div class="status-section">
                        <h4 style="color: #667eea; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-info-circle"></i> Order Status Overview
                        </h4>
                        
                        <div class="status-grid">
                            <!-- Order Status -->
                            <div class="status-item">
                                <span class="status-label">
                                    <i class="fas fa-shopping-cart"></i> Order Status:
                                </span>
                                <span class="status-badge order-status-<?php echo $po['order_status']; ?>">
                                    <i class="fas fa-<?php 
                                        echo match($po['order_status']) {
                                            'pending' => 'clock',
                                            'pending_payment' => 'credit-card',
                                            'confirmed' => 'check-circle',
                                            'preparing' => 'fire',
                                            'ready' => 'bell',
                                            'cancelled' => 'times-circle',
                                            default => 'question-circle'
                                        };
                                    ?>"></i>
                                    <?php 
                                    echo match($po['order_status']) {
                                        'pending' => 'Pending',
                                        'pending_payment' => 'Awaiting Payment',
                                        'confirmed' => 'Confirmed',
                                        'preparing' => 'Preparing',
                                        'ready' => 'Ready',
                                        'cancelled' => 'Cancelled',
                                        default => ucfirst($po['order_status'])
                                    };
                                    ?>
                                </span>
                            </div>

                            <!-- Payment Status -->
                            <div class="status-item">
                                <span class="status-label">
                                    <i class="fas fa-credit-card"></i> Payment:
                                </span>
                                <?php if ($po['total_amount'] == 0): ?>
                                    <span class="status-badge payment-status-completed">
                                        <i class="fas fa-gift"></i> Free Order
                                    </span>
                                <?php elseif ($po['payment_status']): ?>
                                    <span class="status-badge payment-status-<?php echo $po['payment_status']; ?>">
                                        <i class="fas fa-<?php 
                                            echo match($po['payment_status']) {
                                                'pending' => 'clock',
                                                'completed' => 'check-circle',
                                                'failed' => 'times-circle',
                                                'cancelled' => 'ban',
                                                'expired' => 'hourglass-end',
                                                default => 'question-circle'
                                            };
                                        ?>"></i>
                                        <?php echo ucfirst($po['payment_status']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge payment-status-pending">
                                        <i class="fas fa-exclamation-triangle"></i> No Payment
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Kitchen Status -->
                            <div class="status-item">
                                <span class="status-label">
                                    <i class="fas fa-fire"></i> Kitchen:
                                </span>
                                <?php if ($po['preparation_status']): ?>
                                    <span class="status-badge kitchen-status-<?php echo $po['preparation_status']; ?>">
                                        <i class="fas fa-<?php 
                                            echo match($po['preparation_status']) {
                                                'pending' => 'clock',
                                                'in_progress' => 'fire',
                                                'ready' => 'bell',
                                                'served' => 'check-circle',
                                                default => 'question-circle'
                                            };
                                        ?>"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $po['preparation_status'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge kitchen-status-pending">
                                        <i class="fas fa-clock"></i> Not Started
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Booking Status -->
                            <div class="status-item">
                                <span class="status-label">
                                    <i class="fas fa-calendar-check"></i> Booking:
                                </span>
                                <span class="status-badge order-status-<?php echo $po['booking_status']; ?>">
                                    <i class="fas fa-<?php 
                                        echo match($po['booking_status']) {
                                            'pending' => 'clock',
                                            'confirmed' => 'check-circle',
                                            'cancelled' => 'times-circle',
                                            'completed' => 'flag-checkered',
                                            default => 'question-circle'
                                        };
                                    ?>"></i>
                                    <?php echo ucfirst($po['booking_status']); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Payment Details -->
                        <?php if ($po['payment_reference']): ?>
                            <div style="margin-top: 15px; padding: 10px; background: rgba(255,255,255,0.7); border-radius: 8px;">
                                <strong style="color: #667eea;">Payment Details:</strong><br>
                                <small>
                                    Reference: <?php echo htmlspecialchars($po['payment_reference']); ?><br>
                                    <?php if ($po['payment_method']): ?>
                                        Method: <?php echo ucfirst($po['payment_method']); ?><br>
                                    <?php endif; ?>
                                    <?php if ($po['payment_created_at']): ?>
                                        Created: <?php echo date('M d, Y g:i A', strtotime($po['payment_created_at'])); ?><br>
                                    <?php endif; ?>
                                    <?php if ($po['payment_expires_at'] && $po['payment_status'] === 'pending'): ?>
                                        Expires: <?php echo date('M d, Y g:i A', strtotime($po['payment_expires_at'])); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endif; ?>

                        <!-- Kitchen Details -->
                        <?php if ($po['chef_name'] || $po['prep_started_at'] || $po['chef_notes']): ?>
                            <div style="margin-top: 15px; padding: 10px; background: rgba(255,255,255,0.7); border-radius: 8px;">
                                <strong style="color: #667eea;">Kitchen Details:</strong><br>
                                <small>
                                    <?php if ($po['chef_name']): ?>
                                        Chef: <?php echo htmlspecialchars($po['chef_name']); ?><br>
                                    <?php endif; ?>
                                    <?php if ($po['prep_started_at']): ?>
                                        Started: <?php echo date('M d, Y g:i A', strtotime($po['prep_started_at'])); ?><br>
                                    <?php endif; ?>
                                    <?php if ($po['prep_completed_at']): ?>
                                        Completed: <?php echo date('M d, Y g:i A', strtotime($po['prep_completed_at'])); ?><br>
                                    <?php endif; ?>
                                    <?php if ($po['chef_notes']): ?>
                                        Notes: <?php echo htmlspecialchars($po['chef_notes']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Timing Information -->
                    <div class="timing-info">
                        <h4 style="color: #856404; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-clock"></i> Timing Information
                        </h4>
                        
                        <div class="timing-grid">
                            <div class="timing-item">
                                <span><strong>Order Age:</strong></span>
                                <span>
                                    <?php 
                                    $hours = floor($po['order_age_seconds'] / 3600);
                                    $minutes = floor(($po['order_age_seconds'] % 3600) / 60);
                                    if ($hours > 0) {
                                        echo $hours . 'h ' . $minutes . 'm';
                                    } else {
                                        echo $minutes . 'm';
                                    }
                                    ?>
                                </span>
                            </div>
                            
                            <?php if ($po['order_status'] === 'pending_payment' && $po['total_amount'] > 0): ?>
                                <?php 
                                $order_time_info = getExactRemainingOrderTime($po['created_at']);
                                ?>
                                <div class="timing-item">
                                    <span><strong>Payment Deadline:</strong></span>
                                    <span class="countdown-timer">
                                        <?php if ($order_time_info['expired']): ?>
                                            <span style="color: #dc3545;">EXPIRED</span>
                                        <?php else: ?>
                                            <?php echo $order_time_info['formatted_time']; ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <?php if ($order_time_info['expired']): ?>
                                    <div class="expired-warning" style="grid-column: 1 / -1;">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <strong>Order Expired - Should be cancelled automatically</strong>
                                    </div>
                                <?php elseif ($order_time_info['is_critical']): ?>
                                    <div style="grid-column: 1 / -1; background: #fff3cd; padding: 8px 12px; border-radius: 8px; color: #856404; font-weight: 600;">
                                        <i class="fas fa-hourglass-half"></i>
                                        Payment deadline approaching - Order will expire soon!
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php
                    // Fetch items for this pre-order with enhanced error handling
                    try {
                        $stmt_items = $conn->prepare("
                            SELECT poi.quantity, poi.price as item_price,
                                   mi.name AS food_name, mi.price as menu_price, mi.image, mi.category
                            FROM pre_order_items poi
                            JOIN menu_items mi ON poi.menu_item_id = mi.id
                            WHERE poi.pre_order_id = ?
                            ORDER BY mi.category, mi.name
                        ");
                        $stmt_items->execute([$po['preorder_id']]);
                        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        // Fallback query if poi.price doesn't exist
                        try {
                            $stmt_items = $conn->prepare("
                                SELECT poi.quantity, mi.price as item_price,
                                       mi.name AS food_name, mi.price as menu_price, mi.image, mi.category
                                FROM pre_order_items poi
                                JOIN menu_items mi ON poi.menu_item_id = mi.id
                                WHERE poi.pre_order_id = ?
                                ORDER BY mi.category, mi.name
                            ");
                            $stmt_items->execute([$po['preorder_id']]);
                            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e2) {
                            $items = [];
                        }
                    }
                    ?>

                    <?php if(count($items) > 0): ?>
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-image"></i> Image</th>
                                    <th><i class="fas fa-utensils"></i> Item</th>
                                    <th><i class="fas fa-sort-numeric-up"></i> Qty</th>
                                    <th><i class="fas fa-coins"></i> Price (ETB)</th>
                                    <th><i class="fas fa-calculator"></i> Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $calculated_total = 0;
                                foreach($items as $item):
                                    // Use item_price if available, otherwise use menu_price
                                    $price = isset($item['item_price']) ? $item['item_price'] : $item['menu_price'];
                                    $subtotal = $item['quantity'] * $price;
                                    $calculated_total += $subtotal;
                                ?>
                                <tr>
                                    <td>
                                        <?php 
                                        $image_found = false;
                                        $image_paths = [
                                            "../uploads/menu_items/" . $item['image'],
                                            "../src/menu/menu_items/" . $item['image'],
                                            "../uploads/menu/" . $item['image']
                                        ];
                                        
                                        foreach ($image_paths as $path) {
                                            if ($item['image'] && file_exists($path)) {
                                                echo '<img src="' . $path . '" class="food-img" alt="' . htmlspecialchars($item['food_name']) . '">';
                                                $image_found = true;
                                                break;
                                            }
                                        }
                                        
                                        if (!$image_found):
                                        ?>
                                            <div class="no-image">
                                                <i class="fas fa-utensils"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['food_name']); ?></strong>
                                        <?php if (isset($item['category'])): ?>
                                            <br><small style="color: #666;"><?php echo htmlspecialchars($item['category']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="quantity-badge"><?php echo $item['quantity']; ?></span></td>
                                    <td>ETB <?php echo number_format($price, 2); ?></td>
                                    <td><strong>ETB <?php echo number_format($subtotal, 2); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="4">
                                        <strong><i class="fas fa-receipt"></i> Total Amount</strong>
                                    </td>
                                    <td>
                                        <strong>ETB <?php echo number_format($po['total_amount'], 2); ?></strong>
                                        <?php if (abs($calculated_total - $po['total_amount']) > 0.01): ?>
                                            <br><small style="color: #666;">(Calculated: ETB <?php echo number_format($calculated_total, 2); ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-items">
                            <i class="fas fa-exclamation-circle"></i>
                            No food items pre-ordered for this booking.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>