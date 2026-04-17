<?php

$query = "SELECT id, name, gender, age, age_group, country_id FROM profiles WHERE 1=1";
$params = [];

// Filters
if (isset($_GET['gender'])) {
    $query .= " AND LOWER(gender) = LOWER(?)";
    $params[] = $_GET['gender'];
}

if (isset($_GET['country_id'])) {
    $query .= " AND LOWER(country_id) = LOWER(?)";
    $params[] = $_GET['country_id'];
}

if (isset($_GET['age_group'])) {
    $query .= " AND LOWER(age_group) = LOWER(?)";
    $params[] = $_GET['age_group'];
}

$stmt = $conn->prepare($query);
$stmt->execute($params);

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    "status" => "success",
    "count" => count($results),
    "data" => $results
]);