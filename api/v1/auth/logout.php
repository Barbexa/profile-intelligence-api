<?php
header("Content-Type: application/json");
require "../../../db.php";

$headers = apache_request_headers();
$auth_header = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $auth_header);

if ($token) {
    // Delete the access token AND any associated refresh tokens for that user session
    $stmt = $conn->prepare("DELETE FROM tokens WHERE user_id = (SELECT user_id FROM (SELECT user_id FROM tokens WHERE token_value = ?) as tmp)");
    $stmt->execute([$token]);
}

session_start();
session_destroy();

echo json_encode(["status" => "success", "message" => "Logged out successfully"]);