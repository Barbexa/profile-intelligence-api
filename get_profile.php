<?php

$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "ID is required"]);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM profiles WHERE id = ?");
$stmt->execute([$id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Profile not found"]);
    exit;
}

echo json_encode([
    "status" => "success",
    "data" => $profile
]);