<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

// Get messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success'], $_SESSION['error']);

$patient = null;
$recent_vitals = null;

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
    $provider_name = $_SESSION['user_name'] ?? 'Dr. Unknown';
    
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
        // header("Location: clinical_notes.php?patient_id=" . $patient_id);
        // exit();
        
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
        
        .collapse-toggle {
            background: none;
            border: none;
            color: #3b82f6;
            cursor: pointer;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-top: 0.5rem;
        }
        
        .collapsed-content {
            display: none;
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
                        htmlspecialchars(round(($recent_vitals['temperature'] - 32) * 5/9, 1) . '°C') : '--'; ?>,
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
                    <label class="form-label">Subjective (Symptoms & History)</label>
                    <textarea name="symptoms" class="form-textarea" 
                              placeholder="Patient's complaints, symptoms, medical history, duration, severity..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Objective (Examination Findings)</label>
                    <textarea name="objective" class="form-textarea" 
                              placeholder="Physical examination findings, vital signs, test results..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Assessment (Diagnosis)</label>
                    <textarea name="diagnosis" class="form-textarea" 
                              placeholder="Diagnosis, differential diagnosis, assessment findings..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Plan (Treatment Plan)</label>
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
                        <div class="note-label">Subjective:</div>
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


    
    <script>
        function toggleCollapse(noteId) {
            const content = document.getElementById('notes-' + noteId);
            const toggle = event.currentTarget;
            const icon = toggle.querySelector('i');
            
            if (content.style.display === 'none' || !content.style.display) {
                content.style.display = 'block';
                icon.className = 'fas fa-chevron-up';
                toggle.innerHTML = '<i class="fas fa-chevron-up"></i> Show Less';
            } else {
                content.style.display = 'none';
                icon.className = 'fas fa-chevron-down';
                toggle.innerHTML = '<i class="fas fa-chevron-down"></i> Show More';
            }
        }

        // After saving clinical notes, add consultation fee
function addConsultationFee($patient_id, $visit_id, $doctor_id) {
    global $pdo;
    
    $bill_id = getOrCreateBill($patient_id, $visit_id);
    $consultation_fee = 50.00; // Or get from services table
    
    $stmt = $pdo->prepare("
        INSERT INTO bill_items (bill_id, service_id, quantity, unit_price, total_price, department, service_date)
        VALUES (?, 'CONSULT', 1, ?, ?, 'consultation', CURDATE())");
    $stmt->execute([$bill_id, $consultation_fee, $consultation_fee]);
    
    updateBillTotal($bill_id);
}
    </script>
</body>
</html>
<?php include "includes/footer.php"; ?>