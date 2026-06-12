<?php
session_start();
require_once "../connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_name = $_SESSION['name'] ?? 'User';

// Function to get meal time slots
function getMealTimeSlot($meal_type) {
    switch(strtolower($meal_type)) {
        case 'breakfast':
            return '7:00 AM - 11:00 AM';
        case 'lunch':
            return '11:00 AM - 5:00 PM';
        case 'dinner':
            return '5:00 PM - 12:00 AM';
        default:
            return 'All Day';
    }
}

// Automatic cleanup: Mark past bookings as 'completed' 
try {
    $cleanup_stmt = $conn->prepare("
        UPDATE bookings 
        SET status = 'completed' 
        WHERE status IN ('confirmed', 'pending') 
        AND booking_datetime <= NOW()
    ");
    $cleanup_stmt->execute();
} catch (Exception $e) {
    // Continue even if cleanup fails
}

// Get tables organized by location
try {
    $locations = ['Main Hall', 'VIP', 'Patio', 'Group'];
    $tables_by_location = [];
    
    foreach ($locations as $location) {
        $stmt = $conn->prepare("SELECT * FROM restaurant_tables WHERE location = ? ORDER BY table_label ASC");
        $stmt->execute([$location]);
        $tables = $stmt->fetchAll();
        
        // Get bookings for each table
        foreach ($tables as &$table) {
            $booking_stmt = $conn->prepare("
                SELECT b.id, b.booking_datetime, b.meal_type, b.guests, b.status
                FROM bookings b 
                WHERE b.table_id = ? 
                AND b.status IN ('confirmed', 'pending') 
                AND b.booking_datetime > NOW()
                ORDER BY b.booking_datetime ASC
            ");
            $booking_stmt->execute([$table['id']]);
            $table['bookings'] = $booking_stmt->fetchAll();
            
            // Determine overall status
            $table['status'] = empty($table['bookings']) ? 'Available' : 'Has Reservations';
        }
        
        $tables_by_location[$location] = $tables;
    }
} catch (Exception $e) {
    $tables_by_location = [];
}
?>

<!DOCTYPE html>
<html>
<head>
<title>View Tables</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: Arial; background: linear-gradient(135deg,  #b3dae6ff 100%, #aeb5ddff 0%); min-height: 100vh; }
header { background: rgba(255,255,255,0.95); padding: 20px; display: flex; justify-content: space-between; align-items: center; }

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
.container { max-width: 1400px; margin: auto; padding: 20px; }
.title { text-align: center; color: white; font-size: 2.5rem; margin-bottom: 20px; }

/* Location Tabs */
.location-tabs { 
    display: flex; 
    justify-content: center; 
    gap: 10px; 
    margin-bottom: 20px; 
    flex-wrap: wrap;
}

/* Seat Filter Tabs */
.seat-filter-tabs { 
    display: flex; 
    justify-content: center; 
    gap: 10px; 
    margin-bottom: 30px; 
    flex-wrap: wrap;
}

.seat-filter-btn { 
    padding: 10px 20px; 
    background: rgba(255,255,255,0.2); 
    color: white; 
    border: 2px solid rgba(255,152,0,0.3); 
    border-radius: 20px; 
    cursor: pointer; 
    transition: 0.3s; 
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    font-size: 0.9rem;
}

.seat-filter-btn:hover { 
    background: rgba(255,152,0,0.2); 
    transform: translateY(-2px);
    border-color: rgba(255,152,0,0.5);
}

.seat-filter-btn.active {
    background: rgba(255,152,0,0.9); 
    color: white; 
    border-color: #ff9800;
}

.tab-btn { 
    padding: 12px 25px; 
    background: rgba(255,255,255,0.2); 
    color: white; 
    border: 2px solid rgba(255,255,255,0.3); 
    border-radius: 25px; 
    cursor: pointer; 
    transition: 0.3s; 
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}
.tab-btn:hover { 
    background: rgba(255,255,255,0.3); 
    transform: translateY(-2px);
}
.tab-btn.active { 
    background: white; 
    color: #667eea; 
    border-color: white;
}

/* Location Content */
.location-content { 
    display: none; 
}
.location-content.active { 
    display: block; 
}
.location-title { 
    text-align: center; 
    color: white; 
    font-size: 1.8rem; 
    margin-bottom: 25px; 
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}



/* No Tables Message */
.no-tables { 
    text-align: center; 
    padding: 60px 20px; 
    background: rgba(255,255,255,0.95); 
    border-radius: 20px; 
    color: #666;
}
.no-tables i { 
    font-size: 3rem; 
    margin-bottom: 15px; 
    color: #ddd; 
}

/* Table Image Placeholder */
.table-img-placeholder { 
    height: 200px; 
    background: #f0f0f0; 
    display: flex; 
    flex-direction: column; 
    align-items: center; 
    justify-content: center; 
    color: #999;
}
.table-img-placeholder i { 
    font-size: 2rem; 
    margin-bottom: 10px; 
}

.current-time-info {
    text-align: center;
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.1rem;
    margin-bottom: 30px;
    background: rgba(255, 255, 255, 0.1);
    padding: 10px 20px;
    border-radius: 25px;
    display: inline-block;
    margin-left: 50%;
    transform: translateX(-50%);
}

.current-time-info i {
    margin-right: 8px;
}
.table-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, 500px); 
    gap: 30px; 
    justify-content: center;
}
.table-card { 
    width: 500px;
    height: 550px;
    background: white; 
    border-radius: 20px; 
    overflow: hidden; 
    box-shadow: 0 15px 40px rgba(0,0,0,0.2); 
    transition: 0.3s; 
    display: flex;
    flex-direction: column;
}
.table-card:hover { transform: translateY(-10px); box-shadow: 0 20px 50px rgba(0,0,0,0.3); }
.table-img { 
    width: 100%; 
    height: 250px; 
    object-fit: cover; 
    flex-shrink: 0;
}
.table-img-placeholder { 
    height: 250px; 
    background: #f0f0f0; 
    display: flex; 
    flex-direction: column; 
    align-items: center; 
    justify-content: center; 
    color: #999;
    flex-shrink: 0;
}
.table-img-placeholder i { 
    font-size: 3rem; 
    margin-bottom: 10px; 
}
.table-info { 
    padding: 25px; 
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.table-name { 
    font-size: 1.4rem; 
    margin-bottom: 15px; 
    color: #333; 
    text-align: center;
    font-weight: 600;
    background: linear-gradient(45deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.table-details { 
    margin-bottom: 15px; 
    color: #666; 
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 10px;
    justify-content: center;
}
.table-details i {
    color: #667eea;
    font-size: 1.2rem;
}
.status { 
    padding: 10px 16px; 
    border-radius: 25px; 
    font-size: 0.9rem; 
    font-weight: 600; 
    margin-bottom: 20px; 
    display: block; 
    text-align: center;
    border: 1px solid;
}
.available { 
    background: rgba(40, 167, 69, 0.2); 
    color: #28a745; 
    border-color: rgba(40, 167, 69, 0.4);
}
.has-reservations { 
    background: rgba(255, 193, 7, 0.2); 
    color: #ffc107; 
    border-color: rgba(255, 193, 7, 0.4);
}

/* Simple status display only */

/* Table Actions */
.table-actions {
    display: flex;
    gap: 12px;
    margin-top: auto;
}

.view-details-btn {
    flex: 1;
    padding: 12px 15px;
    background: linear-gradient(45deg, #74b9ff, #0984e3);
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
    font-size: 0.95rem;
}

.view-details-btn:hover {
    background: linear-gradient(45deg, #0984e3, #74b9ff);
    transform: translateY(-2px);
}
.book-btn { 
    flex: 1;
    padding: 12px 15px; 
    background: linear-gradient(45deg, #667eea, #764ba2); 
    color: white; 
    text-decoration: none; 
    border-radius: 10px; 
    text-align: center; 
    font-weight: 500;
    transition: 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 0.95rem;
}
.book-btn:hover { 
    background: linear-gradient(45deg, #764ba2, #667eea); 
    transform: translateY(-2px);
}
.book-btn:disabled { background: #ccc; cursor: not-allowed; }

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 30px;
    border-radius: 15px;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow-y: auto;
    position: relative;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-close {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: #aaa;
}

.modal-close:hover {
    color: #000;
}

.modal-header {
    margin-bottom: 25px;
    text-align: center;
}

.modal-title {
    font-size: 1.5rem;
    color: #333;
    margin-bottom: 10px;
}

.modal-subtitle {
    color: #666;
    font-size: 1rem;
}

/* Modal Reservation Styles */
.reservation-list {
    max-height: 400px;
    overflow-y: auto;
}

.modal-reservation-item {
    background: rgba(102, 126, 234, 0.1);
    border: 1px solid rgba(102, 126, 234, 0.3);
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 10px;
}

.reservation-simple {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.simple-date, .simple-time {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    color: #555;
    font-weight: 500;
}

.simple-date i, .simple-time i {
    color: #667eea;
}

/* Simplified modal - only date and time */

.no-reservations-modal {
    text-align: center;
    padding: 30px;
}

.no-reservations-modal i {
    font-size: 3rem;
    color: #28a745;
    margin-bottom: 20px;
}

.no-reservations-modal h3 {
    color: #333;
    margin-bottom: 15px;
}

.no-reservations-modal p {
    color: #666;
    margin-bottom: 25px;
}

.available-times {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 20px;
}

.time-slot-available {
    background: rgba(40, 167, 69, 0.1);
    border: 1px solid rgba(40, 167, 69, 0.3);
    padding: 12px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #28a745;
    font-weight: 500;
}
@media (max-width: 768px) { 
    .table-grid { 
        grid-template-columns: 1fr; 
        gap: 20px;
    } 
    .location-tabs { gap: 5px; }
    .tab-btn { padding: 10px 15px; font-size: 0.9rem; }
    
    .table-card {
        width: 100%;
        max-width: 450px;
        height: 500px;
        margin: 0 auto;
    }
    
    .table-img, .table-img-placeholder {
        height: 200px;
    }
    
    .table-img-placeholder i {
        font-size: 2.5rem;
    }
    
    .table-info {
        padding: 20px;
    }
    
    .table-name {
        font-size: 1.3rem;
        margin-bottom: 12px;
    }
    
    .table-details {
        font-size: 1rem;
        margin-bottom: 12px;
    }
    
    .status {
        padding: 8px 14px;
        font-size: 0.85rem;
        margin-bottom: 15px;
    }
    
    .table-actions {
        gap: 10px;
    }
    
    .view-details-btn, .book-btn {
        padding: 10px 12px;
        font-size: 0.9rem;
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
    }
}

@media (max-width: 480px) {
    .table-card {
        max-width: 350px;
        height: 450px;
    }
    
    .table-img, .table-img-placeholder {
        height: 160px;
    }
    
    .table-img-placeholder i {
        font-size: 2rem;
    }
    
    .table-info {
        padding: 15px;
    }
    
    .table-name {
        font-size: 1.2rem;
    }
    
    .table-details {
        font-size: 0.95rem;
    }
    
    .view-details-btn, .book-btn {
        padding: 8px 10px;
        font-size: 0.85rem;
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
        <h2>Welcome, <?php echo htmlspecialchars($user_name); ?></h2>
    </div>
    <nav>
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="View_Tables.php"><i class="fas fa-chair"></i> Tables</a>
        <a href="view_menu.php"><i class="fas fa-utensils"></i> Food</a>
        <a href="view_drink_menu.php"><i class="fas fa-cocktail"></i> Drinks</a>
        <a href="write_comment.php"><i class="fas fa-star"></i> Comments</a>
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
    
    <a href="write_comment.php">
        <i class="fas fa-star"></i> Comments
    </a>
    
    <a href="../logout.php" style="background: linear-gradient(45deg, #ff6b6b, #ee5a24); color: white; margin-top: 20px;">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay" onclick="toggleMobileMenu()"></div>

<div class="container">
    <h1 class="title">Available Tables</h1>
    <div class="current-time-info">
        <i class="fas fa-clock"></i> Current Time: <?php echo date('M j, Y g:i A'); ?>
    </div>
    
    <!-- Location Tabs -->
    <div class="location-tabs">
        <button class="tab-btn active" onclick="showLocation('Main Hall')">
            <i class="fas fa-home"></i> Main Hall
        </button>
        <button class="tab-btn" onclick="showLocation('VIP')">
            <i class="fas fa-crown"></i> VIP
        </button>
        <button class="tab-btn" onclick="showLocation('Patio')">
            <i class="fas fa-tree"></i> Patio
        </button>
        <button class="tab-btn" onclick="showLocation('Group')">
            <i class="fas fa-users"></i> Group
        </button>
    </div>

    <!-- Seat Filter Buttons - Only for Main Hall -->
    <div class="seat-filter-tabs" id="seatFilterTabs">
        <button class="seat-filter-btn active" onclick="filterBySeats('all')">
            <i class="fas fa-th"></i> All Tables
        </button>
        <button class="seat-filter-btn" onclick="filterBySeats('2')">
            <i class="fas fa-chair"></i> 2 Seats
        </button>
        <button class="seat-filter-btn" onclick="filterBySeats('4')">
            <i class="fas fa-couch"></i> 4 Seats
        </button>
        <button class="seat-filter-btn" onclick="filterBySeats('other')">
            <i class="fas fa-users"></i> Other Sizes
        </button>
    </div>
    
    <!-- Main Hall Tables -->
    <div id="Main Hall" class="location-content active">
        <h2 class="location-title">
            <i class="fas fa-home"></i> Main Hall Tables
        </h2>
        
        <?php if (!empty($tables_by_location['Main Hall'])): ?>
        <div class="table-grid">
            <?php foreach ($tables_by_location['Main Hall'] as $table): ?>
            <div class="table-card" data-seats="<?php echo $table['capacity']; ?>">
                <?php if (!empty($table['image']) && file_exists("../uploads/tables/" . $table['image'])): ?>
                    <img src="../uploads/tables/<?php echo $table['image']; ?>" alt="Table" class="table-img">
                <?php else: ?>
                    <div class="table-img-placeholder">
                        <i class="fas fa-chair"></i>
                        <span>No Image</span>
                    </div>
                <?php endif; ?>
                
                <div class="table-info">
                    <h3 class="table-name"><?php echo htmlspecialchars($table['table_label']); ?></h3>
                    <div class="table-details">
                        <i class="fas fa-users"></i> <?php echo $table['capacity']; ?> seats
                    </div>
                    
                    <span class="status <?php echo strtolower(str_replace(' ', '-', $table['status'])); ?>">
                        <?php echo $table['status']; ?>
                    </span>
                    
                    <div class="table-actions">
                        <a href="choose_time.php?table_id=<?php echo $table['id']; ?>" class="book-btn">
                            <i class="fas fa-plus"></i> Book Table
                        </a>
                        <button class="view-details-btn" onclick="showTableDetails(<?php echo $table['id']; ?>, '<?php echo htmlspecialchars($table['table_label']); ?>')">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-tables">
            <i class="fas fa-chair"></i>
            <p>No tables available in Main Hall</p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Other Locations (VIP, Patio, Group) -->
    <?php foreach (['VIP', 'Patio', 'Group'] as $location): ?>
    <div id="<?php echo $location; ?>" class="location-content">
        <h2 class="location-title">
            <i class="fas fa-<?php echo $location === 'VIP' ? 'crown' : ($location === 'Patio' ? 'tree' : 'users'); ?>"></i> 
            <?php echo $location; ?> Tables
        </h2>
        
        <?php if (!empty($tables_by_location[$location])): ?>
        <div class="table-grid">
            <?php foreach ($tables_by_location[$location] as $table): ?>
            <div class="table-card" data-seats="<?php echo $table['capacity']; ?>">
                <?php if (!empty($table['image']) && file_exists("../uploads/tables/" . $table['image'])): ?>
                    <img src="../uploads/tables/<?php echo $table['image']; ?>" alt="Table" class="table-img">
                <?php else: ?>
                    <div class="table-img-placeholder">
                        <i class="fas fa-chair"></i>
                        <span>No Image</span>
                    </div>
                <?php endif; ?>
                
                <div class="table-info">
                    <h3 class="table-name"><?php echo htmlspecialchars($table['table_label']); ?></h3>
                    <div class="table-details">
                        <i class="fas fa-users"></i> <?php echo $table['capacity']; ?> seats
                    </div>
                    
                    <span class="status <?php echo strtolower(str_replace(' ', '-', $table['status'])); ?>">
                        <?php echo $table['status']; ?>
                    </span>
                    
                    <div class="table-actions">
                        <a href="choose_time.php?table_id=<?php echo $table['id']; ?>" class="book-btn">
                            <i class="fas fa-plus"></i> Book Table
                        </a>
                        <button class="view-details-btn" onclick="showTableDetails(<?php echo $table['id']; ?>, '<?php echo htmlspecialchars($table['table_label']); ?>')">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-tables">
            <i class="fas fa-chair"></i>
            <p>No tables available in <?php echo $location; ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Table Details Modal -->
<div id="tableDetailsModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <div class="modal-header">
            <h2 class="modal-title" id="modalTableName">Table Details</h2>
            <p class="modal-subtitle">Complete reservation schedule</p>
        </div>
        <div id="modalTableDetails">
            <!-- Table details will be loaded here -->
        </div>
    </div>
</div>

<script>
// Seat filtering functionality
function filterBySeats(seatCount) {
    // Remove active class from all seat filter buttons
    const seatBtns = document.querySelectorAll('.seat-filter-btn');
    seatBtns.forEach(btn => btn.classList.remove('active'));
    
    // Add active class to clicked button
    event.target.classList.add('active');
    
    // Get all table cards in the currently active location
    const activeLocation = document.querySelector('.location-content.active');
    const tableCards = activeLocation.querySelectorAll('.table-card');
    
    tableCards.forEach(card => {
        const cardSeats = card.getAttribute('data-seats');
        let shouldShow = false;
        
        if (seatCount === 'all') {
            shouldShow = true;
        } else if (seatCount === '2') {
            shouldShow = cardSeats === '2';
        } else if (seatCount === '4') {
            shouldShow = cardSeats === '4';
        } else if (seatCount === 'other') {
            shouldShow = cardSeats !== '2' && cardSeats !== '4';
        }
        
        if (shouldShow) {
            card.style.display = 'flex';
            card.style.opacity = '1';
            card.style.transform = 'scale(1)';
        } else {
            card.style.display = 'none';
        }
    });
    
    // Update the display message if no tables match
    updateNoTablesMessage(activeLocation, seatCount);
}

// Update no tables message based on filter
function updateNoTablesMessage(locationElement, seatCount) {
    const visibleCards = locationElement.querySelectorAll('.table-card[style*="display: flex"], .table-card:not([style*="display: none"])');
    const noTablesDiv = locationElement.querySelector('.no-tables');
    const tablesGrid = locationElement.querySelector('.table-grid');
    
    if (visibleCards.length === 0 && tablesGrid) {
        if (!noTablesDiv) {
            const newNoTablesDiv = document.createElement('div');
            newNoTablesDiv.className = 'no-tables';
            newNoTablesDiv.innerHTML = `
                <i class="fas fa-chair"></i>
                <p>No ${seatCount === 'all' ? '' : (seatCount === 'other' ? 'other sized' : seatCount + '-seat')} tables found</p>
                <small>Try a different filter or check other locations</small>
            `;
            tablesGrid.parentNode.appendChild(newNoTablesDiv);
        }
        tablesGrid.style.display = 'none';
    } else if (tablesGrid) {
        tablesGrid.style.display = 'grid';
        const tempNoTablesDiv = locationElement.querySelector('.no-tables:not(.original-no-tables)');
        if (tempNoTablesDiv) {
            tempNoTablesDiv.remove();
        }
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

// Table details modal functions
function showTableDetails(tableId, tableName) {
    document.getElementById('modalTableName').textContent = tableName + ' - Reservation Details';
    
    // Get table details via AJAX
    fetch('get_table_details.php?table_id=' + tableId)
        .then(response => response.json())
        .then(data => {
            let detailsHTML = '';
            
            if (data.bookings && data.bookings.length > 0) {
                detailsHTML = '<div class="reservation-list">';
                
                data.bookings.forEach(booking => {
                    const timeSlot = getMealTimeSlot(booking.meal_type);
                    const bookingDate = new Date(booking.booking_datetime);
                    
                    detailsHTML += `
                        <div class="modal-reservation-item">
                            <div class="reservation-simple">
                                <div class="simple-date">
                                    <i class="fas fa-calendar"></i> 
                                    ${bookingDate.toLocaleDateString('en-US', {
                                        year: 'numeric',
                                        month: 'short',
                                        day: 'numeric'
                                    })}
                                </div>
                                <div class="simple-time">
                                    <i class="fas fa-clock"></i> 
                                    ${timeSlot}
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                detailsHTML += '</div>';
            } else {
                detailsHTML = `
                    <div class="no-reservations-modal">
                        <i class="fas fa-check-circle"></i>
                        <h3>No Current Reservations</h3>
                        <p>This table is available for booking at all meal times.</p>
                        <div class="available-times">
                            <div class="time-slot-available">
                                <i class="fas fa-sun"></i> Breakfast: 7:00 AM - 11:00 AM
                            </div>
                            <div class="time-slot-available">
                                <i class="fas fa-utensils"></i> Lunch: 11:00 AM - 5:00 PM
                            </div>
                            <div class="time-slot-available">
                                <i class="fas fa-moon"></i> Dinner: 5:00 PM - 12:00 AM
                            </div>
                        </div>
                    </div>
                `;
            }
            
            document.getElementById('modalTableDetails').innerHTML = detailsHTML;
            document.getElementById('tableDetailsModal').style.display = 'block';
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('modalTableDetails').innerHTML = '<p>Error loading table details.</p>';
            document.getElementById('tableDetailsModal').style.display = 'block';
        });
}

function closeModal() {
    document.getElementById('tableDetailsModal').style.display = 'none';
}

function getMealTimeSlot(mealType) {
    switch(mealType.toLowerCase()) {
        case 'breakfast':
            return '7:00 AM - 11:00 AM';
        case 'lunch':
            return '11:00 AM - 5:00 PM';
        case 'dinner':
            return '5:00 PM - 12:00 AM';
        default:
            return 'All Day';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('tableDetailsModal');
    if (event.target == modal) {
        closeModal();
    }
}

// Location tab functionality
function showLocation(locationName) {
    // Hide all location contents
    const contents = document.querySelectorAll('.location-content');
    contents.forEach(content => content.classList.remove('active'));
    
    // Remove active class from all tabs
    const tabs = document.querySelectorAll('.tab-btn');
    tabs.forEach(tab => tab.classList.remove('active'));
    
    // Show selected location content
    const selectedContent = document.getElementById(locationName);
    if (selectedContent) {
        selectedContent.classList.add('active');
    }
    
    // Add active class to clicked tab
    const clickedTab = event.target.closest('.tab-btn');
    if (clickedTab) {
        clickedTab.classList.add('active');
    }
    
    // Show/hide seat filter buttons based on location
    const seatFilterTabs = document.getElementById('seatFilterTabs');
    if (locationName === 'Main Hall') {
        seatFilterTabs.style.display = 'flex';
    } else {
        seatFilterTabs.style.display = 'none';
    }
    
    // Reset seat filter to "All Tables" when switching locations
    const seatBtns = document.querySelectorAll('.seat-filter-btn');
    seatBtns.forEach(btn => btn.classList.remove('active'));
    seatBtns[0].classList.add('active'); // First button is "All Tables"
    
    // Show all tables in the new location
    if (selectedContent) {
        const tableCards = selectedContent.querySelectorAll('.table-card');
        tableCards.forEach(card => {
            card.style.display = 'flex';
            card.style.opacity = '1';
            card.style.transform = 'scale(1)';
        });
        
        // Remove any temporary no-tables messages
        const tempNoTablesDiv = selectedContent.querySelector('.no-tables:not(.original-no-tables)');
        if (tempNoTablesDiv) {
            tempNoTablesDiv.remove();
        }
        
        const tablesGrid = selectedContent.querySelector('.table-grid');
        if (tablesGrid) {
            tablesGrid.style.display = 'grid';
        }
    }
}

// Book table function
function bookTable(tableId) {
    window.location.href = 'choose_time.php?table_id=' + tableId;
}

// Auto-refresh every 60 seconds (increased from 30 to reduce server load)
setInterval(() => location.reload(), 60000);
</script>
</body>
</html>
