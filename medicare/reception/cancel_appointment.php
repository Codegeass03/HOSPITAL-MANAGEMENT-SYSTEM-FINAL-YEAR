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

// Get appointment details from URL
$appointment_id = $_GET['id'] ?? 0;
$appointment_number = $_GET['apponum'] ?? '';
$patient_name = $_GET['patient'] ?? '';

if($appointment_id == 0){
    header("location: appointment.php");
    exit();
}

// Handle cancellation confirmation
if($_POST && isset($_POST['confirm_cancel'])){
    $cancellation_reason = $_POST['cancellation_reason'] ?? 'No reason provided';
    
    // Start transaction
    $database->begin_transaction();
    
    try {
        // Get appointment details before deletion for logging
        $appointment_sql = "SELECT a.*, p.pname, p.pemail, s.title, d.docname 
                           FROM appointment a
                           JOIN patient p ON a.pid = p.pid
                           JOIN schedule s ON a.scheduleid = s.scheduleid
                           JOIN doctor d ON s.docid = d.docid
                           WHERE a.appoid = '$appointment_id'";
        $appointment_result = $database->query($appointment_sql);
        $appointment_data = $appointment_result->fetch_assoc();
        
        // Delete from main appointment table
        $delete_sql = "DELETE FROM appointment WHERE appoid = '$appointment_id'";
        $database->query($delete_sql);
        
        // Also delete from appointments table if it exists
        $appointments_check = $database->query("SHOW TABLES LIKE 'appointments'");
        if($appointments_check->num_rows > 0){
            $delete_appointments_sql = "DELETE FROM appointments WHERE appointment_id = '$appointment_id'";
            $database->query($delete_appointments_sql);
        }
        
        // Log the cancellation (you might want to create a cancellations table for this)
        // For now, we'll just commit the transaction
        
        $database->commit();
        
        // Redirect with success message
        header("location: appointment.php?action=cancelled&apponum=" . $appointment_data['apponum'] . "&patient=" . urlencode($appointment_data['pname']));
        exit();
        
    } catch (Exception $e) {
        $database->rollback();
        $error = "Error cancelling appointment: " . $e->getMessage();
    }
}

// If not confirmed, show confirmation page
if(!isset($_POST['confirm_cancel'])){
    // Fetch appointment details for confirmation
    $sql = "SELECT a.appoid, a.apponum, p.pname, s.title, d.docname, s.scheduledate, s.scheduletime
            FROM appointment a
            JOIN patient p ON a.pid = p.pid
            JOIN schedule s ON a.scheduleid = s.scheduleid
            JOIN doctor d ON s.docid = d.docid
            WHERE a.appoid = '$appointment_id'";
    
    $result = $database->query($sql);
    
    if($result->num_rows == 0){
        header("location: appointment.php");
        exit();
    }
    
    $appointment = $result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">  
    <link rel="stylesheet" href="../css/main.css">  
    <link rel="stylesheet" href="../css/admin.css">
    <title>Cancel Appointment - Smart Clinic</title>
    <style>
        .confirmation-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin: 20px auto;
            max-width: 600px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        .warning-icon {
            font-size: 48px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        .appointment-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }
        .detail-item {
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .detail-item:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #333;
        }
        .form-group {
            margin: 20px 0;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            resize: vertical;
            min-height: 80px;
        }
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 25px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="menu">
            <table class="menu-container" border="0">
                <tr>
                    <td style="padding:10px" colspan="2">
                        <table border="0" class="profile-container">
                            <tr>
                                <td width="30%" style="padding-left:20px">
                                    <img src="../img/user.png" alt="" width="100%" style="border-radius:50%">
                                </td>
                                <td style="padding:0px;margin:0px;">
                                    <p class="profile-title">Administrator</p>
                                    <p class="profile-subtitle">admin@edoc.com</p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <a href="../logout.php"><input type="button" value="Log out" class="logout-btn btn-primary-soft btn"></a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-dashbord">
                        <a href="index.php" class="non-style-link-menu"><div><p class="menu-text">Dashboard</p></a></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appoinment menu-active menu-icon-appoinment-active">
                        <a href="appointment.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">Appointment</p></a></div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="dash-body">
            <table border="0" width="100%" style="border-spacing: 0; margin:0; padding:0; margin-top:25px;">
                <tr>
                    <td width="13%">
                        <a href="appointment.php"><button class="login-btn btn-primary-soft btn btn-icon-back" style="padding-top:11px;padding-bottom:11px;margin-left:20px;width:125px">
                            <font class="tn-in-text">Back</font>
                        </button></a>
                    </td>
                    <td>
                        <p style="font-size: 23px;padding-left:12px;font-weight: 600;">Cancel Appointment</p>
                    </td>
                </tr>
                
                <tr>
                    <td colspan="2">
                        <div class="confirmation-container">
                            <?php if(isset($error)): ?>
                                <div class="alert alert-error" style="margin-bottom: 20px;">
                                    <?php echo $error; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="warning-icon">⚠️</div>
                            <h2 style="color: #dc3545; margin-bottom: 15px;">Confirm Cancellation</h2>
                            <p style="color: #666; margin-bottom: 25px;">
                                You are about to cancel the following appointment. This action cannot be undone.
                            </p>
                            
                            <div class="appointment-details">
                                <div class="detail-item">
                                    <span class="detail-label">Appointment Number:</span>
                                    <span>#<?php echo $appointment['apponum']; ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Patient Name:</span>
                                    <span><?php echo htmlspecialchars($appointment['pname']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Session:</span>
                                    <span><?php echo htmlspecialchars($appointment['title']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Doctor:</span>
                                    <span>Dr. <?php echo htmlspecialchars($appointment['docname']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Scheduled Date & Time:</span>
                                    <span>
                                        <?php echo date('F j, Y', strtotime($appointment['scheduledate'])); ?> at 
                                        <?php echo date('g:i A', strtotime($appointment['scheduletime'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <form method="POST" action="">
                                <div class="form-group">
                                    <label for="cancellation_reason">Reason for Cancellation (Optional):</label>
                                    <textarea id="cancellation_reason" name="cancellation_reason" 
                                              placeholder="Please provide a reason for cancellation..."></textarea>
                                </div>
                                
                                <div class="action-buttons">
                                    <a href="view-appointment.php?id=<?php echo $appointment_id; ?>" class="non-style-link">
                                        <button type="button" class="login-btn btn-primary-soft btn">Go Back</button>
                                    </a>
                                    <button type="submit" name="confirm_cancel" class="login-btn btn-primary btn" 
                                            style="background: #dc3545; color: white;">
                                        Confirm Cancellation
                                    </button>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>