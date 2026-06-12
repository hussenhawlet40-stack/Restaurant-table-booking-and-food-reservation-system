<?php
session_start();
require_once "../connection.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

$admin_name = $_SESSION['name'] ?? 'Admin';

// Get dashboard statistics
try {
    // Total users
    $users_count = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    
    // Total bookings
    $bookings_count = $conn->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    
    // Today's bookings
    $today_bookings = $conn->query("SELECT COUNT(*) FROM bookings WHERE DATE(booking_datetime) = CURDATE()")->fetchColumn();
    
    // Total revenue (from pre-orders)
    $revenue = $conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM pre_orders WHERE status != 'cancelled'")->fetchColumn();
    
    // Recent bookings
    $recent_bookings = $conn->query("SELECT b.*, u.name as user_name, rt.table_label 
                                    FROM bookings b 
                                    LEFT JOIN users u ON b.user_id = u.id 
                                    LEFT JOIN restaurant_tables rt ON b.table_id = rt.id 
                                    ORDER BY b.created_at DESC LIMIT 5")->fetchAll();
    
    // Pending orders
    $pending_orders = $conn->query("SELECT COUNT(*) FROM pre_orders WHERE status = 'pending'")->fetchColumn();
    
    // Reviews waiting for responses
    $pending_responses = $conn->query("
        SELECT COUNT(*) FROM comments c 
        LEFT JOIN comment_replies cr ON c.id = cr.comment_id 
        WHERE cr.id IS NULL
    ")->fetchColumn();
    
} catch (Exception $e) {
    $users_count = $bookings_count = $today_bookings = $revenue = $pending_orders = $pending_responses = 0;
    $recent_bookings = [];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
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

.header-right {
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

header h1 { 
    background: linear-gradient(45deg, #667eea, #764ba2); 
    -webkit-background-clip: text; 
    -webkit-text-fill-color: transparent; 
    font-size: 2rem; 
}

/* Header Admin Controls Dropdown */
.admin-controls-dropdown {
    position: relative;
}

.admin-controls-btn {
    background: rgba(102,126,234,0.1);
    border: 1px solid rgba(102,126,234,0.2);
    color: #667eea;
    padding: 12px 15px;
    border-radius: 25px;
    cursor: pointer;
    font-size: 1rem;
    transition: 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.admin-controls-btn:hover {
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102,126,234,0.3);
}

.admin-dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 15px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
    border: 1px solid rgba(0,0,0,0.1);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    min-width: 280px;
    max-height: 0;
    overflow: hidden;
}

.admin-dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(5px);
    max-height: 500px;
}

.admin-dropdown-header {
    padding: 15px 20px;
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    font-weight: bold;
    border-top-left-radius: 15px;
    border-top-right-radius: 15px;
    text-align: center;
}

.admin-dropdown-item {
    display: block;
    padding: 12px 20px;
    color: #333;
    text-decoration: none;
    transition: 0.2s;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.admin-dropdown-item:hover {
    background: rgba(102,126,234,0.1);
    color: #667eea;
    padding-left: 25px;
}

.admin-dropdown-item:last-child {
    border-bottom: none;
    border-bottom-left-radius: 15px;
    border-bottom-right-radius: 15px;
}

.admin-dropdown-item i {
    margin-right: 12px;
    width: 20px;
    color: #667eea;
}

.admin-dropdown-divider {
    height: 1px;
    background: rgba(0,0,0,0.1);
    margin: 8px 0;
}
.admin-controls-dropdown {
    position: relative;
}

.admin-controls-btn {
    background: rgba(102,126,234,0.1);
    border: 1px solid rgba(102,126,234,0.2);
    color: #667eea;
    padding: 12px 15px;
    border-radius: 25px;
    cursor: pointer;
    font-size: 1.2rem;
    transition: 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.admin-controls-btn:hover {
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102,126,234,0.3);
}

.admin-dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 15px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
    border: 1px solid rgba(0,0,0,0.1);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    min-width: 280px;
    max-height: 0;
    overflow: hidden;
}

.admin-dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(5px);
    max-height: 500px;
}

.admin-dropdown-header {
    padding: 15px 20px;
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    font-weight: bold;
    border-top-left-radius: 15px;
    border-top-right-radius: 15px;
    text-align: center;
}

.admin-dropdown-item {
    display: block;
    padding: 12px 20px;
    color: #333;
    text-decoration: none;
    transition: 0.2s;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.admin-dropdown-item:hover {
    background: rgba(102,126,234,0.1);
    color: #667eea;
    padding-left: 25px;
}

.admin-dropdown-item:last-child {
    border-bottom: none;
    border-bottom-left-radius: 15px;
    border-bottom-right-radius: 15px;
}

.admin-dropdown-item i {
    margin-right: 12px;
    width: 20px;
    color: #667eea;
}

.admin-dropdown-divider {
    height: 1px;
    background: rgba(0,0,0,0.1);
    margin: 8px 0;
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
.stat-icon.users { background: linear-gradient(45deg, #4ecdc4, #44a08d); }
.stat-icon.bookings { background: linear-gradient(45deg, #667eea, #764ba2); }
.stat-icon.revenue { background: linear-gradient(45deg, #ffa726, #fb8c00); }
.stat-icon.orders { background: linear-gradient(45deg, #ff6b6b, #ee5a24); }
.stat-number { font-size: 2rem; font-weight: bold; color: #333; margin-bottom: 5px; }
.stat-label { color: #666; font-size: 0.9rem; }

/* Admin Menu */
.admin-menu { 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px); 
    border-radius: 15px; 
    padding: 25px; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
    border: 1px solid rgba(255,255,255,0.3); 
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
    background: rgba(102,126,234,0.1); 
    border: 1px solid rgba(102,126,234,0.2); 
    cursor: pointer;
    width: 100%;
    box-sizing: border-box;
}
.menu-item:hover { 
    background: linear-gradient(45deg, #667eea, #764ba2); 
    color: white; 
    transform: translateX(5px); 
}
.menu-item i { margin-right: 10px; width: 20px; }

/* Dropdown Styles */
.dropdown-container {
    position: relative;
    margin-bottom: 10px;
}

.dropdown-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: rgba(255,193,7,0.1) !important;
    border: 1px solid rgba(255,193,7,0.3) !important;
}

.dropdown-toggle:hover {
    background: linear-gradient(45deg, #ffc107, #ff8f00) !important;
}

.dropdown-arrow {
    transition: transform 0.3s ease;
    margin-left: auto;
}

.dropdown-toggle.active .dropdown-arrow {
    transform: rotate(180deg);
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border-radius: 10px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    border: 1px solid rgba(0,0,0,0.1);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    max-height: 0;
    overflow: hidden;
}

.dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
    max-height: 400px;
}

.dropdown-item {
    display: block;
    padding: 12px 20px;
    color: #333;
    text-decoration: none;
    transition: 0.2s;
    border-radius: 0;
    margin: 0;
    background: transparent;
    border: none;
}

.dropdown-item:first-child {
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
}

.dropdown-item:last-child {
    border-bottom-left-radius: 10px;
    border-bottom-right-radius: 10px;
}

.dropdown-item:hover {
    background: rgba(102,126,234,0.1);
    color: #667eea;
    transform: none;
}

.dropdown-item i {
    margin-right: 10px;
    width: 20px;
    color: #667eea;
}

.dropdown-divider {
    height: 1px;
    background: rgba(0,0,0,0.1);
    margin: 8px 0;
}

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
    background: linear-gradient(45deg, #667eea, #764ba2); 
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

/* Welcome Section */
.welcome-section { 
    text-align: center; 
    margin-bottom: 30px; 
    color: #2c3e50; 
}
.welcome-title { 
    font-size: 3rem; 
    font-weight: bold; 
    margin-bottom: 10px; 
    text-shadow: 2px 2px 4px rgba(0,0,0,0.1); 
}
.welcome-subtitle { 
    font-size: 1.2rem; 
    opacity: 0.8; 
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
        color: #667eea;
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
        <h1><i class="fas fa-shield-alt"></i> Admin Dashboard</h1>
    </div>
    <div class="header-right">
        <!-- Admin Controls Dropdown -->
        <div class="admin-controls-dropdown">
            <button class="admin-controls-btn" onclick="toggleAdminDropdown()">
                <i class="fas fa-ellipsis-v"></i>
                <span>Controls</span>
            </button>
            
            <div class="admin-dropdown-menu" id="adminDropdownMenu">
                <div class="admin-dropdown-header">
                    <i class="fas fa-cogs"></i> Admin Controls
                </div>
                
                <a href="manage_tables.php" class="admin-dropdown-item">
                    <i class="fas fa-chair"></i>
                    Manage Tables
                </a>
                
                <a href="manage_menu.php" class="admin-dropdown-item">
                    <i class="fas fa-utensils"></i>
                    Manage Menu Items
                </a>
                
                <a href="booking-management.php" class="admin-dropdown-item">
                    <i class="fas fa-calendar-alt"></i>
                    Booking Management
                </a>
                
                <a href="view_preorders.php" class="admin-dropdown-item">
                    <i class="fas fa-shopping-cart"></i>
                    View Pre-Orders
                </a>
                

                
                <a href="view_comments.php" class="admin-dropdown-item">
                    <i class="fas fa-comments"></i>
                    User Reviews & Responses
                    <?php if ($pending_responses > 0): ?>
                        <span style="background: #ff6b6b; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; margin-left: 10px;">
                            <?= $pending_responses ?>
                        </span>
                    <?php endif; ?>
                </a>
                
                <div class="admin-dropdown-divider"></div>
                
                <a href="admin_add_admin.php" class="admin-dropdown-item">
                    <i class="fas fa-user-plus"></i>
                    Staff Management
                </a>
                
                <a href="settings.php" class="admin-dropdown-item">
                    <i class="fas fa-cog"></i>
                    Settings
                </a>
                
                <a href="chef_reports.php" class="admin-dropdown-item">
                    <i class="fas fa-file-alt"></i>
                    Chef Reports
                </a>
                
                <a href="verify_roles.php" class="admin-dropdown-item">
                    <i class="fas fa-users-cog"></i>
                    User Management
                </a>
            </div>
        </div>
        
        <a href="../logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</header>

<div class="welcome-section">
    <h1 class="welcome-title">Welcome Back, <?= htmlspecialchars($admin_name) ?></h1>
    <p class="welcome-subtitle">Manage your restaurant operations from here</p>
</div>

<div class="main-layout">
    <div class="content">
        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= number_format($users_count) ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= number_format($bookings_count) ?></div>
                        <div class="stat-label">Total Bookings</div>
                    </div>
                    <div class="stat-icon bookings">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number">ETB <?= number_format($revenue, 0) ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                    <div class="stat-icon revenue">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= number_format($today_bookings) ?></div>
                        <div class="stat-label">Today's Bookings</div>
                    </div>
                    <div class="stat-icon orders">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="recent-activity">
            <div class="activity-title">Recent Bookings</div>
            <?php if (empty($recent_bookings)): ?>
                <div style="text-align: center; color: #666; padding: 20px;">
                    <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 10px;"></i>
                    <p>No recent bookings found</p>
                </div>
            <?php else: ?>
                <?php foreach ($recent_bookings as $booking): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">
                                <strong><?= htmlspecialchars($booking['user_name'] ?? 'Unknown') ?></strong> 
                                booked <?= htmlspecialchars($booking['table_label'] ?? 'Table ' . $booking['table_id']) ?>
                                for <?= $booking['guests'] ?> guest<?= $booking['guests'] > 1 ? 's' : '' ?>
                            </div>
                            <div class="activity-time">
                                <?= date('M j, Y g:i A', strtotime($booking['booking_datetime'])) ?> 
                                (<?= ucfirst($booking['status']) ?>)
                            </div>
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
        
        <!-- Quick Stats -->
        <div class="admin-menu">
            <div class="menu-title">Quick Actions</div>
            
            <a href="booking-management.php" class="menu-item">
                <i class="fas fa-calendar-check"></i>
                Today's Bookings
            </a>
            
            <a href="view_preorders.php" class="menu-item">
                <i class="fas fa-clock"></i>
                Pending Orders
            </a>
            

            
            <a href="view_comments.php" class="menu-item">
                <i class="fas fa-reply-all"></i>
                Reviews & Responses
                <?php if ($pending_responses > 0): ?>
                    <span style="background: #ff6b6b; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; margin-left: 10px;">
                        <?= $pending_responses ?>
                    </span>
                <?php endif; ?>
            </a>
        </div>
    </div>
</div>

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay" onclick="toggleMobileMenu()"></div>

<script>
// Add entrance animations
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.stat-card, .admin-menu, .recent-activity');
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
    const menuItems = document.querySelectorAll('.menu-item');
    menuItems.forEach(item => {
        item.addEventListener('click', function() {
            this.style.transform = 'scale(0.95) translateX(5px)';
            setTimeout(() => {
                this.style.transform = 'translateX(5px)';
            }, 150);
            
            // Close mobile menu when clicking on menu items
            if (window.innerWidth <= 768) {
                setTimeout(() => {
                    toggleMobileMenu();
                }, 200);
            }
        });
    });

});

// Mobile menu toggle function
function toggleMobileMenu() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    const closeBtn = document.querySelector('.sidebar-close');
    
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
    
    // Show/hide close button on mobile
    if (window.innerWidth <= 768) {
        closeBtn.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
    }
}

// Dropdown toggle function
function toggleDropdown() {
    const dropdownMenu = document.getElementById('dropdownMenu');
    const dropdownToggle = document.querySelector('.dropdown-toggle');
    
    dropdownMenu.classList.toggle('show');
    dropdownToggle.classList.toggle('active');
}

// Admin Controls Dropdown toggle function
function toggleAdminDropdown() {
    const adminDropdownMenu = document.getElementById('adminDropdownMenu');
    
    adminDropdownMenu.classList.toggle('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdownContainer = document.querySelector('.dropdown-container');
    const dropdownMenu = document.getElementById('dropdownMenu');
    const dropdownToggle = document.querySelector('.dropdown-toggle');
    
    const adminDropdownContainer = document.querySelector('.admin-controls-dropdown');
    const adminDropdownMenu = document.getElementById('adminDropdownMenu');
    
    // Close sidebar dropdown
    if (dropdownContainer && !dropdownContainer.contains(event.target)) {
        dropdownMenu.classList.remove('show');
        dropdownToggle.classList.remove('active');
    }
    
    // Close admin header dropdown
    if (adminDropdownContainer && !adminDropdownContainer.contains(event.target)) {
        adminDropdownMenu.classList.remove('show');
    }
});

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
</body>
</html>
