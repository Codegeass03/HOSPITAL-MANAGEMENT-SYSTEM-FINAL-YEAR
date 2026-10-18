<?php
session_start();

if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

$message = "";

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add') {
            $docname = sanitize_input($_POST['docname']);
            $docemail = sanitize_input($_POST['docemail']);
            $docpassword = sanitize_input($_POST['docpassword']);
            $doctel = sanitize_input($_POST['doctel']);
            $specialties = sanitize_input($_POST['specialties']);
            
            // Check if email already exists
            $check = $database->query("SELECT * FROM doctor WHERE docemail='$docemail'");
            if ($check->num_rows > 0) {
                $message = "<div class='alert alert-error'>Email already exists!</div>";
            } else {
                $sql1 = "INSERT INTO doctor (docemail, docname, docpassword, doctel, specialties) 
                        VALUES ('$docemail', '$docname', '$docpassword', '$doctel', '$specialties')";
                $sql2 = "INSERT INTO webuser (email, usertype) VALUES ('$docemail', 'd')";
                
                if ($database->query($sql1) && $database->query($sql2)) {
                    $message = "<div class='alert alert-success'>Doctor added successfully!</div>";
                } else {
                    $message = "<div class='alert alert-error'>Error: " . $database->error . "</div>";
                }
            }
        }
        
        if ($action == 'delete') {
            $docid = sanitize_input($_POST['docid']);
            $docemail = $database->query("SELECT docemail FROM doctor WHERE docid='$docid'")->fetch_assoc()['docemail'];
            
            $sql1 = "DELETE FROM doctor WHERE docid='$docid'";
            $sql2 = "DELETE FROM webuser WHERE email='$docemail'";
            
            if ($database->query($sql1) && $database->query($sql2)) {
                $message = "<div class='alert alert-success'>Doctor deleted successfully!</div>";
            } else {
                $message = "<div class='alert alert-error'>Error: " . $database->error . "</div>";
            }
        }
        
        if ($action == 'edit') {
            $docid = sanitize_input($_POST['docid']);
            $docname = sanitize_input($_POST['docname']);
            $docemail = sanitize_input($_POST['docemail']);
            $doctel = sanitize_input($_POST['doctel']);
            $specialties = sanitize_input($_POST['specialties']);
            
            // Get old email first
            $old_email_result = $database->query("SELECT docemail FROM doctor WHERE docid='$docid'");
            $old_email = $old_email_result->fetch_assoc()['docemail'];
            
            // Update doctor table
            $sql1 = "UPDATE doctor SET docname='$docname', docemail='$docemail', doctel='$doctel', specialties='$specialties' 
                   WHERE docid='$docid'";
            
            // Update webuser table if email changed
            $sql2 = "";
            if ($old_email != $docemail) {
                $sql2 = "UPDATE webuser SET email='$docemail' WHERE email='$old_email'";
            }
            
            if ($database->query($sql1)) {
                if (!empty($sql2)) {
                    $database->query($sql2);
                }
                $message = "<div class='alert alert-success'>Doctor updated successfully!</div>";
            } else {
                $message = "<div class='alert alert-error'>Error: " . $database->error . "</div>";
            }
        }
    }
}

// Get all doctors
$doctors = $database->query("
    SELECT d.*, s.sname as specialty_name 
    FROM doctor d 
    LEFT JOIN specialties s ON d.specialties = s.id
    ORDER BY d.docname
");

// Get specialties for dropdown
$specialties = $database->query("SELECT * FROM specialties ORDER BY sname");

function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Management - Smart Clinic</title>
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

        /* Sidebar Styles - Matching Dashboard */
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

        .menu-icon-pharmacy::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z'/%3E%3Cpath d='M9 12h6'/%3E%3Cpath d='M12 9v6'/%3E%3C/svg%3E");
        }

        .menu-icon-billing::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cline x1='12' y1='1' x2='12' y2='23'/%3E%3Cpath d='M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'/%3E%3C/svg%3E");
        }

        .menu-icon-reports::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cline x1='18' y1='20' x2='18' y2='10'/%3E%3Cline x1='12' y1='20' x2='12' y2='4'/%3E%3Cline x1='6' y1='20' x2='6' y2='14'/%3E%3C/svg%3E");
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

        /* Form Container - Matching Dashboard Cards */
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);
            border-left: 4px solid var(--primarycolor);
            animation: transitionIn-Y-bottom 0.5s;
        }

        .form-container h3 {
            color: var(--primarycolor);
            margin-bottom: 20px;
            font-size: 20px;
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--textcolor);
            font-size: 14px;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primarycolor);
            box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.1);
        }

        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        /* Buttons - Matching Dashboard Style */
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(8, 145, 178, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            color: white;
        }

        .btn-primary-soft {
            background: rgba(8, 145, 178, 0.1);
            color: var(--primarycolor);
            border: 1px solid var(--primarycolor);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }

        /* Staff Table - Matching Dashboard Table Style */
        .staff-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.08);
            animation: transitionIn-Y-bottom 0.6s;
        }

        .staff-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .staff-table th, .staff-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }

        .staff-table th {
            background: var(--primarycolor);
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .staff-table tr:hover {
            background: #f0fdfa;
        }

        .staff-table td {
            font-size: 14px;
            color: #334155;
        }

        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 12px;
        }

        .status-inactive {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 12px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .action-btn:hover {
            transform: scale(1.05);
        }

        .edit-btn {
            background: var(--accentcolor);
            color: white;
        }

        .delete-btn {
            background: #dc3545;
            color: white;
        }

        /* Alerts - Matching Dashboard Color Scheme */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            animation: transitionIn-Y-over 0.5s;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        /* Toggle Form Button */
        .toggle-form {
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            margin-bottom: 20px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(8, 145, 178, 0.2);
            transition: all 0.3s;
        }

        .toggle-form:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }

        .hidden {
            display: none;
        }

        /* System Info Box */
        .system-info {
            background: rgba(8, 145, 178, 0.1);
            border-left: 4px solid var(--primarycolor);
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 14px;
            color: var(--textcolor);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            position: relative;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eaeaea;
        }

        .modal-header h2 {
            color: var(--primarycolor);
            font-size: 22px;
            font-weight: 700;
        }

        .close {
            font-size: 28px;
            cursor: pointer;
            color: var(--primarycolor);
            transition: all 0.3s;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close:hover {
            background: #f0fdfa;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eaeaea;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #cbd5e1;
        }

        .empty-state p {
            font-size: 14px;
            margin: 0;
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
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
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
                    <td class="menu-btn menu-icon-dashbord <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'menu-active' : ''; ?>">
                        <a href="index.php" class="non-style-link-menu"><p class="menu-text">Dashboard</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor <?php echo basename($_SERVER['PHP_SELF']) == 'staff.php' ? 'menu-active' : ''; ?>">
                        <a href="staff.php" class="non-style-link-menu"><p class="menu-text">Staff Management</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor <?php echo basename($_SERVER['PHP_SELF']) == 'doctors.php' ? 'menu-active' : ''; ?>">
                        <a href="doctors.php" class="non-style-link-menu"><p class="menu-text">Doctor Management</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-schedule <?php echo basename($_SERVER['PHP_SELF']) == 'schedule.php' ? 'menu-active' : ''; ?>">
                        <a href="schedule.php" class="non-style-link-menu"><p class="menu-text">Schedule</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appoinment <?php echo basename($_SERVER['PHP_SELF']) == 'appointment.php' ? 'menu-active' : ''; ?>">
                        <a href="appointment.php" class="non-style-link-menu"><p class="menu-text">Appointment</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-patient <?php echo basename($_SERVER['PHP_SELF']) == 'patient.php' ? 'menu-active' : ''; ?>">
                        <a href="patient.php" class="non-style-link-menu"><p class="menu-text">Patients</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-pharmacy <?php echo basename($_SERVER['PHP_SELF']) == 'pharmacy.php' ? 'menu-active' : ''; ?>">
                        <a href="pharmacy.php" class="non-style-link-menu"><p class="menu-text">Pharmacy</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-billing <?php echo basename($_SERVER['PHP_SELF']) == 'billing.php' ? 'menu-active' : ''; ?>">
                        <a href="billing.php" class="non-style-link-menu"><p class="menu-text">Billing</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-reports <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'menu-active' : ''; ?>">
                        <a href="reports.php" class="non-style-link-menu"><p class="menu-text">Reports</p></a>
                    </td>
                </tr>
            </table>
        </div>

        <div class="dash-body">
            <table border="0" width="100%" style="border-spacing: 0; margin:0; padding:0;">
                <tr>
                    <td colspan="2" class="nav-bar">
                        <p style="font-size: 28px; font-weight: 700; color: var(--primarycolor);">Doctor Management</p>
                        <p style="font-size: 14px; color: #64748b; margin-top: 5px;">Manage clinic doctors</p>
                    </td>
                </tr>

                <!-- Alerts -->
                <?php echo $message; ?>

                <tr>
                    <td colspan="2">
                        <button class="toggle-form" onclick="document.getElementById('addModal').style.display='flex'">+ Add New Doctor</button>
                        
                        <!-- Doctors List -->
                        <div class="staff-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Telephone</th>
                                        <th>Specialty</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($doctors->num_rows > 0) {
                                        while($row = $doctors->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td><strong>#" . htmlspecialchars($row['docid']) . "</strong></td>";
                                            echo "<td>" . htmlspecialchars($row['docname']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['docemail']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['doctel']) . "</td>";
                                            echo "<td><span class='status-active'>" . htmlspecialchars($row['specialty_name'] ?? 'N/A') . "</span></td>";
                                            echo "<td>
                                                    <div class='action-buttons'>
                                                        <button class='action-btn edit-btn' onclick='editDoctor(" . json_encode($row) . ")'>
                                                            Edit
                                                        </button>
                                                        <form method='POST' style='display:inline;'>
                                                            <input type='hidden' name='action' value='delete'>
                                                            <input type='hidden' name='docid' value='" . $row['docid'] . "'>
                                                            <button type='submit' class='action-btn delete-btn' onclick='return confirm(\"Are you sure you want to delete this doctor? This action cannot be undone.\")'>
                                                                Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                  </td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr>
                                                <td colspan='6' style='text-align: center; padding: 20px;'>
                                                    <div class='empty-state'>
                                                        <i class='fas fa-user-md'></i>
                                                        <p>No doctors found. Add your first doctor to get started.</p>
                                                    </div>
                                                </td>
                                              </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Add Doctor Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Doctor</h2>
                <span class="close" onclick="document.getElementById('addModal').style.display='none'">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Name:</label>
                    <input type="text" name="docname" required placeholder="Enter doctor's full name">
                </div>
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="docemail" required placeholder="doctor@smartclinic.com">
                </div>
                <div class="form-group">
                    <label>Password:</label>
                    <input type="password" name="docpassword" required placeholder="Create a secure password">
                </div>
                <div class="form-group">
                    <label>Telephone:</label>
                    <input type="tel" name="doctel" required placeholder="e.g., 0712345678">
                </div>
                <div class="form-group">
                    <label>Specialty:</label>
                    <select name="specialties" required>
                        <option value="">Select Specialty</option>
                        <?php
                        $specialties->data_seek(0);
                        while($spec = $specialties->fetch_assoc()) {
                            echo "<option value='" . $spec['id'] . "'>" . htmlspecialchars($spec['sname']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-primary-soft" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Doctor</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Doctor Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Doctor</h2>
                <span class="close" onclick="document.getElementById('editModal').style.display='none'">&times;</span>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="docid" id="edit_docid">
                <div class="form-group">
                    <label>Name:</label>
                    <input type="text" name="docname" id="edit_docname" required>
                </div>
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="docemail" id="edit_docemail" required>
                </div>
                <div class="form-group">
                    <label>Telephone:</label>
                    <input type="tel" name="doctel" id="edit_doctel" required>
                </div>
                <div class="form-group">
                    <label>Specialty:</label>
                    <select name="specialties" id="edit_specialties" required>
                        <?php
                        $specialties->data_seek(0);
                        while($spec = $specialties->fetch_assoc()) {
                            echo "<option value='" . $spec['id'] . "'>" . htmlspecialchars($spec['sname']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-primary-soft" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Doctor</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editDoctor(doctor) {
            document.getElementById('edit_docid').value = doctor.docid;
            document.getElementById('edit_docname').value = doctor.docname;
            document.getElementById('edit_docemail').value = doctor.docemail;
            document.getElementById('edit_doctel').value = doctor.doctel;
            document.getElementById('edit_specialties').value = doctor.specialties;
            document.getElementById('editModal').style.display = 'flex';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }

        // Mobile menu toggle (for future responsiveness)
        document.addEventListener('DOMContentLoaded', function() {
            // Add mobile responsiveness if needed
            if (window.innerWidth <= 768) {
                // Add mobile menu functionality here
            }
        });
    </script>
</body>
</html>