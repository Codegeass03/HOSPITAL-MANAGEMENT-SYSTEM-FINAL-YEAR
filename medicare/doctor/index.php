<?php
// Start session at the very beginning with output buffering
ob_start();
session_start();

if(isset($_SESSION["user"])){
    if(($_SESSION["user"])=="" or $_SESSION['usertype']!='d'){
        header("location: ../login.php");
        exit();
    }else{
        $useremail=$_SESSION["user"];
    }
}else{
    header("location: ../login.php");
    exit();
}

//import database
include("../connection.php");

// Get user data
$userrow = $database->query("select * from doctor where docemail='$useremail'");
if($userrow && $userrow->num_rows > 0){
    $userfetch=$userrow->fetch_assoc();
    $userid= $userfetch["docid"];
    $username=$userfetch["docname"];
} else {
    // If no user found, redirect to login
    header("location: ../login.php");
    exit();
}

// Get today's date
date_default_timezone_set('Asia/Kolkata');
$today = date('Y-m-d');

// Get doctor-specific statistics - FIXED QUERIES
$myPatientsCount = $database->query("SELECT COUNT(DISTINCT a.pid) as count FROM appointment a INNER JOIN schedule s ON a.scheduleid = s.scheduleid WHERE s.docid='$userid'")->fetch_assoc()['count'];
$todayAppointments = $database->query("SELECT COUNT(*) as count FROM appointment a INNER JOIN schedule s ON a.scheduleid = s.scheduleid WHERE s.docid='$userid' AND a.appodate='$today'")->fetch_assoc()['count'];
$upcomingAppointments = $database->query("SELECT COUNT(*) as count FROM appointment a INNER JOIN schedule s ON a.scheduleid = s.scheduleid WHERE s.docid='$userid' AND a.appodate>='$today'")->fetch_assoc()['count'];
$todaySessions = $database->query("SELECT COUNT(*) as count FROM schedule WHERE docid='$userid' AND scheduledate='$today'")->fetch_assoc()['count'];
$totalSessions = $database->query("SELECT COUNT(*) as count FROM schedule WHERE docid='$userid'")->fetch_assoc()['count'];
$completedAppointments = $database->query("SELECT COUNT(*) as count FROM appointment a INNER JOIN schedule s ON a.scheduleid = s.scheduleid WHERE s.docid='$userid' AND a.status='Complete'")->fetch_assoc()['count'];

// End output buffering and send output
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - Smart Clinic Management System</title>
    <style>
        :root {
            --primarycolor: #0891b2;
            --primarydark: #0e7490;
            --primarylight: #06b6d4;
            --secondarycolor: #14b8a6;
            --accentcolor: #22d3ee;
            --bgcolor: #f0fdfa;
            --textcolor: #134e4a;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bgcolor);
            color: var(--textcolor);
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .menu {
            width: 280px;
            background: linear-gradient(135deg, var(--primarycolor) 0%, var(--primarydark) 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .menu::-webkit-scrollbar {
            width: 6px;
        }

        .menu::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }

        .menu::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }

        .menu::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }

        .menu-container {
            width: 100%;
        }

        .profile-container {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 10px;
        }

        .profile-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .profile-subtitle {
            font-size: 13px;
            opacity: 0.8;
        }

        .logout-btn {
            width: 100%;
            padding: 10px;
            margin-top: 15px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .menu-row {
            margin: 5px 0;
        }

        .menu-btn {
            padding: 15px 20px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .menu-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        .menu-btn.menu-active {
            background: rgba(255,255,255,0.2);
            border-left: 4px solid white;
        }

        .menu-btn::before {
            content: '';
            width: 24px;
            height: 24px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            filter: brightness(0) invert(1);
        }

        .menu-icon-dashbord::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Crect x='3' y='3' width='7' height='7'/%3E%3Crect x='14' y='3' width='7' height='7'/%3E%3Crect x='14' y='14' width='7' height='7'/%3E%3Crect x='3' y='14' width='7' height='7'/%3E%3C/svg%3E");
        }

        .menu-icon-appointment::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M9 11l3 3L22 4'/%3E%3Cpath d='M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'/%3E%3C/svg%3E");
        }

        .menu-icon-session::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3E%3Cline x1='16' y1='2' x2='16' y2='6'/%3E%3Cline x1='8' y1='2' x2='8' y2='6'/%3E%3Cline x1='3' y1='10' x2='21' y2='10'/%3E%3C/svg%3E");
        }

        .menu-icon-patient::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2'/%3E%3Ccircle cx='12' cy='7' r='4'/%3E%3C/svg%3E");
        }

        .menu-icon-settings::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z'/%3E%3Cpath d='M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z'/%3E%3C/svg%3E");
        }

        .menu-text {
            font-size: 15px;
            color: white;
            text-decoration: none;
        }

        .non-style-link-menu {
            text-decoration: none;
            color: white;
            display: block;
        }

        /* Main Content */
        .dash-body {
            flex: 1;
            margin-left: 280px;
            padding: 30px 50px;
        }

        .nav-bar {
            background: white;
            padding: 20px 40px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);
            border-left: 4px solid var(--primarycolor);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(8, 145, 178, 0.2);
        }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--primarycolor);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }

        /* Section Titles */
        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--primarycolor);
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--accentcolor);
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 30px;
        }

        .action-btn {
            padding: 12px 20px;
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(8, 145, 178, 0.2);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }

        /* Tables */
        .dashbord-tables {
            width: 100%;
            margin-top: 30px;
        }

        .sub-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .table-headin {
            background: var(--primarycolor);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        .sub-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }

        .sub-table tbody tr:hover {
            background: #f0fdfa;
        }

        .sub-table tbody td {
            padding: 12px 15px;
            font-size: 14px;
            color: #334155;
        }

        .scroll {
            overflow-y: auto;
        }

        .scroll::-webkit-scrollbar {
            width: 8px;
        }

        .scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .scroll::-webkit-scrollbar-thumb {
            background: var(--primarycolor);
            border-radius: 4px;
        }

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, var(--primarycolor) 0%, var(--primarydark) 100%);
            color: white;
            border-radius: 12px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.2);
        }

        .welcome-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .welcome-subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .welcome-description {
            font-size: 14px;
            opacity: 0.8;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        @keyframes transitionIn-Y-over {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes transitionIn-Y-bottom {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .dashbord-tables {
            animation: transitionIn-Y-over 0.5s;
        }

        .stats-grid {
            animation: transitionIn-Y-bottom 0.5s;
        }

        .welcome-section {
            animation: transitionIn-Y-bottom 0.6s;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar Menu -->
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
                                    <p class="profile-title"><?php echo substr($username,0,13)  ?>..</p>
                                    <p class="profile-subtitle"><?php echo substr($useremail,0,22)  ?></p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <a href="../logout.php"><input type="button" value="Log out" class="logout-btn"></a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-dashbord menu-active">
                        <a href="index.php" class="non-style-link-menu"><p class="menu-text">Dashboard</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appointment">
                        <a href="appointment.php" class="non-style-link-menu"><p class="menu-text">My Appointments</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-session">
                        <a href="schedule.php" class="non-style-link-menu"><p class="menu-text">My Sessions</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-patient">
                        <a href="patient.php" class="non-style-link-menu"><p class="menu-text">My Patients</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-settings">
                        <a href="settings.php" class="non-style-link-menu"><p class="menu-text">Settings</p></a>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Main Content -->
        <div class="dash-body">
            <table border="0" width="100%" style="border-spacing: 0;margin:0;padding:0;">
                <tr>
                    <td colspan="2" class="nav-bar">
                        <div>
                            <p style="font-size: 28px;font-weight: 700;color: var(--primarycolor);margin:0;">Doctor Dashboard</p>
                            <p style="font-size: 14px;color: #64748b;margin-top:5px;">Welcome back, Dr. <?php echo $username; ?></p>
                        </div>
                        <div style="text-align: right;">
                            <p style="font-size: 14px;color: #64748b;margin:0;">Today's Date</p>
                            <p style="font-size: 16px;font-weight: 600;color: var(--primarydark);margin:5px 0 0 0;">
                                <?php echo date('F j, Y'); ?>
                            </p>
                        </div>
                    </td>
                </tr>
                
                <!-- Welcome Section -->
                <tr>
                    <td colspan="2">
                        <div class="welcome-section">
                            <div class="welcome-title">Welcome, Dr. <?php echo $username; ?>!</div>
                            <div class="welcome-subtitle">Ready for today's consultations?</div>
                            <div class="welcome-description">
                                You have <?php echo $todayAppointments; ?> appointments scheduled for today. 
                                Manage your schedule, view patient records, and provide quality healthcare services.
                            </div>
                            <a href="appointment.php" class="action-btn">View Today's Appointments</a>
                        </div>
                    </td>
                </tr>
                
                <!-- Statistics Overview -->
                <tr>
                    <td colspan="2">
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $todayAppointments; ?></div>
                                <div class="stat-label">Today's Appointments</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $upcomingAppointments; ?></div>
                                <div class="stat-label">Upcoming Appointments</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $myPatientsCount; ?></div>
                                <div class="stat-label">My Patients</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $todaySessions; ?></div>
                                <div class="stat-label">Today's Sessions</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $totalSessions; ?></div>
                                <div class="stat-label">Total Sessions</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $completedAppointments; ?></div>
                                <div class="stat-label">Completed Appointments</div>
                            </div>
                        </div>
                    </td>
                </tr>
                
                <!-- Quick Actions -->
                <tr>
                    <td colspan="2">
                        <p class="section-title">Quick Actions</p>
                        <div class="quick-actions">
                            <a href="appointment.php" class="action-btn">View Appointments</a>
                            <a href="schedule.php" class="action-btn">Manage Schedule</a>
                            <a href="patient.php" class="action-btn">Patient Records</a>
                            <a href="schedule.php?action=add" class="action-btn">Add Session</a>
                            <a href="settings.php" class="action-btn">Profile Settings</a>
                        </div>
                    </td>
                </tr>
                
                <!-- Recent Activity -->
                <tr>
                    <td colspan="2">
                        <p class="section-title">Today's Schedule</p>
                        <table width="100%" border="0" class="dashbord-tables" style="table-layout: fixed;">
                            <tr>
                                <td width="50%" style="padding-right: 15px; vertical-align: top;">
                                    <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                        <p style="font-size:20px;font-weight:600;color:var(--primarycolor);margin-bottom:15px;">
                                            Today's Appointments
                                        </p>
                                        <div class="scroll" style="height: 300px;">
                                            <table width="100%" class="sub-table" border="0">
                                                <thead>
                                                    <tr>
                                                        <th class="table-headin">Time</th>
                                                        <th class="table-headin">Patient</th>
                                                        <th class="table-headin">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $sqlmain= "SELECT a.appoid, p.pname, s.scheduletime, a.status 
                                                              FROM appointment a 
                                                              INNER JOIN patient p ON a.pid = p.pid 
                                                              INNER JOIN schedule s ON a.scheduleid = s.scheduleid 
                                                              WHERE s.docid='$userid' AND a.appodate = '$today' 
                                                              ORDER BY s.scheduletime";
                                                    $result= $database->query($sqlmain);
                                                    
                                                    if($result->num_rows==0){
                                                        echo '<tr><td colspan="3" style="text-align:center;padding:20px;">No appointments scheduled for today.</td></tr>';
                                                    } else {
                                                        while($row=$result->fetch_assoc()){
                                                            $status_badge = $row["status"] == 'Complete' ? 
                                                                '<span style="background:#d1fae5;color:#065f46;padding:4px 8px;border-radius:12px;font-size:12px;">Completed</span>' : 
                                                                '<span style="background:#fef3c7;color:#92400e;padding:4px 8px;border-radius:12px;font-size:12px;">Scheduled</span>';
                                                            echo '<tr>
                                                                <td>'.substr($row["scheduletime"],0,5).'</td>
                                                                <td>'.substr($row["pname"],0,20).'</td>
                                                                <td>'.$status_badge.'</td>
                                                            </tr>';
                                                        }
                                                    }
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                                <td width="50%" style="padding-left: 15px; vertical-align: top;">
                                    <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                        <p style="font-size:20px;font-weight:600;color:var(--primarycolor);margin-bottom:15px;">
                                            Upcoming Sessions (Next 7 Days)
                                        </p>
                                        <div class="scroll" style="height: 300px;">
                                            <table width="100%" class="sub-table" border="0">
                                                <thead>
                                                    <tr>
                                                        <th class="table-headin">Date</th>
                                                        <th class="table-headin">Title</th>
                                                        <th class="table-headin">Time</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $nextweek=date("Y-m-d",strtotime("+1 week"));
                                                    $sqlmain= "SELECT schedule.scheduleid, schedule.title, schedule.scheduledate, schedule.scheduletime 
                                                              FROM schedule 
                                                              WHERE schedule.docid='$userid' AND schedule.scheduledate>='$today' AND schedule.scheduledate<='$nextweek' 
                                                              ORDER BY schedule.scheduledate, schedule.scheduletime"; 
                                                    $result= $database->query($sqlmain);
                                                    
                                                    if($result->num_rows==0){
                                                        echo '<tr><td colspan="3" style="text-align:center;padding:20px;">No upcoming sessions scheduled.</td></tr>';
                                                    } else {
                                                        while($row=$result->fetch_assoc()){
                                                            echo '<tr>
                                                                <td>'.date('M d', strtotime($row["scheduledate"])).'</td>
                                                                <td>'.substr($row["title"],0,25).'</td>
                                                                <td>'.substr($row["scheduletime"],0,5).'</td>
                                                            </tr>';
                                                        }
                                                    }
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>