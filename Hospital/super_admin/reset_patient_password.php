<?php
session_start();
// if(!isset($_SESSION["super_admin"])) {
//     header("Location: ../index.php");
//     exit();
// }

include "includes/db_connect.php";

// Check if patient_id is provided
if (!isset($_GET['patient_id']) || empty($_GET['patient_id'])) {
    $_SESSION['error'] = "Patient ID is required.";
    header("Location: patient_management.php");
    exit();
}

$patient_id = $_GET['patient_id'];

// Fetch patient details
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               u.id as user_id,
               u.username,
               u.email as user_email,
               u.last_login,
               u.created_at as user_created_at
        FROM patients p
        LEFT JOIN users u ON (p.patient_id = u.id OR p.email = u.email OR p.phone = u.phone) 
                        AND u.role = 'Patient'
        WHERE p.id = ?
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch();
    
    if (!$patient) {
        $_SESSION['error'] = "Patient not found.";
        header("Location: patient_management.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header("Location: patient_management.php");
    exit();
}

// Process password reset if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Generate temporary password (8 characters)
        $temp_password = bin2hex(random_bytes(4));
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        
        // First, check the structure of the users table
        $check_columns = $pdo->query("SHOW COLUMNS FROM users");
        $columns = $check_columns->fetchAll(PDO::FETCH_COLUMN);
        
        if ($patient['user_id']) {
            // Update existing user
            $update_stmt = $pdo->prepare("
                UPDATE users SET 
                password_hash = ?, 
                status = 'active',
                role = 'Patient',
                updated_at = NOW()
                WHERE id = ? AND role = 'Patient'
            ");
            $update_stmt->execute([$hashed_password, $patient['user_id']]);
            
            $_SESSION['success'] = "Password reset successful! Temporary password: <strong>$temp_password</strong>";
            
        } else {
            // Check if user already exists with this email or phone
            $check_user = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR phone = ?) AND role = 'Patient'");
            $check_user->execute([$patient['email'], $patient['phone']]);
            $existing_user = $check_user->fetch();
            
            if ($existing_user) {
                // Update existing user
                $update_stmt = $pdo->prepare("
                    UPDATE users SET 
                    password_hash = ?, 
                    status = 'active',
                    role = 'Patient',
                    updated_at = NOW()
                    WHERE (email = ? OR phone = ?) AND role = 'Patient'
                ");
                $update_stmt->execute([$hashed_password, $patient['email'], $patient['phone']]);
                $user_id = $existing_user['id'];
                
                // Also update patient record with patient_id
                $update_patient = $pdo->prepare("UPDATE patients SET patient_id = ? WHERE id = ?");
                $update_patient->execute([$user_id, $patient_id]);
                
            } else {
                // Check if user exists with different role
                $check_any_user = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
                $check_any_user->execute([$patient['email'], $patient['phone']]);
                $any_user = $check_any_user->fetch();
                
                if ($any_user) {
                    $_SESSION['error'] = "Email or phone is already used by another user (Role: " . $any_user['role'] . ").";
                } else {
                    // Create new user
                    // Check if we have a role_id column
                    $has_role_id = in_array('role_id', $columns);
                    
                    if ($has_role_id) {
                        // Get role_id for Patient
                        $role_stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'Patient'");
                        $role_stmt->execute();
                        $role = $role_stmt->fetch();
                        $role_id = $role ? $role['id'] : 9;
                        
                        $insert_stmt = $pdo->prepare("
                            INSERT INTO users (
                                role_id, first_name, last_name, email, phone, 
                                password_hash, role, status, created_at
                            ) VALUES (?, ?, ?, ?, ?, ?, 'Patient', 'active', NOW())
                        ");
                        $insert_stmt->execute([
                            $role_id,
                            $patient['first_name'],
                            $patient['last_name'],
                            $patient['email'],
                            $patient['phone'],
                            $hashed_password
                        ]);
                    } else {
                        // Insert without role_id
                        $insert_stmt = $pdo->prepare("
                            INSERT INTO users (
                                first_name, last_name, email, phone, 
                                password_hash, role, status, created_at
                            ) VALUES (?, ?, ?, ?, ?, 'Patient', 'active', NOW())
                        ");
                        $insert_stmt->execute([
                            $patient['first_name'],
                            $patient['last_name'],
                            $patient['email'],
                            $patient['phone'],
                            $hashed_password
                        ]);
                    }
                    
                    $user_id = $pdo->lastInsertId();
                    
                    // Update patient record with patient_id
                    $update_patient = $pdo->prepare("UPDATE patients SET patient_id = ? WHERE id = ?");
                    $update_patient->execute([$user_id, $patient_id]);
                }
            }
            
            if (empty($_SESSION['error'])) {
                $_SESSION['success'] = "Login access created successfully! Temporary password: <strong>$temp_password</strong>";
            }
        }
        
        if (empty($_SESSION['error'])) {
            $_SESSION['temp_password'] = $temp_password;
            $_SESSION['reset_patient_id'] = $patient_id;
        }
        
        header("Location: reset_patient_password.php?patient_id=" . $patient_id);
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: reset_patient_password.php?patient_id=" . $patient_id);
        exit();
    }
}

// Get success or error messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$temp_password = isset($_SESSION['temp_password']) ? $_SESSION['temp_password'] : null;
$reset_patient_id = isset($_SESSION['reset_patient_id']) ? $_SESSION['reset_patient_id'] : null;

// Clear messages after displaying
if ($reset_patient_id == $patient_id) {
    unset($_SESSION['success'], $_SESSION['temp_password'], $_SESSION['reset_patient_id']);
}

$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Patient Password - MediCare HMS</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #f8fafc;
      min-height: 100vh;
      padding: 1rem;
    }

    .container {
      max-width: 1000px;
      margin: 0 auto;
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      overflow: hidden;
    }

    .header {
      background: linear-gradient(135deg, #0ea5e9, #0284c7);
      color: white;
      padding: 1.5rem;
      text-align: center;
    }

    .header h1 {
      font-size: 1.5rem;
      margin-bottom: 0.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
    }

    .header p {
      opacity: 0.9;
      font-size: 0.875rem;
      margin-bottom: 1rem;
    }

    .back-link {
      color: white;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.875rem;
      background: rgba(255,255,255,0.2);
      padding: 0.5rem 1rem;
      border-radius: 6px;
      transition: all 0.2s;
    }

    .back-link:hover {
      background: rgba(255,255,255,0.3);
    }

    .content {
      padding: 2rem;
    }

    @media (max-width: 768px) {
      .content {
        padding: 1rem;
      }
    }

    /* Alert Messages */
    .alert {
      padding: 1rem 1.5rem;
      border-radius: 0.5rem;
      margin-bottom: 1.5rem;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .alert-success {
      background-color: #dcfce7;
      color: #166534;
      border: 1px solid #bbf7d0;
    }

    .alert-error {
      background-color: #fee2e2;
      color: #dc2626;
      border: 1px solid #fecaca;
    }

    .alert-warning {
      background-color: #fef3c7;
      color: #92400e;
      border: 1px solid #fde68a;
    }

    .patient-info {
      display: flex;
      gap: 1.5rem;
      margin-bottom: 2rem;
      padding: 1.5rem;
      background: #f8fafc;
      border-radius: 0.75rem;
      border: 1px solid #e2e8f0;
    }

    @media (max-width: 640px) {
      .patient-info {
        flex-direction: column;
        text-align: center;
      }
    }

    .patient-avatar {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: linear-gradient(135deg, #0ea5e9, #0284c7);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      color: white;
      font-weight: 600;
      flex-shrink: 0;
    }

    @media (max-width: 640px) {
      .patient-avatar {
        align-self: center;
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
      }
    }

    .patient-details h3 {
      margin: 0 0 0.5rem 0;
      color: #1e293b;
      font-size: 1.5rem;
      font-weight: 600;
    }

    .patient-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      color: #64748b;
      font-size: 0.875rem;
      margin-bottom: 1rem;
    }

    .meta-item {
      display: flex;
      align-items: center;
      gap: 0.25rem;
    }

    .login-status {
      margin-top: 0.5rem;
    }

    .login-badge {
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
    }

    .login-active {
      background: #d1fae5;
      color: #065f46;
    }

    .login-new {
      background: #fef3c7;
      color: #92400e;
    }

    .login-inactive {
      background: #f3f4f6;
      color: #6b7280;
    }

    .action-card {
      background: #f8fafc;
      padding: 1.5rem;
      border-radius: 0.75rem;
      border: 1px solid #e2e8f0;
    }

    .action-header {
      margin-bottom: 1.5rem;
    }

    .action-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: #1e293b;
      margin: 0 0 0.5rem 0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .action-description {
      color: #64748b;
      font-size: 0.875rem;
      margin: 0;
    }

    .warning-box {
      background: #fffbeb;
      border: 1px solid #fde68a;
      border-radius: 0.5rem;
      padding: 1rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
    }

    .warning-icon {
      color: #f59e0b;
      font-size: 1.25rem;
      margin-top: 0.125rem;
    }

    .warning-content h4 {
      margin: 0 0 0.5rem 0;
      color: #92400e;
      font-size: 0.875rem;
      font-weight: 600;
    }

    .warning-content p {
      margin: 0;
      color: #92400e;
      font-size: 0.875rem;
    }

    .info-box {
      background: #f0f9ff;
      border: 1px solid #e0f2fe;
      border-radius: 0.5rem;
      padding: 1rem;
      margin: 1.5rem 0;
    }

    .info-title {
      color: #0369a1;
      font-size: 0.875rem;
      font-weight: 600;
      margin: 0 0 0.5rem 0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .info-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .info-list li {
      color: #0c4a6e;
      font-size: 0.875rem;
      padding: 0.25rem 0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .info-list i {
      color: #0ea5e9;
      width: 16px;
    }

    .form-actions {
      display: flex;
      gap: 0.75rem;
      margin-top: 2rem;
      padding-top: 1.5rem;
      border-top: 1px solid #e2e8f0;
    }

    @media (max-width: 640px) {
      .form-actions {
        flex-direction: column;
      }
    }

    .btn {
      padding: 0.75rem 1.5rem;
      border-radius: 0.5rem;
      font-weight: 500;
      text-decoration: none;
      cursor: pointer;
      transition: all 0.2s;
      border: none;
      font-size: 0.875rem;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    .btn-secondary {
      background: white;
      color: #374151;
      border: 1px solid #d1d5db;
    }

    .btn-secondary:hover {
      background: #f9fafb;
    }

    .btn-primary {
      background: linear-gradient(135deg, #0ea5e9, #0284c7);
      color: white;
      flex: 1;
      justify-content: center;
    }

    .btn-primary:hover:not(:disabled) {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
    }

    .btn-primary:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .btn-success {
      background: linear-gradient(135deg, #10b981, #059669);
      color: white;
      flex: 1;
      justify-content: center;
    }

    .btn-success:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    /* Password Result */
    .password-result {
      margin-top: 1.5rem;
      padding: 1.5rem;
      background: #f0f9ff;
      border: 2px solid #0ea5e9;
      border-radius: 0.75rem;
      text-align: center;
    }

    .credentials-box {
      background: white;
      border: 1px solid #e5e7eb;
      border-radius: 0.5rem;
      padding: 1rem;
      margin: 1rem 0;
      text-align: left;
    }

    .credential-item {
      display: flex;
      justify-content: space-between;
      padding: 0.5rem 0;
      border-bottom: 1px solid #f3f4f6;
    }

    .credential-item:last-child {
      border-bottom: none;
    }

    .credential-label {
      font-weight: 500;
      color: #374151;
      font-size: 0.875rem;
    }

    .credential-value {
      font-family: 'Courier New', monospace;
      color: #1f2937;
      font-weight: 600;
    }

    .password-display {
      font-family: 'Courier New', monospace;
      font-size: 1.5rem;
      font-weight: 700;
      text-align: center;
      margin: 1rem 0;
      padding: 1rem;
      background: white;
      border-radius: 0.5rem;
      border: 2px dashed #3b82f6;
      letter-spacing: 0.1em;
      color: #1e40af;
    }

    @media (max-width: 480px) {
      .password-display {
        font-size: 1.25rem;
        padding: 0.75rem;
      }
    }

    .manual-instructions {
      background: #fef3c7;
      border: 1px solid #fbbf24;
      border-radius: 0.5rem;
      padding: 1rem;
      margin: 1.5rem 0;
    }

    .manual-instructions h4 {
      color: #92400e;
      font-size: 0.875rem;
      margin-bottom: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .manual-instructions ol {
      margin: 0.5rem 0 0 1.5rem;
      color: #92400e;
      font-size: 0.875rem;
    }

    .manual-instructions li {
      margin-bottom: 0.5rem;
    }

    .copy-btn {
      background: #f1f5f9;
      border: 1px solid #cbd5e1;
      color: #475569;
      padding: 0.5rem 1rem;
      border-radius: 0.375rem;
      cursor: pointer;
      font-size: 0.875rem;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.2s;
      margin-top: 0.5rem;
    }

    .copy-btn:hover {
      background: #e2e8f0;
    }

    .copy-btn.copied {
      background: #d1fae5;
      color: #065f46;
      border-color: #a7f3d0;
    }

    .print-btn {
      background: #3b82f6;
      color: white;
      border: none;
      padding: 0.75rem 1.5rem;
      border-radius: 0.5rem;
      font-weight: 500;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      margin-top: 1rem;
      text-decoration: none;
    }

    .print-btn:hover {
      background: #2563eb;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>
        <i class="fas fa-key"></i>
        <?php echo $patient['user_id'] ? 'Reset Patient Password' : 'Setup Patient Login'; ?>
      </h1>
      <p>Manage patient login credentials</p>
      <a href="add_new_patients.php" class="back-link">
        <i class="fas fa-arrow-left"></i>
        Back to Patients
      </a>
    </div>
    
    <div class="content">
      <!-- Alert Messages -->
      <?php if ($success_message): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo $success_message; ?>
      </div>
      <?php endif; ?>

      <?php if ($error_message): ?>
      <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle"></i>
        <?php echo htmlspecialchars($error_message); ?>
      </div>
      <?php endif; ?>

      <!-- Patient Information -->
      <div class="patient-info">
        <div class="patient-avatar">
          <?php echo strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1)); ?>
        </div>
        <div class="patient-details">
          <h3><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h3>
          <div class="patient-meta">
            <div class="meta-item">
              <i class="fas fa-id-card"></i>
              <span>ID: <?php echo htmlspecialchars($patient['patient_id'] ?: $patient['id']); ?></span>
            </div>
            <div class="meta-item">
              <i class="fas fa-phone"></i>
              <span><?php echo htmlspecialchars($patient['phone'] ?? 'No phone'); ?></span>
            </div>
            <div class="meta-item">
              <i class="fas fa-envelope"></i>
              <span><?php echo htmlspecialchars($patient['email'] ?? 'No email'); ?></span>
            </div>
          </div>
          <?php if ($patient['user_id']): ?>
            <div class="login-status">
              <span class="login-badge <?php echo $patient['last_login'] ? 'login-active' : 'login-new'; ?>">
                <i class="fas <?php echo $patient['last_login'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo $patient['last_login'] ? 'Has Online Access' : 'Setup - Never Logged In'; ?>
              </span>
              <?php if ($patient['last_login']): ?>
                <small style="color: #64748b; font-size: 0.75rem; display: block; margin-top: 0.25rem;">
                  Last login: <?php echo date('M d, Y h:i A', strtotime($patient['last_login'])); ?>
                </small>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($temp_password): ?>
        <!-- Password Result Display -->
        <div class="password-result">
          <h3 style="color: #0369a1; margin-bottom: 1rem;">
            <i class="fas fa-check-circle"></i>
            <?php echo $patient['user_id'] ? 'Password Reset Complete' : 'Login Setup Complete'; ?>
          </h3>
          
          <div class="credentials-box">
            <div class="credential-item">
              <span class="credential-label">Patient Name:</span>
              <span class="credential-value"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></span>
            </div>
            <div class="credential-item">
              <span class="credential-label">Patient ID:</span>
              <span class="credential-value"><?php echo htmlspecialchars($patient['patient_id'] ?: $patient['id']); ?></span>
            </div>
            <?php if ($patient['email']): ?>
            <div class="credential-item">
              <span class="credential-label">Login Email:</span>
              <span class="credential-value"><?php echo htmlspecialchars($patient['email']); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($patient['phone']): ?>
            <div class="credential-item">
              <span class="credential-label">Login Phone:</span>
              <span class="credential-value"><?php echo htmlspecialchars($patient['phone']); ?></span>
            </div>
            <?php endif; ?>
          </div>
          
          <h4 style="color: #1e40af; margin: 1.5rem 0 0.5rem 0;">Temporary Password:</h4>
          <div class="password-display" id="tempPassword">
            <?php echo htmlspecialchars($temp_password); ?>
          </div>
          
          <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($temp_password); ?>')">
            <i class="fas fa-copy"></i> Copy Password
          </button>
          
          <div class="manual-instructions">
            <h4><i class="fas fa-clipboard-check"></i> Instructions:</h4>
            <ol>
              <li>Write down the temporary password above on paper</li>
              <li>Provide it to the patient securely</li>
              <li>Inform patient to login at: <strong>/patient-login.php</strong></li>
              <li>Patient should change password on first login</li>
              <li>Patient can login with <?php echo $patient['email'] ? 'email' : ''; ?><?php echo ($patient['email'] && $patient['phone']) ? ' or ' : ''; ?><?php echo $patient['phone'] ? 'phone number' : ''; ?></li>
            </ol>
          </div>
          
          <button class="print-btn" onclick="window.print()">
            <i class="fas fa-print"></i> Print This Page
          </button>
        </div>
        
        <div class="form-actions">
          <a href="add_new_patients.php" class="btn btn-secondary">
            <i class="fas fa-users"></i> Back to Patients
          </a>
          <a href="reset_patient_password.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-success">
            <i class="fas fa-sync-alt"></i> Reset Again
          </a>
        </div>
        
      <?php else: ?>
        <!-- Action Form -->
        <div class="action-card">
          <div class="action-header">
            <h2 class="action-title">
              <i class="fas fa-key"></i>
              <?php echo $patient['user_id'] ? 'Reset Password' : 'Setup Login Access'; ?>
            </h2>
            <p class="action-description">
              <?php if ($patient['user_id']): ?>
                This will generate a new temporary password for the patient.
              <?php else: ?>
                Create login access for this patient. A temporary password will be generated.
              <?php endif; ?>
            </p>
          </div>

          <?php if (!$patient['user_id']): ?>
          <div class="warning-box">
            <i class="fas fa-exclamation-triangle warning-icon"></i>
            <div class="warning-content">
              <h4>Important Notice</h4>
              <p>This patient does not currently have online access.</p>
            </div>
          </div>
          <?php endif; ?>

          <?php if (empty($patient['email']) && empty($patient['phone'])): ?>
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>No email or phone found.</strong> The patient needs at least one contact method for login access.
          </div>
          <?php endif; ?>

          <div class="info-box">
            <h4 class="info-title"><i class="fas fa-info-circle"></i> What happens next:</h4>
            <ul class="info-list">
              <?php if ($patient['user_id']): ?>
              <li><i class="fas fa-sync-alt"></i> Current password will be invalidated immediately</li>
              <?php else: ?>
              <li><i class="fas fa-user-plus"></i> New patient portal account will be created</li>
              <?php endif; ?>
              <li><i class="fas fa-key"></i> A temporary password will be generated (8 characters)</li>
              <li><i class="fas fa-print"></i> You will need to manually write down the password</li>
            </ul>
          </div>

          <form method="POST" action="">
            <div class="manual-instructions">
              <h4><i class="fas fa-info-circle"></i> Manual Distribution Required</h4>
              <p style="color: #92400e; margin: 0.5rem 0;">You must:</p>
              <ol>
                <li>Write down the generated password on paper</li>
                <li>Provide it securely to the patient</li>
                <li>Inform them to change it on first login</li>
              </ol>
            </div>
            
            <div class="form-actions">
              <a href="patient_management.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
              </a>
              <?php if ($patient['user_id']): ?>
              <button type="submit" class="btn btn-primary" onclick="return confirmReset()">
                <i class="fas fa-sync-alt"></i> Reset Password
              </button>
              <?php else: ?>
              <button type="submit" class="btn btn-success" onclick="return confirmSetup()" <?php echo (empty($patient['email']) && empty($patient['phone'])) ? 'disabled' : ''; ?>>
                <i class="fas fa-user-plus"></i> Setup Login Access
              </button>
              <?php endif; ?>
            </div>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>

<script>
// Copy to clipboard functionality
function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(() => {
    const btn = event.target.closest('.copy-btn');
    if (btn) {
      const originalText = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
      btn.classList.add('copied');
      setTimeout(() => {
        btn.innerHTML = originalText;
        btn.classList.remove('copied');
      }, 2000);
    }
  }).catch(err => {
    console.error('Failed to copy: ', err);
    alert('Failed to copy to clipboard. Please copy manually.');
  });
}

// Form confirmation
function confirmReset() {
  const patientName = "<?php echo htmlspecialchars(addslashes($patient['first_name'] . ' ' . $patient['last_name'])); ?>";
  return confirm(`Are you sure you want to reset the password for ${patientName}?\n\nYou will need to manually write down the new temporary password.`);
}

function confirmSetup() {
  const patientName = "<?php echo htmlspecialchars(addslashes($patient['first_name'] . ' ' . $patient['last_name'])); ?>";
  return confirm(`Are you sure you want to setup login access for ${patientName}?\n\nYou will need to manually write down the temporary password.`);
}

// Print page function
function printPage() {
  window.print();
}

// Add print styles when password is displayed
<?php if ($temp_password): ?>
document.addEventListener('DOMContentLoaded', function() {
  const style = document.createElement('style');
  style.innerHTML = `
    @media print {
      .back-link, .form-actions, .alert {
        display: none !important;
      }
      .password-display {
        font-size: 24px !important;
        border: 3px dashed #000 !important;
        margin: 20px 0 !important;
      }
      .credentials-box {
        border: 2px solid #000 !important;
        margin: 20px 0 !important;
      }
    }
  `;
  document.head.appendChild(style);
});
<?php endif; ?>

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
  // Escape key goes back
  if (e.key === 'Escape') {
    window.location.href = 'patient_management.php';
  }
  
  // Ctrl/Cmd + P for print when password is displayed
  if ((e.ctrlKey || e.metaKey) && e.key === 'p' && <?php echo $temp_password ? 'true' : 'false'; ?>) {
    e.preventDefault();
    window.print();
  }
});
</script>
</body>
</html>