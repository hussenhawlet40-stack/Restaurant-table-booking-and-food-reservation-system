<?php
session_start();
require_once "../connection.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'chef') {
    header("Location: ../login.php");
    exit;
}

$chef_id = $_SESSION['user_id'];
$chef_name = $_SESSION['name'] ?? 'Chef';
$message = $error = "";

// PAYMENT FILTERING: Kitchen management only shows PAID orders
// - Orders with total_amount = 0 (free orders) are always shown
// - Orders with total_amount > 0 are only shown if payment is completed OR order is confirmed
// - Orders with status 'pending_payment' (user clicked "Not Now") are HIDDEN from kitchen
// - This ensures kitchen staff only prepare food for orders that are guaranteed to be paid

// Handle kitchen operations
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $order_id = intval($_POST['order_id']);
        $status = $_POST['status'] ?? '';
        $chef_notes = $_POST['chef_notes'] ?? '';
        
        try {
            // Check if kitchen order exists
            $check = $conn->prepare("SELECT * FROM kitchen_orders WHERE pre_order_id = ?");
            $check->execute([$order_id]);
            
            if ($check->rowCount() == 0) {
                // Create kitchen order if it doesn't exist
                $stmt = $conn->prepare("INSERT INTO kitchen_orders (pre_order_id, chef_id, preparation_status, chef_notes, started_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$order_id, $chef_id, $status, $chef_notes]);
            } else {
                // Update existing kitchen order
                $update_fields = ["preparation_status = ?", "chef_id = ?", "chef_notes = ?"];
                $params = [$status, $chef_id, $chef_notes];
                
                if ($status === 'in_progress') {
                    $update_fields[] = "started_at = NOW()";
                } elseif ($status === 'ready') {
                    $update_fields[] = "completed_at = NOW()";
                }
                
                $stmt = $conn->prepare("UPDATE kitchen_orders SET " . implode(", ", $update_fields) . " WHERE pre_order_id = ?");
                $params[] = $order_id;
                $stmt->execute($params);
            }
            
            // Update pre-order status
            $pre_order_status = match($status) {
                'in_progress' => 'preparing',
                'ready' => 'ready',
                'served' => 'ready',
                default => 'pending'
            };
            
            $conn->prepare("UPDATE pre_orders SET status = ? WHERE id = ?")->execute([$pre_order_status, $order_id]);
            $message = "Order status updated successfully!";
            
        } catch (Exception $e) {
            $error = "Error updating status: " . $e->getMessage();
        }
    }
    
    if ($action === 'update_time') {
        $order_id = intval($_POST['order_id']);
        $estimated_time = intval($_POST['estimated_time']);
        
        try {
            // Ensure kitchen order exists
            $check = $conn->prepare("SELECT * FROM kitchen_orders WHERE pre_order_id = ?");
            $check->execute([$order_id]);
            
            if ($check->rowCount() == 0) {
                $stmt = $conn->prepare("INSERT INTO kitchen_orders (pre_order_id, chef_id, estimated_time) VALUES (?, ?, ?)");
                $stmt->execute([$order_id, $chef_id, $estimated_time]);
            } else {
                $stmt = $conn->prepare("UPDATE kitchen_orders SET estimated_time = ?, chef_id = ? WHERE pre_order_id = ?");
                $stmt->execute([$estimated_time, $chef_id, $order_id]);
            }
            
            $message = "Estimated time updated to {$estimated_time} minutes!";
        } catch (Exception $e) {
            $error = "Error updating time: " . $e->getMessage();
        }
    }
    
    if ($action === 'batch_update') {
        $selected_orders = $_POST['selected_orders'] ?? [];
        $batch_status = $_POST['batch_status'] ?? '';
        
        if (!empty($selected_orders) && !empty($batch_status)) {
            try {
                $conn->beginTransaction();
                
                foreach ($selected_orders as $order_id) {
                    // Ensure kitchen order exists
                    $check = $conn->prepare("SELECT * FROM kitchen_orders WHERE pre_order_id = ?");
                    $check->execute([$order_id]);
                    
                    if ($check->rowCount() == 0) {
                        $stmt = $conn->prepare("INSERT INTO kitchen_orders (pre_order_id, chef_id, preparation_status, started_at) VALUES (?, ?, ?, NOW())");
                        $stmt->execute([$order_id, $chef_id, $batch_status]);
                    } else {
                        $update_fields = ["preparation_status = ?", "chef_id = ?"];
                        $params = [$batch_status, $chef_id];
                        
                        if ($batch_status === 'in_progress') {
                            $update_fields[] = "started_at = NOW()";
                        } elseif ($batch_status === 'ready') {
                            $update_fields[] = "completed_at = NOW()";
                        }
                        
                        $stmt = $conn->prepare("UPDATE kitchen_orders SET " . implode(", ", $update_fields) . " WHERE pre_order_id = ?");
                        $params[] = $order_id;
                        $stmt->execute($params);
                    }
                    
                    // Update pre-order status
                    $pre_order_status = match($batch_status) {
                        'in_progress' => 'preparing',
                        'ready' => 'ready',
                        'served' => 'ready',
                        default => 'pending'
                    };
                    
                    $conn->prepare("UPDATE pre_orders SET status = ? WHERE id = ?")->execute([$pre_order_status, $order_id]);
                }
                
                $conn->commit();
                $message = "Batch update completed for " . count($selected_orders) . " orders!";
                
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "Batch update failed: " . $e->getMessage();
            }
        } else {
            $error = "Please select orders and status for batch update.";
        }
    }
    
    if ($action === 'add_notes') {
        $order_id = intval($_POST['order_id']);
        $notes = trim($_POST['notes'] ?? '');
        
        try {
            $stmt = $conn->prepare("UPDATE kitchen_orders SET chef_notes = ? WHERE pre_order_id = ?");
            $stmt->execute([$notes, $order_id]);
            $message = "Notes added successfully!";
        } catch (Exception $e) {
            $error = "Error adding notes: " . $e->getMessage();
        }
    }
}

// Get kitchen statistics - Only count PAID orders
try {
    $stats = [
        'pending' => $conn->query("
            SELECT COUNT(*) FROM pre_orders po 
            LEFT JOIN kitchen_orders ko ON po.id = ko.pre_order_id 
            LEFT JOIN payments p ON po.id = p.order_id AND p.payment_type = 'order'
            WHERE po.status IN ('pending', 'confirmed') 
            AND (ko.preparation_status IS NULL OR ko.preparation_status = 'pending')
            AND po.status != 'pending_payment'
            AND (
                po.total_amount = 0 
                OR (po.total_amount > 0 AND p.status = 'completed')
                OR (po.total_amount > 0 AND po.status = 'confirmed')
            )
        ")->fetchColumn(),
        
        'in_progress' => $conn->query("
            SELECT COUNT(*) FROM kitchen_orders WHERE preparation_status = 'in_progress'
        ")->fetchColumn(),
        
        'ready' => $conn->query("
            SELECT COUNT(*) FROM kitchen_orders WHERE preparation_status = 'ready'
        ")->fetchColumn(),
        
        'served_today' => $conn->query("
            SELECT COUNT(*) FROM kitchen_orders 
            WHERE preparation_status = 'served' AND DATE(completed_at) = CURDATE()
        ")->fetchColumn(),
        
        'avg_time' => $conn->query("
            SELECT AVG(TIMESTAMPDIFF(MINUTE, started_at, completed_at)) 
            FROM kitchen_orders 
            WHERE started_at IS NOT NULL AND completed_at IS NOT NULL 
            AND DATE(completed_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAYS)
        ")->fetchColumn() ?: 0,
        
        'total_revenue_today' => $conn->query("
            SELECT COALESCE(SUM(po.total_amount), 0) 
            FROM pre_orders po 
            JOIN kitchen_orders ko ON po.id = ko.pre_order_id 
            LEFT JOIN payments p ON po.id = p.order_id AND p.payment_type = 'order'
            WHERE ko.preparation_status = 'served' AND DATE(ko.completed_at) = CURDATE()
            AND (
                po.total_amount = 0 
                OR (po.total_amount > 0 AND p.status = 'completed')
                OR (po.total_amount > 0 AND po.status = 'confirmed')
            )
        ")->fetchColumn()
    ];
} catch (Exception $e) {
    $stats = ['pending' => 0, 'in_progress' => 0, 'ready' => 0, 'served_today' => 0, 'avg_time' => 0, 'total_revenue_today' => 0];
}

// Get all orders that need kitchen attention - Only PAID orders
try {
    $active_orders = $conn->query("
        SELECT po.id as pre_order_id, po.total_amount, po.status as order_status, po.created_at as order_time,
               b.booking_datetime, b.meal_type, b.guests, u.name as customer_name,
               rt.table_label, rt.location,
               ko.preparation_status, ko.estimated_time, ko.chef_notes, ko.started_at, ko.completed_at,
               COALESCE(ko.chef_id, 0) as assigned_chef_id,
               chef.name as assigned_chef_name,
               p.status as payment_status, p.payment_method,
               GROUP_CONCAT(CONCAT(mi.name, ' (', poi.quantity, 'x - ETB ', poi.price, ')') ORDER BY mi.category, mi.name SEPARATOR '<br>') as items,
               COUNT(poi.id) as item_count,
               CASE 
                   WHEN ko.started_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, ko.started_at, NOW())
                   ELSE NULL
               END as elapsed_time,
               CASE 
                   WHEN ko.estimated_time IS NOT NULL AND ko.started_at IS NOT NULL 
                   THEN ko.estimated_time - TIMESTAMPDIFF(MINUTE, ko.started_at, NOW())
                   ELSE NULL
               END as remaining_time
        FROM pre_orders po
        JOIN bookings b ON po.booking_id = b.id
        JOIN users u ON b.user_id = u.id
        LEFT JOIN restaurant_tables rt ON b.table_id = rt.id
        LEFT JOIN kitchen_orders ko ON po.id = ko.pre_order_id
        LEFT JOIN users chef ON ko.chef_id = chef.id
        LEFT JOIN pre_order_items poi ON po.id = poi.pre_order_id
        LEFT JOIN menu_items mi ON poi.menu_item_id = mi.id
        LEFT JOIN payments p ON po.id = p.order_id AND p.payment_type = 'order'
        WHERE po.status IN ('pending', 'confirmed', 'preparing', 'ready') 
        AND po.status != 'pending_payment'
        AND (ko.preparation_status IS NULL OR ko.preparation_status IN ('pending', 'in_progress', 'ready'))
        AND (
            po.total_amount = 0 
            OR (po.total_amount > 0 AND p.status = 'completed')
            OR (po.total_amount > 0 AND po.status = 'confirmed')
        )
        GROUP BY po.id
        ORDER BY 
            CASE COALESCE(ko.preparation_status, 'pending')
                WHEN 'pending' THEN 1
                WHEN 'in_progress' THEN 2
                WHEN 'ready' THEN 3
            END,
            po.created_at ASC
    ")->fetchAll();
} catch (Exception $e) {
    $active_orders = [];
    $error = "Error loading orders: " . $e->getMessage();
}

// Get popular menu items and kitchen insights
try {
    $popular_items = $conn->query("
        SELECT mi.name, mi.category, COUNT(*) as order_count, SUM(poi.quantity) as total_quantity,
               AVG(poi.price) as avg_price
        FROM pre_order_items poi
        JOIN menu_items mi ON poi.menu_item_id = mi.id
        JOIN pre_orders po ON poi.pre_order_id = po.id
        WHERE po.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAYS)
        GROUP BY mi.id
        ORDER BY total_quantity DESC
        LIMIT 8
    ")->fetchAll();
    
    $kitchen_insights = $conn->query("
        SELECT 
            AVG(CASE WHEN ko.preparation_status = 'served' THEN TIMESTAMPDIFF(MINUTE, ko.started_at, ko.completed_at) END) as avg_prep_time,
            COUNT(CASE WHEN ko.preparation_status = 'served' AND DATE(ko.completed_at) = CURDATE() THEN 1 END) as orders_completed_today,
            COUNT(CASE WHEN ko.preparation_status IN ('pending', 'in_progress') THEN 1 END) as orders_in_queue,
            MAX(CASE WHEN ko.preparation_status = 'in_progress' THEN TIMESTAMPDIFF(MINUTE, ko.started_at, NOW()) END) as longest_prep_time
        FROM kitchen_orders ko
        WHERE ko.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    ")->fetch();
} catch (Exception $e) {
    $popular_items = [];
    $kitchen_insights = ['avg_prep_time' => 0, 'orders_completed_today' => 0, 'orders_in_queue' => 0, 'longest_prep_time' => 0];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Kitchen Management - <?= htmlspecialchars($chef_name) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #ff9a56 0%, #ff6b35 100%);
    min-height: 100vh;
    color: #333;
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
    background: linear-gradient(45deg, #ff9a56, #ff6b35); 
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

.container { max-width: 1600px; margin: 30px auto; padding: 0 20px; }

.message { 
    padding: 15px; 
    border-radius: 15px; 
    margin-bottom: 30px; 
    text-align: center; 
    font-weight: 500; 
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.message.success {
    background: rgba(40,167,69,0.1); 
    color: #28a745; 
    border: 2px solid rgba(40,167,69,0.3); 
}

.message.error {
    background: rgba(220,53,69,0.1); 
    color: #dc3545; 
    border: 2px solid rgba(220,53,69,0.3); 
}

.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 30px;
    margin-bottom: 30px;
}

.stats-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-card {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(15px);
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    text-align: center;
    transition: 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    font-size: 2.5rem;
    margin-bottom: 15px;
    background: linear-gradient(45deg, #ff9a56, #ff6b35);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
}

.popular-items {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(15px);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.section-title {
    font-size: 1.3rem;
    font-weight: bold;
    color: #333;
    margin-bottom: 20px;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.item-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.item-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
}

.item-name {
    font-weight: 500;
    color: #333;
}

.item-count {
    background: #ff9a56;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: bold;
}

.kitchen-controls {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(15px);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.controls-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.control-btn {
    padding: 15px;
    background: linear-gradient(45deg, #ff9a56, #ff6b35);
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 500;
    transition: 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.control-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255,154,86,0.4);
}

.selection-info {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 15px;
    background: rgba(255,255,255,0.8);
    border-radius: 10px;
    font-weight: 600;
    color: #666;
    min-height: 50px;
}

.batch-controls {
    display: flex;
    gap: 15px;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.batch-select {
    padding: 10px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    background: white;
}

.orders-table {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(15px);
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.table-header {
    background: linear-gradient(45deg, #ff9a56, #ff6b35);
    color: white;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.orders-grid {
    display: grid;
    gap: 20px;
    padding: 20px;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
}

.order-card {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(15px);
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border-left: 4px solid #ddd;
}

.order-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.15);
}

.order-card.order-overdue {
    border-left-color: #dc3545;
    background: rgba(220,53,69,0.05);
}

.order-card.order-ready {
    border-left-color: #28a745;
    background: rgba(40,167,69,0.05);
}

.order-card.order-progress {
    border-left-color: #ff9a56;
    background: rgba(255,154,86,0.05);
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.order-select {
    display: flex;
    align-items: center;
    gap: 10px;
}

.order-id {
    font-weight: bold;
    font-size: 1.1rem;
    color: #333;
}

.priority-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: bold;
    text-transform: uppercase;
}

.priority-badge.overdue {
    background: #dc3545;
    color: white;
}

.priority-badge.ready {
    background: #28a745;
    color: white;
}

.order-details {
    margin-bottom: 15px;
}

.customer-info {
    display: grid;
    gap: 8px;
    margin-bottom: 15px;
}

.customer-name, .table-info, .booking-info {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    color: #666;
}

.customer-name {
    font-weight: 600;
    color: #333;
}

.order-items-section {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.items-header {
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.order-items {
    font-size: 0.85rem;
    line-height: 1.5;
    color: #666;
}

.order-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
    color: #666;
}

.order-total {
    font-weight: bold;
    color: #ff9a56;
}

.order-controls {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.status-control, .time-control {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.status-control label, .time-control label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
}

.status-select {
    padding: 8px 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    background: white;
    font-weight: 500;
    transition: 0.3s;
}

.status-select:focus {
    border-color: #ff9a56;
    outline: none;
}

.time-input-group {
    display: flex;
    gap: 5px;
    align-items: center;
}

.time-input {
    width: 70px;
    padding: 8px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    text-align: center;
    font-weight: 500;
}

.timer-display {
    grid-column: 1 / -1;
    margin-top: 10px;
}

.timer-info {
    display: flex;
    gap: 15px;
    font-size: 0.85rem;
}

.elapsed-time, .remaining-time {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    border-radius: 12px;
    font-weight: 500;
}

.elapsed-time {
    background: rgba(255,154,86,0.1);
    color: #ff9a56;
}

.elapsed-time.overdue, .remaining-time.overdue {
    background: rgba(220,53,69,0.1);
    color: #dc3545;
}

.remaining-time {
    background: rgba(40,167,69,0.1);
    color: #28a745;
}

.ready-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: rgba(40,167,69,0.1);
    color: #28a745;
    border-radius: 8px;
    font-weight: 600;
}

.chef-notes-section {
    border-top: 1px solid #eee;
    padding-top: 15px;
}

.notes-header {
    font-size: 0.9rem;
    font-weight: 600;
    color: #666;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.notes-input-group {
    display: flex;
    gap: 8px;
    align-items: flex-start;
}

.chef-notes {
    flex: 1;
    padding: 8px 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    resize: vertical;
    min-height: 60px;
    font-family: inherit;
    font-size: 0.85rem;
}

.chef-notes:focus {
    border-color: #ff9a56;
    outline: none;
}

.assigned-chef {
    margin-top: 8px;
    font-size: 0.8rem;
    color: #666;
    display: flex;
    align-items: center;
    gap: 5px;
}

.btn-notes {
    background: #6c757d;
    color: white;
}

.btn-notes:hover {
    background: #5a6268;
}

.order-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.order-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.order-id {
    font-weight: bold;
    color: #333;
}

.order-customer {
    color: #666;
    font-size: 0.9rem;
}

.order-items {
    color: #666;
    font-size: 0.85rem;
    line-height: 1.4;
}

.status-select {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 5px;
    background: white;
}

.time-input {
    width: 80px;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 5px;
    text-align: center;
}

.timer-display {
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: bold;
}

.timer-running {
    color: #ff6b35;
}

.timer-overdue {
    color: #dc3545;
}

.action-btns {
    display: flex;
    gap: 5px;
}

.btn-sm {
    padding: 6px 10px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 0.8rem;
    transition: 0.3s;
}

.btn-update { background: #28a745; color: white; }
.btn-update:hover { background: #218838; }

.no-orders {
    text-align: center;
    padding: 40px;
    color: #666;
}

@media (max-width: 1200px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .order-row {
        grid-template-columns: 1fr;
        gap: 10px;
    }
}

@media (max-width: 768px) {
    .stats-section {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .controls-grid {
        grid-template-columns: 1fr;
    }
    
    .batch-controls {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>
</head>
<body>
<header>
    <h1><i class="fas fa-fire"></i> Kitchen Management</h1>
    <a href="dashboard.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</header>

<div class="container">
    <?php if ($message): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Dashboard Grid -->
    <div class="dashboard-grid">
        <!-- Kitchen Statistics -->
        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?= $stats['pending'] ?></div>
                <div class="stat-label">Pending Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-fire"></i></div>
                <div class="stat-number"><?= $stats['in_progress'] ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?= $stats['ready'] ?></div>
                <div class="stat-label">Ready to Serve</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                <div class="stat-number"><?= $stats['served_today'] ?></div>
                <div class="stat-label">Served Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-stopwatch"></i></div>
                <div class="stat-number"><?= round($stats['avg_time']) ?></div>
                <div class="stat-label">Avg Time (min)</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-coins"></i></div>
                <div class="stat-number">ETB <?= number_format($stats['total_revenue_today'], 0) ?></div>
                <div class="stat-label">Today's Revenue</div>
            </div>
        </div>

        <!-- Popular Items -->
        <div class="popular-items">
            <div class="section-title">
                <i class="fas fa-chart-line"></i>
                Popular This Week
            </div>
            <div class="item-list">
                <?php if (empty($popular_items)): ?>
                    <div style="text-align: center; color: #666; padding: 20px;">
                        No data available
                    </div>
                <?php else: ?>
                    <?php foreach ($popular_items as $item): ?>
                        <div class="item-row">
                            <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="item-count"><?= $item['total_quantity'] ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Kitchen Controls -->
    <div class="kitchen-controls">
        <div class="section-title">
            <i class="fas fa-cogs"></i>
            Kitchen Controls
        </div>
        <div class="controls-grid">
            <button class="control-btn" onclick="refreshOrders()">
                <i class="fas fa-sync"></i> Refresh Orders
            </button>
            <button class="control-btn" onclick="selectAll()">
                <i class="fas fa-check-square"></i> Select All
            </button>
            <button class="control-btn" onclick="clearSelection()">
                <i class="fas fa-square"></i> Clear Selection
            </button>
            <button class="control-btn" onclick="showBatchControls()">
                <i class="fas fa-tasks"></i> Batch Operations
            </button>
            <button class="control-btn" onclick="toggleAutoRefresh()">
                <i class="fas fa-pause"></i> Pause Auto-Refresh
            </button>
            <div class="selection-info">
                <span id="selection-count"></span>
            </div>
        </div>
    </div>

    <!-- Batch Controls -->
    <div class="batch-controls" id="batch-controls" style="display: none;">
        <form method="POST" id="batch-form">
            <input type="hidden" name="action" value="batch_update">
            <select name="batch_status" class="batch-select" required>
                <option value="">Select Status</option>
                <option value="in_progress">Start Preparation</option>
                <option value="ready">Mark as Ready</option>
                <option value="served">Mark as Served</option>
            </select>
            <button type="submit" class="control-btn">
                <i class="fas fa-play"></i> Apply to Selected
            </button>
        </form>
    </div>

    <!-- Orders Table -->
    <div class="orders-table">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Active Kitchen Orders</h3>
            <div>Total: <?= count($active_orders) ?> orders</div>
        </div>

        <?php if (empty($active_orders)): ?>
            <div class="no-orders">
                <i class="fas fa-utensils" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                <h3>No Active Orders</h3>
                <p>All orders are completed or there are no new orders in the kitchen.</p>
                <div style="margin-top: 20px;">
                    <button onclick="refreshOrders()" class="control-btn">
                        <i class="fas fa-sync"></i> Refresh Orders
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="orders-grid">
                <?php foreach ($active_orders as $order): ?>
                    <?php 
                    $status = $order['preparation_status'] ?? 'pending';
                    $priority_class = '';
                    if ($order['elapsed_time'] && $order['estimated_time'] && $order['elapsed_time'] > $order['estimated_time']) {
                        $priority_class = 'order-overdue';
                    } elseif ($status === 'ready') {
                        $priority_class = 'order-ready';
                    } elseif ($status === 'in_progress') {
                        $priority_class = 'order-progress';
                    }
                    ?>
                    <div class="order-card <?= $priority_class ?>" data-order-id="<?= $order['pre_order_id'] ?>">
                        <div class="order-header">
                            <div class="order-select">
                                <input type="checkbox" name="selected_orders[]" value="<?= $order['pre_order_id'] ?>" class="order-checkbox">
                                <div class="order-id">Order #<?= $order['pre_order_id'] ?></div>
                            </div>
                            <div class="order-priority">
                                <?php if ($order['elapsed_time'] && $order['estimated_time'] && $order['elapsed_time'] > $order['estimated_time']): ?>
                                    <span class="priority-badge overdue">
                                        <i class="fas fa-exclamation-triangle"></i> OVERDUE
                                    </span>
                                <?php elseif ($status === 'ready'): ?>
                                    <span class="priority-badge ready">
                                        <i class="fas fa-bell"></i> READY
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="order-details">
                            <div class="customer-info">
                                <div class="customer-name">
                                    <i class="fas fa-user"></i>
                                    <?= htmlspecialchars($order['customer_name']) ?>
                                </div>
                                <div class="table-info">
                                    <i class="fas fa-chair"></i>
                                    <?= htmlspecialchars($order['table_label'] ?? 'Table N/A') ?>
                                    <?php if ($order['location']): ?>
                                        - <?= htmlspecialchars($order['location']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="booking-info">
                                    <i class="fas fa-calendar"></i>
                                    <?= date('M j, g:i A', strtotime($order['booking_datetime'])) ?>
                                    (<?= $order['guests'] ?> guests)
                                </div>
                            </div>
                            
                            <div class="order-items-section">
                                <div class="items-header">
                                    <i class="fas fa-utensils"></i>
                                    Items (<?= $order['item_count'] ?>):
                                </div>
                                <div class="order-items"><?= $order['items'] ?></div>
                            </div>
                            
                            <div class="order-meta">
                                <div class="order-total">
                                    <i class="fas fa-coins"></i>
                                    Total: ETB <?= number_format($order['total_amount'], 2) ?>
                                </div>
                                <div class="order-time">
                                    <i class="fas fa-clock"></i>
                                    Ordered: <?= date('g:i A', strtotime($order['order_time'])) ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="order-controls">
                            <div class="status-control">
                                <label>Status:</label>
                                <select class="status-select" onchange="updateOrderStatus(<?= $order['pre_order_id'] ?>, this.value)">
                                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="ready" <?= $status === 'ready' ? 'selected' : '' ?>>Ready</option>
                                    <option value="served" <?= $status === 'served' ? 'selected' : '' ?>>Served</option>
                                </select>
                            </div>
                            
                            <div class="time-control">
                                <label>Est. Time (min):</label>
                                <div class="time-input-group">
                                    <input type="number" class="time-input" value="<?= $order['estimated_time'] ?? 30 ?>" 
                                           min="5" max="180" step="5" id="time-<?= $order['pre_order_id'] ?>">
                                    <button type="button" class="btn-sm btn-update" onclick="updateEstimatedTime(<?= $order['pre_order_id'] ?>)">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="timer-display">
                                <?php if ($status === 'in_progress' && $order['elapsed_time'] !== null): ?>
                                    <div class="timer-info">
                                        <div class="elapsed-time <?= $order['elapsed_time'] > ($order['estimated_time'] ?? 30) ? 'overdue' : '' ?>">
                                            <i class="fas fa-stopwatch"></i>
                                            Elapsed: <?= $order['elapsed_time'] ?>m
                                        </div>
                                        <?php if ($order['remaining_time'] !== null): ?>
                                            <div class="remaining-time <?= $order['remaining_time'] < 0 ? 'overdue' : '' ?>">
                                                <i class="fas fa-hourglass-half"></i>
                                                <?= $order['remaining_time'] > 0 ? "Remaining: {$order['remaining_time']}m" : "Overdue by " . abs($order['remaining_time']) . "m" ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($status === 'ready'): ?>
                                    <div class="ready-indicator">
                                        <i class="fas fa-bell"></i>
                                        Ready to serve!
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="chef-notes-section">
                            <div class="notes-header">
                                <i class="fas fa-sticky-note"></i>
                                Chef Notes:
                            </div>
                            <div class="notes-input-group">
                                <textarea class="chef-notes" placeholder="Add preparation notes..." 
                                          id="notes-<?= $order['pre_order_id'] ?>"><?= htmlspecialchars($order['chef_notes'] ?? '') ?></textarea>
                                <button type="button" class="btn-sm btn-notes" onclick="saveNotes(<?= $order['pre_order_id'] ?>)">
                                    <i class="fas fa-save"></i>
                                </button>
                            </div>
                            <?php if ($order['assigned_chef_name']): ?>
                                <div class="assigned-chef">
                                    <i class="fas fa-chef-hat"></i>
                                    Assigned to: <?= htmlspecialchars($order['assigned_chef_name']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Auto-refresh functionality
let autoRefresh = true;
let refreshInterval;

function refreshOrders() {
    if (confirm('Refresh orders? Any unsaved changes will be lost.')) {
        location.reload();
    }
}

function toggleAutoRefresh() {
    autoRefresh = !autoRefresh;
    const btn = document.querySelector('[onclick="toggleAutoRefresh()"]');
    
    if (autoRefresh) {
        btn.innerHTML = '<i class="fas fa-pause"></i> Pause Auto-Refresh';
        startAutoRefresh();
    } else {
        btn.innerHTML = '<i class="fas fa-play"></i> Resume Auto-Refresh';
        clearInterval(refreshInterval);
    }
}

function startAutoRefresh() {
    refreshInterval = setInterval(() => {
        if (autoRefresh) {
            // Only refresh if no forms are being edited
            const activeInputs = document.querySelectorAll('input:focus, textarea:focus, select:focus');
            if (activeInputs.length === 0) {
                location.reload();
            }
        }
    }, 45000); // 45 seconds
}

function selectAll() {
    const checkboxes = document.querySelectorAll('.order-checkbox');
    checkboxes.forEach(cb => cb.checked = true);
    updateSelectionCount();
}

function clearSelection() {
    const checkboxes = document.querySelectorAll('.order-checkbox');
    checkboxes.forEach(cb => cb.checked = false);
    updateSelectionCount();
}

function updateSelectionCount() {
    const selected = document.querySelectorAll('.order-checkbox:checked').length;
    const countDisplay = document.getElementById('selection-count');
    if (countDisplay) {
        countDisplay.textContent = selected > 0 ? `${selected} selected` : '';
    }
}

function showBatchControls() {
    const controls = document.getElementById('batch-controls');
    const selected = document.querySelectorAll('.order-checkbox:checked').length;
    
    if (selected === 0) {
        alert('Please select at least one order first.');
        return;
    }
    
    controls.style.display = controls.style.display === 'none' ? 'flex' : 'none';
}

function updateOrderStatus(orderId, status) {
    // Show loading indicator
    const card = document.querySelector(`[data-order-id="${orderId}"]`);
    const originalContent = card.innerHTML;
    
    // Create and submit form
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="order_id" value="${orderId}">
        <input type="hidden" name="status" value="${status}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function updateEstimatedTime(orderId) {
    const timeInput = document.getElementById(`time-${orderId}`);
    const estimatedTime = timeInput.value;
    
    if (!estimatedTime || estimatedTime < 5) {
        alert('Please enter a valid estimated time (minimum 5 minutes).');
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="update_time">
        <input type="hidden" name="order_id" value="${orderId}">
        <input type="hidden" name="estimated_time" value="${estimatedTime}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function saveNotes(orderId) {
    const notesTextarea = document.getElementById(`notes-${orderId}`);
    const notes = notesTextarea.value.trim();
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="add_notes">
        <input type="hidden" name="order_id" value="${orderId}">
        <input type="hidden" name="notes" value="${notes}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Handle batch form submission
document.getElementById('batch-form').addEventListener('submit', function(e) {
    const selected = document.querySelectorAll('.order-checkbox:checked');
    if (selected.length === 0) {
        e.preventDefault();
        alert('Please select at least one order.');
        return;
    }
    
    const status = this.querySelector('[name="batch_status"]').value;
    if (!status) {
        e.preventDefault();
        alert('Please select a status for batch update.');
        return;
    }
    
    if (!confirm(`Update ${selected.length} orders to "${status}"?`)) {
        e.preventDefault();
        return;
    }
    
    // Add selected order IDs to form
    selected.forEach(checkbox => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_orders[]';
        input.value = checkbox.value;
        this.appendChild(input);
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch(e.key) {
            case 'r':
                e.preventDefault();
                refreshOrders();
                break;
            case 'a':
                e.preventDefault();
                selectAll();
                break;
            case 'd':
                e.preventDefault();
                clearSelection();
                break;
        }
    }
});

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Add entrance animations
    const elements = document.querySelectorAll('.stat-card, .order-card');
    elements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        element.style.transition = `opacity 0.4s ease ${index * 0.05}s, transform 0.4s ease ${index * 0.05}s`;
        
        setTimeout(() => {
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, 50 + (index * 50));
    });
    
    // Add selection change listeners
    document.querySelectorAll('.order-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectionCount);
    });
    
    // Start auto-refresh
    startAutoRefresh();
    
    // Add real-time clock updates for elapsed times
    setInterval(updateElapsedTimes, 60000); // Update every minute
});

function updateElapsedTimes() {
    // This would update elapsed times without full page refresh
    // For now, we'll keep the simple auto-refresh approach
}

// Add notification sound for new orders (optional)
function playNotificationSound() {
    // Create audio context for notification sound
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    
    oscillator.frequency.value = 800;
    oscillator.type = 'sine';
    
    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
    
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.5);
}
</script>
</body>
</html>