<?php
// api/v1/auth/github.php
session_start();

$client_id = getenv('GITHUB_CLIENT_ID');

if (!$client_id) {
    die("Error: GITHUB_CLIENT_ID is not set in environment variables.");
}
// This must match EXACTLY what you put in GitHub Developer Settings


$redirect_uri = "https://" . $_SERVER['HTTP_HOST'] . "/api/v1/auth/callback.php";

// State is for security (prevents CSRF)
$_SESSION['oauth_state'] = bin2hex(random_bytes(16));

$params = [
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'scope' => 'user:email',
    'state' => $_SESSION['oauth_state']
];

header("Location: https://github.com/login/oauth/authorize?" . http_build_query($params));
exit;