<?php
// cancel_appointment.php
session_start();
include "includes/db_connect.php";

if (!isset($pdo)) {
    $_SESSION['error'] = "Database connection failed.";
    header("Location: appointment_scheduling.php");
    exit();
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No appointment ID provided.";
    header("Location: appointment_scheduling.php");
    exit();
}

$appointment_id = intval($_GET['id']);

try {
    // Fetch appointment details
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
            CONCAT(d.first_name, ' ', d.last_name) AS doctor_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users d ON a.doctor_id = d.id
        WHERE a.id = ?
    ");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        $_SESSION['error'] = "Appointment not found.";
        header("Location: appointment_scheduling.php");
        exit();
    }
    
    // Check if appointment can be cancelled
    if ($appointment['status'] == 'completed' || $appointment['status'] == 'cancelled') {
        $_SESSION['error'] = "This appointment cannot be cancelled.";
        header("Location: appointment_scheduling.php");
        exit();
    }
    
    // Update appointment status to cancelled
    $cancelled_by = $_SESSION['user_id'] ?? 1; // Replace with actual logged-in user ID
    
    $stmt = $pdo->prepare("
        UPDATE appointments 
        SET status = 'cancelled',
            cancelled_by = ?,
            cancellation_reason = 'Cancelled by admin',
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $result = $stmt->execute([$cancelled_by, $appointment_id]);
    
    if ($result) {
        $_SESSION['success'] = "Appointment #{$appointment['appointment_number']} has been cancelled successfully.";
        error_log("Appointment cancelled: ID {$appointment_id}, Patient: {$appointment['patient_name']}");
        
        // Optionally: Send notification to patient
        // sendCancellationNotification($appointment['patient_id'], $appointment_id);
    } else {
        $_SESSION['error'] = "Failed to cancel appointment.";
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    error_log("Error cancelling appointment: " . $e->getMessage());
}

header("Location: appointment_scheduling.php");
exit();
?>