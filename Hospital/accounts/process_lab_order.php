<?php
session_start();
include "includes/db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user role from database if not set in session
if (!isset($_SESSION['role'])) {
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['role'] = $user['role'];
        } else {
            $_SESSION['error'] = "User not found in database.";
            header("Location: lab_orders.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error fetching user role: " . $e->getMessage();
        header("Location: lab_orders.php");
        exit();
    }
}

// Check if user has permission to order labs
$user_role = $_SESSION['role'];
$allowed_roles = ['Doctor', 'Super Admin', 'super_admin', 'doctor', 'admin', 'Nurse'];

if (!in_array($user_role, $allowed_roles)) {
    $_SESSION['error'] = "You don't have permission to order lab tests. Your role: " . $user_role;
    header("Location: lab_orders.php");
    exit();
}

// ... rest of your existing process_lab_order.php code ...

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['patient_id'])) {
    try {
        $pdo->beginTransaction();
        
        // Validate patient exists and is active
        $patient_stmt = $pdo->prepare("SELECT id, first_name, last_name FROM patients WHERE id = ? AND status = 'Active'");
        $patient_stmt->execute([$_POST['patient_id']]);
        $patient = $patient_stmt->fetch();
        
        if (!$patient) {
            throw new Exception("Patient not found or inactive.");
        }
        
        // Validate that tests were selected
        if (!isset($_POST['tests']) || !is_array($_POST['tests']) || count($_POST['tests']) === 0) {
            throw new Exception("Please select at least one test.");
        }
        
        // Calculate total price and get service details
        $total_price = 0;
        $placeholders = str_repeat('?,', count($_POST['tests']) - 1) . '?';
        $service_stmt = $pdo->prepare("SELECT id, name, price, category, description FROM services WHERE id IN ($placeholders) AND is_active = 1");
        $service_stmt->execute($_POST['tests']);
        $selected_services = $service_stmt->fetchAll();
        
        if (count($selected_services) !== count($_POST['tests'])) {
            throw new Exception("Some selected services are not available or inactive.");
        }
        
        foreach ($selected_services as $service) {
            $total_price += $service['price'];
        }
        
        // Generate order number
        $order_number = 'LO-' . date('YmdHis') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        // Create lab order with total_amount and payment_status
        $order_stmt = $pdo->prepare("
            INSERT INTO lab_orders 
            (order_number, patient_id, ordered_by, priority, clinical_notes, total_amount, payment_status, status, order_date) 
            VALUES (?, ?, ?, ?, ?, ?, 'unpaid', 'ordered', NOW())
        ");
        $order_stmt->execute([
            $order_number,
            $_POST['patient_id'],
            $_SESSION['user_id'],
            $_POST['priority'],
            $_POST['clinical_notes'] ?? '',
            $total_price
        ]);
        
        $lab_order_id = $pdo->lastInsertId();
        
        // Add selected services to lab_order_items
        $item_stmt = $pdo->prepare("
            INSERT INTO lab_order_items 
            (lab_order_id, test_name, test_code, specimen_type, instructions, status) 
            VALUES (?, ?, ?, ?, ?, 'ordered')
        ");
        
        foreach ($selected_services as $service) {
            // Use service name as test name, and generate a simple code from the name
            $test_code = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $service['name']), 0, 8));
            $specimen_type = $service['category'] . ' specimen';
            $instructions = $service['description'] ?? 'Standard laboratory procedure';
            
            $item_stmt->execute([
                $lab_order_id,
                $service['name'],
                $test_code,
                $specimen_type,
                $instructions
            ]);
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = "Lab order #$order_number created successfully for " . $patient['first_name'] . " " . $patient['last_name'] . "! Total amount: KSH " . number_format($total_price, 2);
        header("Location: lab_orders.php");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error creating lab order: " . $e->getMessage();
        header("Location: lab_orders.php");
        exit();
    }
} else {
    $_SESSION['error'] = "Invalid request.";
    header("Location: lab_orders.php");
    exit();
}
?>