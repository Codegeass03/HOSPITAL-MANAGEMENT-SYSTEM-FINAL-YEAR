<?php
session_start();

// Debug: Check if session is working
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include connection with absolute path
$connection_path = $_SERVER['DOCUMENT_ROOT'] . '/Medicare/connection.php';
if (file_exists($connection_path)) {
    include($connection_path);
} else {
    // Try relative path
    include('../connection.php');
}

// Check if database connection was established
if (!isset($database) || $database->connect_error) {
    die("Database connection failed. Please check connection.php file.");
}

// Check if doctor is logged in
if(!isset($_SESSION["usertype"]) || $_SESSION["usertype"] != "d") {
    header("location: ../login.php");
    exit();
}

// Check if doctor_id is set in session
if(!isset($_SESSION['doctor_id'])) {
    // Try to get doctor ID from database
    if(isset($_SESSION['user'])) {
        $email = $_SESSION['user'];
        $sql = "SELECT docid FROM doctor WHERE docemail = ?";
        $stmt = $database->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if($result->num_rows == 1) {
            $doctor_data = $result->fetch_assoc();
            $_SESSION['doctor_id'] = $doctor_data['docid'];
        }
    }
    
    if(!isset($_SESSION['doctor_id'])) {
        header("location: ../login.php");
        exit();
    }
}

$doctor_id = $_SESSION['doctor_id'];

// Get today's appointments using your existing database structure
$today = date('Y-m-d');

try {
    // First, let's check what tables exist in your database
    $appointments_sql = "SELECT 
                        a.appoid as id,
                        p.pname as patient_name,
                        p.ptel as phone,
                        s.title as appointment_type,
                        s.scheduledate as appointment_date,
                        s.scheduletime as appointment_time,
                        'scheduled' as status
                        FROM appointment a 
                        JOIN patient p ON a.pid = p.pid 
                        JOIN schedule s ON a.scheduleid = s.scheduleid 
                        WHERE s.docid = ? AND s.scheduledate = ? 
                        ORDER BY s.scheduletime";
    
    $stmt = $database->prepare($appointments_sql);
    $stmt->bind_param("is", $doctor_id, $today);
    $stmt->execute();
    $appointments = $stmt->get_result();
    
} catch (Exception $e) {
    // If the query fails, create an empty result set
    $appointments = false;
    $error_message = "No appointments found or table structure differs.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Doctor Dashboard - Smart Clinic</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background-color: #f5f5f5; 
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        .dashboard { 
            display: grid; 
            grid-template-columns: 2fr 1fr; 
            gap: 30px; 
            margin-top: 20px; 
        }
        .card { 
            background: white; 
            padding: 25px; 
            border-radius: 8px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            border-left: 4px solid #007bff; 
        }
        .appointment-list { 
            list-style: none; 
            padding: 0; 
            margin: 0; 
        }
        .appointment-item { 
            padding: 15px; 
            border-bottom: 1px solid #eee; 
            margin-bottom: 10px; 
            background: #f9f9f9; 
            border-radius: 5px; 
        }
        .appointment-item:last-child { 
            border-bottom: none; 
        }
        .status-badge { 
            padding: 4px 12px; 
            border-radius: 15px; 
            font-size: 12px; 
            font-weight: bold; 
            margin-left: 10px; 
        }
        .scheduled { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .confirmed { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        
        .quick-actions { 
            list-style: none; 
            padding: 0; 
        }
        .quick-actions li { 
            margin-bottom: 10px; 
        }
        .quick-actions a { 
            display: block; 
            padding: 12px 15px; 
            background: #007bff; 
            color: white; 
            text-decoration: none; 
            border-radius: 5px; 
            transition: background 0.3s; 
        }
        .quick-actions a:hover { 
            background: #0056b3; 
        }
        
        .welcome-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .no-appointments {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        
        .debug-info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="welcome-header">
            <h1>Doctor Dashboard</h1>
            <p>Welcome, Dr. <?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'User'; ?></p>
            <p>Today: <?php echo date('F j, Y'); ?> | Doctor ID: <?php echo $doctor_id; ?></p>
        </div>
        
        <!-- Debug Information -->
        <div class="debug-info">
            <strong>Session Info:</strong> 
            User: <?php echo isset($_SESSION['user']) ? $_SESSION['user'] : 'Not set'; ?> | 
            UserType: <?php echo isset($_SESSION['usertype']) ? $_SESSION['usertype'] : 'Not set'; ?>
        </div>
        
        <?php if(isset($error_message)): ?>
            <div class="error-message">
                <strong>Note:</strong> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="dashboard">
            <div class="card">
                <h3>Today's Appointments</h3>
                <?php if($appointments && $appointments->num_rows > 0): ?>
                    <ul class="appointment-list">
                        <?php while($apt = $appointments->fetch_assoc()): ?>
                            <li class="appointment-item">
                                <strong><?php echo htmlspecialchars($apt['patient_name']); ?></strong>
                                <span class="status-badge scheduled">
                                    <?php echo ucfirst($apt['status']); ?>
                                </span>
                                <br>
                                <strong>Time:</strong> <?php echo date('h:i A', strtotime($apt['appointment_time'])); ?>
                                <br>
                                <strong>Phone:</strong> <?php echo htmlspecialchars($apt['phone']); ?>
                                <br>
                                <?php if(isset($apt['appointment_type'])): ?>
                                    <strong>Type:</strong> <?php echo htmlspecialchars($apt['appointment_type']); ?>
                                <?php endif; ?>
                                <br><br>
                                <a href="medical-record.php?appointment_id=<?php echo $apt['id']; ?>" style="background: #28a745; padding: 8px 15px; border-radius: 4px; color: white; text-decoration: none;">
                                    View/Add Medical Record
                                </a>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <div class="no-appointments">
                        <p>No appointments scheduled for today.</p>
                        <p>You can manage your schedule and view upcoming appointments.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h3>Quick Actions</h3>
                <ul class="quick-actions">
                    <li><a href="my-schedule.php">📅 Manage Schedule</a></li>
                    <li><a href="patient-records.php">📋 Patient Records</a></li>
                    <li><a href="e-prescription.php">💊 E-Prescription</a></li>
                    <li><a href="appointment-requests.php">🔔 Appointment Requests</a></li>
                    <li><a href="profile-settings.php">⚙️ Profile Settings</a></li>
                    <li><a href="../logout.php" style="background: #dc3545;">🚪 Logout</a></li>
                </ul>
                
                <div style="margin-top: 30px; padding: 15px; background: #e9ecef; border-radius: 5px;">
                    <h4>Quick Stats</h4>
                    <p><strong>Today's Appointments:</strong> <?php echo ($appointments && $appointments->num_rows) ? $appointments->num_rows : 0; ?></p>
                    <p><strong>Total Patients:</strong> Coming soon</p>
                    <p><strong>Pending Tasks:</strong> Coming soon</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>