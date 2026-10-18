<?php
// Start session at the very beginning
session_start();

// Check authentication
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

// Import database
include("../connection.php");

// Get user data
$userrow = $database->query("select * from doctor where docemail='$useremail'");
$userfetch=$userrow->fetch_assoc();
$userid= $userfetch["docid"];
$username=$userfetch["docname"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Sessions - Smart Clinic Management System</title>
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

        .menu-icon-appoinment::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M9 11l3 3L22 4'/%3E%3Cpath d='M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'/%3E%3C/svg%3E");
        }

        .menu-icon-session::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3E%3Cline x1='16' y1='2' x2='16' y2='6'/%3E%3Cline x1='8' y1='2' x2='8' y2='6'/%3E%3Cline x1='3' y1='10' x2='21' y2='10'/%3E%3C/svg%3E");
        }

        .menu-icon-patient::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2'/%3E%3Ccircle cx='12' cy='7' r='4'/%3E%3C/svg%3E");
        }

        .menu-icon-settings::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Ccircle cx='12' cy='12' r='3'/%3E%3Cpath d='M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z'/%3E%3C/svg%3E");
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

        /* Button Styles */
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

        .btn-primary-soft {
            background: rgba(8, 145, 178, 0.1);
            color: var(--primarycolor);
            border: 1px solid rgba(8, 145, 178, 0.2);
            border-radius: 6px;
            padding: 8px 16px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary-soft:hover {
            background: rgba(8, 145, 178, 0.2);
            transform: translateY(-2px);
        }

        .btn-icon-back {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-label {
            background: var(--primarycolor);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 15px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-label:hover {
            background: var(--primarydark);
        }

        .button-icon {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }

        .btn-view {
            background: rgba(20, 184, 166, 0.1);
            color: var(--secondarycolor);
            border: 1px solid rgba(20, 184, 166, 0.2);
        }

        .btn-view:hover {
            background: rgba(20, 184, 166, 0.2);
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .btn-delete:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        .btn-filter {
            background: var(--primarycolor);
            color: white;
            border: none;
        }

        .btn-filter:hover {
            background: var(--primarydark);
        }

        /* Form Styles */
        .input-text, .box {
            padding: 10px 15px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
            width: 100%;
        }

        .input-text:focus, .box:focus {
            outline: none;
            border-color: var(--primarycolor);
            box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.1);
        }

        /* Table Styles */
        .sub-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
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

        /* Modal/Overlay Styles */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            display: flex;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s;
        }

        .popup {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideDown 0.3s;
            position: relative;
        }

        .popup::-webkit-scrollbar {
            width: 8px;
        }

        .popup::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .popup::-webkit-scrollbar-thumb {
            background: var(--primarycolor);
            border-radius: 4px;
        }

        .close {
            position: absolute;
            top: 20px;
            right: 25px;
            font-size: 32px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
            transition: color 0.3s;
            text-decoration: none;
        }

        .close:hover {
            color: #000;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--textcolor);
            font-size: 14px;
        }

        .label-td {
            padding: 10px 0;
        }

        .add-new-form {
            width: 100%;
        }

        /* Filter Container */
        .filter-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        /* Header Actions */
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        /* Section Titles */
        .heading-main12 {
            font-size: 24px;
            font-weight: 700;
            color: var(--primarycolor);
            margin: 20px 0;
        }

        .heading-sub12 {
            font-size: 16px;
            font-weight: 600;
            color: var(--primarydark);
        }

        /* Animations */
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

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .popup{
            animation: transitionIn-Y-bottom 0.5s;
        }
        .sub-table{
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
                                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='50' fill='%23e0f2fe'/%3E%3Cpath d='M50 45c8.284 0 15-6.716 15-15s-6.716-15-15-15-15 6.716-15 15 6.716 15 15 15zm0 7.5c-10 0-30 5-30 15V75h60v-7.5c0-10-20-15-30-15z' fill='%230891b2'/%3E%3C/svg%3E" alt="" width="100%" style="border-radius:50%">
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
                    <td class="menu-btn menu-icon-appoinment">
                        <a href="appointment.php" class="non-style-link-menu"><p class="menu-text">My Appointments</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-session menu-active">
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
                    <td colspan="4" class="nav-bar">
                        <div>
                            <a href="schedule.php"><button class="btn-primary-soft btn btn-icon-back" style="padding:10px;margin-right:20px;">
                                <span style="font-size:18px;">←</span> Back
                            </button></a>
                        </div>
                        <div>
                            <p style="font-size: 28px;font-weight: 700;color: var(--primarycolor);margin:0;">My Sessions</p>
                            <p style="font-size: 14px;color: #64748b;margin-top:5px;">Manage your consultation sessions</p>
                        </div>
                        <div style="text-align: right;">
                            <p style="font-size: 14px;color: #64748b;margin:0;">Today's Date</p>
                            <p style="font-size: 16px;font-weight: 600;color: var(--primarydark);margin:5px 0 0 0;">
                                <?php 
                                date_default_timezone_set('Asia/Kolkata');
                                $today = date('Y-m-d');
                                echo $today;

                                // Get session count
                                if(isset($userid) && !empty($userid)) {
                                    $list110 = $database->query("SELECT * FROM schedule WHERE docid='$userid'");
                                } else {
                                    $list110 = $database->query("SELECT * FROM schedule WHERE 1=0");
                                }
                                ?>
                            </p>
                        </div>
                    </td>
                </tr>
                
                <!-- Session Count and Add Button -->
                <tr>
                    <td colspan="4" style="padding-top:10px;">
                        <div class="header-actions">
                            <p class="heading-main12" style="margin-left: 0;font-size:20px;color:rgb(49, 49, 49)">
                                My Sessions (<?php echo isset($list110) ? $list110->num_rows : 0; ?>)
                            </p>
                            <a href="?action=add-session&id=none&error=0" style="text-decoration: none;">
                                <button class="btn btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                    Add New Session
                                </button>
                            </a>
                        </div>
                    </td>
                </tr>
                
                <!-- Filter Section -->
                <tr>
                    <td colspan="4">
                        <center>
                            <table class="filter-container" border="0">
                                <tr>
                                    <td width="10%"></td>
                                    <td width="5%" style="text-align: center;">Date:</td>
                                    <td width="30%">
                                        <form action="" method="post">
                                            <input type="date" name="sheduledate" id="date" class="input-text filter-container-items" style="margin: 0;width: 95%;">
                                    </td>
                                    <td width="12%">
                                        <input type="submit" name="filter" value="Filter" class="btn-primary-soft btn button-icon btn-filter" style="padding: 12px; margin:0;width:100%">
                                        </form>
                                    </td>
                                </tr>
                            </table>
                        </center>
                    </td>
                </tr>
                
                <?php
                // Build SQL query
                if(isset($userid) && !empty($userid)) {
                    $sqlmain = "SELECT schedule.scheduleid, schedule.title, doctor.docname, schedule.scheduledate, schedule.scheduletime, schedule.nop 
                               FROM schedule 
                               INNER JOIN doctor ON schedule.docid=doctor.docid 
                               WHERE doctor.docid='$userid'";
                    
                    if($_POST && !empty($_POST["sheduledate"])){
                        $sheduledate = $_POST["sheduledate"];
                        $sqlmain .= " AND schedule.scheduledate='$sheduledate'";
                    }
                    
                    // Order by date descending (newest first)
                    $sqlmain .= " ORDER BY schedule.scheduledate DESC, schedule.scheduletime DESC";
                } else {
                    $sqlmain = "SELECT * FROM schedule WHERE 1=0";
                }
                ?>
                
                <!-- Sessions Table -->
                <tr>
                    <td colspan="4">
                        <center>
                            <div class="abc scroll">
                                <table width="100%" class="sub-table scrolldown" border="0">
                                    <thead>
                                        <tr>
                                            <th class="table-headin">Session Title</th>
                                            <th class="table-headin">Scheduled Date & Time</th>
                                            <th class="table-headin">Max Bookings</th>
                                            <th class="table-headin">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if(isset($sqlmain)) {
                                            $result = $database->query($sqlmain);

                                            if($result->num_rows == 0){
                                                echo '<tr>
                                                <td colspan="4">
                                                <br><br><br><br>
                                                <center>
                                                <img src="../img/notfound.svg" width="25%">
                                                
                                                <br>
                                                <p class="heading-main12" style="margin-left: 0;font-size:20px;color:rgb(49, 49, 49)">No sessions found!</p>
                                                <a class="non-style-link" href="?action=add-session&id=none&error=0" style="text-decoration:none;">
                                                    <button class="btn btn-primary" style="display: flex;justify-content: center;align-items: center;margin:20px;">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <line x1="12" y1="5" x2="12" y2="19"></line>
                                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                                        </svg>
                                                        Add Your First Session
                                                    </button>
                                                </a>
                                                </center>
                                                <br><br><br><br>
                                                </td>
                                                </tr>';
                                            } else {
                                                while($row = $result->fetch_assoc()){
                                                    $scheduleid = $row["scheduleid"];
                                                    $title = $row["title"];
                                                    $docname = $row["docname"];
                                                    $scheduledate = $row["scheduledate"];
                                                    $scheduletime = $row["scheduletime"];
                                                    $nop = $row["nop"];
                                                    echo '<tr>
                                                        <td>'.htmlspecialchars(substr($title,0,30)).'</td>
                                                        <td style="text-align:center;">
                                                            '.substr($scheduledate,0,10).' '.substr($scheduletime,0,5).'
                                                        </td>
                                                        <td style="text-align:center;">
                                                            '.$nop.'
                                                        </td>
                                                        <td>
                                                        <div style="display:flex;justify-content: center;gap:10px;">
                                                        <a href="?action=view&id='.$scheduleid.'" class="non-style-link"><button class="btn-primary-soft btn button-icon btn-view">View</button></a>
                                                        <a href="?action=drop&id='.$scheduleid.'&name='.urlencode($title).'" class="non-style-link"><button class="btn-primary-soft btn button-icon btn-delete">Cancel Session</button></a>
                                                        </div>
                                                        </td>
                                                    </tr>';
                                                }
                                            }
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </center>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    
    <?php
    if(isset($_GET['action'])) {
        $action = $_GET["action"];
        
        if($action == 'add-session'){
            echo '
            <div id="popup1" class="overlay">
                <div class="popup">
                    <a class="close" href="schedule.php">&times;</a>
                    <h2 style="color: var(--primarycolor); margin-bottom: 20px;">Add New Session</h2>
                    
                    <form action="add-session.php" method="POST" class="add-new-form">
                        <div class="label-td">
                            <label for="title" class="form-label">Session Title</label>
                            <input type="text" name="title" class="input-text" placeholder="Name of this Session" required>
                        </div>
                        
                        <input type="hidden" name="docid" value="'.$userid.'">
                        
                        <div class="label-td">
                            <label for="nop" class="form-label">Number of Patients/Appointment Numbers</label>
                            <input type="number" name="nop" class="input-text" min="1" max="50" placeholder="Maximum appointments for this session" required>
                            <small style="color: #64748b; font-size: 12px;"></small>
                        </div>
                        
                        <div class="label-td">
                            <label for="date" class="form-label">Session Date</label>
                            <input type="date" name="date" class="input-text" min="'.date('Y-m-d').'" required>
                        </div>
                        
                        <div class="label-td">
                            <label for="time" class="form-label">Schedule Time</label>
                            <input type="time" name="time" class="input-text" required>
                            <small style="color: #64748b; font-size: 12px;">Consultation start time</small>
                        </div>
                        
                        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px;">
                            <a href="schedule.php" class="non-style-link">
                                <button type="button" class="btn" style="background: #e5e7eb; color: #374151;">Cancel</button>
                            </a>
                            <button type="submit" name="shedulesubmit" class="btn btn-primary">Create Session</button>
                        </div>
                    </form>
                </div>
            </div>';
            
        } elseif($action == 'session-added'){
            $titleget = isset($_GET["title"]) ? $_GET["title"] : '';
            echo '
            <div id="popup1" class="overlay">
                <div class="popup" style="max-width: 500px; text-align: center;">
                    <a class="close" href="schedule.php">&times;</a>
                    <div style="padding: 40px 20px;">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="margin-bottom: 20px;">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                        <h2 style="color: var(--primarycolor); margin-bottom: 15px;">Session Created!</h2>
                        <p style="color: #64748b; margin-bottom: 30px;">'.htmlspecialchars(substr($titleget,0,50)).' was successfully scheduled.</p>
                        <a href="schedule.php" style="text-decoration: none;">
                            <button class="btn btn-primary">OK</button>
                        </a>
                    </div>
                </div>
            </div>';
            
        } elseif($action == 'drop'){
            $id = $_GET["id"];
            $nameget = isset($_GET["name"]) ? $_GET["name"] : '';
            echo '
            <div id="popup1" class="overlay">
                <div class="popup" style="max-width: 500px; text-align: center;">
                    <a class="close" href="schedule.php">&times;</a>
                    <div style="padding: 40px 20px;">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" style="margin-bottom: 20px;">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        </svg>
                        <h2 style="color: #ef4444; margin-bottom: 15px;">Are you sure?</h2>
                        <p style="color: #64748b; margin-bottom: 30px;">You want to delete: <br><strong>'.htmlspecialchars(substr($nameget,0,50)).'</strong></p>
                        <div style="display: flex; gap: 10px; justify-content: center;">
                            <a href="schedule.php" style="text-decoration: none;">
                                <button class="btn" style="background: #e5e7eb; color: #374151;">No, Cancel</button>
                            </a>
                            <a href="delete-session.php?id='.$id.'" style="text-decoration: none;">
                                <button class="btn btn-danger">Yes, Delete</button>
                            </a>
                        </div>
                    </div>
                </div>
            </div>';
            
        } elseif($action == 'view'){
            $id = $_GET["id"];
            // Check if userid is set before querying
            if(isset($userid) && !empty($userid)) {
                $sqlmain = "SELECT schedule.scheduleid, schedule.title, doctor.docname, schedule.scheduledate, schedule.scheduletime, schedule.nop 
                           FROM schedule 
                           INNER JOIN doctor ON schedule.docid=doctor.docid  
                           WHERE schedule.scheduleid='$id' AND doctor.docid='$userid'";
                $result = $database->query($sqlmain);
                
                if($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $docname = $row["docname"];
                    $scheduleid = $row["scheduleid"];
                    $title = $row["title"];
                    $scheduledate = $row["scheduledate"];
                    $scheduletime = $row["scheduletime"];
                    $nop = $row['nop'];

                    $sqlmain12 = "SELECT * FROM appointment 
                                 INNER JOIN patient ON patient.pid=appointment.pid 
                                 INNER JOIN schedule ON schedule.scheduleid=appointment.scheduleid 
                                 WHERE schedule.scheduleid='$id'";
                    $result12 = $database->query($sqlmain12);
                    
                    echo '
                    <div id="popup1" class="overlay">
                        <div class="popup">
                            <a class="close" href="schedule.php">&times;</a>
                            <h2 style="color: var(--primarycolor); margin-bottom: 30px;">Session Details</h2>
                            
                            <div style="background: #f0fdfa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                                <div style="margin-bottom: 15px;">
                                    <label class="form-label">Session Title:</label>
                                    <p style="font-size: 16px; color: var(--textcolor);">'.htmlspecialchars($title).'</p>
                                </div>
                                
                                <div style="margin-bottom: 15px;">
                                    <label class="form-label">Doctor:</label>
                                    <p style="font-size: 16px; color: var(--textcolor);">'.htmlspecialchars($docname).'</p>
                                </div>
                                
                                <div style="margin-bottom: 15px;">
                                    <label class="form-label">Scheduled Date:</label>
                                    <p style="font-size: 16px; color: var(--textcolor);">'.date('F j, Y', strtotime($scheduledate)).'</p>
                                </div>
                                
                                <div style="margin-bottom: 15px;">
                                    <label class="form-label">Scheduled Time:</label>
                                    <p style="font-size: 16px; color: var(--textcolor);">'.substr($scheduletime,0,5).'</p>
                                </div>
                                
                                <div>
                                    <label class="form-label">Registered Patients:</label>
                                    <p style="font-size: 18px; font-weight: 600; color: var(--primarycolor);">'.$result12->num_rows.' / '.$nop.'</p>
                                </div>
                            </div>
                            
                            <div>
                                <h3 style="color: var(--primarydark); margin-bottom: 15px;">Registered Patients</h3>
                                <div class="table-container" style="max-height: 400px; overflow-y: auto;">
                                    <table width="100%" class="sub-table" border="0">
                                        <thead>
                                            <tr>
                                                <th class="table-headin">Patient ID</th>
                                                <th class="table-headin">Patient Name</th>
                                                <th class="table-headin">Appointment Number</th>
                                                <th class="table-headin">Patient Telephone</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        if($result12->num_rows == 0){
                                            echo '<tr>
                                                <td colspan="4">
                                                    <div style="text-align:center;padding:20px;">
                                                        <p style="color: #64748b;">No patients registered for this session yet</p>
                                                    </div>
                                                </td>
                                            </tr>';
                                        } else {
                                            while($row = $result12->fetch_assoc()){
                                                $apponum = $row["apponum"];
                                                $pid = $row["pid"];
                                                $pname = $row["pname"];
                                                $ptel = $row["ptel"];
                                                
                                                echo '<tr style="text-align:center;">
                                                    <td>'.htmlspecialchars(substr($pid,0,15)).'</td>
                                                    <td style="font-weight:600;">'.htmlspecialchars(substr($pname,0,25)).'</td>
                                                    <td style="text-align:center;font-size:18px;font-weight:500;color:var(--primarycolor);">'.$apponum.'</td>
                                                    <td>'.htmlspecialchars(substr($ptel,0,25)).'</td>
                                                </tr>';
                                            }
                                        }
                                        
                                        echo '</tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div style="text-align: center; margin-top: 30px;">
                                <a href="schedule.php" style="text-decoration: none;">
                                    <button class="btn btn-primary">Close</button>
                                </a>
                            </div>
                        </div>
                    </div>';
                }
            }
        }
    }
    ?>
</body>
</html>