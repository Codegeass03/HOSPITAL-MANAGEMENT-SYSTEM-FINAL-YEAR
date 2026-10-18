<?php
session_start();

if(isset($_SESSION["user"])){
    if(($_SESSION["user"])=="" or $_SESSION['usertype']!='p'){
        header("location: ../login.php");
        exit();
    }else{
        $useremail=$_SESSION["user"];
    }
}else{
    header("location: ../login.php");
    exit();
}

include("../connection.php");
$sqlmain= "select * from patient where pemail=?";
$stmt = $database->prepare($sqlmain);
$stmt->bind_param("s",$useremail);
$stmt->execute();
$result = $stmt->get_result();
$userfetch=$result->fetch_assoc();
$userid= $userfetch["pid"];
$username=$userfetch["pname"];

date_default_timezone_set('Africa/Nairobi');
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Patient Portal</title>
   
</head>
<body>
    <div class="container">
        <!-- Sidebar Menu -->
        <div class="menu">
            <table class="menu-container" border="0">
                <tr> <style>
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
            font-family: inherit;
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

        .menu-icon-home::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'/%3E%3Cpolyline points='9 22 9 12 15 12 15 22'/%3E%3C/svg%3E");
        }

        .menu-icon-doctor::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2'/%3E%3Ccircle cx='8.5' cy='7' r='4'/%3E%3Cline x1='20' y1='8' x2='20' y2='14'/%3E%3Cline x1='23' y1='11' x2='17' y2='11'/%3E%3C/svg%3E");
        }

        .menu-icon-session::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3E%3Cline x1='16' y1='2' x2='16' y2='6'/%3E%3Cline x1='8' y1='2' x2='8' y2='6'/%3E%3Cline x1='3' y1='10' x2='21' y2='10'/%3E%3C/svg%3E");
        }

        .menu-icon-appoinment::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Ccircle cx='12' cy='12' r='10'/%3E%3Cpolyline points='12 6 12 12 16 14'/%3E%3C/svg%3E");
        }

         .menu-icon-medical::before {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'/%3E%3Cpolyline points='14 2 14 8 20 8'/%3E%3Cpath d='M16 13H8'/%3E%3Cpath d='M16 17H8'/%3E%3Cpath d='M10 9H9H8'/%3E%3Ccircle cx='16' cy='9' r='1'/%3E%3C/svg%3E");
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
            animation: transitionIn-Y-over 0.5s;
        }

        /* Top Navigation Bar */
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

        .nav-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--primarycolor);
            margin: 0;
        }

        .nav-subtitle {
            font-size: 14px;
            color: #64748b;
            margin-top: 5px;
        }

        .nav-date {
            text-align: right;
        }

        .date-label {
            font-size: 14px;
            color: #64748b;
            margin: 0;
        }

        .date-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--primarydark);
            margin: 5px 0 0 0;
        }

        /* Settings Cards Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .settings-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(8, 145, 178, 0.1);
            animation: transitionIn-Y-bottom 0.5s;
            cursor: pointer;
            text-decoration: none;
            display: block;
        }

        .settings-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(8, 145, 178, 0.2);
        }

        .settings-card-danger {
            border-left: 5px solid #ef4444;
        }

        .settings-card-primary {
            border-left: 5px solid var(--primarycolor);
        }

        .settings-card-secondary {
            border-left: 5px solid var(--secondarycolor);
        }

        .settings-icon {
            font-size: 40px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .settings-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--primarydark);
            margin-bottom: 10px;
        }

        .settings-title-danger {
            color: #ef4444;
        }

        .settings-desc {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
        }

        /* Modal Styles */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        .popup {
            background: white;
            border-radius: 12px;
            padding: 40px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: transitionIn-Y-bottom 0.3s;
        }

        .popup h2 {
            color: var(--primarycolor);
            margin-bottom: 20px;
            text-align: center;
        }

        .popup .content {
            margin: 20px 0;
            color: #64748b;
            line-height: 1.6;
        }

        .close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 28px;
            color: #64748b;
            text-decoration: none;
            cursor: pointer;
            font-weight: 300;
        }

        .close:hover {
            color: var(--primarycolor);
        }

        /* Form Styles */
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);
            margin: 20px 0;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--textcolor);
        }

        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid rgba(20, 184, 166, 0.3);
            border-radius: 8px;
            background: #f8fafc;
            color: var(--textcolor);
            font-size: 16px;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primarycolor);
            background: white;
            box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.1);
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
        }

        .form-section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--primarycolor);
            margin-bottom: 20px;
        }

        /* Action Buttons */
        .form-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }

        .btn-primary {
            padding: 12px 30px;
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }

        .btn-secondary {
            padding: 12px 30px;
            background: rgba(8, 145, 178, 0.1);
            color: var(--primarycolor);
            border: 1px solid rgba(8, 145, 178, 0.2);
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary:hover {
            background: rgba(8, 145, 178, 0.2);
            transform: translateY(-2px);
        }

        .btn-danger {
            padding: 12px 30px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
        }

        /* Modal Actions */
        .popup-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }

        .popup-btn {
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            border: none;
            font-size: 16px;
        }

        .popup-btn-confirm {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .popup-btn-cancel {
            background: rgba(8, 145, 178, 0.1);
            color: var(--primarycolor);
            border: 1px solid rgba(8, 145, 178, 0.2);
        }

        .popup-btn-ok {
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            color: white;
        }

        .popup-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* Success Message */
        .success-message {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);
        }

        .success-icon {
            font-size: 64px;
            margin-bottom: 20px;
            color: var(--primarycolor);
        }

        /* Error Messages */
        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        /* View Details Table */
        .details-table {
            width: 100%;
            border-collapse: collapse;
            background: #f8fafc;
            border-radius: 8px;
            overflow: hidden;
        }

        .details-row {
            border-bottom: 1px solid #e5e7eb;
        }

        .details-label {
            padding: 15px 20px;
            font-weight: 600;
            color: #64748b;
            background: white;
            width: 30%;
        }

        .details-value {
            padding: 15px 20px;
            color: var(--textcolor);
            background: white;
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

        /* Responsive Design */
        @media (max-width: 1024px) {
            .menu {
                width: 240px;
            }
            .dash-body {
                margin-left: 240px;
                padding: 20px 30px;
            }
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            .menu {
                position: relative;
                width: 100%;
                height: auto;
            }
            .dash-body {
                margin-left: 0;
                padding: 20px;
            }
            .nav-bar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .nav-date {
                text-align: center;
            }
            .form-actions, .popup-actions {
                flex-direction: column;
                align-items: center;
            }
            .btn-primary, .btn-secondary, .btn-danger, .popup-btn {
                width: 100%;
                max-width: 300px;
            }
            .details-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
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
                    <td class="menu-btn menu-icon-home">
                        <a href="index.php" class="non-style-link-menu"><p class="menu-text">Home</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor">
                        <a href="doctors.php" class="non-style-link-menu"><p class="menu-text">All Doctors</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-session">
                        <a href="schedule.php" class="non-style-link-menu"><p class="menu-text">Scheduled Sessions</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appoinment">
                        <a href="appointment.php" class="non-style-link-menu"><p class="menu-text">My Bookings</p></a>
                    </td>
                </tr>
                 <tr class="menu-row">
                    <td class="menu-btn menu-icon-medical">
                        <a href="ehr_records.php" class="non-style-link-menu"><p class="menu-text">Medical Records</p></a>
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
            <!-- Top Navigation Bar -->
            <div class="nav-bar">
                <div>
                    <h1 class="nav-title">Account Settings</h1>
                    <p class="nav-subtitle">Manage your profile and account preferences</p>
                </div>
                <div class="nav-date">
                    <p class="date-label">Today's Date</p>
                    <p class="date-value"><?php echo date('F j, Y'); ?></p>
                </div>
            </div>
            
            <!-- Settings Cards Grid -->
            <div class="settings-grid">
                <!-- Edit Account Card -->
                <a href="?action=edit&id=<?php echo $userid ?>&error=0" class="settings-card settings-card-primary">
                    <div class="settings-icon">
                        <span style="font-size: 40px;">⚙️</span>
                    </div>
                    <h3 class="settings-title">Account Settings</h3>
                    <p class="settings-desc">
                        Edit your account details, update contact information, and change your password.
                    </p>
                </a>
                
                <!-- View Details Card -->
                <a href="?action=view&id=<?php echo $userid ?>" class="settings-card settings-card-secondary">
                    <div class="settings-icon">
                        <span style="font-size: 40px;">👁️</span>
                    </div>
                    <h3 class="settings-title">View Account Details</h3>
                    <p class="settings-desc">
                        Review your personal information and account details in a secure view-only format.
                    </p>
                </a>
                
                <!-- Delete Account Card -->
                <a href="?action=drop&id=<?php echo $userid.'&name='.$username ?>" class="settings-card settings-card-danger">
                    <div class="settings-icon">
                        <span style="font-size: 40px;">🗑️</span>
                    </div>
                    <h3 class="settings-title settings-title-danger">Delete Account</h3>
                    <p class="settings-desc">
                        Permanently delete your account. This action cannot be undone and all data will be lost.
                    </p>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Modal Windows -->
    <?php 
    if(isset($_GET["action"]) && isset($_GET["id"])){
        $id = $_GET["id"];
        $action = $_GET["action"];
        
        if($action == 'drop'){
            $nameget = isset($_GET["name"]) ? $_GET["name"] : '';
            echo '
            <div id="popup1" class="overlay" style="display: flex;">
                <div class="popup">
                    <a class="close" href="settings.php">&times;</a>
                    <h2>⚠️ Delete Account</h2>
                    <div class="content">
                        <p>Are you sure you want to permanently delete your account?</p>
                        <p><strong>Account: ' . htmlspecialchars(substr($nameget,0,40)) . '</strong></p>
                        <p style="color: #ef4444; font-weight: 600; margin-top: 15px;">
                            ⚠️ Warning: This action cannot be undone. All your appointments, medical history, and personal data will be permanently deleted.
                        </p>
                    </div>
                    <div class="popup-actions">
                        <a href="delete-account.php?id='.$id.'" class="popup-btn popup-btn-confirm">Yes, Delete Account</a>
                        <a href="settings.php" class="popup-btn popup-btn-cancel">No, Keep Account</a>
                    </div>
                </div>
            </div>
            <script>
                document.getElementById("popup1").style.display = "flex";
            </script>';
            
        } elseif($action == 'view'){
            $sqlmain= "select * from patient where pid=?";
            $stmt = $database->prepare($sqlmain);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row=$result->fetch_assoc();
            $name=$row["pname"];
            $email=$row["pemail"];
            $address=$row["paddress"];
            $dob=$row["pdob"];
            $nic=$row['pnic'];
            $tele=$row['ptel'];
            
            echo '
            <div id="popup1" class="overlay" style="display: flex;">
                <div class="popup">
                    <a class="close" href="settings.php">&times;</a>
                    <h2>👤 Account Details</h2>
                    <div class="content">
                        <table class="details-table">
                            <tr class="details-row">
                                <td class="details-label">Name</td>
                                <td class="details-value">' . htmlspecialchars($name) . '</td>
                            </tr>
                            <tr class="details-row">
                                <td class="details-label">Email</td>
                                <td class="details-value">' . htmlspecialchars($email) . '</td>
                            </tr>
                            <tr class="details-row">
                                <td class="details-label">ID Number</td>
                                <td class="details-value">' . htmlspecialchars($nic) . '</td>
                            </tr>
                            <tr class="details-row">
                                <td class="details-label">Telephone</td>
                                <td class="details-value">' . htmlspecialchars($tele) . '</td>
                            </tr>
                            <tr class="details-row">
                                <td class="details-label">Address</td>
                                <td class="details-value">' . htmlspecialchars($address) . '</td>
                            </tr>
                            <tr class="details-row">
                                <td class="details-label">Date of Birth</td>
                                <td class="details-value">' . htmlspecialchars($dob) . '</td>
                            </tr>
                        </table>
                    </div>
                    <div class="popup-actions">
                        <a href="settings.php" class="popup-btn popup-btn-ok">OK</a>
                    </div>
                </div>
            </div>
            <script>
                document.getElementById("popup1").style.display = "flex";
            </script>';
            
        } elseif($action == 'edit'){
            $sqlmain= "select * from patient where pid=?";
            $stmt = $database->prepare($sqlmain);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row=$result->fetch_assoc();
            $name=$row["pname"];
            $email=$row["pemail"];
            $address=$row["paddress"];
            $nic=$row['pnic'];
            $tele=$row['ptel'];

            $error_1 = isset($_GET["error"]) ? $_GET["error"] : '0';
            $errorlist = array(
                '1' => '<div class="error-message">Already have an account for this Email address.</div>',
                '2' => '<div class="error-message">Password Confirmation Error! Please reconfirm your password.</div>',
                '3' => '<div class="error-message">An error occurred. Please try again.</div>',
                '4' => '',
                '0' => '',
            );

            if($error_1 != '4'){
                echo '
                <div id="popup1" class="overlay" style="display: flex;">
                    <div class="popup">
                        <a class="close" href="settings.php">&times;</a>
                        <h2>✏️ Edit Account Details</h2>
                        ' . $errorlist[$error_1] . '
                        <div class="content">
                            <form action="edit-user.php" method="POST" class="form-container">
                                <input type="hidden" value="'.$id.'" name="id00">
                                <input type="hidden" name="oldemail" value="'.$email.'">
                                
                                <div class="form-section">
                                    <h3 class="form-section-title">Personal Information</h3>
                                    
                                    <div class="form-group">
                                        <label class="form-label">User ID</label>
                                        <input type="text" class="form-input" value="'.$id.'" readonly>
                                        <small style="color: #64748b;">Auto-generated user identifier</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" id="name" name="name" class="form-input" placeholder="Enter your full name" value="'.htmlspecialchars($name).'" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" id="email" name="email" class="form-input" placeholder="Enter your email address" value="'.htmlspecialchars($email).'" required>
                                    </div>
                                </div>
                                
                                <div class="form-section">
                                    <h3 class="form-section-title">Contact Information</h3>
                                    
                                    <div class="form-group">
                                        <label for="nic" class="form-label">ID Number</label>
                                        <input type="text" id="nic" name="nic" class="form-input" placeholder="Enter your ID number" value="'.htmlspecialchars($nic).'" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="Tele" class="form-label">Telephone Number</label>
                                        <input type="tel" id="Tele" name="Tele" class="form-input" placeholder="Enter your telephone number" value="'.htmlspecialchars($tele).'" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="address" class="form-label">Address</label>
                                        <input type="text" id="address" name="address" class="form-input" placeholder="Enter your address" value="'.htmlspecialchars($address).'" required>
                                    </div>
                                </div>
                                
                                <div class="form-section">
                                    <h3 class="form-section-title">Security</h3>
                                    
                                    <div class="form-group">
                                        <label for="password" class="form-label">New Password</label>
                                        <input type="password" id="password" name="password" class="form-input" placeholder="Enter a new password" required>
                                        <small style="color: #64748b;">Leave blank if you don\'t want to change your password</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="cpassword" class="form-label">Confirm New Password</label>
                                        <input type="password" id="cpassword" name="cpassword" class="form-input" placeholder="Confirm your new password" required>
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="reset" style="background: #a3caffff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;" class="btn-secondary"><a href="settings.php">Close</a></button>
                                    <button type="submit" class="btn-primary">Save Changes</button>
                                    
                            </form>
                        </div>
                    </div>
                </div>
                <script>
                    document.getElementById("popup1").style.display = "flex";
                </script>';
            } else {
                echo '
                <div id="popup1" class="overlay" style="display: flex;">
                    <div class="popup">
                        <a class="close" href="settings.php">&times;</a>
                        <div class="success-message">
                            <div class="success-icon">✅</div>
                            <h2>Success!</h2>
                            <p>Your account details have been successfully updated.</p>
                            <p style="color: #64748b; margin-top: 15px;">
                                <strong>Important:</strong> If you changed your email address, please logout and login again with your new email.
                            </p>
                            <div class="popup-actions">
                                <a href="settings.php" class="popup-btn popup-btn-ok">Continue</a>
                                <a href="../logout.php" class="popup-btn popup-btn-cancel">Logout Now</a>
                            </div>
                        </div>
                    </div>
                </div>
                <script>
                    document.getElementById("popup1").style.display = "flex";
                </script>';
            }
        }
    }
    ?>
</body>
</html>