<?php
session_start();
require_once "../connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? "Customer";
$success = $error = "";
$order_id = intval($_GET['order_id'] ?? 0);

// Get order details
$stmt = $conn->prepare("
    SELECT po.*, b.booking_datetime, b.meal_type, rt.table_label, b.id as booking_id
    FROM pre_orders po
    JOIN bookings b ON po.booking_id = b.id
    LEFT JOIN restaurant_tables rt ON b.table_id = rt.id
    WHERE po.id = ? AND b.user_id = ? AND po.status = 'pending'
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: my_orders.php");
    exit;
}

// Get current order items
$stmt = $conn->prepare("
    SELECT poi.*, mi.name, mi.category, mi.price as current_price
    FROM pre_order_items poi
    JOIN menu_items mi ON poi.menu_item_id = mi.id
    WHERE poi.pre_order_id = ?
");
$stmt->execute([$order_id]);
$current_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create array for easy access
$order_quantities = [];
foreach ($current_items as $item) {
    $order_quantities[$item['menu_item_id']] = $item['quantity'];
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST['items'])) {
    try {
        $conn->beginTransaction();
        
        // Delete existing order items
        $conn->prepare("DELETE FROM pre_order_items WHERE pre_order_id = ?")->execute([$order_id]);
        
        $total = 0;
        foreach ($_POST['items'] as $item) {
            $id = intval($item['id']);
            $qty = intval($item['quantity']);
            if ($qty > 0) {
                $price_stmt = $conn->prepare("SELECT price FROM menu_items WHERE id = ?");
                $price_stmt->execute([$id]);
                $price = $price_stmt->fetchColumn();
                if ($price) {
                    $total += $price * $qty;
                    $conn->prepare("INSERT INTO pre_order_items (pre_order_id, menu_item_id, quantity, price) VALUES (?, ?, ?, ?)")->execute([$order_id, $id, $qty, $price]);
                }
            }
        }
        
        // Update order total
        $conn->prepare("UPDATE pre_orders SET total_amount = ? WHERE id = ?")->execute([$total, $order_id]);
        $conn->commit();
        
        $success = "Order updated successfully! New total: ETB " . number_format($total, 2);
        
        // Refresh order data
        $stmt = $conn->prepare("
            SELECT poi.*, mi.name, mi.category, mi.price as current_price
            FROM pre_order_items poi
            JOIN menu_items mi ON poi.menu_item_id = mi.id
            WHERE poi.pre_order_id = ?
        ");
        $stmt->execute([$order_id]);
        $current_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $order_quantities = [];
        foreach ($current_items as $item) {
            $order_quantities[$item['menu_item_id']] = $item['quantity'];
        }
        
        // Update order total
        $order['total_amount'] = $total;
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Failed to update order: " . $e->getMessage();
    }
}

// Get all menu items
$items = $conn->query("SELECT * FROM menu_items ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC);
$categories = [];
foreach ($items as $item) {
    $categories[$item['category']][] = $item;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Update Order #<?= $order_id ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #667eea, #764ba2); 
    min-height: 100vh; 
}

/* Header */
header { 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px); 
    padding: 20px; 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    box-shadow: 0 4px 20px rgba(0,0,0,0.1); 
    position: sticky;
    top: 0;
    z-index: 100;
}
header h2 { 
    background: linear-gradient(45deg, #667eea, #764ba2); 
    -webkit-background-clip: text; 
    -webkit-text-fill-color: transparent; 
    font-size: 1.8rem;
}
nav { display: flex; gap: 10px; flex-wrap: wrap; }
nav a { 
    color: #333; 
    text-decoration: none; 
    padding: 8px 15px; 
    border-radius: 20px; 
    transition: 0.3s; 
    font-weight: 500;
}
nav a:hover { background: #667eea; color: white; }
.logout-btn { background: #ff6b6b !important; color: white !important; }

/* Container */
.container { max-width: 1200px; margin: 30px auto; p