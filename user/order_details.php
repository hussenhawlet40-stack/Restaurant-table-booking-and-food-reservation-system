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
$order_id = intval($_GET['order_id'] ?? 0);

if ($order_id <= 0) {
    header("Location: my_orders.php");
    exit();
}

// Get order details with all related information
$stmt = $conn->prepare("
    SELECT po.*, b.booking_datetime, b.meal_type, b.guests, b.special_requests,
           rt.table_label, rt.location, rt.capacity,
           u.name as user_name, u.email as user_email,
           p.payment_reference, p.status as payment_status, p.payment_method, 
           p.expires_at, p.transaction_id, p.created_at as payment_created
    FROM pre_orders po
    JOIN bookings b ON po.booking_id = b.id
    LEFT JOIN restaurant_tables rt ON b.table_id = rt.id
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN payments p ON po.id = p.order_id AND p.payment_type = 'order'
    WHERE po.id = ? AND b.user_id = ?
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: my_orders.php");
    exit();
}

// Get order items
$stmt = $conn->prepare("
    SELECT poi.*, mi.name, mi.category, mi.image
    FROM pre_order_items poi
    JOIN menu_items mi ON poi.menu_item_id = mi.id
    WHERE poi.pre_order_id = ?
    ORDER BY mi.category, mi.name
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Order Details #<?= $order_id ?> - Ethiopian Restaurant</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100vh; }
        
        header { background: rgba(255,255,255,0.95); padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        nav a { color: #333; text-decoration: none; margin: 0 10px; padding: 8px 15px; border-radius: 20px; transition: 0.3s; }
        nav a:hover { background: #667eea; color: white; }
        .logout-btn { background: #ff6b6b; color: white !important; }
        
        .container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        .page-title { text-align: center; color: white; font-size: 2.5rem; margin-bottom: 30px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); }
        
        .order-card { background: rgba(255,255,255,0.95); border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); margin-bottom: 20px; }
        
        .order-header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #eee; }
        .order-number { font-size: 2rem; font-weight: bold; color: #667eea; margin-bottom: 10px; }
        .order-status { display: inline-block; padding: 8px 20px; border-radius: 25px; font-weight: bold; }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-preparing { background: #cce5ff; color: #004085; }
        .status-ready { background: #d1ecf1; color: #0c5460; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
        .detail-section { background: #f8f9fa; padding: 20px; border-radius: 15px; }
        .section-title { font-size: 1.2rem; font-weight: bold; color: #333; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 10px; padding: 5px 0; }
        .detail-label { font-weight: 500; color: #666; }
        .detail-value { color: #333; }
        
        .items-section { margin-bottom: 30px; }
        .items-grid { display: grid; gap: 15px; }
        .item-card { background: #f8f9fa; padding: 15px; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; }
        .item-info { display: flex; align-items: center; gap: 15px; }
        .item-image { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; background: #ddd; }
        .item-details h4 { margin: 0; color: #333; }
        .item-details p { margin: 5px 0 0 0; color: #666; font-size: 0.9rem; }
        .item-price { text-align: right; }
        .item-quantity { font-weight: bold; color: #667eea; }
        .item-total { font-weight: bold; color: #333; }
        
        .order-total { background: linear-gradient(45deg, #667eea, #764ba2); color: white; padding: 20px; border-radius: 15px; text-align: center; margin-bottom: 30px; }
        .total-amount { font-size: 2rem; font-weight: bold; margin-bottom: 5px; }
        .total-label { opacity: 0.9; }
        
        .payment-section { background: #e3f2fd; padding: 20px; border-radius: 15px; margin-bottom: 30px; }
        .payment-status { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.9rem; font-weight: 500; }
        
        .actions { display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; }
        .btn { padding: 12px 25px; border: none; border-radius: 25px; cursor: pointer; font-size: 1rem; transition: 0.3s; text-decoration: none; display: inline-block; text-align: center; font-weight: 500; }
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.2); }

        /* Three-dot dropdown menu styles */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-toggle {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .dropdown-toggle:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            min-width: 200px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateX(-50%) translateY(-10px);
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-top: 5px;
        }

        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
        }

        .dropdown-item {
            display: block;
            width: 100%;
            padding: 15px 20px;
            text-decoration: none;
            color: #333;
            border: none;
            background: none;
            text-align: left;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 500;
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item:hover {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            transform: translateX(5px);
        }

        .dropdown-item i {
            margin-right: 12px;
            width: 18px;
            text-align: center;
        }

        .dropdown-item.pay-item {
            color: #28a745;
            font-weight: 600;
        }

        .dropdown-item.pay-item:hover {
            background: linear-gradient(45deg, #28a745, #218838);
            color: white;
        }

        .dropdown-item.delete-item {
            color: #dc3545;
        }

        .dropdown-item.delete-item:hover {
            background: linear-gradient(45deg, #dc3545, #c82333);
            color: white;
        }
        
        .back-link { display: inline-block; margin-top: 20px; color: white; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        
        @media (max-width: 768px) {
            .container { margin: 20px; padding: 10px; }
            .details-grid { grid-template-columns: 1fr; }
            .actions { flex-direction: column; }
        }
    </style>
</head>
<body>

<header>
    <h2>Welcome, <?php echo htmlspecialchars($user_name); ?></h2>
    <nav>
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="my_orders.php"><i class="fas fa-receipt"></i> My Orders</a>
        <a href="my_bookings.php"><i class="fas fa-calendar-check"></i> Bookings</a>
        <a href="View_Tables.php"><i class="fas fa-chair"></i> Tables</a>
        <a class="logout-btn" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</header>

<div class="container">
    <h1 class="page-title">Order Details</h1>
    
    <div class="order-card">
        <div class="order-header">
            <div class="order-number">Order #<?= $order_id ?></div>
            <div class="order-status status-<?= $order['status'] ?>">
                <?= ucfirst($order['status']) ?>
            </div>
        </div>
        
        <div class="details-grid">
            <div class="detail-section">
                <div class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Order Information
                </div>
                <div class="detail-row">
                    <span class="detail-label">Order Date:</span>
                    <span class="detail-value"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Customer:</span>
                    <span class="detail-value"><?= htmlspecialchars($order['user_name'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value"><?= htmlspecialchars($order['user_email'] ?? 'N/A') ?></span>
                </div>
            </div>
            
            <div class="detail-section">
                <div class="section-title">
                    <i class="fas fa-calendar-alt"></i>
                    Booking Information
                </div>
                <div class="detail-row">
                    <span class="detail-label">Table:</span>
                    <span class="detail-value"><?= htmlspecialchars($order['table_label'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Location:</span>
                    <span class="detail-value"><?= htmlspecialchars($order['location'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date & Time:</span>
                    <span class="detail-value"><?= date('M j, Y g:i A', strtotime($order['booking_datetime'])) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Meal Type:</span>
                    <span class="detail-value"><?= htmlspecialchars($order['meal_type'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Guests:</span>
                    <span class="detail-value"><?= $order['guests'] ?> people</span>
                </div>
            </div>
        </div>
        
        <?php if ($order['special_requests']): ?>
        <div class="detail-section" style="margin-bottom: 30px;">
            <div class="section-title">
                <i class="fas fa-comment"></i>
                Special Requests
            </div>
            <p style="color: #666; line-height: 1.6;"><?= htmlspecialchars($order['special_requests'] ?? 'No special requests') ?></p>
        </div>
        <?php endif; ?>
        
        <div class="items-section">
            <div class="section-title">
                <i class="fas fa-utensils"></i>
                Ordered Items
            </div>
            <div class="items-grid">
                <?php foreach ($order_items as $item): ?>
                <div class="item-card">
                    <div class="item-info">
                        <?php if ($item['image']): ?>
                            <img src="../uploads/menu_items/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name'] ?? 'Menu Item') ?>" class="item-image">
                        <?php else: ?>
                            <div class="item-image" style="display: flex; align-items: center; justify-content: center; color: #999;">
                                <i class="fas fa-utensils"></i>
                            </div>
                        <?php endif; ?>
                        <div class="item-details">
                            <h4><?= htmlspecialchars($item['name'] ?? 'Unknown Item') ?></h4>
                            <p><?= htmlspecialchars($item['category'] ?? 'N/A') ?></p>
                        </div>
                    </div>
                    <div class="item-price">
                        <div class="item-quantity"><?= $item['quantity'] ?>x</div>
                        <div class="item-total">ETB <?= number_format($item['price'] * $item['quantity'], 2) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="order-total">
            <div class="total-amount">ETB <?= number_format($order['total_amount'], 2) ?></div>
            <div class="total-label">Total Amount</div>
        </div>
        
        <?php if ($order['payment_reference']): ?>
        <div class="payment-section">
            <div class="section-title">
                <i class="fas fa-credit-card"></i>
                Payment Information
            </div>
            <div class="payment-status">
                <span>Status:</span>
                <?php 
                $payment_info = formatPaymentStatus($order['payment_status']);
                $method_info = formatPaymentMethod($order['payment_method']);
                ?>
                <span class="status-badge <?= $payment_info['class'] ?>">
                    <i class="<?= $payment_info['icon'] ?>"></i> <?= $payment_info['text'] ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Payment Method:</span>
                <span class="detail-value">
                    <i class="<?= $method_info['icon'] ?>"></i> <?= $method_info['text'] ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Payment Reference:</span>
                <span class="detail-value" style="font-family: monospace;"><?= htmlspecialchars($order['payment_reference'] ?? 'N/A') ?></span>
            </div>
            <?php if ($order['transaction_id']): ?>
            <div class="detail-row">
                <span class="detail-label">Transaction ID:</span>
                <span class="detail-value" style="font-family: monospace;"><?= htmlspecialchars($order['transaction_id'] ?? 'N/A') ?></span>
            </div>
            <?php endif; ?>
            <?php if ($order['payment_created']): ?>
            <div class="detail-row">
                <span class="detail-label">Payment Date:</span>
                <span class="detail-value"><?= date('M j, Y g:i A', strtotime($order['payment_created'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="actions">
            <?php 
            // Check if payment is needed
            $needs_payment = false;
            $order_expired = false;
            $can_delete = false;
            
            if (REQUIRE_PAYMENT_FOR_ORDERS && $order['total_amount'] > 0) {
                if (!$order['payment_reference'] || in_array($order['payment_status'], ['pending', 'failed', 'cancelled', 'expired'])) {
                    $needs_payment = true;
                }
            }
            
            // Check if order is expired (3+ hours from creation)
            $order_created_time = strtotime($order['created_at']);
            $current_time = time();
            $order_expired = ($current_time - $order_created_time) >= (3 * 3600);
            
            // Check if order can be deleted
            if ($order['status'] === 'cancelled' || $order_expired || in_array($order['status'], ['ready', 'completed'])) {
                $can_delete = true;
            }
            ?>
            
            <!-- Three-dot Menu -->
            <div class="dropdown">
                <button class="dropdown-toggle" onclick="toggleDropdown()">
                    <i class="fas fa-ellipsis-v"></i>
                    Actions
                </button>
                <div class="dropdown-menu" id="dropdown-menu">
                    <!-- Pay Now - Only show if payment is needed and order is not expired -->
                    <?php if ($needs_payment && !$order_expired && $order['status'] !== 'cancelled'): ?>
                        <a href="payment_select.php?type=order&order_id=<?= $order_id ?>&amount=<?= $order['total_amount'] ?>" class="dropdown-item pay-item">
                            <i class="fas fa-credit-card"></i> Pay Now - ETB <?= number_format($order['total_amount'], 2) ?>
                        </a>
                    <?php endif; ?>
                    
                    <!-- View Details (current page - could be refresh or different view) -->
                    <a href="order_details.php?order_id=<?= $order_id ?>" class="dropdown-item">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                    
                    <!-- Delete Order - Only show if order can be deleted -->
                    <?php if ($can_delete): ?>
                        <button type="button" class="dropdown-item delete-item" onclick="deleteOrder(<?= $order_id ?>)">
                            <i class="fas fa-trash"></i> Delete Order
                        </button>
                    <?php endif; ?>
                    
                    <!-- Back to Orders -->
                    <a href="my_orders.php" class="dropdown-item">
                        <i class="fas fa-arrow-left"></i> Back to Orders
                    </a>
                    
                    <!-- Order Again -->
                    <a href="pre_order.php" class="dropdown-item">
                        <i class="fas fa-redo"></i> Order Again
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <a href="my_orders.php" class="back-link">← Back to My Orders</a>
</div>

<script>
// Three-dot dropdown toggle function
function toggleDropdown() {
    const dropdown = document.getElementById('dropdown-menu');
    dropdown.classList.toggle('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.dropdown')) {
        document.getElementById('dropdown-menu').classList.remove('show');
    }
});

// Delete order function
function deleteOrder(orderId) {
    if (confirm('⚠️ PERMANENTLY DELETE this order?\n\nThis will remove:\n• Order #' + orderId + '\n• All order items\n• Payment records\n• Kitchen records\n\nThis action CANNOT be undone!\n\nAre you sure you want to delete this order?')) {
        // Create a form to submit the delete request
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'my_orders.php'; // Redirect to my_orders.php to handle deletion
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        
        const orderIdInput = document.createElement('input');
        orderIdInput.type = 'hidden';
        orderIdInput.name = 'order_id';
        orderIdInput.value = orderId;
        
        form.appendChild(actionInput);
        form.appendChild(orderIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

</body>
</html>