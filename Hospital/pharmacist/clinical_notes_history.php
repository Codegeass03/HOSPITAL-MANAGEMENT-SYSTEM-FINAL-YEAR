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
$clinical_notes = [];

// Check if patient_id is provided via GET
if (isset($_GET['patient_id'])) {
    $patient_id = $_GET['patient_id'];
    
    // Fetch patient details
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch();
    
    if ($patient) {
        // Fetch all clinical notes for this patient
        $notes_stmt = $pdo->prepare("
            SELECT cn.*, u.first_name, u.last_name 
            FROM clinical_notes cn 
            LEFT JOIN users u ON cn.doctor_id = u.id 
            WHERE cn.patient_id = ? 
            ORDER BY cn.created_at DESC
        ");
        $notes_stmt->execute([$patient_id]);
        $clinical_notes = $notes_stmt->fetchAll();
    }
}

// Handle note deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_note'])) {
    $note_id = $_POST['note_id'];
    $patient_id = $_POST['patient_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM clinical_notes WHERE id = ?");
        $stmt->execute([$note_id]);
        
        $_SESSION['success'] = "Clinical note deleted successfully.";
        // header("Location: clinical_notes_history.php?patient_id=" . $patient_id);
        // exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting clinical note: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinical Notes History - AuraCare HMS</title>
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
        
        .notes-container {
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
        
        .note-actions {
            display: flex;
            gap: 0.5rem;
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
        
        .btn {
            padding: 0.5rem 1rem;
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
        
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
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
                <i class="fas fa-history"></i>
                Clinical Notes History
            </h1>
            <p class="page-subtitle">View and manage patient's clinical notes history</p>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 2rem;">
            <h1><i class="fas fa-syringe"></i> MEDICAL HISTORY</h1>
            <a href="clinical_notes.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Notes
            </a>
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
                </div>
            </div>
        </div>

        <div class="notes-container">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-file-medical"></i>
                    Clinical Notes History
                </h2>
                <a href="clinical_notes.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add New Note
                </a>
            </div>
            
            <?php if (count($clinical_notes) > 0): ?>
                <?php foreach ($clinical_notes as $note): ?>
                <div class="note-item">
                    <div class="note-header">
                        <div class="note-date">
                            <?php echo date('M j, Y g:i A', strtotime($note['created_at'])); ?>
                        </div>
                        <div class="note-actions">
                            <div class="note-doctor">
                                By <?php echo htmlspecialchars($note['provider_name'] ?: $note['first_name'] . ' ' . $note['last_name']); ?>
                            </div>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this clinical note?');">
                                <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">
                                <button type="submit" name="delete_note" class="btn btn-danger">
                                    <i class="fas fa-trash"></i>
                                    Delete
                                </button>
                            </form>
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
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-medical"></i>
                    <h3>No Clinical Notes Found</h3>
                    <p>No clinical notes have been recorded for this patient yet.</p>
                    <a href="clinical_notes.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-plus"></i>
                        Add First Clinical Note
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-user-plus"></i>
            <h3>No Patient Selected</h3>
            <p>Please select a patient to view their clinical notes history.</p>
            <a href="patient_search.php" class="btn btn-primary" style="margin-top: 1rem;">
                <i class="fas fa-search"></i>
                Search for Patient
            </a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php include "includes/footer.php"; ?>