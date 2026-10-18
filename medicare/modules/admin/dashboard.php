<?php
session_start();

// Ensure user is logged in and is an admin
if (!isset($_SESSION["user"]) || $_SESSION["role"] != "a") {
    header("Location: ../index.php");
    exit();
}

include("../connection.php");

$useremail = $_SESSION["user"];
$username = $_SESSION["username"];

date_default_timezone_set('Africa/Nairobi');
$today = date('Y-m-d');

// Fetch data counts with error handling
try {
    $doctorCount = $database->query("SELECT COUNT(*) AS count FROM doctor")->fetch_assoc()['count'];
    $patientCount = $database->query("SELECT COUNT(*) AS count FROM patient")->fetch_assoc()['count'];
    $appointmentCount = $database->query("SELECT COUNT(*) AS count FROM appointment WHERE appodate >= '$today'")->fetch_assoc()['count'];
    $scheduleCount = $database->query("SELECT COUNT(*) AS count FROM schedule WHERE scheduledate = '$today'")->fetch_assoc()['count'];
    
    // Additional stats for better insights
    $totalAppointments = $database->query("SELECT COUNT(*) AS count FROM appointment")->fetch_assoc()['count'];
    $pendingAppointments = $database->query("SELECT COUNT(*) AS count FROM appointment WHERE status = 'pending'")->fetch_assoc()['count'];
    
} catch (Exception $e) {
    // Handle errors gracefully
    error_log("Dashboard error: " . $e->getMessage());
    $doctorCount = $patientCount = $appointmentCount = $scheduleCount = $totalAppointments = $pendingAppointments = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Clinic Management System</title>
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .dashboard-tables, .admin-header {
            animation: transitionIn-Y-over 0.5s;
        }
        .filter-container {
            animation: transitionIn-Y-bottom 0.5s;
        }
        .sub-table {
            animation: transitionIn-Y-bottom 0.5s;
        }
        
        /* Enhanced Dashboard Styles */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #007bff;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card i {
            font-size: 2.5em;
            margin-bottom: 15px;
            color: #007bff;
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 1.1em;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 30px 0;
        }
        
        .action-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .recent-activity {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 30px;
        }
        
        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e3f2fd;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .card-doctor { border-left-color: #e74c3c; }
        .card-patient { border-left-color: #2ecc71; }
        .card-appointment { border-left-color: #3498db; }
        .card-schedule { border-left-color: #f39c12; }
        .card-total { border-left-color: #9b59b6; }
        .card-pending { border-left-color: #e67e22; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="menu">
            <table class="menu-container" border="0">
                <tr>
                    <td style="padding:10px" colspan="2">
                        <table border="0" class="profile-container">
                            <tr>
                                <td width="30%" style="padding-left:20px">
                                    <img src="../img/user.png" width="100%" style="border-radius:50%">
                                </td>
                                <td style="padding:0px;margin:0px;">
                                    <p class="profile-title"><?php echo htmlspecialchars(substr($username, 0, 13)); ?>..</p>
                                    <p class="profile-subtitle"><?php echo htmlspecialchars(substr($useremail, 0, 22)); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <a href="../logout.php">
                                        <input type="button" value="Log out" class="logout-btn btn-primary-soft btn">
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- Sidebar Menu Items -->
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-dashbord menu-active menu-icon-dashbord-active">
                        <a href="dashboard.php" class="non-style-link-menu non-style-link-menu-active">
                            <div><p class="menu-text">Dashboard</p></div>
                        </a>
                    </td>
                </tr>

                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor">
                        <a href="doctors.php" class="non-style-link-menu">
                            <div><p class="menu-text">Doctors</p></div>
                        </a>
                    </td>
                </tr>

                <tr class="menu-row">
                    <td class="menu-btn menu-icon-patient">
                        <a href="patients.php" class="non-style-link-menu">
                            <div><p class="menu-text">Patients</p></div>
                        </a>
                    </td>
                </tr>

                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appoinment">
                        <a href="appointments.php" class="non-style-link-menu">
                            <div><p class="menu-text">Appointments</p></div>
                        </a>
                    </td>
                </tr>

                <tr class="menu-row">
                    <td class="menu-btn menu-icon-schedule">
                        <a href="schedules.php" class="non-style-link-menu">
                            <div><p class="menu-text">Schedules</p></div>
                        </a>
                    </td>
                </tr>

                <tr class="menu-row">
                    <td class="menu-btn menu-icon-settings">
                        <a href="settings.php" class="non-style-link-menu">
                            <div><p class="menu-text">Settings</p></div>
                        </a>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Dashboard Content -->
        <div class="dash-body" style="margin-top: 15px">
            <table border="0" width="100%" style="border-spacing: 0;">
                <tr>
                    <td colspan="1" class="nav-bar">
                        <p style="font-size: 23px; padding-left:12px; font-weight:600; margin-left:20px;">
                            <i class="fas fa-tachometer-alt"></i> Admin Dashboard
                        </p>
                    </td>
                    <td width="25%"></td>
                    <td width="15%">
                        <p style="font-size:14px; color:gray; text-align:right;">Today's Date</p>
                        <p class="heading-sub12"><?php echo date('F j, Y'); ?></p>
                    </td>
                    <td width="10%">
                        <button class="btn-label" style="display:flex; justify-content:center; align-items:center;">
                            <img src="../img/calendar.svg" width="100%">
                        </button>
                    </td>
                </tr>

                <tr>
                    <td colspan="4">
                        <center>
                            <table class="filter-container admin-header" border="0" style="width:95%; border:none;">
                                <tr>
                                    <td>
                                        <h3>Welcome Back,</h3>
                                        <h1><?php echo htmlspecialchars($username); ?>! 👋</h1>
                                        <p>Manage your clinic efficiently with real-time insights and quick access to all features.</p>
                                        <a href="appointments.php" class="non-style-link">
                                            <button class="btn-primary btn" style="width:30%">
                                                <i class="fas fa-calendar-check"></i> Manage Appointments
                                            </button>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </center>
                    </td>
                </tr>

                <!-- Enhanced Dashboard Stats -->
                <tr>
                    <td colspan="4">
                        <center>
                            <div style="width:95%;">
                                <div class="stats-grid">
                                    <div class="stat-card card-doctor">
                                        <i class="fas fa-user-md"></i>
                                        <div class="stat-number"><?php echo $doctorCount; ?></div>
                                        <div class="stat-label">Registered Doctors</div>
                                    </div>
                                    
                                    <div class="stat-card card-patient">
                                        <i class="fas fa-users"></i>
                                        <div class="stat-number"><?php echo $patientCount; ?></div>
                                        <div class="stat-label">Registered Patients</div>
                                    </div>
                                    
                                    <div class="stat-card card-appointment">
                                        <i class="fas fa-calendar-alt"></i>
                                        <div class="stat-number"><?php echo $appointmentCount; ?></div>
                                        <div class="stat-label">Upcoming Appointments</div>
                                    </div>
                                    
                                    <div class="stat-card card-schedule">
                                        <i class="fas fa-clock"></i>
                                        <div class="stat-number"><?php echo $scheduleCount; ?></div>
                                        <div class="stat-label">Today's Schedules</div>
                                    </div>
                                    
                                    <div class="stat-card card-total">
                                        <i class="fas fa-list-alt"></i>
                                        <div class="stat-number"><?php echo $totalAppointments; ?></div>
                                        <div class="stat-label">Total Appointments</div>
                                    </div>
                                    
                                    <div class="stat-card card-pending">
                                        <i class="fas fa-hourglass-half"></i>
                                        <div class="stat-number"><?php echo $pendingAppointments; ?></div>
                                        <div class="stat-label">Pending Appointments</div>
                                    </div>
                                </div>

                                <!-- Quick Actions -->
                                <div class="quick-actions">
                                    <a href="doctors.php" class="action-btn">
                                        <i class="fas fa-user-md fa-2x"></i>
                                        <div>Manage Doctors</div>
                                    </a>
                                    <a href="patients.php" class="action-btn">
                                        <i class="fas fa-users fa-2x"></i>
                                        <div>Manage Patients</div>
                                    </a>
                                    <a href="appointments.php" class="action-btn">
                                        <i class="fas fa-calendar-plus fa-2x"></i>
                                        <div>Book Appointment</div>
                                    </a>
                                    <a href="schedules.php" class="action-btn">
                                        <i class="fas fa-calendar-day fa-2x"></i>
                                        <div>View Schedules</div>
                                    </a>
                                </div>

                                <!-- Recent Activity Section -->
                                <div class="recent-activity">
                                    <h3 style="margin-bottom: 20px; color: #2c3e50;">
                                        <i class="fas fa-history"></i> Recent Activity
                                    </h3>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-user-md text-primary"></i>
                                        </div>
                                        <div>
                                            <strong>Doctor Management</strong>
                                            <p>You have <?php echo $doctorCount; ?> doctors registered in the system</p>
                                        </div>
                                    </div>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-users text-success"></i>
                                        </div>
                                        <div>
                                            <strong>Patient Records</strong>
                                            <p>Total of <?php echo $patientCount; ?> patients in the database</p>
                                        </div>
                                    </div>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-calendar-check text-info"></i>
                                        </div>
                                        <div>
                                            <strong>Appointments Today</strong>
                                            <p><?php echo $appointmentCount; ?> appointments scheduled for upcoming dates</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </center>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <script>
        // Simple animation for stats counter (optional)
        document.addEventListener('DOMContentLoaded', function() {
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const target = parseInt(stat.textContent);
                let current = 0;
                const increment = target / 50;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        stat.textContent = target;
                        clearInterval(timer);
                    } else {
                        stat.textContent = Math.floor(current);
                    }
                }, 30);
            });
        });
    </script>
</body>
</html>