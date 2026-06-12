<?php
/**
 * Cron Job Script: Cleanup Expired Orders and Payments
 * 
 * This script should be run every 5-10 minutes via cron job to automatically
 * cancel expired orders and free up table reservations.
 * 
 * Cron job example (run every 5 minutes):
 * */5 * * * * /usr/bin/php /path/to/Restaurant_booking/cron/cleanup_expired_orders.php
 * 
 * Or via web cron (run every 5 minutes):
 * */5 * * * * curl -s https://yourdomain.com/Restaurant_booking/cron/cleanup_expired_orders.php
 */

// Prevent direct web access for security (optional)
if (isset($_SERVER['HTTP_HOST']) && !isset($_GET['allow_web'])) {
    http_response_code(403);
    die("Access denied. This script should be run via cron job.");
}

// Set execution time limit
set_time_limit(300); // 5 minutes max

// Include required files
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/payment_functions.php';

// Log start of cleanup
$start_time = microtime(true);
$log_prefix = "[CRON-CLEANUP " . date('Y-m-d H:i:s') . "]";

error_log("{$log_prefix} Starting automatic cleanup of expired orders and payments...");

try {
    // 1. Cancel expired payments (15-30 minute timeout)
    $expired_payments = cancelExpiredPayments($conn);
    
    // 2. Cancel expired orders and bookings (3-hour timeout from order creation)
    $expired_orders = autoCleanupExpiredOrders($conn);
    
    // Calculate execution time
    $execution_time = round((microtime(true) - $start_time) * 1000, 2);
    
    // Log results
    $total_cleaned = $expired_payments + $expired_orders;
    
    if ($total_cleaned > 0) {
        error_log("{$log_prefix} Cleanup completed successfully:");
        error_log("{$log_prefix} - Expired payments cancelled: {$expired_payments}");
        error_log("{$log_prefix} - Expired orders/bookings cancelled: {$expired_orders}");
        error_log("{$log_prefix} - Total items processed: {$total_cleaned}");
        error_log("{$log_prefix} - Execution time: {$execution_time}ms");
        
        // Output for web cron monitoring
        if (isset($_SERVER['HTTP_HOST'])) {
            echo json_encode([
                'success' => true,
                'message' => 'Cleanup completed successfully',
                'expired_payments' => $expired_payments,
                'expired_orders' => $expired_orders,
                'total_cleaned' => $total_cleaned,
                'execution_time_ms' => $execution_time,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    } else {
        error_log("{$log_prefix} Cleanup completed - no expired items found (execution time: {$execution_time}ms)");
        
        // Output for web cron monitoring
        if (isset($_SERVER['HTTP_HOST'])) {
            echo json_encode([
                'success' => true,
                'message' => 'No expired items found',
                'expired_payments' => 0,
                'expired_orders' => 0,
                'total_cleaned' => 0,
                'execution_time_ms' => $execution_time,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
} catch (Exception $e) {
    $error_message = "Cleanup failed: " . $e->getMessage();
    error_log("{$log_prefix} ERROR: {$error_message}");
    
    // Output error for web cron monitoring
    if (isset($_SERVER['HTTP_HOST'])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $error_message,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    exit(1);
}

// Optional: Clean up old log entries (keep last 30 days)
try {
    // This could be expanded to clean up old cancelled orders, expired payments, etc.
    // For now, we'll just log that cleanup is complete
    error_log("{$log_prefix} Cleanup cycle completed successfully");
} catch (Exception $e) {
    error_log("{$log_prefix} Warning: Log cleanup failed: " . $e->getMessage());
}

exit(0);
?>