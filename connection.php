<?php
//connection
$host = "localhost";
$dbname = "Restaurant_db";
$username = "root";
$password = ""; // WAMP default password is empty

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection Error: " . $e->getMessage());
}
?>
