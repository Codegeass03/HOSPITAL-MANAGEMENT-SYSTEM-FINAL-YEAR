<?php
// assign_bed.php
session_start();
include "includes/db_connect.php";

$bed_id = $_GET['bed_id'] ?? null;

if (!$bed_id) {
    $_SESSION['error'] = "Invalid bed ID.";
    header("Location: wards.php");
    exit();
}

// Get bed data
$stmt = $pdo->prepare("SELECT b.*, w.ward_name, w.floor_number FROM beds b JOIN ward w ON b.ward_id = w.id WHERE b.id = ?");
$stmt->execute([$bed_id]);
$bed = $stmt->fetch();

if (!$bed) {
    $_SESSION['error'] = "Bed not found.";
    header("Location: wards.php");
    exit();
}

// Check if bed is available
if ($bed['bed_status'] != 'available') {
    $_SESSION['error'] = "This bed is not available for assignment.";
    header("Location: beds.php?ward_id=" . $bed['ward_id']);
    exit();
}

// Get available patients for assignment (only active patients not currently assigned to any bed)
$stmt = $pdo->prepare("
    SELECT p.id, p.patient_id, p.first_name, p.last_name, p.phone, p.gender, p.age 
    FROM patients p 
    WHERE p.status = 'Active' 
    AND p.id NOT IN (
        SELECT patient_id FROM bed_assignments WHERE status = 'active'
    )
    ORDER BY p.first_name, p.last_name
");
$stmt->execute();
$patients = $stmt->fetchAll();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $patient_id = $_POST['patient_id'];
    $admission_date = $_POST['admission_date'];
    $expected_discharge_date = $_POST['expected_discharge_date'] ?? null;
    $reason_for_admission = $_POST['reason_for_admission'];
    $assigned_by = 1; // Default to admin user for now

    try {
        // Validate patient exists and is active
        $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ? AND status = 'Active'");
        $stmt->execute([$patient_id]);
        $patient_exists = $stmt->fetch();

        if (!$patient_exists) {
            $_SESSION['error'] = "Selected patient is not active or doesn't exist.";
            header("Location: assign_bed.php?bed_id=" . $bed_id);
            exit();
        }

        // Check if patient is already assigned to another active bed
        $stmt = $pdo->prepare("SELECT id FROM bed_assignments WHERE patient_id = ? AND status = 'active'");
        $stmt->execute([$patient_id]);
        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "Patient is already assigned to another bed.";
            header("Location: assign_bed.php?bed_id=" . $bed_id);
            exit();
        }

        // Start transaction
        $pdo->beginTransaction();

        // Update bed status to occupied
        $stmt = $pdo->prepare("UPDATE beds SET bed_status = 'occupied' WHERE id = ?");
        $stmt->execute([$bed_id]);

        // Create bed assignment record
        $stmt = $pdo->prepare("INSERT INTO bed_assignments (bed_id, patient_id, admission_date, expected_discharge_date, reason_for_admission, assigned_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$bed_id, $patient_id, $admission_date, $expected_discharge_date, $reason_for_admission, $assigned_by]);

        // Update ward occupied beds count
        $stmt = $pdo->prepare("UPDATE ward SET occupied_beds = (SELECT COUNT(*) FROM beds WHERE ward_id = ? AND bed_status = 'occupied') WHERE id = ?");
        $stmt->execute([$bed['ward_id'], $bed['ward_id']]);

        $pdo->commit();

        $_SESSION['success'] = "Patient assigned to bed successfully!";
        header("Location: beds.php?ward_id=" . $bed['ward_id']);
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error assigning patient to bed: " . $e->getMessage();
        header("Location: assign_bed.php?bed_id=" . $bed_id);
        exit();
    }
}
include "includes/sidebar.php"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Patient to Bed - Hospital Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #334155;
        }

        .container {
            min-height: 100vh;
            display: flex;
        }

        .sidebar {
            width: 260px;
            background: #1e293b;
            color: white;
            height: 100vh;
            position: fixed;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-subtitle {
            color: #64748b;
            margin-top: 0.5rem;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color 0.2s;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color 0.2s;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        /* Form Styles */
        .form-container {
            background: white;
            border-radius: 0.75rem;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-width: 800px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-input, .form-select, .form-textarea {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            transition: border-color 0.2s;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }

        /* Bed Info Card */
        .bed-info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .bed-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-weight: 600;
            color: #1e293b;
        }

        /* Patient List Styles */
        .patient-option {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 0.5rem 0;
        }

        .patient-info {
            flex: 1;
        }

        .patient-name {
            font-weight: 500;
            color: #1e293b;
        }

        .patient-details {
            font-size: 0.875rem;
            color: #64748b;
        }

        /* Alert Styles */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>
    <div class="container">
       
        
        <div class="main-content">
            <?php
            $success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
            $error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
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
                        <i class="fas fa-user-plus"></i>
                        Assign Patient to Bed
                    </h1>
                    <p class="page-subtitle">
                        Assign a patient to <?php echo htmlspecialchars($bed['bed_number']); ?>
                    </p>
                </div>
                <a href="beds.php?ward_id=<?php echo $bed['ward_id']; ?>" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Beds
                </a>
            </div>

            <!-- Bed Information -->
            <div class="bed-info-card">
                <h3 style="margin-bottom: 1rem; color: #1e293b;">Bed Information</h3>
                <div class="bed-info-grid">
                    <div class="info-item">
                        <span class="info-label">Bed Number</span>
                        <span class="info-value"><?php echo htmlspecialchars($bed['bed_number']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Ward</span>
                        <span class="info-value"><?php echo htmlspecialchars($bed['ward_name']); ?> (Floor <?php echo $bed['floor_number']; ?>)</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Bed Type</span>
                        <span class="info-value"><?php echo htmlspecialchars($bed['bed_type']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Price per Day</span>
                        <span class="info-value">Ksh <?php echo number_format($bed['price_per_day'], 2); ?></span>
                    </div>
                </div>
            </div>

            <div class="form-container">
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label">Select Patient *</label>
                            <select name="patient_id" class="form-select" required>
                                <option value="">Select a patient</option>
                                <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>">
                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?> 
                                    (ID: <?php echo htmlspecialchars($patient['patient_id']); ?>) - 
                                    <?php echo htmlspecialchars($patient['gender']); ?>, 
                                    <?php echo $patient['age']; ?> years
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (count($patients) === 0): ?>
                            <p style="color: #ef4444; font-size: 0.875rem; margin-top: 0.5rem;">
                                <i class="fas fa-exclamation-triangle"></i>
                                No available patients found. All active patients are already assigned to beds or there are no active patients.
                            </p>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Admission Date *</label>
                            <input type="date" name="admission_date" class="form-input" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Expected Discharge Date</label>
                            <input type="date" name="expected_discharge_date" class="form-input">
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Reason for Admission *</label>
                            <textarea name="reason_for_admission" class="form-textarea" placeholder="Enter reason for admission (e.g., Surgery, Observation, Treatment, etc.)" required></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="beds.php?ward_id=<?php echo $bed['ward_id']; ?>" class="btn-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn-primary" <?php echo count($patients) === 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-user-plus"></i>
                            Assign Patient
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Set minimum date for discharge date to admission date
        document.querySelector('input[name="admission_date"]').addEventListener('change', function() {
            const dischargeInput = document.querySelector('input[name="expected_discharge_date"]');
            dischargeInput.min = this.value;
        });

        // Initialize min date for discharge
        document.querySelector('input[name="expected_discharge_date"]').min = 
            document.querySelector('input[name="admission_date"]').value;
    </script>
</body>
</html>