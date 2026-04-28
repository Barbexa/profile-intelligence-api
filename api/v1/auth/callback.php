<?php
// api/v1/auth/callback.php
session_start();
require "../../../db.php";

$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;

// Security check: Does the state match what we sent?
if (!$state || $state !== $_SESSION['oauth_state']) {
    die("Invalid state. Possible CSRF attack.");
}

if (!$code) {
    die("No code received from GitHub.");
}

echo "Success! We got the code: " . $code;
echo "<br>Next step: Trade this code for an Access Token.";