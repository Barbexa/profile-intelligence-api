<?php
$host = getenv('DB_HOST'); // The universal internal address
$port = getenv('DB_PORT');      // The standard internal MySQL port
$db = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
try {
    // Notice how we add ;port=$port into the string
    $conn = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

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
        id CHAR(36) PRIMARY KEY, -- Using UUID v7 as requested
        name VARCHAR(255) NOT NULL,
        gender VARCHAR(50),
        probability FLOAT, -- TRD uses 'probability'
        age INT,
        country_id VARCHAR(10),
        is_confident BOOLEAN DEFAULT FALSE, -- Required by TRD
        sample_size INT,
        processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP -- TRD uses 'processed_at'
    )";
    $conn->exec($sql);
    // Optional: echo "Table created successfully!";
} catch (PDOException $e) {
    // This will help us see if the API can talk to the DB even if TablePlus can't
    error_log("Table creation failed: " . $e->getMessage());
}

?>