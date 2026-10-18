<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Management - Smart Clinic</title>
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

        /* Section Titles */
        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--primarycolor);
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--accentcolor);
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
            cursor: pointer;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }

        /* Form Container */
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

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--primarycolor);
            box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.1);
        }

        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 20px;
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

        /* Patient Table */
        .patient-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.08);
            animation: transitionIn-Y-bottom 0.6s;
        }

        .patient-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .patient-table th, .patient-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }

        .patient-table th {
            background: var(--primarycolor);
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .patient-table tr:hover {
            background: #f0fdfa;
        }

        .patient-table td {
            font-size: 14px;
            color: #334155;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn-sm {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .action-btn-sm:hover {
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

        /* Alerts */
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

        /* Hidden utility */
        .hidden {
            display: none;
        }

        /* Scroll */
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

        .patient-table {
            animation: transitionIn-Y-bottom 0.6s;
        }

        .stats-grid {
            animation: transitionIn-Y-bottom 0.5s;
        }

        .form-container {
            animation: transitionIn-Y-bottom 0.5s;
        }
    </style>
</head>
<body>
    <?php
    if(isset($_SESSION["user"])){
        if(($_SESSION["user"])=="" or $_SESSION['usertype']!='a'){
            header("location: ../login.php");
        }
    } else {
        // header("location: ../login.php");
    }
    
    include("../connection.php");

    // Handle patient registration
    if($_POST && isset($_POST['register_patient'])){
        $pname = $_POST['pname'];
        $pemail = $_POST['pemail'];
        $ppassword = $_POST['ppassword'];
        $paddress = $_POST['paddress'];
        $pnic = $_POST['pnic'];
        $pdob = $_POST['pdob'];
        $ptel = $_POST['ptel'];
        
        // Check if patient already exists
        $check_sql = "SELECT * FROM patient WHERE pemail = '$pemail' OR pnic = '$pnic'";
        $check_result = $database->query($check_sql);
        
        if($check_result->num_rows > 0){
            $error = "Patient with this email or NIC already exists!";
        } else {
            // Start transaction for multiple table inserts
            $database->begin_transaction();
            
            try {
                // Insert into patient table
                $sql = "INSERT INTO patient (pname, pemail, ppassword, paddress, pnic, pdob, ptel) 
                        VALUES ('$pname', '$pemail', '$ppassword', '$paddress', '$pnic', '$pdob', '$ptel')";
                $database->query($sql);
                $patient_id = $database->insert_id;
                
                // Also register in webuser table for authentication
                $webuser_sql = "INSERT INTO webuser (email, usertype) VALUES ('$pemail', 'p')";
                $database->query($webuser_sql);
                
                // Also register in users table if it exists and has required columns
                $users_check = $database->query("SHOW TABLES LIKE 'users'");
                if($users_check->num_rows > 0) {
                    $users_columns = $database->query("SHOW COLUMNS FROM users");
                    $has_phone = false;
                    $has_gender = false;
                    while($col = $users_columns->fetch_assoc()) {
                        if($col['Field'] == 'phone') $has_phone = true;
                        if($col['Field'] == 'gender') $has_gender = true;
                    }
                    
                    $users_sql = "INSERT INTO users (full_name, username, email, password, role";
                    $users_values = "VALUES ('$pname', '$pemail', '$pemail', '$ppassword', 'patient'";
                    
                    if($has_phone) {
                        $users_sql .= ", phone";
                        $users_values .= ", '$ptel'";
                    }
                    if($has_gender) {
                        $users_sql .= ", gender";
                        $users_values .= ", ''";
                    }
                    
                    $users_sql .= ") " . $users_values . ")";
                    $database->query($users_sql);
                }
                
                // Commit transaction
                $database->commit();
                $success = "Patient registered successfully! Patient ID: P-" . $patient_id;
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $database->rollback();
                $error = "Error registering patient: " . $e->getMessage();
            }
        }
    }
    ?>
    
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
                    <td class="menu-btn menu-icon-doctor">
                        <a href="staff.php" class="non-style-link-menu"><p class="menu-text">Staff Management</p></a>
                    </td>
                </tr>
                 <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor">
                        <a href="doctors.php" class="non-style-link-menu"><p class="menu-text">Doctor Management</p></a>
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
                    <td class="menu-btn menu-icon-patient menu-active">
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
                    <td colspan="4" class="nav-bar">
                        <div>
                            <p style="font-size: 28px;font-weight: 700;color: var(--primarycolor);margin:0;">Patient Management</p>
                            <p style="font-size: 14px;color: #64748b;margin-top:5px;">Manage clinic patients</p>
                        </div>
                        <div style="text-align: right;">
                            <p style="font-size: 14px;color: #64748b;margin:0;">Today's Date</p>
                            <p style="font-size: 16px;font-weight: 600;color: var(--primarydark);margin:5px 0 0 0;">
                                <?php echo date('F j, Y'); ?>
                            </p>
                        </div>
                    </td>
                </tr>

                <!-- Alerts -->
                <?php if(isset($success)): ?>
                    <tr>
                        <td colspan="4">
                            <div class="alert alert-success">
                                <?php echo $success; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php if(isset($error)): ?>
                    <tr>
                        <td colspan="4">
                            <div class="alert alert-error">
                                <?php echo $error; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>

                <!-- Quick Stats -->
                <tr>
                    <td colspan="4">
                        <?php
                        // Fixed queries to match your database schema
                        $total_patients = $database->query("SELECT COUNT(*) as count FROM patient")->fetch_assoc()['count'];
                        
                        // Since patient table doesn't have created_at, we'll use a different approach
                        // Count patients with appointments today as "active today"
                        $today = date('Y-m-d');
                        $active_today = $database->query("SELECT COUNT(DISTINCT p.pid) as count 
                                                         FROM patient p 
                                                         INNER JOIN appointment a ON p.pid = a.pid 
                                                         WHERE a.appodate = '$today'")->fetch_assoc()['count'];
                        
                        $active_appointments = $database->query("SELECT COUNT(*) as count FROM appointment WHERE appodate >= '$today'")->fetch_assoc()['count'];
                        $total_prescriptions = $database->query("SELECT COUNT(*) as count FROM prescriptions")->fetch_assoc()['count'];
                        ?>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $total_patients; ?></div>
                                <div class="stat-label">Total Patients</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $active_today; ?></div>
                                <div class="stat-label">Active Today</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $active_appointments; ?></div>
                                <div class="stat-label">Upcoming Appointments</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $total_prescriptions; ?></div>
                                <div class="stat-label">Total Prescriptions</div>
                            </div>
                        </div>
                    </td>
                </tr>

                <!-- Search and Add -->
                <tr>
                    <td colspan="4">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <form action="" method="post" style="display: flex; gap: 10px; flex: 1; max-width: 500px;">
                                <input type="search" name="search" class="form-group input" placeholder="Search Patient name or Email" list="patient" style="margin: 0;">
                                <datalist id="patient">
                                    <?php
                                        $list11 = $database->query("SELECT pname, pemail FROM patient");
                                        while($row = $list11->fetch_assoc()){
                                            echo "<option value='{$row['pname']}'>";
                                            echo "<option value='{$row['pemail']}'>";
                                        }
                                    ?>
                                </datalist>
                                <button type="submit" class="btn btn-primary" style="margin: 0;">Search</button>
                            </form>
                            <button onclick="togglePatientForm()" class="action-btn">+ Register New Patient</button>
                        </div>
                    </td>
                </tr>

                <!-- Patient Registration Form -->
                <tr>
                    <td colspan="4">
                        <div id="patientForm" class="form-container hidden">
                            <h3>Register New Patient</h3>
                            <form method="POST" action="">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label for="pname">Full Name *</label>
                                        <input type="text" id="pname" name="pname" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="pemail">Email Address *</label>
                                        <input type="email" id="pemail" name="pemail" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="ppassword">Password *</label>
                                        <input type="password" id="ppassword" name="ppassword" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="pnic">ID Number *</label>
                                        <input type="text" id="pnic" name="pnic" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="ptel">Telephone *</label>
                                        <input type="tel" id="ptel" name="ptel" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="pdob">Date of Birth *</label>
                                        <input type="date" id="pdob" name="pdob" required max="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    
                                    <div class="form-group" style="grid-column: span 2;">
                                        <label for="paddress">Address</label>
                                        <textarea id="paddress" name="paddress" rows="3"></textarea>
                                    </div>
                                </div>
                                
                                <div class="btn-group">
                                    <button type="submit" name="register_patient" class="btn btn-primary">Register Patient</button>
                                    <button type="button" onclick="togglePatientForm()" class="btn btn-primary-soft">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>

                <!-- Patient List -->
                <tr>
                    <td colspan="4">
                        <div class="patient-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Patient ID</th>
                                        <th>Name</th>
                                        <th>ID</th>
                                        <th>Telephone</th>
                                        <th>Email</th>
                                        <th>Date of Birth</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if($_POST && isset($_POST['search'])){
                                        $keyword = $_POST["search"];
                                        $sqlmain = "SELECT * FROM patient WHERE pemail='$keyword' OR pname='$keyword' OR pname LIKE '$keyword%' OR pname LIKE '%$keyword' OR pname LIKE '%$keyword%' ORDER BY pid DESC";
                                    } else {
                                        $sqlmain = "SELECT * FROM patient ORDER BY pid DESC";
                                    }
                                    
                                    $result = $database->query($sqlmain);
                                    
                                    if($result->num_rows == 0): ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 40px;">
                                                <p style="font-size:20px;color:rgb(49, 49, 49); margin-bottom:20px;">No patients found.</p>
                                                <button onclick="togglePatientForm()" class="btn btn-primary">Register First Patient</button>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php while($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td>P-<?php echo $row["pid"]; ?></td>
                                                <td><?php echo htmlspecialchars($row["pname"]); ?></td>
                                                <td><?php echo htmlspecialchars($row["pnic"]); ?></td>
                                                <td><?php echo htmlspecialchars($row["ptel"]); ?></td>
                                                <td><?php echo htmlspecialchars($row["pemail"]); ?></td>
                                                <td><?php echo $row["pdob"]; ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="?action=view&id=<?php echo $row['pid']; ?>" class="action-btn-sm edit-btn">View</a>
                                                        <a href="appointment.php?action=book&patient_id=<?php echo $row['pid']; ?>" class="action-btn-sm" style="background: var(--secondarycolor); color: white;">Book Appt</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- View Patient Details Popup -->
    <?php 
    if(isset($_GET['action']) && $_GET['action'] == 'view' && isset($_GET['id'])){
        $id = $_GET["id"];
        $sqlmain = "SELECT * FROM patient WHERE pid='$id'";
        $result = $database->query($sqlmain);
        if($result->num_rows > 0) {
            $row = $result->fetch_assoc();
    ?>
    <div id="popup1" class="overlay" style="display: block; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000;">
        <div class="popup" style="background: white; border-radius: 12px; padding: 30px; max-width: 600px; margin: 50px auto; animation: transitionIn-Y-bottom 0.5s;">
            <center>
                <a class="close" href="patient.php" style="float: right; font-size: 30px; text-decoration: none; color: var(--textcolor);">&times;</a>
                <div style="display: flex;justify-content: center;">
                    <table width="80%" class="sub-table" border="0">
                        <tr>
                            <td>
                                <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500; color: var(--primarycolor);">Patient Details</p>
                                <br><br>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">
                                <label class="form-label" style="font-weight: 600;">Patient ID: </label>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">
                                P-<?php echo $id; ?><br><br>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">
                                <label class="form-label" style="font-weight: 600;">Name: </label>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">
                                <?php echo htmlspecialchars($row["pname"]); ?><br><br>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">
                                <label class="form-label" style="font-weight: 600;">Email: </label>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">
                                <?php echo htmlspecialchars($row["pemail"]); ?><br><br>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">
                                <label class="form-label" style="font-weight: 600;">ID: </label>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">
                                <?php echo htmlspecialchars($row["pnic"]); ?><br><br>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">
                                <label class="form-label" style="font-weight: 600;">Telephone: </label>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">
                                <?php echo htmlspecialchars($row["ptel"]); ?><br><br>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">
                                <label class="form-label" style="font-weight: 600;">Address: </label>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">
                                <?php echo htmlspecialchars($row["paddress"]); ?><br><br>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">
                                <label class="form-label" style="font-weight: 600;">Date of Birth: </label>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">
                                <?php echo $row["pdob"]; ?><br><br>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <a href="patient.php"><input type="button" value="OK" class="btn btn-primary-soft"></a>
                            </td>
                        </tr>
                    </table>
                </div>
            </center>
        </div>
    </div>
    <?php 
        }
    } 
    ?>

    <script>
        function togglePatientForm() {
            const form = document.getElementById('patientForm');
            form.classList.toggle('hidden');
            
            if(!form.classList.contains('hidden')) {
                form.scrollIntoView({ behavior: 'smooth' });
            }
        }
        
        // Auto-hide success/error messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.alert');
            messages.forEach(message => {
                message.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>