<?php
//delete booking
session_start();
require_once "../connection.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {

    $booking_id = intval($_POST['booking_id']);

    // Delete related preorders
    $stmt1 = $conn->prepare("DELETE FROM preorders WHERE booking_id = ?");
    $stmt1->execute([$booking_id]);

    // Delete actual booking
    $stmt2 = $conn->prepare("DELETE FROM bookings WHERE id = ?");
    $stmt2->execute([$booking_id]);

    header("Location: view_bookings.php?msg=deleted");
    exit;
}

header("Location: view_bookings.php");
exit;
