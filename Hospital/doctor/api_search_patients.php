<?php
session_start();
include "includes/db_connect.php";

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit();
}

try {
    $search_term = "%$query%";
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, patient_id, dob, phone 
        FROM patients 
        WHERE (first_name LIKE ? OR last_name LIKE ? OR patient_id LIKE ? OR phone LIKE ?)
        AND status = 'active'
        ORDER BY first_name, last_name 
        LIMIT 10
    ");
    $stmt->execute([$search_term, $search_term, $search_term, $search_term]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($patients);
} catch (PDOException $e) {
    error_log("Search patients error: " . $e->getMessage());
    echo json_encode([]);
}
?>