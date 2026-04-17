<?php
// Detect Heroku JawsDB URL
$url = getenv('JAWSDB_URL');

if ($url) {
    // We are on Heroku - Parse the connection string
    $dbparts = parse_url($url);
    $host = $dbparts['host'];
    $user = $dbparts['user'];
    $pass = $dbparts['pass'];
    $db = ltrim($dbparts['path'], '/');
} else {
    // We are on Localhost (XAMPP)
    $host = '127.0.0.1';
    $db = 'profile_db';
    $user = 'root';
    $pass = '';
}

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "DB Error: " . $e->getMessage()]);
    exit;
}