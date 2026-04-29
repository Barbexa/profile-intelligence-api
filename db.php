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
    echo json_encode(["status" => "error", "message" => "Connection Failed: " . $e->getMessage()]);
    exit;
}

// 1. PROFILES TABLE
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS profiles (
        id CHAR(36) PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        gender VARCHAR(50),
        probability FLOAT,
        age INT,
        country_id VARCHAR(10),
        is_confident BOOLEAN DEFAULT FALSE,
        sample_size INT,
        processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    error_log($e->getMessage());
}

// 2. STAGE 3 USERS TABLE (UUID v7 & Avatar & Analyst Role)
try {
    // We use 'analyst' as default as per rubric.
    $sql_users = "CREATE TABLE IF NOT EXISTS users (
        id CHAR(36) PRIMARY KEY, 
        github_id VARCHAR(255) UNIQUE NOT NULL,
        username VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        avatar_url VARCHAR(255),
        role ENUM('admin', 'analyst') DEFAULT 'analyst',
        is_active BOOLEAN DEFAULT TRUE,
        last_login_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql_users);

    // EMERGENCY: Ensure YOU are an admin so you can test POST/DELETE
    // Run this once, then you can remove it.
    $stmt = $conn->prepare("INSERT INTO users (id, github_id, username, role) 
                            VALUES (UUID(), '0', 'Barbexa', 'admin') 
                            ON DUPLICATE KEY UPDATE role='admin'");
    $stmt->execute();

} catch (PDOException $e) {
    error_log("Users table update failed: " . $e->getMessage());
}
?>