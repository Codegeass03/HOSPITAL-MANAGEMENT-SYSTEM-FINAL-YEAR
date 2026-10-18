<?php
session_start();
if(isset($_SESSION["user"])){
    if(($_SESSION["user"])=="" or $_SESSION['usertype']!='a'){
        header("location: ../login.php");
    }
} else {
    header("location: ../login.php");
}

include("../connection.php");

// Get appointment ID from URL
$appointment_id = $_GET['id'] ?? 0;

if($appointment_id == 0){
    header("location: appointment.php?error=Invalid appointment ID");
    exit();
}

// Fetch appointment details before deletion for confirmation and logging
$sql = "SELECT a.appoid, a.apponum, a.pid, a.scheduleid, p.pname, p.pemail, s.title, d.docname, 
               s.scheduledate, s.scheduletime, s.nop
        FROM appointment a
        JOIN patient p ON a.pid = p.pid
        JOIN schedule s ON a.scheduleid = s.scheduleid
        JOIN doctor d ON s.docid = d.docid
        WHERE a.appoid = '$appointment_id'";

$result = $database->query($sql);

if($result->num_rows == 0){
    header("location: appointment.php?error=Appointment not found");
    exit();
}

$appointment = $result->fetch_assoc();

// Handle the deletion process
$database->begin_transaction();

try {
    // 1. First, get all details for potential logging or notification
    $appointment_number = $appointment['apponum'];
    $patient_name = $appointment['pname'];
    $patient_email = $appointment['pemail'];
    $session_title = $appointment['title'];
    $doctor_name = $appointment['docname'];
    $schedule_date = $appointment['scheduledate'];
    $schedule_time = $appointment['scheduletime'];
    
    // 2. Delete from the main appointment table
    $delete_sql = "DELETE FROM appointment WHERE appoid = '$appointment_id'";
    if(!$database->query($delete_sql)){
        throw new Exception("Failed to delete appointment from main table: " . $database->error);
    }
    
    // 3. Also delete from the appointments table if it exists and has matching record
    $appointments_check = $database->query("SHOW TABLES LIKE 'appointments'");
    if($appointments_check->num_rows > 0){
        // Try to find matching appointment in appointments table
        $find_appointment_sql = "SELECT appointment_id FROM appointments 
                                WHERE patient_id = '{$appointment['pid']}' 
                                AND appointment_date LIKE '{$schedule_date}%'";
        $appointment_match = $database->query($find_appointment_sql);
        
        if($appointment_match->num_rows > 0){
            $appointment_row = $appointment_match->fetch_assoc();
            $matching_appointment_id = $appointment_row['appointment_id'];
            
            $delete_appointments_sql = "DELETE FROM appointments WHERE appointment_id = '$matching_appointment_id'";
            if(!$database->query($delete_appointments_sql)){
                throw new Exception("Failed to delete from appointments table: " . $database->error);
            }
        }
    }
    
    // 4. Update any related records if needed
    // For example, you might want to update a cancellation log table here
    
    // 5. Commit the transaction
    $database->commit();
    
    // 6. Redirect with success message
    $success_message = "Appointment #{$appointment_number} for {$patient_name} has been successfully cancelled.";
    header("location: appointment.php?success=" . urlencode($success_message));
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    $database->rollback();
    
    // Log the error (you can implement proper logging here)
    error_log("Appointment cancellation failed: " . $e->getMessage());
    
    // Redirect with error message
    $error_message = "Failed to cancel appointment. Please try again. Error: " . $e->getMessage();
    header("location: appointment.php?error=" . urlencode($error_message));
    exit();
}
?>

<!-- This part will only be shown if the script doesn't redirect -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">  
    <link rel="stylesheet" href="../css/main.css">  
    <link rel="stylesheet" href="../css/admin.css">
    <title>Deleting Appointment - Smart Clinic</title>
    <style>
        .processing-container {
            background: white;
            border-radius: 10px;
            padding: 40px;
            margin: 50px auto;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primarycolor);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 2s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="menu">
            <!-- Your menu code here -->
        </div>
        
        <div class="dash-body">
            <div class="processing-container">
                <div class="loading-spinner"></div>
                <h3>Processing Cancellation...</h3>
                <p>Please wait while we cancel the appointment.</p>
                <p>You will be redirected automatically.</p>
            </div>
        </div>
    </div>
    
    <script>
        // Fallback redirect in case header redirect fails
        setTimeout(function() {
            window.location.href = 'appointment.php';
        }, 3000);
    </script>
</body>
</html>