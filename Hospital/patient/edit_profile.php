<?php
session_start();

// Check if user is logged in as patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Patient') {
    header("Location: ../login.php?role=patient");
    exit();
}

// Try different possible database connection files
$conn = null;

if (file_exists("../includes/db_connect.php")) {
    include "../includes/db_connect.php";
} elseif (file_exists("../config/database.php")) {
    include "../config/database.php";
} elseif (file_exists("../db_connect.php")) {
    include "../db_connect.php";
} elseif (file_exists("includes/db_connect.php")) {
    include "includes/db_connect.php";
} elseif (file_exists("config/database.php")) {
    include "config/database.php";
} else {
    // Create connection if it doesn't exist
    $host = '127.0.0.1';
    $dbname = 'hospital';
    $username = 'root';
    $password = '';
    
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        $_SESSION['error'] = "Database connection failed!";
        header("Location: dashboard.php");
        exit();
    }
}

// If connection is still null, try the global connection from other files
if (!isset($conn) && isset($pdo)) {
    // If PDO connection exists, we need to use mysqli instead
    $conn = new mysqli('127.0.0.1', 'root', '', 'hospital');
} elseif (!isset($conn) && isset($connection)) {
    // If $connection exists (from connection.php)
    $conn = $connection;
}

// Final check - if still no connection, create one
if (!isset($conn) || $conn === null) {
    $conn = new mysqli('127.0.0.1', 'root', '', 'hospital');
    
    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }
}

$user_id = $_SESSION['user_id'];
$patient_id = $_SESSION['patient_id'] ?? null;

// Fetch current user and patient data
$user_data = [];
$patient_data = [];

try {
    // Fetch user data
    $stmt = $conn->prepare("
        SELECT id, username, email, first_name, last_name, phone, created_at
        FROM users 
        WHERE id = ? AND role = 'Patient' AND status = 'active'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    
    if (!$user_data) {
        $_SESSION['error'] = "User not found!";
        header("Location: ../login.php?role=patient");
        exit();
    }
    
    // Fetch patient data using email/phone matching
    $stmt = $conn->prepare("
        SELECT * FROM patients 
        WHERE (email = ? OR phone = ?)
        AND status = 'Active'
        LIMIT 1
    ");
    $stmt->bind_param("ss", $user_data['email'], $user_data['phone']);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient_data = $result->fetch_assoc();
    
    // If no patient record exists, create basic structure
    if (!$patient_data) {
        $patient_data = [
            'patient_id' => 'PT' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
            'first_name' => $user_data['first_name'],
            'last_name' => $user_data['last_name'],
            'email' => $user_data['email'],
            'phone' => $user_data['phone'],
            'date_of_birth' => '2000-01-01',
            'gender' => 'Other',
            'marital_status' => 'single'
        ];
    } else {
        $_SESSION['patient_id'] = $patient_data['id'];
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching profile data: " . $e->getMessage();
    header("Location: dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get form data
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $marital_status = $_POST['marital_status'];
    $address = trim($_POST['address']);
    $height = $_POST['height'] ? floatval($_POST['height']) : null;
    $weight = $_POST['weight'] ? floatval($_POST['weight']) : null;
    $blood_type = $_POST['blood_type'] ?? null;
    
    // Emergency contact info
    $emergency_contact_name = trim($_POST['emergency_contact_name']);
    $relationship = trim($_POST['relationship']);
    $emergency_contact_phone = trim($_POST['emergency_contact_phone']);
    $emergency_email = trim($_POST['emergency_email']);
    
    // Insurance info
    $insurance_provider = trim($_POST['insurance_provider']);
    $insurance_id = trim($_POST['insurance_id']);
    
    // Medical history
    $medical_history = trim($_POST['medical_history']);
    $chronic_conditions = trim($_POST['chronic_conditions']);
    $past_surgeries = trim($_POST['past_surgeries']);
    $family_history = trim($_POST['family_history']);
    $allergies = trim($_POST['allergies']);
    $current_medications = trim($_POST['current_medications']);
    
    // Lifestyle
    $smoking_status = $_POST['smoking_status'] ?? null;
    $alcohol_consumption = $_POST['alcohol_consumption'] ?? null;
    $exercise_frequency = $_POST['exercise_frequency'] ?? null;
    $dietary_habits = trim($_POST['dietary_habits']);
    
    // Calculate age
    try {
        $birthDate = new DateTime($date_of_birth);
        $today = new DateTime();
        if ($birthDate > $today) {
            $_SESSION['error'] = "Date of birth cannot be in the future!";
            header("Location: edit_profile.php");
            exit();
        }
        $age = $today->diff($birthDate)->y;
    } catch (Exception $e) {
        $_SESSION['error'] = "Invalid date format!";
        header("Location: edit_profile.php");
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Check if new email already exists in users table (excluding current user)
        if ($email !== $user_data['email']) {
            $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_email->bind_param("si", $email, $user_id);
            $check_email->execute();
            if ($check_email->get_result()->num_rows > 0) {
                throw new Exception("Email already exists! Please use a different email.");
            }
        }
        
        // Check if new phone already exists in users table (excluding current user)
        if ($phone !== $user_data['phone']) {
            $check_phone = $conn->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
            $check_phone->bind_param("si", $phone, $user_id);
            $check_phone->execute();
            if ($check_phone->get_result()->num_rows > 0) {
                throw new Exception("Phone number already exists! Please use a different phone number.");
            }
        }
        
        // 1. Update users table
        $update_user_stmt = $conn->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, email = ?, phone = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $update_user_stmt->bind_param("ssssi", $first_name, $last_name, $email, $phone, $user_id);
        
        if (!$update_user_stmt->execute()) {
            throw new Exception("Failed to update user account: " . $conn->error);
        }
        
        // 2. Update or insert into patients table
        if ($patient_data && isset($patient_data['id'])) {
            // Update existing patient record
            $update_patient_stmt = $conn->prepare("
                UPDATE patients 
                SET 
                    first_name = ?,
                    last_name = ?,
                    date_of_birth = ?,
                    age = ?,
                    gender = ?,
                    marital_status = ?,
                    phone = ?,
                    email = ?,
                    address = ?,
                    height = ?,
                    weight = ?,
                    blood_type = ?,
                    emergency_contact_name = ?,
                    relationship = ?,
                    emergency_contact_phone = ?,
                    emergency_email = ?,
                    insurance_provider = ?,
                    insurance_id = ?,
                    medical_history = ?,
                    chronic_conditions = ?,
                    past_surgeries = ?,
                    family_history = ?,
                    allergies = ?,
                    current_medications = ?,
                    smoking_status = ?,
                    alcohol_consumption = ?,
                    exercise_frequency = ?,
                    dietary_habits = ?,
                    updated_at = NOW(),
                    updated_by = ?
                WHERE id = ?
            ");
            
            // Prepare null values for database
            $height_db = $height ?: null;
            $weight_db = $weight ?: null;
            $blood_type_db = $blood_type ?: null;
            $smoking_status_db = $smoking_status ?: null;
            $alcohol_consumption_db = $alcohol_consumption ?: null;
            $exercise_frequency_db = $exercise_frequency ?: null;
            
            $update_patient_stmt->bind_param(
                "sssisssssddsssssssssssssssssii",
                $first_name,
                $last_name,
                $date_of_birth,
                $age,
                $gender,
                $marital_status,
                $phone,
                $email,
                $address,
                $height_db,
                $weight_db,
                $blood_type_db,
                $emergency_contact_name,
                $relationship,
                $emergency_contact_phone,
                $emergency_email,
                $insurance_provider,
                $insurance_id,
                $medical_history,
                $chronic_conditions,
                $past_surgeries,
                $family_history,
                $allergies,
                $current_medications,
                $smoking_status_db,
                $alcohol_consumption_db,
                $exercise_frequency_db,
                $dietary_habits,
                $user_id,
                $patient_data['id']
            );
            
            if (!$update_patient_stmt->execute()) {
                throw new Exception("Failed to update patient record: " . $conn->error);
            }
            
        } else {
            // Insert new patient record
            $patient_id_new = 'PT' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            
            $insert_patient_stmt = $conn->prepare("
                INSERT INTO patients (
                    patient_id, first_name, last_name, date_of_birth, age, 
                    gender, marital_status, phone, email, address,
                    height, weight, blood_type, emergency_contact_name,
                    relationship, emergency_contact_phone, emergency_email,
                    insurance_provider, insurance_id, medical_history,
                    chronic_conditions, past_surgeries, family_history,
                    allergies, current_medications, smoking_status,
                    alcohol_consumption, exercise_frequency, dietary_habits,
                    status, created_at, updated_at, updated_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', NOW(), NOW(), ?)
            ");
            
            // Prepare null values for database
            $height_db = $height ?: null;
            $weight_db = $weight ?: null;
            $blood_type_db = $blood_type ?: null;
            $smoking_status_db = $smoking_status ?: null;
            $alcohol_consumption_db = $alcohol_consumption ?: null;
            $exercise_frequency_db = $exercise_frequency ?: null;
            
            $insert_patient_stmt->bind_param(
                "ssssisssssddsssssssssssssssssi",
                $patient_id_new,
                $first_name,
                $last_name,
                $date_of_birth,
                $age,
                $gender,
                $marital_status,
                $phone,
                $email,
                $address,
                $height_db,
                $weight_db,
                $blood_type_db,
                $emergency_contact_name,
                $relationship,
                $emergency_contact_phone,
                $emergency_email,
                $insurance_provider,
                $insurance_id,
                $medical_history,
                $chronic_conditions,
                $past_surgeries,
                $family_history,
                $allergies,
                $current_medications,
                $smoking_status_db,
                $alcohol_consumption_db,
                $exercise_frequency_db,
                $dietary_habits,
                $user_id
            );
            
            if (!$insert_patient_stmt->execute()) {
                throw new Exception("Failed to create patient record: " . $conn->error);
            }
            
            $_SESSION['patient_id'] = $conn->insert_id;
            $patient_data['id'] = $_SESSION['patient_id'];
        }
        
        // Commit transaction
        $conn->commit();
        
        // Update session data
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name'] = $last_name;
        $_SESSION['email'] = $email;
        $_SESSION['phone'] = $phone;
        $_SESSION['patient_name'] = $first_name . ' ' . $last_name;
        
        $_SESSION['success'] = "Profile updated successfully!";
        header("Location: dashboard.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        
        $_SESSION['error'] = $e->getMessage();
        header("Location: edit_profile.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - AuraCare HMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: #333;
            min-height: 100vh;
        }

        .edit-profile-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .header h1 {
            color: #2c3e50;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }

        .back-btn:hover {
            background: #45a049;
        }

        .form-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .form-tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab-btn {
            padding: 12px 24px;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: #7f8c8d;
            position: relative;
            transition: color 0.3s;
        }

        .tab-btn.active {
            color: #4CAF50;
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 3px;
            background: #4CAF50;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        label .required {
            color: #f44336;
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="date"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="tel"]:focus,
        input[type="date"]:focus,
        input[type="number"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 5px;
        }

        .radio-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            cursor: pointer;
        }

        .radio-label input[type="radio"] {
            margin: 0;
            width: auto;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-primary:hover {
            background: #45a049;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-reset {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
        }

        .btn-reset:hover {
            background: #e9ecef;
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .help-text {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle .toggle-btn {
            position: absolute;
            right: 10px;
            top: 38px;
            background: none;
            border: none;
            color: #7f8c8d;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .edit-profile-container {
                padding: 15px;
            }
            
            .form-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="edit-profile-container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-user-edit"></i> Edit Profile</h1>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="" class="form-container">
            <!-- Tabs -->
            <div class="form-tabs">
                <button type="button" class="tab-btn active" data-tab="personal">Personal Info</button>
                <button type="button" class="tab-btn" data-tab="contact">Contact & Emergency</button>
                <button type="button" class="tab-btn" data-tab="medical">Medical History</button>
                <button type="button" class="tab-btn" data-tab="lifestyle">Lifestyle</button>
            </div>

            <!-- Personal Info Tab -->
            <div class="tab-content active" id="personal-tab">
                <div class="form-section">
                    <h3><i class="fas fa-user"></i> Basic Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>First Name <span class="required">*</span></label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($patient_data['first_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Last Name <span class="required">*</span></label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($patient_data['last_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Date of Birth <span class="required">*</span></label>
                            <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($patient_data['date_of_birth']); ?>" required max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Gender <span class="required">*</span></label>
                            <select name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo ($patient_data['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($patient_data['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($patient_data['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Marital Status</label>
                            <select name="marital_status">
                                <option value="">Select Status</option>
                                <option value="single" <?php echo ($patient_data['marital_status'] == 'single') ? 'selected' : ''; ?>>Single</option>
                                <option value="married" <?php echo ($patient_data['marital_status'] == 'married') ? 'selected' : ''; ?>>Married</option>
                                <option value="divorced" <?php echo ($patient_data['marital_status'] == 'divorced') ? 'selected' : ''; ?>>Divorced</option>
                                <option value="widowed" <?php echo ($patient_data['marital_status'] == 'widowed') ? 'selected' : ''; ?>>Widowed</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Blood Type</label>
                            <select name="blood_type">
                                <option value="">Select Blood Type</option>
                                <option value="A+" <?php echo ($patient_data['blood_type'] == 'A+') ? 'selected' : ''; ?>>A+</option>
                                <option value="A-" <?php echo ($patient_data['blood_type'] == 'A-') ? 'selected' : ''; ?>>A-</option>
                                <option value="B+" <?php echo ($patient_data['blood_type'] == 'B+') ? 'selected' : ''; ?>>B+</option>
                                <option value="B-" <?php echo ($patient_data['blood_type'] == 'B-') ? 'selected' : ''; ?>>B-</option>
                                <option value="AB+" <?php echo ($patient_data['blood_type'] == 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                <option value="AB-" <?php echo ($patient_data['blood_type'] == 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                <option value="O+" <?php echo ($patient_data['blood_type'] == 'O+') ? 'selected' : ''; ?>>O+</option>
                                <option value="O-" <?php echo ($patient_data['blood_type'] == 'O-') ? 'selected' : ''; ?>>O-</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-weight"></i> Physical Measurements</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Height (cm)</label>
                            <input type="number" name="height" step="0.1" min="0" max="300" value="<?php echo htmlspecialchars($patient_data['height'] ?? ''); ?>">
                            <div class="help-text">Enter height in centimeters</div>
                        </div>
                        
                        <div class="form-group">
                            <label>Weight (kg)</label>
                            <input type="number" name="weight" step="0.1" min="0" max="500" value="<?php echo htmlspecialchars($patient_data['weight'] ?? ''); ?>">
                            <div class="help-text">Enter weight in kilograms</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact & Emergency Tab -->
            <div class="tab-content" id="contact-tab">
                <div class="form-section">
                    <h3><i class="fas fa-address-book"></i> Contact Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Email Address <span class="required">*</span></label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($patient_data['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Phone Number <span class="required">*</span></label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($patient_data['phone']); ?>" required pattern="[0-9+\s\-()]{10,15}">
                            <div class="help-text">Format: +254712345678 or 0712345678</div>
                        </div>
                        
                        <div class="form-group">
                            <label>Physical Address</label>
                            <textarea name="address"><?php echo htmlspecialchars($patient_data['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-first-aid"></i> Emergency Contact</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Emergency Contact Name</label>
                            <input type="text" name="emergency_contact_name" value="<?php echo htmlspecialchars($patient_data['emergency_contact_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Relationship</label>
                            <input type="text" name="relationship" value="<?php echo htmlspecialchars($patient_data['relationship'] ?? ''); ?>">
                            <div class="help-text">e.g., Spouse, Parent, Sibling, Friend</div>
                        </div>
                        
                        <div class="form-group">
                            <label>Emergency Contact Phone</label>
                            <input type="tel" name="emergency_contact_phone" value="<?php echo htmlspecialchars($patient_data['emergency_contact_phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Emergency Email</label>
                            <input type="email" name="emergency_email" value="<?php echo htmlspecialchars($patient_data['emergency_email'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-shield-alt"></i> Insurance Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Insurance Provider</label>
                            <input type="text" name="insurance_provider" value="<?php echo htmlspecialchars($patient_data['insurance_provider'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Insurance ID/Policy Number</label>
                            <input type="text" name="insurance_id" value="<?php echo htmlspecialchars($patient_data['insurance_id'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Medical History Tab -->
            <div class="tab-content" id="medical-tab">
                <div class="form-section">
                    <h3><i class="fas fa-history"></i> Medical History</h3>
                    <div class="form-group">
                        <label>Medical History</label>
                        <textarea name="medical_history" placeholder="Any past medical conditions, illnesses, or hospitalizations..."><?php echo htmlspecialchars($patient_data['medical_history'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Chronic Conditions</label>
                        <textarea name="chronic_conditions" placeholder="e.g., Diabetes, Hypertension, Asthma..."><?php echo htmlspecialchars($patient_data['chronic_conditions'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Past Surgeries</label>
                        <textarea name="past_surgeries" placeholder="List any past surgeries with dates if known..."><?php echo htmlspecialchars($patient_data['past_surgeries'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-users"></i> Family History</h3>
                    <div class="form-group">
                        <label>Family Medical History</label>
                        <textarea name="family_history" placeholder="Any hereditary conditions in your family..."><?php echo htmlspecialchars($patient_data['family_history'] ?? ''); ?></textarea>
                        <div class="help-text">Include conditions like heart disease, diabetes, cancer in close relatives</div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-allergies"></i> Allergies & Medications</h3>
                    <div class="form-group">
                        <label>Allergies</label>
                        <textarea name="allergies" placeholder="List any allergies (drugs, food, environmental)..."><?php echo htmlspecialchars($patient_data['allergies'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Current Medications</label>
                        <textarea name="current_medications" placeholder="List all current medications with dosages..."><?php echo htmlspecialchars($patient_data['current_medications'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Lifestyle Tab -->
            <div class="tab-content" id="lifestyle-tab">
                <div class="form-section">
                    <h3><i class="fas fa-smoking"></i> Habits</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Smoking Status</label>
                            <select name="smoking_status">
                                <option value="">Select</option>
                                <option value="never" <?php echo ($patient_data['smoking_status'] == 'never') ? 'selected' : ''; ?>>Never Smoked</option>
                                <option value="former" <?php echo ($patient_data['smoking_status'] == 'former') ? 'selected' : ''; ?>>Former Smoker</option>
                                <option value="current" <?php echo ($patient_data['smoking_status'] == 'current') ? 'selected' : ''; ?>>Current Smoker</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Alcohol Consumption</label>
                            <select name="alcohol_consumption">
                                <option value="">Select</option>
                                <option value="none" <?php echo ($patient_data['alcohol_consumption'] == 'none') ? 'selected' : ''; ?>>None</option>
                                <option value="social" <?php echo ($patient_data['alcohol_consumption'] == 'social') ? 'selected' : ''; ?>>Social Drinker</option>
                                <option value="moderate" <?php echo ($patient_data['alcohol_consumption'] == 'moderate') ? 'selected' : ''; ?>>Moderate</option>
                                <option value="heavy" <?php echo ($patient_data['alcohol_consumption'] == 'heavy') ? 'selected' : ''; ?>>Heavy</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-running"></i> Exercise & Diet</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Exercise Frequency</label>
                            <select name="exercise_frequency">
                                <option value="">Select</option>
                                <option value="none" <?php echo ($patient_data['exercise_frequency'] == 'none') ? 'selected' : ''; ?>>None</option>
                                <option value="rarely" <?php echo ($patient_data['exercise_frequency'] == 'rarely') ? 'selected' : ''; ?>>Rarely</option>
                                <option value="1-2" <?php echo ($patient_data['exercise_frequency'] == '1-2') ? 'selected' : ''; ?>>1-2 times/week</option>
                                <option value="3+" <?php echo ($patient_data['exercise_frequency'] == '3+') ? 'selected' : ''; ?>>3+ times/week</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Dietary Habits</label>
                            <textarea name="dietary_habits" placeholder="e.g., Vegetarian, Vegan, Low-sodium, Gluten-free..."><?php echo htmlspecialchars($patient_data['dietary_habits'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Buttons -->
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <button type="reset" class="btn btn-reset">
                    <i class="fas fa-redo"></i> Reset Form
                </button>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        $(document).ready(function() {
            // Tab switching
            $('.tab-btn').click(function() {
                const tabId = $(this).data('tab');
                
                // Update active tab button
                $('.tab-btn').removeClass('active');
                $(this).addClass('active');
                
                // Show active tab content
                $('.tab-content').removeClass('active');
                $('#' + tabId + '-tab').addClass('active');
            });
            
            // Form validation
            $('form').submit(function(e) {
                let valid = true;
                
                // Check required fields
                $('input[required], select[required]').each(function() {
                    if (!$(this).val().trim()) {
                        valid = false;
                        $(this).css('border-color', '#f44336');
                        $(this).focus();
                    } else {
                        $(this).css('border-color', '#ddd');
                    }
                });
                
                if (!valid) {
                    e.preventDefault();
                    alert('Please fill in all required fields marked with *');
                }
            });
            
            // Real-time validation
            $('input, select, textarea').on('blur', function() {
                if ($(this).attr('required') && !$(this).val().trim()) {
                    $(this).css('border-color', '#f44336');
                } else {
                    $(this).css('border-color', '#ddd');
                }
            });
            
            // Phone number formatting
            $('input[name="phone"], input[name="emergency_contact_phone"]').on('blur', function() {
                let phone = $(this).val().replace(/\D/g, '');
                if (phone.length === 9 && phone.startsWith('7')) {
                    $(this).val('0' + phone);
                } else if (phone.length === 9 && phone.startsWith('1')) {
                    $(this).val('0' + phone);
                }
            });
            
            // Date of birth validation
            $('input[name="date_of_birth"]').on('change', function() {
                const dob = new Date($(this).val());
                const today = new Date();
                
                if (dob > today) {
                    alert('Date of birth cannot be in the future!');
                    $(this).val('');
                    $(this).css('border-color', '#f44336');
                }
            });
            
            // Height validation
            $('input[name="height"]').on('blur', function() {
                const height = parseFloat($(this).val());
                if (height && (height < 30 || height > 250)) {
                    alert('Please enter a valid height between 30 and 250 cm');
                    $(this).val('');
                }
            });
            
            // Weight validation
            $('input[name="weight"]').on('blur', function() {
                const weight = parseFloat($(this).val());
                if (weight && (weight < 1 || weight > 300)) {
                    alert('Please enter a valid weight between 1 and 300 kg');
                    $(this).val('');
                }
            });
            
            // Auto-switch to tab with errors
            $('form').on('invalid', function() {
                const firstError = $('input:invalid, select:invalid')[0];
                if (firstError) {
                    const tabId = $(firstError).closest('.tab-content').attr('id').replace('-tab', '');
                    $('.tab-btn').removeClass('active');
                    $(`.tab-btn[data-tab="${tabId}"]`).addClass('active');
                    $('.tab-content').removeClass('active');
                    $(`#${tabId}-tab`).addClass('active');
                }
            }, true);
        });
    </script>
</body>
</html>