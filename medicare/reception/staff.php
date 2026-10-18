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

// Handle form submissions
if($_POST){
    if(isset($_POST['register'])){
        // Register new staff member
        $firstname = $_POST['firstname'];
        $lastname = $_POST['lastname'];
        $username = $_POST['username'];
        $password = $_POST['password'];
        $role = $_POST['role'];
        $status = $_POST['status'];
        
        $email = $username . "@smartclinic.com";
        
        $usertype = 'u';
        switch($role) {
            case 'Administrator':
                $usertype = 'a';
                break;
            case 'Doctor':
                $usertype = 'd';
                break;
            case 'Pharmacist':
                $usertype = 'p';
                break;
            case 'Receptionist':
                $usertype = 'r';
                break;
        }
        
        $check_sql = "SELECT * FROM user_registration WHERE username = '$username'";
        $check_result = $database->query($check_sql);
        
        if($check_result->num_rows > 0){
            $error = "Username already exists!";
        } else {
            $database->begin_transaction();
            try {
                $sql1 = "INSERT INTO user_registration (firstname, lastname, username, password, role, status) 
                        VALUES ('$firstname', '$lastname', '$username', '$password', '$role', '$status')";
                $database->query($sql1);
                
                $sql2 = "INSERT INTO webuser (email, usertype) VALUES ('$email', '$usertype')";
                $database->query($sql2);
                
                if($role == 'Doctor' || $role == 'Pharmacist' || $role == 'Receptionist' || $role == 'Administrator'){
                    $full_name = $firstname . " " . $lastname;
                    $sql3 = "INSERT INTO users (full_name, username, email, password, role, phone, gender) 
                            VALUES ('$full_name', '$username', '$email', '$password', '$role', '', '')";
                    $database->query($sql3);
                    
                    if($role == 'Doctor') {
                        $doctor_sql = "INSERT INTO doctor (docemail, docname, docpassword, docnic, doctel, specialties) 
                                     VALUES ('$email', '$full_name', '$password', '', '', 1)";
                        $database->query($doctor_sql);
                    }
                }
                
                $database->commit();
                $success = "Staff member registered successfully!";
                
            } catch (Exception $e) {
                $database->rollback();
                $error = "Error: " . $e->getMessage();
            }
        }
    }
    
    if(isset($_POST['update'])){
        $id = $_POST['id'];
        $firstname = $_POST['firstname'];
        $lastname = $_POST['lastname'];
        $username = $_POST['username'];
        $password = $_POST['password'];
        $role = $_POST['role'];
        $status = $_POST['status'];
        
        $email = $username . "@smartclinic.com";
        
        $usertype = 'u';
        switch($role) {
            case 'Administrator':
                $usertype = 'a';
                break;
            case 'Doctor':
                $usertype = 'd';
                break;
            case 'Pharmacist':
                $usertype = 'p';
                break;
            case 'Receptionist':
                $usertype = 'r';
                break;
        }
        
        $database->begin_transaction();
        try {
            $sql1 = "UPDATE user_registration SET 
                    firstname = '$firstname', 
                    lastname = '$lastname', 
                    username = '$username', 
                    password = '$password', 
                    role = '$role', 
                    status = '$status' 
                    WHERE id = $id";
            $database->query($sql1);
            
            $sql2 = "UPDATE webuser SET email = '$email', usertype = '$usertype' WHERE email = (SELECT email FROM users WHERE username = '$username')";
            $database->query($sql2);
            
            $full_name = $firstname . " " . $lastname;
            $sql3 = "UPDATE users SET 
                    full_name = '$full_name', 
                    username = '$username', 
                    email = '$email', 
                    password = '$password', 
                    role = '$role' 
                    WHERE username = '$username'";
            $database->query($sql3);
            
            if($role == 'Doctor') {
                $doctor_sql = "UPDATE doctor SET 
                              docemail = '$email', 
                              docname = '$full_name', 
                              docpassword = '$password' 
                              WHERE docemail = '$email'";
                $database->query($doctor_sql);
            }
            
            $database->commit();
            $success = "Staff member updated successfully!";
            
        } catch (Exception $e) {
            $database->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
    
    if(isset($_POST['delete'])){
        $id = $_POST['id'];
        $user_sql = "SELECT username, role FROM user_registration WHERE id = $id";
        $user_result = $database->query($user_sql);
        
        if($user_result->num_rows > 0) {
            $user = $user_result->fetch_assoc();
            $username = $user['username'];
            $role = $user['role'];
            $email = $username . "@smartclinic.com";
            
            $database->begin_transaction();
            try {
                $sql1 = "DELETE FROM user_registration WHERE id = $id";
                $database->query($sql1);
                
                $sql2 = "DELETE FROM webuser WHERE email = '$email'";
                $database->query($sql2);
                
                $sql3 = "DELETE FROM users WHERE username = '$username'";
                $database->query($sql3);
                
                if($role == 'Doctor') {
                    $doctor_sql = "DELETE FROM doctor WHERE docemail = '$email'";
                    $database->query($doctor_sql);
                }
                
                $database->commit();
                $success = "Staff member deleted successfully!";
                
            } catch (Exception $e) {
                $database->rollback();
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

$staff_sql = "SELECT * FROM user_registration ORDER BY id DESC";
$staff_result = $database->query($staff_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Smart Clinic</title>
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
                    <td class="menu-btn menu-icon-dashbord">
                        <a href="index.php" class="non-style-link-menu"><p class="menu-text">Dashboard</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor menu-active">
                        <a href="staff.php" class="non-style-link-menu"><p class="menu-text">Staff Management</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-schedule">
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

        <div class="dash-body">
            <table border="0" width="100%" style="border-spacing: 0; margin:0; padding:0;">
                <tr>
                    <td colspan="2" class="nav-bar">
                        <p style="font-size: 28px; font-weight: 700; color: var(--primarycolor);">Staff Management</p>
                        <p style="font-size: 14px; color: #64748b; margin-top: 5px;">Manage clinic staff</p>
                    </td>
                </tr>

             

                <!-- Alerts -->
                <?php if(isset($success)): ?>
                    <tr>
                        <td colspan="2">
                            <div class="alert alert-success">
                                <?php echo $success; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php if(isset($error)): ?>
                    <tr>
                        <td colspan="2">
                            <div class="alert alert-error">
                                <?php echo $error; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>

                <tr>
                    <td colspan="2">
                        <button class="toggle-form" onclick="toggleStaffForm()">+ Add New Staff Member</button>
                        
                        <!-- Staff Registration Form -->
                        <div id="staffForm" class="form-container hidden">
                            <h3 id="formTitle">Register New Staff Member</h3>
                            <form method="POST" action="">
                                <input type="hidden" id="staffId" name="id">
                                
                                <div class="form-group">
                                    <label for="firstname">First Name</label>
                                    <input type="text" id="firstname" name="firstname" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="lastname">Last Name</label>
                                    <input type="text" id="lastname" name="lastname" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="username">Username</label>
                                    <input type="text" id="username" name="username" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password">Password</label>
                                    <input type="password" id="password" name="password" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="role">Role</label>
                                    <select id="role" name="role" required>
                                        <option value="">Select Role</option>
                                        <option value="Administrator">Administrator</option>
                                        <option value="Doctor">Doctor</option>
                                        <option value="Pharmacist">Pharmacist</option>
                                        <option value="Receptionist">Receptionist</option>
                                        <option value="Accountant">Accountant</option>
                                        <option value="User">User</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select id="status" name="status" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                
                                <div class="btn-group">
                                    <button type="submit" name="register" id="submitBtn" class="btn btn-primary">Register Staff</button>
                                    <button type="button" onclick="resetForm()" class="btn btn-primary-soft">Cancel</button>
                                </div>
                            </form>
                        </div>

                        <!-- Staff List -->
                        <div class="staff-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($staff_result->num_rows > 0): ?>
                                        <?php while($staff = $staff_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $staff['id']; ?></td>
                                                <td><?php echo $staff['firstname'] . ' ' . $staff['lastname']; ?></td>
                                                <td><?php echo $staff['username']; ?></td>
                                                <td><?php echo $staff['role']; ?></td>
                                                <td>
                                                    <span class="<?php echo $staff['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                                        <?php echo ucfirst($staff['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="action-btn edit-btn" 
                                                                onclick="editStaff(<?php echo $staff['id']; ?>, '<?php echo $staff['firstname']; ?>', '<?php echo $staff['lastname']; ?>', '<?php echo $staff['username']; ?>', '<?php echo $staff['password']; ?>', '<?php echo $staff['role']; ?>', '<?php echo $staff['status']; ?>')">
                                                            Edit
                                                        </button>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="id" value="<?php echo $staff['id']; ?>">
                                                            <button type="submit" name="delete" class="action-btn delete-btn" 
                                                                    onclick="return confirm('Are you sure you want to delete this staff member from all systems?')">
                                                                Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 20px;">
                                                No staff members found.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <script>
        function toggleStaffForm() {
            const form = document.getElementById('staffForm');
            form.classList.toggle('hidden');
            resetForm();
        }

        function resetForm() {
            document.getElementById('staffId').value = '';
            document.getElementById('firstname').value = '';
            document.getElementById('lastname').value = '';
            document.getElementById('username').value = '';
            document.getElementById('password').value = '';
            document.getElementById('role').value = '';
            document.getElementById('status').value = 'active';
            
            document.getElementById('formTitle').textContent = 'Register New Staff Member';
            document.getElementById('submitBtn').name = 'register';
            document.getElementById('submitBtn').textContent = 'Register Staff';
        }

        function editStaff(id, firstname, lastname, username, password, role, status) {
            document.getElementById('staffId').value = id;
            document.getElementById('firstname').value = firstname;
            document.getElementById('lastname').value = lastname;
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            document.getElementById('role').value = role;
            document.getElementById('status').value = status;
            
            document.getElementById('formTitle').textContent = 'Edit Staff Member';
            document.getElementById('submitBtn').name = 'update';
            document.getElementById('submitBtn').textContent = 'Update Staff';
            
            document.getElementById('staffForm').classList.remove('hidden');
            document.getElementById('staffForm').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>