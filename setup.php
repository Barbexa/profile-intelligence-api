<?php
require "db.php"; // This uses your getenv variables

$sql = "CREATE TABLE IF NOT EXISTS profiles (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL,
    gender VARCHAR(20),
    gender_probability FLOAT,
    sample_size INT,
    age INT,
    age_group VARCHAR(20),
    country_id VARCHAR(10),
    country_probability FLOAT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

try {
    $conn->exec($sql);
    echo "Table 'profiles' created successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>