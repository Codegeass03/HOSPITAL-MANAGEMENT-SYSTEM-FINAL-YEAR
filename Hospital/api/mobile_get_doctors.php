<?php
// api/mobile_get_doctors.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            CONCAT(first_name, ' ', last_name) AS name,
            department,
            phone,
            email
        FROM users
        WHERE role = 'Doctor'
          AND status = 'active'
        ORDER BY first_name, last_name
    ");

    $stmt->execute();
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted_doctors = [];

    foreach ($doctors as $doctor) {
        $formatted_doctors[] = [
            'id' => $doctor['id'],
            'name' => $doctor['name'],
            'specialty' => $doctor['department'] ?: 'General Practice',
            'phone' => $doctor['phone'],
            'email' => $doctor['email'],
        ];
    }

    echo json_encode([
        'success' => true,
        'doctors' => $formatted_doctors,
        'count' => count($formatted_doctors),
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ]);
}
