<?php
// Not used as i have not finished setting up patient regustration, only registered by hospital staff for now
session_start();

// Try different possible database connection files
if (file_exists("includes/db_connect.php")) {
    include "includes/db_connect.php";
} elseif (file_exists("config/database.php")) {
    include "config/database.php";
} elseif (file_exists("db_connect.php")) {
    include "db_connect.php";
} else {
    // Create connection if it doesn't exist
    $host = '127.0.0.1';
    $dbname = 'hospital';
    $username = 'root';
    $password = '';
    
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        $_SESSION['error'] = "Database connection failed!";
        header("Location: login.php?role=patient&error=Database%20connection%20failed!");
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get form data
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $username_input = trim($_POST['username']);
    $password = $_POST['password'];
    $role_from_form = $_POST['role'] ?? 'patient';
    
    // Validate required fields
    $required_fields = [
        'first_name', 'last_name', 'email', 'phone', 
        'date_of_birth', 'gender', 'username', 'password'
    ];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $_SESSION['error'] = "Please fill in all required fields!";
            header("Location: login.php?role=patient&error=Please%20fill%20in%20all%20required%20fields!");
            exit();
        }
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format!";
        header("Location: login.php?role=patient&error=Invalid%20email%20format!");
        exit();
    }
    
    // Calculate age
    try {
        $birthDate = new DateTime($date_of_birth);
        $today = new DateTime();
        
        if ($birthDate > $today) {
            $_SESSION['error'] = "Date of birth cannot be in the future!";
            header("Location: login.php?role=patient&error=Date%20of%20birth%20cannot%20be%20in%20the%20future!");
            exit();
        }
        
        $age = $today->diff($birthDate)->y;
    } catch (Exception $e) {
        $_SESSION['error'] = "Invalid date format!";
        header("Location: login.php?role=patient&error=Invalid%20date%20format!");
        exit();
    }
    
    // Generate patient ID
    $patient_id = 'PT' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Check if username or email already exists in users table
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check_stmt->bind_param("ss", $username_input, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            throw new Exception("Username or email already exists!");
        }
        
        // 2. Check if patient already exists
        $check_patient = $conn->prepare("SELECT id FROM patients WHERE email = ? OR phone = ?");
        $check_patient->bind_param("ss", $email, $phone);
        $check_patient->execute();
        $check_patient_result = $check_patient->get_result();
        
        if ($check_patient_result->num_rows > 0) {
            throw new Exception("A patient with this email or phone already exists!");
        }
        
        // 3. Insert into patients table
        $patient_stmt = $conn->prepare("
            INSERT INTO patients (
                patient_id, first_name, last_name, date_of_birth, age, 
                gender, phone, email, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $patient_stmt->bind_param(
            "ssssisss", 
            $patient_id, $first_name, $last_name, $date_of_birth, 
            $age, $gender, $phone, $email
        );
        
        if (!$patient_stmt->execute()) {
            throw new Exception("Failed to create patient record: " . $conn->error);
        }
        
        $patient_db_id = $conn->insert_id;
        
        // 4. Insert into users table
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Get role_id for Patient
        $role_stmt = $conn->prepare("SELECT id FROM roles WHERE name = 'Patient'");
        $role_stmt->execute();
        $role_result = $role_stmt->get_result();
        $role_row = $role_result->fetch_assoc();
        $role_id = $role_row['id'] ?? 9;
        
        $user_stmt = $conn->prepare("
            INSERT INTO users (
                role_id, username, email, password_hash, role, 
                first_name, last_name, phone, status, created_at
            ) VALUES (?, ?, ?, ?, 'Patient', ?, ?, ?, 'active', NOW())
        ");
        $user_stmt->bind_param(
            "issssss", 
            $role_id, $username_input, $email, $hashed_password, 
            $first_name, $last_name, $phone
        );
        
        if (!$user_stmt->execute()) {
            throw new Exception("Failed to create user account: " . $conn->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        // Set success message and auto-login the patient
        $user_id = $conn->insert_id;
        
        // Start patient session
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username_input;
        $_SESSION['role'] = 'Patient';
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name'] = $last_name;
        $_SESSION['email'] = $email;
        $_SESSION['phone'] = $phone;
        $_SESSION['patient_id'] = $patient_db_id;
        $_SESSION['patient_number'] = $patient_id;
        $_SESSION['patient_name'] = $first_name . ' ' . $last_name;
        
        // Update last login
        $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $update_stmt->bind_param("i", $user_id);
        $update_stmt->execute();
        
        // Redirect to patient dashboard
        $_SESSION['success'] = "Registration successful! Welcome, " . $first_name . "!";
        header("Location: patient/dashboard.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        
        // Set error message
        $_SESSION['error'] = $e->getMessage();
        header("Location: login.php?role=patient&error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    // If not POST request, redirect to login page
    header("Location: login.php?role=patient");
    exit();
}
?>