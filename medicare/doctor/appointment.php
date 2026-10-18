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

// Handle appointment cancellation
if(isset($_GET['action']) && $_GET['action'] == 'drop') {
    $appoid = $_GET['id'];
    $delete_query = $database->prepare("DELETE FROM appointment WHERE appoid=?");
    $delete_query->bind_param("i", $appoid);
    if($delete_query->execute()) {
        $success_message = "Appointment cancelled successfully!";
    } else {
        $error_message = "Error cancelling appointment!";
    }
}

// Build main query
$sqlmain = "SELECT a.appoid, a.apponum, p.pname, p.pid, d.docname, s.title, 
           s.scheduledate, s.scheduletime, a.appodate, a.status,
           (SELECT COUNT(*) FROM ehr_records e WHERE e.appoid = a.appoid) as has_ehr
           FROM appointment a 
           INNER JOIN schedule s ON a.scheduleid = s.scheduleid 
           INNER JOIN patient p ON a.pid = p.pid 
           INNER JOIN doctor d ON s.docid = d.docid 
           WHERE s.docid = '$userid'";

if($_POST && isset($_POST['filter'])){
    if(!empty($_POST["sheduledate"])){
        $sheduledate = $_POST["sheduledate"];
        $sqlmain .= " AND s.scheduledate = '$sheduledate'";
    }
}

$sqlmain .= " ORDER BY s.scheduledate DESC, s.scheduletime DESC";

$result = $database->query($sqlmain);
$total_appointments = $result->num_rows;

// Get statistics
$today_appointments = $database->query("SELECT COUNT(*) as count FROM appointment a 
                                      INNER JOIN schedule s ON a.scheduleid = s.scheduleid 
                                      WHERE s.docid = '$userid' AND a.appodate = '$today'")->fetch_assoc()['count'];
$upcoming_appointments = $database->query("SELECT COUNT(*) as count FROM appointment a 
                                         INNER JOIN schedule s ON a.scheduleid = s.scheduleid 
                                         WHERE s.docid = '$userid' AND s.scheduledate >= '$today'")->fetch_assoc()['count'];
$completed_appointments = $database->query("SELECT COUNT(*) as count FROM appointment a 
                                          INNER JOIN schedule s ON a.scheduleid = s.scheduleid 
                                          WHERE s.docid = '$userid' AND a.status = 'Complete'")->fetch_assoc()['count'];

// End output buffering
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - Smart Clinic Management System</title>
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
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z'/%3E%3Cpath d='M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h-.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z'/%3E%3C/svg%3E");
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

        /* Filter Container */
        .filter-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .filter-row {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 15px;
            align-items: center;
        }

        .filter-label {
            font-weight: 600;
            color: var(--primarydark);
            font-size: 14px;
        }

        .input-text {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .input-text:focus {
            outline: none;
            border-color: var(--primarycolor);
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primarycolor);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primarydark);
            transform: translateY(-2px);
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-info {
            background: var(--accentcolor);
            color: white;
        }

        .btn-info:hover {
            background: var(--primarylight);
        }

        /* Tables */
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--primarycolor);
            color: white;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }

        tbody tr:hover {
            background: #f0fdfa;
        }

        td {
            padding: 15px;
            font-size: 14px;
            color: #334155;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-view {
            background: var(--accentcolor);
            color: white;
        }

        .btn-view:hover {
            background: var(--primarylight);
        }

        .btn-start {
            background: #10b981;
            color: white;
        }

        .btn-start:hover {
            background: #059669;
        }

        .btn-cancel {
            background: #ef4444;
            color: white;
        }

        .btn-cancel:hover {
            background: #dc2626;
        }

        .btn-ehr {
            background: #8b5cf6;
            color: white;
        }

        .btn-ehr:hover {
            background: #7c3aed;
        }

        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border-color: #22c55e;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-color: #ef4444;
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

        .notfound-container {
            text-align: center;
            padding: 60px 20px;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-scheduled {
            background: #fef3c7;
            color: #92400e;
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

        .table-container {
            animation: transitionIn-Y-over 0.5s;
        }

        .stats-grid {
            animation: transitionIn-Y-bottom 0.5s;
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
                    <td class="menu-btn menu-icon-dashbord">
                        <a href="index.php" class="non-style-link-menu"><p class="menu-text">Dashboard</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appointment menu-active">
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
            <!-- Header -->
            <div class="nav-bar">
                <div>
                    <p style="font-size: 28px;font-weight: 700;color: var(--primarycolor);margin:0;">My Appointments</p>
                    <p style="font-size: 14px;color: #64748b;margin-top:5px;">Manage your patient appointments</p>
                </div>
                <div style="text-align: right;">
                    <p style="font-size: 14px;color: #64748b;margin:0;">Today's Date</p>
                    <p style="font-size: 16px;font-weight: 600;color: var(--primarydark);margin:5px 0 0 0;">
                        <?php echo date('F j, Y'); ?>
                    </p>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if(isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if(isset($error_message)): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <!-- Statistics Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_appointments; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $today_appointments; ?></div>
                    <div class="stat-label">Today's Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $upcoming_appointments; ?></div>
                    <div class="stat-label">Upcoming Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $completed_appointments; ?></div>
                    <div class="stat-label">Completed Appointments</div>
                </div>
            </div>

            <!-- Filter Container -->
            <div class="filter-container">
                <form method="POST" action="">
                    <div class="filter-row">
                        <label class="filter-label">Filter by Date:</label>
                        <input type="date" name="sheduledate" class="input-text" value="<?php echo $_POST['sheduledate'] ?? ''; ?>">
                        <input type="submit" name="filter" value="Filter" class="btn btn-primary">
                    </div>
                </form>
            </div>

            <!-- Appointments Table -->
            <div class="table-container">
                <p class="section-title">My Appointments (<?php echo $total_appointments; ?>)</p>
                
                <?php if($result->num_rows == 0): ?>
                    <div class="notfound-container">
                        <p style="font-size:20px;color:#64748b;">No appointments found matching your criteria</p>
                    </div>
                <?php else: ?>
                    <div class="scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Appointment #</th>
                                    <th>Patient Name</th>
                                    <th>Session Title</th>
                                    <th>Session Date & Time</th>
                                    <th>Appointment Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $result->fetch_assoc()): 
                                    $appoid = $row["appoid"];
                                    $apponum = $row["apponum"];
                                    $pname = $row["pname"];
                                    $pid = $row["pid"];
                                    $docname = $row["docname"];
                                    $title = $row["title"];
                                    $scheduledate = $row["scheduledate"];
                                    $scheduletime = $row["scheduletime"];
                                    $appodate = $row["appodate"];
                                    $status = $row["status"];
                                    $has_ehr = $row["has_ehr"] > 0;
                                ?>
                                <tr>
                                    <td style="text-align:center;font-weight:600;"><?php echo $apponum; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($pname); ?></strong><br>
                                        <small style="color:#666;">ID: P-<?php echo $pid; ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($title); ?></td>
                                    <td style="text-align:center;">
                                        <?php echo date('M j, Y', strtotime($scheduledate)); ?><br>
                                        <small><?php echo date('g:i A', strtotime($scheduletime)); ?></small>
                                    </td>
                                    <td style="text-align:center;"><?php echo $appodate; ?></td>
                                    <td style="text-align:center;">
                                        <?php if($status == 'Complete'): ?>
                                            <span class="badge badge-completed">Completed</span>
                                        <?php else: ?>
                                            <span class="badge badge-scheduled">Scheduled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if($status != 'Complete'): ?>
                                                <!-- Changed from Start EHR to Start Appointment -->
                                                <a href="start_appointment.php?id=<?php echo $appoid; ?>" class="non-style-link">
                                                    <button class="action-btn btn-start">
                                                        Start Appointment
                                                    </button>
                                                </a>
                                                <a href="?action=drop&id=<?php echo $appoid; ?>" 
                                                   class="action-btn btn-cancel"
                                                   onclick="return confirm('Are you sure you want to cancel appointment #<?php echo $apponum; ?> for <?php echo htmlspecialchars($pname); ?>?')">
                                                    Cancel
                                                </a>
                                            <?php else: ?>
                                                <?php if($has_ehr): ?>
                                                    <!-- View EHR button for completed appointments -->
                                                    <button class="action-btn btn-ehr" onclick="viewEHR(<?php echo $appoid; ?>)">
                                                        View EHR
                                                    </button>
                                                <?php else: ?>
                                                    <span style="color:#64748b;font-size:12px;">No EHR</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function viewEHR(appoid) {
            // Redirect to view_ehr.php or show EHR details
            window.location.href = 'view_ehr.php?id=' + appoid;
        }

        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.alert');
            messages.forEach(message => {
                message.style.display = 'none';
            });
        }, 3000);
    </script>
</body>
</html>