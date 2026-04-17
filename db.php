<?php
// Use $_ENV which is often more reliable than getenv() on cloud hosts
$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
$db = $_ENV['DB_NAME'] ?? getenv('DB_NAME');
$user = $_ENV['DB_USER'] ?? getenv('DB_USER');
$pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // TEMPORARY: If you see this, the connection worked!
    // echo "Connected successfully"; 

} catch (PDOException $e) {
    header('Content-Type: application/json');
    // This will help us see exactly what values PHP is trying to use
    echo json_encode([
        "status" => "error",
        "message" => "Connection Failed: " . $e->getMessage(),
        "debug_user_was" => $user // This helps us see if the variable is still empty
    ]);
    exit;
}