<?php
session_start();

if(isset($_SESSION["user"])){
    if(($_SESSION["user"])=="" or $_SESSION['usertype']!='a'){
        header("location: ../login.php");
    }
}else{
    header("location: ../login.php");
}

include("../connection.php");

date_default_timezone_set('Africa/Nairobi');
$today = date('Y-m-d');

$list110 = $database->query("select * from schedule;");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule - Smart Clinic Management System</title>
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

        /* Sidebar */
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

.menu-icon-doctor::before {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2'/%3E%3Ccircle cx='9' cy='7' r='4'/%3E%3Cpath d='M22 21v-2a4 4 0 0 0-3-3.87'/%3E%3Cpath d='M16 3.13a4 4 0 0 1 0 7.75'/%3E%3C/svg%3E");
}

.menu-icon-schedule::before {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3E%3Cline x1='16' y1='2' x2='16' y2='6'/%3E%3Cline x1='8' y1='2' x2='8' y2='6'/%3E%3Cline x1='3' y1='10' x2='21' y2='10'/%3E%3C/svg%3E");
}

.menu-icon-appoinment::before {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M9 11l3 3L22 4'/%3E%3Cpath d='M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'/%3E%3C/svg%3E");
}

.menu-icon-patient::before {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2'/%3E%3Ccircle cx='12' cy='7' r='4'/%3E%3C/svg%3E");
}

.menu-icon-pharmacy::before {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z'/%3E%3Cpath d='M9 12h6'/%3E%3Cpath d='M12 9v6'/%3E%3C/svg%3E");
}

.menu-icon-billing::before {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cline x1='12' y1='1' x2='12' y2='23'/%3E%3Cpath d='M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'/%3E%3C/svg%3E");
}

.menu-icon-reports::before {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cline x1='18' y1='20' x2='18' y2='10'/%3E%3Cline x1='12' y1='20' x2='12' y2='4'/%3E%3Cline x1='6' y1='20' x2='6' y2='14'/%3E%3C/svg%3E");
}

/* Menu Layout */
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

/* Remove borders between menu items */
.menu-row {
    margin: 0;
    border: none;
}

.menu-btn {
    border: none;
    margin: 0;
}

/* Profile container styling */
.profile-container {
    padding: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    margin-bottom: 10px;
}

.profile-container tr {
    border: none;
}

.profile-container td {
    border: none;
    padding: 5px 0;
}
        .non-style-link-menu {
            text-decoration: none;
            color: white;
            display: block;
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
    background: rgba(255,255,255,0.1);
    border-radius: 3px;
}

.menu::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.1);
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

/* .logout-btn:hover {
    background: rgba(0, 0, 0, 0);
} */

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
    background: rgba(0, 0, 0, 0.1);
}

.menu-btn.menu-active {
    background: rgba(0, 0, 0, 0.2);
    border-left: 4px solid white;
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
            grid-template-columns: auto 1fr auto 1fr auto;
            gap: 15px;
            align-items: center;
        }

        .filter-label {
            font-weight: 600;
            color: var(--primarydark);
            font-size: 14px;
        }

        .input-text, .box {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .input-text:focus, .box:focus {
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
            border-right: 1px solid rgba(255,255,255,0.2);
        }

        th:last-child {
            border-right: none;
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
            border-right: 1px solid #f1f5f9;
        }

        td:last-child {
            border-right: none;
        }

        /* Modal/Overlay */
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

        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .notfound-container {
            text-align: center;
            padding: 60px 20px;
        }

        .content {
            padding: 20px 0;
            text-align: center;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar Menu -->
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
                            <p class="profile-title">Administrator</p>
                            <p class="profile-subtitle">admin@edoc.com</p>
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
            <td class="menu-btn menu-icon-doctor">
                <a href="staff.php" class="non-style-link-menu"><p class="menu-text">Staff Management</p></a>
            </td>
        </tr>
        <tr class="menu-row">
            <td class="menu-btn menu-icon-schedule menu-active">
                <a href="schedule.php" class="non-style-link-menu"><p class="menu-text">Schedule</p></a>
            </td>
        </tr>
        <tr class="menu-row">
            <td class="menu-btn menu-icon-appoinment">
                <a href="appointment.php" class="non-style-link-menu"><p class="menu-text">Appointment</p></a>
            </td>
        </tr>
        <tr class="menu-row">
            <td class="menu-btn menu-icon-patient">
                <a href="patient.php" class="non-style-link-menu"><p class="menu-text">Patients</p></a>
            </td>
        </tr>
        <tr class="menu-row">
            <td class="menu-btn menu-icon-pharmacy">
                <a href="pharmacy.php" class="non-style-link-menu"><p class="menu-text">Pharmacy</p></a>
            </td>
        </tr>
        <tr class="menu-row">
            <td class="menu-btn menu-icon-billing">
                <a href="billing.php" class="non-style-link-menu"><p class="menu-text">Billing</p></a>
            </td>
        </tr>
        <tr class="menu-row">
            <td class="menu-btn menu-icon-reports">
                <a href="reports.php" class="non-style-link-menu"><p class="menu-text">Reports</p></a>
            </td>
        </tr>
    </table>
</div>

        <!-- Main Content -->
        <div class="dash-body">
            <div class="nav-bar">
                <div>
                    <p style="font-size: 28px;font-weight: 700;color: var(--primarycolor);margin:0;">Schedule Manager</p>
                    <p style="font-size: 14px;color: #64748b;margin-top:5px;">Manage doctor schedules and sessions</p>
                </div>
                <div style="text-align: right;">
                    <p style="font-size: 14px;color: #64748b;margin:0;">Today's Date</p>
                    <p style="font-size: 16px;font-weight: 600;color: var(--primarydark);margin:5px 0 0 0;"><?php echo date('F j, Y'); ?></p>
                </div>
            </div>

            <!-- Header with Add Button -->
            <div class="header-actions">
                <h2 style="color: var(--primarycolor);">All Sessions (<?php echo $list110->num_rows; ?>)</h2>
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

            <!-- Filter Container -->
            <div class="filter-container">
                <form action="" method="post">
                    <div class="filter-row">
                        <label class="filter-label">Date:</label>
                        <input type="date" name="sheduledate" class="input-text">
                        
                        <label class="filter-label">Doctor:</label>
                        <select name="docid" class="box">
                            <option value="" disabled selected hidden>Choose Doctor Name</option>
                            <?php 
                            $list11 = $database->query("select * from doctor order by docname asc;");
                            for ($y=0;$y<$list11->num_rows;$y++){
                                $row00=$list11->fetch_assoc();
                                $sn=$row00["docname"];
                                $id00=$row00["docid"];
                                echo "<option value=".$id00.">$sn</option>";
                            }
                            ?>
                        </select>
                        
                        <input type="submit" name="filter" value="Filter" class="btn btn-primary">
                    </div>
                </form>
            </div>

            <?php
            if($_POST){
                $sqlpt1="";
                if(!empty($_POST["sheduledate"])){
                    $sheduledate=$_POST["sheduledate"];
                    $sqlpt1=" schedule.scheduledate='$sheduledate' ";
                }

                $sqlpt2="";
                if(!empty($_POST["docid"])){
                    $docid=$_POST["docid"];
                    $sqlpt2=" doctor.docid=$docid ";
                }
                
                $sqlmain= "select schedule.scheduleid,schedule.title,doctor.docname,schedule.scheduledate,schedule.scheduletime,schedule.nop from schedule inner join doctor on schedule.docid=doctor.docid ";
                $sqllist=array($sqlpt1,$sqlpt2);
                $sqlkeywords=array(" where "," and ");
                $key2=0;
                foreach($sqllist as $key){
                    if(!empty($key)){
                        $sqlmain.=$sqlkeywords[$key2].$key;
                        $key2++;
                    };
                };
            }else{
                $sqlmain= "select schedule.scheduleid,schedule.title,doctor.docname,schedule.scheduledate,schedule.scheduletime,schedule.nop from schedule inner join doctor on schedule.docid=doctor.docid order by schedule.scheduledate desc";
            }
            ?>

            <!-- Sessions Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Session Title</th>
                            <th>Doctor</th>
                            <th>Scheduled Date & Time</th>
                            <th>Max Appointments</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $database->query($sqlmain);

                        if($result->num_rows==0){
                            echo '<tr>
                                <td colspan="5">
                                    <div class="notfound-container">
                                        <p style="font-size:20px;color:#64748b;">No sessions found matching your criteria</p>
                                        <a href="schedule.php" style="text-decoration:none;">
                                            <button class="btn btn-primary" style="margin-top:20px;">Show All Sessions</button>
                                        </a>
                                    </div>
                                </td>
                            </tr>';
                        }
                        else{
                            for ($x=0; $x<$result->num_rows;$x++){
                                $row=$result->fetch_assoc();
                                $scheduleid=$row["scheduleid"];
                                $title=$row["title"];
                                $docname=$row["docname"];
                                $scheduledate=$row["scheduledate"];
                                $scheduletime=$row["scheduletime"];
                                $nop=$row["nop"];
                                
                                echo '<tr>
                                    <td><strong>'.substr($title,0,40).'</strong></td>
                                    <td>'.substr($docname,0,30).'</td>
                                    <td>'.date('M d, Y', strtotime($scheduledate)).' at '.substr($scheduletime,0,5).'</td>
                                    <td style="text-align:center;"><strong>'.$nop.'</strong></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?action=view&id='.$scheduleid.'" style="text-decoration:none;">
                                                <button class="btn btn-info" style="padding:8px 16px;">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                        <circle cx="12" cy="12" r="3"></circle>
                                                    </svg>
                                                    View
                                                </button>
                                            </a>
                                            <a href="?action=drop&id='.$scheduleid.'&name='.$title.'" style="text-decoration:none;">
                                                <button class="btn btn-danger" style="padding:8px 16px;">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="3 6 5 6 21 6"></polyline>
                                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                    </svg>
                                                    Remove
                                                </button>
                                            </a>
                                        </div>
                                    </td>
                                </tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php
    if($_GET){
        $id=$_GET["id"];
        $action=$_GET["action"];
        
        if($action=='add-session'){
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
                        
                        <div class="label-td">
                            <label for="docid" class="form-label">Select Doctor</label>
                            <select name="docid" class="box" required>
                                <option value="" disabled selected hidden>Choose Doctor Name</option>';
                                
            $list11 = $database->query("select * from doctor order by docname asc;");
            for ($y=0;$y<$list11->num_rows;$y++){
                $row00=$list11->fetch_assoc();
                $sn=$row00["docname"];
                $id00=$row00["docid"];
                echo "<option value=".$id00.">$sn</option>";
            }
            
            echo '      </select>
                        </div>
                        
                        <div class="label-td">
                            <label for="nop" class="form-label">Number of Patients/Appointment Numbers</label>
                            <input type="number" name="nop" class="input-text" min="1" placeholder="Maximum appointments for this session" required>
                        </div>
                        
                        <div class="label-td">
                            <label for="date" class="form-label">Session Date</label>
                            <input type="date" name="date" class="input-text" min="'.date('Y-m-d').'" required>
                        </div>
                        
                        <div class="label-td">
                            <label for="time" class="form-label">Schedule Time</label>
                            <input type="time" name="time" class="input-text" required>
                        </div>
                        
                        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px;">
                            <button type="reset" class="btn" style="background: #e5e7eb; color: #374151;">Reset</button>
                            <button type="submit" name="shedulesubmit" class="btn btn-primary">Create Session</button>
                        </div>
                    </form>
                </div>
            </div>';
            
        }elseif($action=='session-added'){
            $titleget=$_GET["title"];
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
                        <p style="color: #64748b; margin-bottom: 30px;">'.substr($titleget,0,50).' was successfully scheduled.</p>
                        <a href="schedule.php" style="text-decoration: none;">
                            <button class="btn btn-primary">OK</button>
                        </a>
                    </div>
                </div>
            </div>';
            
        }elseif($action=='drop'){
            $nameget=$_GET["name"];
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
                        <p style="color: #64748b; margin-bottom: 30px;">You want to delete: <br><strong>'.substr($nameget,0,50).'</strong></p>
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
            
        }elseif($action=='view'){
            $sqlmain= "select schedule.scheduleid,schedule.title,doctor.docname,schedule.scheduledate,schedule.scheduletime,schedule.nop from schedule inner join doctor on schedule.docid=doctor.docid where schedule.scheduleid=$id";
            $result= $database->query($sqlmain);
            $row=$result->fetch_assoc();
            $docname=$row["docname"];
            $scheduleid=$row["scheduleid"];
            $title=$row["title"];
            $scheduledate=$row["scheduledate"];
            $scheduletime=$row["scheduletime"];
            $nop=$row['nop'];

            $sqlmain12= "select * from appointment inner join patient on patient.pid=appointment.pid inner join schedule on schedule.scheduleid=appointment.scheduleid where schedule.scheduleid=$id;";
            $result12= $database->query($sqlmain12);
            
            echo '
            <div id="popup1" class="overlay">
                <div class="popup" style="max-width: 900px;">
                    <a class="close" href="schedule.php">&times;</a>
                    <h2 style="color: var(--primarycolor); margin-bottom: 30px;">Session Details</h2>
                    
                    <div style="background: #f0fdfa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <div style="margin-bottom: 15px;">
                            <label class="form-label">Session Title:</label>
                            <p style="font-size: 16px; color: var(--textcolor);">'.$title.'</p>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label class="form-label">Doctor:</label>
                            <p style="font-size: 16px; color: var(--textcolor);">'.$docname.'</p>
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
                            <table>
                                <thead>
                                    <tr>
                                        <th>Patient ID</th>
                                        <th>Patient Name</th>
                                        <th>Appointment Number</th>
                                        <th>Telephone</th>
                                    </tr>
                                </thead>
                                <tbody>';
            
            if($result12->num_rows==0){
                echo '<tr>
                    <td colspan="4">
                        <div class="notfound-container">
                            <p style="color: #64748b;">No patients registered for this session yet</p>
                        </div>
                    </td>
                </tr>';
            }
            else{
                for ($x=0; $x<$result12->num_rows;$x++){
                    $row=$result12->fetch_assoc();
                    $apponum=$row["apponum"];
                    $pid=$row["pid"];
                    $pname=$row["pname"];
                    $ptel=$row["ptel"];
                    
                    echo '<tr>
                        <td>'.substr($pid,0,15).'</td>
                        <td><strong>'.substr($pname,0,30).'</strong></td>
                        <td style="text-align:center;"><span style="background: var(--accentcolor); color: white; padding: 4px 12px; border-radius: 20px; font-weight: 600;">'.$apponum.'</span></td>
                        <td>'.substr($ptel,0,15).'</td>
                    </tr>';
                }
            }
            
            echo '          </tbody>
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
    ?>
</body>
</html>