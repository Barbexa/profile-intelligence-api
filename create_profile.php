<?php
$data = json_decode(file_get_contents("php://input"), true);//puts json in php array
// ("php://input")This reads the "raw" data sent in the request body (since APIs send JSON, not standard form data).

if (!isset($data['name']) || empty($data['name'])) {//if name is empty or not set, return 400 error 
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Name is required"]);
    exit;//Stopping execution here if name is missing, returning error response. This ensures that we don't proceed with invalid input.
}

$name = strtolower(trim($data['name']));//remove whitespace and convert to lowercase for consistency

//Checking indempotency: If a profile with the same name already exists, return it instead of creating a new one
$stmt = $conn->prepare("SELECT * FROM profiles WHERE name = ?");//Prepared statement to prevent 
$stmt->execute([$name]);//safe query execution with user input
$existing = $stmt->fetch(PDO::FETCH_ASSOC);//execute the query and fetch the result as an associative array. If a profile with the same name exists, it will be stored in $existing; otherwise, $existing will be false.

if ($existing) {//If a profile with the same name already exists, return it instead of creating a new one. This ensures idempotency.
    echo json_encode([
        "status" => "success",
        "message" => "Profile already exists",
        "data" => $existing
    ]);
    exit;//Stopping execution here if profile exists, returning existing data instead of creating a new one. This ensures idempotency.
}
//Fetch data from external APIs
$gender = json_decode(file_get_contents("https://api.genderize.io?name=$name"), true);//Calls API to predict gender from name.
$age = json_decode(file_get_contents("https://api.agify.io?name=$name"), true);//Calls API to predict age from name.
$country = json_decode(file_get_contents("https://api.nationalize.io?name=$name"), true);//Calls API to predict country from name.

//Validate API responses
// Genderize
if (!$gender['gender'] || $gender['count'] == 0) {//If no gender found OR no data samples → error.
    http_response_code(502);
    echo json_encode(["status" => "error", "message" => "Genderize returned an invalid response"]);
    exit;
}

// Agify
if (!$age['age']) {//If no age found → error. Note: age 0 is valid, so we check for falsy value instead of empty.
    http_response_code(502);
    echo json_encode(["status" => "error", "message" => "Agify returned an invalid response"]);
    exit;
}

// Nationalize
if (empty($country['country'])) {//If no countries found → error. We check if the 'country' array is empty, which indicates that Nationalize couldn't predict any country for the given name.
    http_response_code(502);
    echo json_encode(["status" => "error", "message" => "Nationalize returned an invalid response"]);
    exit;
}
//Process data
// Age group
if ($age['age'] <= 12)
    $group = "child";
elseif ($age['age'] <= 19)
    $group = "teenager";
elseif ($age['age'] <= 59)
    $group = "adult";
else
    $group = "senior";

// Top country
usort($country['country'], fn($a, $b) => $b['probability'] <=> $a['probability']);//Sorts countries by probability (highest first).
$top = $country['country'][0];//Takes the country with the highest probability as the top prediction.

//Generate unique ID and timestamp
$id = uniqid(); // acceptable fallback if UUID lib not used
$created_at = gmdate("Y-m-d\TH:i:s\Z");//Generates a timestamp in ISO 8601 format (e.g., "2024-06-01T12:00:00Z") representing the current time in UTC. This is useful for consistent time representation across different systems and time zones.


//Store in database
$stmt = $conn->prepare("
INSERT INTO profiles 
(id, name, gender, gender_probability, sample_size, age, age_group, country_id, country_probability, created_at)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $id,
    $name,
    $gender['gender'],
    $gender['probability'],
    $gender['count'],
    $age['age'],
    $group,
    $top['country_id'],
    $top['probability'],
    $created_at
]);

http_response_code(201); // Task says 201 for success
echo json_encode([
    "status" => "success",
    "data" => [
        "id" => $id,
        "name" => $name,
        "gender" => $gender['gender'],
        "gender_probability" => (float) $gender['probability'],
        "sample_size" => (int) $gender['count'],
        "age" => (int) $age['age'],
        "age_group" => $group,
        "country_id" => $top['country_id'],
        "country_probability" => (float) $top['probability'],
        "created_at" => $created_at
    ]
]);
exit;
?>