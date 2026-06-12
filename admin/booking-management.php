
<?php
session_start();
require_once "../connection.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$admin_name = $_SESSION['name'] ?? 'Admin';
$message = "";

// Check for session message
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Check which columns exist in the bookings table
$columns_check = $conn->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN);
$has_booking_date = in_array('booking_date', $columns_check);
$has_booking_time = in_array('booking_time', $columns_check);
$has_booking_datetime = in_array('booking_datetime', $columns_check);
$has_guests = in_array('guests', $columns_check);
$has_special_requests = in_array('special_requests', $columns_check);
$has_notes = in_array('notes', $columns_check);

// Update booking status
if (isset($_POST['update_status'])) {
    $booking_id = intval($_POST['booking_id']);
    $new_status = $_POST['status'];
    $allowed = ['confirmed', 'cancelled', 'completed'];
    
    if (in_array($new_status, $allowed)) {
        try {
            $stmt = $conn->prepare("UPDATE bookings SET status=? WHERE id=?");
            $stmt->execute([$new_status, $booking_id]);
            $_SESSION['message'] = "Booking status updated successfully!";
            header("Location: booking-management.php");
            exit;
        } catch (Exception $e) {
            $message = "Error updating booking: " . $e->getMessage();
        }
    }
}

// Delete booking
if (isset($_GET['delete_booking'])) {
    $booking_id = intval($_GET['delete_booking']);
    try {
        // Delete related records in correct order
        $conn->beginTransaction();
        
        // Delete pre-order items first
        $conn->prepare("DELETE poi FROM pre_order_items poi 
                       JOIN pre_orders po ON poi.pre_order_id = po.id 
                       WHERE po.booking_id = ?")->execute([$booking_id]);
        
        // Delete payments
        $conn->prepare("DELETE p FROM payments p 
                       JOIN pre_orders po ON p.order_id = po.id 
                       WHERE po.booking_id = ?")->execute([$booking_id]);
        
        // Delete pre-orders
        $conn->prepare("DELETE FROM pre_orders WHERE booking_id=?")->execute([$booking_id]);
        
        // Delete booking
        $conn->prepare("DELETE FROM bookings WHERE id=?")->execute([$booking_id]);
        
        $conn->commit();
        $_SESSION['message'] = "Booking deleted successfully!";
        header("Location: booking-management.php");
        exit;
    } catch (PDOException $e) {
        $conn->rollback();
        $message = "Error deleting booking: " . $e->getMessage();
    }
}

// Build dynamic SQL query based on available columns
$base_fields = "b.id AS booking_id, b.user_id, b.status, b.created_at,
                u.name AS user_name, u.email AS user_email, u.phone AS user_phone, u.created_at as user_registered,
                t.table_label, t.capacity, t.location";

$optional_fields = "";
if ($has_booking_date) $optional_fields .= ", b.booking_date";
if ($has_booking_time) $optional_fields .= ", b.booking_time";
if ($has_booking_datetime) $optional_fields .= ", b.booking_datetime";
if ($has_guests) $optional_fields .= ", b.guests";
if ($has_special_requests) $optional_fields .= ", b.special_requests";
if ($has_notes) $optional_fields .= ", b.notes";

// Fetch bookings with enhanced error handling
try {
    $sql = "SELECT $base_fields $optional_fields,
            COALESCE(po.total_amount, 0) as total_amount, 
            po.status as order_status, po.id as preorder_id,
            p.payment_reference, p.status as payment_status, p.payment_method, p.expires_at, p.created_at as payment_date,
            COUNT(poi.id) as total_items,
            TIMESTAMPDIFF(MINUTE, b.created_at, NOW()) as minutes_ago
            FROM bookings b 
            JOIN users u ON b.user_id = u.id 
            JOIN restaurant_tables t ON b.table_id = t.id 
            LEFT JOIN pre_orders po ON b.id = po.booking_id
            LEFT JOIN payments p ON po.id = p.order_id AND p.payment_type = 'order'
            LEFT JOIN pre_order_items poi ON po.id = poi.pre_order_id
            GROUP BY b.id, po.id, p.id
            ORDER BY b.created_at DESC";
    
    $bookings = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback query with minimal fields
    try {
        $sql = "SELECT b.id AS booking_id, b.user_id, b.status, b.created_at,
                u.name AS user_name, u.email AS user_email, u.phone AS user_phone, u.created_at as user_registered,
                t.table_label, t.capacity, t.location,
                0 as total_amount, NULL as order_status, NULL as preorder_id,
                NULL as payment_reference, NULL as payment_status, NULL as payment_method, NULL as expires_at, NULL as payment_date,
                0 as total_items, TIMESTAMPDIFF(MINUTE, b.created_at, NOW()) as minutes_ago
                FROM bookings b 
                JOIN users u ON b.user_id = u.id 
                JOIN restaurant_tables t ON b.table_id = t.id 
                ORDER BY b.created_at DESC";
        $bookings = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {
        $bookings = [];
        $message = "Error loading bookings: " . $e2->getMessage();
    }
}

// Get booking statistics with error handling
try {
    $total_bookings = $conn->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    $pending_bookings = $conn->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
    $confirmed_bookings = $conn->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'")->fetchColumn();
    $new_bookings_today = $conn->query("SELECT COUNT(*) FROM bookings WHERE DATE(created_at) = CURDATE()")->fetchColumn();
} catch (Exception $e) {
    $total_bookings = $pending_bookings = $confirmed_bookings = $new_bookings_today = 0;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Booking Management</title>
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

.header-controls {
    display: flex;
    align-items: center;
    gap: 15px;
}

.live-indicator {
    background: linear-gradient(45deg, #4caf50, #45a049);
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    animation: pulse-live 2s infinite;
}

@keyframes pulse-live {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

.refresh-btn {
    background: linear-gradient(45deg, #ff9800, #f57c00);
    color: white;
    border: none;
    padding: 10px 18px;
    border-radius: 20px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.refresh-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255,152,0,0.4);
}

.refresh-btn.loading i {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
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
    display: flex;
    align-items: center;
    gap: 8px;
}
.back-btn:hover { 
    transform: translateY(-2px); 
    box-shadow: 0 6px 20px rgba(78,205,196,0.4); 
}

/* Statistics Dashboard */
.stats-dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(15px);
    padding: 25px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border: 1px solid rgba(255,255,255,0.2);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    background: linear-gradient(45deg, #667eea, #764ba2);
}

.stat-icon.pending {
    background: linear-gradient(45deg, #ff9800, #f57c00);
}

.stat-icon.confirmed {
    background: linear-gradient(45deg, #4caf50, #45a049);
}

.stat-icon.today {
    background: linear-gradient(45deg, #2196f3, #1976d2);
}

.stat-icon.recent {
    background: linear-gradient(45deg, #e91e63, #c2185b);
    animation: pulse-recent 2s infinite;
}

@keyframes pulse-recent {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.stat-info {
    flex: 1;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: #333;
    margin-bottom: 5px;
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Update Info */
.update-info {
    background: rgba(255,255,255,0.9);
    padding: 15px 20px;
    border-radius: 15px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
    color: #666;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.3);
}

.auto-refresh-status {
    color: #4caf50;
    font-weight: 600;
}

/* New Booking Badge */
.new-booking-badge {
    background: linear-gradient(45deg, #ff4081, #e91e63);
    color: white;
    padding: 2px 5px;
    border-radius: 8px;
    font-size: 0.55rem;
    font-weight: 700;
    margin-left: 6px;
    animation: pulse-new 1.5s infinite;
    text-transform: uppercase;
    letter-spacing: 0.2px;
}

@keyframes pulse-new {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 64, 129, 0.7); }
    70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(255, 64, 129, 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 64, 129, 0); }
}

.booking-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.time-ago {
    font-size: 0.7rem;
    color: #666;
    display: flex;
    align-items: center;
    gap: 3px;
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

.bookings-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); 
    gap: 15px; 
}

.booking-card { 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px); 
    border-radius: 12px; 
    padding: 15px; 
    box-shadow: 0 4px 20px rgba(0,0,0,0.06); 
    transition: all 0.3s ease; 
    border: 1px solid rgba(255,255,255,0.2); 
    position: relative;
}
.booking-card:hover { 
    transform: translateY(-3px); 
    box-shadow: 0 8px 25px rgba(0,0,0,0.1); 
}

.booking-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: 10px; 
    padding-bottom: 8px; 
    padding-left: 50px;
    border-bottom: 1px solid rgba(102,126,234,0.1); 
}
.booking-id { 
    font-size: 1rem; 
    font-weight: 700; 
    color: #333; 
    display: flex; 
    align-items: center; 
    gap: 5px; 
}
.booking-id i { color: #667eea; font-size: 0.9rem; }

.status-badge { 
    padding: 4px 10px; 
    border-radius: 15px; 
    font-size: 0.75rem; 
    font-weight: 600; 
    text-transform: uppercase; 
    letter-spacing: 0.2px; 
}
.status-pending { background: linear-gradient(45deg, #ffc107, #ff8f00); color: white; }
.status-confirmed { background: linear-gradient(45deg, #4caf50, #388e3c); color: white; }
.status-cancelled { background: linear-gradient(45deg, #f44336, #d32f2f); color: white; }
.status-completed { background: linear-gradient(45deg, #9e9e9e, #616161); color: white; }

.booking-details { margin-bottom: 10px; }

.section-header {
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    padding: 6px 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.8rem;
    margin: 8px 0 6px 0;
    display: flex;
    align-items: center;
    gap: 6px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    box-shadow: 0 2px 8px rgba(102,126,234,0.15);
}

.section-header:first-child {
    margin-top: 0;
}

.section-header i {
    font-size: 0.8rem;
}

.detail-row { 
    display: flex; 
    justify-content: space-between; 
    align-items: flex-start;
    margin-bottom: 3px; 
    padding: 6px 10px; 
    border-bottom: 1px solid rgba(0,0,0,0.03); 
    background: rgba(255,255,255,0.3);
    border-radius: 4px;
    margin-bottom: 3px;
    transition: background 0.3s ease;
}

.detail-row:hover {
    background: rgba(102,126,234,0.05);
}

.detail-label { 
    font-weight: 600; 
    color: #666; 
    display: flex; 
    align-items: center; 
    gap: 5px; 
    min-width: 110px;
    flex-shrink: 0;
    font-size: 0.8rem;
}
.detail-label i { 
    color: #667eea; 
    width: 12px; 
    text-align: center;
    font-size: 0.75rem;
}
.detail-value { 
    color: #333; 
    font-weight: 500; 
    text-align: right;
    flex: 1;
    word-break: break-word;
    font-size: 0.8rem;
}

.booking-actions { 
    display: grid; 
    grid-template-columns: repeat(2, 1fr); 
    gap: 12px; 
}
.btn { 
    padding: 12px 16px; 
    border: none; 
    border-radius: 12px; 
    cursor: pointer; 
    font-size: 0.9rem; 
    font-weight: 600; 
    transition: all 0.3s ease; 
    text-decoration: none; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    gap: 8px; 
    text-transform: uppercase; 
    letter-spacing: 0.5px; 
}
.btn:hover { transform: translateY(-2px); }
.btn-confirm { background: linear-gradient(45deg, #4caf50, #45a049); color: white; box-shadow: 0 4px 15px rgba(76,175,80,0.3); }
.btn-cancel { background: linear-gradient(45deg, #ff9800, #f57c00); color: white; box-shadow: 0 4px 15px rgba(255,152,0,0.3); }
.btn-complete { background: linear-gradient(45deg, #9e9e9e, #757575); color: white; box-shadow: 0 4px 15px rgba(158,158,158,0.3); }
.btn-delete { background: linear-gradient(45deg, #f44336, #d32f2f); color: white; box-shadow: 0 4px 15px rgba(244,67,54,0.3); }

/* Payment Status Badges */
.payment-badge {
    padding: 3px 6px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    text-transform: uppercase;
    letter-spacing: 0.2px;
    margin-top: 2px;
}
.payment-badge.paid {
    background: linear-gradient(45deg, #4caf50, #388e3c);
    color: white;
    box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
}
.payment-badge.pending {
    background: linear-gradient(45deg, #ff9800, #f57c00);
    color: white;
    box-shadow: 0 2px 8px rgba(255, 152, 0, 0.3);
}
.payment-badge.not-paid {
    background: linear-gradient(45deg, #f44336, #d32f2f);
    color: white;
    box-shadow: 0 2px 8px rgba(244, 67, 54, 0.3);
    animation: pulse-warning 2s infinite;
}
@keyframes pulse-warning {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.no-bookings { 
    grid-column: 1/-1; 
    text-align: center; 
    padding: 80px 40px; 
    background: rgba(255,255,255,0.95); 
    border-radius: 20px; 
    backdrop-filter: blur(15px); 
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}
.no-bookings i { 
    font-size: 4rem; 
    color: #ccc; 
    margin-bottom: 20px; 
}
.no-bookings h3 { 
    color: #666; 
    margin-bottom: 10px; 
    font-size: 1.5rem;
}
.no-bookings p { 
    color: #999; 
    font-size: 1.1rem;
}

/* Enhanced responsive design */
@media (max-width: 768px) { 
    .bookings-grid { 
        grid-template-columns: 1fr; 
        gap: 20px;
    } 
    .booking-actions { 
        grid-template-columns: 1fr;
        gap: 10px;
    }
    .booking-actions .btn {
        padding: 14px 16px;
        font-size: 0.9rem;
    }
    header { 
        flex-direction: column; 
        text-align: center; 
        gap: 15px; 
        padding: 15px 20px;
    }
    header h1 {
        font-size: 1.5rem;
    }
    .booking-card { 
        padding: 20px; 
    }
    .booking-header { 
        flex-direction: column; 
        gap: 15px; 
        text-align: center; 
    }
    .detail-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
        text-align: left;
    }
    .detail-label {
        min-width: auto;
        width: 100%;
    }
    .detail-value {
        margin-left: 0;
        text-align: left;
        width: 100%;
        padding-left: 24px;
    }
    .section-header {
        padding: 10px 15px;
        font-size: 0.9rem;
        margin: 15px 0 10px 0;
    }
    .container {
        padding: 0 15px;
    }
}

@media (max-width: 480px) {
    .booking-card {
        padding: 15px;
    }
    .booking-header {
        margin-bottom: 20px;
        padding-bottom: 15px;
    }
    .booking-id {
        font-size: 1.1rem;
    }
    .status-badge {
        padding: 6px 12px;
        font-size: 0.8rem;
    }
    .section-header {
        padding: 8px 12px;
        font-size: 0.85rem;
    }
    .detail-row {
        padding: 10px 12px;
    }
    .detail-label {
        font-size: 0.9rem;
    }
    .detail-value {
        font-size: 0.9rem;
        padding-left: 20px;
    }
    .payment-badge {
        font-size: 0.75rem;
        padding: 4px 8px;
    }
}

@media (max-width: 480px) {
    .bookings-grid {
        gap: 15px;
    }
    .booking-card {
        padding: 15px;
    }
    .booking-header {
        margin-bottom: 20px;
        padding-bottom: 15px;
    }
    .booking-id {
        font-size: 1.1rem;
    }
    .status-badge {
        padding: 6px 12px;
        font-size: 0.8rem;
    }
}
</style>
</head>
<body>

<header>
    <h1><i class="fas fa-calendar-check"></i> Booking Management</h1>
    <div class="header-controls">
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</header>

<div class="container">
    <?php if ($message): ?>
        <div class="message">
            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Dashboard -->
    <div class="stats-dashboard">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $total_bookings; ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon pending">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $pending_bookings; ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon confirmed">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $confirmed_bookings; ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon today">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $new_bookings_today; ?></div>
                <div class="stat-label">Today's Bookings</div>
            </div>
        </div>
    </div>

    <div class="bookings-grid">
        <?php if (empty($bookings)): ?>
            <div class="no-bookings">
                <i class="fas fa-calendar-times"></i>
                <h3>No Bookings Found</h3>
                <p>There are currently no bookings in the system.</p>
            </div>
        <?php else: ?>
            <?php foreach ($bookings as $b): ?>
                <div class="booking-card">
                    <div class="booking-header">
                        <div class="booking-id">
                            <i class="fas fa-hashtag"></i>
                            Booking #<?php echo $b['booking_id']; ?>
                            <?php if (isset($b['minutes_ago']) && $b['minutes_ago'] <= 30): ?>
                                <span class="new-booking-badge">
                                    <i class="fas fa-star"></i> RECENT
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="booking-meta">
                            <span class="status-badge status-<?php echo strtolower($b['status']); ?>">
                                <?php echo ucfirst($b['status']); ?>
                            </span>
                            <?php if (isset($b['minutes_ago'])): ?>
                            <div class="time-ago">
                                <i class="fas fa-clock"></i>
                                <?php 
                                if ($b['minutes_ago'] < 1) {
                                    echo "Just now";
                                } elseif ($b['minutes_ago'] < 60) {
                                    echo $b['minutes_ago'] . " min ago";
                                } elseif ($b['minutes_ago'] < 1440) {
                                    echo floor($b['minutes_ago'] / 60) . " hr ago";
                                } else {
                                    echo floor($b['minutes_ago'] / 1440) . " days ago";
                                }
                                ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="booking-details">
                        <!-- User Information Section -->
                        <div class="section-header">
                            <i class="fas fa-user-circle"></i> Customer Information
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-user"></i> Full Name
                            </span>
                            <span class="detail-value"><?php echo htmlspecialchars($b['user_name']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-envelope"></i> Email
                            </span>
                            <span class="detail-value">
                                <a href="mailto:<?php echo htmlspecialchars($b['user_email']); ?>" style="color: #667eea; text-decoration: none;">
                                    <?php echo htmlspecialchars($b['user_email']); ?>
                                </a>
                            </span>
                        </div>
                        <?php if (!empty($b['user_phone'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-phone"></i> Phone
                            </span>
                            <span class="detail-value">
                                <a href="tel:<?php echo htmlspecialchars($b['user_phone']); ?>" style="color: #667eea; text-decoration: none;">
                                    <?php echo htmlspecialchars($b['user_phone']); ?>
                                </a>
                            </span>
                        </div>
                        <?php endif; ?>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-user-plus"></i> Customer Since
                            </span>
                            <span class="detail-value"><?php echo date('M d, Y', strtotime($b['user_registered'])); ?></span>
                        </div>

                        <!-- Booking Information Section -->
                        <div class="section-header">
                            <i class="fas fa-calendar-check"></i> Booking Details
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-table"></i> Table
                            </span>
                            <span class="detail-value">
                                <?php echo htmlspecialchars($b['table_label']); ?> 
                                (<?php echo $b['capacity']; ?> seats)
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-map-marker-alt"></i> Location
                            </span>
                            <span class="detail-value"><?php echo htmlspecialchars($b['location']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-users"></i> Guests
                            </span>
                            <span class="detail-value"><?php echo $b['guests']; ?> people</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-calendar"></i> Booking Date & Time
                            </span>
                            <span class="detail-value">
                                <?php 
                                if (!empty($b['booking_datetime'])) {
                                    echo date('M d, Y g:i A', strtotime($b['booking_datetime']));
                                } else {
                                    echo date('M d, Y', strtotime($b['booking_date'])) . ' at ' . date('g:i A', strtotime($b['booking_time']));
                                }
                                ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-plus-circle"></i> Booked On
                            </span>
                            <span class="detail-value"><?php echo date('M d, Y g:i A', strtotime($b['created_at'])); ?></span>
                        </div>

                        <?php if (!empty($b['special_requests'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-comment-alt"></i> Special Requests
                            </span>
                            <span class="detail-value"><?php echo htmlspecialchars($b['special_requests']); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($b['notes'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-sticky-note"></i> Admin Notes
                            </span>
                            <span class="detail-value"><?php echo htmlspecialchars($b['notes']); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($b['total_amount'] > 0): ?>
                        <!-- Order Information Section -->
                        <div class="section-header">
                            <i class="fas fa-shopping-cart"></i> Pre-Order Information
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-receipt"></i> Pre-Order ID
                            </span>
                            <span class="detail-value">
                                <?php if ($b['preorder_id']): ?>
                                    <a href="view_preorders.php" style="color: #667eea; text-decoration: none;">
                                        #<?php echo $b['preorder_id']; ?>
                                    </a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-utensils"></i> Total Items
                            </span>
                            <span class="detail-value"><?php echo $b['total_items']; ?> items</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-money-bill-wave"></i> Order Amount
                            </span>
                            <span class="detail-value">ETB <?php echo number_format($b['total_amount'], 2); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-credit-card"></i> Payment Status
                            </span>
                            <span class="detail-value">
                                <?php if ($b['payment_status'] === 'completed'): ?>
                                    <span class="payment-badge paid">
                                        <i class="fas fa-check-circle"></i> Paid
                                    </span>
                                    <?php if ($b['payment_date']): ?>
                                        <br><small style="color: #666; font-size: 0.8rem;">
                                            Paid on: <?php echo date('M j, g:i A', strtotime($b['payment_date'])); ?>
                                        </small>
                                    <?php endif; ?>
                                <?php elseif ($b['payment_status'] === 'pending'): ?>
                                    <span class="payment-badge pending">
                                        <i class="fas fa-clock"></i> Payment Pending
                                    </span>
                                    <?php if ($b['expires_at'] && strtotime($b['expires_at']) > time()): ?>
                                        <br><small style="color: #666; font-size: 0.8rem;">Expires: <?php echo date('M j, g:i A', strtotime($b['expires_at'])); ?></small>
                                    <?php elseif ($b['expires_at']): ?>
                                        <br><small style="color: #dc3545; font-size: 0.8rem;">Expired</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="payment-badge not-paid">
                                        <i class="fas fa-exclamation-triangle"></i> Not Paid
                                    </span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if ($b['payment_method']): ?>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-wallet"></i> Payment Method
                            </span>
                            <span class="detail-value">
                                <?php 
                                switch($b['payment_method']) {
                                    case 'telebirr': echo '<i class="fas fa-mobile-alt"></i> TeleBirr'; break;
                                    case 'cbe_bank': echo '<i class="fas fa-university"></i> CBE Bank'; break;
                                    case 'cash_on_arrival': echo '<i class="fas fa-money-bill"></i> Cash on Arrival'; break;
                                    default: echo ucfirst(str_replace('_', ' ', $b['payment_method']));
                                }
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <?php if ($b['payment_reference']): ?>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-hashtag"></i> Payment Reference
                            </span>
                            <span class="detail-value" style="font-family: 'Courier New', monospace; font-size: 0.9rem;">
                                <?php echo htmlspecialchars($b['payment_reference']); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="booking-actions">
                        <!-- Three-dot Menu -->
                        <div class="dropdown" style="position: absolute; top: 15px; left: 15px; z-index: 10;">
                            <button class="dropdown-toggle" onclick="toggleDropdown(<?= $b['booking_id'] ?>)" style="background: none; color: #666; border: none; padding: 8px; border-radius: 50%; cursor: pointer; font-size: 1.1rem; transition: all 0.3s ease; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu" id="dropdown-<?= $b['booking_id'] ?>" style="position: absolute; top: 100%; left: 0; background: white; min-width: 200px; border-radius: 12px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15); z-index: 1000; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.3s ease; border: 1px solid rgba(0, 0, 0, 0.1); overflow: hidden;">
                                
                                <?php if ($b['status'] !== 'confirmed'): ?>
                                    <form method="post" style="display: contents;">
                                        <input type="hidden" name="booking_id" value="<?php echo $b['booking_id']; ?>">
                                        <input type="hidden" name="status" value="confirmed">
                                        <button name="update_status" class="dropdown-item" type="submit" style="display: block; width: 100%; padding: 12px 20px; text-decoration: none; color: #4caf50; border: none; background: none; text-align: left; cursor: pointer; transition: all 0.3s ease; font-size: 0.95rem; border-bottom: 1px solid rgba(0, 0, 0, 0.05);">
                                            <i class="fas fa-check" style="margin-right: 10px; width: 16px; text-align: center;"></i> Confirm Booking
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($b['status'] !== 'cancelled'): ?>
                                    <form method="post" style="display: contents;">
                                        <input type="hidden" name="booking_id" value="<?php echo $b['booking_id']; ?>">
                                        <input type="hidden" name="status" value="cancelled">
                                        <button name="update_status" class="dropdown-item" type="submit" onclick="return confirm('Are you sure you want to cancel this booking?')" style="display: block; width: 100%; padding: 12px 20px; text-decoration: none; color: #ff9800; border: none; background: none; text-align: left; cursor: pointer; transition: all 0.3s ease; font-size: 0.95rem; border-bottom: 1px solid rgba(0, 0, 0, 0.05);">
                                            <i class="fas fa-times" style="margin-right: 10px; width: 16px; text-align: center;"></i> Cancel Booking
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($b['status'] !== 'completed'): ?>
                                    <form method="post" style="display: contents;">
                                        <input type="hidden" name="booking_id" value="<?php echo $b['booking_id']; ?>">
                                        <input type="hidden" name="status" value="completed">
                                        <button name="update_status" class="dropdown-item" type="submit" style="display: block; width: 100%; padding: 12px 20px; text-decoration: none; color: #9e9e9e; border: none; background: none; text-align: left; cursor: pointer; transition: all 0.3s ease; font-size: 0.95rem; border-bottom: 1px solid rgba(0, 0, 0, 0.05);">
                                            <i class="fas fa-flag-checkered" style="margin-right: 10px; width: 16px; text-align: center;"></i> Mark Complete
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <a class="dropdown-item" href="?delete_booking=<?php echo $b['booking_id']; ?>" onclick="return confirm('⚠️ Delete this booking permanently?\n\nThis will remove:\n• The booking record\n• All associated orders\n• Payment information\n\nThis action cannot be undone!')" style="display: block; width: 100%; padding: 12px 20px; text-decoration: none; color: #f44336; border: none; background: none; text-align: left; cursor: pointer; transition: all 0.3s ease; font-size: 0.95rem;">
                                    <i class="fas fa-trash" style="margin-right: 10px; width: 16px; text-align: center;"></i> Delete Booking
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>

<script>
// Three-dot dropdown toggle function
function toggleDropdown(bookingId) {
    const dropdown = document.getElementById(`dropdown-${bookingId}`);
    const allDropdowns = document.querySelectorAll('.dropdown-menu');
    
    // Close all other dropdowns
    allDropdowns.forEach(menu => {
        if (menu.id !== `dropdown-${bookingId}`) {
            menu.style.opacity = '0';
            menu.style.visibility = 'hidden';
            menu.style.transform = 'translateY(-10px)';
        }
    });
    
    // Toggle current dropdown
    if (dropdown.style.opacity === '1') {
        dropdown.style.opacity = '0';
        dropdown.style.visibility = 'hidden';
        dropdown.style.transform = 'translateY(-10px)';
    } else {
        dropdown.style.opacity = '1';
        dropdown.style.visibility = 'visible';
        dropdown.style.transform = 'translateY(0)';
    }
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.style.opacity = '0';
            menu.style.visibility = 'hidden';
            menu.style.transform = 'translateY(-10px)';
        });
    }
});

// Add hover effects for dropdown items
document.addEventListener('DOMContentLoaded', function() {
    const style = document.createElement('style');
    style.textContent = `
        .dropdown-toggle:hover {
            background: rgba(102, 126, 234, 0.1) !important;
            color: #667eea !important;
            transform: scale(1.1);
        }
        
        .dropdown-item:hover {
            background: linear-gradient(45deg, #667eea, #764ba2) !important;
            color: white !important;
            transform: translateX(5px);
        }
        
        .dropdown-item:last-child {
            border-bottom: none !important;
        }
        
        .dropdown-menu.show {
            opacity: 1 !important;
            visibility: visible !important;
            transform: translateY(0) !important;
        }
    `;
    document.head.appendChild(style);
});

let autoRefreshInterval;
let isAutoRefreshEnabled = true;

// Initialize auto-refresh when page loads
document.addEventListener('DOMContentLoaded', function() {
    startAutoRefresh();
    
    // Add notification sound for new bookings
    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIG2m98OScTgwOUarm7blmGgU7k9n1unEiBC13yO/eizEIHWq+8+OWT');
    
    // Check for new bookings every 30 seconds
    setInterval(checkForNewBookings, 30000);
});

function startAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
    
    autoRefreshInterval = setInterval(function() {
        if (isAutoRefreshEnabled) {
            refreshBookings(true);
        }
    }, 30000); // Refresh every 30 seconds
}

function toggleAutoRefresh() {
    isAutoRefreshEnabled = !isAutoRefreshEnabled;
    const statusElement = document.getElementById('autoRefreshStatus');
    
    if (isAutoRefreshEnabled) {
        statusElement.innerHTML = '<i class="fas fa-sync-alt"></i> Auto-refresh: ON';
        statusElement.style.color = '#4caf50';
        startAutoRefresh();
    } else {
        statusElement.innerHTML = '<i class="fas fa-pause"></i> Auto-refresh: OFF';
        statusElement.style.color = '#ff9800';
        clearInterval(autoRefreshInterval);
    }
}

function refreshBookings(isAutomatic = false) {
    const refreshBtn = document.getElementById('refreshBtn');
    const liveIndicator = document.getElementById('liveIndicator');
    
    if (!isAutomatic) {
        refreshBtn.classList.add('loading');
        refreshBtn.disabled = true;
    }
    
    liveIndicator.style.background = 'linear-gradient(45deg, #ff9800, #f57c00)';
    
    fetch('?ajax=refresh')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateStatistics(data.stats);
                updateLastUpdated(data.timestamp);
                
                // Check for new bookings
                if (!isAutomatic) {
                    checkForNewBookings();
                }
                
                // Show success indicator
                liveIndicator.style.background = 'linear-gradient(45deg, #4caf50, #45a049)';
                
                // If there are new bookings, reload the page to show them
                if (data.stats.recent_bookings > 0 && isAutomatic) {
                    showNewBookingNotification(data.stats.recent_bookings);
                }
            } else {
                console.error('Refresh failed:', data.error);
                liveIndicator.style.background = 'linear-gradient(45deg, #f44336, #d32f2f)';
            }
        })
        .catch(error => {
            console.error('Error refreshing bookings:', error);
            liveIndicator.style.background = 'linear-gradient(45deg, #f44336, #d32f2f)';
        })
        .finally(() => {
            if (!isAutomatic) {
                refreshBtn.classList.remove('loading');
                refreshBtn.disabled = false;
            }
            
            // Reset indicator color after 2 seconds
            setTimeout(() => {
                liveIndicator.style.background = 'linear-gradient(45deg, #4caf50, #45a049)';
            }, 2000);
        });
}

function updateStatistics(stats) {
    document.getElementById('totalBookings').textContent = stats.total_bookings;
    document.getElementById('pendingBookings').textContent = stats.pending_bookings;
    document.getElementById('confirmedBookings').textContent = stats.confirmed_bookings;
    document.getElementById('todayBookings').textContent = stats.new_bookings_today;
    document.getElementById('recentBookings').textContent = stats.recent_bookings;
    
    // Animate numbers
    animateNumber('totalBookings', stats.total_bookings);
    animateNumber('pendingBookings', stats.pending_bookings);
    animateNumber('confirmedBookings', stats.confirmed_bookings);
    animateNumber('todayBookings', stats.new_bookings_today);
    animateNumber('recentBookings', stats.recent_bookings);
}

function animateNumber(elementId, newValue) {
    const element = document.getElementById(elementId);
    const currentValue = parseInt(element.textContent);
    
    if (currentValue !== newValue) {
        element.style.transform = 'scale(1.2)';
        element.style.color = '#4caf50';
        
        setTimeout(() => {
            element.style.transform = 'scale(1)';
            element.style.color = '#333';
        }, 300);
    }
}

function updateLastUpdated(timestamp) {
    const date = new Date(timestamp);
    const formatted = date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
    document.getElementById('lastUpdated').textContent = formatted;
}

function checkForNewBookings() {
    // This function can be enhanced to check for specific new bookings
    // and highlight them in the UI
    const bookingCards = document.querySelectorAll('.booking-card');
    const now = Math.floor(Date.now() / 1000);
    
    bookingCards.forEach(card => {
        const createdTime = parseInt(card.dataset.created);
        const minutesAgo = Math.floor((now - createdTime) / 60);
        
        if (minutesAgo <= 5) {
            card.style.border = '2px solid #ff4081';
            card.style.boxShadow = '0 0 20px rgba(255, 64, 129, 0.3)';
        }
    });
}

function showNewBookingNotification(count) {
    // Create notification
    const notification = document.createElement('div');
    notification.className = 'new-booking-notification';
    notification.innerHTML = `
        <i class="fas fa-bell"></i>
        <strong>${count} new booking${count > 1 ? 's' : ''} received!</strong>
        <button onclick="location.reload()" style="margin-left: 10px; padding: 5px 10px; background: #4caf50; color: white; border: none; border-radius: 5px; cursor: pointer;">
            View Now
        </button>
    `;
    
    // Style the notification
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(45deg, #ff4081, #e91e63);
        color: white;
        padding: 15px 20px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(255, 64, 129, 0.3);
        z-index: 1000;
        animation: slideIn 0.5s ease;
        display: flex;
        align-items: center;
        gap: 10px;
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 10 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.5s ease';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 500);
    }, 10000);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    .new-booking-notification {
        font-weight: 600;
        font-size: 0.9rem;
    }
`;
document.head.appendChild(style);

// Add click handler for auto-refresh toggle
document.getElementById('autoRefreshStatus').addEventListener('click', toggleAutoRefresh);
document.getElementById('autoRefreshStatus').style.cursor = 'pointer';
document.getElementById('autoRefreshStatus').title = 'Click to toggle auto-refresh';
</script>

</html>