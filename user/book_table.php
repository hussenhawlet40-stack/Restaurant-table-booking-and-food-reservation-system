<?php
session_start();
require_once "../connection.php";
require_once "../includes/payment_functions.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['table_id'])) {
    die("Invalid table.");
}

$table_id = $_GET['table_id'];
$user_name = $_SESSION['name'] ?? 'User';
$user_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Ensure bookings table exists with correct structure
try {
    // First, check if table exists and get its structure
    $result = $conn->query("SHOW TABLES LIKE 'bookings'");
    if ($result->rowCount() == 0) {
        // Table doesn't exist, create it
        $conn->exec("CREATE TABLE bookings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            table_id INT NOT NULL,
            booking_datetime DATETIME NOT NULL,
            meal_type VARCHAR(50) NOT NULL,
            guests INT NOT NULL,
            special_requests TEXT,
            status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } else {
        // Table exists, check if booking_datetime column exists
        $columns = $conn->query("SHOW COLUMNS FROM bookings LIKE 'booking_datetime'");
        if ($columns->rowCount() == 0) {
            // Column doesn't exist, add it
            $conn->exec("ALTER TABLE bookings ADD COLUMN booking_datetime DATETIME NOT NULL AFTER table_id");
        }
        
        // Check other required columns
        $required_columns = [
            'meal_type' => "VARCHAR(50) NOT NULL",
            'guests' => "INT NOT NULL", 
            'special_requests' => "TEXT",
            'status' => "ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending'"
        ];
        
        foreach ($required_columns as $col_name => $col_definition) {
            $col_check = $conn->query("SHOW COLUMNS FROM bookings LIKE '$col_name'");
            if ($col_check->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN $col_name $col_definition");
            }
        }
    }
} catch (Exception $e) {
    // If all else fails, drop and recreate the table
    try {
        $conn->exec("DROP TABLE IF EXISTS bookings");
        $conn->exec("CREATE TABLE bookings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            table_id INT NOT NULL,
            booking_datetime DATETIME NOT NULL,
            meal_type VARCHAR(50) NOT NULL,
            guests INT NOT NULL,
            special_requests TEXT,
            status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e2) {
        $error = "Database setup error: " . $e2->getMessage();
    }
}

// Get table info
try {
    $stmt = $conn->prepare("SELECT * FROM restaurant_tables WHERE id = ?");
    $stmt->execute([$table_id]);
    $table = $stmt->fetch();
    
    if (!$table) {
        die("Table not found.");
    }
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Handle booking submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $meal = trim($_POST["meal"] ?? '');
    $selected_date = trim($_POST["date"] ?? '');
    $selected_time = trim($_POST["time"] ?? '');
    $guests = trim($_POST["guests"] ?? '');
    $special_requests = trim($_POST["special_requests"] ?? '');

    // Validate inputs
    if (empty($meal) || empty($selected_date) || empty($selected_time) || empty($guests)) {
        $error = "Please fill in all required fields.";
    } elseif (!is_numeric($guests) || $guests < 1) {
        $error = "Please select a valid number of guests.";
    } elseif ($guests > $table['capacity']) {
        $error = "Number of guests exceeds table capacity (" . $table['capacity'] . ").";
    } else {
        // Create booking datetime
        $booking_datetime = $selected_date . ' ' . $selected_time;
        $booking_timestamp = strtotime($booking_datetime);
        
        // Get current date and time
        $current_date = date('Y-m-d');
        $current_time = date('H:i');
        $current_datetime = $current_date . ' ' . $current_time;
        $current_timestamp = strtotime($current_datetime);

        if ($booking_timestamp === false) {
            $error = "Invalid date or time format.";
        } elseif ($selected_date < $current_date) {
            $error = "Please select today's date or a future date.";
        } elseif ($selected_date == $current_date && $selected_time <= $current_time) {
            $error = "Please select a future time for today, or choose a different date.";
        } else {
            // Check if table is already booked for this specific date and time (exclude completed bookings)
            try {
                $check_stmt = $conn->prepare("
                    SELECT COUNT(*) FROM bookings 
                    WHERE table_id = ? 
                    AND booking_datetime = ?
                    AND status IN ('pending', 'confirmed')
                ");
                $check_stmt->execute([$table_id, $booking_datetime]);
                $existing_bookings = $check_stmt->fetchColumn();
                
                if ($existing_bookings > 0) {
                    $error = "This table is already booked for " . date('M j, Y g:i A', strtotime($booking_datetime)) . ". Please choose a different time.";
                } else {
                    // Insert booking
                    $stmt = $conn->prepare("
                        INSERT INTO bookings (user_id, table_id, booking_datetime, meal_type, guests, special_requests, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    
                    if ($stmt->execute([$user_id, $table_id, $booking_datetime, $meal, $guests, $special_requests])) {
                        $booking_id = $conn->lastInsertId();
                        
                        // Create payments table if it doesn't exist
                        createPaymentsTable($conn);
                        
                        // Check if payment is required for bookings
                        if (defined('REQUIRE_PAYMENT_FOR_BOOKING') && REQUIRE_PAYMENT_FOR_BOOKING && defined('BOOKING_DEPOSIT_AMOUNT') && BOOKING_DEPOSIT_AMOUNT > 0) {
                            // Redirect to payment page for booking deposit
                            header("Location: payment_select.php?type=booking&booking_id=" . $booking_id . "&amount=" . BOOKING_DEPOSIT_AMOUNT);
                            exit();
                        } else {
                            // No payment required, confirm booking
                            $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?")->execute([$booking_id]);
                            $success = "Your table has been booked successfully! Booking ID: #" . $booking_id;
                        }
                        
                        // Clear form data after successful booking
                        $_POST = array();
                    } else {
                        $error = "Failed to book table. Please try again.";
                    }
                }
            } catch (Exception $e) {
                $error = "Booking failed: " . $e->getMessage();
            }
        }
    }
}

// Get meal type, date, and specific time from URL if coming from choose_time.php
$selected_meal = $_GET['time'] ?? '';
$selected_booking_date = $_GET['date'] ?? date('Y-m-d');
$selected_specific_time = $_GET['specific_time'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
<title>Book Table</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: Arial; background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100vh; }
header { background: rgba(255,255,255,0.95); padding: 20px; display: flex; justify-content: space-between; align-items: center; }
nav a { color: #333; text-decoration: none; margin: 0 10px; padding: 8px 15px; border-radius: 20px; transition: 0.3s; }
nav a:hover { background: #667eea; color: white; }
.logout-btn { background: #ff6b6b; color: white !important; }
.container { max-width: 600px; margin: 50px auto; background: white; padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
.title { text-align: center; color: #333; margin-bottom: 20px; }
.table-info { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
.form-group { margin-bottom: 20px; }
.form-label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
.form-input, .form-select, .form-textarea { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 10px; font-size: 16px; }
.form-input:focus, .form-select:focus, .form-textarea:focus { border-color: #667eea; outline: none; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.submit-btn { width: 100%; padding: 15px; background: #667eea; color: white; border: none; border-radius: 10px; font-size: 16px; cursor: pointer; transition: 0.3s; }
.submit-btn:hover { background: #5a6fd8; }
.message { padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
@media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } .container { margin: 20px; padding: 20px; } }
</style>
</head>
<body>
<header>
    <h2>Welcome, <?php echo htmlspecialchars($user_name); ?></h2>
    <nav>
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="View_Tables.php"><i class="fas fa-chair"></i> Tables</a>
        <a href="view_menu.php"><i class="fas fa-utensils"></i> Food</a>
        <a href="view_drink_menu.php"><i class="fas fa-cocktail"></i> Drinks</a>
        <a href="write_comment.php"><i class="fas fa-star"></i> Comments</a>
        <a class="logout-btn" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</header>

<div class="container">
    <h1 class="title">Book Table: <?php echo htmlspecialchars($table['table_label']); ?></h1>
    
    <div class="table-info">
        <strong>Capacity:</strong> <?php echo $table['capacity']; ?> guests<br>
        <strong>Location:</strong> <?php echo htmlspecialchars($table['location']); ?>
    </div>

    <?php if ($success): ?>
        <div class="message success"><?= $success ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="message error"><?= $error ?></div>
    <?php endif; ?>
    
    <!-- Debug Information -->
    <div style="background: #e3f2fd; padding: 10px; border-radius: 5px; margin-bottom: 20px; font-size: 12px;">
        <strong>Debug Info:</strong><br>
        Current Server Date: <?php echo date('Y-m-d'); ?><br>
        Current Server Time: <?php echo date('g:i A'); ?> (<?php echo date('H:i:s'); ?>)<br>
        Current Server Timezone: <?php echo date_default_timezone_get(); ?>
    </div>

    <form method="POST">
        <div class="form-group">
            <label class="form-label">Meal Type *</label>
            <select name="meal" class="form-select" required>
                <option value="">Select meal type</option>
                <option value="Breakfast" <?php echo ($selected_meal == 'Breakfast') ? 'selected' : ''; ?>>🍳 Breakfast (7:00 AM - 11:00 AM)</option>
                <option value="Lunch" <?php echo ($selected_meal == 'Lunch') ? 'selected' : ''; ?>>🍛 Lunch (11:00 AM - 5:00 PM)</option>
                <option value="Dinner" <?php echo ($selected_meal == 'Dinner') ? 'selected' : ''; ?>>🍽️ Dinner (5:00 PM - 12:00 AM)</option>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Date *</label>
                <input type="date" name="date" class="form-input" required min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+1 year')); ?>" value="<?php echo isset($_POST['date']) ? $_POST['date'] : $selected_booking_date; ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Time *</label>
                <select name="time" class="form-select" required>
                    <option value="">Select time</option>
                    <?php
                    // Generate 12-hour time options
                    $times = array();
                    for ($hour = 7; $hour <= 23; $hour++) {
                        for ($minute = 0; $minute < 60; $minute += 30) {
                            $time_24 = sprintf("%02d:%02d", $hour, $minute);
                            $time_12 = date("g:i A", strtotime($time_24));
                            $selected = '';
                            if (isset($_POST['time']) && $_POST['time'] == $time_24) {
                                $selected = 'selected';
                            } elseif (!isset($_POST['time']) && $selected_specific_time == $time_24) {
                                $selected = 'selected';
                            }
                            echo "<option value='$time_24' $selected>$time_12</option>";
                        }
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Number of Guests *</label>
            <select name="guests" class="form-select" required>
                <option value="">Select number of guests</option>
                <?php for($i = 1; $i <= $table['capacity']; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo (isset($_POST['guests']) && $_POST['guests'] == $i) ? 'selected' : ''; ?>>
                        <?php echo $i; ?> Guest<?php echo $i > 1 ? 's' : ''; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Special Requests (Optional)</label>
            <textarea name="special_requests" class="form-textarea" rows="3" placeholder="Any dietary restrictions, special occasions, or other requests..."><?php echo isset($_POST['special_requests']) ? htmlspecialchars($_POST['special_requests']) : ''; ?></textarea>
        </div>
        
        <button type="submit" class="submit-btn">Confirm Booking</button>
    </form>
    
    <br>
    <a href="choose_time.php?table_id=<?php echo $table_id; ?>" style="color: #667eea;">← Back to Time Selection</a>
</div>

</body>
</html>
