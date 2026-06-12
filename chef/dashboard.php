<?php
session_start();
require_once "../connection.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'chef') {
    header("Location: ../login.php");
    exit;
}

$chef_name = $_SESSION['name'] ?? 'Chef';
$chef_id = $_SESSION['user_id'];

// PAYMENT FILTERING: Chef dashboard only shows statistics for PAID orders
// - Orders with total_amount = 0 (free orders) are always counted
// - Orders with total_amount > 0 are only counted if payment is completed OR order is confirmed
// - Orders with status 'pending_payment' (user clicked "Not Now") are EXCLUDED from statistics
// - This ensures chef only sees and prepares food for orders that are guaranteed to be paid

// Get dashboard statistics - Only count PAID orders
try {
    // Pending orders (only paid ones)
    $pending_orders = $conn->query("
        SELECT COUNT(*) FROM pre_orders po
        LEFT JOIN payments p ON po.id = p.order_id AND p.payment_type = 'order'
        WHERE po.status = 'pending' 
        AND po.status != 'pending_payment'
        AND (
            po.total_amount = 0 
            OR (po.total_amount > 0 AND p.status = 'completed')
            OR (po.total_amount > 0 AND po.status = 'confirmed')
        )
    ")->fetchColumn();
    
    // Orders in progress
    $in_progress = $conn->query("SELECT COUNT(*) FROM kitchen_orders WHERE preparation_status = 'in_progress'")->fetchColumn();
    
    // Orders ready
    $ready_orders = $conn->query("SELECT COUNT(*) FROM kitchen_orders WHERE preparation_status = 'ready'")->fetchColumn();
    
    // Unread reports
    $unread_reports = $conn->query("SELECT COUNT(*) FROM reports WHERE receiver_id = ? AND status = 'unread'")->prepare();
    $unread_reports->execute([$chef_id]);
    $unread_reports = $unread_reports->fetchColumn();
    
    // Today's completed orders
    $today_completed = $conn->query("SELECT COUNT(*) FROM kitchen_orders WHERE preparation_status = 'served' AND DATE(completed_at) = CURDATE()")->fetchColumn();
    
    // Recent orders with payment status - Only show PAID orders
    $recent_orders = $conn->query("
        SELECT po.*, b.booking_datetime, u.name as customer_name, rt.table_label,
               ko.preparation_status, ko.estimated_time, ko.chef_notes,
               p.payment_reference, p.status as payment_status, p.payment_method
        FROM pre_orders po
        JOIN bookings b ON po.booking_id = b.id
        JOIN users u ON b.user_id = u.id
        LEFT JOIN restaurant_tables rt ON b.table_id = rt.id
        LEFT JOIN kitchen_orders ko ON po.id = ko.pre_order_id
        LEFT JOIN payments p ON po.id = p.order_id AND p.payment_type = 'order'
        WHERE po.status IN ('pending', 'confirmed', 'preparing')
        AND po.status != 'pending_payment'
        AND (
            po.total_amount = 0 
            OR (po.total_amount > 0 AND p.status = 'completed')
            OR (po.total_amount > 0 AND po.status = 'confirmed')
        )
        ORDER BY po.created_at DESC
        LIMIT 5
    ")->fetchAll();
    
} catch (Exception $e) {
    $pending_orders = $in_progress = $ready_orders = $unread_reports = $today_completed = 0;
    $recent_orders = [];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Chef Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #ff9a56 0%, #ff6b35 100%);
    min-height: 100vh;
}

/* Header */
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
    color: #ff9a56;
    cursor: pointer;
    padding: 10px;
    border-radius: 8px;
    transition: 0.3s;
}

.mobile-menu-btn:hover {
    background: rgba(255,154,86,0.1);
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
.logout-btn { 
    background: linear-gradient(45deg, #ff6b6b, #ee5a24); 
    color: white; 
    padding: 12px 20px; 
    text-decoration: none; 
    border-radius: 25px; 
    font-weight: 500; 
    transition: 0.3s; 
    box-shadow: 0 4px 15px rgba(238,90,36,0.3); 
}
.logout-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(238,90,36,0.4); }

/* Main Layout */
.main-layout { display: flex; max-width: 1400px; margin: 30px auto; gap: 30px; padding: 0 20px; }
.sidebar { 
    width: 300px; 
    transition: 0.3s;
}
.content { flex: 1; }

/* Stats Grid */
.stats-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
    gap: 20px; 
    margin-bottom: 30px; 
}
.stat-card { 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px); 
    padding: 25px; 
    border-radius: 15px; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
    transition: 0.3s; 
    border: 1px solid rgba(255,255,255,0.3); 
}
.stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.15); }
.stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.stat-icon { 
    width: 50px; 
    height: 50px; 
    border-radius: 50%; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-size: 1.5rem; 
    color: white; 
}
.stat-icon.pending { background: linear-gradient(45deg, #ffa726, #fb8c00); }
.stat-icon.progress { background: linear-gradient(45deg, #42a5f5, #1e88e5); }
.stat-icon.ready { background: linear-gradient(45deg, #66bb6a, #43a047); }
.stat-icon.reports { background: linear-gradient(45deg, #ab47bc, #8e24aa); }
.stat-icon.completed { background: linear-gradient(45deg, #26c6da, #00acc1); }
.stat-number { font-size: 2rem; font-weight: bold; color: #333; margin-bottom: 5px; }
.stat-label { color: #666; font-size: 0.9rem; }

/* Chef Menu */
.chef-menu { 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px); 
    border-radius: 15px; 
    padding: 25px; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
    border: 1px solid rgba(255,255,255,0.3); 
    margin-bottom: 20px;
}
.menu-title { 
    font-size: 1.3rem; 
    font-weight: bold; 
    color: #333; 
    margin-bottom: 20px; 
    text-align: center; 
}
.menu-item { 
    display: block; 
    text-decoration: none; 
    color: #333; 
    padding: 15px 20px; 
    margin-bottom: 10px; 
    border-radius: 10px; 
    transition: 0.3s; 
    background: rgba(255,154,86,0.1); 
    border: 1px solid rgba(255,154,86,0.2); 
}
.menu-item:hover { 
    background: linear-gradient(45deg, #ff9a56, #ff6b35); 
    color: white; 
    transform: translateX(5px); 
}
.menu-item i { margin-right: 10px; width: 20px; }

/* Recent Activity */
.recent-activity { 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px); 
    border-radius: 15px; 
    padding: 25px; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
    border: 1px solid rgba(255,255,255,0.3); 
}
.activity-title { 
    font-size: 1.3rem; 
    font-weight: bold; 
    color: #333; 
    margin-bottom: 20px; 
    text-align: center; 
}
.activity-item { 
    display: flex; 
    align-items: center; 
    padding: 12px 0; 
    border-bottom: 1px solid rgba(0,0,0,0.05); 
}
.activity-item:last-child { border-bottom: none; }
.activity-icon { 
    width: 35px; 
    height: 35px; 
    border-radius: 50%; 
    background: linear-gradient(45deg, #ff9a56, #ff6b35); 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    color: white; 
    margin-right: 15px; 
    font-size: 0.9rem; 
}
.activity-content { flex: 1; }
.activity-text { font-size: 0.9rem; color: #333; margin-bottom: 2px; }
.activity-time { font-size: 0.8rem; color: #666; }
.activity-status { 
    padding: 4px 8px; 
    border-radius: 12px; 
    font-size: 0.7rem; 
    font-weight: 500; 
    margin-left: 10px;
}
.status-pending { background: #fff3cd; color: #856404; }
.status-confirmed { background: #d4edda; color: #155724; }
.status-preparing { background: #cce5ff; color: #004085; }

/* Payment Status Styles */
.payment-info {
    margin-top: 5px;
}
.payment-status {
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.payment-status.paid {
    background: linear-gradient(45deg, #4caf50, #388e3c);
    color: white;
}
.payment-status.pending {
    background: linear-gradient(45deg, #ff9800, #f57c00);
    color: white;
}
.payment-status.not-paid {
    background: linear-gradient(45deg, #f44336, #d32f2f);
    color: white;
    animation: pulse-warning 2s infinite;
}
@keyframes pulse-warning {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

/* Welcome Section */
.welcome-section { 
    text-align: center; 
    margin-bottom: 30px; 
    color: white; 
}
.welcome-title { 
    font-size: 3rem; 
    font-weight: bold; 
    margin-bottom: 10px; 
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3); 
}
.welcome-subtitle { 
    font-size: 1.2rem; 
    opacity: 0.9; 
}

/* Quick Actions */
.quick-actions {
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px); 
    border-radius: 15px; 
    padding: 25px; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
    border: 1px solid rgba(255,255,255,0.3); 
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 15px;
    background: linear-gradient(45deg, #ff9a56, #ff6b35);
    color: white;
    text-decoration: none;
    border-radius: 10px;
    font-weight: 500;
    transition: 0.3s;
    box-shadow: 0 4px 15px rgba(255,154,86,0.3);
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255,154,86,0.4);
}

/* Responsive */
@media (max-width: 1024px) { 
    .main-layout { flex-direction: column; } 
    .sidebar { width: 100%; } 
}
@media (max-width: 768px) { 
    .stats-grid { grid-template-columns: 1fr; } 
    
    .header-left h1 {
        font-size: 1.5rem;
    }
    
    .mobile-menu-btn {
        display: block;
    }
    
    .sidebar {
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
    
    .sidebar.active {
        left: 0;
    }
    
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
    
    .sidebar-close {
        display: block;
        position: absolute;
        top: 15px;
        right: 15px;
        background: none;
        border: none;
        font-size: 1.5rem;
        color: #ff9a56;
        cursor: pointer;
        padding: 5px;
    }
    
    .welcome-title { font-size: 2rem; } 
}
</style>
</head>
<body>
<header>
    <div class="header-left">
        <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <h1><i class="fas fa-chef-hat"></i> Chef Dashboard</h1>
    </div>
    <a href="../logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</header>

<div class="welcome-section">
    <h1 class="welcome-title">Welcome Back, <?= htmlspecialchars($chef_name) ?></h1>
    <p class="welcome-subtitle">Manage kitchen operations and orders</p>
</div>

<div class="main-layout">
    <div class="content">
        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= number_format($pending_orders) ?></div>
                        <div class="stat-label">Pending Orders</div>
                    </div>
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= number_format($in_progress) ?></div>
                        <div class="stat-label">In Progress</div>
                    </div>
                    <div class="stat-icon progress">
                        <i class="fas fa-fire"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= number_format($ready_orders) ?></div>
                        <div class="stat-label">Ready to Serve</div>
                    </div>
                    <div class="stat-icon ready">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= number_format($unread_reports) ?></div>
                        <div class="stat-label">Unread Reports</div>
                    </div>
                    <div class="stat-icon reports">
                        <i class="fas fa-envelope"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= number_format($today_completed) ?></div>
                        <div class="stat-label">Today's Completed</div>
                    </div>
                    <div class="stat-icon completed">
                        <i class="fas fa-trophy"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="menu-title">Quick Actions</div>
            <div class="actions-grid">
                <a href="view_orders.php" class="action-btn">
                    <i class="fas fa-list"></i> View All Orders
                </a>
                <a href="kitchen_management.php" class="action-btn">
                    <i class="fas fa-fire"></i> Kitchen Management
                </a>
                <a href="reports_analytics.php" class="action-btn">
                    <i class="fas fa-chart-line"></i> Reports & Analytics
                </a>
                <a href="send_report.php" class="action-btn">
                    <i class="fas fa-paper-plane"></i> Send Report to Admin
                </a>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="recent-activity">
            <div class="activity-title">Recent Orders</div>
            <?php if (empty($recent_orders)): ?>
                <div style="text-align: center; color: #666; padding: 20px;">
                    <i class="fas fa-utensils" style="font-size: 2rem; margin-bottom: 10px;"></i>
                    <p>No recent orders found</p>
                </div>
            <?php else: ?>
                <?php foreach ($recent_orders as $order): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">
                                <strong>Order #<?= $order['id'] ?></strong> - 
                                <?= htmlspecialchars($order['customer_name']) ?> 
                                (<?= htmlspecialchars($order['table_label'] ?? 'Table ' . $order['id']) ?>)
                            </div>
                            <div class="activity-time">
                                <?= date('M j, Y g:i A', strtotime($order['booking_datetime'])) ?> 
                                - ETB <?= number_format($order['total_amount'], 2) ?>
                            </div>
                            <div class="payment-info">
                                <?php if ($order['payment_status'] === 'completed'): ?>
                                    <span class="payment-status paid">
                                        <i class="fas fa-check-circle"></i> Paid
                                    </span>
                                <?php elseif ($order['payment_status'] === 'pending'): ?>
                                    <span class="payment-status pending">
                                        <i class="fas fa-clock"></i> Payment Pending
                                    </span>
                                <?php else: ?>
                                    <span class="payment-status not-paid">
                                        <i class="fas fa-exclamation-triangle"></i> Not Paid
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="activity-status status-<?= $order['preparation_status'] ?? 'pending' ?>">
                            <?= ucfirst($order['preparation_status'] ?? 'pending') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="sidebar" id="sidebar">
        <button class="sidebar-close" onclick="toggleMobileMenu()" style="display: none;">
            <i class="fas fa-times"></i>
        </button>
        
        <!-- Chef Menu -->
        <div class="chef-menu">
            <div class="menu-title">Kitchen Controls</div>
            
            <a href="view_orders.php" class="menu-item">
                <i class="fas fa-list-alt"></i>
                View All Orders
            </a>
            
            <a href="kitchen_management.php" class="menu-item">
                <i class="fas fa-fire"></i>
                Kitchen Management
            </a>
            
            <a href="menu_analysis.php" class="menu-item">
                <i class="fas fa-utensils"></i>
                Menu Analysis
            </a>
            
            <a href="reports_analytics.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                Reports & Analytics
            </a>
            
            <a href="send_report.php" class="menu-item">
                <i class="fas fa-paper-plane"></i>
                Send Report to Admin
            </a>
            
            <a href="receive_reports.php" class="menu-item">
                <i class="fas fa-inbox"></i>
                Receive Reports
                <?php if ($unread_reports > 0): ?>
                    <span style="background: #ff6b35; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; margin-left: 10px;">
                        <?= $unread_reports ?>
                    </span>
                <?php endif; ?>
            </a>
        </div>
    </div>
</div>

<script>
// Add entrance animations
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.stat-card, .chef-menu, .recent-activity, .quick-actions');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 + (index * 100));
    });

    // Add click animations to menu items
    const menuItems = document.querySelectorAll('.menu-item, .action-btn');
    menuItems.forEach(item => {
        item.addEventListener('click', function() {
            this.style.transform = 'scale(0.95) translateX(5px)';
            setTimeout(() => {
                this.style.transform = 'translateX(5px)';
            }, 150);
            
            // Close mobile menu when clicking on menu items
            if (window.innerWidth <= 768 && item.classList.contains('menu-item')) {
                setTimeout(() => {
                    toggleMobileMenu();
                }, 200);
            }
        });
    });

    // Auto-refresh stats every 30 seconds
    setInterval(() => {
        location.reload();
    }, 30000);
});

// Mobile menu toggle function
function toggleMobileMenu() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    const closeBtn = document.querySelector('.sidebar-close');
    
    if (!overlay) {
        console.error('Mobile overlay not found');
        return;
    }
    
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
    
    // Show/hide close button on mobile
    if (window.innerWidth <= 768) {
        closeBtn.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
    }
}

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    const closeBtn = document.querySelector('.sidebar-close');
    
    if (window.innerWidth > 768) {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        closeBtn.style.display = 'none';
    }
});
</script>

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay" onclick="toggleMobileMenu()"></div>

</body>
</html>