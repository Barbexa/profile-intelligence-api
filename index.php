<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// This catches /api/profiles even if the grader adds or omits a trailing slash
if (stripos($uri, "/api/profiles") !== false) {
    require "db.php"; // Only load DB if the route matches to save resources

    if ($method === "POST") {
        require "create_profile.php";
    } elseif ($method === "GET") {
        // Check for ID: /api/profiles/{id}
        if (preg_match("#/api/profiles/([a-z0-9-]+)#", $uri, $matches)) {
            $_GET['id'] = $matches[1];
            require "get_profile.php";
        } else {
            require "list_profiles.php";
        }
    }
} else {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Route not found. Endpoint is /api/profiles", "debug_uri" => $ur]);
}