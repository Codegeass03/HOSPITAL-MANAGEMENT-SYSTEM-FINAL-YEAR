<?php
include "admin/connection.php";
include "includes/db_connect.php";
include "includes/sidebar.php";

$visit_id = $_GET['visit_id'] ?? 0;
$current_user_id = $_SESSION['user_id'];

// Get visit details
$visit_query = "
    SELECT pv.*, p.first_name, p.last_name, p.patient_id, p.date_of_birth,
           TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
    FROM patient_visits pv
    JOIN patients p ON pv.patient_id = p.id
    WHERE pv.id = ?
";
$stmt = mysqli_prepare($link, $visit_query);
mysqli_stmt_bind_param($stmt, "i", $visit_id);
mysqli_stmt_execute($stmt);
$visit = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$visit) {
    die("Visit not found");
}

if ($_POST) {
    // Save vitals and triage assessment
    $bp_systolic = $_POST['bp_systolic'];
    $bp_diastolic = $_POST['bp_diastolic'];
    $heart_rate = $_POST['heart_rate'];
    $temperature = $_POST['temperature'];
    $oxygen_saturation = $_POST['oxygen_saturation'];
    $respiratory_rate = $_POST['respiratory_rate'];
    $weight = $_POST['weight'];
    $height = $_POST['height'];
    $pain_level = $_POST['pain_level'];
    $triage_category = $_POST['triage_category'];
    $priority = $_POST['priority'];
    $nurse_notes = $_POST['nurse_notes'];

    // Insert vitals
    $vitals_query = "
        INSERT INTO patient_vitals (patient_id, blood_pressure_systolic, blood_pressure_diastolic, 
                                  heart_rate, temperature, oxygen_saturation, respiratory_rate,
                                  weight, height, recorded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $stmt = mysqli_prepare($link, $vitals_query);
    mysqli_stmt_bind_param($stmt, "iiiddddddi", $visit['patient_id'], $bp_systolic, $bp_diastolic, 
                          $heart_rate, $temperature, $oxygen_saturation, $respiratory_rate,
                          $weight, $height, $current_user_id);
    mysqli_stmt_execute($stmt);

    // Insert triage assessment
    $triage_query = "
        INSERT INTO triage_assessments (patient_id, nurse_id, temperature, blood_pressure_systolic,
                                      blood_pressure_diastolic, heart_rate, respiratory_rate,
                                      oxygen_saturation, pain_level, triage_category, priority,
                                      chief_complaint, nurse_notes, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')
    ";
    $stmt = mysqli_prepare($link, $triage_query);
    mysqli_stmt_bind_param($stmt, "iiddddddiisss", $visit['patient_id'], $current_user_id, $temperature,
                          $bp_systolic, $bp_diastolic, $heart_rate, $respiratory_rate,
                          $oxygen_saturation, $pain_level, $triage_category, $priority,
                          $visit['chief_complaint'], $nurse_notes);
    mysqli_stmt_execute($stmt);
    $triage_id = mysqli_insert_id($link);

    // Update visit status
    $update_visit = "UPDATE patient_visits SET visit_status = 'doctor_assigned' WHERE id = ?";
    $stmt = mysqli_prepare($link, $update_visit);
    mysqli_stmt_bind_param($stmt, "i", $visit_id);
    mysqli_stmt_execute($stmt);

    // Create medical record
    $record_query = "
        INSERT INTO medical_records (patient_id, triage_assessment_id, doctor_id, record_type,
                                   record_date, workflow_status, status, created_by)
        VALUES (?, ?, ?, 'consultation', CURDATE(), 'triage_completed', 'draft', ?)
    ";
    $stmt = mysqli_prepare($link, $record_query);
    mysqli_stmt_bind_param($stmt, "iiii", $visit['patient_id'], $triage_id, $current_user_id, $current_user_id);
    mysqli_stmt_execute($stmt);

    header("Location: ehr_dashboard.php?message=triage_completed");
    exit();
}
?>

<style>
.triage-container {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    padding: 2rem;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.patient-info-card {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    padding: 1.5rem;
    border-radius: 0.75rem;
    margin-bottom: 2rem;
}

.patient-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
}

.detail-label {
    font-size: 0.875rem;
    color: #64748b;
    font-weight: 500;
}

.detail-value {
    font-size: 1rem;
    color: #1e293b;
    font-weight: 600;
}

.vitals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.vital-input {
    display: flex;
    flex-direction: column;
}

.vital-input label {
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 0.25rem;
    color: #374151;
}

.vital-input input {
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
}

.vital-input input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
</style>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-stethoscope"></i>
                Patient Triage Assessment
            </h1>
            <p class="page-subtitle">
                Record patient vitals and initial assessment
            </p>
        </div>
    </div>

    <div class="triage-container">
        <!-- Patient Information -->
        <div class="patient-info-card">
            <h3 style="margin: 0 0 1rem 0; color: #1565c0;">Patient Information</h3>
            <div class="patient-details">
                <div class="detail-item">
                    <span class="detail-label">Patient Name</span>
                    <span class="detail-value"><?php echo htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Patient ID</span>
                    <span class="detail-value"><?php echo htmlspecialchars($visit['patient_id']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Age</span>
                    <span class="detail-value"><?php echo $visit['age']; ?> years</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Chief Complaint</span>
                    <span class="detail-value"><?php echo htmlspecialchars($visit['chief_complaint']); ?></span>
                </div>
            </div>
        </div>

        <!-- Triage Form -->
        <form method="POST">
            <h3 style="margin-bottom: 1.5rem; color: #374151;">Vital Signs</h3>
            
            <div class="vitals-grid">
                <div class="vital-input">
                    <label>Blood Pressure (Systolic)</label>
                    <input type="number" name="bp_systolic" placeholder="e.g., 120" required>
                </div>
                <div class="vital-input">
                    <label>Blood Pressure (Diastolic)</label>
                    <input type="number" name="bp_diastolic" placeholder="e.g., 80" required>
                </div>
                <div class="vital-input">
                    <label>Heart Rate (BPM)</label>
                    <input type="number" name="heart_rate" placeholder="e.g., 72" required>
                </div>
                <div class="vital-input">
                    <label>Temperature (°C)</label>
                    <input type="number" step="0.1" name="temperature" placeholder="e.g., 36.6" required>
                </div>
                <div class="vital-input">
                    <label>Oxygen Saturation (%)</label>
                    <input type="number" name="oxygen_saturation" placeholder="e.g., 98" required>
                </div>
                <div class="vital-input">
                    <label>Respiratory Rate</label>
                    <input type="number" name="respiratory_rate" placeholder="e.g., 16" required>
                </div>
                <div class="vital-input">
                    <label>Weight (kg)</label>
                    <input type="number" step="0.1" name="weight" placeholder="e.g., 70.5">
                </div>
                <div class="vital-input">
                    <label>Height (cm)</label>
                    <input type="number" step="0.1" name="height" placeholder="e.g., 175">
                </div>
                <div class="vital-input">
                    <label>Pain Level (1-10)</label>
                    <input type="number" min="1" max="10" name="pain_level" placeholder="1-10" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="triage_category">Triage Category</label>
                <select class="form-select" id="triage_category" name="triage_category" required>
                    <option value="">Select Category</option>
                    <option value="emergency">Emergency</option>
                    <option value="urgent">Urgent</option>
                    <option value="semi_urgent">Semi-Urgent</option>
                    <option value="non_urgent">Non-Urgent</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="priority">Priority Level</label>
                <select class="form-select" id="priority" name="priority" required>
                    <option value="">Select Priority</option>
                    <option value="critical">Critical</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="nurse_notes">Nurse Assessment Notes</label>
                <textarea class="form-textarea" id="nurse_notes" name="nurse_notes" placeholder="Enter assessment notes, observations, and any immediate concerns..."></textarea>
            </div>

            <button type="submit" class="submit-btn">
                <i class="fas fa-save"></i>
                Complete Triage & Send to Doctor
            </button>
        </form>
    </div>
</div>

<?php
include "includes/footer.php";
?>