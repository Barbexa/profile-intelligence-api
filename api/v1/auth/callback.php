<?php
session_start();
require "../../../db.php";

// Helper for Stage 3 UUID v7 requirement
function generate_uuid_v7()
{
    $time = microtime(true) * 1000;
    $time = floor($time);
    $hex = dechex($time);
    $hex = str_pad($hex, 12, "0", STR_PAD_LEFT);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-7' .
        substr(bin2hex(random_bytes(2)), 1) . '-' .
        ['8', '9', 'a', 'b'][random_int(0, 3)] .
        substr(bin2hex(random_bytes(2)), 1) . '-' .
        bin2hex(random_bytes(6));
}

$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;

if (!$state || $state !== $_SESSION['oauth_state']) {
    die("Invalid state. Possible CSRF attack.");
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
if (!$access_token)
    die("Failed to obtain access token.");

// 3. Get User Details (Updated to include Avatar)
$ch = curl_init("https://api.github.com/user");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token", // Modern 'Bearer' format
    "User-Agent: Profile_Intelligence_API"
]);
$user_data = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($user_data['login']))
    die("Failed to fetch user data.");

// 4. Save to Database (Stage 3 Schema)
$github_id = $user_data['id'];
$username = $user_data['login'];
$email = $user_data['email'] ?? ($username . "@github.com");
$avatar = $user_data['avatar_url'];

// Check if user exists to preserve their original UUID
$stmt = $conn->prepare("SELECT id, role FROM users WHERE github_id = ?");
$stmt->execute([$github_id]);
$existing_user = $stmt->fetch();

if ($existing_user) {
    $user_id = $existing_user['id'];
    $role = $existing_user['role'];
    // Just update the login time and avatar
    $stmt = $conn->prepare("UPDATE users SET last_login_at = CURRENT_TIMESTAMP, avatar_url = ? WHERE github_id = ?");
    $stmt->execute([$avatar, $github_id]);
} else {
    $user_id = generate_uuid_v7();
    $role = 'analyst'; // Default Stage 3 role
    $stmt = $conn->prepare("INSERT INTO users (id, github_id, username, email, avatar_url, role) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $github_id, $username, $email, $avatar, $role]);
}

$_SESSION['user_id'] = $user_id;
$_SESSION['username'] = $username;
$_SESSION['user_role'] = $role;

// ... (After setting $_SESSION['user_role'] = $role;)

// 1. Generate Secure Tokens
$access_token = bin2hex(random_bytes(32));
$refresh_token = bin2hex(random_bytes(32));

// 2. Set Expiry Times (Strict Stage 3 Rules: 3 mins and 5 mins)
$access_expiry = date('Y-m-d H:i:s', strtotime('+3 minutes'));
$refresh_expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

// 3. Save to Database
$token_stmt = $conn->prepare("INSERT INTO tokens (user_id, token_type, token_value, expires_at) VALUES (?, ?, ?, ?)");
// --- EMERGENCY TABLE CHECK ---
$conn->exec("CREATE TABLE IF NOT EXISTS tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    token_type ENUM('access', 'refresh') NOT NULL,
    token_value VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");
// --- END EMERGENCY CHECK ---
// Save Access Token
$token_stmt->execute([$user_id, 'access', $access_token, $access_expiry]);
// Save Refresh Token
$token_stmt->execute([$user_id, 'refresh', $refresh_token, $refresh_expiry]);

// 4. Update the Success Page to show the tokens (So you can copy them to Postman!)
echo "<h1>Success!</h1>";
echo "<p>Welcome, " . htmlspecialchars($username) . "</p>";
echo "<div style='background: #f4f4f4; padding: 15px; border-radius: 8px;'>";
echo "<strong>Your Access Token (Expires in 3 mins):</strong><br>";
echo "<code style='word-break: break-all;'>$access_token</code><br><br>";
echo "<strong>Your Refresh Token (Expires in 5 mins):</strong><br>";
echo "<code>$refresh_token</code>";
echo "</div>";
echo "<p><a href='/api/v1/profiles'>Go to Profiles</a></p>";

// 5. Success
echo "<h1>Success!</h1>";
echo "<img src='$avatar' width='100' style='border-radius:50%'><br>";
echo "Welcome, " . htmlspecialchars($username) . " (Role: $role).";
echo "<br><a href='/api/v1/profiles'>Go to Dashboard</a>";