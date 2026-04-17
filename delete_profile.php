<?php

$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "ID is required"
    ]);
    exit;
}

// Check if profile exists
$stmt = $conn->prepare("SELECT * FROM profiles WHERE id = ?");
$stmt->execute([$id]);

if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Profile not found"
    ]);
    exit;
}

// Delete profile
$stmt = $conn->prepare("DELETE FROM profiles WHERE id = ?");
$stmt->execute([$id]);

// ✅ IMPORTANT: return 204 and NOTHING else
http_response_code(204);
exit;