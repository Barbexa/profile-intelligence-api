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
if (($_SERVER['HTTP_X_API_VERSION'] ?? null) !== "1") {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "API version header required"]);
    exit;
}

// 2. GLOBAL AUTH CHECK
$headers = apache_request_headers();
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

// Helpers
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

function generate_uuid()
{
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
}

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
        if (empty($name)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Name required"]);
            exit;
        }

        $stmt = $conn->prepare("SELECT * FROM profiles WHERE name = ?");
        $stmt->execute([$name]);
        if ($existing = $stmt->fetch()) {
            echo json_encode(["status" => "success", "data" => $existing]);
            exit;
        }

        $gender = fetch_api_data("https://api.genderize.io?name=$name");
        $age = fetch_api_data("https://api.agify.io?name=$name");
        $country = fetch_api_data("https://api.nationalize.io?name=$name");

        if (!$gender || !$age || !$country) {
            http_response_code(502);
            echo json_encode(["status" => "error", "message" => "External API failure"]);
            exit;
        }

        $top_country = $country['country'][0] ?? ['country_id' => 'Unknown'];
        $id = generate_uuid();
        $stmt = $conn->prepare("INSERT INTO profiles (id, name, gender, probability, age, country_id, processed_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id, $name, $gender['gender'], $gender['probability'], $age['age'], $top_country['country_id'], gmdate("Y-m-d H:i:s")]);

        http_response_code(201);
        echo json_encode(["status" => "success", "data" => ["id" => $id, "name" => $name]]);
        break;

    case 'GET':
        // 1. EXPORT CHECK
        if (strpos($url_path, '/export') !== false) {
            header_remove("Content-Type");
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="profiles_' . time() . '.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['id', 'name', 'gender', 'probability', 'age', 'country_id', 'processed_at']);
            $stmt = $conn->query("SELECT * FROM profiles ORDER BY processed_at DESC");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [$row['id'], $row['name'], $row['gender'], $row['probability'], $row['age'], $row['country_id'], $row['processed_at']]);
            }
            fclose($output);
            exit;
        }

        // 2. SINGLE PROFILE vs LIST
        $url_segments = explode('/', trim($url_path, '/'));
        $idOrProfiles = end($url_segments);

        if ($idOrProfiles !== 'profiles' && !empty($idOrProfiles)) {
            $stmt = $conn->prepare("SELECT * FROM profiles WHERE id = ?");
            $stmt->execute([$idOrProfiles]);
            if ($profile = $stmt->fetch()) {
                echo json_encode(["status" => "success", "data" => $profile]);
            } else {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Not found"]);
            }
        } else {
            $page = (int) ($_GET['page'] ?? 1);
            $limit = (int) ($_GET['limit'] ?? 10);
            $offset = ($page - 1) * $limit;

            $stmt = $conn->prepare("SELECT * FROM profiles ORDER BY processed_at DESC LIMIT $limit OFFSET $offset");
            $stmt->execute();
            echo json_encode(["status" => "success", "data" => $stmt->fetchAll(), "pagination" => ["page" => $page, "limit" => $limit]]);
        }
        break;

    case 'DELETE':
        $url_segments = explode('/', trim($url_path, '/'));
        $id = end($url_segments);
        if ($id !== 'profiles') {
            $stmt = $conn->prepare("DELETE FROM profiles WHERE id = ?");
            $stmt->execute([$id]);
            http_response_code(204);
        }
        break;

    default:
        http_response_code(405);
        break;
}