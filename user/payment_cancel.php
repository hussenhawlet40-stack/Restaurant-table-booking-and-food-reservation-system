<?php
session_start();
require_once "../connection.php";
require_once "../includes/payment_functions.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_name = $_SESSION['name'] ?? 'Customer';
$payment_reference = $_GET['ref'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment Cancelled - Ethiopian Restaurant</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #ffc107, #ff8f00); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        
        .container { max-width: 500px; width: 90%; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); text-align: center; }
        
        .cancel-icon { font-size: 5rem; color: #ff9800; margin-bottom: 20px; }
        .title { color: #333; margin-bottom: 10px; }
        .message { color: #666; margin-bottom: 30px; line-height: 1.6; }
        
        .info-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 15px; margin-bottom: 30px; }
        .info-box p { color: #856404; margin-bottom: 10px; }
        .info-box p:last-child { margin-bottom: 0; }
        
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
    <div class="cancel-icon">
        <i class="fas fa-times-circle"></i>
    </div>
    
    <h1 class="title">Payment Cancelled</h1>
    <p class="message">You have cancelled the payment process. No charges have been made to your account.</p>
    
    <div class="info-box">
        <p><strong>What happened?</strong></p>
        <p>Your payment was cancelled and your order/booking is still pending.</p>
        <p>You can try again with the same or different payment method, or choose "Cash on Arrival".</p>
    </div>
    
    <div class="action-buttons">
        <a href="javascript:history.back()" class="btn btn-primary">
            <i class="fas fa-redo"></i> Try Payment Again
        </a>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-home"></i> Back to Dashboard
        </a>
    </div>
</div>

</body>
</html>