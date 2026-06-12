<?php
session_start();
require_once "../connection.php";
require_once "../includes/payment_functions.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? "Customer";
$success = $error = "";

// Setup tables
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS pre_orders (id INT AUTO_INCREMENT PRIMARY KEY, booking_id INT NOT NULL, total_amount DECIMAL(10,2) DEFAULT 0, status ENUM('pending', 'confirmed', 'preparing', 'ready') DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE)");
    $conn->exec("CREATE TABLE IF NOT EXISTS pre_order_items (id INT AUTO_INCREMENT PRIMARY KEY, pre_order_id INT NOT NULL, menu_item_id INT NOT NULL, quantity INT NOT NULL, price DECIMAL(10,2) NOT NULL, FOREIGN KEY (pre_order_id) REFERENCES pre_orders(id) ON DELETE CASCADE, FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE)");
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Get booking
$stmt = $conn->prepare("SELECT b.*, rt.table_label FROM bookings b LEFT JOIN restaurant_tables rt ON b.table_id = rt.id WHERE b.user_id = ? AND b.status IN ('pending', 'confirmed') ORDER BY b.id DESC LIMIT 1");
$stmt->execute([$user_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) $error = "You must have an active table booking before pre-ordering food.";

// Get menu items (load early for email notifications)
$items = $conn->query("SELECT * FROM menu_items ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC);

// Handle form
if ($_SERVER["REQUEST_METHOD"] === "POST" && $booking && !empty($_POST['items'])) {
    try {
        $conn->beginTransaction();
        $stmt_check = $conn->prepare("SELECT * FROM pre_orders WHERE booking_id = ?");
        $stmt_check->execute([$booking['id']]);
        $existing = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            $conn->prepare("DELETE FROM pre_order_items WHERE pre_order_id = ?")->execute([$existing['id']]);
            $pre_order_id = $existing['id'];
        } else {
            $conn->prepare("INSERT INTO pre_orders (booking_id, total_amount) VALUES (?, 0)")->execute([$booking['id']]);
            $pre_order_id = $conn->lastInsertId();
        }
        
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
                    $conn->prepare("INSERT INTO pre_order_items (pre_order_id, menu_item_id, quantity, price) VALUES (?, ?, ?, ?)")->execute([$pre_order_id, $id, $qty, $price]);
                }
            }
        }
        
        $conn->prepare("UPDATE pre_orders SET total_amount = ? WHERE id = ?")->execute([$total, $pre_order_id]);
        $conn->commit();
        
        // Create detailed success message with ordered items
        $order_items = [];
        $items_summary = "";
        
        foreach ($_POST['items'] as $item_data) {
            $id = intval($item_data['id']);
            $qty = intval($item_data['quantity']);
            if ($qty > 0) {
                $item = null;
                foreach ($items as $menu_item) {
                    if ($menu_item['id'] == $id) {
                        $item = $menu_item;
                        break;
                    }
                }
                if ($item) {
                    $order_items[] = $qty . "x " . $item['name'];
                }
            }
        }
        
        if (!empty($order_items)) {
            $items_summary = " | Items: " . implode(", ", $order_items);
        }
        
        // Create payments table if it doesn't exist
        createPaymentsTable($conn);
        
        // Check if payment is required for orders
        if (defined('REQUIRE_PAYMENT_FOR_ORDERS') && REQUIRE_PAYMENT_FOR_ORDERS && $total > 0) {
            // Redirect to payment page
            header("Location: payment_select.php?type=order&order_id=" . $pre_order_id . "&amount=" . $total);
            exit();
        } else {
            // No payment required, confirm order
            $conn->prepare("UPDATE pre_orders SET status = 'confirmed' WHERE id = ?")->execute([$pre_order_id]);
            $success = "Pre-order placed successfully! Order ID: #" . $pre_order_id . " | Total: ETB " . number_format($total, 2) . $items_summary;
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Failed to place pre-order: " . $e->getMessage();
    }
}
$categories = [];
foreach ($items as $item) {
    $categories[$item['category']][] = $item;
}

?>

<!DOCTYPE html>
<html>
<head>
<title>Pre-Order Food & Drinks</title>
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

.header-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.mobile-menu-btn {
    display: none;
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #667eea;
    cursor: pointer;
    padding: 10px;
    border-radius: 8px;
    transition: 0.3s;
}

.mobile-menu-btn:hover {
    background: rgba(102,126,234,0.1);
}

header h2 { 
    background: linear-gradient(45deg, #667eea, #764ba2); 
    -webkit-background-clip: text; 
    -webkit-text-fill-color: transparent; 
    font-size: 1.8rem;
}

/* Mobile Menu Styles */
.mobile-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 999;
    opacity: 0;
    visibility: hidden;
    transition: 0.3s;
}

.mobile-overlay.active {
    opacity: 1;
    visibility: visible;
}

.mobile-nav {
    position: fixed;
    top: 0;
    left: -100%;
    width: 280px;
    height: 100vh;
    background: rgba(255,255,255,0.98);
    backdrop-filter: blur(20px);
    z-index: 1000;
    padding: 20px;
    box-shadow: 2px 0 20px rgba(0,0,0,0.1);
    transition: 0.3s ease;
    overflow-y: auto;
}

.mobile-nav.active {
    left: 0;
}

.mobile-nav-close {
    display: block;
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #667eea;
    cursor: pointer;
    padding: 5px;
}

.mobile-nav-header {
    margin-bottom: 30px;
    padding-top: 40px;
    text-align: center;
}

.mobile-nav-header h3 {
    background: linear-gradient(45deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 1.2rem;
}

.mobile-nav a {
    display: block;
    color: #333;
    text-decoration: none;
    padding: 15px 20px;
    margin-bottom: 10px;
    border-radius: 10px;
    transition: 0.3s;
    background: rgba(102,126,234,0.1);
    border: 1px solid rgba(102,126,234,0.2);
}

.mobile-nav a:hover {
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    transform: translateX(5px);
}

.mobile-nav a i {
    margin-right: 10px;
    width: 20px;
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
.container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }

/* Messages */
.message { 
    padding: 15px; 
    border-radius: 10px; 
    margin-bottom: 20px; 
    text-align: center; 
    font-weight: 500;
}
.success { 
    background: rgba(40,167,69,0.1); 
    color: #28a745; 
    border: 2px solid rgba(40,167,69,0.3); 
}
.error { 
    background: rgba(220,53,69,0.1); 
    color: #dc3545; 
    border: 2px solid rgba(220,53,69,0.3); 
}

/* Booking Info */
.booking-info { 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px);
    padding: 20px; 
    border-radius: 15px; 
    margin-bottom: 20px; 
    text-align: center; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

/* Menu Sections - More compact */
.menu-section { 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px);
    border-radius: 12px; 
    padding: 20px; 
    margin-bottom: 15px; 
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}
.section-title { 
    font-size: 1.6rem; 
    text-align: center; 
    margin-bottom: 15px; 
    background: linear-gradient(45deg, #667eea, #764ba2); 
    -webkit-background-clip: text; 
    -webkit-text-fill-color: transparent; 
}

/* Menu Grid - Fixed 350x350 boxes */
.menu-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, 350px); 
    gap: 20px; 
    justify-content: center;
}

/* Menu Items - Fixed 350x350 Square Boxes */
.menu-item { 
    background: white; 
    border-radius: 15px; 
    padding: 20px; 
    box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
    transition: all 0.3s ease; 
    border: 2px solid transparent; 
    position: relative;
    overflow: hidden;
    width: 350px; /* Fixed width */
    height: 350px; /* Fixed height - perfect square */
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.menu-item:hover { 
    transform: translateY(-5px); 
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}
.menu-item.selected { 
    border-color: #667eea; 
    background: linear-gradient(135deg, rgba(102,126,234,0.1), rgba(118,75,162,0.1)); 
}
.menu-item.selected::before {
    content: '✓';
    position: absolute;
    top: 10px;
    right: 15px;
    background: #667eea;
    color: white;
    width: 25px;
    height: 25px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: bold;
    z-index: 10;
}

.item-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: flex-start;
    margin-bottom: 15px; 
    min-height: 45px;
}
.item-name { 
    font-weight: 600; 
    font-size: 1.1rem;
    color: #333;
    line-height: 1.3;
    flex: 1;
    margin-right: 10px;
    /* Limit text to 2 lines */
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}
.item-price { 
    color: #667eea; 
    font-weight: bold; 
    font-size: 1.2rem;
    white-space: nowrap;
}

.item-image { 
    width: 100%; 
    height: 180px; /* Fixed height for images within 350px box */
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 10px; 
    margin-bottom: 15px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-size: 2.5rem; 
    color: #adb5bd; 
    overflow: hidden;
    flex-shrink: 0;
}
.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 10px;
}

/* Quantity Controls - Fixed at bottom of 350px box */
.quantity-controls { 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    gap: 15px; 
    margin-top: auto;
    padding-top: 15px;
}
.quantity-btn { 
    width: 40px; 
    height: 40px; 
    border: none; 
    border-radius: 50%; 
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white; 
    cursor: pointer; 
    transition: all 0.3s ease; 
    font-size: 1.2rem;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.quantity-btn:hover { 
    transform: scale(1.1); 
    box-shadow: 0 5px 15px rgba(102,126,234,0.4);
}
.quantity-btn:active {
    transform: scale(0.95);
}
.quantity-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
}

.quantity-display { 
    font-weight: 600; 
    font-size: 1.3rem;
    min-width: 40px; 
    text-align: center; 
    background: rgba(102,126,234,0.1);
    padding: 8px 12px;
    border-radius: 20px;
    color: #667eea;
    flex-shrink: 0;
}

/* Cart Summary */
.cart-summary { 
    position: fixed; 
    bottom: 20px; 
    right: 20px; 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px);
    padding: 25px; 
    border-radius: 20px; 
    box-shadow: 0 15px 35px rgba(0,0,0,0.2); 
    min-width: 250px; 
    transform: translateY(120px); 
    opacity: 0; 
    transition: all 0.4s ease; 
    z-index: 1000;
}
.cart-summary.show { 
    transform: translateY(0); 
    opacity: 1; 
}

.cart-total { 
    font-size: 1.1rem; 
    font-weight: bold; 
    margin-bottom: 15px; 
    text-align: center; 
    color: #333;
}

.submit-btn { 
    width: 100%; 
    background: linear-gradient(45deg, #667eea, #764ba2); 
    color: white; 
    padding: 15px; 
    border: none; 
    border-radius: 12px; 
    font-weight: 600; 
    font-size: 1rem;
    cursor: pointer; 
    transition: all 0.3s ease; 
}
.submit-btn:hover { 
    transform: translateY(-2px); 
    box-shadow: 0 8px 20px rgba(102,126,234,0.4);
}
.submit-btn:disabled { 
    background: #ccc; 
    cursor: not-allowed; 
    transform: none; 
}

/* No Booking */
.no-booking { 
    text-align: center; 
    padding: 60px 40px; 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px);
    border-radius: 20px; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}
.book-table-btn { 
    display: inline-block; 
    background: linear-gradient(45deg, #667eea, #764ba2); 
    color: white; 
    padding: 15px 30px; 
    border-radius: 25px; 
    text-decoration: none; 
    margin-top: 20px; 
    transition: 0.3s; 
    font-weight: 600;
}
.book-table-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102,126,234,0.4);
}

/* Responsive - Maintain 350x350 on larger screens, scale down on mobile */
@media (max-width: 1200px) {
    .menu-grid { 
        grid-template-columns: repeat(auto-fit, 350px); 
        justify-content: center;
    }
}

@media (max-width: 768px) { 
    .container { padding: 0 15px; } 
    .menu-grid { 
        grid-template-columns: repeat(auto-fit, 320px); 
        gap: 15px; 
        justify-content: center;
    }
    .menu-item {
        width: 320px;
        height: 320px;
        padding: 18px;
    }
    .item-image {
        height: 160px;
    }
    .cart-summary { 
        position: relative; 
        bottom: auto; 
        right: auto; 
        margin: 20px 0; 
        transform: none; 
        opacity: 1; 
    }
    nav { 
        flex-direction: column; 
        gap: 8px; 
        align-items: center;
    }
    .mobile-menu-btn {
        display: block;
    }
    
    nav {
        display: none;
    }
    
    header { 
        flex-direction: row; 
        text-align: left; 
        gap: 15px;
    }
    .item-header {
        flex-direction: column;
        align-items: center;
        gap: 5px;
        min-height: 50px;
    }
    .item-name {
        text-align: center;
        margin-right: 0;
    }
}

/* Small screens - Scale down to fit */
@media (max-width: 480px) {
    .menu-grid { 
        grid-template-columns: 1fr; 
        gap: 15px; 
        justify-content: center;
    }
    .menu-item {
        width: 300px;
        height: 300px;
        padding: 15px;
        margin: 0 auto;
    }
    .item-image {
        height: 140px;
    }
    .item-header {
        min-height: 45px;
    }
    .item-name {
        font-size: 1rem;
    }
    .item-price {
        font-size: 1.1rem;
    }
    .quantity-btn {
        width: 35px;
        height: 35px;
        font-size: 1rem;
    }
    .quantity-display {
        font-size: 1.1rem;
        padding: 6px 10px;
    }
}

/* Very small screens */
@media (max-width: 360px) {
    .menu-item {
        width: 280px;
        height: 280px;
        padding: 12px;
    }
    .item-image {
        height: 120px;
    }
}

/* Loading Animation */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.fa-spinner {
    animation: spin 1s linear infinite;
}
</style>
</head>
<body>
<header>
    <div class="header-left">
        <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <h2><i class="fas fa-utensils"></i> Pre-Order Menu</h2>
    </div>
    <nav>
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="View_Tables.php"><i class="fas fa-chair"></i> Tables</a>
        <a href="view_menu.php"><i class="fas fa-utensils"></i> Food</a>
        <a href="view_drink_menu.php"><i class="fas fa-cocktail"></i> Drinks</a>
        <a href="my_bookings.php"><i class="fas fa-calendar-check"></i> Bookings</a>
        <a href="write_comment.php"><i class="fas fa-star"></i> Reviews</a>
        <a class="logout-btn" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</header>

<!-- Mobile Navigation -->
<div class="mobile-nav" id="mobileNav">
    <button class="mobile-nav-close" onclick="toggleMobileMenu()">
        <i class="fas fa-times"></i>
    </button>
    
    <div class="mobile-nav-header">
        <h3>Restaurant Menu</h3>
    </div>
    
    <a href="dashboard.php">
        <i class="fas fa-home"></i> Dashboard
    </a>
    
    <a href="View_Tables.php">
        <i class="fas fa-chair"></i> Tables
    </a>
    
    <a href="view_menu.php">
        <i class="fas fa-utensils"></i> Food
    </a>
    
    <a href="view_drink_menu.php">
        <i class="fas fa-cocktail"></i> Drinks
    </a>
    
    <a href="my_bookings.php">
        <i class="fas fa-calendar-check"></i> Bookings
    </a>
    
    <a href="write_comment.php">
        <i class="fas fa-star"></i> Reviews
    </a>
    
    <a href="../logout.php" style="background: linear-gradient(45deg, #ff6b6b, #ee5a24); color: white; margin-top: 20px;">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay" onclick="toggleMobileMenu()"></div>

<div class="container">
    <?php if ($success): ?>
        <div class="message success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="message error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>

    <?php if ($booking): ?>
        <div class="booking-info">
            <h3><i class="fas fa-calendar-check"></i> Your Booking Details</h3>
            <div style="margin-top: 10px; font-size: 1.1rem;">
                <strong>Table:</strong> <?= htmlspecialchars($booking['table_label'] ?? 'Table ' . $booking['table_id']) ?> | 
                <strong>Date:</strong> <?= date('M j, Y g:i A', strtotime($booking['booking_datetime'])) ?><br>
                <strong>Guests:</strong> <?= $booking['guests'] ?> | 
                <strong>Meal:</strong> <?= htmlspecialchars($booking['meal_type']) ?>
            </div>
        </div>

        <form method="POST" id="preOrderForm">
            <?php foreach ($categories as $category => $categoryItems): ?>
                <div class="menu-section">
                    <h2 class="section-title">
                        <i class="fas fa-<?= strtolower($category) === 'drink' ? 'cocktail' : 'utensils' ?>"></i> 
                        <?= htmlspecialchars($category) ?>
                    </h2>
                    <div class="menu-grid">
                        <?php foreach ($categoryItems as $item): ?>
                            <div class="menu-item" data-item-id="<?= $item['id'] ?>" data-price="<?= $item['price'] ?>">
                                <div class="item-header">
                                    <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                                    <div class="item-price">ETB <?= number_format($item['price'], 2) ?></div>
                                </div>
                                <div class="item-image">
                                    <?php if (!empty($item['image']) && file_exists("../uploads/menu_items/" . $item['image'])): ?>
                                        <img src="../uploads/menu_items/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                    <?php else: ?>
                                        <i class="fas fa-<?= strtolower($category) === 'drink' ? 'cocktail' : 'utensils' ?>"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="quantity-controls">
                                    <button type="button" class="quantity-btn minus" data-item-id="<?= $item['id'] ?>">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <span class="quantity-display" data-item-id="<?= $item['id'] ?>">0</span>
                                    <button type="button" class="quantity-btn plus" data-item-id="<?= $item['id'] ?>">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="cart-summary" id="cartSummary">
                <div class="cart-total" id="cartTotal">Total: ETB 0.00</div>
                <button type="submit" class="submit-btn" id="submitBtn" disabled>
                    <i class="fas fa-shopping-cart"></i> Place Pre-Order
                </button>
            </div>
        </form>

    <?php else: ?>
        <div class="no-booking">
            <i class="fas fa-calendar-times" style="font-size:4rem;color:#ddd;margin-bottom:20px;"></i>
            <h3>No Active Booking Found</h3>
            <p style="margin: 15px 0; color: #666; font-size: 1.1rem;">You need to book a table first before you can pre-order food and drinks.</p>
            <a href="View_Tables.php" class="book-table-btn">
                <i class="fas fa-chair"></i> Book a Table Now
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
let cart = {};
let total = 0;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize quantity controls
    document.querySelectorAll('.quantity-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const itemId = this.dataset.itemId;
            const menuItem = document.querySelector(`.menu-item[data-item-id="${itemId}"]`);
            const price = parseFloat(menuItem.dataset.price);
            const quantityDisplay = document.querySelector(`.quantity-display[data-item-id="${itemId}"]`);
            let quantity = parseInt(quantityDisplay.textContent) || 0;
            
            // Update quantity based on button clicked
            if (this.classList.contains('plus')) {
                quantity++;
                // Add visual feedback
                this.style.transform = 'scale(0.9)';
                setTimeout(() => this.style.transform = 'scale(1.1)', 100);
                setTimeout(() => this.style.transform = 'scale(1)', 200);
            } else if (this.classList.contains('minus') && quantity > 0) {
                quantity--;
                // Add visual feedback
                this.style.transform = 'scale(0.9)';
                setTimeout(() => this.style.transform = 'scale(1.1)', 100);
                setTimeout(() => this.style.transform = 'scale(1)', 200);
            }
            
            // Update display
            quantityDisplay.textContent = quantity;
            
            // Update cart
            if (quantity === 0) {
                delete cart[itemId];
                menuItem.classList.remove('selected');
            } else {
                cart[itemId] = { 
                    quantity: quantity, 
                    price: price,
                    name: menuItem.querySelector('.item-name').textContent
                };
                menuItem.classList.add('selected');
            }
            
            updateCartSummary();
        });
    });

    // Form submission
    document.getElementById('preOrderForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (Object.keys(cart).length === 0) {
            alert('Please select at least one item before placing your order.');
            return;
        }
        
        // Show loading state
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Order...';
        submitBtn.disabled = true;
        
        // Create hidden inputs for cart items
        const existingHiddenInputs = this.querySelector('.hidden-cart-inputs');
        if (existingHiddenInputs) {
            existingHiddenInputs.remove();
        }
        
        const hiddenInputsContainer = document.createElement('div');
        hiddenInputsContainer.className = 'hidden-cart-inputs';
        hiddenInputsContainer.style.display = 'none';
        
        let index = 0;
        for (let itemId in cart) {
            // Create hidden inputs for each item
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = `items[${index}][id]`;
            idInput.value = itemId;
            
            const quantityInput = document.createElement('input');
            quantityInput.type = 'hidden';
            quantityInput.name = `items[${index}][quantity]`;
            quantityInput.value = cart[itemId].quantity;
            
            hiddenInputsContainer.appendChild(idInput);
            hiddenInputsContainer.appendChild(quantityInput);
            index++;
        }
        
        this.appendChild(hiddenInputsContainer);
        
        // Submit the form
        setTimeout(() => {
            this.submit();
        }, 1000);
    });

    // Initialize cart summary
    updateCartSummary();
});

function updateCartSummary() {
    // Calculate total
    total = Object.values(cart).reduce((sum, item) => sum + (item.quantity * item.price), 0);
    
    // Update total display
    const cartTotalElement = document.getElementById('cartTotal');
    
    // Show/hide cart summary
    const cartSummary = document.getElementById('cartSummary');
    const submitBtn = document.getElementById('submitBtn');
    
    if (total > 0) {
        cartSummary.classList.add('show');
        submitBtn.disabled = false;
        
        // Add item count to total display
        const itemCount = Object.values(cart).reduce((sum, item) => sum + item.quantity, 0);
        cartTotalElement.innerHTML = `
            <div style="font-size: 0.9rem; color: #666; margin-bottom: 8px;">
                <i class="fas fa-shopping-cart"></i> ${itemCount} item${itemCount !== 1 ? 's' : ''} selected
            </div>
            <div style="font-size: 1.3rem; color: #667eea;">Total: ETB ${total.toFixed(2)}</div>
        `;
    } else {
        cartSummary.classList.remove('show');
        submitBtn.disabled = true;
        cartTotalElement.innerHTML = 'Total: ETB 0.00';
    }
}

// Mobile menu toggle function
function toggleMobileMenu() {
    const mobileNav = document.getElementById('mobileNav');
    const overlay = document.getElementById('mobileOverlay');
    
    if (!mobileNav || !overlay) {
        console.error('Mobile menu elements not found');
        return;
    }
    
    mobileNav.classList.toggle('active');
    overlay.classList.toggle('active');
}

// Handle window resize for mobile menu
window.addEventListener('resize', function() {
    const mobileNav = document.getElementById('mobileNav');
    const overlay = document.getElementById('mobileOverlay');
    
    if (window.innerWidth > 768 && mobileNav && overlay) {
        mobileNav.classList.remove('active');
        overlay.classList.remove('active');
    }
});

// Add entrance animations
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu items click handlers
    const mobileMenuItems = document.querySelectorAll('.mobile-nav a');
    mobileMenuItems.forEach(item => {
        item.addEventListener('click', function() {
            // Close mobile menu when clicking on menu items
            if (window.innerWidth <= 768) {
                setTimeout(() => {
                    toggleMobileMenu();
                }, 200);
            }
        });
    });

    const menuItems = document.querySelectorAll('.menu-item');
    menuItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(30px)';
        item.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        
        setTimeout(() => {
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, 100 + (index * 100));
    });
});
</script>
</body>
</html>