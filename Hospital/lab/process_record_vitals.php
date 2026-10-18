<?php
session_start();
include "includes/db_connect.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $appointment_id = $_POST['appointment_id'] ?? ''; // Changed from 'id' to 'appointment_id'
    $blood_pressure_systolic = $_POST['blood_pressure_systolic'] ?? null;
    $blood_pressure_diastolic = $_POST['blood_pressure_diastolic'] ?? null;
    $heart_rate = $_POST['heart_rate'] ?? '';
    $temperature = $_POST['temperature'] ?? '';
    $respiratory_rate = $_POST['respiratory_rate'] ?? '';
    $spo2 = $_POST['spo2'] ?? '';
    $weight = $_POST['weight'] ?? '';
    $height = $_POST['height'] ?? '';
    $pain_level = $_POST['pain_level'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    try {
        // First, get the patient_id from the appointment
        $patient_stmt = $pdo->prepare("SELECT patient_id FROM appointments WHERE id = ?");
        $patient_stmt->execute([$appointment_id]);
        $appointment = $patient_stmt->fetch();
        
        if (!$appointment) {
            throw new Exception("Appointment not found");
        }
        
        $patient_id = $appointment['patient_id'];
        $recorded_by = $_SESSION['user_id'] ?? null;
        
        // Calculate BMI if weight and height are provided
        $bmi = null;
        if ($weight && $height) {
            $height_m = $height / 100; // convert to meters
            $bmi = $weight / ($height_m * $height_m);
            $bmi = round($bmi, 2);
        }
        
        // Insert vitals record - CORRECTED VERSION
        $stmt = $pdo->prepare("
            INSERT INTO patient_vitals 
            (patient_id, blood_pressure_systolic, blood_pressure_diastolic, heart_rate, 
             temperature, respiratory_rate, oxygen_saturation, weight, height, bmi, 
             recorded_by, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $patient_id,                    // patient_id
            $blood_pressure_systolic,       // blood_pressure_systolic
            $blood_pressure_diastolic,      // blood_pressure_diastolic
            $heart_rate,                    // heart_rate
            $temperature,                   // temperature
            $respiratory_rate,              // respiratory_rate
            $spo2,                          // oxygen_saturation (maps to spo2 from form)
            $weight,                        // weight
            $height,                        // height
            $bmi,                           // bmi
            $recorded_by,                   // recorded_by
            $notes                          // notes
        ]);
        
        $_SESSION['success'] = "Patient vitals recorded successfully.";
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error recording vitals: " . $e->getMessage();
        error_log("Vitals recording error: " . $e->getMessage());
    }
    
    header("Location: appointment_scheduling.php");
    exit();
}
?>