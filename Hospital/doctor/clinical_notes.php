<?php
session_start();
include "includes/db_connect.php";


if (!isset($pdo)) {
    die("Database connection failed.");
}

// Get messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success'], $_SESSION['error']);

$patient = null;
$recent_vitals = null;
$current_appointment = null;

// Check if patient_id is provided via GET
if (isset($_GET['patient_id'])) {
    $patient_id = $_GET['patient_id'];
    
    // Fetch patient details
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch();
    
    if ($patient) {
        // Fetch recent vitals from patient_vitals table
        $vitals_stmt = $pdo->prepare("
            SELECT * FROM patient_vitals 
            WHERE patient_id = ? 
            ORDER BY recorded_at DESC 
            LIMIT 1
        ");
        $vitals_stmt->execute([$patient_id]);
        $recent_vitals = $vitals_stmt->fetch();
        
        // Fetch current appointment (today's scheduled/confirmed appointment)
        $apt_stmt = $pdo->prepare("
            SELECT id, appointment_number, status 
            FROM appointments 
            WHERE patient_id = ? 
            AND appointment_date = CURDATE()
            AND status IN ('scheduled', 'confirmed')
            ORDER BY appointment_time ASC
            LIMIT 1
        ");
        $apt_stmt->execute([$patient_id]);
        $current_appointment = $apt_stmt->fetch();
    }
}

// Handle Complete Visit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['complete_visit'])) {
    $patient_id = $_POST['patient_id'];
    $appointment_id = $_POST['appointment_id'];
    
    try {
        // Update appointment status to completed
        $update_stmt = $pdo->prepare("
            UPDATE appointments 
            SET status = 'completed', 
                updated_at = CURRENT_TIMESTAMP 
            WHERE id = ? AND patient_id = ?
        ");
        $update_stmt->execute([$appointment_id, $patient_id]);
        
        if ($update_stmt->rowCount() > 0) {
            $_SESSION['success'] = "Visit completed successfully! Appointment status updated.";
        } else {
            $_SESSION['error'] = "Failed to update appointment status.";
        }
        
        header("Location: clinical_notes.php?patient_id=" . $patient_id);
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error completing visit: " . $e->getMessage();
        error_log("Complete visit error: " . $e->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_notes'])) {
    $patient_id = $_POST['patient_id'];
    $doctor_id = $_SESSION['user_id'] ?? 55;
    $subjective = $_POST['symptoms'] ?? '';
    $objective = $_POST['objective'] ?? '';
    $assessment = $_POST['diagnosis'] ?? '';
    $plan = $_POST['treatment_plan'] ?? '';
    $prescription = $_POST['prescription'] ?? '';
    $follow_up_date = $_POST['follow_up_date'] ?? '';
    $provider_name = $_SESSION['username'] ?? 'Dr. Unknown';
    
    try {
        // Ensure the clinical_notes table exists with correct structure
        $check_table = $pdo->query("SHOW TABLES LIKE 'clinical_notes'");
        if ($check_table->rowCount() == 0) {
            // Create table with the exact structure from your SQL dump
            $create_table_sql = "
                CREATE TABLE clinical_notes (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    patient_id VARCHAR(20) DEFAULT NULL,
                    doctor_id INT(10) UNSIGNED NOT NULL,
                    subjective TEXT DEFAULT NULL,
                    objective TEXT DEFAULT NULL,
                    assessment TEXT DEFAULT NULL,
                    plan TEXT DEFAULT NULL,
                    provider_name VARCHAR(100) DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                    PRIMARY KEY (id),
                    KEY patient_id (patient_id),
                    KEY doctor_id (doctor_id),
                    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
            ";
            $pdo->exec($create_table_sql);
        }
        
        // Insert into clinical_notes table using the correct column names
        $stmt = $pdo->prepare("
            INSERT INTO clinical_notes 
            (patient_id, doctor_id, subjective, objective, assessment, plan, provider_name) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $patient_id, 
            $doctor_id, 
            $subjective, 
            $objective, 
            $assessment, 
            $plan, 
            $provider_name
        ]);
        
        $_SESSION['success'] = "Clinical notes saved successfully!";
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error saving clinical notes: " . $e->getMessage();
        error_log("Clinical notes error: " . $e->getMessage());
    }
}

// Fetch patient's previous clinical notes if patient is selected
$previous_notes = [];
if ($patient) {
    try {
        $notes_stmt = $pdo->prepare("
            SELECT cn.*, u.first_name, u.last_name 
            FROM clinical_notes cn 
            LEFT JOIN users u ON cn.doctor_id = u.id 
            WHERE cn.patient_id = ? 
            ORDER BY cn.created_at DESC 
            LIMIT 10
        ");
        $notes_stmt->execute([$patient['id']]);
        $previous_notes = $notes_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching previous notes: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinical Notes - AuraCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .page-header {
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
        
        .patient-info-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        
        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 500;
            color: #374151;
        }
        
        .info-value {
            color: #6b7280;
        }
        
        .clinical-notes-form {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .form-section {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-input, .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-family: inherit;
        }
        
        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-footer {
            padding: 1.5rem;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
            display: flex;
            justify-content: space-between;
            gap: 1rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        
        .btn-secondary {
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .btn-complete {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .alert-info {
            background: #e0e7ff;
            color: #4338ca;
            border: 1px solid #c7d2fe;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }
        
        .vitals-alert {
            background: #f0f9ff;
            border: 1px solid #e0f2fe;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .vitals-alert.warning {
            background: #fef3c7;
            border-color: #fde68a;
        }
        
        .previous-notes {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .note-item {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .note-item:last-child {
            border-bottom: none;
        }
        
        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .note-date {
            font-weight: 600;
            color: #374151;
        }
        
        .note-doctor {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .note-content {
            color: #4b5563;
            line-height: 1.6;
        }
        
        .note-section {
            margin-bottom: 1rem;
        }
        
        .note-section:last-child {
            margin-bottom: 0;
        }
        
        .note-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }
        
        .quick-actions {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            padding: 1.5rem;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .action-card {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1.5rem;
            text-align: center;
        }
        
        .action-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #3b82f6;
        }
        
        .action-title {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .action-description {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        
        .history-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #3b82f6;
            text-decoration: none;
            font-size: 0.875rem;
            margin-top: 1rem;
        }
        
        .history-link:hover {
            text-decoration: underline;
        }

        .appointment-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .appointment-status-badge.active {
            background: #dbeafe;
            color: #1e40af;
        }

        .appointment-status-badge.completed {
            background: #d1fae5;
            color: #065f46;
        }
    </style>
</head>
<body>
    <div class="main-content">
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
            <h1 class="page-title">
                <i class="fas fa-file-medical"></i>
                Clinical Notes
            </h1>
            <p class="page-subtitle">Document patient encounters and medical assessments</p>
        </div>
        
        <?php if ($patient): ?>
        
        <?php if ($current_appointment): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            Active Appointment: <?php echo htmlspecialchars($current_appointment['appointment_number']); ?> 
            (Status: <?php echo ucfirst($current_appointment['status']); ?>)
        </div>
        <?php endif; ?>
        
        <div class="patient-info-card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-user-injured"></i>
                    Patient Information
                </h2>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Patient Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Patient ID</span>
                        <span class="info-value"><?php echo htmlspecialchars($patient['patient_id']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Date of Birth</span>
                        <span class="info-value"><?php echo date('M j, Y', strtotime($patient['date_of_birth'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Gender</span>
                        <span class="info-value"><?php echo ucfirst($patient['gender']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?php echo htmlspecialchars($patient['phone'] ?? 'Not recorded'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Blood Group</span>
                        <span class="info-value"><?php echo $patient['blood_type'] ?: 'Not recorded'; ?></span>
                    </div>
                </div>
                
                <?php if ($recent_vitals): ?>
                <div class="vitals-alert">
                    <strong><i class="fas fa-heartbeat"></i> Recent Vitals (<?php echo date('M j, Y', strtotime($recent_vitals['recorded_at'])); ?>):</strong>
                    BP: <?php echo ($recent_vitals['blood_pressure_systolic'] && $recent_vitals['blood_pressure_diastolic']) ? 
                        htmlspecialchars($recent_vitals['blood_pressure_systolic'] . '/' . $recent_vitals['blood_pressure_diastolic']) : '--'; ?>, 
                    HR: <?php echo htmlspecialchars($recent_vitals['heart_rate'] ?: '--'); ?> bpm, 
                    Temp: <?php echo $recent_vitals['temperature'] ? 
                        htmlspecialchars(round($recent_vitals['temperature'], 1) . '°C') : '--'; ?>,
                    SpO2: <?php echo htmlspecialchars($recent_vitals['oxygen_saturation'] ?: '--'); ?>%
                </div>
                <?php else: ?>
                <div class="vitals-alert warning">
                    <i class="fas fa-info-circle"></i> No recent vitals recorded for this patient.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions Section -->
        <div class="quick-actions">
            <h3 class="section-title">
                <i class="fas fa-bolt"></i>
                Quick Actions
            </h3>
            <div class="actions-grid">
                <div class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-flask"></i>
                    </div>
                    <div class="action-title">Order Laboratory Tests</div>
                    <div class="action-description">Request blood tests, urinalysis, imaging, or other diagnostic tests</div>
                    <a href="lab_orders.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-warning">
                        <i class="fas fa-flask"></i>
                        Order Labs
                    </a>
                </div>
                
                <div class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-pills"></i>
                    </div>
                    <div class="action-title">Prescribe Medication</div>
                    <div class="action-description">Create and manage medication prescriptions for this patient</div>
                    <a href="prescriptions.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-success">
                        <i class="fas fa-pills"></i>
                        Prescribe Meds
                    </a>
                </div>

                <div class="action-card">
                    <div class="action-icon" style="color:#dc2626;">
                        <i class="fas fa-procedures"></i>
                    </div>
                    <div class="action-title">Admit Patient</div>
                    <div class="action-description">
                        Admit this patient to a ward for inpatient care and monitoring
                    </div>
                    <a href="wards.php?patient_id=<?php echo $patient['id']; ?>" 
                       class="btn btn-danger"
                       onclick="return confirm('Are you sure you want to admit this patient?');">
                        <i class="fas fa-hospital-user"></i>
                        Admit Patient
                    </a>
                </div>
            </div>
        </div>
        
        <form method="POST" class="clinical-notes-form">
            <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">
            
            <div class="form-section">
                <h3 class="section-title">
                    <i class="fas fa-stethoscope"></i>
                    Clinical Assessment (SOAP Format)
                </h3>
                
                <div class="form-group">
                    <label class="form-label">Patient's Symptoms & History</label>
                    <textarea name="symptoms" class="form-textarea" 
                              placeholder="Patient's complaints, symptoms, how long they've experienced the symptoms, severity..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Vital Signs Findings</label>
                    <textarea name="objective" class="form-textarea" 
                              placeholder="Physical examination findings, vital signs, test results..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Final Diagnosis</label>
                    <textarea name="diagnosis" class="form-textarea" 
                              placeholder="Diagnosis, differential diagnosis, assessment findings..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Treatment Plan</label>
                    <textarea name="treatment_plan" class="form-textarea" 
                              placeholder="Treatment plan, procedures, medications, recommendations..."></textarea>
                </div>
            </div>
            
            <div class="form-footer">
                <a href="patient_search.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Search
                </a>
                <div class="action-buttons">
                    <a href="clinical_notes_history.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-history"></i>
                        View History
                    </a>
                    <button type="submit" name="save_notes" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save Clinical Notes
                    </button>
                    
                    <?php if ($current_appointment): ?>
                    <button type="button" class="btn btn-complete" onclick="confirmCompleteVisit()">
                        <i class="fas fa-check-double"></i>
                        Complete Visit
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
        
        <?php if (count($previous_notes) > 0): ?>
        <div class="previous-notes">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-history"></i>
                    Recent Clinical Notes
                </h2>
                <a href="clinical_notes_history.php?patient_id=<?php echo $patient['id']; ?>" class="history-link">
                    <i class="fas fa-external-link-alt"></i>
                    View Full History
                </a>
            </div>
            <?php foreach ($previous_notes as $note): ?>
            <div class="note-item">
                <div class="note-header">
                    <div class="note-date">
                        <?php echo date('M j, Y g:i A', strtotime($note['created_at'])); ?>
                    </div>
                    <div class="note-doctor">
                        By <?php echo htmlspecialchars($note['provider_name'] ?: $note['first_name'] . ' ' . $note['last_name']); ?>
                    </div>
                </div>
                
                <div class="note-content">
                    <?php if ($note['subjective']): ?>
                    <div class="note-section">
                        <div class="note-label">Patient's Symptoms & History:</div>
                        <div><?php echo nl2br(htmlspecialchars($note['subjective'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($note['objective']): ?>
                    <div class="note-section">
                        <div class="note-label">Objective:</div>
                        <div><?php echo nl2br(htmlspecialchars($note['objective'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($note['assessment']): ?>
                    <div class="note-section">
                        <div class="note-label">Assessment:</div>
                        <div><?php echo nl2br(htmlspecialchars($note['assessment'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($note['plan']): ?>
                    <div class="note-section">
                        <div class="note-label">Plan:</div>
                        <div><?php echo nl2br(htmlspecialchars($note['plan'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-user-plus"></i>
            <h3>No Patient Selected</h3>
            <p>Please search for a patient first to start documenting clinical notes.</p>
            <a href="patient_search.php" class="btn btn-primary" style="margin-top: 1rem;">
                <i class="fas fa-search"></i>
                Search for Patient
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Hidden form for completing visit -->
    <?php if ($current_appointment): ?>
    <form method="POST" id="completeVisitForm" style="display: none;">
        <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">
        <input type="hidden" name="appointment_id" value="<?php echo $current_appointment['id']; ?>">
        <input type="hidden" name="complete_visit" value="1">
    </form>
    <?php endif; ?>
    
    <script>
        function confirmCompleteVisit() {
            if (confirm('Are you sure you want to mark this visit as completed? This will update the appointment status to "Completed".')) {
                document.getElementById('completeVisitForm').submit();
            }
        }
    </script>
</body>
</html>
<?php include "includes/footer.php"; ?>

<aside class="sidebar">
  <div class="sidebar-header">
    <div class="logo">
      <div class="logo-icon">
        <i class="fas fa-heartbeat"></i>
      </div>
      <div class="logo-text">
        <h2>AuraCare HMS</h2>
      </div>
    </div>
    <button class="sidebar-toggle" id="sidebarToggle">
      <i class="fas fa-bars"></i>
    </button>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section">
      <div class="section-label">Main Menu</div>
      <ul class="sidebar-menu">
        <li>
          <a href="dashboard.php" class="menu-item">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
            <!-- <span class="menu-badge">New</span> -->
          </a>
        </li>
      </ul>
    </div>

    <div class="nav-section">
      <div class="section-label">Patient Care</div>
      <ul class="sidebar-menu"> 
        <li>
          <a href="add_new_patients.php" class="menu-item">
            <i class="fas fa-user-injured"></i>
            <span>Patient Management</span>
          </a>
        </li>
        
        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-calendar-check"></i>
            <span>Appointments</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="appointment_scheduling.php"><i class="fas fa-plus-circle"></i>Schedule Appointment</a></li>
            <li><a href="calendar_view.php"><i class="fas fa-calendar-week"></i>Calendar View</a></li>
            <li><a href="appointment_requests.php"><i class="fas fa-clock"></i>Requests & Approvals</a></li>
          </ul>
        </li>

        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-file-medical"></i>
            <span>EHR System</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="ehr_dashboard.php"><i class="fas fa-tachometer-alt"></i>EHR Dashboard</a></li>
            <li><a href="patient_search.php"><i class="fas fa-search"></i>Patient Search</a></li>
            <li><a href="clinical_notes.php"><i class="fas fa-file-medical-alt"></i>Clinical Notes</a></li>
            <li><a href="lab_orders.php"><i class="fas fa-vial"></i>Lab Orders</a></li>
            <li><a href="prescriptions.php"><i class="fas fa-prescription"></i>Medication Orders</a></li>
            <li><a href="medical_history.php"><i class="fas fa-history"></i>Medical History</a></li>
            <li><a href="vitals_history.php"><i class="fas fa-heartbeat"></i>Vitals History</a></li>
          </ul>
        </li>
      </ul>
    </div>

    <div class="nav-section">
      <div class="section-label">Clinical Modules</div>
      <ul class="sidebar-menu">
        <li>
          <a href="pharmacy.php" class="menu-item">
            <i class="fas fa-capsules"></i>
            <span>Pharmacy</span>
          </a>
        </li>
        
       

     
        <li>
          <a href="wards.php" class="menu-item">
            <i class="fas fa-bed"></i>
            <span>Ward & Bed Management</span>
          </a>
        </li>
      </ul>
    </div>

  
       
          <ul class="submenu">
            <li><a href="purchase_report.php"><i class="fas fa-shopping-cart"></i>PURCHASE REPORT</a></li>
            <li><a href="bills.php"><i class="fas fa-receipt"></i>SALES REPORT</a></li>
            <li><a href="inventory.php"><i class="fas fa-box-open"></i>STOCK REPORT</a></li>
            <li><a href="supplier_report.php"><i class="fas fa-truck-loading"></i>SUPPLIERS REPORT</a></li>
            <li><a href="return_product_list.php"><i class="fas fa-undo-alt"></i>RETURNS PRODUCTS REPORT</a></li>
            <li><a href="expiry_report.php"><i class="fas fa-calendar-times"></i>EXPIRED PRODUCTS REPORT</a></li>
          </ul>
        </li>
      </ul>
    </div>

    <div class="nav-section">
      <div class="section-label">System</div>
      <ul class="sidebar-menu">
        <li>
          <a href="settings.php" class="menu-item">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
          </a>
        </li>
        <li>
          <a href="logout.php" class="menu-item logout-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
          </a>
        </li>
      </ul>
    </div>
  </nav>

  <div class="sidebar-footer">
    <div class="user-profile">
      <div class="user-avatar">
        <i class="fas fa-user-shield"></i>
      </div>
      <div class="user-info">
        <span class="user-name">Doctors Dashboard</span>
        <span class="user-role">Doctor</span>
      </div>
    </div>
  </div>
</aside>

<style>
/* Sidebar Base Styles */
.sidebar {
  position: fixed;
  left: 0;
  top: 0;
  width: 290px;
  height: 100vh;
  background: linear-gradient(180deg, #0c4da2 0%, #07377c 100%);
  color: white;
  overflow-y: auto;
  overflow-x: hidden;
  box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
  z-index: 800;
  transition: all 0.3s ease;
  display: flex;
  flex-direction: column;
}

/* Compact Sidebar */
.sidebar.compact {
  width: 90px;
}

.sidebar.compact .logo-text,
.sidebar.compact .section-label,
.sidebar.compact .menu-item span,
.sidebar.compact .user-info,
.sidebar.compact .menu-badge,
.sidebar.compact .dropdown-arrow {
  display: none !important;
}

.sidebar.compact .submenu {
  display: none !important;
}

/* Custom Scrollbar */
.sidebar::-webkit-scrollbar {
  width: 6px;
}

.sidebar::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 3px;
  transition: background 0.3s ease;
}

.sidebar::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}

/* Sidebar Header */
.sidebar-header {
  padding: 1.5rem 1.5rem 1rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.05);
  position: sticky;
  top: 0;
  z-index: 10;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.logo {
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

.logo-icon {
  width: 40px;
  height: 45px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  flex-shrink: 0;
}

.logo-text h2 {
  margin: 0;
  font-size: 1.6rem;
  font-weight: 900;
  color: white;
  line-height: 1.5;
  letter-spacing: 0.8px;
}

.logo-text small {
  display: block;
  font-size: 1.2rem;
  color: rgba(255, 255, 255, 0.9);
  font-weight: 500;
  margin-top: 0.8rem;
  letter-spacing: 0.3px;
}

.sidebar-toggle {
  background: rgba(255, 255, 255, 0.1);
  border: none;
  color: white;
  width: 35px;
  height: 35px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 1rem;
}

.sidebar-toggle:hover {
  background: rgba(255, 255, 255, 0.2);
  transform: rotate(90deg);
}

/* Navigation */
.sidebar-nav {
  flex: 1;
  padding: 1rem 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.nav-section {
  margin-bottom: 1rem;
}

.section-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.6);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 0.5rem 1.5rem 0.25rem;
  margin-bottom: 0.25rem;
}

/* Menu List */
.sidebar-menu {
  list-style: none;
  padding: 0;
  margin: 0;
}

.sidebar-menu li {
  margin: 0;
  position: relative;
}

/* Menu Item Styles */
.menu-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 1.5rem;
  color: rgba(255, 255, 255, 0.9);
  text-decoration: none;
  transition: all 0.3s ease;
  font-size: 0.9rem;
  font-weight: 500;
  border-left: 3px solid transparent;
  position: relative;
}

.menu-item:hover {
  background: rgba(255, 255, 255, 0.12);
  border-left-color: #4dabf7;
  color: white;
  padding-left: 1.75rem;
}

.menu-item.active {
  background: rgba(255, 255, 255, 0.15);
  border-left-color: #51cf66;
  color: white;
  font-weight: 600;
}

.menu-item i:first-child {
  font-size: 1.1rem;
  width: 20px;
  text-align: center;
  flex-shrink: 0;
}

.menu-item span {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.menu-badge {
  background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
  color: white;
  padding: 0.2rem 0.6rem;
  border-radius: 12px;
  font-size: 0.7rem;
  font-weight: 600;
  min-width: 20px;
  text-align: center;
}

/* Submenu Styles */
.has-submenu > .menu-item {
  position: relative;
}

.dropdown-arrow {
  transition: transform 0.3s ease;
  font-size: 0.8rem;
  color: rgba(255, 255, 255, 0.7);
}

.has-submenu.active .dropdown-arrow {
  transform: rotate(180deg);
  color: white;
}

.submenu {
  list-style: none;
  padding: 0;
  margin: 0;
  background: rgba(0, 0, 0, 0.2);
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.has-submenu.active .submenu {
  max-height: 500px;
}

.submenu li a {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1.5rem 0.75rem 3.5rem;
  color: rgba(255, 255, 255, 0.8);
  text-decoration: none;
  transition: all 0.3s ease;
  font-size: 0.85rem;
  border-left: 3px solid transparent;
}

.submenu li a:hover {
  background: rgba(255, 255, 255, 0.08);
  border-left-color: #ffd43b;
  color: white;
  padding-left: 4rem;
}

.submenu li a i {
  font-size: 0.9rem;
  width: 16px;
  text-align: center;
}

/* Logout Item */
.logout-item {
  color: #ff6b6b !important;
}

.logout-item:hover {
  background: rgba(255, 107, 107, 0.15) !important;
  border-left-color: #ff6b6b !important;
  color: #ff6b6b !important;
}

/* Sidebar Footer */
.sidebar-footer {
  padding: 1.5rem;
  border-top: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.05);
  margin-top: auto;
}

.user-profile {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.user-avatar {
  width: 45px;
  height: 45px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.user-info {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.user-name {
  font-size: 0.9rem;
  font-weight: 600;
  color: white;
  margin-bottom: 0.1rem;
}

.user-role {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.7);
}

/* Main Content Adjustment */
.main-content {
  margin-left: 300px;
  min-height: 100vh;
  padding: 2rem;
  background-color: #f8f9fa;
  transition: margin-left 0.3s ease;
}

.main-content.expanded {
  margin-left: 80px;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .sidebar {
    width: 80px;
    transform: translateX(-100%);
  }
  
  .sidebar.mobile-open {
    transform: translateX(0);
  }
  
  .main-content {
    margin-left: 0;
  }
  
  .mobile-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
  }
  
  .mobile-overlay.active {
    display: block;
  }
}

@media (max-width: 768px) {
  .main-content {
    padding: 1rem;
  }
}

/* Animation for menu items */
@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(-10px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.sidebar-menu li {
  animation: slideIn 0.3s ease forwards;
}

.sidebar-menu li:nth-child(1) { animation-delay: 0.05s; }
.sidebar-menu li:nth-child(2) { animation-delay: 0.1s; }
.sidebar-menu li:nth-child(3) { animation-delay: 0.15s; }
.sidebar-menu li:nth-child(4) { animation-delay: 0.2s; }
.sidebar-menu li:nth-child(5) { animation-delay: 0.25s; }

/* Enhanced hover effects */
.menu-item::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  height: 100%;
  width: 0;
  background: linear-gradient(90deg, rgba(255,255,255,0.1), transparent);
  transition: width 0.3s ease;
}

.menu-item:hover::before {
  width: 100%;
}

/* Status indicators */
.menu-item.active::after {
  content: '';
  position: absolute;
  right: 1rem;
  top: 50%;
  transform: translateY(-50%);
  width: 6px;
  height: 6px;
  background: #51cf66;
  border-radius: 50%;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% { opacity: 1; }
  50% { opacity: 0.5; }
  100% { opacity: 1; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mainContent = document.querySelector('.main-content');
    const currentPath = window.location.pathname.split('/').pop();
    const mobileOverlay = document.createElement('div');
    
    mobileOverlay.className = 'mobile-overlay';
    document.body.appendChild(mobileOverlay);

    // Toggle sidebar compact mode
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('compact');
        mainContent.classList.toggle('expanded');
        
        // Update toggle icon
        const icon = this.querySelector('i');
        if (sidebar.classList.contains('compact')) {
            icon.className = 'fas fa-chevron-right';
        } else {
            icon.className = 'fas fa-bars';
        }
    });

    // Mobile overlay click to close sidebar
    mobileOverlay.addEventListener('click', function() {
        sidebar.classList.remove('mobile-open');
        this.classList.remove('active');
    });

    // Handle dynamic 'active' class on page load
    document.querySelectorAll('.menu-item').forEach(item => {
        const href = item.getAttribute('href');
        if (href && href !== '#' && currentPath === href.split('/').pop()) {
            // Remove active class from all items
            document.querySelectorAll('.menu-item').forEach(i => i.classList.remove('active'));
            document.querySelectorAll('.has-submenu').forEach(i => i.classList.remove('active'));
            
            // Add active class to current item
            item.classList.add('active');
            
            // If it's in a submenu, activate parent
            const parentSubmenu = item.closest('.has-submenu');
            if (parentSubmenu) {
                parentSubmenu.classList.add('active');
            }
        }
    });

    // Handle Submenu Toggle
    document.querySelectorAll('.submenu-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const parentLi = this.closest('.has-submenu');
            
            // Close other submenus
            document.querySelectorAll('.has-submenu.active').forEach(item => {
                if (item !== parentLi) {
                    item.classList.remove('active');
                }
            });
            
            // Toggle current submenu
            parentLi.classList.toggle('active');
        });
    });

    // Close submenus when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024 && !sidebar.contains(e.target)) {
            document.querySelectorAll('.has-submenu.active').forEach(item => {
                item.classList.remove('active');
            });
        }
    });

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            sidebar.classList.remove('mobile-open');
            mobileOverlay.classList.remove('active');
        }
    });

    // Add smooth loading animation
    setTimeout(() => {
        sidebar.style.opacity = '1';
        sidebar.style.transform = 'translateX(0)';
    }, 100);
});

// Mobile menu toggle function
function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.mobile-overlay');
    
    sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('active');
}

// Initialize sidebar with fade-in effect
window.addEventListener('load', function() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.style.opacity = '0';
    sidebar.style.transform = 'translateX(-20px)';
    
    setTimeout(() => {
        sidebar.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        sidebar.style.opacity = '1';
        sidebar.style.transform = 'translateX(0)';
    }, 300);
});
</script>