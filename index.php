<?php
// 1. CORS Headers (Must be at the very top)
$allowed_origin = "https://insighta-web-orcin-nine.vercel.app/"; // CHECK THIS
header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-API-VERSION");

// 2. Handle Browser Preflight (CORS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 3. Include Database (Must be before any DB queries)
require "db.php";

// 4. Router
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Stats Route
if ($request_uri === '/api/v1/stats') {
    header('Content-Type: application/json');
    // Now $conn is available because db.php was required above!
    $count = $conn->query("SELECT COUNT(*) FROM profiles")->fetchColumn();
    echo json_encode(['total' => (int) $count]);
    exit;
}

// 5. Default Response
header("Content-Type: application/json");
echo json_encode([
    "status" => "success",
    "message" => "Welcome to the Profile Intelligence API",
    "version" => "1.0.0"
]);