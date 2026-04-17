<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require "db.php";

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

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