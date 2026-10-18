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

$doctorrow = $database->query("select * from doctor");
$schedulerow = $database->query("select * from schedule where scheduledate='$today'");
$appointmentrow = $database->query("select * from appointment where appodate>='$today'");

$sqlmain= "select * from schedule inner join appointment on schedule.scheduleid=appointment.scheduleid inner join patient on patient.pid=appointment.pid inner join doctor on schedule.docid=doctor.docid  where  patient.pid=$userid  and schedule.scheduledate>='$today' order by schedule.scheduledate asc";
$result= $database->query($sqlmain);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Patient Portal</title>
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

        /* Welcome Section */
        .welcome-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);
            margin-bottom: 30px;
            border-left: 5px solid var(--primarycolor);
        }

        .welcome-header {
            margin-bottom: 20px;
        }

        .welcome-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--primarydark);
            margin-bottom: 5px;
        }

        .welcome-header p {
            color: #64748b;
            font-size: 15px;
        }

        .search-box {
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            border-radius: 10px;
            padding: 25px;
            margin-top: 20px;
            color: white;
        }

        .search-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .search-form {
            display: flex;
            gap: 10px;
        }

        .search-input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 6px;
            background: rgba(255,255,255,0.2);
            color: white;
            font-size: 15px;
        }

        .search-input::placeholder {
            color: rgba(255,255,255,0.7);
        }

        .search-btn {
            padding: 12px 30px;
            background: white;
            color: var(--primarycolor);
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
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
            text-align: center;
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

        /* Appointments Table */
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--primarycolor);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .scroll {
            overflow-y: auto;
        }

        .sub-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
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
            padding: 15px;
            font-size: 14px;
            color: #334155;
        }

        .appointment-number {
            font-size: 18px;
            font-weight: 700;
            color: var(--primarycolor);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
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

        .dashbord-tables {
            animation: transitionIn-Y-over 0.5s;
        }

        .stats-grid {
            animation: transitionIn-Y-bottom 0.5s;
        }

        .welcome-container {
            animation: transitionIn-Y-bottom 0.4s;
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
            .search-form {
                flex-direction: column;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
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
                    <td class="menu-btn menu-icon-home menu-active">
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
                    <p style="font-size: 28px;font-weight: 700;color: var(--primarycolor);margin:0;">Patient Dashboard</p>
                    <p style="font-size: 14px;color: #64748b;margin-top:5px;">Welcome back, <?php echo $username; ?></p>
                </div>
                <div style="text-align: right;">
                    <p style="font-size: 14px;color: #64748b;margin:0;">Today's Date</p>
                    <p style="font-size: 16px;font-weight: 600;color: var(--primarydark);margin:5px 0 0 0;">
                        <?php echo $today; ?>
                    </p>
                </div>
            </div>

            <!-- Welcome & Search Section -->
            <div class="welcome-container">
                <div class="welcome-header">
                    <h1>Welcome, <?php echo $username; ?>!</h1>
                    <p>Track your appointments, find doctors, and manage your healthcare journey with us.</p>
                </div>
                
                <div class="search-box">
                    <div class="search-title">🔍 Find a Doctor</div>
                    <form action="schedule.php" method="post" class="search-form">
                        <input type="search" name="search" class="search-input" 
                               placeholder="Search Doctor and We will Find The Session Available" 
                               list="doctors">
                        <?php
                            echo '<datalist id="doctors">';
                            $list11 = $database->query("select docname from doctor");
                            for ($y=0;$y<$list11->num_rows;$y++){
                                $row00=$list11->fetch_assoc();
                                $d=$row00["docname"];
                                echo "<option value='$d'>";
                            };
                            echo '</datalist>';
                        ?>
                        <button type="submit" class="search-btn">Search</button>
                    </form>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $doctorrow->num_rows; ?></div>
                    <div class="stat-label">Available Doctors</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $appointmentrow->num_rows; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $schedulerow->num_rows; ?></div>
                    <div class="stat-label">Today's Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $result->num_rows; ?></div>
                    <div class="stat-label">Your Upcoming Appointments</div>
                </div>
            </div>

            <!-- Upcoming Appointments -->
            <div class="table-container">
                <div class="section-title">
                    📅 Your Upcoming Appointments
                </div>
                
                <?php if($result->num_rows > 0): ?>
                    <div class="scroll" style="height: 350px;">
                        <table class="sub-table" border="0">
                            <thead>
                                <tr>
                                    <th class="table-headin">Appointment #</th>
                                    <th class="table-headin">Session Title</th>
                                    <th class="table-headin">Doctor</th>
                                    <th class="table-headin">Scheduled Date & Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $result->data_seek(0);
                                for ($x=0; $x<$result->num_rows;$x++){
                                    $row=$result->fetch_assoc();
                                    $scheduleid=$row["scheduleid"];
                                    $title=$row["title"];
                                    $apponum=$row["apponum"];
                                    $docname=$row["docname"];
                                    $scheduledate=$row["scheduledate"];
                                    $scheduletime=$row["scheduletime"];
                                   
                                    echo '<tr>
                                        <td><div class="appointment-number">'.$apponum.'</div></td>
                                        <td>'.substr($title,0,30).'</td>
                                        <td>'.substr($docname,0,20).'</td>
                                        <td>'.substr($scheduledate,0,10).' '.substr($scheduletime,0,5).'</td>
                                    </tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📅</div>
                        <h3 class="empty-title">No Upcoming Appointments</h3>
                        <p class="empty-subtitle">
                            You don't have any scheduled appointments yet. 
                            Book your first appointment with one of our doctors!
                        </p>
                        <a href="schedule.php" class="btn-primary-soft">Find Available Sessions</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Links -->
            <div style="display: flex; justify-content: center; gap: 20px; margin-top: 30px;">
                <a href="doctors.php" class="btn-primary-soft">View All Doctors</a>
                <a href="schedule.php" class="btn-primary-soft">Browse Sessions</a>
                <a href="appointment.php" class="btn-primary-soft">My Bookings History</a>
            </div>
        </div>
    </div>
</body>
</html>