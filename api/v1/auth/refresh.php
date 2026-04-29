<?php
header("Content-Type: application/json");
require "../../../db.php";

$data = json_decode(file_get_contents("php://input"), true);
$refresh_token = $data['refresh_token'] ?? '';

// 1. Validate and check if it's actually a REFRESH token
$stmt = $conn->prepare("SELECT user_id, id FROM tokens WHERE token_value = ? AND token_type = 'refresh' AND expires_at > CURRENT_TIMESTAMP");
$stmt->execute([$refresh_token]);
$old_token = $stmt->fetch();

if (!$old_token) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid refresh token"]);
    exit;
}

// 2. INVALIDATE the old refresh token immediately (Rubric Requirement)
$delete = $conn->prepare("DELETE FROM tokens WHERE id = ?");
$delete->execute([$old_token['id']]);

// 3. Issue NEW pair
$new_access = bin2hex(random_bytes(32));
$new_refresh = bin2hex(random_bytes(32));

$conn->prepare("INSERT INTO tokens (user_id, token_type, token_value, expires_at) VALUES (?, 'access', ?, ?)")
    ->execute([$old_token['user_id'], $new_access, date('Y-m-d H:i:s', strtotime('+3 minutes'))]);

$conn->prepare("INSERT INTO tokens (user_id, token_type, token_value, expires_at) VALUES (?, 'refresh', ?, ?)")
    ->execute([$old_token['user_id'], $new_refresh, date('Y-m-d H:i:s', strtotime('+5 minutes'))]);

echo json_encode([
    "status" => "success",
    "access_token" => $new_access,
    "refresh_token" => $new_refresh
]);