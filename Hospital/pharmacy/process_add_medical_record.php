<?php
session_start();
include "includes/db_connect.php";

if (!isset($pdo)) {
    $_SESSION['error'] = "Database connection failed.";
    header("Location: ehr_system.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Get and sanitize form inputs
        $patient_id = intval($_POST['patient_id']);
        $record_type = trim($_POST['record_type']);
        $doctor_id = intval($_POST['doctor_id']);
        $department = trim($_POST['department']);
        $record_date = trim($_POST['record_date']);
        $diagnosis = trim($_POST['diagnosis']);
        $treatment = trim($_POST['treatment']);
        $notes = trim($_POST['notes']);
        $priority = isset($_POST['priority']) ? trim($_POST['priority']) : 'normal';
        $created_by = $_SESSION['user_id'] ?? 1; // Replace with actual logged-in user ID
        
        // Validation
        $errors = [];
        
        if (empty($patient_id)) {
            $errors[] = "Patient is required";
        }
        
        if (empty($record_type)) {
            $errors[] = "Record type is required";
        }
        
        if (empty($doctor_id)) {
            $errors[] = "Doctor is required";
        }
        
        if (empty($record_date)) {
            $errors[] = "Record date is required";
        }
        
        if (empty($diagnosis)) {
            $errors[] = "Diagnosis is required";
        }
        
        // Verify patient exists
        $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch();
        
        if (!$patient) {
            $errors[] = "Selected patient does not exist";
        }
        
        // Verify doctor exists
        $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE id = ? AND role = 'Doctor'");
        $stmt->execute([$doctor_id]);
        $doctor = $stmt->fetch();
        
        if (!$doctor) {
            $errors[] = "Selected doctor does not exist";
        }
        
        if (!empty($errors)) {
            $_SESSION['error'] = implode(", ", $errors);
            header("Location: add_medical_record.php");
            exit();
        }
        
        // Generate record number
        $record_number = 'EHR' . date('Ym') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
        
        // Insert medical record
        $stmt = $pdo->prepare("
            INSERT INTO medical_records (
                record_number,
                patient_id,
                doctor_id,
                record_type,
                record_date,
                department,
                diagnosis,
                treatment,
                notes,
                status,
                priority,
                created_by,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $record_number,
            $patient_id,
            $doctor_id,
            $record_type,
            $record_date,
            $department,
            $diagnosis,
            $treatment,
            $notes,
            $priority,
            $created_by
        ]);
        
        if ($result) {
            $record_id = $pdo->lastInsertId();
            
            // Log the access
            $log_stmt = $pdo->prepare("
                INSERT INTO medical_record_access_log (record_id, patient_id, accessed_by, access_type, ip_address)
                VALUES (?, ?, ?, 'create', ?)
            ");
            $log_stmt->execute([$record_id, $patient_id, $created_by, $_SERVER['REMOTE_ADDR']]);
            
            $_SESSION['success'] = "Medical record created successfully! Record Number: {$record_number}";
            
            error_log("New medical record created: ID {$record_id}, Patient: {$patient['first_name']} {$patient['last_name']}, Type: {$record_type}");
        } else {
            $_SESSION['error'] = "Failed to create medical record. Please try again.";
            error_log("Failed to insert medical record");
        }
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        error_log("Database error in process_add_medical_record.php: " . $e->getMessage());
    } catch (Exception $e) {
        $_SESSION['error'] = "An error occurred: " . $e->getMessage();
        error_log("General error in process_add_medical_record.php: " . $e->getMessage());
    }
} else {
    $_SESSION['error'] = "Invalid request method.";
}

header("Location: ehr_system.php");
exit();
?>