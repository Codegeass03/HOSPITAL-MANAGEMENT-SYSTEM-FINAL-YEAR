<?php
session_start();

// Include database connection
include "includes/db_connect.php";

// DEBUG: Check database connection
echo "Database connection status: ";
if (isset($pdo)) {
    echo "CONNECTED<br>";
} else {
    die("NOT CONNECTED - Check db_connect.php");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // DEBUG: Show all POST data
    echo "<h3>Received POST Data:</h3>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    try {
        // Get form inputs
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $phone = trim($_POST['phone']);
        $role = trim($_POST['role']);
        $department = trim($_POST['department']);
        $status = isset($_POST['status']) && !empty($_POST['status']) ? $_POST['status'] : 'active';
        $specialization = isset($_POST['specialization']) && !empty($_POST['specialization']) ? trim($_POST['specialization']) : '';
        $schedule = isset($_POST['schedule']) && !empty($_POST['schedule']) ? trim($_POST['schedule']) : '';
        
        echo "<h3>Processing Data:</h3>";
        echo "First Name: {$first_name}<br>";
        echo "Last Name: {$last_name}<br>";
        echo "Username: {$username}<br>";
        echo "Email: {$email}<br>";
        echo "Phone: {$phone}<br>";
        echo "Role: {$role}<br>";
        echo "Department: {$department}<br>";
        echo "Status: {$status}<br>";
        
        // Get role_id
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE LOWER(name) = LOWER(?)");
        $stmt->execute([$role]);
        $role_data = $stmt->fetch();
        
        if ($role_data) {
            $role_id = $role_data['id'];
            echo "Role ID found: {$role_id}<br>";
        } else {
            echo "<strong>WARNING: Role '{$role}' not found in roles table!</strong><br>";
            $stmt = $pdo->prepare("SELECT id, name FROM roles");
            $stmt->execute();
            echo "Available roles:<br>";
            while ($r = $stmt->fetch()) {
                echo "- ID: {$r['id']}, Name: {$r['name']}<br>";
            }
            die("Stopping here - fix role issue first");
        }
        
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        echo "Password hashed successfully<br>";
        
        // Prepare INSERT
        $sql = "INSERT INTO users (
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
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
        
        echo "<h3>SQL Query:</h3>";
        echo "<pre>" . $sql . "</pre>";
        
        $stmt = $pdo->prepare($sql);
        
        $params = [
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
        ];
        
        echo "<h3>Parameters:</h3>";
        echo "<pre>";
        print_r($params);
        echo "</pre>";
        
        // Execute
        $result = $stmt->execute($params);
        
        if ($result) {
            $user_id = $pdo->lastInsertId();
            echo "<h3 style='color: green;'>INSERT SUCCESSFUL!</h3>";
            echo "New user ID: {$user_id}<br>";
            
            // Verify
            $verify = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $verify->execute([$user_id]);
            $user_record = $verify->fetch();
            
            if ($user_record) {
                echo "<h3>Verified in Database:</h3>";
                echo "<pre>";
                print_r($user_record);
                echo "</pre>";
            } else {
                echo "<h3 style='color: red;'>ERROR: Record not found after insert!</h3>";
            }
            
            // Check total count
            $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            echo "<br>Total users in database: {$count}<br>";
            
        } else {
            echo "<h3 style='color: red;'>INSERT FAILED!</h3>";
            echo "<pre>";
            print_r($stmt->errorInfo());
            echo "</pre>";
        }
        
    } catch (PDOException $e) {
        echo "<h3 style='color: red;'>PDO EXCEPTION!</h3>";
        echo "Message: " . $e->getMessage() . "<br>";
        echo "Code: " . $e->getCode() . "<br>";
        echo "<pre>";
        print_r($e);
        echo "</pre>";
    }
    
    echo "<br><br><a href='manage_users.php'>Back to Manage Users</a>";
    
} else {
    echo "No POST data received. Please submit the form.";
}
?>