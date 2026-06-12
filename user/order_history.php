<?php
session_start();
require_once "../connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? "Customer";

// Get complete history with statistics
$stmt = $conn->prepare("
    SELECT 
        b.id as booking_id,
        b.booking_datetime,
        b.meal_type,
        b.guests,
        b.status as booking_status,
        b.created_at as booking_created,
        rt.table_label,
        po.id as order_id,
        po.total_amount,
        po.status as order_status,
        po.created_at as order_created,
        GROUP_CONCAT(CONCAT(mi.name, ' (', poi.quantity, 'x)') SEPARATOR ', ') as items
    FROM bookings b
    LEFT JOIN restaurant_tables rt ON b.table_id = rt.id
    LEFT JOIN pre_orders po ON b.id = po.booking_id
    LEFT JOIN pre_order_items poi ON po.id = poi.pre_order_id
    LEFT JOIN menu_items mi ON poi.menu_item_id = mi.id
    WHERE b.user_id = ?
    GROUP BY b.id, po.id
    ORDER BY b.booking_datetime DESC
");
$stmt->execute([$user_id]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_bookings = 0;
$total_orders = 0;
$total_spent = 0;
$favorite_meal = [];

foreach ($history as $record) {
    if ($record['booking_id']) {
        $total_bookings++;
        if (isset($favorite_meal[$record['meal_type']])) {
            $favorite_meal[$record['meal_type']]++;
        } else {
            $favorite_meal[$record['meal_type']] = 1;
        }
    }
    if ($record['order_id']) {
        $total_orders++;
        $total_spent += $record['total_amount'];
    }
}

$most_frequent_meal = !empty($favorite_meal) ? array_keys($favorite_meal, max($favorite_meal))[0] : 'None';
?>
<!DOCTYPE html>
<html>
<head>
<title>Order History</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: Arial; background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100vh; }
header { background: rgba(255,255,255,0.95); backdrop-filter: blur(15px); padding: 20px; display: flex; justify-content: space-between; align-items: center; }
header h2 { background: linear-gradient(45deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
nav a { color: #333; text-decoration: none; margin: 0 10px; padding: 8px 15px; border-radius: 20px; transition: 0.3s; }
nav a:hover { background: #667eea; color: white; }
.logout-btn { background: #ff6b6b; color: white !important; }
.container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
.page-title { text-align: center; color: white; font-size: 2.5rem; margin-bottom: 30px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
.stat-card { background: rgba(255,255,255,0.95); border-radius: 15px; padding: 20px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
.stat-icon { font-size: 2.5rem; margin-bottom: 10px; background: linear-gradient(45deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.stat-number { font-size: 2rem; font-weight: bold; color: #333; margin-bottom: 5px; }
.stat-label { color: #666; font-size: 0.9rem; }
.history-timeline { background: rgba(255,255,255,0.95); border-radius: 15px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
.timeline-title { font-size: 1.5rem; font-weight: bold; color: #333; margin-bottom: 25px; text-align: center; }
.timeline-item { display: flex; margin-bottom: 25px; position: relative; }
.timeline-item:before { content: ''; position: absolute; left: 20px; top: 40px; bottom: -25px; width: 2px; background: #e9ecef; }
.timeline-item:last-child:before { display: none; }
.timeline-icon { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(45deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; margin-right: 20px; flex-shrink: 0; z-index: 2; position: relative; }
.timeline-content { flex: 1; background: #f8f9fa; border-radius: 10px; padding: 20px; }
.timeline-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.timeline-date { font-weight: bold; color: #333; }
.timeline-status { padding: 4px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: 500; }
.status-confirmed { background: #d4edda; color: #155724; }
.status-pending { background: #fff3cd; color: #856404; }
.status-cancelled { background: #f8d7da; color: #721c24; }
.timeline-details { margin-bottom: 10px; }
.detail-item { margin-bottom: 8px; color: #666; }
.detail-label { font-weight: 500; }
.order-summary { background: white; border-radius: 8px; padding: 15px; margin-top: 10px; border-left: 4px solid #667eea; }
.order-items { color: #666; margin-bottom: 10px; }
.order-total { font-weight: bold; color: #667eea; }
.no-history { text-align: center; padding: 60px 20px; background: rgba(255,255,255,0.95); border-radius: 15px; }
.no-history i { font-size: 4rem; color: #ddd; margin-bottom: 20px; }
.start-btn { display: inline-block; background: linear-gradient(45deg, #667eea, #764ba2); color: white; padding: 15px 30px; border-radius: 25px; text-decoration: none; margin-top: 20px; transition: 0.3s; }
@media (max-width: 768px) { 
    .stats-grid { grid-template-columns: repeat(2, 1fr); } 
    .timeline-item { flex-direction: column; }
    .timeline-icon { margin-bottom: 10px; }
    nav { flex-direction: column; gap: 10px; }
    header { flex-direction: column; text-align: center; }
}
</style>
</head>
<body>
<header>
    <h2><i class="fas fa-history"></i> Order History</h2>
    <nav>
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="my_bookings.php"><i class="fas fa-calendar-check"></i> Bookings</a>
        <a href="my_orders.php"><i class="fas fa-receipt"></i> Orders</a>
        <a href="View_Tables.php"><i class="fas fa-chair"></i> Tables</a>
        <a href="write_comment.php"><i class="fas fa-star"></i> Reviews</a>
        <a class="logout-btn" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</header>

<div class="container">
    <h1 class="page-title">My Dining History</h1>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-number"><?= $total_bookings ?></div>
            <div class="stat-label">Total Bookings</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-number"><?= $total_orders ?></div>
            <div class="stat-label">Food Orders</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-number">ETB <?= number_format($total_spent, 0) ?></div>
            <div class="stat-label">Total Spent</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-heart"></i></div>
            <div class="stat-number"><?= $most_frequent_meal ?></div>
            <div class="stat-label">Favorite Meal</div>
        </div>
    </div>

    <?php if (empty($history)): ?>
        <div class="no-history">
            <i class="fas fa-history"></i>
            <h3>No History Found</h3>
            <p>Start your dining journey with us!</p>
            <a href="View_Tables.php" class="start-btn">Book Your First Table</a>
        </div>
    <?php else: ?>
        <div class="history-timeline">
            <div class="timeline-title">Your Dining Journey</div>
            
            <?php 
            $grouped_history = [];
            foreach ($history as $record) {
                $booking_id = $record['booking_id'];
                if (!isset($grouped_history[$booking_id])) {
                    $grouped_history[$booking_id] = [
                        'booking' => $record,
                        'orders' => []
                    ];
                }
                if ($record['order_id']) {
                    $grouped_history[$booking_id]['orders'][] = $record;
                }
            }
            
            foreach ($grouped_history as $group): 
                $booking = $group['booking'];
                $orders = $group['orders'];
            ?>
                <div class="timeline-item">
                    <div class="timeline-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <div class="timeline-date">
                                <?= date('M j, Y g:i A', strtotime($booking['booking_datetime'])) ?>
                            </div>
                            <div class="timeline-status status-<?= $booking['booking_status'] ?>">
                                <?= ucfirst($booking['booking_status']) ?>
                            </div>
                        </div>
                        
                        <div class="timeline-details">
                            <div class="detail-item">
                                <span class="detail-label">Table:</span> <?= htmlspecialchars($booking['table_label'] ?? 'Table') ?>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Guests:</span> <?= $booking['guests'] ?> person<?= $booking['guests'] > 1 ? 's' : '' ?>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Meal Type:</span> <?= htmlspecialchars($booking['meal_type']) ?>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Booked On:</span> <?= date('M j, Y', strtotime($booking['booking_created'])) ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($orders)): ?>
                            <?php foreach ($orders as $order): ?>
                                <div class="order-summary">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                        <strong>Pre-Order #<?= $order['order_id'] ?></strong>
                                        <span class="timeline-status status-<?= $order['order_status'] ?>">
                                            <?= ucfirst($order['order_status']) ?>
                                        </span>
                                    </div>
                                    <?php if ($order['items']): ?>
                                        <div class="order-items">
                                            <i class="fas fa-utensils"></i> <?= htmlspecialchars($order['items']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="order-total">
                                        Total: ETB <?= number_format($order['total_amount'], 2) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>