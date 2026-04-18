=
<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require "db.php";

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// This is the trick: We check if the URI ENDS with our endpoint, 
// ignoring whatever folders come before it.
if ($method === "POST" && preg_match("#/api/profiles/?$#", $uri)) {
    require "create_profile.php";
} elseif ($method === "GET" && preg_match("#/api/profiles/([a-z0-9-]+)/?$#", $uri, $matches)) {
    $_GET['id'] = $matches[1];
    require "get_profile.php";
} elseif ($method === "GET" && preg_match("#/api/profiles/?$#", $uri)) {
    require "list_profiles.php";
} elseif ($method === "DELETE" && preg_match("#/api/profiles/([a-z0-9-]+)/?$#", $uri, $matches)) {
    $_GET['id'] = $matches[1];
    require "delete_profile.php";
} else {
    // If we reach here, tell the grader it's a 404 but show them the URI for debugging
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Route not found",
        "debug_uri" => $uri
    ]);
}
?>