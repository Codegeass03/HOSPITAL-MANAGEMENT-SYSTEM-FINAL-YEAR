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

$sqlmain = "select * from patient where pemail=?";
$stmt = $database->prepare($sqlmain);
$stmt->bind_param("s", $useremail);
$stmt->execute();
$result = $stmt->get_result();
$userfetch = $result->fetch_assoc();
$userid = $userfetch["pid"];
$username = $userfetch["pname"];

date_default_timezone_set('Asia/Kolkata');
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Details - Review Booking</title>
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

        /* Session Details Container */
        .booking-container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);
            margin-bottom: 30px;
            border-left: 5px solid var(--primarycolor);
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
        }

        .booking-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--primarydark);
        }

        .booking-subtitle {
            font-size: 16px;
            color: #64748b;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .session-details-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 30px;
            border: 1px solid rgba(8, 145, 178, 0.1);
        }

        .appointment-card {
            background: linear-gradient(135deg, var(--primarycolor) 0%, var(--primarydark) 100%);
            border-radius: 12px;
            padding: 30px;
            color: white;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .detail-section {
            margin-bottom: 20px;
        }

        .detail-label {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-value {
            font-size: 18px;
            font-weight: 600;
            color: var(--textcolor);
            padding-left: 24px;
        }

        .doctor-info {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid rgba(8, 145, 178, 0.1);
        }

        .appointment-number {
            font-size: 64px;
            font-weight: 800;
            color: white;
            margin: 20px 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .appointment-label {
            font-size: 16px;
            color: rgba(255,255,255,0.9);
            margin-bottom: 10px;
        }

        .fee-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-top: 15px;
        }

        /* Form and Buttons */
        .booking-form {
            background: #f8fafc;
            border-radius: 12px;
            padding: 30px;
            margin-top: 30px;
        }

        .form-actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
        }

        .btn-book {
            padding: 15px 40px;
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }

        .btn-book:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(8, 145, 178, 0.4);
        }

        .btn-back {
            padding: 15px 40px;
            background: rgba(8, 145, 178, 0.1);
            color: var(--primarycolor);
            border: 1px solid rgba(8, 145, 178, 0.2);
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-back:hover {
            background: rgba(8, 145, 178, 0.2);
            transform: translateY(-2px);
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

        .booking-container {
            animation: transitionIn-Y-bottom 0.5s;
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
            .details-grid {
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
            .form-actions {
                flex-direction: column;
                align-items: center;
            }
            .btn-book, .btn-back {
                width: 100%;
                max-width: 300px;
            }
        }

        /* Icons for details */
        .icon {
            width: 18px;
            height: 18px;
            filter: invert(38%) sepia(93%) saturate(387%) hue-rotate(152deg) brightness(94%) contrast(87%);
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
                    <td class="menu-btn menu-icon-session menu-active">
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
                    <h1 class="nav-title">Review Booking</h1>
                    <p class="nav-subtitle">Confirm your appointment details</p>
                </div>
                <div class="nav-date">
                    <p class="date-label">Today's Date</p>
                    <p class="date-value"><?php echo date('F j, Y'); ?></p>
                </div>
            </div>
            
            <?php
            if(isset($_GET["id"])){
                $id=$_GET["id"];
                
                $sqlmain= "select * from schedule inner join doctor on schedule.docid=doctor.docid where schedule.scheduleid=? order by schedule.scheduledate desc";
                $stmt = $database->prepare($sqlmain);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if($result->num_rows > 0){
                    $row=$result->fetch_assoc();
                    $scheduleid=$row["scheduleid"];
                    $title=$row["title"];
                    $docname=$row["docname"];
                    $docemail=$row["docemail"];
                    $scheduledate=$row["scheduledate"];
                    $scheduletime=$row["scheduletime"];
                    
                    $sql2="select * from appointment where scheduleid=$id";
                    $result12= $database->query($sql2);
                    $apponum=($result12->num_rows)+1;
            ?>
            
            <div class="booking-container">
                <div class="booking-header">
                    <div>
                        <h2 class="booking-title">Session Booking Review</h2>
                        <p class="booking-subtitle">Please review the details before confirming your booking</p>
                    </div>
                </div>
                
                <form action="booking-complete.php" method="post" class="booking-form">
                    <input type="hidden" name="scheduleid" value="<?php echo $scheduleid; ?>">
                    <input type="hidden" name="apponum" value="<?php echo $apponum; ?>">
                    <input type="hidden" name="date" value="<?php echo $today; ?>">
                    
                    <div class="details-grid">
                        <!-- Session Details -->
                        <div class="session-details-card">
                            <h3 style="color: var(--primarycolor); margin-bottom: 25px; font-size: 22px;">📋 Session Details</h3>
                            
                            <div class="detail-section">
                                <div class="detail-label">
                                    <svg class="icon" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M21 10h-8V2h8v8zm-2-6h-4v4h4V4zM3 10h8V2H3v8zm2-6h4v4H5V4zM13 22h8v-8h-8v8zm2-6h4v4h-4v-4zM3 22h8v-8H3v8zm2-6h4v4H5v-4z"/>
                                    </svg>
                                    Session Title
                                </div>
                                <div class="detail-value"><?php echo htmlspecialchars($title); ?></div>
                            </div>
                            
                            <div class="detail-section">
                                <div class="detail-label">
                                    <svg class="icon" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M8 6v3H5v14h14V9h-3V6H8zm2 3h4V8h-4v1zm7 12H7V10h10v11z"/>
                                    </svg>
                                    Scheduled Date
                                </div>
                                <div class="detail-value"><?php echo htmlspecialchars($scheduledate); ?></div>
                            </div>
                            
                            <div class="detail-section">
                                <div class="detail-label">
                                    <svg class="icon" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/>
                                    </svg>
                                    Session Time
                                </div>
                                <div class="detail-value"><?php echo htmlspecialchars(substr($scheduletime,0,5)); ?> (24h format)</div>
                            </div>
                            
                            <div class="doctor-info">
                                <h4 style="color: var(--primarycolor); margin-bottom: 15px;">👨‍⚕️ Doctor Information</h4>
                                <div class="detail-section">
                                    <div class="detail-label">Doctor Name</div>
                                    <div class="detail-value">Dr. <?php echo htmlspecialchars($docname); ?></div>
                                </div>
                                <div class="detail-section">
                                    <div class="detail-label">Doctor Email</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($docemail); ?></div>
                                </div>
                            </div>
                            
                            <div style="margin-top: 25px; padding-top: 20px; border-top: 2px solid rgba(8, 145, 178, 0.1);">
                                <div class="detail-section">
                                    <div class="detail-label">
                                        <svg class="icon" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.31-8.86l-3.42 3.42-1.42-1.42 3.42-3.42-3.42-3.42 1.42-1.42 3.42 3.42 3.42-3.42 1.42 1.42-3.42 3.42 3.42 3.42-1.42 1.42-3.42-3.42z"/>
                                        </svg>
                                        Consultation Fee
                                    </div>
                                    <div class="detail-value" style="font-size: 24px; color: var(--primarycolor);">KSH. 500.00</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Appointment Card -->
                        <div class="appointment-card">
                            <h3 style="color: white; margin-bottom: 15px;">Your Appointment Number</h3>
                            <div class="appointment-number">#<?php echo $apponum; ?></div>
                            <p class="appointment-label">This is your unique appointment identifier</p>
                            <div class="fee-badge">Fee: KSH. 500.00</div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="schedule.php" class="btn-back">← Back to Sessions</a>
                        <button type="submit" class="btn-book" name="booknow">Confirm Booking</button>
                    </div>
                </form>
            </div>
            
            <?php
                } else {
                    echo '<div class="empty-state" style="text-align: center; padding: 50px 20px; background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);">
                            <div style="font-size: 64px; margin-bottom: 20px; color: rgba(8, 145, 178, 0.3);">❌</div>
                            <h3 style="font-size: 20px; color: var(--textcolor); margin-bottom: 10px;">Session Not Found</h3>
                            <p style="color: #64748b; margin-bottom: 25px; max-width: 400px; margin-left: auto; margin-right: auto;">
                                The session you are looking for does not exist or has been removed.
                            </p>
                            <a href="schedule.php" class="btn-back" style="display: inline-block;">← Back to Sessions</a>
                          </div>';
                }
            } else {
                echo '<div class="empty-state" style="text-align: center; padding: 50px 20px; background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);">
                        <div style="font-size: 64px; margin-bottom: 20px; color: rgba(8, 145, 178, 0.3);">⚠️</div>
                        <h3 style="font-size: 20px; color: var(--textcolor); margin-bottom: 10px;">Invalid Request</h3>
                        <p style="color: #64748b; margin-bottom: 25px; max-width: 400px; margin-left: auto; margin-right: auto;">
                            No session ID provided. Please select a session from the sessions page.
                        </p>
                        <a href="schedule.php" class="btn-back" style="display: inline-block;">← Back to Sessions</a>
                      </div>';
            }
            ?>
        </div>
    </div>
</body>
</html>