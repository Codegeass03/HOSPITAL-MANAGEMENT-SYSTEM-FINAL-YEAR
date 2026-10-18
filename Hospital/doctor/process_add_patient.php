<?php
session_start();

// Include database connection
include "includes/db_connect.php";

// Check if $pdo is defined
if (!isset($pdo)) {
    $_SESSION['error'] = "Database connection failed.";
    header("Location: add_new_patients.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Get and sanitize form inputs
        $first_name = trim($_POST['first_name']);
        $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : null;
        $last_name = trim($_POST['last_name']);
        $date_of_birth = trim($_POST['date_of_birth']);
        $gender = trim($_POST['gender']);
        $marital_status = isset($_POST['marital_status']) ? trim($_POST['marital_status']) : null;
        $phone = trim($_POST['phone']);
        $preferred_contact = isset($_POST['preferred_contact']) ? trim($_POST['preferred_contact']) : 'Phone';
        $email = isset($_POST['email']) ? trim($_POST['email']) : null;
        $address = isset($_POST['address']) ? trim($_POST['address']) : null;
        
        // Medical info
        $height = isset($_POST['height']) && !empty($_POST['height']) ? trim($_POST['height']) : null;
        $weight = isset($_POST['weight']) && !empty($_POST['weight']) ? trim($_POST['weight']) : null;
        $blood_type = isset($_POST['blood_type']) && !empty($_POST['blood_type']) ? trim($_POST['blood_type']) : null;
        
        // Emergency contact
        $emergency_contact_name = isset($_POST['emergency_name']) ? trim($_POST['emergency_name']) : null;
        $relationship = isset($_POST['relationship']) ? trim($_POST['relationship']) : null;
        $emergency_contact_phone = isset($_POST['emergency_phone']) ? trim($_POST['emergency_phone']) : null;
        $emergency_email = isset($_POST['emergency_email']) ? trim($_POST['emergency_email']) : null;
        
        // Insurance
        $insurance_provider = isset($_POST['insurance_provider']) ? trim($_POST['insurance_provider']) : null;
        $insurance_id = isset($_POST['policy_number']) ? trim($_POST['policy_number']) : null;
        
        // Medical history
        $allergies = isset($_POST['allergies']) ? trim($_POST['allergies']) : null;
        $current_medications = isset($_POST['current_medications']) ? trim($_POST['current_medications']) : null;
        $chronic_conditions = isset($_POST['chronic_conditions']) ? trim($_POST['chronic_conditions']) : null;
        $past_surgeries = isset($_POST['past_surgeries']) ? trim($_POST['past_surgeries']) : null;
        
        // Family history
        $family_history = null;
        if (isset($_POST['family_history']) && is_array($_POST['family_history'])) {
            $family_history = implode(', ', $_POST['family_history']);
        }
        $additional_family_notes = isset($_POST['additional_family_notes']) ? trim($_POST['additional_family_notes']) : null;
        
        // Lifestyle
        $smoking_status = isset($_POST['smoking_status']) ? trim($_POST['smoking_status']) : null;
        $alcohol_consumption = isset($_POST['alcohol_consumption']) ? trim($_POST['alcohol_consumption']) : null;
        $exercise_frequency = isset($_POST['exercise_frequency']) ? trim($_POST['exercise_frequency']) : null;
        $dietary_habits = isset($_POST['dietary_habits']) ? trim($_POST['dietary_habits']) : null;
        
        // Validation array
        $errors = [];
        
        // Validate required fields
        if (empty($first_name)) {
            $errors[] = "First name is required";
        }
        
        if (empty($last_name)) {
            $errors[] = "Last name is required";
        }
        
        if (empty($date_of_birth)) {
            $errors[] = "Date of birth is required";
        } else {
            // Validate date format and calculate age
            $dob = DateTime::createFromFormat('Y-m-d', $date_of_birth);
            if (!$dob || $dob->format('Y-m-d') !== $date_of_birth) {
                $errors[] = "Please enter a valid date of birth (YYYY-MM-DD)";
            } else {
                // Calculate age
                $today = new DateTime();
                $age = $today->diff($dob)->y;
            }
        }
        
        if (empty($gender)) {
            $errors[] = "Gender is required";
        }
        
        if (empty($phone)) {
            $errors[] = "Phone number is required";
        }
        
        // Validate email if provided
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address";
        }
        
        // Validate height and weight if provided
        if (!empty($height) && (!is_numeric($height) || $height <= 0)) {
            $errors[] = "Please enter a valid height";
        }
        
        if (!empty($weight) && (!is_numeric($weight) || $weight <= 0)) {
            $errors[] = "Please enter a valid weight";
        }
        
        // Check if phone already exists
        if (!empty($phone)) {
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetch()) {
                $errors[] = "Phone number already exists in the system.";
            }
        }
        
        // Check if email already exists (if provided)
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Email already exists in the system.";
            }
        }
        
        // If there are validation errors, redirect back with error message
        if (!empty($errors)) {
            $_SESSION['error'] = implode(", ", $errors);
            header("Location: add_new_patients.php");
            exit();
        }
       
        // ✅ GENERATE PATIENT ID USING DATABASE FUNCTION (FIXED)
        try {
            $stmt = $pdo->query("SELECT generate_patient_id() as new_patient_id");
            $result = $stmt->fetch();
            $patient_id = $result['new_patient_id'];
            
            // Debug log
            error_log("Generated Patient ID: " . $patient_id);
            
        } catch (PDOException $e) {
            // If function doesn't exist, generate ID manually
            error_log("Database function error: " . $e->getMessage());
            
            $stmt = $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(patient_id, 3) AS UNSIGNED)), 0) + 1 as next_id FROM patients WHERE patient_id REGEXP '^PT[0-9]+$'");
            $result = $stmt->fetch();
            $next_id = $result['next_id'];
            $patient_id = 'PT' . str_pad($next_id, 6, '0', STR_PAD_LEFT);
            
            error_log("Generated Patient ID manually: " . $patient_id);
        }

        // ✅ NOW INSERT THE PATIENT WITH THE GENERATED ID
        $stmt = $pdo->prepare("
            INSERT INTO patients (
                patient_id, first_name, middle_name, last_name, date_of_birth, age, gender, 
                marital_status, phone, preferred_contact, email, address, height, weight, blood_type, 
                emergency_contact_name, relationship, emergency_contact_phone, emergency_email,
                insurance_provider, insurance_id, 
                allergies, current_medications, chronic_conditions, past_surgeries,
                family_history, additional_family_notes,
                smoking_status, alcohol_consumption, exercise_frequency, dietary_habits,
                status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', NOW())
        ");
        
        $result = $stmt->execute([
            $patient_id,
            $first_name,
            $middle_name,
            $last_name,
            $date_of_birth,
            $age,
            $gender,
            $marital_status,
            $phone,
            $preferred_contact,
            $email,
            $address,
            $height,
            $weight,
            $blood_type,
            $emergency_contact_name,
            $relationship,
            $emergency_contact_phone,
            $emergency_email,
            $insurance_provider,
            $insurance_id,
            $allergies,
            $current_medications,
            $chronic_conditions,
            $past_surgeries,
            $family_history,
            $additional_family_notes,
            $smoking_status,
            $alcohol_consumption,
            $exercise_frequency,
            $dietary_habits
        ]);
        
        if ($result) {
            $new_patient_id = $pdo->lastInsertId();
            
            // Verify the insert actually happened
            $verify = $pdo->prepare("SELECT id, patient_id FROM patients WHERE id = ?");
            $verify->execute([$new_patient_id]);
            if ($patient_data = $verify->fetch()) {
                $_SESSION['success'] = "Patient '{$first_name} {$last_name}' has been registered successfully! Patient ID: {$patient_data['patient_id']}";
                error_log("New patient added successfully: {$first_name} {$last_name} (ID: {$new_patient_id}, Patient ID: {$patient_data['patient_id']})");
                
                // Redirect to patient list or patient details page
                header("Location: add_new_patients.php");
                exit();
            } else {
                $_SESSION['error'] = "Insert reported success but record not found. Please check database manually.";
                error_log("WARNING: Insert succeeded but verification failed for patient: {$first_name} {$last_name}");
            }
        } else {
            $_SESSION['error'] = "Failed to add patient. Database insert failed.";
            $error_info = $stmt->errorInfo();
            error_log("Failed to insert patient. Error info: " . print_r($error_info, true));
            $_SESSION['error'] .= " SQL Error: " . $error_info[2];
        }
        
        header("Location: add_new_patients.php");
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        error_log("Database error in process_new_patient.php: " . $e->getMessage());
        error_log("SQL State: " . $e->getCode());
        header("Location: add_new_patients.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "An error occurred: " . $e->getMessage();
        error_log("General error in process_new_patient.php: " . $e->getMessage());
        header("Location: add_new_patients.php");
        exit();
    }
} else {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: add_new_patients.php");
    exit();
}
?>