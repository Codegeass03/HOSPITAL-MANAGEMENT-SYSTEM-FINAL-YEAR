<?php
session_start();
include "includes/db_connect.php";

// Only staff can access this
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Doctor' && $_SESSION['role'] !== 'Nurse' && $_SESSION['role'] !== 'Super Admin' && $_SESSION['role'] !== 'Receptionist')) {
    header("Location: ../login.php");
    exit;
}

// Handle patient login setup
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = $_POST['patient_id'];
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $action = $_POST['action'];
    
    try {
        if ($action === 'setup') {
            // Check if patient exists
            $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
            $stmt->execute([$patient_id]);
            $patient = $stmt->fetch();
            
            if (!$patient) {
                $_SESSION['error'] = "Patient not found!";
            } else {
                // Validate inputs
                if (empty($email) && empty($phone)) {
                    $_SESSION['error'] = "Please provide either email or phone number for login.";
                    header("Location: setup_patient_login.php");
                    exit;
                }
                
                // Validate email format if provided
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $_SESSION['error'] = "Invalid email format!";
                    header("Location: setup_patient_login.php");
                    exit;
                }
                
                // Check if email already exists in users table
                if (!empty($email)) {
                    $check_email = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'Patient'");
                    $check_email->execute([$email]);
                    if ($check_email->fetch()) {
                        $_SESSION['error'] = "Email already exists in the system!";
                        header("Location: setup_patient_login.php");
                        exit;
                    }
                }
                
                // Check if phone already exists in users table
                if (!empty($phone)) {
                    $check_phone = $pdo->prepare("SELECT * FROM users WHERE phone = ? AND role = 'Patient'");
                    $check_phone->execute([$phone]);
                    if ($check_phone->fetch()) {
                        $_SESSION['error'] = "Phone number already exists in the system!";
                        header("Location: setup_patient_login.php");
                        exit;
                    }
                }
                
                // Generate temporary password
                $temp_password = bin2hex(random_bytes(4)); // 8 character password
                $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                
                // Get role_id for Patient (should be 9 based on your data)
                $role_id = 9;
                
                // Check if user already exists for this patient (using email or phone from patient table)
                $check_existing = $pdo->prepare("
                    SELECT * FROM users 
                    WHERE (email = ? OR phone = ?) 
                    AND role = 'Patient'
                ");
                $check_existing->execute([$patient['email'] ?? '', $patient['phone'] ?? '']);
                $existing_user = $check_existing->fetch();
                
                if ($existing_user) {
                    // Update existing user
                    $update_stmt = $pdo->prepare("
                        UPDATE users SET 
                        email = ?, 
                        phone = ?,
                        password_hash = ?, 
                        status = 'active',
                        updated_at = NOW()
                        WHERE id = ?
                    ");
                    $update_stmt->execute([$email, $phone, $hashed_password, $existing_user['id']]);
                    
                    $_SESSION['success'] = "Patient login credentials updated successfully! Temporary password: <strong>$temp_password</strong>";
                    $_SESSION['temp_password'] = $temp_password;
                    
                } else {
                    // Create new user
                    // Generate username from patient name
                    $base_username = strtolower($patient['first_name'][0] . $patient['last_name']) . $patient['id'];
                    $username = $base_username;
                    
                    // Check if username exists and make it unique
                    $counter = 1;
                    while (true) {
                        $check_username = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                        $check_username->execute([$username]);
                        if (!$check_username->fetch()) {
                            break;
                        }
                        $username = $base_username . $counter;
                        $counter++;
                    }
                    
                    // Insert new user
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO users (
                            role_id, username, first_name, last_name, email, phone, 
                            password_hash, role, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Patient', 'active', NOW())
                    ");
                    $insert_stmt->execute([
                        $role_id,
                        $username,
                        $patient['first_name'],
                        $patient['last_name'],
                        $email ?: null,
                        $phone ?: null,
                        $hashed_password
                    ]);
                    
                    $_SESSION['success'] = "Patient login enabled successfully! Temporary password: <strong>$temp_password</strong>";
                    $_SESSION['temp_password'] = $temp_password;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Setup error: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
    
    // Redirect back to the same page
    header("Location: setup_patient_login.php");
    exit;
}

// Check if we have a specific patient_id from URL
$selected_patient_id = isset($_GET['patient_id']) ? $_GET['patient_id'] : null;

// Get patients - with priority to the selected one
if ($selected_patient_id) {
    $patients_stmt = $pdo->prepare("
        SELECT p.*, 
               u.id as user_id, 
               u.username, 
               u.email as user_email,
               u.phone as user_phone
        FROM patients p 
        LEFT JOIN users u ON (
            (p.email = u.email OR p.phone = u.phone) 
            AND u.role = 'Patient'
        )
        WHERE p.id = ?
        ORDER BY p.created_at DESC
    ");
    $patients_stmt->execute([$selected_patient_id]);
    $patients = $patients_stmt->fetchAll();
} else {
    $patients_stmt = $pdo->query("
        SELECT p.*, 
               u.id as user_id, 
               u.username, 
               u.email as user_email,
               u.phone as user_phone
        FROM patients p 
        LEFT JOIN users u ON (
            (p.email = u.email OR p.phone = u.phone) 
            AND u.role = 'Patient'
        )
        WHERE p.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT 20
    ");
    $patients = $patients_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Patient Login - MediCare HMS</title>
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
            padding: 2rem;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        
        .header h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 0.875rem;
        }
        
        .back-link {
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
            font-size: 0.875rem;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .content {
            padding: 1.5rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        @media (max-width: 768px) {
            .content {
                grid-template-columns: 1fr;
            }
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            grid-column: 1 / -1;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .patient-list {
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }
        
        .patient-item {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.2s;
            cursor: pointer;
        }
        
        .patient-item:hover {
            background-color: #f9fafb;
        }
        
        .patient-item.selected {
            background-color: #e0f2fe;
            border-left: 4px solid #3b82f6;
        }
        
        .patient-item:last-child {
            border-bottom: none;
        }
        
        .patient-info h3 {
            font-size: 1rem;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }
        
        .patient-info p {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .login-status {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .has-login {
            background: #d1fae5;
            color: #065f46;
        }
        
        .no-login {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .setup-form {
            background: #f9fafb;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: border-color 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-control:read-only {
            background-color: #f3f4f6;
            cursor: not-allowed;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover:not(:disabled) {
            background: #2563eb;
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .btn-block {
            width: 100%;
            justify-content: center;
        }
        
        .password-result {
            margin-top: 1.5rem;
            padding: 1rem;
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 8px;
            color: #92400e;
        }
        
        .password-display {
            font-family: monospace;
            font-size: 1.25rem;
            font-weight: 600;
            text-align: center;
            margin: 0.5rem 0;
            padding: 0.5rem;
            background: white;
            border-radius: 4px;
            border: 1px dashed #f59e0b;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }
        
        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        
        .field-note {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
            display: block;
        }
        
        .required::after {
            content: " *";
            color: #ef4444;
        }
        
        .login-details {
            background: #f0f9ff;
            border: 1px solid #e0f2fe;
            border-radius: 6px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .login-details h4 {
            color: #0369a1;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .login-details p {
            color: #64748b;
            font-size: 0.75rem;
            margin: 0.25rem 0;
        }
        
        .login-details strong {
            color: #1e40af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-user-plus"></i> Setup Patient Login Access</h1>
            <p>Enable existing patients to access the patient portal</p>
            <a href="add_new_patients.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Patient Management
            </a>
        </div>
        
        <div class="content">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success']; ?>
                    
                    <?php if (isset($_SESSION['temp_password'])): ?>
                        <div class="password-result">
                            <p><strong>Important:</strong> Share this temporary password with the patient.</p>
                            <div class="password-display"><?php echo $_SESSION['temp_password']; ?></div>
                            <p style="font-size: 0.875rem; margin-top: 0.5rem;">
                                Patient can change this password after first login.
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <?php 
                    unset($_SESSION['success'], $_SESSION['temp_password']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <div>
                <h2 class="section-title">
                    <i class="fas fa-users"></i>
                    Patients List
                </h2>
                
                <?php if (count($patients) > 0): ?>
                    <div class="patient-list">
                        <?php foreach ($patients as $patient): 
                            $has_login = !empty($patient['user_id']);
                            $is_selected = $patient['id'] == $selected_patient_id;
                        ?>
                            <div class="patient-item <?php echo $is_selected ? 'selected' : ''; ?>" 
                                 onclick="selectPatient(<?php echo $patient['id']; ?>, '<?php echo htmlspecialchars(addslashes($patient['first_name'] . ' ' . $patient['last_name'])); ?>', '<?php echo htmlspecialchars(addslashes($patient['user_email'] ?? $patient['email'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($patient['user_phone'] ?? $patient['phone'] ?? '')); ?>', <?php echo $has_login ? 'true' : 'false'; ?>, '<?php echo htmlspecialchars(addslashes($patient['username'] ?? '')); ?>')">
                                <div class="patient-info">
                                    <h3><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h3>
                                    <p>
                                        ID: <?php echo htmlspecialchars($patient['id']); ?> | 
                                        Phone: <?php echo htmlspecialchars($patient['phone'] ?? 'Not set'); ?>
                                    </p>
                                    <?php if ($has_login && !empty($patient['username'])): ?>
                                        <p style="color: #059669; font-size: 0.75rem; margin-top: 0.25rem;">
                                            <i class="fas fa-check"></i> Login: <?php echo htmlspecialchars($patient['username']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <span class="login-status <?php echo $has_login ? 'has-login' : 'no-login'; ?>">
                                    <?php echo $has_login ? 'Has Login' : 'No Login'; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <h3>No Patients Found</h3>
                        <p>No patients available for login setup.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div>
                <h2 class="section-title">
                    <i class="fas fa-key"></i>
                    Setup Login Credentials
                </h2>
                
                <div class="setup-form">
                    <form method="POST" id="patientSetupForm">
                        <input type="hidden" name="patient_id" id="patient_id" value="<?php echo $selected_patient_id; ?>">
                        <input type="hidden" name="action" value="setup">
                        
                        <div class="form-group">
                            <label class="form-label">Patient Name</label>
                            <input type="text" id="patient_name" class="form-control" readonly 
                                   value="<?php echo $selected_patient_id ? htmlspecialchars($patients[0]['first_name'] . ' ' . $patients[0]['last_name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Patient ID</label>
                            <input type="text" id="patient_display_id" class="form-control" readonly 
                                   value="<?php echo $selected_patient_id ? htmlspecialchars($patients[0]['id']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" id="email" class="form-control" 
                                   placeholder="Enter email address for login"
                                   value="<?php echo $selected_patient_id ? htmlspecialchars($patients[0]['user_email'] ?? $patients[0]['email'] ?? '') : ''; ?>">
                            <span class="field-note">Required for login. Must be unique in the system.</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required">Phone Number</label>
                            <input type="tel" name="phone" id="phone" class="form-control" required 
                                   placeholder="Enter phone number for login"
                                   value="<?php echo $selected_patient_id ? htmlspecialchars($patients[0]['user_phone'] ?? $patients[0]['phone'] ?? '') : ''; ?>">
                            <span class="field-note">Required for login. Must be unique in the system.</span>
                        </div>
                        
                        <?php if ($selected_patient_id && !empty($patients[0]['user_id'])): ?>
                        <div class="login-details">
                            <h4><i class="fas fa-info-circle"></i> Current Login Details</h4>
                            <p><strong>Username:</strong> <?php echo htmlspecialchars($patients[0]['username'] ?? 'N/A'); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($patients[0]['user_email'] ?? 'N/A'); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($patients[0]['user_phone'] ?? 'N/A'); ?></p>
                            <p style="font-size: 0.7rem; color: #ef4444; margin-top: 0.5rem;">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Updating will generate a new temporary password.
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="clearForm()">
                                <i class="fas fa-times"></i> Clear
                            </button>
                            <button type="submit" class="btn btn-primary btn-block" id="submitBtn" <?php echo !$selected_patient_id ? 'disabled' : ''; ?>>
                                <i class="fas fa-user-check"></i> 
                                <span id="submitText">
                                    <?php 
                                    if ($selected_patient_id && !empty($patients[0]['user_id'])) {
                                        echo 'Update Login Credentials';
                                    } else {
                                        echo 'Enable Patient Login';
                                    }
                                    ?>
                                </span>
                            </button>
                        </div>
                        
                        <div style="margin-top: 1.5rem; padding: 1rem; background: #f0f9ff; border-radius: 6px; border: 1px solid #e0f2fe;">
                            <h4 style="color: #0369a1; font-size: 0.875rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-info-circle"></i> Information
                            </h4>
                            <p style="color: #64748b; font-size: 0.75rem; margin: 0;">
                                • A temporary password will be generated automatically<br>
                                • Patient can login with email or phone number<br>
                                • Patient should change password on first login<br>
                                • Access includes: Dashboard, Appointments, Medical Records<br>
                                • Email and phone must be unique in the system
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let hasExistingLogin = false;
        let existingUsername = '';
        
        function selectPatient(patientId, patientName, patientEmail, patientPhone, hasLogin = false, username = '') {
            document.getElementById('patient_id').value = patientId;
            document.getElementById('patient_name').value = patientName;
            document.getElementById('patient_display_id').value = patientId;
            document.getElementById('email').value = patientEmail || '';
            document.getElementById('phone').value = patientPhone || '';
            document.getElementById('submitBtn').disabled = false;
            
            hasExistingLogin = hasLogin;
            existingUsername = username;
            
            // Update submit button text
            const submitText = document.getElementById('submitText');
            if (hasLogin) {
                submitText.textContent = 'Update Login Credentials';
            } else {
                submitText.textContent = 'Enable Patient Login';
            }
            
            // Update URL without reloading page
            history.pushState(null, '', `?patient_id=${patientId}`);
            
            // Highlight selected patient
            document.querySelectorAll('.patient-item').forEach(item => {
                item.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
        }
        
        function clearForm() {
            document.getElementById('patient_id').value = '';
            document.getElementById('patient_name').value = '';
            document.getElementById('patient_display_id').value = '';
            document.getElementById('email').value = '';
            document.getElementById('phone').value = '';
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitText').textContent = 'Enable Patient Login';
            hasExistingLogin = false;
            existingUsername = '';
            
            // Update URL
            history.pushState(null, '', 'setup_patient_login.php');
            
            // Remove selection highlight
            document.querySelectorAll('.patient-item').forEach(item => {
                item.classList.remove('selected');
            });
        }
        
        // Auto-select patient if coming from URL
        <?php if ($selected_patient_id && count($patients) > 0): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const patient = <?php echo json_encode($patients[0]); ?>;
            const hasLogin = !!(patient.user_id);
            selectPatient(
                patient.id, 
                patient.first_name + ' ' + patient.last_name, 
                patient.user_email || patient.email || '', 
                patient.user_phone || patient.phone || '',
                hasLogin,
                patient.username || ''
            );
        });
        <?php endif; ?>
        
        // Form validation
        document.getElementById('patientSetupForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const patientId = document.getElementById('patient_id').value;
            
            if (!patientId) {
                e.preventDefault();
                alert('Please select a patient first.');
                return false;
            }
            
            // Validate at least one credential is provided
            if (!email && !phone) {
                e.preventDefault();
                alert('Please provide either email or phone number for login.');
                return false;
            }
            
            // Validate email format if provided
            if (email && !isValidEmail(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
            
            // Validate phone if provided
            if (phone && !isValidPhone(phone)) {
                e.preventDefault();
                alert('Please enter a valid phone number (10-15 digits).');
                return false;
            }
            
            // Confirm action
            let message = hasExistingLogin 
                ? 'Are you sure you want to update the login credentials for this patient? This will generate a new temporary password.'
                : 'Are you sure you want to enable login access for this patient? A temporary password will be generated.';
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
            
            // Re-enable button if form submission fails
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 5000);
            
            return true;
        });
        
        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
        
        function isValidPhone(phone) {
            // Basic phone validation - allow digits, spaces, dashes, parentheses
            const cleaned = phone.replace(/\D/g, '');
            return cleaned.length >= 10 && cleaned.length <= 15;
        }
        
        // Auto-format phone number
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                value = value.substring(0, 15);
                e.target.value = value;
            }
        });
    </script>
</body>
</html>