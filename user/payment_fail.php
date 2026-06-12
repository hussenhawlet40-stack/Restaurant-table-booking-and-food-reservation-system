<?php
session_start();
require_once "../connection.php";
require_once "../includes/payment_functions.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_name = $_SESSION['name'] ?? 'Customer';
$reason = $_GET['reason'] ?? 'unknown';

$reasons = [
    'expired' => [
        'title' => 'Payment Session Expired',
        'message' => 'Your payment session has expired. Please try again.',
        'icon' => 'fas fa-hourglass-end',
        'color' => '#ff9800'
    ],
    'failed' => [
        'title' => 'Payment Failed',
        'message' => 'Your payment could not be processed. Please try again or use a different payment method.',
        'icon' => 'fas fa-exclamation-triangle',
        'color' => '#dc3545'
    ],
    'declined' => [
        'title' => 'Payment Declined',
        'message' => 'Your payment was declined. Please check your payment details and try again.',
        'icon' => 'fas fa-ban',
        'color' => '#dc3545'
    ],
    'unknown' => [
        'title' => 'Payment Error',
        'message' => 'An error occurred while processing your payment. Please try again.',
        'icon' => 'fas fa-question-circle',
        'color' => '#6c757d'
    ]
];

$error_info = $reasons[$reason] ?? $reasons['unknown'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment Failed - Ethiopian Restaurant</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #dc3545, #c82333); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        
        .container { max-width: 500px; width: 90%; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); text-align: center; }
        
        .error-icon { font-size: 5rem; color: <?= $error_info['color'] ?>; margin-bottom: 20px; }
        .title { color: #333; margin-bottom: 10px; }
        .message { color: #666; margin-bottom: 30px; line-height: 1.6; }
        
        .suggestions { background: #f8f9fa; padding: 20px; border-radius: 15px; margin-bottom: 30px; text-align: left; }
        .suggestions h4 { color: #333; margin-bottom: 15px; text-align: center; }
        .suggestions ul { list-style: none; padding: 0; }
        .suggestions li { margin-bottom: 10px; padding-left: 25px; position: relative; }
        .suggestions li:before { content: '•'; position: absolute; left: 0; color: #667eea; font-weight: bold; }
        
        .action-buttons { display: flex; gap: 15px; flex-wrap: wrap; }
        .btn { flex: 1; min-width: 150px; padding: 15px 20px; border: none; border-radius: 10px; font-size: 16px; cursor: pointer; transition: 0.3s; text-decoration: none; display: inline-block; text-align: center; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5a6fd8; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        
        @media (max-width: 768px) {
            .container { padding: 30px 20px; }
            .action-buttons { flex-direction: column; }
            .btn { min-width: auto; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="error-icon">
        <i class="<?= $error_info['icon'] ?>"></i>
    </div>
    
    <h1 class="title"><?= $error_info['title'] ?></h1>
    <p class="message"><?= $error_info['message'] ?></p>
    
    <div class="suggestions">
        <h4><i class="fas fa-lightbulb"></i> What you can do:</h4>
        <ul>
            <li>Try a different payment method</li>
            <li>Check your internet connection</li>
            <li>Verify your payment details</li>
            <li>Contact your bank if the problem persists</li>
            <li>Choose "Cash on Arrival" as an alternative</li>
        </ul>
    </div>
    
    <div class="action-buttons">
        <a href="javascript:history.back()" class="btn btn-primary">
            <i class="fas fa-redo"></i> Try Again
        </a>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-home"></i> Back to Dashboard
        </a>
    </div>
</div>

</body>
</html>