<?php
session_start();
include "includes/db_connect.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name']);
        $category = trim($_POST['category']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $duration_minutes = !empty($_POST['duration_minutes']) ? intval($_POST['duration_minutes']) : null;
        $is_active = intval($_POST['is_active']);

        // Validate required fields
        if (empty($name) || empty($category) || $price < 0) {
            throw new Exception('Please fill in all required fields correctly.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO services (name, category, description, price, duration_minutes, is_active, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$name, $category, $description, $price, $duration_minutes, $is_active]);
        
        $_SESSION['success'] = 'Service added successfully!';
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error adding service: ' . $e->getMessage();
    }
}

header('Location: services.php');
exit;
?>