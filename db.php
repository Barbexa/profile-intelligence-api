<?php

$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$db = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

try {
    $conn = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        "status" => "error",
        "message" => "Connection Failed: " . $e->getMessage()
    ]);
    exit;
}

// 1. EMERGENCY PROFILES TABLE
try {
    $sql = "CREATE TABLE IF NOT EXISTS profiles (
        id CHAR(36) PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        gender VARCHAR(50),
        probability FLOAT,
        age INT,
        country_id VARCHAR(10),
        is_confident BOOLEAN DEFAULT FALSE,
        sample_size INT,
        processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);
} catch (PDOException $e) {
    error_log("Profiles table creation failed: " . $e->getMessage());
}

// 2. NEW USERS TABLE (For GitHub OAuth)
try {
    $sql_users = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        github_id VARCHAR(255) UNIQUE NOT NULL,
        username VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        role ENUM('admin', 'user') DEFAULT 'user',
        last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->exec($sql_users);
} catch (PDOException $e) {
    error_log("Users table creation failed: " . $e->getMessage());
}

?>