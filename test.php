<?php
header("Content-Type: application/json");
require "../../../db.php";
session_start();

// 0. Compatibility Helper for Headers
if (!function_exists('apache_request_headers')) {
    function apache_request_headers()
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

// 1. STRICT VERSION CHECK
$headers = apache_request_headers();
if (($headers['X-Api-Version'] ?? null) !== "1") {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "API version header required"]);
    exit;
}

// 2. GLOBAL AUTH CHECK (Keep your existing Auth Logic)
$auth_header = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $auth_header);
$user = null;

if (!empty($token)) {
    $stmt = $conn->prepare("SELECT u.* FROM users u JOIN tokens t ON u.id = t.user_id WHERE t.token_value = ? AND t.token_type = 'access' AND t.expires_at > CURRENT_TIMESTAMP AND u.is_active = 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
}

if (!$user && isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}

if (!$user) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Authentication required."]);
    exit;
}

// HELPER FUNCTIONS
function fetch_api_data($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($httpCode === 200) ? json_decode($response, true) : null;
}

function sendPaginatedResponse($stmt, $page, $limit, $conn, $url_path, $params = [])
{
    // 1. Get total count for pagination
    $total_stmt = $conn->prepare("SELECT COUNT(*) FROM profiles " . ($params ? "WHERE name LIKE ?" : ""));
    $total_stmt->execute($params ? ["%$params[0]%"] : []);
    $total = (int) $total_stmt->fetchColumn();
    $total_pages = ceil($total / $limit);

    // 2. Format output
    echo json_encode([
        "status" => "success",
        "page" => $page,
        "limit" => $limit,
        "total" => $total,
        "total_pages" => $total_pages,
        "links" => [
            "self" => "$url_path?page=$page&limit=$limit",
            "next" => ($page < $total_pages) ? "$url_path?page=" . ($page + 1) . "&limit=$limit" : null,
            "prev" => ($page > 1) ? "$url_path?page=" . ($page - 1) . "&limit=$limit" : null
        ],
        "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

// MAIN LOGIC
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$url_path = parse_url($uri, PHP_URL_PATH);

switch ($method) {
    case 'POST':
        if ($_SESSION['user_role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Admins only."]);
            exit;
        }
        $data = json_decode(file_get_contents("php://input"), true);
        $name = strtolower(trim($data['name'] ?? ''));

        // ... (Keep existing Name/Auth check logic here)

        $gender = fetch_api_data("https://api.genderize.io?name=$name");
        $age = fetch_api_data("https://api.agify.io?name=$name");
        $country = fetch_api_data("https://api.nationalize.io?name=$name");

        $top_country = $country['country'][0] ?? ['country_id' => 'Unknown', 'probability' => 0];
        $id = uniqid();

        $stmt = $conn->prepare("INSERT INTO profiles (id, name, gender, probability, age, country_id, processed_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id, $name, $gender['gender'], $gender['probability'], $age['age'], $top_country['country_id'], gmdate("Y-m-d H:i:s")]);

        // REQUIRED POST RESPONSE SCHEMA
        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "data" => [
                "id" => $id,
                "name" => $name,
                "gender" => $gender['gender'],
                "gender_probability" => $gender['probability'],
                "age" => $age['age'],
                "age_group" => ($age['age'] < 18) ? 'minor' : 'adult',
                "country_id" => $top_country['country_id'],
                "country_probability" => $top_country['probability'],
                "created_at" => gmdate("Y-m-d H:i:s")
            ]
        ]);
        break;

    case 'GET':
        $page = (int) ($_GET['page'] ?? 1);
        $limit = (int) ($_GET['limit'] ?? 10);
        $offset = ($page - 1) * $limit;

        // Search Route
        if (isset($_GET['q'])) {
            $stmt = $conn->prepare("SELECT * FROM profiles WHERE name LIKE ? ORDER BY processed_at DESC LIMIT $limit OFFSET $offset");
            $stmt->execute(["%" . $_GET['q'] . "%"]);
            sendPaginatedResponse($stmt, $page, $limit, $conn, "/api/profiles/search", [$_GET['q']]);
        }
        // List Route
        else {
            $stmt = $conn->prepare("SELECT * FROM profiles ORDER BY processed_at DESC LIMIT $limit OFFSET $offset");
            $stmt->execute();
            sendPaginatedResponse($stmt, $page, $limit, $conn, "/api/profiles");
        }
        break;

    // ... (Keep existing DELETE case)
}
?>