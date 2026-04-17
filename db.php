<?php
$host = 'profile_db'; // The universal internal address
$port = '3306';      // The standard internal MySQL port
$db = 'pxxldb_mo3adgw19670dd6';
$user = 'pxxluser_mo3adgw1f5bdc16';
$pass = '977871dc9fac61038fbfd4705073ddd6a9c2e556b0c2bdf10dee595c68f88d46';
try {
    // We explicitly include the port 3306 for the internal network

    $conn = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        "status" => "error",
        "message" => "Connection Failed: " . $e->getMessage(),
        "debug_info" => "Connecting to $host on port $port"
    ]);
    exit;
}


// EMERGENCY TABLE CREATION
try {
    $sql = "CREATE TABLE IF NOT EXISTS profiles (
        id CHAR(36) PRIMARY KEY,
        name VARCHAR(255) UNIQUE NOT NULL,
        gender VARCHAR(20),
        gender_probability DECIMAL(5,2),
        sample_size INT,
        age INT,
        age_group VARCHAR(20),
        country_id VARCHAR(10),
        country_probability DECIMAL(5,2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);
    // Optional: echo "Table created successfully!";
} catch (PDOException $e) {
    // This will help us see if the API can talk to the DB even if TablePlus can't
    error_log("Table creation failed: " . $e->getMessage());
}
?>