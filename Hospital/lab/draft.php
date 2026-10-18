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

// Check if ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No user ID provided.";
    header("Location: manage_users.php");
    exit();
}

$id = intval($_GET['id']);

// Check if confirmation is provided
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    // Show confirmation page
    try {
        // Fetch user details
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, username, email, role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $_SESSION['error'] = "User not found.";
            header("Location: manage_users.php");
            exit();
        }
        
        // Prevent deleting yourself or super admin
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id) {
            $_SESSION['error'] = "You cannot delete your own account.";
            header("Location: manage_users.php");
            exit();
        }
        
        // Check if user is Super Admin
        $stmt = $pdo->prepare("SELECT r.name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
        $stmt->execute([$id]);
        $role_data = $stmt->fetch();
        
        if ($role_data && strtolower($role_data['name']) === 'super admin') {
            $_SESSION['error'] = "Super Admin accounts cannot be deleted for security reasons.";
            header("Location: manage_users.php");
            exit();
        }
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header("Location: manage_users.php");
        exit();
    }
    
    // Display confirmation page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Delete User Confirmation</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: Arial, sans-serif;
                background-color: #f8fafc;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 1rem;
            }
            
            .confirmation-card {
                background: white;
                border-radius: 0.75rem;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
                max-width: 500px;
                width: 100%;
                overflow: hidden;
            }
            
            .card-header {
                background: linear-gradient(135deg, #ef4444, #dc2626);
                color: white;
                padding: 1.5rem;
                text-align: center;
            }
            
            .card-header i {
                font-size: 3rem;
                margin-bottom: 0.5rem;
            }
            
            .card-header h2 {
                font-size: 1.5rem;
                font-weight: 600;
                margin-top: 0.5rem;
            }
            
            .card-body {
                padding: 2rem;
            }
            
            .warning-message {
                background: #fef3c7;
                border-left: 4px solid #f59e0b;
                padding: 1rem;
                margin-bottom: 1.5rem;
                border-radius: 0.375rem;
            }
            
            .warning-message p {
                color: #92400e;
                font-size: 0.875rem;
                line-height: 1.5;
            }
            
            .user-details {
                background: #f9fafb;
                padding: 1.5rem;
                border-radius: 0.5rem;
                margin-bottom: 1.5rem;
            }
            
            .user-details h3 {
                color: #1e293b;
                font-size: 1rem;
                margin-bottom: 1rem;
            }
            
            .detail-row {
                display: flex;
                justify-content: space-between;
                padding: 0.5rem 0;
                border-bottom: 1px solid #e5e7eb;
            }
            
            .detail-row:last-child {
                border-bottom: none;
            }
            
            .detail-label {
                color: #64748b;
                font-size: 0.875rem;
                font-weight: 500;
            }
            
            .detail-value {
                color: #1e293b;
                font-size: 0.875rem;
                font-weight: 600;
            }
            
            .actions {
                display: flex;
                gap: 1rem;
            }
            
            .btn {
                flex: 1;
                padding: 0.875rem;
                border-radius: 0.5rem;
                font-weight: 500;
                text-decoration: none;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                cursor: pointer;
                transition: all 0.2s;
                border: none;
                font-size: 0.875rem;
            }
            
            .btn-cancel {
                background: #f3f4f6;
                color: #374151;
            }
            
            .btn-cancel:hover {
                background: #e5e7eb;
            }
            
            .btn-delete {
                background: linear-gradient(135deg, #ef4444, #dc2626);
                color: white;
            }
            
            .btn-delete:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
            }
        </style>
    </head>
    <body>
        <div class="confirmation-card">
            <div class="card-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h2>Delete User Confirmation</h2>
            </div>
            
            <div class="card-body">
                <div class="warning-message">
                    <p><strong>Warning:</strong> This action cannot be undone. Deleting this user will permanently remove all associated data from the system.</p>
                </div>
                
                <div class="user-details">
                    <h3>User Information</h3>
                    <div class="detail-row">
                        <span class="detail-label">Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Username:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Role:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($user['role']); ?></span>
                    </div>
                </div>
                
                <div class="actions">
                    <a href="manage_users.php" class="btn btn-cancel">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                    <a href="delete_user.php?id=<?php echo $id; ?>&confirm=yes" class="btn btn-delete">
                        <i class="fas fa-trash"></i>
                        Yes, Delete User
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Confirmed deletion - proceed with delete
try {
    // Fetch user info for logging
    $stmt = $pdo->prepare("SELECT first_name, last_name, username FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error'] = "User not found.";
        header("Location: manage_users.php");
        exit();
    }
    
    // Delete the user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        $_SESSION['success'] = "User '{$user['first_name']} {$user['last_name']}' has been deleted successfully.";
        error_log("User deleted: ID {$id}, Username: {$user['username']} by admin");
    } else {
        $_SESSION['error'] = "Failed to delete user. Please try again.";
        error_log("Failed to delete user ID: {$id}");
    }
    
} catch (PDOException $e) {
    // Check if deletion failed due to foreign key constraints
    if ($e->getCode() == 23000) {
        $_SESSION['error'] = "Cannot delete user. This user has related records in the system. Please remove or reassign those records first.";
    } else {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
    error_log("Database error in delete_user.php: " . $e->getMessage());
}

header("Location: manage_users.php");
exit();
?>