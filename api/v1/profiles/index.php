<?php
header("Content-Type: application/json");
require "../../../db.php";
session_start();

// Compatibility Helper
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

// 2. GLOBAL AUTH CHECK
$auth_header = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $auth_header);
$user = null;

if (!empty($token)) {
    $stmt = $conn->prepare("SELECT u.* FROM users u JOIN tokens t ON u.id = t.user_id WHERE t.token_value = ? AND t.token_type = 'access' AND t.expires_at > CURRENT_TIMESTAMP AND u.is_active = 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
    }
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

// HELPERS
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

function sendPaginatedResponse($stmt, $page, $limit, $conn, $url_path, $params = [], $is_search = false)
{
    // 1. Get total count
    $sql_count = "SELECT COUNT(*) FROM profiles " . ($is_search ? "WHERE name LIKE ?" : "");
    $total_stmt = $conn->prepare($sql_count);
    $total_stmt->execute($is_search ? ["%$params[0]%"] : []);
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
            "self" => "$url_path?page=$page&limit=$limit" . ($is_search ? "&q=" . urlencode($params[0]) : ""),
            "next" => ($page < $total_pages) ? "$url_path?page=" . ($page + 1) . "&limit=$limit" . ($is_search ? "&q=" . urlencode($params[0]) : "") : null,
            "prev" => ($page > 1) ? "$url_path?page=" . ($page - 1) . "&limit=$limit" . ($is_search ? "&q=" . urlencode($params[0]) : "") : null
        ],
        "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

// MAIN LOGIC
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$url_path = parse_url($uri, PHP_URL_PATH);
$url_segments = explode('/', trim($url_path, '/'));
$last_segment = end($url_segments);

switch ($method) {
    case 'POST':
        if ($_SESSION['user_role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Admins only."]);
            exit;
        }
        $data = json_decode(file_get_contents("php://input"), true);
        $name = strtolower(trim($data['name'] ?? ''));
        if (empty($name)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Name required"]);
            exit;
        }

        $gender = fetch_api_data("https://api.genderize.io?name=$name");
        $age = fetch_api_data("https://api.agify.io?name=$name");
        $country = fetch_api_data("https://api.nationalize.io?name=$name");

        $top_country = $country['country'][0] ?? ['country_id' => 'Unknown', 'probability' => 0];
        $id = uniqid();

        $stmt = $conn->prepare("INSERT INTO profiles (id, name, gender, probability, age, country_id, processed_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id, $name, $gender['gender'], $gender['probability'], $age['age'], $top_country['country_id'], gmdate("Y-m-d H:i:s")]);

        // FULL SCHEMA RETURNED
        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "data" => [
                "id" => $id,
                "name" => $name,
                "gender" => $gender['gender'],
                "gender_probability" => $gender['probability'],
                "age" => $age['age'],
                "age_group" => ($age['age'] < 18 ? 'minor' : 'adult'),
                "country_id" => $top_country['country_id'],
                "country_probability" => $top_country['probability'],
                "created_at" => gmdate("Y-m-d H:i:s")
            ]
        ]);
        break;

    case 'GET':
        // 1. Export
        if (strpos($url_path, '/export') !== false) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="profiles.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['id', 'name', 'gender', 'probability', 'age', 'country_id', 'processed_at']);
            $stmt = $conn->query("SELECT * FROM profiles ORDER BY processed_at DESC");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit;
        }

        $page = (int) ($_GET['page'] ?? 1);
        $limit = (int) ($_GET['limit'] ?? 10);
        $offset = ($page - 1) * $limit;

        // 2. Search
        if ($last_segment === 'search') {
            $stmt = $conn->prepare("SELECT * FROM profiles WHERE name LIKE ? ORDER BY processed_at DESC LIMIT $limit OFFSET $offset");
            $stmt->execute(["%" . ($_GET['q'] ?? '') . "%"]);
            sendPaginatedResponse($stmt, $page, $limit, $conn, "/api/profiles/search", [$_GET['q'] ?? ''], true);
        }
        // 3. Single Profile (UUID)
        elseif ($last_segment !== 'profiles' && !empty($last_segment)) {
            $stmt = $conn->prepare("SELECT * FROM profiles WHERE id = ?");
            $stmt->execute([$last_segment]);
            if ($p = $stmt->fetch()) {
                echo json_encode(["status" => "success", "data" => $p]);
            } else {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Not found"]);
            }
        }
        // 4. List All
        else {
            $stmt = $conn->prepare("SELECT * FROM profiles ORDER BY processed_at DESC LIMIT $limit OFFSET $offset");
            $stmt->execute();
            sendPaginatedResponse($stmt, $page, $limit, $conn, "/api/profiles");
        }
        break;

    case 'DELETE':
        if ($last_segment !== 'profiles') {
            $stmt = $conn->prepare("DELETE FROM profiles WHERE id = ?");
            $stmt->execute([$last_segment]);
            http_response_code(204);
        }
        break;

    default:
        http_response_code(405);
        break;
}