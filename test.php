<?php
// api/v1/auth/callback.php
session_start();
require "../../../db.php";

$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;

// 1. Security Check
if (!$state || $state !== $_SESSION['oauth_state']) {
    die("Invalid state. Possible CSRF attack.");
}

if (!$code) {
    die("No code received from GitHub.");
}

// 2. Trade CODE for ACCESS TOKEN
$client_id = getenv('GITHUB_CLIENT_ID');
$client_secret = getenv('GITHUB_CLIENT_SECRET');

$ch = curl_init("https://github.com/login/oauth/access_token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'code' => $code,
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

$response = json_decode(curl_exec($ch), true);
curl_close($ch);

$access_token = $response['access_token'] ?? null;

if (!$access_token) {
    die("Failed to obtain access token. Check your Client Secret in PXXXL.");
}

// 3. Get User Details from GitHub
$ch = curl_init("https://api.github.com/user");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: token $access_token",
    "User-Agent: PHP-App" // GitHub requires a User-Agent header
]);

$user_data = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($user_data['login'])) {
    die("Failed to fetch user data from GitHub.");
}

// 4. Save to Database & Start Session
$github_id = $user_data['id'];
$username = $user_data['login'];
$email = $user_data['email'] ?? ($username . "@github.com"); // Fallback if email is private

$stmt = $conn->prepare("INSERT INTO users (github_id, username, email) 
                        VALUES (?, ?, ?) 
                        ON DUPLICATE KEY UPDATE last_login = CURRENT_TIMESTAMP");
$stmt->execute([$github_id, $username, $email]);

$_SESSION['user_id'] = $github_id;
$_SESSION['username'] = $username;

// 5. Success Redirect
echo "<h1>Success!</h1>";
echo "Welcome, " . htmlspecialchars($username) . ". You are now logged in.";
echo "<br><a href='/api/v1/profiles'>View Profiles</a>";