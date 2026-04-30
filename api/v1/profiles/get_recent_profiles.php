<?php
// Inside your Backend Repository
header('Content-Type: application/json');
require_once 'db.php'; // Your secure DB connection

// Query the database
$stmt = $conn->query("SELECT name, gender, processed_at FROM profiles ORDER BY processed_at DESC LIMIT 5");
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return the data as JSON
echo json_encode($data);
?>