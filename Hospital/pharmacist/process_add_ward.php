<?php
session_start();
include "includes/db_connect.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $stmt = $pdo->prepare("INSERT INTO ward (ward_number, ward_name, ward_type, floor_number, department, capacity, description, facilities, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['ward_number'],
            $_POST['ward_name'],
            $_POST['ward_type'],
            $_POST['floor_number'],
            $_POST['department'],
            $_POST['capacity'],
            $_POST['description'],
            $_POST['facilities'],
            $_POST['status'],
            $_SESSION['user_id'] ?? 1
        ]);
        
        $_SESSION['success'] = "Ward added successfully!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error adding ward: " . $e->getMessage();
    }
    
    header("Location: wards.php");
    exit();
}
?>