<?php
// Define permissions
$permissions = [
    'GET' => ['admin', 'analyst'],
    'POST' => ['admin'],
    'DELETE' => ['admin'],
    'PUT' => ['admin']
];

$method = $_SERVER['REQUEST_METHOD'];
$user_role = $_SESSION['user_role'] ?? 'guest';

if (!in_array($user_role, $permissions[$method])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Forbidden: $user_role role lacks $method permission"]);
    exit;
}
?>