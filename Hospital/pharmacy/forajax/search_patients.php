<?php
session_start();
include "../admin/connection.php"; // Use MySQLi for now since your patients table might be in the main hospital DB

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit();
}

// Search patients in the hospital database
$search_query = "
    SELECT id, patient_id, CONCAT(first_name, ' ', last_name) as name 
    FROM patients 
    WHERE first_name LIKE ? OR last_name LIKE ? OR patient_id LIKE ?
    ORDER BY first_name, last_name 
    LIMIT 10
";

$stmt = mysqli_prepare($link, $search_query);
$searchTerm = "%$query%";
mysqli_stmt_bind_param($stmt, "sss", $searchTerm, $searchTerm, $searchTerm);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$patients = [];
while ($row = mysqli_fetch_assoc($result)) {
    $patients[] = [
        'id' => $row['id'],
        'patient_id' => $row['patient_id'],
        'name' => $row['name']
    ];
}

echo json_encode($patients);
?>