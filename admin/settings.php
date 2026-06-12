<?php
session_start();
require_once "../connection.php";

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = '';
$error = '';

// Configuration file path
$config_file = '../config/restaurant_config.json';

// Create config directory if it doesn't exist
if (!file_exists('../config')) {
    mkdir('../config', 0755, true);
}

// Default settings
$default_settings = [
    'restaurant_name' => 'Adabina Restaurant',
    'phone' => '+251 712 272 260',
    'email' => 'info@adabinarestaurant.com',
    'location' => 'Central Ethiopia, Gurage Emdibir - Directly in front of Danial Yegebiya Maekel',
    'opening_time' => '01:00',
    'closing_time' => '18:00',
    'description' => 'Experience authentic Ethiopian cuisine in a warm, welcoming atmosphere. From traditional injera to modern fusion dishes, we bring you the best of Ethiopian flavors.'
];

// Load current settings
$settings = $default_settings;
if (file_exists($config_file)) {
    $json_data = file_get_contents($config_file);
    $loaded_settings = json_decode($json_data, true);
    if ($loaded_settings) {
        $settings = array_merge($default_settings, $loaded_settings);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $restaurant_name = trim($_POST['restaurant_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $opening_time = trim($_POST['opening_time'] ?? '');
    $closing_time = trim($_POST['closing_time'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // Basic validation
    if (empty($restaurant_name) || empty($phone) || empty($email) || empty($location)) {
        $error = "Please fill in all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        // Update settings
        $settings = [
            'restaurant_name' => $restaurant_name,
            'phone' => $phone,
            'email' => $email,
            'location' => $location,
            'opening_time' => $opening_time,
            'closing_time' => $closing_time,
            'description' => $description,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Save to configuration file
        if (file_put_contents($config_file, json_encode($settings, JSON_PRETTY_PRINT))) {
            $message = "Settings updated successfully! Changes will appear on the about page.";
        } else {
            $error = "Failed to save settings. Please check file permissions.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .back-btn {
            position: absolute;
            top: 30px;
            left: 30px;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        .content {
            padding: 40px;
        }

        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }

        .success {
            background: rgba(76, 175, 80, 0.1);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .error {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #eee;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .time-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 20px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .settings-info {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            border-left: 4px solid #667eea;
        }

        .settings-info h3 {
            color: #667eea;
            margin-bottom: 10px;
        }

        .settings-info p {
            color: #666;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .time-inputs {
                grid-template-columns: 1fr;
            }
            
            .back-btn {
                position: static;
                display: inline-block;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <h1><i class="fas fa-cog"></i> Restaurant Settings</h1>
            <p>Manage your restaurant information and operating hours</p>
        </div>

        <div class="content">
            <div class="settings-info">
                <h3><i class="fas fa-info-circle"></i> Restaurant Configuration</h3>
                <p>Update your restaurant's basic information, contact details, and operating hours. These settings control how your restaurant information appears throughout the system.</p>
            </div>

            <?php if ($message): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="restaurant_name">
                            <i class="fas fa-utensils"></i> Restaurant Name *
                        </label>
                        <input type="text" id="restaurant_name" name="restaurant_name" 
                               value="<?= htmlspecialchars($settings['restaurant_name']) ?>" 
                               required placeholder="Enter restaurant name">
                    </div>

                    <div class="form-group">
                        <label for="phone">
                            <i class="fas fa-phone"></i> Phone Number *
                        </label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?= htmlspecialchars($settings['phone']) ?>" 
                               required placeholder="Enter phone number">
                    </div>

                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i> Email Address *
                        </label>
                        <input type="email" id="email" name="email" 
                               value="<?= htmlspecialchars($settings['email']) ?>" 
                               required placeholder="Enter email address">
                    </div>

                    <div class="form-group">
                        <label for="location">
                            <i class="fas fa-map-marker-alt"></i> Location *
                        </label>
                        <input type="text" id="location" name="location" 
                               value="<?= htmlspecialchars($settings['location']) ?>" 
                               required placeholder="Enter restaurant location">
                    </div>
                </div>

                <div class="form-group full-width">
                    <label><i class="fas fa-clock"></i> Operating Hours</label>
                    <div class="time-inputs">
                        <div>
                            <label for="opening_time">Opening Time</label>
                            <input type="time" id="opening_time" name="opening_time" 
                                   value="<?= htmlspecialchars($settings['opening_time']) ?>">
                        </div>
                        <div>
                            <label for="closing_time">Closing Time</label>
                            <input type="time" id="closing_time" name="closing_time" 
                                   value="<?= htmlspecialchars($settings['closing_time']) ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label for="description">
                        <i class="fas fa-align-left"></i> Restaurant Description
                    </label>
                    <textarea id="description" name="description" 
                              placeholder="Enter a brief description of your restaurant"
                              style="width: 100%; padding: 15px; border: 2px solid #eee; border-radius: 10px; font-size: 16px; background: #f8f9fa; min-height: 100px; resize: vertical;"><?= htmlspecialchars($settings['description']) ?></textarea>
                </div>

                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> Update Settings
                </button>
            </form>
        </div>
    </div>
</body>
</html>