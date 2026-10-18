<?php
session_start();

// my database connection files
if (file_exists("includes/db_connect.php")) {
    include "includes/db_connect.php";
} elseif (file_exists("config/database.php")) {
    include "config/database.php";
} elseif (file_exists("db_connect.php")) {
    include "db_connect.php";
} else {
    // Creates a connection if none of the files exist
    $host = '127.0.0.1';
    $dbname = 'hospital';
    $username = 'root';
    $password = '';
    
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        $_SESSION['error'] = "Database connection failed!";
        header("Location: login.php");
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = $_POST["password"];
    $role_from_form = $_POST["role"] ?? 'guest';
    
// Checks if this is a patient login
if ($role_from_form === 'patient') {
    // Check if user exists
    $stmt = $conn->prepare("
        SELECT u.*, p.id as patient_db_id, p.patient_id as patient_number
        FROM users u 
        LEFT JOIN patients p ON (
            (u.email = p.email COLLATE utf8mb4_unicode_ci) OR 
            (u.phone = p.phone COLLATE utf8mb4_unicode_ci)
        )
        WHERE (u.username = ? OR u.email = ?) 
        AND u.role = 'Patient' 
        AND u.status = 'active'
    ");
    $stmt->bind_param("ss", $username, $username);

        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Authentication successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['department'] = $user['department'];
            $_SESSION['phone'] = $user['phone'];
            
            // Store patient information if available
            if ($user['patient_db_id']) {
                $_SESSION['patient_id'] = $user['patient_db_id'];
                $_SESSION['patient_number'] = $user['patient_number'];
                $_SESSION['patient_name'] = $user['first_name'] . ' ' . $user['last_name'];
            }
            
            // Update last login
            $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $update_stmt->bind_param("i", $user['id']);
            $update_stmt->execute();
            
            // Redirect to patient dashboard
            $_SESSION['success'] = "Welcome back, " . $user['first_name'] . "!";
            header("Location: patient/dashboard.php");
            exit();
            
        } else {
            $_SESSION['error'] = "Invalid username or password!";
            header("Location: login.php?role=patient&error=Invalid%20username%20or%20password!");
            exit();
        }
    } else {
        // Handle other roles/staff logins
        $stmt = $conn->prepare("
            SELECT id, username, email, password_hash, role, first_name, last_name, 
                   department, phone, status 
            FROM users 
            WHERE (username = ? OR email = ?) 
            AND role = ? 
            AND status = 'active'
        ");
        
        // Map role from form to actual role names in database
        $role_map = [
            'super-admin' => 'Super Admin',
            'admin' => 'Admin',
            'doctor' => 'Doctor',
            'nurse' => 'Nurse',
            'pharmacist' => 'Pharmacist',
            'lab' => 'Lab Technician',
            'accountant' => 'Accountant',
            'receptionist' => 'Receptionist'
        ];
        
        $db_role = $role_map[$role_from_form] ?? ucfirst($role_from_form);
        
        $stmt->bind_param("sss", $username, $username, $db_role);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Authentication successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['department'] = $user['department'];
            
            // Update last login
            $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $update_stmt->bind_param("i", $user['id']);
            $update_stmt->execute();
            
            // Redirect based on role
            $roleRedirects = [
                'Super Admin' => 'super_admin/dashboard.php',
                'Admin' => 'admin/dashboard.php',
                'Doctor' => 'doctor/dashboard.php',
                'Nurse' => 'nurse/dashboard.php',
                'Pharmacist' => 'pharmacy/dashboard.php',
                'Lab Technician' => 'lab/dashboard.php',
                'Accountant' => 'accounts/dashboard.php',
                'Receptionist' => 'reception/dashboard.php',
                'Patient' => 'patient/dashboard.php'
            ];
            
            $redirect = $roleRedirects[$user['role']] ?? 'dashboard.php';
            
            $_SESSION['success'] = "Welcome back, " . $user['first_name'] . "!";
            header("Location: " . $redirect);
            exit();
            
        } else {
            $_SESSION['error'] = "Invalid username or password!";
            header("Location: login.php?role=" . urlencode($role_from_form) . "&error=Invalid%20username%20or%20password!");
            exit();
        }
    }
} else {
    header("Location: index.php");
    exit();
}
?>