<?php
session_start();
include "includes/db_connect.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode([]);
    exit;
}

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, patient_id, DATE_FORMAT(dob, '%Y-%m-%d') as dob
        FROM patients 
        WHERE (first_name LIKE ? OR last_name LIKE ? OR patient_id LIKE ?)
        AND active = 1
        ORDER BY first_name, last_name
        LIMIT 10
    ");
    $search_term = "%$query%";
    $stmt->execute([$search_term, $search_term, $search_term]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($patients);
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([]);
}
?>