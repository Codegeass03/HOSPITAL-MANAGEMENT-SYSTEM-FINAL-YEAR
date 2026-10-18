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
        $id = intval($_POST['id']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $role = trim($_POST['role']);
        $department = isset($_POST['department']) ? trim($_POST['department']) : null;
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
        $specialization = isset($_POST['specialization']) ? trim($_POST['specialization']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        
        // Validation
        $errors = [];
        
        if (empty($first_name)) {
            $errors[] = "First name is required";
        }
        
        if (empty($last_name)) {
            $errors[] = "Last name is required";
        }
        
        if (empty($username)) {
            $errors[] = "Username is required";
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Valid email is required";
        }
        
        if (empty($phone)) {
            $errors[] = "Phone is required";
        }
        
        if (empty($role)) {
            $errors[] = "Role is required";
        }
        
        // Check if username is taken by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $id]);
        if ($stmt->fetch()) {
            $errors[] = "Username already taken by another user";
        }
        
        // Check if email is taken by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            $errors[] = "Email already taken by another user";
        }
        
        if (!empty($errors)) {
            $_SESSION['error'] = implode(", ", $errors);
            header("Location: edit_user.php?id=" . $id);
            exit();
        }
        
        // Get role_id from role name
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE LOWER(name) = LOWER(?)");
        $stmt->execute([$role]);
        $role_data = $stmt->fetch();
        $role_id = $role_data ? $role_data['id'] : 1; // Default to 1 if not found
        
        // Build UPDATE query
        if (!empty($password)) {
            // Update with new password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                UPDATE users SET 
                    role_id = ?,
                    username = ?,
                    email = ?,
                    password_hash = ?,
                    first_name = ?,
                    last_name = ?,
                    phone = ?,
                    role = ?,
                    department = ?,
                    status = ?,
                    specialization = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $role_id,
                $username,
                $email,
                $password_hash,
                $first_name,
                $last_name,
                $phone,
                $role,
                $department,
                $status,
                $specialization,
                $id
            ]);
        } else {
            // Update without changing password
            $stmt = $pdo->prepare("
                UPDATE users SET 
                    role_id = ?,
                    username = ?,
                    email = ?,
                    first_name = ?,
                    last_name = ?,
                    phone = ?,
                    role = ?,
                    department = ?,
                    status = ?,
                    specialization = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $role_id,
                $username,
                $email,
                $first_name,
                $last_name,
                $phone,
                $role,
                $department,
                $status,
                $specialization,
                $id
            ]);
        }
        
        if ($result) {
            $_SESSION['success'] = "User '{$first_name} {$last_name}' updated successfully!";
            error_log("User updated: ID {$id}, Username: {$username}");
        } else {
            $_SESSION['error'] = "Failed to update user. Please try again.";
            error_log("Failed to update user ID: {$id}");
        }
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        error_log("Database error in update_user.php: " . $e->getMessage());
    }
} else {
    $_SESSION['error'] = "Invalid request method.";
}

// Redirect back to manage users
header("Location: manage_users.php");
exit();
?>