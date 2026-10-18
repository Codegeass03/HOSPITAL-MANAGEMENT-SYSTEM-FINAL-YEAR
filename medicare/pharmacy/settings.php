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
    <title>Settings - Smart Clinic Management System</title>
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

        .btn-primary {
            background: var(--primarycolor);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 20px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: var(--primarydark);
            transform: translateY(-2px);
        }

        .btn-icon-back {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Settings Tabs */
        .filter-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .setting-tabs {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border-left: 4px solid var(--primarycolor);
            cursor: pointer;
        }

        .setting-tabs:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(8, 145, 178, 0.15);
            border-left: 4px solid var(--accentcolor);
        }

        .dashboard-icons-setting {
            width: 60px;
            height: 60px;
            background-size: contain;
            background-repeat: no-repeat;
            margin-right: 20px;
        }

        .h1-dashboard {
            font-size: 20px;
            font-weight: 600;
            color: var(--primarydark);
            margin-bottom: 8px;
        }

        .h3-dashboard {
            font-size: 14px;
            color: #64748b;
            line-height: 1.5;
        }

        /* Form Styles */
        .add-doc-form-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .input-text {
            padding: 12px 15px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
            width: 100%;
            margin-bottom: 15px;
        }

        .input-text:focus {
            outline: none;
            border-color: var(--primarycolor);
            box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.1);
        }

        .box {
            padding: 12px 15px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            background: white;
            transition: all 0.3s;
            width: 100%;
            margin-bottom: 15px;
        }

        .box:focus {
            outline: none;
            border-color: var(--primarycolor);
            box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.1);
        }

        .label-td {
            padding: 10px 0;
        }

        .form-label {
            font-weight: 600;
            color: var(--primarydark);
            margin-bottom: 5px;
            display: block;
        }

        /* Popup Styles */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        .popup {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            color: var(--primarycolor);
            text-decoration: none;
        }

        .close:hover {
            color: var(--primarydark);
        }

        /* Table Styles */
        .sub-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }

        /* Animations */
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

        @keyframes transitionIn-X {
            from {
                transform: translateX(-20px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
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

        .dashbord-tables{
            animation: transitionIn-Y-over 0.5s;
        }
        .filter-container{
            animation: transitionIn-X 0.5s;
        }
        .sub-table{
            animation: transitionIn-Y-bottom 0.5s;
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

        .tn-in-text {
            font-size: 14px;
            font-weight: 500;
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
                    <td class="menu-btn menu-icon-settings menu-active">
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
                            <a href="settings.php"><button class="btn-primary-soft btn btn-icon-back" style="padding:10px;margin-right:20px;">
                                <span style="font-size:18px;">←</span> Back
                            </button></a>
                        </div>
                        <div>
                            <p style="font-size: 28px;font-weight: 700;color: var(--primarycolor);margin:0;">Settings</p>
                            <p style="font-size: 14px;color: #64748b;margin-top:5px;">Manage your account preferences</p>
                        </div>
                        <div style="text-align: right;">
                            <p style="font-size: 14px;color: #64748b;margin:0;">Today's Date</p>
                            <p style="font-size: 16px;font-weight: 600;color: var(--primarydark);margin:5px 0 0 0;">
                                <?php 
                                date_default_timezone_set('Asia/Kolkata');
                                $today = date('Y-m-d');
                                echo $today;
                                ?>
                            </p>
                        </div>
                    </td>
                </tr>
                
                <!-- Settings Options -->
                <tr>
                    <td colspan="4">
                        <center>
                            <table class="filter-container" style="border: none;" border="0">
                                <tr>
                                    <td colspan="4">
                                        <p style="font-size: 20px;color: var(--primarycolor);font-weight:600;">Account Management</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 25%;">
                                        <a href="?action=edit&id=<?php echo $userid ?>&error=0" class="non-style-link">
                                        <div class="setting-tabs" style="padding:25px;margin:auto;width:95%;display: flex;align-items: center;">
                                            <div class="dashboard-icons-setting" style="background-image: url('data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%230891b2\' stroke-width=\'2\'%3E%3Cpath d=\'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2\'/%3E%3Ccircle cx=\'12\' cy=\'7\' r=\'4\'/%3E%3C/svg%3E');"></div>
                                            <div style="flex: 1;">
                                                    <div class="h1-dashboard">
                                                        Account Settings &nbsp;
                                                    </div>
                                                    <div class="h3-dashboard">
                                                        Edit your Account Details & Change Password
                                                    </div>
                                            </div>
                                        </div>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="4">
                                        <p style="font-size: 10px">&nbsp;</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 25%;">
                                        <a href="?action=view&id=<?php echo $userid ?>" class="non-style-link">
                                        <div class="setting-tabs" style="padding:25px;margin:auto;width:95%;display: flex;align-items: center;">
                                            <div class="dashboard-icons-setting" style="background-image: url('data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%230891b2\' stroke-width=\'2\'%3E%3Cpath d=\'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z\'/%3E%3Ccircle cx=\'12\' cy=\'12\' r=\'3\'/%3E%3C/svg%3E');"></div>
                                            <div style="flex: 1;">
                                                    <div class="h1-dashboard">
                                                        View Account Details
                                                    </div>
                                                    <div class="h3-dashboard">
                                                        View Personal information About Your Account
                                                    </div>
                                            </div>
                                        </div>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="4">
                                        <p style="font-size: 10px">&nbsp;</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 25%;">
                                        <a href="?action=drop&id=<?php echo $userid.'&name='.$username ?>" class="non-style-link">
                                        <div class="setting-tabs" style="padding:25px;margin:auto;width:95%;display: flex;align-items: center;">
                                            <div class="dashboard-icons-setting" style="background-image: url('data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23ef4444\' stroke-width=\'2\'%3E%3Cpath d=\'M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2\'/%3E%3C/svg%3E');"></div>
                                            <div style="flex: 1;">
                                                    <div class="h1-dashboard" style="color: #ef4444;">
                                                        Delete Account
                                                    </div>
                                                    <div class="h3-dashboard">
                                                        Will Permanently Remove your Account
                                                    </div>
                                            </div>
                                        </div>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </center>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    
    <?php 
    if(isset($_GET['action']) && isset($_GET['id'])){
        $id = $_GET["id"];
        $action = $_GET["action"];
        
        if($action == 'drop'){
            $nameget = isset($_GET["name"]) ? $_GET["name"] : '';
            echo '
            <div id="popup1" class="overlay">
                <div class="popup">
                    <center>
                        <h2 style="color:var(--primarydark);margin-bottom:20px;">Are you sure?</h2>
                        <a class="close" href="settings.php">&times;</a>
                        <div class="content" style="margin-bottom:20px;">
                            You want to delete your account<br><strong>('.htmlspecialchars(substr($nameget,0,40)).')</strong>.
                        </div>
                        <div style="display: flex;justify-content: center;gap:20px;">
                            <a href="delete-doctor.php?id='.$id.'" class="non-style-link"><button class="btn-primary btn" style="padding:10px 20px;">Yes, Delete</button></a>
                            <a href="settings.php" class="non-style-link"><button class="btn-primary-soft btn" style="padding:10px 20px;">Cancel</button></a>
                        </div>
                    </center>
                </div>
            </div>
            ';
        } elseif($action == 'view'){
            $sqlmain = "select * from doctor where docid='$id'";
            $result = $database->query($sqlmain);
            
            if($result && $result->num_rows > 0){
                $row = $result->fetch_assoc();
                $name = $row["docname"];
                $email = $row["docemail"];
                $spe = $row["specialties"];
                
                $spcil_res = $database->query("select sname from specialties where id='$spe'");
                $spcil_array = $spcil_res->fetch_assoc();
                $spcil_name = $spcil_array["sname"];
                $nic = $row['docnic'];
                $tele = $row['doctel'];
                
                echo '
                <div id="popup1" class="overlay">
                    <div class="popup">
                        <center>
                            <a class="close" href="settings.php">&times;</a>
                            <div style="display: flex;justify-content: center;">
                                <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                                    <tr>
                                        <td>
                                            <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500;color:var(--primarydark);">Account Details</p><br><br>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <label for="name" class="form-label">Name: </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            '.htmlspecialchars($name).'<br><br>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <label for="Email" class="form-label">Email: </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                        '.htmlspecialchars($email).'<br><br>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <label for="nic" class="form-label">NIC: </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                        '.htmlspecialchars($nic).'<br><br>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <label for="Tele" class="form-label">Telephone: </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                        '.htmlspecialchars($tele).'<br><br>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <label for="spec" class="form-label">Specialties: </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                        '.htmlspecialchars($spcil_name).'<br><br>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">
                                            <a href="settings.php"><input type="button" value="OK" class="btn-primary btn" style="padding:10px 20px;"></a>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </center>
                        <br><br>
                    </div>
                </div>
                ';
            }
        } elseif($action == 'edit'){
            $sqlmain = "select * from doctor where docid='$id'";
            $result = $database->query($sqlmain);
            $row = $result->fetch_assoc();
            $name = $row["docname"];
            $email = $row["docemail"];
            $spe = $row["specialties"];
            
            $spcil_res = $database->query("select sname from specialties where id='$spe'");
            $spcil_array = $spcil_res->fetch_assoc();
            $spcil_name = $spcil_array["sname"];
            $nic = $row['docnic'];
            $tele = $row['doctel'];

            $error_1 = isset($_GET["error"]) ? $_GET["error"] : '0';
            $errorlist = array(
                '1'=>'<label for="promter" class="form-label" style="color:#ef4444;text-align:center;">Already have an account for this Email address.</label>',
                '2'=>'<label for="promter" class="form-label" style="color:#ef4444;text-align:center;">Password Conformation Error! Reconform Password</label>',
                '3'=>'<label for="promter" class="form-label" style="color:#ef4444;text-align:center;"></label>',
                '4'=>"",
                '0'=>'',
            );

            if($error_1 != '4'){
                echo '
                <div id="popup1" class="overlay">
                    <div class="popup">
                        <center>
                            <a class="close" href="settings.php">&times;</a>
                            <div style="display: flex;justify-content: center;">
                                <div style="width: 100%;">
                                    <table width="100%" class="sub-table scrolldown add-doc-form-container" border="0">
                                        <tr>
                                            <td class="label-td" colspan="2">'.
                                                $errorlist[$error_1]
                                            .'</td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500;color:var(--primarydark);">Edit Account Details</p>
                                                Doctor ID: '.$id.' (Auto Generated)<br><br>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="label-td" colspan="2">
                                                <form action="edit-doc.php" method="POST" class="add-new-form">
                                                <input type="hidden" value="'.$id.'" name="id00">
                                                <input type="hidden" name="oldemail" value="'.$email.'">
                                                <label for="Email" class="form-label">Email: </label>
                                                <input type="email" name="email" class="input-text" placeholder="Email Address" value="'.htmlspecialchars($email).'" required><br>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="label-td" colspan="2">
                                                <label for="name" class="form-label">Name: </label>
                                                <input type="text" name="name" class="input-text" placeholder="Doctor Name" value="'.htmlspecialchars($name).'" required><br>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="label-td" colspan="2">
                                                <label for="nic" class="form-label">NIC: </label>
                                                <input type="text" name="nic" class="input-text" placeholder="NIC Number" value="'.htmlspecialchars($nic).'" required><br>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="label-td" colspan="2">
                                                <label for="Tele" class="form-label">Telephone: </label>
                                                <input type="tel" name="Tele" class="input-text" placeholder="Telephone Number" value="'.htmlspecialchars($tele).'" required><br>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="label-td" colspan="2">
                                                <label for="spec" class="form-label">Choose Specialties: (Current: '.htmlspecialchars($spcil_name).')</label>
                                                <select name="spec" class="box">';
                                                    
                                                    $list11 = $database->query("select * from specialties;");
                                                    while($row00 = $list11->fetch_assoc()){
                                                        $sn = $row00["sname"];
                                                        $id00 = $row00["id"];
                                                        echo "<option value='$id00'>$sn</option>";
                                                    };
                                                    
                                                echo '</select><br><br>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="label-td" colspan="2">
                                                <label for="password" class="form-label">Password: </label>
                                                <input type="password" name="password" class="input-text" placeholder="Define a Password" required><br>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="label-td" colspan="2">
                                                <label for="cpassword" class="form-label">Confirm Password: </label>
                                                <input type="password" name="cpassword" class="input-text" placeholder="Confirm Password" required><br>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" style="text-align: center;">
                                                <input type="reset" value="Reset" class="btn-primary-soft btn" style="padding:10px 20px;">&nbsp;&nbsp;&nbsp;
                                                <input type="submit" value="Save Changes" class="btn-primary btn" style="padding:10px 20px;">
                                            </td>
                                        </tr>
                                        </form>
                                    </table>
                                </div>
                            </div>
                        </center>
                        <br><br>
                    </div>
                </div>
                ';
            } else {
                echo '
                <div id="popup1" class="overlay">
                    <div class="popup">
                        <center>
                            <h2 style="color:var(--primarydark);margin-bottom:20px;">Edit Successful!</h2>
                            <a class="close" href="settings.php">&times;</a>
                            <div class="content" style="margin-bottom:20px;">
                                If you changed your email, please logout and login again with your new email.
                            </div>
                            <div style="display: flex;justify-content: center;gap:20px;">
                                <a href="settings.php" class="non-style-link"><button class="btn-primary btn" style="padding:10px 20px;">OK</button></a>
                                <a href="../logout.php" class="non-style-link"><button class="btn-primary-soft btn" style="padding:10px 20px;">Log out</button></a>
                            </div>
                            <br><br>
                        </center>
                    </div>
                </div>
                ';
            }
        }
    }
    ?>
</body>
</html>