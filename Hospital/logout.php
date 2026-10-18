<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process logout when form is submitted
    
    // Unset all session variables
    $_SESSION = array();
    
  if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    // Log logout in audit_logs
    addAuditLog($pdo, $user_id, 'logout', 'User logged out', $ip_address, $user_agent);
    
    // Clear session
    session_destroy();
}
    // Redirect to login page
    header("Location: index.php");
    exit;
}



// If GET request, show confirmation page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - Hospital Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logout-container {
            background: white;
            padding: 3rem;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }

        .logout-icon {
            font-size: 4rem;
            color: #dc2626;
            margin-bottom: 1rem;
        }

        h1 {
            color: #1f2937;
            margin-bottom: 1rem;
            font-size: 1.875rem;
        }

        p {
            color: #6b7280;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-logout {
            background: #dc2626;
            color: white;
        }

        .btn-logout:hover {
            background: #b91c1c;
        }

        .btn-cancel {
            background: #6b7280;
            color: white;
        }

        .btn-cancel:hover {
            background: #4b5563;
        }

        form {
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h1>Logout Confirmation</h1>
        <p>Are you sure you want to logout from the Hospital Management System?</p>
        
        <div class="button-group">
            <form method="POST" style="display: inline;">
                <button type="submit" class="btn btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Yes, Logout
                </button>
            </form>
            <a href="javascript:history.back()" class="btn btn-cancel">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </div>
</body>
</html>