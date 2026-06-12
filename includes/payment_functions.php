<?php
// Payment Functions for Ethiopian Restaurant System

require_once __DIR__ . '/../config/payment_config.php';

// Create payments table if it doesn't exist
function createPaymentsTable($conn) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            payment_reference VARCHAR(100) UNIQUE NOT NULL,
            user_id INT NOT NULL,
            order_id INT NULL,
            booking_id INT NULL,
            payment_type ENUM('order', 'booking', 'deposit') NOT NULL,
            payment_method ENUM('telebirr', 'cbe', 'cash') NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'ETB',
            status ENUM('pending', 'completed', 'failed', 'cancelled', 'expired') DEFAULT 'pending',
            transaction_id VARCHAR(255) NULL,
            payment_data JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        $conn->exec($sql);
        return true;
    } catch (Exception $e) {
        error_log("Error creating payments table: " . $e->getMessage());
        return false;
    }
}

// Generate unique payment reference
function generatePaymentReference() {
    return 'PAY_' . date('Ymd') . '_' . strtoupper(substr(uniqid(), -8));
}

// Create payment record
function createPayment($conn, $user_id, $amount, $payment_type, $order_id = null, $booking_id = null) {
    try {
        $payment_reference = generatePaymentReference();
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . PAYMENT_TIMEOUT_MINUTES . ' minutes'));
        
        $stmt = $conn->prepare("
            INSERT INTO payments (payment_reference, user_id, order_id, booking_id, payment_type, amount, expires_at, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->execute([$payment_reference, $user_id, $order_id, $booking_id, $payment_type, $amount, $expires_at]);
        
        return [
            'payment_id' => $conn->lastInsertId(),
            'payment_reference' => $payment_reference,
            'amount' => $amount,
            'expires_at' => $expires_at
        ];
    } catch (Exception $e) {
        error_log("Error creating payment: " . $e->getMessage());
        return false;
    }
}

// Get payment by reference
function getPaymentByReference($conn, $payment_reference) {
    try {
        $stmt = $conn->prepare("SELECT * FROM payments WHERE payment_reference = ?");
        $stmt->execute([$payment_reference]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting payment: " . $e->getMessage());
        return false;
    }
}

// Update payment status
function updatePaymentStatus($conn, $payment_reference, $status, $transaction_id = null, $payment_data = null) {
    try {
        $sql = "UPDATE payments SET status = ?, updated_at = CURRENT_TIMESTAMP";
        $params = [$status];
        
        if ($transaction_id) {
            $sql .= ", transaction_id = ?";
            $params[] = $transaction_id;
        }
        
        if ($payment_data) {
            $sql .= ", payment_data = ?";
            $params[] = json_encode($payment_data);
        }
        
        $sql .= " WHERE payment_reference = ?";
        $params[] = $payment_reference;
        
        $stmt = $conn->prepare($sql);
        return $stmt->execute($params);
    } catch (Exception $e) {
        error_log("Error updating payment status: " . $e->getMessage());
        return false;
    }
}

// Simulate TeleBirr payment (for demo purposes)
function initiateTeleBirrPayment($payment_reference, $amount, $phone_number, $description) {
    // In production, this would integrate with actual TeleBirr API
    // For demo, we'll simulate the payment process
    
    $payment_data = [
        'merchant_id' => TELEBIRR_MERCHANT_ID,
        'amount' => $amount,
        'currency' => PAYMENT_CURRENCY,
        'phone_number' => $phone_number,
        'description' => $description,
        'reference' => $payment_reference,
        'callback_url' => TELEBIRR_WEBHOOK_URL,
        'return_url' => PAYMENT_SUCCESS_URL,
        'cancel_url' => PAYMENT_CANCEL_URL
    ];
    
    // Simulate API response
    $response = [
        'success' => true,
        'payment_url' => 'payment_process.php?ref=' . $payment_reference . '&method=telebirr',
        'transaction_id' => 'TB_' . time() . '_' . rand(1000, 9999),
        'message' => 'Payment initiated successfully'
    ];
    
    return $response;
}

// Simulate CBE payment (for demo purposes)
function initiateCBEPayment($payment_reference, $amount, $account_number, $description) {
    // In production, this would integrate with actual CBE API
    // For demo, we'll simulate the payment process
    
    $payment_data = [
        'merchant_id' => CBE_MERCHANT_ID,
        'amount' => $amount,
        'currency' => PAYMENT_CURRENCY,
        'account_number' => $account_number,
        'description' => $description,
        'reference' => $payment_reference,
        'callback_url' => CBE_WEBHOOK_URL,
        'return_url' => PAYMENT_SUCCESS_URL,
        'cancel_url' => PAYMENT_CANCEL_URL
    ];
    
    // Simulate API response
    $response = [
        'success' => true,
        'payment_url' => 'payment_process.php?ref=' . $payment_reference . '&method=cbe',
        'transaction_id' => 'CBE_' . time() . '_' . rand(1000, 9999),
        'message' => 'Payment initiated successfully'
    ];
    
    return $response;
}

// Process cash payment
function processCashPayment($payment_reference) {
    // For cash payments, we just mark as pending until arrival
    return [
        'success' => true,
        'message' => 'Cash payment selected. Please pay upon arrival.',
        'transaction_id' => 'CASH_' . time()
    ];
}

// Check if payment is expired
function isPaymentExpired($payment) {
    if (!$payment || !$payment['expires_at']) {
        return false;
    }
    
    return strtotime($payment['expires_at']) < time();
}

// Cancel expired payments and related orders/bookings
function cancelExpiredPayments($conn) {
    try {
        $cancelled_count = 0;
        
        // 1. Cancel expired payments (but keep bookings reserved until 3-hour order timeout)
        $stmt = $conn->prepare("
            SELECT * FROM payments 
            WHERE status = 'pending' 
            AND expires_at < NOW()
        ");
        $stmt->execute();
        $expired_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($expired_payments as $payment) {
            // Update payment status to expired
            updatePaymentStatus($conn, $payment['payment_reference'], 'expired');
            
            // For orders: Only cancel the order, keep booking reserved until 3-hour timeout
            if ($payment['payment_type'] === 'order' && $payment['order_id']) {
                $conn->prepare("UPDATE pre_orders SET status = 'cancelled' WHERE id = ?")->execute([$payment['order_id']]);
                
                // DO NOT cancel the booking yet - let it expire after 3 hours from order creation
                // The booking will be cancelled by the 3-hour timeout logic below
                
            } elseif ($payment['payment_type'] === 'booking' && $payment['booking_id']) {
                // For direct booking payments, cancel immediately
                $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$payment['booking_id']]);
            }
            $cancelled_count++;
        }
        
        // 2. Cancel orders AND bookings that are older than 3 hours from ORDER CREATION (not payment creation)
        // Use precise timing to ensure orders are not cancelled prematurely
        // IMPORTANT: Only cancel AFTER 3 hours have COMPLETELY passed (not during the 3rd hour)
        $stmt = $conn->prepare("
            SELECT po.*, b.id as booking_id, po.created_at as order_created_at,
                   TIMESTAMPDIFF(SECOND, po.created_at, NOW()) as seconds_elapsed
            FROM pre_orders po
            JOIN bookings b ON po.booking_id = b.id
            LEFT JOIN payments p ON po.id = p.order_id AND p.payment_type = 'order' AND p.status = 'completed'
            WHERE po.status IN ('pending', 'pending_payment', 'cancelled')
            AND po.total_amount > 0
            AND TIMESTAMPDIFF(SECOND, po.created_at, NOW()) >= 10800
            AND p.id IS NULL
            AND b.status != 'cancelled'
        ");
        $stmt->execute();
        $expired_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($expired_orders as $order) {
            // Triple-check expiration: Must be EXACTLY 3 hours or more (no buffer for safety)
            $seconds_elapsed = $order['seconds_elapsed'];
            if ($seconds_elapsed < 10800) { // Less than exactly 3 hours (10800 seconds)
                error_log("SAFETY CHECK: Skipping order #{$order['id']} - only {$seconds_elapsed} seconds elapsed (need 10800+)");
                continue;
            }
            
            // Cancel the order (if not already cancelled)
            if ($order['status'] != 'cancelled') {
                $conn->prepare("UPDATE pre_orders SET status = 'cancelled' WHERE id = ?")->execute([$order['id']]);
            }
            
            // NOW cancel the associated booking after 3 hours from order creation
            $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$order['booking_id']]);
            
            $cancelled_count++;
            
            // Log the cancellation with precise timing
            $hours_elapsed = round($order['seconds_elapsed'] / 3600, 2);
            error_log("Auto-cancelled expired order #{$order['id']} and booking #{$order['booking_id']} after {$hours_elapsed} hours ({$order['seconds_elapsed']} seconds) from order creation");
        }
        
        // 3. Cancel bookings that are older than 3 hours without any orders
        $stmt = $conn->prepare("
            SELECT b.* 
            FROM bookings b
            LEFT JOIN pre_orders po ON b.id = po.booking_id
            LEFT JOIN payments p ON b.id = p.booking_id AND p.payment_type = 'booking'
            WHERE b.status = 'pending' 
            AND b.created_at < DATE_SUB(NOW(), INTERVAL 3 HOUR)
            AND po.id IS NULL
            AND (p.id IS NULL OR p.status IN ('failed', 'cancelled', 'expired'))
        ");
        $stmt->execute();
        $expired_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($expired_bookings as $booking) {
            // Cancel the booking
            $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$booking['id']]);
            
            $cancelled_count++;
            
            // Log the cancellation
            error_log("Auto-cancelled expired booking #{$booking['id']} after 3 hours (no orders)");
        }
        
        return $cancelled_count;
    } catch (Exception $e) {
        error_log("Error cancelling expired payments/orders/bookings: " . $e->getMessage());
        return 0;
    }
}

// Get user payments
function getUserPayments($conn, $user_id, $limit = 10) {
    try {
        $stmt = $conn->prepare("
            SELECT p.*, 
                   CASE 
                       WHEN p.order_id IS NOT NULL THEN CONCAT('Order #', p.order_id)
                       WHEN p.booking_id IS NOT NULL THEN CONCAT('Booking #', p.booking_id)
                       ELSE 'Unknown'
                   END as payment_for
            FROM payments p 
            WHERE p.user_id = ? 
            ORDER BY p.created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting user payments: " . $e->getMessage());
        return [];
    }
}

// Format payment status for display
function formatPaymentStatus($status) {
    $statuses = [
        'pending' => ['text' => 'Pending', 'class' => 'status-pending', 'icon' => 'fas fa-clock'],
        'completed' => ['text' => 'PAID', 'class' => 'status-paid', 'icon' => 'fas fa-check-circle'],
        'failed' => ['text' => 'Failed', 'class' => 'status-failed', 'icon' => 'fas fa-times-circle'],
        'cancelled' => ['text' => 'Cancelled', 'class' => 'status-cancelled', 'icon' => 'fas fa-ban'],
        'expired' => ['text' => 'Expired', 'class' => 'status-expired', 'icon' => 'fas fa-hourglass-end']
    ];
    
    return $statuses[$status] ?? ['text' => 'Unknown', 'class' => 'status-unknown', 'icon' => 'fas fa-question'];
}

// Format payment method for display
function formatPaymentMethod($method) {
    $methods = [
        'telebirr' => ['text' => 'TeleBirr', 'class' => 'method-telebirr', 'icon' => 'fas fa-mobile-alt'],
        'cbe' => ['text' => 'CBE Bank', 'class' => 'method-cbe', 'icon' => 'fas fa-university'],
        'cash' => ['text' => 'Cash on Arrival', 'class' => 'method-cash', 'icon' => 'fas fa-money-bill-wave']
    ];
    
    return $methods[$method] ?? ['text' => 'Unknown', 'class' => 'method-unknown', 'icon' => 'fas fa-question'];
}

// Calculate remaining payment time
function getRemainingPaymentTime($expires_at) {
    if (!$expires_at) {
        return null;
    }
    
    $expires_timestamp = strtotime($expires_at);
    $current_timestamp = time();
    $time_left = $expires_timestamp - $current_timestamp;
    
    if ($time_left <= 0) {
        return ['expired' => true, 'text' => 'EXPIRED', 'class' => 'expired'];
    }
    
    $hours = floor($time_left / 3600);
    $minutes = floor(($time_left % 3600) / 60);
    $seconds = $time_left % 60;
    
    $is_critical = $time_left < 3600; // Less than 1 hour
    $is_urgent = $time_left < 300; // Less than 5 minutes
    
    $text = '';
    if ($hours > 0) {
        $text = $hours . 'h ' . $minutes . 'm';
    } elseif ($minutes > 0) {
        $text = $minutes . 'm ' . $seconds . 's';
    } else {
        $text = $seconds . 's';
    }
    
    $class = $is_urgent ? 'urgent' : ($is_critical ? 'critical' : 'normal');
    
    return [
        'expired' => false,
        'text' => $text,
        'class' => $class,
        'hours' => $hours,
        'minutes' => $minutes,
        'seconds' => $seconds,
        'total_seconds' => $time_left,
        'is_critical' => $is_critical,
        'is_urgent' => $is_urgent
    ];
}

// Format remaining time for display
function formatRemainingTime($expires_at, $show_icon = true) {
    $time_info = getRemainingPaymentTime($expires_at);
    
    if (!$time_info) {
        return '';
    }
    
    $icon = $show_icon ? '<i class="fas fa-hourglass-half"></i> ' : '';
    
    if ($time_info['expired']) {
        return '<span class="time-expired">' . $icon . 'EXPIRED</span>';
    }
    
    $class = 'time-' . $time_info['class'];
    $prefix = '';
    
    if ($time_info['is_urgent']) {
        $prefix = '<strong>URGENT:</strong> ';
    } elseif ($time_info['is_critical']) {
        $prefix = '<strong>CRITICAL:</strong> ';
    }
    
    return '<span class="' . $class . '">' . $icon . $prefix . $time_info['text'] . ' remaining</span>';
}

// Check if order is expired (EXACTLY 3 hours from creation) - NO BUFFER for display purposes
function isOrderExpired($order_created_at) {
    $order_created = strtotime($order_created_at);
    $expiry_time = $order_created + (3 * 3600); // Exactly 3 hours in seconds (10800)
    $current_time = time();
    
    return ($current_time >= $expiry_time); // Expired when current time reaches or exceeds expiry time
}

// Check if order is expired for cleanup (NO BUFFER - same as display)
function isOrderExpiredForCleanup($order_created_at) {
    $order_created = strtotime($order_created_at);
    $expiry_time = $order_created + (3 * 3600); // Exactly 3 hours in seconds (10800)
    $current_time = time();
    
    // NO BUFFER - cancel exactly when 3 hours have passed
    return ($current_time >= $expiry_time);
}

// Get exact remaining time for an order (3 hours from creation)
function getExactRemainingOrderTime($order_created_at) {
    $order_created = strtotime($order_created_at);
    $expiry_time = $order_created + (3 * 3600); // 3 hours in seconds
    $current_time = time();
    $time_left = $expiry_time - $current_time;
    
    // Show as expired exactly at 3 hours (no buffer for display)
    if ($time_left <= 0) {
        return [
            'expired' => true,
            'hours' => 0,
            'minutes' => 0,
            'seconds' => 0,
            'total_seconds' => 0,
            'is_critical' => false,
            'is_urgent' => false,
            'expiry_timestamp' => $expiry_time,
            'formatted_time' => '00:00:00'
        ];
    } else {
        $hours = floor($time_left / 3600);
        $minutes = floor(($time_left % 3600) / 60);
        $seconds = $time_left % 60;
        
        $is_critical = $time_left < 1800; // Less than 30 minutes
        $is_urgent = $time_left < 300; // Less than 5 minutes
        
        return [
            'expired' => false,
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $seconds,
            'total_seconds' => $time_left,
            'is_critical' => $is_critical,
            'is_urgent' => $is_urgent,
            'expiry_timestamp' => $expiry_time,
            'formatted_time' => sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds)
        ];
    }
}

// Get remaining order time (3 hours from creation)
function getRemainingOrderTime($order_created_at) {
    $order_created = strtotime($order_created_at);
    $expiry_time = $order_created + (3 * 3600); // 3 hours in seconds
    $time_left = $expiry_time - time();
    
    if ($time_left <= 0) {
        return [
            'expired' => true, 
            'text' => 'EXPIRED', 
            'class' => 'expired',
            'expiry_timestamp' => $expiry_time,
            'total_seconds' => 0
        ];
    }
    
    $hours = floor($time_left / 3600);
    $minutes = floor(($time_left % 3600) / 60);
    $seconds = $time_left % 60;
    
    $is_critical = $time_left < 1800; // Less than 30 minutes
    $is_urgent = $time_left < 300; // Less than 5 minutes
    
    $text = '';
    if ($hours > 0) {
        $text = $hours . 'h ' . $minutes . 'm';
    } elseif ($minutes > 0) {
        $text = $minutes . 'm ' . $seconds . 's';
    } else {
        $text = $seconds . 's';
    }
    
    $class = $is_urgent ? 'urgent' : ($is_critical ? 'critical' : 'normal');
    
    return [
        'expired' => false,
        'text' => $text,
        'class' => $class,
        'hours' => $hours,
        'minutes' => $minutes,
        'seconds' => $seconds,
        'total_seconds' => $time_left,
        'is_critical' => $is_critical,
        'is_urgent' => $is_urgent,
        'expiry_timestamp' => $expiry_time
    ];
}

// Check if booking is expired (3 hours from creation) - for bookings without orders
function isBookingExpired($booking_created_at) {
    $booking_created = strtotime($booking_created_at);
    $expiry_time = $booking_created + (3 * 3600); // 3 hours in seconds
    return time() > $expiry_time;
}

// Get remaining booking time (3 hours from creation)
function getRemainingBookingTime($booking_created_at) {
    $booking_created = strtotime($booking_created_at);
    $expiry_time = $booking_created + (3 * 3600); // 3 hours in seconds
    $time_left = $expiry_time - time();
    
    if ($time_left <= 0) {
        return [
            'expired' => true, 
            'text' => 'EXPIRED', 
            'class' => 'expired',
            'expiry_timestamp' => $expiry_time,
            'total_seconds' => 0
        ];
    }
    
    $hours = floor($time_left / 3600);
    $minutes = floor(($time_left % 3600) / 60);
    $seconds = $time_left % 60;
    
    $is_critical = $time_left < 1800; // Less than 30 minutes
    $is_urgent = $time_left < 300; // Less than 5 minutes
    
    $text = '';
    if ($hours > 0) {
        $text = $hours . 'h ' . $minutes . 'm';
    } elseif ($minutes > 0) {
        $text = $minutes . 'm ' . $seconds . 's';
    } else {
        $text = $seconds . 's';
    }
    
    $class = $is_urgent ? 'urgent' : ($is_critical ? 'critical' : 'normal');
    
    return [
        'expired' => false,
        'text' => $text,
        'class' => $class,
        'hours' => $hours,
        'minutes' => $minutes,
        'seconds' => $seconds,
        'total_seconds' => $time_left,
        'is_critical' => $is_critical,
        'is_urgent' => $is_urgent,
        'expiry_timestamp' => $expiry_time
    ];
}

// Auto-cancel expired orders and bookings (to be called periodically)
function autoCleanupExpiredOrders($conn) {
    try {
        $cleanup_count = 0;
        
        // Cancel orders and bookings that are expired (EXACTLY 3+ hours from ORDER CREATION) and unpaid
        // This ensures tables remain reserved for the FULL 3 hours from when the order was placed
        // Use precise timing: 3 hours = 10800 seconds (NO BUFFER - cancel only after full 3 hours)
        $stmt = $conn->prepare("
            SELECT po.id as order_id, po.created_at, po.status as order_status, 
                   b.id as booking_id, b.status as booking_status, u.name as user_name,
                   TIMESTAMPDIFF(SECOND, po.created_at, NOW()) as seconds_elapsed
            FROM pre_orders po
            JOIN bookings b ON po.booking_id = b.id
            JOIN users u ON b.user_id = u.id
            LEFT JOIN payments p ON po.id = p.order_id AND p.payment_type = 'order' AND p.status = 'completed'
            WHERE po.status IN ('pending', 'pending_payment', 'cancelled')
            AND b.status IN ('pending', 'confirmed')
            AND po.total_amount > 0
            AND TIMESTAMPDIFF(SECOND, po.created_at, NOW()) >= 10800
            AND p.id IS NULL
        ");
        $stmt->execute();
        $expired_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($expired_items as $item) {
            // Strict check: Must be EXACTLY 3 hours or more (no premature cancellation)
            if ($item['seconds_elapsed'] < 10800) {
                error_log("AUTO-CLEANUP STRICT CHECK: Skipping order #{$item['order_id']} - only {$item['seconds_elapsed']} seconds elapsed (need exactly 10800+)");
                continue;
            }
            
            // Cancel the order (if not already cancelled)
            if ($item['order_status'] != 'cancelled') {
                $conn->prepare("UPDATE pre_orders SET status = 'cancelled' WHERE id = ?")->execute([$item['order_id']]);
            }
            
            // Cancel the booking (this frees up the table)
            if ($item['booking_status'] != 'cancelled') {
                $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$item['booking_id']]);
            }
            
            $cleanup_count++;
            
            // Log the auto-cancellation with detailed info
            $hours_elapsed = round($item['seconds_elapsed'] / 3600, 2);
            error_log("AUTO-CLEANUP: Cancelled expired order #{$item['order_id']} (status: {$item['order_status']}) and booking #{$item['booking_id']} for user {$item['user_name']} after {$hours_elapsed} hours ({$item['seconds_elapsed']} seconds from order creation)");
        }
        
        return $cleanup_count;
    } catch (Exception $e) {
        error_log("Error in auto-cleanup: " . $e->getMessage());
        return 0;
    }
}
?>