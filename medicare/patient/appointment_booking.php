<?php
session_start();
include("connection.php");

// Check if patient is logged in
if($_SESSION["usertype"] != "p") {
    header("location: login.php");
    exit();
}

if($_POST) {
    $patient_id = $_SESSION['patient_id'];
    $doctor_id = $_POST['doctor_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $appointment_type = $_POST['appointment_type'];
    $reason = $_POST['reason'];
    
    // Check availability
    $check_sql = "SELECT COUNT(*) as count FROM appointments 
                  WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? 
                  AND status IN ('scheduled', 'confirmed')";
    $stmt = $database->prepare($check_sql);
    $stmt->bind_param("iss", $doctor_id, $appointment_date, $appointment_time);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if($row['count'] < 3) { // Max 3 appointments per slot
        $insert_sql = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, 
                        appointment_time, appointment_type, reason, created_by) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $database->prepare($insert_sql);
        $stmt->bind_param("iissssi", $patient_id, $doctor_id, $appointment_date, 
                         $appointment_time, $appointment_type, $reason, $patient_id);
        
        if($stmt->execute()) {
            $success = "Appointment booked successfully!";
        } else {
            $error = "Error booking appointment. Please try again.";
        }
    } else {
        $error = "This time slot is fully booked. Please choose another time.";
    }
}

// Get available doctors
$doctors_sql = "SELECT u.id, u.first_name, u.last_name, u.specialization 
                FROM users u 
                WHERE u.role = 'Doctor' AND u.status = 'active'";
$doctors_result = $database->query($doctors_sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Book Appointment</title>
    <style>
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Book Appointment</h2>
        
        <?php if(isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Select Doctor:</label>
                <select name="doctor_id" required>
                    <option value="">Choose Doctor</option>
                    <?php while($doctor = $doctors_result->fetch_assoc()): ?>
                        <option value="<?php echo $doctor['id']; ?>">
                            Dr. <?php echo $doctor['first_name'] . ' ' . $doctor['last_name']; ?> 
                            (<?php echo $doctor['specialization']; ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Appointment Date:</label>
                <input type="date" name="appointment_date" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Preferred Time:</label>
                <select name="appointment_time" required>
                    <option value="09:00:00">9:00 AM</option>
                    <option value="09:30:00">9:30 AM</option>
                    <option value="10:00:00">10:00 AM</option>
                    <!-- Add more time slots -->
                </select>
            </div>
            
            <div class="form-group">
                <label>Appointment Type:</label>
                <select name="appointment_type" required>
                    <option value="in_person">In-Person</option>
                    <option value="online">Online Consultation</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Reason for Visit:</label>
                <textarea name="reason" rows="3" required></textarea>
            </div>
            
            <button type="submit" class="btn">Book Appointment</button>
        </form>
    </div>
</body>
</html>