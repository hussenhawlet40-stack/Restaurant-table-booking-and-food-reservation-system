<?php
// Payment Configuration for Ethiopian Restaurant System

// Payment Methods
define('ENABLE_TELEBIRR', true);
define('ENABLE_CBE', true);
define('ENABLE_CASH_ON_ARRIVAL', true);

// TeleBirr Configuration
define('TELEBIRR_MERCHANT_ID', 'RESTAURANT_TELEBIRR');
define('TELEBIRR_PHONE', '0991826384'); // Restaurant TeleBirr number
define('TELEBIRR_SANDBOX', true); // Set to false for production

// CBE (Commercial Bank of Ethiopia) Configuration
define('CBE_MERCHANT_ID', 'RESTAURANT_CBE');
define('CBE_ACCOUNT', '1000324271116'); // Restaurant CBE account
define('CBE_SANDBOX', true); // Set to false for production

// Payment Settings
define('REQUIRE_PAYMENT_FOR_BOOKING', false); // Set to true to require payment for table booking
define('REQUIRE_PAYMENT_FOR_ORDERS', true);   // Set to true to require payment for pre-orders
define('BOOKING_DEPOSIT_AMOUNT', 50.00);      // ETB amount for table booking deposit
define('PAYMENT_TIMEOUT_MINUTES', 180);       // 3 hours (180 minutes) to complete payment

// Order & Booking Timeout Structure
// When user orders food:
// 1. Order created with status 'pending'
// 2. Table booking created with status 'pending' (table is RESERVED)
// 3. User can pay immediately OR click "Not Now"
// 4. If "Not Now": order status becomes 'pending_payment', table REMAINS RESERVED
// 5. User has 3 HOURS from ORDER CREATION to complete payment
// 6. After 3 hours: both order AND booking are cancelled, table becomes available
// 7. Payment timeout (for payment attempts) is separate from order timeout

define('ORDER_TIMEOUT_HOURS', 3);             // Hours from order creation until cancellation
define('TABLE_RESERVATION_TIMEOUT_HOURS', 3); // Hours table remains reserved for unpaid orders

// Currency
define('PAYMENT_CURRENCY', 'ETB');
define('PAYMENT_CURRENCY_SYMBOL', 'ETB');

// Payment Status
define('PAYMENT_PENDING', 'pending');
define('PAYMENT_COMPLETED', 'completed');
define('PAYMENT_FAILED', 'failed');
define('PAYMENT_CANCELLED', 'cancelled');
define('PAYMENT_EXPIRED', 'expired');

// Order Status Flow
// 'pending' -> User just placed order, can pay or skip
// 'pending_payment' -> User clicked "Not Now", countdown active
// 'confirmed' -> Payment completed successfully
// 'cancelled' -> Order expired or manually cancelled

// Booking Status Flow (for orders)
// 'pending' -> Table reserved, waiting for payment
// 'confirmed' -> Payment completed, table confirmed
// 'cancelled' -> Order expired (3+ hours) or payment failed permanently

// Webhook URLs (for production)
define('TELEBIRR_WEBHOOK_URL', 'https://yourrestaurant.com/webhooks/telebirr.php');
define('CBE_WEBHOOK_URL', 'https://yourrestaurant.com/webhooks/cbe.php');

// Return URLs
define('PAYMENT_SUCCESS_URL', '/user/payment_success.php');
define('PAYMENT_CANCEL_URL', '/user/payment_cancel.php');
define('PAYMENT_FAIL_URL', '/user/payment_fail.php');
?>