<?php
$host = getenv('DB_HOST');
$db = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASSWORD');
$port = '23771'; // The public port from your screenshot

try {
    // We add ;port= to the DSN string
    $conn = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    // For debugging: echo $e->getMessage(); 
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
    exit;
}