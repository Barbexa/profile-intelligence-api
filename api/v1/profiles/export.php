<?php
require "../../../db.php";
// ... (Include the same Security Guard/Token Check code from index.php here) ...

if ($_GET['format'] === 'csv') {
    $stmt = $conn->query("SELECT * FROM profiles ORDER BY processed_at DESC");
    $profiles = $stmt->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="profiles_' . time() . '.csv"');

    $output = fopen('php://output', 'w');
    // CSV Header Row
    fputcsv($output, ['id', 'name', 'gender', 'gender_probability', 'age', 'country_id', 'created_at']);

    foreach ($profiles as $row) {
        fputcsv($output, [
            $row['id'],
            $row['name'],
            $row['gender'],
            $row['probability'],
            $row['age'],
            $row['country_id'],
            $row['processed_at']
        ]);
    }
    fclose($output);
    exit;
}