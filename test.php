<?php
session_start();
require "../../../db.php";

$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;

if (!$state || $state !== $_SESSION['oauth_state']) {
    die("Security check failed: State mismatch.");
}

if (!$code) {
    die("No code received from GitHub.");
}

$client_id = getenv('GITHUB_CLIENT_ID');
$client_secret = getenv('GITHUB_CLIENT_SECRET');

// 1. TRADE CODE FOR ACCESS TOKEN
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

$token = $response['access_token'] ?? null;

if (!$token) {
    die("Failed to get Access Token. Check your Client Secret in PXXXL.");
}

// 2. GET USER PROFILE FROM GITHUB
$ch = curl_init("https://api.github.com/user");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $token",
    "User-Agent: Profile-API-Habiba"
]);
$user = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($user['login'])) {
    die("Failed to fetch GitHub user data.");
}

// 3. START SESSION & REDIRECT
$_SESSION['user'] = [
    'username' => $user['login'],
    'avatar' => $user['avatar_url'],
    'id' => $user['id']
];

// Success Message
echo "<h1>Login Successful!</h1>";
echo "Welcome, " . htmlspecialchars($user['login']) . "!";
echo "<br><img src='" . $user['avatar_url'] . "' width='100'>";
echo "<br><br><a href='/api/v1/profiles'>Go to Profiles Dashboard</a>";