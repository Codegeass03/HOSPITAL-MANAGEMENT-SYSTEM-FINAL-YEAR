<?php
// update_appointment.php
session_start();
include "includes/db_connect.php";

if (!isset($pdo)) {
    $_SESSION['error'] = "Database connection failed.";
    header("Location: appointment_scheduling.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $id = intval($_POST['id']);
        $patient_id = intval($_POST['patient_id']);
        $doctor_id = intval($_POST['doctor_id']);
        $appointment_date = trim($_POST['appointment_date']);
        $appointment_time = trim($_POST['appointment_time']);
        $appointment_type = trim($_POST['appointment_type']);
        $duration = intval($_POST['duration']);
        $reason = trim($_POST['reason']);
        $notes = trim($_POST['notes']);
        $status = trim($_POST['status']);
        
        // Validate inputs
        $errors = [];
        
        if (empty($id)) {
            $errors[] = "Invalid appointment ID";
        }
        
        if (empty($patient_id) || empty($doctor_id)) {
            $errors[] = "Patient and Doctor are required";
        }
        
        if (empty($appointment_date) || empty($appointment_time)) {
            $errors[] = "Date and time are required";
        }
        
        if (!empty($errors)) {
            $_SESSION['error'] = implode(", ", $errors);
            header("Location: edit_appointment.php?id=" . $id);
            exit();
        }
        
        // Update appointment
        $stmt = $pdo->prepare("
            UPDATE appointments SET
                patient_id = ?,
                doctor_id = ?,
                appointment_date = ?,
                appointment_time = ?,
                duration = ?,
                appointment_type = ?,
                reason = ?,
                notes = ?,
                status = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $patient_id,
            $doctor_id,
            $appointment_date,
            $appointment_time,
            $duration,
            $appointment_type,
            $reason,
            $notes,
            $status,
            $id
        ]);
        
        if ($result) {
            $_SESSION['success'] = "Appointment updated successfully!";
            error_log("Appointment updated: ID {$id}");
        } else {
            $_SESSION['error'] = "Failed to update appointment.";
        }
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        error_log("Error updating appointment: " . $e->getMessage());
    }
    
    header("Location: appointment_scheduling.php");
    exit();
}

// If not POST, redirect
header("Location: appointment_scheduling.php");
exit();
?>