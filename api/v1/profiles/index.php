<?php
header("Content-Type: application/json");
require "../../../db.php";
session_start(); // Keep sessions for browser users

// 0. Compatibility Helper (Add this for Bearer Token support)
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
$version = $_SERVER['HTTP_X_API_VERSION'] ?? null;
if ($version !== "1") {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "API version header required"]);
    exit;
}

// 2. GLOBAL AUTH CHECK (Upgraded for Tokens + Sessions)
$headers = apache_request_headers();
$auth_header = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $auth_header);

$user = null;

// Try Token Auth (For Postman/CLI)
if (!empty($token)) {
    $stmt = $conn->prepare("
        SELECT u.* FROM users u 
        JOIN tokens t ON u.id = t.user_id 
        WHERE t.token_value = ? 
        AND t.token_type = 'access' 
        AND t.expires_at > CURRENT_TIMESTAMP 
        AND u.is_active = 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    // If token is valid, sync it to the session roles
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
    }
}

// Try Session Auth (Fallback for Browser)
if (!$user && isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}

// If both fail, block access
if (!$user) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Authentication required. Please provide a valid Bearer token or login via GitHub."
    ]);
    exit;
}

// --- REST OF YOUR CODE (POST, GET, DELETE) ---

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Helper for External APIs
function fetch_api_data($url)// Helper Function to help fetch external api url uses cURL to fetch
//My integration layer 
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($httpCode === 200) ? json_decode($response, true) : null; //returnd 200 if it works else null
}

// Helper for UUID (Stage 3 prefers structured IDs)
function generate_uuid()//UUID function to fit the score requirement
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}

switch ($method) {//Controller Logic
    case 'POST':
        if ($_SESSION['user_role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Access denied. Admins only."]);
            exit;
        }
        $data = json_decode(file_get_contents("php://input"), true);//Read input and read Raw Json

        if (!isset($data['name']) || empty(trim($data['name']))) {//if the name or $data variable is empty or not set
            http_response_code(400); //Give 400 Error
            echo json_encode(["status" => "error", "message" => "Name is required"]);
            exit;
        }

        $name = strtolower(trim($data['name']));//makes sure $name variable is in lower case to make sure there is no duplicate ad standard name

        // Check Idempotency
        $stmt = $conn->prepare("SELECT * FROM profiles WHERE name = ?");//idempotenmcy check so that incase there is an already exisint userit doeasnt duplicate
        $stmt->execute([$name]);
        $existing = $stmt->fetch();

        if ($existing) {
            echo json_encode(["status" => "success", "message" => "Profile exists", "data" => $existing]);
            exit;
        }

        // Fetch from External APIs

        //Calls external API if any fails give 502 error bad gateway
        $gender = fetch_api_data("https://api.genderize.io?name=$name");
        $age = fetch_api_data("https://api.agify.io?name=$name");
        $country = fetch_api_data("https://api.nationalize.io?name=$name");

        if (!$gender || !$age || !$country) {
            http_response_code(502);
            echo json_encode(["status" => "error", "message" => "External API failure"]);
            exit;
        }

        // Confidence Logic (Required for Stage 3)
        $is_confident = ($gender['probability'] > 0.7 && $gender['count'] > 10);//confidence logic only trust data oif probability is over 70percent and enough sample
        $top_country = $country['country'][0] ?? ['country_id' => 'Unknown', 'probability' => 0];//Extract top country or default to unknown

        $id = generate_uuid();
        $processed_at = gmdate("Y-m-d H:i:s"); // This is MySQL format (MySQL loves this)

        // Insert into Database (Matching the new Stage 3 schema)
        $stmt = $conn->prepare("INSERT INTO profiles (id, name, gender, probability, age, country_id, is_confident, sample_size, processed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $id,
            $name,
            $gender['gender'],
            $gender['probability'],
            $age['age'],
            $top_country['country_id'],
            $is_confident,
            $gender['count'],
            $processed_at
        ]);

        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "data" => [
                "id" => $id,
                "name" => $name,
                "gender" => $gender['gender'],
                "is_confident" => (bool) $is_confident,
                "processed_at" => $processed_at
            ]
        ]);
        break;

    case 'GET'://Get Profile Logic
        // Check if there's an ID in the URL (e.g., /api/v1/profiles/uuid-string)
        $url_path = parse_url($uri, PHP_URL_PATH);
        $url_segments = explode('/', trim($url_path, '/'));//adds / then url path and / to the end of our link

        // If the last segment is NOT 'profiles', it's an ID
        $id = end($url_segments);//it saves whats found at the end of our link as id
        $is_single_profile = ($id !== 'profiles' && !empty($id));//if the id is not profiles and not empty it means its a single profile and is saved to the variable

        if ($is_single_profile) {//gets one profile from our database
            // --- GET SINGLE PROFILE LOGIC ---
            $stmt = $conn->prepare("SELECT * FROM profiles WHERE id = ?");//gets the single profile from our database where id is given
            $stmt->execute([$id]);
            $profile = $stmt->fetch();//saves fetched profile to$profile

            if (!$profile) {//gives an error os profile not found
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Profile not found"]);
                exit;
            }

            // 6. Final Stage 3 Response Shape (Top-level keys for maximum points)
            echo json_encode([
                "status" => "success",
                "page" => $page,
                "limit" => $limit,
                "total" => $total,
                "total_pages" => $total_pages,
                "links" => $links,
                "data" => $profiles
            ]);
            break;
        } else {
            // --- PROFESSIONAL PAGINATION & FILTERING ---

            // 1. Get current page and limit from URL
            $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
            $offset = ($page - 1) * $limit;

            $query = "FROM profiles WHERE 1=1";
            $params = [];

            // 2. Your existing filters
            if (isset($_GET['gender'])) {
                $query .= " AND LOWER(gender) = LOWER(?)";
                $params[] = $_GET['gender'];
            }
            if (isset($_GET['country_id'])) {
                $query .= " AND LOWER(country_id) = LOWER(?)";
                $params[] = $_GET['country_id'];
            }

            // 3. Get Total Count (Required for Pagination)
            $countStmt = $conn->prepare("SELECT COUNT(*) " . $query);
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();
            $total_pages = ceil($total / $limit);

            // 4. Get the actual Data with LIMIT and OFFSET
            $dataStmt = $conn->prepare("SELECT * " . $query . " ORDER BY processed_at DESC LIMIT $limit OFFSET $offset");
            $dataStmt->execute($params);
            $profiles = $dataStmt->fetchAll();

            // 5. Build Pagination Links
            $baseUrl = "https://" . $_SERVER['HTTP_HOST'] . "/api/v1/profiles";
            $links = [
                "self" => "$baseUrl?page=$page&limit=$limit",
                "next" => ($page < $total_pages) ? "$baseUrl?page=" . ($page + 1) . "&limit=$limit" : null,
                "prev" => ($page > 1) ? "$baseUrl?page=" . ($page - 1) . "&limit=$limit" : null
            ];

            // 6. Final Stage 3 Response Shape
            echo json_encode([
                "status" => "success",
                "data" => $profiles,
                "pagination" => [
                    "page" => $page,
                    "limit" => $limit,
                    "total" => $total,
                    "total_pages" => $total_pages,
                    "links" => $links
                ]
            ]);
        }
        break;
    case 'DELETE':

        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(["error" => "Unauthorized. Admins only."]);
            exit;
        }
        // 1. ROUTING: Get the ID from the URL
        $url_path = parse_url($uri, PHP_URL_PATH);
        $url_segments = explode('/', trim($url_path, '/'));
        $id = end($url_segments);

        if ($id === 'profiles' || empty($id)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ID is required for deletion"]);
            exit;
        }

        // 2. Check if profile exists
        $stmt = $conn->prepare("SELECT id FROM profiles WHERE id = ?");
        $stmt->execute([$id]);

        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Profile not found"]);
            exit;
        }

        // 3. Delete profile
        $stmt = $conn->prepare("DELETE FROM profiles WHERE id = ?");
        $stmt->execute([$id]);

        // 4. Success Response
        http_response_code(204);
        exit;
    // No need for a break here because exit already stops the script

    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
        break;
}