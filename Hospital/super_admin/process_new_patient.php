<?php
session_start();

// Include database connection
include "includes/db_connect.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Begin transaction
        $pdo->beginTransaction();

        // Get and sanitize form inputs - Personal Information
        $first_name = trim($_POST['first_name']);
        $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
        $last_name = trim($_POST['last_name']);
        $date_of_birth = trim($_POST['date_of_birth']);
        $gender = trim($_POST['gender']);
        $marital_status = isset($_POST['marital_status']) ? trim($_POST['marital_status']) : '';
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phone = trim($_POST['phone']);
        $preferred_contact = isset($_POST['preferred_contact']) ? trim($_POST['preferred_contact']) : 'Phone';
        
        // Emergency Contact
        $emergency_name = isset($_POST['emergency_name']) ? trim($_POST['emergency_name']) : '';
        $relationship = isset($_POST['relationship']) ? trim($_POST['relationship']) : '';
        $emergency_phone = isset($_POST['emergency_phone']) ? trim($_POST['emergency_phone']) : '';
        $emergency_email = isset($_POST['emergency_email']) ? trim($_POST['emergency_email']) : '';
        
        // Medical Information
        $blood_type = isset($_POST['blood_type']) ? trim($_POST['blood_type']) : '';
        $height = isset($_POST['height']) ? floatval($_POST['height']) : null;
        $weight = isset($_POST['weight']) ? floatval($_POST['weight']) : null;
        $allergies = isset($_POST['allergies']) ? trim($_POST['allergies']) : '';
        $current_medications = isset($_POST['current_medications']) ? trim($_POST['current_medications']) : '';
        $chronic_conditions = isset($_POST['chronic_conditions']) ? trim($_POST['chronic_conditions']) : '';
        $past_surgeries = isset($_POST['past_surgeries']) ? trim($_POST['past_surgeries']) : '';
        
        // Family History
        $family_history = isset($_POST['family_history']) ? implode(', ', (array)$_POST['family_history']) : '';
        $additional_family_notes = isset($_POST['additional_family_notes']) ? trim($_POST['additional_family_notes']) : '';
        
        // Lifestyle Information
        $smoking_status = isset($_POST['smoking_status']) ? trim($_POST['smoking_status']) : '';
        $alcohol_consumption = isset($_POST['alcohol_consumption']) ? trim($_POST['alcohol_consumption']) : '';
        $exercise_frequency = isset($_POST['exercise_frequency']) ? trim($_POST['exercise_frequency']) : '';
        $dietary_habits = isset($_POST['dietary_habits']) ? trim($_POST['dietary_habits']) : '';
        
        // Insurance & Billing
        $insurance_provider = isset($_POST['insurance_provider']) ? trim($_POST['insurance_provider']) : '';
        $policy_number = isset($_POST['policy_number']) ? trim($_POST['policy_number']) : '';
        $group_number = isset($_POST['group_number']) ? trim($_POST['group_number']) : '';
        $policy_holder = isset($_POST['policy_holder']) ? trim($_POST['policy_holder']) : '';
        $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
        
        // Payment Details
        $mpesa_phone = isset($_POST['mpesa_phone']) ? trim($_POST['mpesa_phone']) : '';
        $mpesa_code = isset($_POST['mpesa_code']) ? trim($_POST['mpesa_code']) : '';
        $mpesa_amount = isset($_POST['mpesa_amount']) ? floatval($_POST['mpesa_amount']) : 0;
        $card_number = isset($_POST['card_number']) ? trim($_POST['card_number']) : '';
        $card_holder = isset($_POST['card_holder']) ? trim($_POST['card_holder']) : '';
        $card_expiry = isset($_POST['card_expiry']) ? trim($_POST['card_expiry']) : '';
        $card_cvv = isset($_POST['card_cvv']) ? trim($_POST['card_cvv']) : '';
        $card_amount = isset($_POST['card_amount']) ? floatval($_POST['card_amount']) : 0;
        $cash_amount = isset($_POST['cash_amount']) ? floatval($_POST['cash_amount']) : 0;
        $verification_status = isset($_POST['verification_status']) ? trim($_POST['verification_status']) : 'pending';
        $coverage_notes = isset($_POST['coverage_notes']) ? trim($_POST['coverage_notes']) : '';
        
        // Billing Address
        $billing_address = isset($_POST['billing_address']) ? trim($_POST['billing_address']) : $address;

        // Validation array
        $errors = [];
        
        // Validate required fields
        if (empty($first_name)) $errors[] = "First name is required";
        if (empty($last_name)) $errors[] = "Last name is required";
        if (empty($date_of_birth)) $errors[] = "Date of birth is required";
        if (empty($gender)) $errors[] = "Gender is required";
        if (empty($phone)) $errors[] = "Phone number is required";
        
        // Validate date format
        if (!empty($date_of_birth)) {
            $dob = DateTime::createFromFormat('Y-m-d', $date_of_birth);
            if (!$dob || $dob->format('Y-m-d') !== $date_of_birth) {
                $errors[] = "Please enter a valid date of birth (YYYY-MM-DD)";
            } else {
                // Calculate age
                $today = new DateTime();
                $age = $today->diff($dob)->y;
            }
        }
        
        // Validate email if provided
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address";
        }
        
        // Check if phone already exists
        if (!empty($phone)) {
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetch()) {
                $errors[] = "Phone number already exists in the system.";
            }
        }
        
        // Check if email already exists
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
        
        // Generate patient ID
        $stmt = $pdo->query("SELECT generate_patient_id() as new_patient_id");
        $patient_id_data = $stmt->fetch();
        $patient_id = $patient_id_data['new_patient_id'];
        
        // Debug: Check what columns actually exist in your patients table
        error_log("Attempting to insert patient with ID: " . $patient_id);
        
        // Use a simpler insert query that matches your actual database structure
        // Based on your original process_add_patient.php, let's use a safer approach
        $stmt = $pdo->prepare("
            INSERT INTO patients (
                patient_id, first_name, last_name, date_of_birth, age, gender,
                phone, email, address, height, weight, blood_type,
                emergency_contact_name, emergency_contact_phone,
                insurance_provider, insurance_id, medical_history, allergies, current_medications,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')
        ");
        
        $result = $stmt->execute([
            $patient_id,
            $first_name,
            $last_name,
            $date_of_birth,
            $age,
            $gender,
            $phone,
            $email,
            $address,
            $height,
            $weight,
            $blood_type,
            $emergency_name,
            $emergency_phone,
            $insurance_provider,
            $policy_number,
            $chronic_conditions, // Using chronic_conditions as medical_history
            $allergies,
            $current_medications
        ]);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Failed to insert patient record: " . $errorInfo[2]);
        }
        
        $new_patient_id = $pdo->lastInsertId();
        
        // Store additional information in patient_medical_details if the table exists
        try {
            $stmt = $pdo->prepare("
                INSERT INTO patient_medical_details (
                    patient_id, smoking_status, alcohol_consumption, exercise_frequency, 
                    dietary_habits, family_history, additional_family_notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $new_patient_id,
                $smoking_status,
                $alcohol_consumption,
                $exercise_frequency,
                $dietary_habits,
                $family_history,
                $additional_family_notes
            ]);
        } catch (Exception $e) {
            // If table doesn't exist, just log and continue
            error_log("Note: patient_medical_details table might not exist: " . $e->getMessage());
        }
        
        // Store payment information if provided
        if (!empty($payment_method)) {
            try {
                $amount = 0;
                switch ($payment_method) {
                    case 'mpesa': $amount = $mpesa_amount; break;
                    case 'card': $amount = $card_amount; break;
                    case 'cash': $amount = $cash_amount; break;
                    case 'insurance': $amount = 0; break;
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO patient_payments (
                        patient_id, payment_method, amount, mpesa_phone, mpesa_code,
                        card_number, card_holder, card_expiry, card_cvv,
                        insurance_provider, policy_number, group_number, policy_holder,
                        verification_status, coverage_notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $masked_card_number = !empty($card_number) ? substr($card_number, -4) : '';
                
                $stmt->execute([
                    $new_patient_id,
                    $payment_method,
                    $amount,
                    $mpesa_phone,
                    $mpesa_code,
                    $masked_card_number,
                    $card_holder,
                    $card_expiry,
                    $card_cvv,
                    $insurance_provider,
                    $policy_number,
                    $group_number,
                    $policy_holder,
                    $verification_status,
                    $coverage_notes
                ]);
            } catch (Exception $e) {
                // If table doesn't exist, just log and continue
                error_log("Note: patient_payments table might not exist: " . $e->getMessage());
            }
        }
        
        // Create initial medical record
        try {
            $stmt = $pdo->prepare("
                INSERT INTO medical_records (
                    patient_id, doctor_id, record_type, record_date, notes, created_by
                ) VALUES (?, ?, 'consultation', CURDATE(), ?, ?)
            ");
            
            $initial_notes = "New patient registration completed.\n";
            $initial_notes .= "Initial information recorded in system.";
            
            $stmt->execute([
                $new_patient_id,
                $_SESSION['user_id'],
                $initial_notes,
                $_SESSION['user_id']
            ]);
        } catch (Exception $e) {
            error_log("Note: Could not create medical record: " . $e->getMessage());
        }
        
        // Insert vitals if height/weight provided
        if (!empty($height) || !empty($weight)) {
            try {
                $bmi = null;
                if (!empty($height) && !empty($weight)) {
                    $height_m = $height / 100;
                    $bmi = $weight / ($height_m * $height_m);
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO patient_vitals (patient_id, height, weight, bmi, recorded_by)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $new_patient_id,
                    $height,
                    $weight,
                    $bmi,
                    $_SESSION['user_id']
                ]);
            } catch (Exception $e) {
                error_log("Note: Could not insert patient vitals: " . $e->getMessage());
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Success message
        $_SESSION['success'] = "Patient '{$first_name} {$last_name}' has been registered successfully! Patient ID: {$patient_id}";
        error_log("New patient registered: {$first_name} {$last_name} (ID: {$new_patient_id}, Patient ID: {$patient_id})");
        
        // Redirect to patient profile or management page
        header("Location: add_new_patients.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $_SESSION['error'] = "Error registering patient: " . $e->getMessage();
        error_log("Error in process_new_patient.php: " . $e->getMessage());
        
        // Redirect back to form with error
        header("Location: add_new_patients.php");
        exit();
    }
} else {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: add_new_patients.php");
    exit();
}
?>