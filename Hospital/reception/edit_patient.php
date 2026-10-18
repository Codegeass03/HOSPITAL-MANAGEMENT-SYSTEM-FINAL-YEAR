<?php
session_start();
// if(!isset($_SESSION["super_admin"])) {
//     header("Location: ../index.php");
//     exit();
// }

include "includes/db_connect.php";

// Check if patient ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Patient ID is required.";
    header("Location: add_new_patients.php");
    exit();
}

$patient_id = $_GET['id'];

// Fetch patient data
try {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        $_SESSION['error'] = "Patient not found.";
        header("Location: add_new_patients.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header("Location: patienadd_new_patients_management.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Get and sanitize form inputs
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $date_of_birth = trim($_POST['date_of_birth']);
        $age = trim($_POST['age']);
        $gender = trim($_POST['gender']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        $height = trim($_POST['height']);
        $weight = trim($_POST['weight']);
        $blood_type = isset($_POST['blood_type']) ? trim($_POST['blood_type']) : '';
        $emergency_contact_name = isset($_POST['emergency_contact_name']) ? trim($_POST['emergency_contact_name']) : '';
        $emergency_contact_phone = isset($_POST['emergency_contact_phone']) ? trim($_POST['emergency_contact_phone']) : '';
        $insurance_provider = isset($_POST['insurance_provider']) ? trim($_POST['insurance_provider']) : '';
        $insurance_id = isset($_POST['insurance_id']) ? trim($_POST['insurance_id']) : '';
        $medical_history = isset($_POST['medical_history']) ? trim($_POST['medical_history']) : '';
        $allergies = isset($_POST['allergies']) ? trim($_POST['allergies']) : '';
        $current_medications = isset($_POST['current_medications']) ? trim($_POST['current_medications']) : '';
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'Active';
        
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
            // Validate date format
            $dob = DateTime::createFromFormat('Y-m-d', $date_of_birth);
            if (!$dob || $dob->format('Y-m-d') !== $date_of_birth) {
                $errors[] = "Please enter a valid date of birth (YYYY-MM-DD)";
            }
        }
        
        if (empty($age) || !is_numeric($age) || $age < 0 || $age > 150) {
            $errors[] = "Please enter a valid age (0-150)";
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
        
        // Check if phone already exists (excluding current patient)
        if (!empty($phone)) {
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE phone = ? AND id != ?");
            $stmt->execute([$phone, $patient_id]);
            if ($stmt->fetch()) {
                $errors[] = "Phone number already exists in the system.";
            }
        }
        
        // Check if email already exists (excluding current patient)
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE email = ? AND id != ?");
            $stmt->execute([$email, $patient_id]);
            if ($stmt->fetch()) {
                $errors[] = "Email already exists in the system.";
            }
        }
        
        // If there are validation errors, redirect back with error message
        if (!empty($errors)) {
            $_SESSION['error'] = implode(", ", $errors);
            header("Location: edit_patient.php?id=" . $patient_id);
            exit();
        }
        
        // Update patient in the database
        $stmt = $pdo->prepare("
            UPDATE patients SET
                first_name = ?,
                last_name = ?,
                date_of_birth = ?,
                age = ?,
                gender = ?,
                phone = ?,
                email = ?,
                address = ?,
                height = ?,
                weight = ?,
                blood_type = ?,
                emergency_contact_name = ?,
                emergency_contact_phone = ?,
                insurance_provider = ?,
                insurance_id = ?,
                medical_history = ?,
                allergies = ?,
                current_medications = ?,
                status = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $first_name,
            $last_name,
            $date_of_birth,
            $age,
            $gender,
            $phone,
            $email,
            $address,
            $height ?: null,
            $weight ?: null,
            $blood_type ?: null,
            $emergency_contact_name,
            $emergency_contact_phone,
            $insurance_provider,
            $insurance_id,
            $medical_history,
            $allergies,
            $current_medications,
            $status,
            $patient_id
        ]);
        
        if ($result) {
            $_SESSION['success'] = "Patient '{$first_name} {$last_name}' has been updated successfully!";
            error_log("Patient updated successfully: {$first_name} {$last_name} (ID: {$patient_id})");
            header("Location: add_new_patients.php");
            exit();
        } else {
            $_SESSION['error'] = "Failed to update patient. Please try again.";
            error_log("Failed to update patient. Error info: " . print_r($stmt->errorInfo(), true));
            header("Location: edit_patient.php?id=" . $patient_id);
            exit();
        }
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        error_log("Database error in edit_patient.php: " . $e->getMessage());
        header("Location: edit_patient.php?id=" . $patient_id);
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "An error occurred: " . $e->getMessage();
        error_log("General error in edit_patient.php: " . $e->getMessage());
        header("Location: edit_patient.php?id=" . $patient_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Patient - AuraCare HMS</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      margin: 0;
      display: flex;
      font-family: Arial, sans-serif;
      background-color: #f8fafc;
    }

    .main-wrapper {
      margin-left: 250px;
      flex: 1;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    .main-content {
      padding: 2rem;
      flex: 1;
    }

    .page-header {
      display: flex;
      justify-content: space-between;
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

    .back-btn {
      background: #64748b;
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
    }

    .back-btn:hover {
      background: #475569;
    }

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

    .form-container {
      background: white;
      border-radius: 0.75rem;
      padding: 2rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      border: 1px solid #f1f5f9;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
      margin-bottom: 2rem;
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

    .form-label.required::after {
      content: " *";
      color: #dc2626;
    }

    .form-input, .form-select, .form-textarea {
      padding: 0.75rem;
      border: 1px solid #d1d5db;
      border-radius: 0.5rem;
      font-size: 0.875rem;
      transition: border-color 0.2s;
      background: white;
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

    .form-footer {
      display: flex;
      gap: 1rem;
      justify-content: flex-end;
      padding-top: 2rem;
      border-top: 1px solid #f1f5f9;
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
      cursor: pointer;
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

    .patient-info-header {
      background: #f8fafc;
      padding: 1.5rem;
      border-radius: 0.5rem;
      margin-bottom: 2rem;
      border-left: 4px solid #0ea5e9;
    }

    .patient-id {
      font-size: 1.25rem;
      font-weight: 600;
      color: #0ea5e9;
      margin-bottom: 0.5rem;
    }

    .patient-name {
      font-size: 1.5rem;
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 0.25rem;
    }

    .patient-details {
      color: #64748b;
      font-size: 0.875rem;
    }
  </style>
</head>
<body>
<?php include "includes/sidebar.php"; ?>

<div class="main-wrapper">
  <div class="main-content">
    <?php
    // Display success/error messages
    $success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
    $error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;

    // Clear messages after displaying
    unset($_SESSION['success'], $_SESSION['error']);
    ?>

    <?php if ($success_message): ?>
    <div class="alert alert-success">
      <i class="fas fa-check-circle"></i>
      <?php echo htmlspecialchars($success_message); ?>
    </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
    <div class="alert alert-error">
      <i class="fas fa-exclamation-triangle"></i>
      <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <div class="page-header">
      <div>
        <h1 class="page-title">
          <i class="fas fa-edit"></i>
          Edit Patient
        </h1>
        <p class="page-subtitle">Update patient information and medical details</p>
      </div>
      <a href="add_new_patients.php" class="back-btn">
        <i class="fas fa-arrow-left"></i>
        Back to Patients
      </a>
    </div>

    <div class="patient-info-header">
      <div class="patient-id">Patient ID: <?php echo htmlspecialchars($patient['patient_id']); ?></div>
      <div class="patient-name"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></div>
      <div class="patient-details">
        Created: <?php echo date('M j, Y', strtotime($patient['created_at'])); ?> 
        | Last Updated: <?php echo date('M j, Y', strtotime($patient['updated_at'])); ?>
      </div>
    </div>

    <div class="form-container">
      <form method="POST" id="editPatientForm">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label required">First Name</label>
            <input type="text" name="first_name" class="form-input" 
                   value="<?php echo htmlspecialchars($patient['first_name']); ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label required">Last Name</label>
            <input type="text" name="last_name" class="form-input" 
                   value="<?php echo htmlspecialchars($patient['last_name']); ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label required">Date of Birth</label>
            <input type="date" name="date_of_birth" class="form-input" 
                   value="<?php echo htmlspecialchars($patient['date_of_birth']); ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label required">Age</label>
            <input type="number" name="age" class="form-input" 
                   value="<?php echo htmlspecialchars($patient['age']); ?>" min="0" max="150" required>
          </div>
          <div class="form-group">
            <label class="form-label required">Gender</label>
            <select name="gender" class="form-select" required>
              <option value="">Select gender</option>
              <option value="Male" <?php echo $patient['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
              <option value="Female" <?php echo $patient['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
              <option value="Other" <?php echo $patient['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label required">Phone No.</label>
            <input type="tel" name="phone" class="form-input" 
                   value="<?php echo htmlspecialchars($patient['phone']); ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-input" 
                   value="<?php echo htmlspecialchars($patient['email'] ?? ''); ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Height (cm)</label>
            <input type="number" name="height" class="form-input" step="0.1" min="0"
                   value="<?php echo htmlspecialchars($patient['height'] ?? ''); ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Weight (kg)</label>
            <input type="number" name="weight" class="form-input" step="0.1" min="0"
                   value="<?php echo htmlspecialchars($patient['weight'] ?? ''); ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Blood Type</label>
            <select name="blood_type" class="form-select">
              <option value="">Select blood type</option>
              <?php
              $blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
              foreach ($blood_types as $type): ?>
                <option value="<?php echo $type; ?>" 
                  <?php echo ($patient['blood_type'] ?? '') == $type ? 'selected' : ''; ?>>
                  <?php echo $type; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="Active" <?php echo ($patient['status'] ?? 'Active') == 'Active' ? 'selected' : ''; ?>>Active</option>
              <option value="Inactive" <?php echo ($patient['status'] ?? 'Active') == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
              <option value="Deceased" <?php echo ($patient['status'] ?? 'Active') == 'Deceased' ? 'selected' : ''; ?>>Deceased</option>
            </select>
          </div>
          <div class="form-group full-width">
            <label class="form-label">Address</label>
            <input type="text" name="address" class="form-input" 
                   value="<?php echo htmlspecialchars($patient['address'] ?? ''); ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Emergency Contact Name</label>
            <input type="text" name="emergency_contact_name" class="form-input"
                   value="<?php echo htmlspecialchars($patient['emergency_contact_name'] ?? ''); ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Emergency Contact Phone</label>
            <input type="tel" name="emergency_contact_phone" class="form-input"
                   value="<?php echo htmlspecialchars($patient['emergency_contact_phone'] ?? ''); ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Insurance Provider</label>
            <input type="text" name="insurance_provider" class="form-input"
                   value="<?php echo htmlspecialchars($patient['insurance_provider'] ?? ''); ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Insurance ID</label>
            <input type="text" name="insurance_id" class="form-input"
                   value="<?php echo htmlspecialchars($patient['insurance_id'] ?? ''); ?>">
          </div>
          <div class="form-group full-width">
            <label class="form-label">Medical History</label>
            <textarea name="medical_history" class="form-textarea" rows="3"><?php echo htmlspecialchars($patient['medical_history'] ?? ''); ?></textarea>
          </div>
          <div class="form-group full-width">
            <label class="form-label">Allergies</label>
            <textarea name="allergies" class="form-textarea" rows="2"><?php echo htmlspecialchars($patient['allergies'] ?? ''); ?></textarea>
          </div>
          <div class="form-group full-width">
            <label class="form-label">Current Medications</label>
            <textarea name="current_medications" class="form-textarea" rows="2"><?php echo htmlspecialchars($patient['current_medications'] ?? ''); ?></textarea>
          </div>
        </div>
        
        <div class="form-footer">
          <a href="add_new_user.php" class="btn-secondary">Cancel</a>
          <button type="submit" class="btn-primary">
            <i class="fas fa-save"></i>
            Update Patient
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Auto-calculate age when date of birth changes
document.querySelector('input[name="date_of_birth"]').addEventListener('change', function() {
    const dob = new Date(this.value);
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const monthDiff = today.getMonth() - dob.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
        age--;
    }
    
    if (age >= 0 && age <= 150) {
        document.querySelector('input[name="age"]').value = age;
    }
});

// Form validation
document.getElementById('editPatientForm').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('[required]');
    let valid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            valid = false;
            field.style.borderColor = '#dc2626';
        } else {
            field.style.borderColor = '#d1d5db';
        }
    });
    
    if (!valid) {
        e.preventDefault();
        alert('Please fill in all required fields.');
    }
});
</script>

</body>
</html>
<?php include "includes/footer.php"; ?>