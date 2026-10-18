<?php
session_start();

// Include database connection
include "includes/db_connect.php";

// Check if $pdo is defined
if (!isset($pdo)) {
    $_SESSION['error'] = "Database connection failed.";
    header("Location: manage_users.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Get and sanitize form inputs
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $phone = trim($_POST['phone']);
        $role = trim($_POST['role']);
        $department = trim($_POST['department']);
        $status = isset($_POST['status']) && !empty($_POST['status']) ? $_POST['status'] : 'active';
        
        // Handle TEXT fields that are NOT NULL - must have a value even if empty string
        $specialization = isset($_POST['specialization']) && !empty($_POST['specialization']) ? trim($_POST['specialization']) : '';
        $schedule = isset($_POST['schedule']) && !empty($_POST['schedule']) ? trim($_POST['schedule']) : '';
        
        // Validation array
        $errors = [];
        
        // Validate required fields
        if (empty($first_name)) {
            $errors[] = "First name is required";
        }
        
        if (empty($last_name)) {
            $errors[] = "Last name is required";
        }
        
        if (empty($username)) {
            $errors[] = "Username is required";
        } elseif (strlen($username) < 3) {
            $errors[] = "Username must be at least 3 characters long";
        }
        
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address";
        }
        
        if (empty($password)) {
            $errors[] = "Password is required";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long";
        }
        
        if (empty($phone)) {
            $errors[] = "Phone number is required";
        }
        
        if (empty($role)) {
            $errors[] = "Role is required";
        }
        
        if (empty($department)) {
            $errors[] = "Department is required";
        }
        
        // Get role_id from roles table based on role name
        $role_id = null;
        if (!empty($role)) {
            $stmt = $pdo->prepare("SELECT id FROM roles WHERE LOWER(name) = LOWER(?)");
            $stmt->execute([$role]);
            $role_data = $stmt->fetch();
            
            if ($role_data) {
                $role_id = $role_data['id'];
            } else {
                // If role doesn't exist, try to find any role or use a default
                $stmt = $pdo->prepare("SELECT id FROM roles LIMIT 1");
                $stmt->execute();
                $role_data = $stmt->fetch();
                if ($role_data) {
                    $role_id = $role_data['id'];
                } else {
                    $errors[] = "No roles found in the system. Please add roles first.";
                }
            }
        }
        
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = "Username already exists. Please choose a different username.";
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Email already exists. Please use a different email address.";
        }
        
        // Check if phone already exists
        if (!empty($phone)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetch()) {
                $errors[] = "Phone number already exists in the system.";
            }
        }
        
        // If there are validation errors, redirect back with error message
        if (!empty($errors)) {
            $_SESSION['error'] = implode(", ", $errors);
            header("Location: manage_users.php");
            exit();
        }
        
        // Hash the password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user into the database - matching your exact table structure
        $stmt = $pdo->prepare("
            INSERT INTO users (
                role_id, 
                username, 
                email, 
                password_hash, 
                role, 
                department, 
                first_name, 
                last_name, 
                phone, 
                specialization,
                schedule,
                status,
                last_login,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        
        $result = $stmt->execute([
            $role_id, 
            $username, 
            $email, 
            $password_hash, 
            $role, 
            $department,
            $first_name, 
            $last_name, 
            $phone,
            $specialization,
            $schedule,
            $status
        ]);
        
        if ($result) {
            $user_id = $pdo->lastInsertId();
            
            // Verify the insert actually happened
            $verify = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $verify->execute([$user_id]);
            if ($verify->fetch()) {
                $_SESSION['success'] = "Staff member '{$first_name} {$last_name}' has been added successfully!";
                error_log("New staff member added successfully: {$first_name} {$last_name} (ID: {$user_id}, Username: {$username})");
            } else {
                $_SESSION['error'] = "Insert reported success but record not found. Please check database manually.";
                error_log("WARNING: Insert succeeded but verification failed for user: {$username}");
            }
        } else {
            $_SESSION['error'] = "Failed to add staff member. Database insert failed.";
            error_log("Failed to insert staff member. Error info: " . print_r($stmt->errorInfo(), true));
        }
        
        header("Location: manage_users.php");
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        error_log("Database error in process_add_staff.php: " . $e->getMessage());
        error_log("SQL State: " . $e->getCode());
        header("Location: manage_users.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "An error occurred: " . $e->getMessage();
        error_log("General error in process_add_staff.php: " . $e->getMessage());
        header("Location: manage_users.php");
        exit();
    }
} else {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: manage_users.php");
    exit();
}
?>
<style>
.flash-message {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 5px;
    color: white;
    z-index: 1000;
    max-width: 400px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    animation: slideIn 0.3s ease-out;
}

.flash-success {
    background-color: #28a745;
    border-left: 4px solid #1e7e34;
}

.flash-error {
    background-color: #dc3545;
    border-left: 4px solid #c82333;
}

.flash-close {
    float: right;
    font-size: 20px;
    font-weight: bold;
    cursor: pointer;
    margin-left: 15px;
}

.flash-close:hover {
    opacity: 0.7;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
</style>