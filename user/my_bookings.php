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

// Check for payment skip message
if (isset($_SESSION['payment_message'])) {
    $success = $_SESSION['payment_message'];
    unset($_SESSION['payment_message']);
}

// Handle booking actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    $booking_id = intval($_POST['booking_id'] ?? 0);
    
    if ($action === 'cancel' && $booking_id > 0) {
        try {
            $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$booking_id, $user_id])) {
                $success = "Booking cancelled successfully.";
            } else {
                $error = "Failed to cancel booking.";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    if ($action === 'update' && $booking_id > 0) {
        $new_datetime = $_POST['new_datetime'] ?? '';
        $new_guests = intval($_POST['new_guests'] ?? 0);
        
        if ($new_datetime && $new_guests > 0) {
            try {
                $stmt = $conn->prepare("UPDATE bookings SET booking_datetime = ?, guests = ? WHERE id = ? AND user_id = ? AND status NOT IN ('cancelled', 'completed')");
                if ($stmt->execute([$new_datetime, $new_guests, $booking_id, $user_id])) {
                    $success = "Booking updated successfully.";
                } else {
                    $error = "Failed to update booking.";
                }
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        } else {
            $error = "Please provide valid date/time and guest count.";
        }
    }
}

// Only run cleanup functions periodically, not on every page load
// These should be called by cron jobs or admin cleanup, not user page views
// cancelExpiredPayments($conn);

// Get user bookings with payment details
$stmt = $conn->prepare("
    SELECT b.*, rt.table_label, rt.capacity,
           p.payment_reference, p.status as payment_status, p.payment_method, p.expires_at, p.amount as payment_amount
    FROM bookings b 
    LEFT JOIN restaurant_tables rt ON b.table_id = rt.id 
    LEFT JOIN payments p ON b.id = p.booking_id AND p.payment_type = 'booking'
    WHERE b.user_id = ? 
    ORDER BY b.booking_datetime DESC
");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<title>My Bookings</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: Arial; background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100vh; }
header { background: rgba(255,255,255,0.95); backdrop-filter: blur(15px); padding: 20px; display: flex; justify-content: space-between; align-items: center; }

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

header h2 { background: linear-gradient(45deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
nav a { color: #333; text-decoration: none; margin: 0 10px; padding: 8px 15px; border-radius: 20px; transition: 0.3s; }
nav a:hover { background: #667eea; color: white; }
.logout-btn { background: #ff6b6b; color: white !important; }

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
.container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
.message { padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
.success { background: rgba(40,167,69,0.1); color: #28a745; border: 2px solid rgba(40,167,69,0.3); }
.error { background: rgba(220,53,69,0.1); color: #dc3545; border: 2px solid rgba(220,53,69,0.3); }
.page-title { text-align: center; color: white; font-size: 2.5rem; margin-bottom: 30px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); }
.bookings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; }
.booking-card { background: rgba(255,255,255,0.95); border-radius: 15px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); transition: 0.3s; }
.booking-card:hover { transform: translateY(-5px); }
.booking-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.booking-id { font-size: 1.2rem; font-weight: bold; color: #333; }
.status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.9rem; font-weight: 500; }
.status-pending { background: #fff3cd; color: #856404; }
.status-confirmed { background: #d4edda; color: #155724; }
.status-cancelled { background: #f8d7da; color: #721c24; }
.status-completed { background: #e2e3e5; color: #495057; }
.status-failed { background: #f8d7da; color: #721c24; }
.status-expired { background: #e2e3e5; color: #495057; }
.method-telebirr { color: #ff6b35; }
.method-cbe { color: #2c5aa0; }
.method-cash { color: #28a745; }
.booking-details { margin-bottom: 20px; }
.detail-row { display: flex; justify-content: space-between; margin-bottom: 10px; padding: 8px 0; border-bottom: 1px solid #eee; }
.detail-label { font-weight: 500; color: #666; }
.detail-value { color: #333; }
.booking-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.btn { padding: 8px 16px; border: none; border-radius: 20px; cursor: pointer; font-size: 0.9rem; transition: 0.3s; text-decoration: none; display: inline-block; text-align: center; }
.btn-update { background: #667eea; color: white; }
.btn-cancel { background: #dc3545; color: white; }
.btn-update:hover { background: #5a6fd8; }
.btn-cancel:hover { background: #c82333; }
.update-form { display: none; margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 10px; }
.form-group { margin-bottom: 15px; }
.form-label { display: block; margin-bottom: 5px; font-weight: 500; }
.form-input { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; }
.form-actions { display: flex; gap: 10px; }
.btn-save { background: #28a745; color: white; }
.btn-cancel-form { background: #6c757d; color: white; }
.no-bookings { text-align: center; padding: 60px 20px; background: rgba(255,255,255,0.95); border-radius: 15px; }
.no-bookings i { font-size: 4rem; color: #ddd; margin-bottom: 20px; }
.book-now-btn { display: inline-block; background: linear-gradient(45deg, #667eea, #764ba2); color: white; padding: 15px 30px; border-radius: 25px; text-decoration: none; margin-top: 20px; transition: 0.3s; }
@media (max-width: 768px) { 
    .bookings-grid { grid-template-columns: 1fr; } 
    .booking-actions { flex-direction: column; }
    
    .mobile-menu-btn {
        display: block;
    }
    
    nav {
        display: none;
    }
    
    header {
        flex-direction: row;
        text-align: left;
    }
}
</style>
</head>
<body>
<header>
    <div class="header-left">
        <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <h2><i class="fas fa-calendar-check"></i> My Bookings</h2>
    </div>
    <nav>
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="View_Tables.php"><i class="fas fa-chair"></i> Tables</a>
        <a href="pre_order.php"><i class="fas fa-shopping-cart"></i> Pre-Order</a>
        <a href="my_orders.php"><i class="fas fa-receipt"></i> Orders</a>
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
    
    <a href="pre_order.php">
        <i class="fas fa-shopping-cart"></i> Pre-Order
    </a>
    
    <a href="my_orders.php">
        <i class="fas fa-receipt"></i> Orders
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
    <h1 class="page-title">My Table Bookings</h1>
    
    <?php if ($success): ?>
        <div class="message success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="message error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>

    <?php if (empty($bookings)): ?>
        <div class="no-bookings">
            <i class="fas fa-calendar-times"></i>
            <h3>No Bookings Found</h3>
            <p>You haven't made any table reservations yet.</p>
            <a href="View_Tables.php" class="book-now-btn">Book a Table Now</a>
        </div>
    <?php else: ?>
        <div class="bookings-grid">
            <?php foreach ($bookings as $booking): ?>
                <div class="booking-card">
                    <div class="booking-header">
                        <div class="booking-id">Booking #<?= $booking['id'] ?></div>
                        <div class="status-badge status-<?= $booking['status'] ?>">
                            <?= ucfirst($booking['status']) ?>
                        </div>
                    </div>
                    
                    <div class="booking-details">
                        <div class="detail-row">
                            <span class="detail-label">Table:</span>
                            <span class="detail-value"><?= htmlspecialchars($booking['table_label'] ?? 'Table ' . $booking['table_id']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Booking Date & Time:</span>
                            <span class="detail-value"><?= date('M j, Y g:i A', strtotime($booking['booking_datetime'])) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Guests:</span>
                            <span class="detail-value"><?= $booking['guests'] ?> person<?= $booking['guests'] > 1 ? 's' : '' ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Meal Type:</span>
                            <span class="detail-value"><?= htmlspecialchars($booking['meal_type']) ?></span>
                        </div>
                        <?php if ($booking['special_requests']): ?>
                            <div class="detail-row">
                                <span class="detail-label">Special Requests:</span>
                                <span class="detail-value"><?= htmlspecialchars($booking['special_requests']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="detail-row">
                            <span class="detail-label">Booked On:</span>
                            <span class="detail-value"><?= date('M j, Y g:i A', strtotime($booking['created_at'])) ?></span>
                        </div>
                        
                        <?php if ($booking['payment_reference']): ?>
                        <div class="detail-row">
                            <span class="detail-label">Payment Status:</span>
                            <span class="detail-value">
                                <?php 
                                $payment_info = formatPaymentStatus($booking['payment_status']);
                                $method_info = formatPaymentMethod($booking['payment_method']);
                                ?>
                                <span class="status-badge <?= $payment_info['class'] ?>">
                                    <i class="<?= $payment_info['icon'] ?>"></i> <?= $payment_info['text'] ?>
                                </span>
                                <?php if ($booking['payment_status'] === 'pending' && $booking['expires_at']): ?>
                                    <br><small>Expires: <?= date('M j, Y g:i A', strtotime($booking['expires_at'])) ?></small>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Payment Method:</span>
                            <span class="detail-value">
                                <i class="<?= $method_info['icon'] ?>"></i> <?= $method_info['text'] ?>
                            </span>
                        </div>
                        <?php if ($booking['payment_amount']): ?>
                        <div class="detail-row">
                            <span class="detail-label">Payment Amount:</span>
                            <span class="detail-value">ETB <?= number_format($booking['payment_amount'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!in_array($booking['status'], ['cancelled', 'completed']) && strtotime($booking['booking_datetime']) > time()): ?>
                        <div class="booking-actions">
                            <button class="btn btn-update" onclick="toggleUpdateForm(<?= $booking['id'] ?>)">
                                <i class="fas fa-edit"></i> Update
                            </button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this booking?')">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                <button type="submit" class="btn btn-cancel">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </form>
                        </div>
                        
                        <div class="update-form" id="updateForm<?= $booking['id'] ?>">
                            <form method="POST">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                
                                <div class="form-group">
                                    <label class="form-label">New Date & Time:</label>
                                    <input type="datetime-local" name="new_datetime" class="form-input" 
                                           value="<?= date('Y-m-d\TH:i', strtotime($booking['booking_datetime'])) ?>" 
                                           min="<?= date('Y-m-d\TH:i') ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Number of Guests:</label>
                                    <select name="new_guests" class="form-input" required>
                                        <?php for($i = 1; $i <= ($booking['capacity'] ?? 8); $i++): ?>
                                            <option value="<?= $i ?>" <?= $i == $booking['guests'] ? 'selected' : '' ?>>
                                                <?= $i ?> Guest<?= $i > 1 ? 's' : '' ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-save">Save Changes</button>
                                    <button type="button" class="btn btn-cancel-form" onclick="toggleUpdateForm(<?= $booking['id'] ?>)">Cancel</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
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

// Add click event listeners to mobile menu items
document.addEventListener('DOMContentLoaded', function() {
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
});

function toggleUpdateForm(bookingId) {
    const form = document.getElementById('updateForm' + bookingId);
    form.style.display = form.style.display === 'none' || form.style.display === '' ? 'block' : 'none';
}
</script>
</body>
</html>