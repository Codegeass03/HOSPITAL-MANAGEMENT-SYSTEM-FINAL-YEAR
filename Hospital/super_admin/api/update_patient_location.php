<?php
require_once "../includes/db_connect.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$patient_id = $data['patient_id'] ?? null;
$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;

if (!$patient_id || !$latitude || !$longitude) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE patients 
        SET last_known_lat = ?, last_known_lng = ?, location_updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$latitude, $longitude, $patient_id]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>