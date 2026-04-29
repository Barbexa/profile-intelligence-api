<?php
// THIS LINE IS THE KEY: It triggers the table creation logic in db.php
require "db.php";

header("Content-Type: application/json");
echo json_encode([
    "status" => "success",
    "message" => "Welcome to the Profile Intelligence API",
    "version" => "1.0.0",
    "documentation" => "/api/v1/profiles",
    "auth_endpoint" => "/api/v1/auth/github.php"
]);