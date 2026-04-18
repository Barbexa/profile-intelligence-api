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
    "data" => [
        "id" => $profile['id'],
        "name" => $profile['name'],
        "gender" => $profile['gender'],
        "gender_probability" => (float) $profile['gender_probability'],
        "sample_size" => (int) $profile['sample_size'],
        "age" => (int) $profile['age'],
        "age_group" => $profile['age_group'],
        "country_id" => $profile['country_id'],
        "country_probability" => (float) $profile['country_probability'],
        "created_at" => $profile['created_at']
    ]
]);