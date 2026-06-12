<?php
//choose time
session_start();
require_once "../connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['table_id'])) {
    die("Invalid table.");
}

$table_id = $_GET['table_id'];
$user_name = $_SESSION['name'] ?? 'User';

// Get table info
$stmt = $conn->prepare("SELECT * FROM restaurant_tables WHERE id = ?");
$stmt->execute([$table_id]);
$table = $stmt->fetch();

if (!$table) {
    die("Table not found.");
}

// Clean up past bookings first
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

// Check which specific times are already booked for future dates only
$stmt = $conn->prepare("
    SELECT booking_datetime, meal_type, DATE(booking_datetime) as booking_date, TIME(booking_datetime) as booking_time
    FROM bookings 
    WHERE table_id = ? 
    AND status IN ('confirmed', 'pending') 
    AND booking_datetime > NOW()
");
$stmt->execute([$table_id]);
$existing_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create array of booked time slots by date and meal type
$booked_slots = [];
$booked_times = [];
foreach ($existing_bookings as $booking) {
    $date = $booking['booking_date'];
    $meal = strtolower($booking['meal_type']);
    $time = $booking['booking_time'];
    
    if (!isset($booked_slots[$date])) {
        $booked_slots[$date] = [];
    }
    $booked_slots[$date][] = $meal;
    
    // Also track specific booked times
    if (!isset($booked_times[$date])) {
        $booked_times[$date] = [];
    }
    $booked_times[$date][] = $time;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Select Meal Time - Restaurant Booking</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }

        .particle:nth-child(1) { width: 15px; height: 15px; left: 15%; animation-delay: 0s; }
        .particle:nth-child(2) { width: 20px; height: 20px; left: 25%; animation-delay: 1s; }
        .particle:nth-child(3) { width: 12px; height: 12px; left: 35%; animation-delay: 2s; }
        .particle:nth-child(4) { width: 18px; height: 18px; left: 45%; animation-delay: 3s; }

        @keyframes float {
            0%, 100% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10%, 90% { opacity: 1; }
            50% { transform: translateY(-100px) rotate(180deg); }
        }

        header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            color: #333;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        header h2 {
            margin: 0;
            font-size: 24px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        nav {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        nav a {
            color: #333;
            text-decoration: none;
            font-weight: 500;
            padding: 10px 15px;
            border-radius: 25px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        nav a:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, #667eea, #764ba2);
            transition: left 0.3s ease;
            z-index: -1;
        }

        nav a:hover:before { left: 0; }
        nav a:hover { color: white; transform: translateY(-2px); }

        .logout-btn {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            padding: 10px 20px;
            color: #fff !important;
            border-radius: 25px;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(238, 90, 36, 0.3);
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(238, 90, 36, 0.4);
        }

        .main-content {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 80px);
            padding: 40px 20px;
        }

        .container {
            max-width: 500px;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
            animation: slideUp 0.6s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: bold;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .page-subtitle {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }

        .table-info {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .table-name {
            font-size: 1.3rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }

        .table-details {
            display: flex;
            justify-content: space-around;
            color: #666;
            font-size: 0.95rem;
        }

        .meal-options {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .meal-btn {
            display: block;
            padding: 20px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            border-radius: 15px;
            font-size: 1.1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }

        .meal-btn:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .meal-btn:hover:before { left: 100%; }
        .meal-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        /* Date Selection Styles */
        .date-selection {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .date-selection h3 {
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .date-selection input[type="date"] {
            padding: 12px 20px;
            border: 2px solid #667eea;
            border-radius: 10px;
            font-size: 1rem;
            background: white;
            color: #333;
            cursor: pointer;
            transition: 0.3s;
        }

        .date-selection input[type="date"]:focus {
            outline: none;
            border-color: #764ba2;
            box-shadow: 0 0 10px rgba(102, 126, 234, 0.3);
        }

        /* Booking Status Styles */
        .meal-btn.available {
            background: linear-gradient(45deg, #667eea, #764ba2);
        }

        .meal-btn.booked {
            background: linear-gradient(45deg, #dc3545, #c82333);
            cursor: not-allowed;
            opacity: 0.8;
        }

        .meal-btn.booked:hover {
            transform: none;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .available-label {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            font-size: 0.9rem;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .booked-label {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            font-size: 0.9rem;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        /* Time Selection Modal */
        .time-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .time-modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .time-modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }

        .time-modal-close:hover {
            color: #000;
        }

        .time-modal-header {
            margin-bottom: 25px;
            text-align: center;
        }

        .time-modal-header h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .time-modal-header p {
            color: #666;
            font-size: 0.9rem;
        }

        .time-selection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 25px;
        }

        .time-slot-btn {
            padding: 12px 8px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: 0.3s;
        }

        .time-slot-btn:hover {
            background: linear-gradient(45deg, #764ba2, #667eea);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .time-modal-actions {
            text-align: center;
        }

        .cancel-btn {
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: 0.3s;
        }

        .cancel-btn:hover {
            background: #5a6268;
        }

        .meal-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
        }

        .meal-name {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .meal-time {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .back-btn {
            position: fixed;
            bottom: 30px;
            left: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            font-size: 1.5rem;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .back-btn:hover {
            transform: scale(1.1) translateY(-5px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.5);
        }

        @media (max-width: 768px) {
            .container { padding: 30px 20px; }
            .page-title { font-size: 2rem; }
            nav { flex-direction: column; gap: 10px; }
            header { flex-direction: column; text-align: center; }
            .back-btn { bottom: 20px; left: 20px; width: 50px; height: 50px; font-size: 1.2rem; }
            .table-details { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>

<div class="particles">
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
</div>

<header>
    <h2>
        <i class="fas fa-clock"></i>
        Welcome, <?php echo htmlspecialchars($user_name); ?>
    </h2>
    <nav>
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="View_Tables.php"><i class="fas fa-chair"></i> Tables</a>
        <a href="view_menu.php"><i class="fas fa-utensils"></i> Food</a>
        <a href="view_drink_menu.php"><i class="fas fa-cocktail"></i> Drinks</a>
        <a href="write_comment.php"><i class="fas fa-comment"></i> Comments</a>
        <a class="logout-btn" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</header>

<div class="main-content">
    <div class="container">
        <h1 class="page-title">
            <i class="fas fa-utensils"></i>
            Select Meal Time
        </h1>
        <p class="page-subtitle">Choose your preferred dining time for your table reservation</p>

        <div class="table-info">
            <div class="table-name">
                <i class="fas fa-chair"></i>
                <?php echo htmlspecialchars($table['table_label']); ?>
            </div>
            <div class="table-details">
                <div><i class="fas fa-users"></i> <?php echo $table['capacity']; ?> Seats</div>
                <div><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($table['location']); ?></div>
            </div>
        </div>

        <div class="date-selection">
            <h3><i class="fas fa-calendar-alt"></i> Select Date</h3>
            <input type="date" id="selectedDate" min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" onchange="updateAvailableSlots()">
        </div>

        <div class="meal-options" id="mealOptions">
            <!-- Meal options will be loaded here based on selected date -->
        </div>

        <!-- Time Selection Modal -->
        <div id="timeSelectionModal" class="time-modal">
            <div class="time-modal-content">
                <span class="time-modal-close" onclick="closeTimeModal()">&times;</span>
                <div class="time-modal-header">
                    <h3 id="modalMealName">Select Time</h3>
                    <p id="modalMealPeriod">Choose your preferred time within the meal period</p>
                </div>
                <div class="time-selection-grid" id="timeSelectionGrid">
                    <!-- Time slots will be generated here -->
                </div>
                <div class="time-modal-actions">
                    <button class="cancel-btn" onclick="closeTimeModal()">Cancel</button>
                </div>
            </div>
        </div>

        <script>
        // Booked slots data from PHP
        const bookedSlots = <?php echo json_encode($booked_slots); ?>;
        
        function updateAvailableSlots() {
            const selectedDate = document.getElementById('selectedDate').value;
            const mealOptions = document.getElementById('mealOptions');
            
            const meals = [
                {name: 'Breakfast', icon: '🍳', time: '7:00 AM - 11:00 AM', value: 'Breakfast'},
                {name: 'Lunch', icon: '🍛', time: '11:00 AM - 5:00 PM', value: 'Lunch'},
                {name: 'Dinner', icon: '🍽️', time: '5:00 PM - 12:00 AM', value: 'Dinner'}
            ];
            
            let optionsHTML = '';
            
            meals.forEach(meal => {
                const isBooked = bookedSlots[selectedDate] && bookedSlots[selectedDate].includes(meal.value.toLowerCase());
                
                if (isBooked) {
                    optionsHTML += `
                        <div class="meal-btn booked">
                            <span class="meal-icon">${meal.icon}</span>
                            <div class="meal-name">${meal.name}</div>
                            <div class="meal-time">${meal.time}</div>
                            <div class="booked-label"><i class="fas fa-lock"></i> Already Booked</div>
                        </div>
                    `;
                } else {
                    optionsHTML += `
                        <div class="meal-btn available" onclick="showTimeSelection('${meal.value}', '${selectedDate}', '${meal.name}', '${meal.time}')">
                            <span class="meal-icon">${meal.icon}</span>
                            <div class="meal-name">${meal.name}</div>
                            <div class="meal-time">${meal.time}</div>
                            <div class="available-label"><i class="fas fa-check-circle"></i> Available - Click to select time</div>
                        </div>
                    `;
                }
            });
            
            mealOptions.innerHTML = optionsHTML;
        }
        
        // Initialize with today's date
        document.addEventListener('DOMContentLoaded', function() {
            updateAvailableSlots();
        });

        // Time selection functions
        function showTimeSelection(mealType, selectedDate, mealName, mealPeriod) {
            document.getElementById('modalMealName').textContent = mealName + ' Time Selection';
            document.getElementById('modalMealPeriod').textContent = 'Choose your preferred time (' + mealPeriod + ')';
            
            const timeGrid = document.getElementById('timeSelectionGrid');
            let timeSlots = [];
            
            // Generate time slots based on meal type
            switch(mealType) {
                case 'Breakfast':
                    timeSlots = generateTimeSlots('07:00', '11:00', 30); // 7:00 AM to 11:00 AM, 30-minute intervals
                    break;
                case 'Lunch':
                    timeSlots = generateTimeSlots('11:00', '17:00', 30); // 11:00 AM to 5:00 PM, 30-minute intervals
                    break;
                case 'Dinner':
                    timeSlots = generateTimeSlots('17:00', '24:00', 30); // 5:00 PM to 12:00 AM, 30-minute intervals
                    break;
            }
            
            let slotsHTML = '';
            timeSlots.forEach(time => {
                slotsHTML += `
                    <button class="time-slot-btn" onclick="selectTime('${mealType}', '${selectedDate}', '${time}')">
                        ${formatTime(time)}
                    </button>
                `;
            });
            
            timeGrid.innerHTML = slotsHTML;
            document.getElementById('timeSelectionModal').style.display = 'block';
        }

        function generateTimeSlots(startTime, endTime, intervalMinutes) {
            const slots = [];
            const start = timeToMinutes(startTime);
            const end = timeToMinutes(endTime);
            
            for (let minutes = start; minutes < end; minutes += intervalMinutes) {
                slots.push(minutesToTime(minutes));
            }
            
            return slots;
        }

        function timeToMinutes(timeStr) {
            const [hours, minutes] = timeStr.split(':').map(Number);
            return hours * 60 + minutes;
        }

        function minutesToTime(minutes) {
            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;
            return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
        }

        function formatTime(time24) {
            const [hours, minutes] = time24.split(':').map(Number);
            const period = hours >= 12 ? 'PM' : 'AM';
            const displayHours = hours > 12 ? hours - 12 : (hours === 0 ? 12 : hours);
            return `${displayHours}:${minutes.toString().padStart(2, '0')} ${period}`;
        }

        function selectTime(mealType, selectedDate, selectedTime) {
            // Redirect to booking page with selected time
            window.location.href = `book_table.php?table_id=<?php echo $table_id; ?>&time=${mealType}&date=${selectedDate}&specific_time=${selectedTime}`;
        }

        function closeTimeModal() {
            document.getElementById('timeSelectionModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('timeSelectionModal');
            if (event.target == modal) {
                closeTimeModal();
            }
        }
        </script>
    </div>
</div>

<a href="View_Tables.php" class="back-btn" title="Back to Tables">
    <i class="fas fa-arrow-left"></i>
</a>

<script>
// Add entrance animations
document.addEventListener('DOMContentLoaded', function() {
    const mealBtns = document.querySelectorAll('.meal-btn');
    mealBtns.forEach((btn, index) => {
        btn.style.opacity = '0';
        btn.style.transform = 'translateY(30px)';
        btn.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        
        setTimeout(() => {
            btn.style.opacity = '1';
            btn.style.transform = 'translateY(0)';
        }, (index + 1) * 200);
    });
});
</script>

</body>
</html>
