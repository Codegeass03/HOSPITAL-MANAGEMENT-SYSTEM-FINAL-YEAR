<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Management - Smart Clinic Management System</title>
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

        .menu-container {
            width: 100%;
        }

        .profile-container {
            padding: 20px;
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
            margin: 0;
            border: none;
        }

        .menu-btn {
            padding: 15px 20px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 15px;
            border: none;
            margin: 0;
        }

        /* Remove hover effect */
        .menu-btn:hover {
            background: transparent;
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

        /* Section Titles */
        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--primarycolor);
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--accentcolor);
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

        /* Form Container */
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--textcolor);
            font-size: 14px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-grid-full {
            grid-column: span 2;
        }

        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .appointment-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .action-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-view {
            background: var(--accentcolor);
            color: white;
        }

        .btn-view:hover {
            background: var(--primarylight);
        }

        .btn-start {
            background: #10b981;
            color: white;
        }

        .btn-start:hover {
            background: #059669;
        }

        .btn-cancel {
            background: #ef4444;
            color: white;
        }

        .btn-cancel:hover {
            background: #dc2626;
        }

        /* Alerts */
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

        /* Header Actions */
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .hidden {
            display: none;
        }

        .scroll {
            overflow-y: auto;
            max-height: 500px;
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

        .notfound-container {
            text-align: center;
            padding: 60px 20px;
        }

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

        .table-container {
            animation: transitionIn-Y-over 0.5s;
        }

        .stats-grid {
            animation: transitionIn-Y-bottom 0.5s;
        }

        .form-container {
            animation: transitionIn-Y-bottom 0.6s;
        }
    </style>
</head>
<body>
    <?php

    // Display success/error messages
    if(isset($_GET['success'])){
        echo '<div class="alert alert-success">' . htmlspecialchars($_GET['success']) . '</div>';
    }
    if(isset($_GET['error'])){
        echo '<div class="alert alert-error">' . htmlspecialchars($_GET['error']) . '</div>';
    }

    if(isset($_SESSION["user"])){
        if(($_SESSION["user"])=="" or $_SESSION['usertype']!='a'){
            header("location: ../login.php");
        }
    } else {
        // header("location: ../login.php");
    }
    
    include("../connection.php");

    // Handle appointment actions
    if($_POST){
        // Book new appointment
        if(isset($_POST['book_appointment'])){
            $patient_id = $_POST['patient_id'];
            $schedule_id = $_POST['schedule_id'];
            $appointment_date = $_POST['appointment_date'];
            $appointment_reason = $_POST['appointment_reason'];
            
            // Check if patient exists
            $patient_check = $database->query("SELECT * FROM patient WHERE pid = '$patient_id'");
            if($patient_check->num_rows == 0){
                $error = "Patient not found!";
            } else {
                // Check schedule availability
                $schedule_check = $database->query("SELECT s.*, d.docid,
                    (SELECT COUNT(*) FROM appointment a WHERE a.scheduleid = s.scheduleid) as booked_count
                    FROM schedule s 
                    JOIN doctor d ON s.docid = d.docid
                    WHERE s.scheduleid = '$schedule_id'");
                
                if($schedule_check->num_rows > 0){
                    $schedule = $schedule_check->fetch_assoc();
                    $max_patients = $schedule['nop'];
                    $booked_count = $schedule['booked_count'];
                    $doctor_id = $schedule['docid'];
                    
                    if($booked_count < $max_patients){
                        // Generate appointment number
                        $appointment_number = $booked_count + 1;
                        
                        // Start transaction for multiple table inserts
                        $database->begin_transaction();
                        
                        try {
                            // Insert into main appointment table
                            $sql = "INSERT INTO appointment (pid, scheduleid, apponum, appodate) 
                                    VALUES ('$patient_id', '$schedule_id', '$appointment_number', '$appointment_date')";
                            
                            if($database->query($sql)){
                                $appointment_id = $database->insert_id;
                                
                                // Check if appointments table exists and handle the insert properly
                                $appointments_check = $database->query("SHOW TABLES LIKE 'appointments'");
                                if($appointments_check->num_rows > 0){
                                    // Get the correct doctor_id from doctors table that matches the foreign key
                                    $doctor_check = $database->query("SELECT doctor_id FROM doctors WHERE docid = '$doctor_id' OR doctor_id = '$doctor_id'");
                                    
                                    if($doctor_check->num_rows > 0){
                                        $doctor_row = $doctor_check->fetch_assoc();
                                        $valid_doctor_id = $doctor_row['doctor_id'];
                                        
                                        $appointment_datetime = $schedule['scheduledate'] . ' ' . $schedule['scheduletime'];
                                        
                                        $appointments_sql = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_reason, status) 
                                                           VALUES ('$patient_id', '$valid_doctor_id', '$appointment_datetime', '$appointment_reason', 'scheduled')";
                                        $database->query($appointments_sql);
                                    } else {
                                        // If no matching doctor found in doctors table, don't insert into appointments table
                                        // or create the doctor record first
                                        $error = "Doctor not found in doctors table. Please ensure the doctor is properly registered.";
                                        $database->rollback();
                                        throw new Exception($error);
                                    }
                                }
                                
                                // Commit transaction
                                $database->commit();
                                $success = "Appointment booked successfully! Appointment #: " . $appointment_number;
                            } else {
                                throw new Exception("Error booking appointment: " . $database->error);
                            }
                        } catch (Exception $e) {
                            $database->rollback();
                            $error = $e->getMessage();
                        }
                    } else {
                        $error = "This session is fully booked!";
                    }
                } else {
                    $error = "Schedule not found!";
                }
            }
        }
        
        // Update appointment status
        if(isset($_POST['update_status'])){
            $appointment_id = $_POST['appointment_id'];
            $new_status = $_POST['status'];
            $notes = $_POST['notes'] ?? '';
            
            // Update in appointments table if it exists
            $appointments_check = $database->query("SHOW TABLES LIKE 'appointments'");
            if($appointments_check->num_rows > 0){
                $update_sql = "UPDATE appointments SET status = '$new_status'";
                if(!empty($notes)){
                    $update_sql .= ", notes = '$notes'";
                }
                $update_sql .= " WHERE appointment_id = '$appointment_id'";
                $database->query($update_sql);
            }
            
            $success = "Appointment status updated to: " . ucfirst($new_status);
        }
    }
    ?>
    
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
                                    <p class="profile-title">Receptionist</p>
                                    <p class="profile-subtitle">reception@smartclinic.com</p>
                                    
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
            <!-- Clean Header without lines -->
            <div style="margin-bottom: 30px;">
                <p style="font-size: 28px;font-weight: 700;color: var(--primarycolor);margin:0;">Appointment Manager</p>
                <p style="font-size: 14px;color: #64748b;margin-top:5px;">Manage patient appointments and schedules</p>
            </div>

            <!-- Statistics Overview -->
            <?php
            $total_appointments = $database->query("SELECT COUNT(*) as count FROM appointment")->fetch_assoc()['count'];
            $today_appointments = $database->query("SELECT COUNT(*) as count FROM appointment WHERE appodate = CURDATE()")->fetch_assoc()['count'];
            $upcoming_appointments = $database->query("SELECT COUNT(*) as count FROM appointment a 
                                                      JOIN schedule s ON a.scheduleid = s.scheduleid 
                                                      WHERE s.scheduledate >= CURDATE()")->fetch_assoc()['count'];
            
            // Safely check appointments table
            $pending_appointments = 0;
            $appointments_table_check = $database->query("SHOW TABLES LIKE 'appointments'");
            if($appointments_table_check->num_rows > 0) {
                $pending_result = $database->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'scheduled'");
                $pending_appointments = $pending_result->fetch_assoc()['count'];
            }
            ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_appointments; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $today_appointments; ?></div>
                    <div class="stat-label">Today's Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $upcoming_appointments; ?></div>
                    <div class="stat-label">Upcoming Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $pending_appointments; ?></div>
                    <div class="stat-label">Pending Appointments</div>
                </div>
            </div>

            <!-- Header with Add Button -->
            <div class="header-actions">
                <h2 style="color: var(--primarycolor);">All Appointments (<?php echo $total_appointments; ?>)</h2>
                <button onclick="toggleBookingForm()" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Book New Appointment
                </button>
            </div>

            <!-- Appointment Booking Form -->
            <div id="bookingForm" class="form-container hidden">
                <h2 style="color: var(--primarycolor); margin-bottom: 20px;">Book New Appointment</h2>
                
                <?php if(isset($success)): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($error)): ?>
                    <div class="alert alert-error">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="patient_id" class="form-label">Select Patient *</label>
                            <select id="patient_id" name="patient_id" class="box" required>
                                <option value="">Choose Patient</option>
                                <?php
                                $patients = $database->query("SELECT pid, pname FROM patient ORDER BY pname");
                                while($patient = $patients->fetch_assoc()){
                                    echo "<option value='{$patient['pid']}'>{$patient['pname']} (P-{$patient['pid']})</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="schedule_id" class="form-label">Available Sessions *</label>
                            <select id="schedule_id" name="schedule_id" class="box" required>
                                <option value="">Choose Session</option>
                                <?php
                                $schedules = $database->query("SELECT s.scheduleid, s.title, s.scheduledate, s.scheduletime, s.nop, d.docname,
                                                              (SELECT COUNT(*) FROM appointment a WHERE a.scheduleid = s.scheduleid) as booked_count
                                                              FROM schedule s 
                                                              JOIN doctor d ON s.docid = d.docid 
                                                              WHERE s.scheduledate >= CURDATE() 
                                                              ORDER BY s.scheduledate, s.scheduletime");
                                while($schedule = $schedules->fetch_assoc()){
                                    $available = $schedule['nop'] - $schedule['booked_count'];
                                    if($available > 0){
                                        echo "<option value='{$schedule['scheduleid']}'>
                                            {$schedule['title']} - Dr. {$schedule['docname']} 
                                            (" . date('M j, g:i A', strtotime($schedule['scheduledate'] . ' ' . $schedule['scheduletime'])) . ")
                                            - {$available} slots available
                                        </option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="appointment_date" class="form-label">Appointment Date *</label>
                            <input type="date" id="appointment_date" name="appointment_date" class="input-text" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group form-grid-full">
                            <label for="appointment_reason" class="form-label">Reason for Appointment</label>
                            <textarea id="appointment_reason" name="appointment_reason" placeholder="Brief reason for the appointment"></textarea>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="button" onclick="toggleBookingForm()" class="btn" style="background: #e5e7eb; color: #374151;">Cancel</button>
                        <button type="submit" name="book_appointment" class="btn btn-primary">Book Appointment</button>
                    </div>
                </form>
            </div>

            <!-- Filter Container -->
            <div class="filter-container">
                <form method="POST" action="">
                    <div class="filter-row">
                        <label class="filter-label">Date:</label>
                        <input type="date" name="sheduledate" class="input-text" value="<?php echo $_POST['sheduledate'] ?? ''; ?>">
                        
                        <label class="filter-label">Doctor:</label>
                        <select name="docid" class="box">
                            <option value="">All Doctors</option>
                            <?php 
                            $doctors = $database->query("SELECT * FROM doctor ORDER BY docname ASC");
                            while($doctor = $doctors->fetch_assoc()){
                                $selected = (isset($_POST['docid']) && $_POST['docid'] == $doctor['docid']) ? 'selected' : '';
                                echo "<option value='{$doctor['docid']}' $selected>{$doctor['docname']}</option>";
                            }
                            ?>
                        </select>
                        
                        <input type="submit" name="filter" value="Filter" class="btn btn-primary">
                    </div>
                </form>
            </div>

            <!-- Appointments Table -->
            <div class="table-container">
                <?php
                // Build the query based on filters - using only the main appointment table
                $sqlmain = "SELECT a.appoid, a.apponum, p.pname, p.ptel, d.docname, s.title, 
                           s.scheduledate, s.scheduletime, a.appodate
                           FROM appointment a 
                           JOIN schedule s ON a.scheduleid = s.scheduleid 
                           JOIN patient p ON a.pid = p.pid 
                           JOIN doctor d ON s.docid = d.docid 
                           WHERE 1=1";
                
                if(isset($_POST['filter'])){
                    if(!empty($_POST["sheduledate"])){
                        $sheduledate = $_POST["sheduledate"];
                        $sqlmain .= " AND s.scheduledate = '$sheduledate'";
                    }
                    if(!empty($_POST["docid"])){
                        $docid = $_POST["docid"];
                        $sqlmain .= " AND d.docid = '$docid'";
                    }
                }
                
                $sqlmain .= " ORDER BY s.scheduledate DESC, s.scheduletime DESC";
                
                $result = $database->query($sqlmain);
                ?>
                
                <?php if($result->num_rows == 0): ?>
                    <div class="notfound-container">
                        <p style="font-size:20px;color:#64748b;">No appointments found matching your criteria</p>
                        <button onclick="toggleBookingForm()" class="btn btn-primary" style="margin-top:20px;">
                            Book First Appointment
                        </button>
                    </div>
                <?php else: ?>
                    <div class="scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Appointment #</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Session</th>
                                    <th>Session Date & Time</th>
                                    <th>Appointment Date</th>
                                    <!-- <th>Actions</th> -->
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $result->fetch_assoc()): 
                                    $appoid = $row["appoid"];
                                    $apponum = $row["apponum"];
                                    $pname = $row["pname"];
                                    $ptel = $row["ptel"];
                                    $docname = $row["docname"];
                                    $title = $row["title"];
                                    $scheduledate = $row["scheduledate"];
                                    $scheduletime = $row["scheduletime"];
                                    $appodate = $row["appodate"];
                                ?>
                                <tr>
                                    <td style="text-align:center;font-weight:600;"><?php echo $apponum; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($pname); ?></strong><br>
                                        <small style="color:#666;"><?php echo $ptel; ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($docname); ?></td>
                                    <td><?php echo htmlspecialchars($title); ?></td>
                                    <td style="text-align:center;">
                                        <?php echo date('M j, Y', strtotime($scheduledate)); ?><br>
                                        <small><?php echo date('g:i A', strtotime($scheduletime)); ?></small>
                                    </td>
                                    <td style="text-align:center;"><?php echo $appodate; ?></td>
                                  <!-- In the appointments table section of appointment.php, update the action buttons section: -->
<!-- <td>
    <div class="appointment-actions">
        <a href="view_appointment.php?id=<?php echo $appoid; ?>" class="non-style-link">
            <button class="action-btn btn-view">View</button>
        </a>
        
        <?php 
        // Check if appointment is completed
        $status_check = $database->query("SELECT status FROM appointment WHERE appoid = '$appoid'");
        $is_completed = false;
        if($status_check->num_rows > 0) {
            $status_row = $status_check->fetch_assoc();
            $is_completed = ($status_row['status'] == 'Complete');
        }
        ?>
        
        <?php if(!$is_completed): ?>
            <a href="start_appointment.php?id=<?php echo $appoid; ?>" class="non-style-link">
                <button class="action-btn btn-start">Start Appointment</button>
            </a>
            <a href="delete_appointment.php?id=<?php echo $appoid; ?>" 
               class="non-style-link"
               onclick="return confirm('Are you sure you want to cancel appointment #<?php echo $apponum; ?> for <?php echo htmlspecialchars($pname); ?>? This action cannot be undone.')">
                <button class="action-btn btn-cancel">Cancel</button>
            </a>
        <?php else: ?>
            <button class="action-btn" style="background: #22c55e; color: white; cursor: not-allowed;" disabled>
                Completed
            </button>
            <button class="action-btn" style="background: #94a3b8; color: white; cursor: not-allowed;" disabled>
                View EHR
            </button>
        <?php endif; ?>
    </div>
</td> -->
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleBookingForm() {
            const form = document.getElementById('bookingForm');
            form.classList.toggle('hidden');
            
            // Scroll to form when opening
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
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }

        /* Module Cards */
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .module-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-top: 3px solid var(--primarycolor);
        }

        .module-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 20px rgba(8, 145, 178, 0.15);
        }

        .module-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .module-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primarydark);
            margin-bottom: 12px;
        }

        .module-description {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .module-link {
            display: inline-block;
            padding: 10px 20px;
            background: var(--primarycolor);
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .module-link:hover {
            background: var(--primarydark);
            transform: scale(1.05);
        }

        /* Tables */
        .dashbord-tables {
            width: 100%;
            margin-top: 30px;
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
            padding: 12px 15px;
            font-size: 14px;
            color: #334155;
        }

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

        .modules-grid {
            animation: transitionIn-Y-bottom 0.6s;
        }
    </style>