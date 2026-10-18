<?php
session_start();

// Gets role from query string
$role = isset($_GET['role']) ? strtolower($_GET['role']) : 'guest';

// Define background images for each role
$roleBackgrounds = [
    'super-admin'   => 'public/assets/images/default_lab_bg.jpg',
    'admin'         => 'public/assets/images/admin_bg.jpg',
    'doctor'        => 'public/assets/images/doctor_bg.jpg',
    'nurse'         => 'public/assets/images/nurse_bg.jpg',
    'pharmacist'    => 'public/assets/images/pharmacy_bg.jpg',
    'lab'           => 'public/assets/images/lab_bg.jpg',
    'accountant'    => 'public/assets/images/accountant_bg.jpg',
    'receptionist'  => 'public/assets/images/receptionist_bg.jpg',
    'patient'       => 'public/assets/images/patient_bg.jpg',
    'guest'         => 'public/assets/images/default_bg.jpg'
];

// Pick background image based on role
$bgImage = $roleBackgrounds[$role] ?? $roleBackgrounds['guest'];

// Check if patient role
$isPatient = ($role === 'patient');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in - <?php echo ucfirst($role); ?> | AuraCare HMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #1a6fc4;
            --dark-blue: #01203d;
            --light-blue: #4dabf7;
            --glass-bg: rgba(1, 32, 61, 0.92);
        }
        
        body {
            background: url('<?php echo $bgImage; ?>') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            position: relative;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(1, 32, 61, 0.85) 0%, rgba(26, 111, 196, 0.6) 100%);
            z-index: 0;
        }
        
        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: <?php echo $isPatient ? '550px' : '650px'; ?>;
            padding: 5px;
        }
        
        .login-card {
            background: var(--glass-bg);
            color: white;
            backdrop-filter: blur(20px);
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3),
                        0 0 0 1px rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .brand-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .brand-header h2 {
            color: white;
            font-weight: 700;
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
        }
        
        .brand-header .logo-icon {
            color: var(--light-blue);
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .brand-header p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1rem;
            letter-spacing: 0.5px;
        }
        
        /* Tabs Styling */
        .nav-tabs {
            border: none;
            margin-bottom: 2rem;
            gap: 10px;
        }
        
        .nav-tabs .nav-link {
            color: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.05);
            padding: 12px 25px;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            flex: 1;
            text-align: center;
        }
        
        .nav-tabs .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.25);
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, var(--primary-blue), var(--light-blue));
            color: white;
            border: none;
            box-shadow: 0 5px 15px rgba(26, 111, 196, 0.4);
        }
        
        /* Form Styling */
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
        }
        
        .form-control {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: white;
            padding: 12px 15px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            background: rgba(255, 255, 255, 0.12);
            border-color: var(--light-blue);
            box-shadow: 0 0 0 0.25rem rgba(77, 171, 247, 0.25);
            color: white;
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--light-blue));
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(26, 111, 196, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 111, 196, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }
        
        /* Alerts */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.15);
            color: #d4edda;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.15);
            color: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        
        /* Links */
        .forgot-link {
            color: var(--light-blue) !important;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .forgot-link:hover {
            color: #80d0ff !important;
            text-decoration: underline;
        }
        
        .back-link {
            color: rgba(255, 255, 255, 0.7) !important;
            text-decoration: none;
            font-size: 0.95rem;
            transition: color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-link:hover {
            color: white !important;
        }
        
        /* Tab Content */
        .tab-pane {
            padding-top: 0.5rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .login-card {
                padding: 1.5rem;
            }
            
            .nav-tabs .nav-link {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <div class="brand-header">
            <i class="fas fa-heartbeat logo-icon"></i>
            <h2>AuraCare HMS</h2>
            <p><?php echo ucfirst($role); ?> Portal</p>
        </div>
        
        <?php if ($isPatient): ?>
        <!-- Patient Login/Register Tabs -->
        <ul class="nav nav-tabs nav-fill" id="patientTabs" role="tablist">
            <!-- <li class="nav-item" role="presentation">
                <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#loginTab" 
                        type="button" role="tab" aria-controls="loginTab" aria-selected="true">
                    <i class="fas fa-sign-in-alt me-2"></i>Patient Login
                </button>
            </li> -->
           
        </ul>

        <div class="tab-content" id="patientTabContent">
            <!-- Login Tab - SIMPLIFIED: Only username, password, login button -->
            <div class="tab-pane fade show active" id="loginTab" role="tabpanel" aria-labelledby="login-tab">
                <?php if (isset($_GET['message']) && $_GET['message'] === 'registered'): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>Registration successful! Please login.
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="process_login.php">
                    <input type="hidden" name="role" value="patient">
                    
                    <div class="mb-4">
                        <label for="username" class="form-label">
                            <i class="fas fa-user me-2"></i>Username or Email
                        </label>
                        <input type="text" name="username" id="username" class="form-control" 
                               placeholder="Enter your username or email" required>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock me-2"></i>Password
                        </label>
                        <input type="password" name="password" id="password" class="form-control" 
                               placeholder="Enter your password" required>
                    </div>

                    <div class="d-grid gap-2 mb-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Login to Account
                        </button>
                    </div>
                    
                    <!-- <div class="text-center">
                        <a href="#" onclick="showForgotPassword()" class="forgot-link">
                            <i class="fas fa-key me-1"></i>Forgot Password?
                        </a>
                    </div> -->
                </form>
            </div>
            
            <!-- Register Tab -->
            <!-- <div class="tab-pane fade" id="registerTab" role="tabpanel" aria-labelledby="register-tab">
                <h5 class="text-center mb-4">Create New Patient Account</h5>
                <form method="POST" action="patient_register.php" id="registerForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" name="first_name" id="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" name="last_name" id="last_name" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number *</label>
                        <input type="tel" name="phone" id="phone" class="form-control" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_of_birth" class="form-label">Date of Birth *</label>
                            <input type="date" name="date_of_birth" id="date_of_birth" class="form-control" 
                                   max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="gender" class="form-label">Gender *</label>
                            <select name="gender" id="gender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reg_username" class="form-label">Username *</label>
                        <input type="text" name="username" id="reg_username" class="form-control" required>
                        <small class="text-muted">Choose a unique username for login</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reg_password" class="form-label">Password *</label>
                        <input type="password" name="password" id="reg_password" class="form-control" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                        <div class="invalid-feedback" id="passwordError">Passwords do not match!</div>
                    </div>
                    
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="terms" required>
                        <label class="form-check-label" for="terms">
                            I agree to the <a href="#" class="text-info">Terms & Conditions</a>
                        </label>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-user-plus me-2"></i>Create Account
                        </button>
                    </div>
                </form>
            </div> -->
        </div>
        
        <?php else: ?>
        <!-- Default Login for other roles (simplified) -->
        <h3 class="text-center mb-4">Log in as <?php echo ucfirst($role); ?></h3>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="process_login.php">
            <input type="hidden" name="role" value="<?php echo $role; ?>">

            <div class="mb-4">
                <label for="username" class="form-label">Username</label>
                <input type="text" name="username" id="username" class="form-control" placeholder="Enter your username" required>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3">
                <i class="fas fa-sign-in-alt me-2"></i>Login
            </button>
        </form>
        <?php endif; ?>
        
        <!-- Back Link - Centered at bottom -->
        <div class="text-center mt-4 pt-3 border-top border-secondary">
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i>Back to Role Selection
            </a>
        </div>
        
        <?php if (isset($_GET['message']) && $_GET['message'] === 'loggedout'): ?>
            <div class="alert alert-success text-center mt-3">
                <i class="fas fa-check-circle me-2"></i>You have been logged out successfully.
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Password confirmation validation
document.getElementById('registerForm')?.addEventListener('submit', function(e) {
    const password = document.getElementById('reg_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const confirmInput = document.getElementById('confirm_password');
    const passwordError = document.getElementById('passwordError');
    
    if (password !== confirmPassword) {
        e.preventDefault();
        confirmInput.classList.add('is-invalid');
        passwordError.style.display = 'block';
        return false;
    }
});

function showForgotPassword() {
    alert('Please contact hospital administration to reset your password for you.');
}
</script>
</body>
</html>