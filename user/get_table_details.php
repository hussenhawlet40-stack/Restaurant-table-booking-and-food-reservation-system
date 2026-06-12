<?php
session_start();
require_once "../connection.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$table_id = intval($_GET['table_id'] ?? 0);

if ($table_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid table ID']);
    exit;
}

try {
    // Get table information
    $stmt = $conn->prepare("SELECT * FROM restaurant_tables WHERE id = ?");
    $stmt->execute([$table_id]);
    $table = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$table) {
        http_response_code(404);
        echo json_encode(['error' => 'Table not found']);
        exit;
    }
    
    // Get only future bookings for this table - remove past bookings automatically
    $stmt = $conn->prepare("
        SELECT b.id, b.booking_datetime, b.meal_type, b.guests, b.status, b.special_requests
        FROM bookings b 
        WHERE b.table_id = ? 
        AND b.status IN ('confirmed', 'pending') 
        AND b.booking_datetime > NOW()
        ORDER BY b.booking_datetime ASC
    ");
    $stmt->execute([$table_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    echo json_encode([
        'table' => $table,
        'bookings' => $bookings
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>