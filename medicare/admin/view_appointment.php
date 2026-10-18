<?php
session_start();
if(isset($_SESSION["user"])){
    if(($_SESSION["user"])=="" or $_SESSION['usertype']!='a'){
        header("location: ../login.php");
    }
} else {
    header("location: ../login.php");
}

include("../connection.php");

// Get appointment ID from URL
$appointment_id = $_GET['id'] ?? 0;

if($appointment_id == 0){
    header("location: appointment.php");
    exit();
}

// Fetch appointment details
$sql = "SELECT a.appoid, a.apponum, a.appodate, p.pid, p.pname, p.pemail, p.ptel, p.pnic, p.pdob, p.paddress,
        d.docid, d.docname, d.docemail, d.doctel, s.scheduleid, s.title, s.scheduledate, s.scheduletime, s.nop
        FROM appointment a
        JOIN patient p ON a.pid = p.pid
        JOIN schedule s ON a.scheduleid = s.scheduleid
        JOIN doctor d ON s.docid = d.docid
        WHERE a.appoid = '$appointment_id'";

$result = $database->query($sql);

if($result->num_rows == 0){
    header("location: appointment.php");
    exit();
}

$appointment = $result->fetch_assoc();

// Calculate booked slots
$booked_slots_sql = "SELECT COUNT(*) as booked_count FROM appointment WHERE scheduleid = '{$appointment['scheduleid']}'";
$booked_result = $database->query($booked_slots_sql);
$booked_slots = $booked_result->fetch_assoc()['booked_count'];
$available_slots = $appointment['nop'] - $booked_slots;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointment - Smart Clinic Management System</title>
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

        /* Detail Container Styles */
        .detail-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);
            border-left: 4px solid var(--primarycolor);
        }

        .detail-header {
            border-bottom: 2px solid var(--accentcolor);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .detail-header h2 {
            font-size: 28px;
            font-weight: 700;
            color: var(--primarycolor);
            margin-bottom: 8px;
        }

        .detail-header p {
            font-size: 14px;
            color: #64748b;
            margin: 0;
        }

        .detail-section {
            margin-bottom: 30px;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid var(--primarycolor);
        }

        .detail-section h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--primarycolor);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
        }

        .detail-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 20px;
        }

        .detail-label {
            font-weight: 600;
            color: var(--textcolor);
            font-size: 14px;
            margin-bottom: 5px;
            display: block;
        }

        .detail-value {
            color: #334155;
            font-size: 15px;
            line-height: 1.5;
        }

        /* Buttons */
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(8, 145, 178, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }

        .btn-secondary {
            background: #64748b;
            color: white;
        }

        .btn-secondary:hover {
            background: #475569;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-back {
            background: #94a3b8;
            color: white;
        }

        .btn-back:hover {
            background: #64748b;
            transform: translateY(-2px);
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #e5e7eb;
            flex-wrap: wrap;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        /* Info Box */
        .availability-info {
            background: linear-gradient(135deg, #e7f3ff 0%, #d1e7ff 100%);
            border-left: 4px solid var(--primarycolor);
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(8, 145, 178, 0.1);
        }

        .availability-info strong {
            color: var(--primarycolor);
            font-size: 15px;
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

        .detail-container {
            animation: transitionIn-Y-over 0.5s;
        }

        .detail-section {
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
                    <td class="menu-btn menu-icon-schedule">
                        <a href="schedule.php" class="non-style-link-menu"><p class="menu-text">Schedule</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appoinment menu-active">
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

        <div class="dash-body">
            <div class="nav-bar">
                <div>
                    <p style="font-size: 28px;font-weight: 700;color: var(--primarycolor);margin:0;">Appointment Details</p>
                    <p style="font-size: 14px;color: #64748b;margin-top:5px;">Viewing appointment #<?php echo $appointment['apponum']; ?></p>
                </div>
                <div style="text-align: right;">
                    <p style="font-size: 14px;color: #64748b;margin:0;">Today's Date</p>
                    <p style="font-size: 16px;font-weight: 600;color: var(--primarydark);margin:5px 0 0 0;"><?php echo date('F j, Y'); ?></p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="appointment.php" class="action-btn">Back to Appointments</a>
                <a href="?action=reschedule&id=<?php echo $appointment_id; ?>" class="action-btn">Reschedule</a>
                <a href="cancel-appointment.php?id=<?php echo $appointment_id; ?>&apponum=<?php echo $appointment['apponum']; ?>&patient=<?php echo urlencode($appointment['pname']); ?>" 
                   onclick="return confirm('Are you sure you want to cancel this appointment?')" 
                   class="action-btn" style="background: #dc3545;">Cancel Appointment</a>
                <a href="?action=print&id=<?php echo $appointment_id; ?>" class="action-btn">Print Details</a>
            </div>

            <!-- Appointment Details Container -->
            <div class="detail-container">
                <!-- Appointment Header -->
                <div class="detail-header">
                    <h2>Appointment #<?php echo $appointment['apponum']; ?></h2>
                    <p>Booked on: <?php echo date('F j, Y', strtotime($appointment['appodate'])); ?></p>
                </div>

                <!-- Session Information -->
                <div class="detail-section">
                    <h3>Session Information</h3>
                    <div class="detail-row">
                        <div>
                            <span class="detail-label">Session Title:</span>
                            <div class="detail-value"><?php echo htmlspecialchars($appointment['title']); ?></div>
                        </div>
                        <div>
                            <span class="detail-label">Session Date & Time:</span>
                            <div class="detail-value">
                                <?php echo date('l, F j, Y', strtotime($appointment['scheduledate'])); ?><br>
                                <?php echo date('g:i A', strtotime($appointment['scheduletime'])); ?>
                            </div>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div>
                            <span class="detail-label">Doctor:</span>
                            <div class="detail-value">Dr. <?php echo htmlspecialchars($appointment['docname']); ?></div>
                        </div>
                        <div>
                            <span class="detail-label">Doctor Contact:</span>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($appointment['docemail']); ?><br>
                                <?php echo htmlspecialchars($appointment['doctel']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="availability-info">
                        <strong>Session Capacity:</strong> <?php echo $booked_slots; ?> / <?php echo $appointment['nop']; ?> slots booked
                        (<?php echo $available_slots; ?> slots available)
                    </div>
                </div>

                <!-- Patient Information -->
                <div class="detail-section">
                    <h3>Patient Information</h3>
                    <div class="detail-row">
                        <div>
                            <span class="detail-label">Patient Name:</span>
                            <div class="detail-value"><?php echo htmlspecialchars($appointment['pname']); ?></div>
                        </div>
                        <div>
                            <span class="detail-label">Patient ID:</span>
                            <div class="detail-value">P-<?php echo $appointment['pid']; ?></div>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div>
                            <span class="detail-label">Email:</span>
                            <div class="detail-value"><?php echo htmlspecialchars($appointment['pemail']); ?></div>
                        </div>
                        <div>
                            <span class="detail-label">Phone:</span>
                            <div class="detail-value"><?php echo htmlspecialchars($appointment['ptel']); ?></div>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div>
                            <span class="detail-label">NIC Number:</span>
                            <div class="detail-value"><?php echo htmlspecialchars($appointment['pnic']); ?></div>
                        </div>
                        <div>
                            <span class="detail-label">Date of Birth:</span>
                            <div class="detail-value"><?php echo date('F j, Y', strtotime($appointment['pdob'])); ?></div>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div style="grid-column: span 2;">
                            <span class="detail-label">Address:</span>
                            <div class="detail-value"><?php echo htmlspecialchars($appointment['paddress']); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Appointment Status -->
                <div class="detail-section">
                    <h3>Appointment Status</h3>
                    <div class="detail-row">
                        <div>
                            <span class="detail-label">Current Status:</span>
                            <div class="detail-value">
                                <span class="status-badge status-active">Scheduled</span>
                            </div>
                        </div>
                        <div>
                            <span class="detail-label">Appointment Date:</span>
                            <div class="detail-value"><?php echo date('F j, Y', strtotime($appointment['appodate'])); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="appointment.php" class="btn btn-back">Back to List</a>
                    <a href="?action=reschedule&id=<?php echo $appointment_id; ?>" class="btn btn-secondary">Reschedule</a>
                    <a href="cancel-appointment.php?id=<?php echo $appointment_id; ?>&apponum=<?php echo $appointment['apponum']; ?>&patient=<?php echo urlencode($appointment['pname']); ?>" 
                       onclick="return confirm('Are you sure you want to cancel this appointment?')" 
                       class="btn btn-danger">Cancel Appointment</a>
                    <a href="?action=print&id=<?php echo $appointment_id; ?>" class="btn btn-primary">Print Details</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Print functionality
        function printAppointmentDetails() {
            window.print();
        }

        // Check if print action is requested
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.get('action') === 'print') {
            window.print();
        }
    </script>
</body>
</html>