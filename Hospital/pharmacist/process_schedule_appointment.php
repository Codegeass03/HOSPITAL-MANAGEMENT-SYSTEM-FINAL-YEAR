<?php
session_start();
include "includes/db_connect.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $patient_id = $_POST['patient_id'];
    $doctor_id = $_POST['doctor_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $appointment_type = $_POST['appointment_type'];
    $service_id = $_POST['service_id'] ?? null;
    $consultation_fee = $_POST['consultation_fee'] ?? 0;
    $duration = $_POST['duration'] ?? 30;
    $reason = $_POST['reason'];
    $notes = $_POST['notes'] ?? '';
    $created_by = $_SESSION['user_id'] ?? 55;
    
    try {
        // Generate appointment number
        $appointment_number = 'APT-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        // Insert appointment
        $stmt = $pdo->prepare("
            INSERT INTO appointments (
                patient_id, doctor_id, appointment_number, 
                appointment_date, appointment_time, appointment_type,
                service_id, consultation_fee, duration, reason, notes, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)
        ");
        
        $stmt->execute([
            $patient_id, $doctor_id, $appointment_number,
            $appointment_date, $appointment_time, $appointment_type,
            $service_id, $consultation_fee, $duration, $reason, $notes, $created_by
        ]);
        
        $appointment_id = $pdo->lastInsertId();
        
        $_SESSION['success'] = "Appointment scheduled successfully!";
        
        // Automatically create billing record if consultation fee > 0
        if ($consultation_fee > 0 && $service_id) {
            createConsultationBill($pdo, $patient_id, $appointment_id, $service_id, $consultation_fee, $created_by);
        }
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error scheduling appointment: " . $e->getMessage();
    }
    
    header("Location: appointment_scheduling.php");
    exit();
}

function createConsultationBill($pdo, $patient_id, $appointment_id, $service_id, $fee, $created_by) {
    try {
        // Get service details
        $service_stmt = $pdo->prepare("SELECT name, category FROM services WHERE id = ?");
        $service_stmt->execute([$service_id]);
        $service = $service_stmt->fetch();
        
        if (!$service) return;
        
        // Generate bill number
        $bill_number = 'BL-' . date('YmdHis') . '-' . rand(100, 999);
        
        // Insert into billing table
        $stmt = $pdo->prepare("
            INSERT INTO billing (
                patient_id, bill_number, bill_date, total_amount, 
                paid_amount, balance_amount, status, due_date, 
                payment_method, created_by
            ) VALUES (?, ?, NOW(), ?, 0, ?, 'pending', 
                     DATE_ADD(NOW(), INTERVAL 7 DAY), 'cash', ?)
        ");
        
        $stmt->execute([$patient_id, $bill_number, $fee, $fee, $created_by]);
        $bill_id = $pdo->lastInsertId();
        
        // Insert bill item with service reference
        $stmt = $pdo->prepare("
            INSERT INTO bill_items (
                bill_id, item_description, quantity, unit_price, 
                total_amount, item_type, service_date
            ) VALUES (?, ?, 1, ?, ?, 'consultation', CURDATE())
        ");
        
        $item_description = $service['name'] . " - " . $service['category'];
        $stmt->execute([$bill_id, $item_description, $fee, $fee]);
        
        // Link appointment to bill (optional - you might want to add an appointment_id column to billing table)
        // $stmt = $pdo->prepare("UPDATE billing SET appointment_id = ? WHERE id = ?");
        // $stmt->execute([$appointment_id, $bill_id]);
        
    } catch (PDOException $e) {
        error_log("Error creating consultation bill: " . $e->getMessage());
    }
}
?>