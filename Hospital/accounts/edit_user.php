<?php
session_start();

// Include database connection (using PDO, not $conn)
include "includes/db_connect.php";
include "includes\sidebar.php";

// Check if $pdo is defined
if (!isset($pdo)) {
    die("Database connection failed. Please check your database configuration.");
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No user ID provided.";
    header("Location: manage_users.php");
    exit();
}

$id = intval($_GET['id']); // Sanitize the ID

try {
    // Fetch user data using PDO (not $conn)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error'] = "User not found.";
        header("Location: manage_users.php");
        exit();
    }
    
    // Fetch all roles for dropdown
    $roles_stmt = $pdo->query("SELECT id, name FROM roles ORDER BY name");
    $roles = $roles_stmt->fetchAll();
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header("Location: manage_users.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - MediCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f8fafc;
        }
        
        .main-content {
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 1.875rem;
            font-weight: 600;
            color: #334155;
            margin: 0 0 0.5rem 0;
        }
        
        .breadcrumb {
            color: #64748b;
            font-size: 0.875rem;
        }
        
        .breadcrumb a {
            color: #0ea5e9;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .edit-form {
            background: white;
            padding: 2rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-label .required {
            color: #dc2626;
        }
        
        .form-input, .form-select {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: border-color 0.2s;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
        }
        
        .btn-secondary {
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        
        .btn-secondary:hover {
            background: #f9fafb;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Edit User</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> / 
                <a href="manage_users.php">Staff Management</a> / 
                Edit User
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php 
                    echo htmlspecialchars($_SESSION['error']); 
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <div class="edit-form">
            <form method="POST" action="update_user.php">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id']); ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">
                            First Name <span class="required">*</span>
                        </label>
                        <input type="text" 
                               name="first_name" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($user['first_name']); ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Last Name <span class="required">*</span>
                        </label>
                        <input type="text" 
                               name="last_name" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($user['last_name']); ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Username <span class="required">*</span>
                        </label>
                        <input type="text" 
                               name="username" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($user['username']); ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Email <span class="required">*</span>
                        </label>
                        <input type="email" 
                               name="email" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Phone <span class="required">*</span>
                        </label>
                        <input type="tel" 
                               name="phone" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($user['phone']); ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Role <span class="required">*</span>
                        </label>
                        <select name="role" class="form-select" required>
                            <option value="">Select role</option>
                            <?php foreach($roles as $role): ?>
                                <option value="<?php echo htmlspecialchars($role['name']); ?>" 
                                        <?php echo ($user['role'] == $role['name']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select name="department" class="form-select">
                            <option value="">Select department</option>
                            <option value="general care" <?php echo ($user['department'] == 'general care') ? 'selected' : ''; ?>>General Care</option>
                            <option value="cardiology" <?php echo ($user['department'] == 'cardiology') ? 'selected' : ''; ?>>Cardiology</option>
                            <option value="neurology" <?php echo ($user['department'] == 'neurology') ? 'selected' : ''; ?>>Neurology</option>
                            <option value="emergency" <?php echo ($user['department'] == 'emergency') ? 'selected' : ''; ?>>Emergency</option>
                            <option value="orthopedics" <?php echo ($user['department'] == 'orthopedics') ? 'selected' : ''; ?>>Orthopedics</option>
                            <option value="pediatrics" <?php echo ($user['department'] == 'pediatrics') ? 'selected' : ''; ?>>Pediatrics</option>
                            <option value="pharmacy" <?php echo ($user['department'] == 'pharmacy') ? 'selected' : ''; ?>>Pharmacy</option>
                            <option value="laboratory" <?php echo ($user['department'] == 'laboratory') ? 'selected' : ''; ?>>Laboratory</option>
                            <option value="finance" <?php echo ($user['department'] == 'finance') ? 'selected' : ''; ?>>Finance</option>
                            <option value="operations" <?php echo ($user['department'] == 'operations') ? 'selected' : ''; ?>>Operations</option>
                            <option value="hr" <?php echo ($user['department'] == 'hr') ? 'selected' : ''; ?>>Human Resources</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?php echo ($user['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($user['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?php echo ($user['status'] == 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Specialization</label>
                        <input type="text" 
                               name="specialization" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($user['specialization'] ?? ''); ?>" 
                               placeholder="Enter specialization (optional)">
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">New Password</label>
                        <input type="password" 
                               name="password" 
                               class="form-input" 
                               placeholder="Leave blank to keep current password">
                        <small style="color: #64748b; font-size: 0.75rem; margin-top: 0.25rem;">
                            Only fill this if you want to change the password
                        </small>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="manage_users.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php include "includes/footer.php"; ?>

<style>
    body {
      margin: 0;
      display: flex;
      font-family: Arial, sans-serif;
    }

    /* Main content wrapper */
    .main-wrapper {
      margin-left: 250px;
      flex: 1;
      display: flex;
      flex-direction: column;
      background-color: #f8fafc;
      min-height: 100vh;
    }

    /* Your existing main-content style */
    .main-content {
      padding: 2rem;
      flex: 1;
      background-color: #f8fafc;
      min-height: 100vh;
    }

    .page-header {
      display: flex;
      justify-content: space-between; /* Fixed: Changed from "between" to "space-between" for proper right alignment */
      align-items: center;
      margin-bottom: 2rem;
    }

    .page-title {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin: 0;
      color: #334155;
      font-size: 1.875rem;
      font-weight: 600;
    }

    .page-subtitle {
      color: #64748b;
      font-size: 0.875rem;
      margin: 0.5rem 0 0 0;
    }

    .add-staff-btn {
      background: linear-gradient(135deg, #0ea5e9, #0284c7);
      color: white;
      padding: 0.75rem 1.5rem;
      border: none;
      border-radius: 0.5rem;
      text-decoration: none;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.2s;
      box-shadow: 0 2px 4px rgba(14, 165, 233, 0.2);
    }

    .add-staff-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
    }

    .search-filters {
      display: flex;
      gap: 1rem;
      margin-bottom: 2rem;
      flex-wrap: wrap;
      align-items: center;
    }

    .search-box {
      position: relative;
      flex: 1;
      min-width: 300px;
    }

    .search-box input {
      width: 100%;
      padding: 0.75rem 1rem 0.75rem 2.5rem;
      border: 1px solid #e2e8f0;
      border-radius: 0.5rem;
      font-size: 0.875rem;
      background: white;
      transition: all 0.2s;
    }

    .search-box input:focus {
      outline: none;
      border-color: #0ea5e9;
      box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
    }

    .search-box .search-icon {
      position: absolute;
      left: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
    }

    .department-filter {
      padding: 0.75rem 1rem;
      border: 1px solid #e2e8f0;
      border-radius: 0.5rem;
      background: white;
      font-size: 0.875rem;
      color: #475569;
      min-width: 150px;
    }

    /* Sidebar list reset */
.sidebar ul {
  list-style: none;        /* removes bullets */
  padding: 0;
  margin: 0;
}

.sidebar li {
  margin-bottom: 0.5rem;   /* adds space between items */
}

.sidebar a {
  display: block;
  text-decoration: none;   /* removes underline */
  color: white;            /* text color */
  padding: 0.15rem;   /* adds spacing around text */
  border-radius: 0.375rem; /* rounded corners on hover */
  transition: background 0.2s ease;
}

.sidebar a:hover {
  background-color: #334155; /* subtle hover effect */
  color: #f8fafc;
}

    .staff-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 1.5rem;
    }

    .staff-card {
      background: white;
      border-radius: 0.75rem;
      padding: 1.5rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      transition: all 0.2s;
      border: 1px solid #f1f5f9;
    }

    .staff-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .staff-header {
      display: flex;
      justify-content: space-between; /* Fixed: Changed from "between" to "space-between" for proper alignment */
      align-items: flex-start;
      margin-bottom: 1rem;
    }

    .staff-avatar {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      color: #64748b;
      font-weight: 600;
      margin-bottom: 1rem;
    }

    .staff-info h3 {
      margin: 0 0 0.25rem 0;
      color: #1e293b;
      font-size: 1.125rem;
      font-weight: 600;
    }

    .staff-role {
      color: #64748b;
      font-size: 0.875rem;
      margin-bottom: 1rem;
    }

    .status-badge {
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.025em;
    }

    .status-active {
      background-color: #dcfce7;
      color: #166534;
    }

    .status-leave {
      background-color: #fef3c7;
      color: #92400e;
    }

    .staff-details {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      margin-bottom: 1rem;
    }

    .detail-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: #64748b;
      font-size: 0.875rem;
    }

    .detail-icon {
      width: 16px;
      color: #94a3b8;
    }

    .staff-stats {
      display: flex;
      gap: 1rem;
      margin-bottom: 1rem;
      padding-top: 1rem;
      border-top: 1px solid #f1f5f9;
    }

    .stat-item {
      display: flex;
      align-items: center;
      gap: 0.375rem;
      color: #64748b;
      font-size: 0.75rem;
    }

    .stat-icon {
      color: #fbbf24;
    }

    .staff-actions {
      display: flex;
      gap: 0.5rem;
      margin-top: 1rem;
    }

    .action-btn {
      flex: 1;
      padding: 0.5rem;
      border: 1px solid #e2e8f0;
      background: white;
      border-radius: 0.375rem;
      color: #64748b;
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.25rem;
      font-size: 0.875rem;
      transition: all 0.2s;
    }

    .action-btn:hover {
      background: #f8fafc;
      color: #334155;
    }

    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }

    .modal.show {
      display: flex;
    }

    .modal-content {
      background: white;
      border-radius: 0.75rem;
      width: 90%;
      max-width: 500px;
      max-height: 90vh;
      overflow-y: auto;
    }

    .modal-header {
      padding: 1.5rem 1.5rem 0 1.5rem;
      display: flex;
      justify-content: space-between; /* Fixed: Changed from "between" to "space-between" for proper alignment */
      align-items: center;
    }

    .modal-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: #1e293b;
      margin: 0;
    }

    .modal-close {
      background: none;
      border: none;
      font-size: 1.5rem;
      color: #64748b;
      cursor: pointer;
    }

    .modal-body {
      padding: 1.5rem;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .form-group {
      display: flex;
      flex-direction: column;
    }

    .form-group.full-width {
      grid-column: 1 / -1;
    }

    .form-label {
      font-size: 0.875rem;
      font-weight: 500;
      color: #374151;
      margin-bottom: 0.5rem;
    }

    .form-input, .form-select, .form-textarea {
      padding: 0.75rem;
      border: 1px solid #d1d5db;
      border-radius: 0.5rem;
      font-size: 0.875rem;
      transition: border-color 0.2s;
    }

    .form-input:focus, .form-select:focus, .form-textarea:focus {
      outline: none;
      border-color: #0ea5e9;
      box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
    }

    .form-textarea {
      resize: vertical;
      min-height: 80px;
    }

    .modal-footer {
      padding: 0 1.5rem 1.5rem 1.5rem;
      display: flex;
      gap: 0.75rem;
      justify-content: flex-end;
    }

    .btn-secondary {
      padding: 0.75rem 1.5rem;
      border: 1px solid #d1d5db;
      background: white;
      color: #374151;
      border-radius: 0.5rem;
      text-decoration: none;
      font-weight: 500;
      transition: all 0.2s;
    }

    .btn-secondary:hover {
      background: #f9fafb;
    }

    .btn-primary {
      padding: 0.75rem 1.5rem;
      background: linear-gradient(135deg, #0ea5e9, #0284c7);
      color: white;
      border: none;
      border-radius: 0.5rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-primary:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
    }
  </style>