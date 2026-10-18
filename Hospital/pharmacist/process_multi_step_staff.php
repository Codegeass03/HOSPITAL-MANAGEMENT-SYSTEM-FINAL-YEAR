<?php
session_start();
include "includes/db_connect.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: add_staff.php");
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // ===== PERSONAL INFORMATION =====
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');

    // ===== PROFESSIONAL INFORMATION =====
    $profession = trim($_POST['profession'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    
    // Qualifications (arrays)
    $degrees = $_POST['degree'] ?? [];
    $institutions = $_POST['institution'] ?? [];
    $years_completed = $_POST['year_completed'] ?? [];
    
    // Licenses (arrays)
    $license_types = $_POST['license_type'] ?? [];
    $license_numbers = $_POST['license_number'] ?? [];
    $license_issue_dates = $_POST['license_issue_date'] ?? [];
    $license_expiry_dates = $_POST['license_expiry_date'] ?? [];

    // ===== EMPLOYMENT INFORMATION =====
    $employee_number = trim($_POST['employee_number'] ?? '');
    $department = $_POST['department'];
    $job_title = trim($_POST['job_title']);
    $hire_date = $_POST['hire_date'];
    $employment_type = $_POST['employment_type'] ?? 'full-time';
    $salary = $_POST['salary'] ?? 0;
    $work_schedule = trim($_POST['work_schedule'] ?? '');

    // ===== ACCESS & ROLES =====
    $username = trim($_POST['username']);
    $access_email = trim($_POST['access_email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $status = 'active';

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email) || empty($username) || empty($password)) {
        throw new Exception("All required fields must be filled.");
    }

    // Validate passwords match
    if ($password !== $confirm_password) {
        throw new Exception("Passwords do not match.");
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format.");
    }

    // Check if username exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        throw new Exception("Username already exists.");
    }

    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception("Email already exists.");
    }

    // Get role_id
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
    $stmt->execute([$role]);
    $role_data = $stmt->fetch();
    
    if (!$role_data) {
        throw new Exception("Invalid role selected.");
    }
    
    $role_id = $role_data['id'];

    // Generate employee number if empty
    if (empty($employee_number)) {
        $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(employee_number, 4) AS UNSIGNED)) as max_num FROM staff_profiles WHERE employee_number LIKE 'EMP%'");
        $result = $stmt->fetch();
        $next_num = ($result['max_num'] ?? 0) + 1;
        $employee_number = 'EMP' . str_pad($next_num, 5, '0', STR_PAD_LEFT);
    }

    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert into users table
    $stmt = $pdo->prepare("
        INSERT INTO users (role_id, username, email, password_hash, role, department, 
                          first_name, last_name, phone, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $role_id,
        $username,
        $access_email,
        $password_hash,
        $role,
        $department,
        $first_name,
        $last_name,
        $phone,
        $status
    ]);

    $user_id = $pdo->lastInsertId();

    // Insert into staff_profiles table
    $stmt = $pdo->prepare("
        INSERT INTO staff_profiles (user_id, employee_number, department, job_title, 
                                   hire_date, salary, address, date_of_birth, gender, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $user_id,
        $employee_number,
        $department,
        $job_title,
        $hire_date,
        $salary,
        $address,
        $date_of_birth,
        $gender
    ]);

    // Create doctor profile if role is Doctor
    if ($role === 'Doctor') {
        $stmt = $pdo->prepare("
            INSERT INTO doctor_profiles (user_id, specialty, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$user_id, $specialization]);
    }

    // Store qualifications in staff_qualifications table
    if (!empty($degrees[0])) {
        $stmt = $pdo->prepare("
            INSERT INTO staff_qualifications (user_id, degree_certification, institution, year_completed, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        for ($i = 0; $i < count($degrees); $i++) {
            if (!empty($degrees[$i])) {
                $stmt->execute([
                    $user_id,
                    $degrees[$i],
                    $institutions[$i] ?? '',
                    $years_completed[$i] ?? ''
                ]);
            }
        }
    }

    // Store licenses in staff_licenses table
    if (!empty($license_types[0])) {
        $stmt = $pdo->prepare("
            INSERT INTO staff_licenses (user_id, license_type, license_number, issue_date, expiry_date, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        for ($i = 0; $i < count($license_types); $i++) {
            if (!empty($license_types[$i])) {
                $stmt->execute([
                    $user_id,
                    $license_types[$i],
                    $license_numbers[$i] ?? '',
                    $license_issue_dates[$i] ?? null,
                    $license_expiry_dates[$i] ?? null
                ]);
            }
        }
    }

    // Commit transaction
    $pdo->commit();

    $_SESSION['success'] = "Staff member added successfully! Employee Number: $employee_number";
    header("Location: manage_users.php");
    exit();

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error'] = $e->getMessage();
    header("Location: add_staff.php");
    exit();
}
?>