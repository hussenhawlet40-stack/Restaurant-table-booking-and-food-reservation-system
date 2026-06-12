<?php
session_start();
require_once "../connection.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'chef') {
    header("Location: ../login.php");
    exit;
}

$chef_id = $_SESSION['user_id'];
$message = "";

// PAYMENT FILTERING: Chef only sees orders that are PAID or FREE
// - Orders with total_amount = 0 (free orders) are always shown
// - Orders with total_amount > 0 are only shown if payment is completed OR order is confirmed
// - Orders with status 'pending_payment' (user clicked "Not Now") are HIDDEN from chef
// - This ensures chef only prepares food for orders that are guaranteed to be paid

// Handle order status updates
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    $order_id = intval($_POST['order_id'] ?? 0);
    
    if ($action === 'start_preparation' && $order_id > 0) {
        try {
            // Check if kitchen order exists
            $check = $conn->prepare("SELECT * FROM kitchen_orders WHERE pre_order_id = ?");
            $check->execute([$order_id]);
            
            if ($check->rowCount() == 0) {
                // Create kitchen order
                $stmt = $conn->prepare("INSERT INTO kitchen_orders (pre_order_id, chef_id, preparation_status, started_at) VALUES (?, ?, 'in_progress', NOW())");
                $stmt->execute([$order_id, $chef_id]);
            } else {
                // Update existing kitchen order
                $stmt = $conn->prepare("UPDATE kitchen_orders SET chef_id = ?, preparation_status = 'in_progress', started_at = NOW() WHERE pre_order_id = ?");
                $stmt->execute([$chef_id, $order_id]);
            }
            
            // Update pre-order status
            $conn->prepare("UPDATE pre_orders SET status = 'preparing' WHERE id = ?")->execute([$order_id]);
            $message = "Order preparation started successfully!";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
    
    if ($action === 'mark_ready' && $order_id > 0) {
        try {
            $stmt = $conn->prepare("UPDATE kitchen_orders SET preparation_status = 'ready', completed_at = NOW() WHERE pre_order_id = ?");
            $stmt->execute([$order_id]);
            
            $conn->prepare("UPDATE pre_orders SET status = 'ready' WHERE id = ?")->execute([$order_id]);
            $message = "Order marked as ready!";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
    
    if ($action === 'mark_served' && $order_id > 0) {
        try {
            $stmt = $conn->prepare("UPDATE kitchen_orders SET preparation_status = 'served' WHERE pre_order_id = ?");
            $stmt->execute([$order_id]);
            $message = "Order marked as served!";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
    
    if ($action === 'add_notes' && $order_id > 0) {
        $notes = trim($_POST['chef_notes'] ?? '');
        try {
            $stmt = $conn->prepare("UPDATE kitchen_orders SET chef_notes = ? WHERE pre_order_id = ?");
            $stmt->execute([$notes, $order_id]);
            $message = "Chef notes updated successfully!";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}

// Get all orders with details - Only show PAID orders to chef
$orders = $conn->query("
    SELECT po.*, b.booking_datetime, b.meal_type, u.name as customer_name, u.phone as customer_phone,
           rt.table_label, ko.preparation_status, ko.estimated_time, ko.chef_notes, ko.started_at, ko.completed_at,
           p.status as payment_status, p.payment_method,
           GROUP_CONCAT(CONCAT(mi.name, ' (', poi.quantity, 'x)') SEPARATOR ', ') as items
    FROM pre_orders po
    JOIN bookings b ON po.booking_id = b.id
    JOIN users u ON b.user_id = u.id
    LEFT JOIN restaurant_tables rt ON b.table_id = rt.id
    LEFT JOIN kitchen_orders ko ON po.id = ko.pre_order_id
    LEFT JOIN pre_order_items poi ON po.id = poi.pre_order_id
    LEFT JOIN menu_items mi ON poi.menu_item_id = mi.id
    LEFT JOIN payments p ON po.id = p.order_id AND p.payment_type = 'order'
    WHERE po.status != 'cancelled' 
    AND po.status != 'pending_payment'
    AND (
        po.total_amount = 0 
        OR (po.total_amount > 0 AND p.status = 'completed')
        OR (po.total_amount > 0 AND po.status = 'confirmed')
    )
    GROUP BY po.id
    ORDER BY 
        CASE 
            WHEN ko.preparation_status = 'pending' THEN 1
            WHEN ko.preparation_status = 'in_progress' THEN 2
            WHEN ko.preparation_status = 'ready' THEN 3
            WHEN ko.preparation_status = 'served' THEN 4
            ELSE 5
        END,
        po.created_at ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<title>Kitchen Orders - Chef Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #ff9a56 0%, #ff6b35 100%);
    min-height: 100vh;
}

header { 
    background: linear-gradient(135deg, #BFCFBB, #8EA5BC); 
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
    color: white; 
    text-shadow: 0 0 10px black; 
    font-size: 2rem; 
    display: flex; 
    align-items: center; 
    gap: 10px; 
    font-weight: bold;
}
.back-btn { 
    background: #738A6A; 
    color: white; 
    padding: 12px 20px; 
    text-decoration: none; 
    border-radius: 25px; 
    font-weight: 500; 
    transition: all 0.3s ease; 
    box-shadow: 0 4px 15px rgba(115,138,106,0.3); 
}
.back-btn:hover { 
    transform: translateY(-2px); 
    box-shadow: 0 6px 20px rgba(115,138,106,0.4); 
    background: #344C3D;
}

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

.filter-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    justify-content: center;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 10px 20px;
    background: rgba(255,255,255,0.9);
    border: 2px solid transparent;
    border-radius: 25px;
    cursor: pointer;
    transition: 0.3s;
    font-weight: 500;
}

.filter-tab.active,
.filter-tab:hover {
    background: linear-gradient(45deg, #ff9a56, #ff6b35);
    color: white;
    border-color: rgba(255,255,255,0.3);
}

.orders-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); 
    gap: 15px; 
}

.order-card { 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px); 
    border-radius: 12px; 
    padding: 15px; 
    box-shadow: 0 6px 20px rgba(0,0,0,0.1); 
    transition: 0.3s; 
    border: 1px solid rgba(255,255,255,0.3); 
    position: relative;
}

.order-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.15); }

.order-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: 12px; 
    padding-bottom: 10px;
    border-bottom: 1px solid #f0f0f0;
}

.order-id { 
    font-size: 1.1rem; 
    font-weight: bold; 
    color: #333; 
}

.priority-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: bold;
}

.priority-high { background: #ff6b6b; color: white; }
.priority-medium { background: #ffa726; color: white; }
.priority-low { background: #66bb6a; color: white; }

.status-badge { 
    padding: 5px 10px; 
    border-radius: 15px; 
    font-size: 0.8rem; 
    font-weight: 500; 
}

.status-pending { background: #fff3cd; color: #856404; }
.status-confirmed { background: #d4edda; color: #155724; }
.status-in_progress { background: #cce5ff; color: #004085; }
.status-ready { background: #d1ecf1; color: #0c5460; }
.status-served { background: #d4edda; color: #155724; }

.order-details { margin-bottom: 12px; }

.detail-row { 
    display: flex; 
    justify-content: space-between; 
    margin-bottom: 6px; 
    padding: 4px 0; 
}

.detail-label { font-weight: 500; color: #666; font-size: 0.85rem; }
.detail-value { color: #333; font-size: 0.85rem; }

.order-items { 
    background: #f8f9fa; 
    padding: 10px; 
    border-radius: 8px; 
    margin: 10px 0; 
}

.items-title { 
    font-weight: bold; 
    margin-bottom: 6px; 
    color: #333; 
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.9rem;
}

.items-list { 
    color: #666; 
    line-height: 1.4; 
    font-size: 0.85rem;
}

.chef-notes {
    background: #fff3cd;
    padding: 8px;
    border-radius: 6px;
    margin: 8px 0;
    border-left: 3px solid #ffa726;
}

.notes-title {
    font-weight: bold;
    color: #856404;
    margin-bottom: 4px;
    font-size: 0.85rem;
}

.order-actions { 
    display: flex; 
    gap: 8px; 
    flex-wrap: wrap; 
    margin-top: 12px;
}

.btn { 
    padding: 8px 12px; 
    border: none; 
    border-radius: 15px; 
    cursor: pointer; 
    font-size: 0.8rem; 
    transition: 0.3s; 
    text-decoration: none; 
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-weight: 500;
}

.btn-start { background: #42a5f5; color: white; }
.btn-ready { background: #66bb6a; color: white; }
.btn-served { background: #26c6da; color: white; }
.btn-notes { background: #ab47bc; color: white; }

.btn:hover { transform: translateY(-2px); }

.notes-form {
    display: none;
    margin-top: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
}

.notes-textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    resize: vertical;
    min-height: 80px;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.btn-save { background: #28a745; color: white; }
.btn-cancel { background: #6c757d; color: white; }

.timer {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #ff6b35;
    font-weight: bold;
}

.no-orders {
    text-align: center;
    padding: 60px 20px;
    background: rgba(255,255,255,0.95);
    border-radius: 15px;
    grid-column: 1 / -1;
}

.no-orders i {
    font-size: 4rem;
    color: #ddd;
    margin-bottom: 20px;
}

@media (max-width: 768px) { 
    .orders-grid { grid-template-columns: 1fr; } 
    .order-actions { flex-direction: column; }
    .filter-tabs { justify-content: center; }
}
</style>
</head>
<body>
<header>
    <h1><i class="fas fa-fire"></i> Kitchen Orders</h1>
    <a href="dashboard.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</header>

<div class="container">
    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <div class="filter-tab active" onclick="filterOrders('all')">All Orders</div>
        <div class="filter-tab" onclick="filterOrders('pending')">Pending</div>
        <div class="filter-tab" onclick="filterOrders('in_progress')">In Progress</div>
        <div class="filter-tab" onclick="filterOrders('ready')">Ready</div>
        <div class="filter-tab" onclick="filterOrders('served')">Served</div>
    </div>

    <?php if (empty($orders)): ?>
        <div class="no-orders">
            <i class="fas fa-utensils"></i>
            <h3>No Paid Orders Found</h3>
            <p>All paid orders are completed or there are no new paid orders to prepare.</p>
            <small style="color: #666; margin-top: 10px; display: block;">
                <i class="fas fa-info-circle"></i> Only orders with completed payment are shown to kitchen staff
            </small>
        </div>
    <?php else: ?>
        <div class="orders-grid">
            <?php foreach ($orders as $order): ?>
                <?php 
                $status = $order['preparation_status'] ?? 'pending';
                $priority = 'medium'; // Default priority
                
                // Calculate priority based on order time and meal type
                $order_time = strtotime($order['booking_datetime']);
                $current_time = time();
                $time_diff = ($order_time - $current_time) / 3600; // hours
                
                if ($time_diff < 1) $priority = 'high';
                elseif ($time_diff < 2) $priority = 'medium';
                else $priority = 'low';
                ?>
                
                <div class="order-card" data-status="<?= $status ?>">
                    <div class="priority-badge priority-<?= $priority ?>">
                        <?= ucfirst($priority) ?> Priority
                    </div>
                    
                    <div class="order-header">
                        <div class="order-id">Order #<?= $order['id'] ?></div>
                        <div class="status-badge status-<?= $status ?>">
                            <?= ucfirst(str_replace('_', ' ', $status)) ?>
                        </div>
                    </div>

                    <div class="order-details">
                        <div class="detail-row">
                            <span class="detail-label">Customer:</span>
                            <span class="detail-value"><?= htmlspecialchars($order['customer_name']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Table:</span>
                            <span class="detail-value"><?= htmlspecialchars($order['table_label'] ?? 'N/A') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Meal Time:</span>
                            <span class="detail-value"><?= date('M j, Y g:i A', strtotime($order['booking_datetime'])) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Meal Type:</span>
                            <span class="detail-value"><?= htmlspecialchars($order['meal_type']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Total Amount:</span>
                            <span class="detail-value">ETB <?= number_format($order['total_amount'], 2) ?></span>
                        </div>
                        
                        <?php if ($order['total_amount'] > 0): ?>
                            <div class="detail-row">
                                <span class="detail-label">Payment Status:</span>
                                <span class="detail-value">
                                    <?php if ($order['payment_status'] === 'completed'): ?>
                                        <span style="color: #28a745; font-weight: bold;">
                                            <i class="fas fa-check-circle"></i> PAID
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #28a745; font-weight: bold;">
                                            <i class="fas fa-check-circle"></i> CONFIRMED
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($order['payment_method']): ?>
                                        <small style="color: #666; margin-left: 10px;">
                                            via <?= ucfirst($order['payment_method']) ?>
                                        </small>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <div class="detail-row">
                                <span class="detail-label">Payment:</span>
                                <span class="detail-value" style="color: #6c757d;">
                                    <i class="fas fa-gift"></i> Free Order
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($order['started_at']): ?>
                            <div class="detail-row">
                                <span class="detail-label">Started:</span>
                                <span class="detail-value timer">
                                    <i class="fas fa-clock"></i>
                                    <?= date('g:i A', strtotime($order['started_at'])) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="order-items">
                        <div class="items-title">
                            <i class="fas fa-utensils"></i>
                            Order Items
                        </div>
                        <div class="items-list">
                            <?= htmlspecialchars($order['items'] ?? 'No items found') ?>
                        </div>
                    </div>

                    <?php if ($order['chef_notes']): ?>
                        <div class="chef-notes">
                            <div class="notes-title">Chef Notes:</div>
                            <div><?= htmlspecialchars($order['chef_notes']) ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="order-actions">
                        <?php if ($status === 'pending' || $status === null): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="start_preparation">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <button type="submit" class="btn btn-start">
                                    <i class="fas fa-play"></i> Start Preparation
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($status === 'in_progress'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="mark_ready">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <button type="submit" class="btn btn-ready">
                                    <i class="fas fa-check"></i> Mark Ready
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($status === 'ready'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="mark_served">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <button type="submit" class="btn btn-served">
                                    <i class="fas fa-utensils"></i> Mark Served
                                </button>
                            </form>
                        <?php endif; ?>

                        <button type="button" class="btn btn-notes" onclick="toggleNotes(<?= $order['id'] ?>)">
                            <i class="fas fa-sticky-note"></i> Add Notes
                        </button>
                    </div>

                    <!-- Notes Form -->
                    <div class="notes-form" id="notes-form-<?= $order['id'] ?>">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_notes">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <textarea name="chef_notes" class="notes-textarea" placeholder="Add chef notes..."><?= htmlspecialchars($order['chef_notes'] ?? '') ?></textarea>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-save">
                                    <i class="fas fa-save"></i> Save Notes
                                </button>
                                <button type="button" class="btn btn-cancel" onclick="toggleNotes(<?= $order['id'] ?>)">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function filterOrders(status) {
    const cards = document.querySelectorAll('.order-card');
    const tabs = document.querySelectorAll('.filter-tab');
    
    // Update active tab
    tabs.forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
    
    // Filter cards
    cards.forEach(card => {
        const cardStatus = card.getAttribute('data-status') || 'pending';
        if (status === 'all' || cardStatus === status) {
            card.style.display = 'block';
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
        } else {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.display = 'none';
            }, 300);
        }
    });
}

function toggleNotes(orderId) {
    const form = document.getElementById(`notes-form-${orderId}`);
    if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block';
    } else {
        form.style.display = 'none';
    }
}

// Auto-refresh every 30 seconds
setInterval(() => {
    location.reload();
}, 30000);

// Add entrance animations
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.order-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 + (index * 100));
    });
});
</script>
</body>
</html>