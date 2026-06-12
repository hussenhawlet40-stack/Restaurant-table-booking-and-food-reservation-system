<?php
// PHPMailer Email Functions for Restaurant System

// Include PHPMailer files directly (without Composer)
require_once __DIR__ . '/../phpmailer/src/Exception.php';
require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/src/SMTP.php';
require_once __DIR__ . '/../config/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendOrderConfirmationEmail($user_email, $user_name, $order_id, $total_amount, $order_items, $booking_details) {
    // Check if order emails are enabled
    if (!defined('SEND_ORDER_EMAILS') || !SEND_ORDER_EMAILS) {
        return false;
    }
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;
        
        // Debug settings
        if (defined('EMAIL_DEBUG') && EMAIL_DEBUG) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        }
        
        // Recipients
        $mail->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
        $mail->addAddress($user_email, $user_name);
        $mail->addReplyTo(EMAIL_REPLY_TO, EMAIL_FROM_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Order Confirmation - Order #' . $order_id;
        $mail->Body    = createOrderEmailTemplate($user_name, $order_id, $total_amount, $order_items, $booking_details);
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Order email error: {$mail->ErrorInfo}");
        return false;
    }
}

function sendBookingConfirmationEmail($user_email, $user_name, $booking_id, $booking_details) {
    // Check if booking emails are enabled
    if (!defined('SEND_BOOKING_EMAILS') || !SEND_BOOKING_EMAILS) {
        return false;
    }
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;
        
        // Debug settings
        if (defined('EMAIL_DEBUG') && EMAIL_DEBUG) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        }
        
        // Recipients
        $mail->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
        $mail->addAddress($user_email, $user_name);
        $mail->addReplyTo(EMAIL_REPLY_TO, EMAIL_FROM_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Table Booking Confirmation - Booking #' . $booking_id;
        $mail->Body    = createBookingEmailTemplate($user_name, $booking_id, $booking_details);
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Booking email error: {$mail->ErrorInfo}");
        return false;
    }
}

function createOrderEmailTemplate($user_name, $order_id, $total_amount, $order_items, $booking_details) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Order Confirmation</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
            .container { max-width: 600px; margin: 0 auto; background: white; }
            .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; }
            .content { padding: 30px; }
            .order-info { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #667eea; }
            .order-items { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .item-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #ddd; }
            .total-row { display: flex; justify-content: space-between; padding: 15px 0; font-weight: bold; font-size: 18px; color: #667eea; border-top: 2px solid #667eea; margin-top: 10px; }
            .booking-info { background: #e8f4fd; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; background: #f8f9fa; color: #666; font-size: 14px; }
            .success-icon { font-size: 48px; color: #28a745; text-align: center; margin: 20px 0; }
            .contact-info { background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; }
            @media (max-width: 600px) {
                .item-row { flex-direction: column; }
                .total-row { flex-direction: column; text-align: center; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🍽️ Order Confirmation</h1>
                <p>Thank you for your order!</p>
            </div>
            
            <div class="content">
                <div class="success-icon">✅</div>
                
                <h2>Hello ' . htmlspecialchars($user_name) . ',</h2>
                <p>Your pre-order has been successfully placed and confirmed. Here are the details:</p>
                
                <div class="order-info">
                    <h3>📋 Order Information</h3>
                    <p><strong>Order ID:</strong> #' . $order_id . '</p>
                    <p><strong>Order Date:</strong> ' . date('M j, Y g:i A') . '</p>
                    <p><strong>Status:</strong> <span style="color: #28a745;">Confirmed</span></p>
                </div>
                
                <div class="booking-info">
                    <h3>🪑 Table Reservation Details</h3>
                    <p><strong>Table:</strong> ' . htmlspecialchars($booking_details['table_label']) . '</p>
                    <p><strong>Date & Time:</strong> ' . date('M j, Y g:i A', strtotime($booking_details['booking_datetime'])) . '</p>
                    <p><strong>Meal Type:</strong> ' . htmlspecialchars($booking_details['meal_type']) . '</p>
                    <p><strong>Guests:</strong> ' . $booking_details['guests'] . ' people</p>
                    ' . (!empty($booking_details['special_requests']) ? '<p><strong>Special Requests:</strong> ' . htmlspecialchars($booking_details['special_requests']) . '</p>' : '') . '
                </div>
                
                <div class="order-items">
                    <h3>🍽️ Your Order Items</h3>';
    
    foreach ($order_items as $item) {
        $html .= '
                    <div class="item-row">
                        <span>' . htmlspecialchars($item['name']) . ' x' . $item['quantity'] . '</span>
                        <span>ETB ' . number_format($item['price'] * $item['quantity'], 2) . '</span>
                    </div>';
    }
    
    $html .= '
                    <div class="total-row">
                        <span>Total Amount:</span>
                        <span>ETB ' . number_format($total_amount, 2) . '</span>
                    </div>
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <p><strong>What happens next?</strong></p>
                    <p>✅ Your order is confirmed and sent to our kitchen<br>
                    👨‍🍳 Our chefs will start preparing your food<br>
                    🔔 You\'ll receive updates on your order status<br>
                    🍽️ Your food will be ready when you arrive</p>
                </div>
                
                <div class="contact-info">
                    <p><strong>📞 Need to make changes?</strong></p>
                    <p>Contact us at: <strong>' . RESTAURANT_PHONE . '</strong><br>
                    Or visit our restaurant to speak with our staff.</p>
                </div>
            </div>
            
            <div class="footer">
                <p>' . EMAIL_FOOTER_TEXT . '</p>
                <p style="font-size: 12px; color: #999;">' . EMAIL_DISCLAIMER . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

function createBookingEmailTemplate($user_name, $booking_id, $booking_details) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Booking Confirmation</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
            .container { max-width: 600px; margin: 0 auto; background: white; }
            .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 30px; text-align: center; }
            .content { padding: 30px; }
            .booking-info { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #667eea; }
            .footer { text-align: center; padding: 20px; background: #f8f9fa; color: #666; font-size: 14px; }
            .success-icon { font-size: 48px; color: #28a745; text-align: center; margin: 20px 0; }
            .contact-info { background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🪑 Table Booking Confirmed</h1>
                <p>Your reservation is confirmed!</p>
            </div>
            
            <div class="content">
                <div class="success-icon">✅</div>
                
                <h2>Hello ' . htmlspecialchars($user_name) . ',</h2>
                <p>Your table reservation has been successfully confirmed. We look forward to serving you!</p>
                
                <div class="booking-info">
                    <h3>📋 Booking Details</h3>
                    <p><strong>Booking ID:</strong> #' . $booking_id . '</p>
                    <p><strong>Table:</strong> ' . htmlspecialchars($booking_details['table_label']) . '</p>
                    <p><strong>Date & Time:</strong> ' . date('M j, Y g:i A', strtotime($booking_details['booking_datetime'])) . '</p>
                    <p><strong>Meal Type:</strong> ' . htmlspecialchars($booking_details['meal_type']) . '</p>
                    <p><strong>Guests:</strong> ' . $booking_details['guests'] . ' people</p>
                    ' . (!empty($booking_details['special_requests']) ? '<p><strong>Special Requests:</strong> ' . htmlspecialchars($booking_details['special_requests']) . '</p>' : '') . '
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <p><strong>What to expect:</strong></p>
                    <p>🕐 Please arrive on time for your reservation<br>
                    🍽️ Your table will be ready when you arrive<br>
                    📱 You can pre-order food to save time<br>
                    🎉 Enjoy authentic Ethiopian cuisine!</p>
                </div>
                
                <div class="contact-info">
                    <p><strong>📞 Need to make changes?</strong></p>
                    <p>Contact us at: <strong>' . RESTAURANT_PHONE . '</strong><br>
                    Visit us at: ' . RESTAURANT_ADDRESS . '</p>
                </div>
            </div>
            
            <div class="footer">
                <p>' . EMAIL_FOOTER_TEXT . '</p>
                <p style="font-size: 12px; color: #999;">' . EMAIL_DISCLAIMER . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

function testEmailConfiguration($test_email) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
        $mail->addAddress($test_email);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Email Configuration Test - ' . RESTAURANT_NAME;
        $mail->Body    = '
        <h2>Email Test Successful!</h2>
        <p>This is a test email from your restaurant booking system.</p>
        <p><strong>Restaurant:</strong> ' . RESTAURANT_NAME . '</p>
        <p><strong>Time:</strong> ' . date('M j, Y g:i A') . '</p>
        <p>If you received this email, your PHPMailer configuration is working correctly!</p>
        ';
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Test email error: {$mail->ErrorInfo}");
        return false;
    }
}
?>