<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require "db.php";

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// remove your project folder from the URL
$basePath = '/profile-intelligence-api';
$uri = str_replace($basePath, '', $uri);

if ($method === "POST" && strpos($uri, "/api/profiles") !== false) {
    require "create_profile.php";
} elseif ($method === "GET" && preg_match("#/api/profiles/([a-z0-9-]+)#", $uri, $matches)) {
    $_GET['id'] = $matches[1];
    require "get_profile.php";
} elseif ($method === "GET" && strpos($uri, "/api/profiles") !== false) {
    require "list_profiles.php";
} elseif ($method === "DELETE" && preg_match("#/api/profiles/([a-z0-9-]+)#", $uri, $matches)) {
    $_GET['id'] = $matches[1];
    require "delete_profile.php";
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Route not found"
    ]);
}