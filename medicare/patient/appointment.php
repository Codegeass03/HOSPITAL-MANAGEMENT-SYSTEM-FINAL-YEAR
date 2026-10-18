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
$userrow = $stmt->get_result();
$userfetch=$userrow->fetch_assoc();
$userid= $userfetch["pid"];
$username=$userfetch["pname"];

date_default_timezone_set('Africa/Nairobi');
$today = date('Y-m-d');

// Modified query to include status from appointment table
$sqlmain= "select appointment.appoid, appointment.status, appointment.completed_at, appointment.completed_by,
          schedule.scheduleid,schedule.title,doctor.docname,patient.pname,
          schedule.scheduledate,schedule.scheduletime,appointment.apponum,appointment.appodate 
          from schedule 
          inner join appointment on schedule.scheduleid=appointment.scheduleid 
          inner join patient on patient.pid=appointment.pid 
          inner join doctor on schedule.docid=doctor.docid  
          where patient.pid=$userid ";

if($_POST && isset($_POST["sheduledate"])){
    $sheduledate=$_POST["sheduledate"];
    $sqlmain.=" and schedule.scheduledate='$sheduledate' ";
}

$sqlmain.="order by appointment.appodate desc";
$result= $database->query($sqlmain);

// Handle appointment cancellation
if(isset($_GET['action']) && $_GET['action'] == 'drop' && isset($_GET['id'])){
    $appoid = $_GET['id'];
    $update_query = $database->prepare("UPDATE appointment SET status = 'Cancelled' WHERE appoid=? AND pid=?");
    $update_query->bind_param("ii", $appoid, $userid);
    if($update_query->execute()){
        $success_message = "Appointment cancelled successfully!";
        // Refresh the page to show updated status
        header("location: appointment.php?success=Appointment cancelled successfully");
        exit();
    } else {
        $error_message = "Error cancelling appointment!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">  
    <!-- <link rel="stylesheet" href="../css/main.css">   -->
    <link rel="stylesheet" href="../css/admin.css">
    <title>My Bookings - Patient Portal</title>
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

        /* Alert Messages */
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

        /* Filter Section */
        .filter-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);
            margin-bottom: 30px;
            border-left: 5px solid var(--primarycolor);
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .filter-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--primarydark);
        }

        .booking-count {
            background: var(--accentcolor);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .filter-form {
            display: flex;
            gap: 15px;
            align-items: center;
            justify-content: center;
        }

        .filter-input {
            padding: 12px 20px;
            border: 2px solid rgba(20, 184, 166, 0.3);
            border-radius: 8px;
            background: #f8fafc;
            color: var(--textcolor);
            font-size: 16px;
            transition: all 0.3s;
            min-width: 200px;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--primarycolor);
            background: white;
            box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.1);
        }

        .filter-btn {
            padding: 12px 30px;
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(8, 145, 178, 0.2);
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }

        /* Bookings Grid */
        .bookings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .booking-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(8, 145, 178, 0.1);
            animation: transitionIn-Y-bottom 0.5s;
            position: relative;
        }

        .booking-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(8, 145, 178, 0.2);
        }

        .booking-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-scheduled {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .booking-ref {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 10px;
        }

        .booking-ref strong {
            color: var(--primarycolor);
        }

        .booking-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--primarycolor);
            margin-bottom: 15px;
        }

        .booking-doctor {
            font-size: 16px;
            color: var(--textcolor);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .booking-appnum {
            font-size: 36px;
            font-weight: 800;
            color: var(--primarydark);
            text-align: center;
            margin: 15px 0;
            background: rgba(8, 145, 178, 0.1);
            border-radius: 8px;
            padding: 10px;
        }

        .booking-details {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }

        .booking-date, .booking-time {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .booking-date strong, .booking-time strong {
            color: var(--textcolor);
        }

        .cancel-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 15px;
            margin-top: 10px;
        }

        .cancel-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
        }

        .view-record-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 15px;
            margin-top: 10px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .view-record-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);
            grid-column: 1 / -1;
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
            color: rgba(8, 145, 178, 0.3);
        }

        .empty-title {
            font-size: 20px;
            color: var(--textcolor);
            margin-bottom: 10px;
        }

        .empty-subtitle {
            color: #64748b;
            margin-bottom: 25px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        .btn-primary-soft {
            background: rgba(8, 145, 178, 0.1);
            color: var(--primarycolor);
            border: 1px solid rgba(8, 145, 178, 0.2);
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-primary-soft:hover {
            background: rgba(8, 145, 178, 0.2);
            transform: translateY(-2px);
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
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: transitionIn-Y-bottom 0.3s;
            position: relative;
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

        .popup-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }

        .popup-btn {
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .popup-btn-confirm {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
        }

        .popup-btn-cancel {
            background: rgba(8, 145, 178, 0.1);
            color: var(--primarycolor);
            border: 1px solid rgba(8, 145, 178, 0.2);
        }

        .popup-btn-ok {
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            color: white;
            border: none;
        }

        .popup-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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
            .filter-form {
                flex-direction: column;
            }
            .nav-bar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .nav-date {
                text-align: center;
            }
            .bookings-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Icons */
        .icon {
            width: 16px;
            height: 16px;
            vertical-align: middle;
            margin-right: 5px;
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
                    <td class="menu-btn menu-icon-appoinment menu-active">
                        <a href="appointment.php" class="non-style-link-menu"><p class="menu-text">My Bookings</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-medical">
                        <a href="ehr_records.php" class="non-style-link-menu"><p class="menu-text">Medical Records</p></a>
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
            <!-- Top Navigation Bar -->
            <div class="nav-bar">
                <div>
                    <h1 class="nav-title">My Bookings</h1>
                    <p class="nav-subtitle">View and manage your appointments</p>
                </div>
                <div class="nav-date">
                    <p class="date-label">Today's Date</p>
                    <p class="date-value"><?php echo date('F j, Y'); ?></p>
                </div>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($error_message)): ?>
                <div class="alert alert-error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Filter Section -->
            <div class="filter-container">
                <div class="filter-header">
                    <h2 class="filter-title">🔍 Filter Bookings</h2>
                    <span class="booking-count"><?php echo $result->num_rows; ?> bookings</span>
                </div>
                <form action="" method="post" class="filter-form">
                    <label style="color: #64748b; font-weight: 500;">Filter by Date:</label>
                    <input type="date" name="sheduledate" class="filter-input" value="<?php echo isset($_POST['sheduledate']) ? htmlspecialchars($_POST['sheduledate']) : ''; ?>">
                    <button type="submit" name="filter" class="filter-btn">Apply Filter</button>
                    <?php if(isset($_POST['sheduledate']) && !empty($_POST['sheduledate'])): ?>
                        <a href="appointment.php" class="btn-primary-soft">Clear Filter</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Bookings Grid -->
            <?php if($result->num_rows > 0): ?>
                <div class="bookings-grid">
                    <?php 
                    for ($x=0; $x<$result->num_rows; $x++){
                        $row=$result->fetch_assoc();
                        $scheduleid=$row["scheduleid"];
                        $title=$row["title"];
                        $docname=$row["docname"];
                        $scheduledate=$row["scheduledate"];
                        $scheduletime=$row["scheduletime"];
                        $apponum=$row["apponum"];
                        $appodate=$row["appodate"];
                        $appoid=$row["appoid"];
                        $status = $row["status"]; // Get actual status from database
                        $completed_at = $row["completed_at"] ?? null;
                        
                        // Determine badge style based on status
                        $badge_class = '';
                        $display_status = '';
                        
                        switch($status) {
                            case 'Complete':
                                $badge_class = 'badge-completed';
                                $display_status = 'Completed';
                                break;
                            case 'Cancelled':
                                $badge_class = 'badge-cancelled';
                                $display_status = 'Cancelled';
                                break;
                            case 'Scheduled':
                            default:
                                $badge_class = 'badge-scheduled';
                                $display_status = 'Scheduled';
                                break;
                        }
                        
                        echo '
<div class="booking-card">
    <div class="booking-badge ' . $badge_class . '">' . $display_status . '</div>
    <div class="booking-ref">
        📅 Booked on: ' . htmlspecialchars(substr($appodate,0,10)) . ' | Ref: OC-000-' . $appoid . '
    </div>
    <h3 class="booking-title">' . htmlspecialchars(substr($title,0,40)) . '</h3>
    <p class="booking-doctor">👨‍⚕️ Dr. ' . htmlspecialchars(substr($docname,0,30)) . '</p>
    <div class="booking-appnum">0' . $apponum . '</div>
    <div class="booking-details">
        <p class="booking-date">
            <svg class="icon" viewBox="0 0 24 24" fill="currentColor">
                <path d="M8 6v3H5v14h14V9h-3V6H8zm2 3h4V8h-4v1zm7 12H7V10h10v11z"/>
            </svg>
            Scheduled Date: <strong>' . htmlspecialchars($scheduledate) . '</strong>
        </p>
        <p class="booking-time">
            <svg class="icon" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/>
            </svg>
            Session Time: <strong>' . htmlspecialchars(substr($scheduletime,0,5)) . '</strong>
        </p>';
                        
                        // Show completed by info if available
                        if($status == 'Complete' && !empty($completed_at)) {
                            echo '<p class="booking-time">
                                    <svg class="icon" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M9 11l3 3L22 4"/>
                                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                    </svg>
                                    Completed: <strong>' . date('M j, Y g:i A', strtotime($completed_at)) . '</strong>
                                  </p>';
                        }
                        
                        echo '</div>';
                        
                        // Show appropriate button based on status
                        if($status == 'Scheduled') {
                            // Only show cancel button for scheduled appointments
                            echo '<a href="?action=drop&id=' . $appoid . '&title=' . urlencode($title) . '&doc=' . urlencode($docname) . '">
                                    <button class="cancel-btn" onclick="return confirm(\'Are you sure you want to cancel this appointment?\')">Cancel Booking</button>
                                  </a>';
                        } elseif($status == 'Complete') {
                            // Show view medical record button for completed appointments
                            echo '<a href="ehr_records.php?id=' . $appoid . '" class="view-record-btn">
                                    View Medical Record
                                  </a>';
                        } elseif($status == 'Cancelled') {
                            // Show cancelled message
                            echo '<div style="text-align: center; padding: 12px; background: rgba(239, 68, 68, 0.1); border-radius: 8px; color: #dc2626; font-weight: 500; margin-top: 10px;">
                                    ✗ Appointment Cancelled
                                  </div>';
                        }
                        
                        echo '</div>';
                    }
                    ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📅</div>
                    <h3 class="empty-title">No Bookings Found</h3>
                    <p class="empty-subtitle">
                        <?php echo isset($_POST['sheduledate']) && !empty($_POST['sheduledate']) ? 
                            "You have no bookings on ".htmlspecialchars($_POST['sheduledate']).". Try a different date or view all bookings." : 
                            "You don't have any appointments booked yet. Browse sessions to make your first booking."; ?>
                    </p>
                    <div style="display: flex; gap: 15px; justify-content: center;">
                        <a href="schedule.php" class="btn-primary-soft">Browse Sessions</a>
                        <?php if(isset($_POST['sheduledate'])): ?>
                            <a href="appointment.php" class="btn-primary-soft">Show All Bookings</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal Windows -->
    <?php 
    if(isset($_GET["action"]) && isset($_GET["id"]) && $_GET["action"] == 'booking-added'){
        $id = $_GET["id"];
        echo '
        <div id="popup1" class="overlay" style="display: flex;">
            <div class="popup">
                <a class="close" href="appointment.php">&times;</a>
                <h2>✅ Booking Successful!</h2>
                <div class="content">
                    Your appointment has been successfully booked.<br><br>
                    <strong>Appointment Number: '.$id.'</strong><br><br>
                    Please arrive 10 minutes before your scheduled time.
                </div>
                <div class="popup-actions">
                    <a href="appointment.php" class="popup-btn popup-btn-ok">View My Bookings</a>
                </div>
            </div>
        </div>
        <script>
            document.getElementById("popup1").style.display = "flex";
        </script>';
    }
    ?>
    
    <script>
        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.overlay');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        };
    </script>
</body>
</html>