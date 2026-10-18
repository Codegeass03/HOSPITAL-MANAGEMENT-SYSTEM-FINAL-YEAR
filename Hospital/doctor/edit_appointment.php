<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No appointment ID provided.";
    header("Location: appointment_scheduling.php");
    exit();
}

$appointment_id = intval($_GET['id']);

// Fetch appointment details
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
        p.patient_id,
        CONCAT(d.first_name, ' ', d.last_name) AS doctor_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users d ON a.doctor_id = d.id
    WHERE a.id = ?
");
$stmt->execute([$appointment_id]);
$appointment = $stmt->fetch();

if (!$appointment) {
    $_SESSION['error'] = "Appointment not found.";
    header("Location: appointment_scheduling.php");
    exit();
}

// Fetch patients and doctors for dropdowns
$patients = $pdo->query("SELECT id, patient_id, CONCAT(first_name, ' ', last_name) AS name FROM patients WHERE status = 'active' ORDER BY first_name")->fetchAll();
$doctors = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM users WHERE role = 'Doctor' AND status = 'active' ORDER BY first_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Appointment - MediCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 260px;
            padding-left: 250px;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 1.875rem;
            font-weight: 600;
            color: #111827;
            margin: 0 0 0.5rem 0;
        }
        
        .breadcrumb {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .breadcrumb a {
            color: #3b82f6;
            text-decoration: none;
        }
        
        .edit-form {
            background: white;
            padding: 2rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            max-width: 800px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
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
        
        .form-input, .form-select, .form-textarea {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
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
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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
    </style>
</head>
<body>
    <div class="main-content"style="padding-left: 280px;">
        <div class="page-header">
            <h1 class="page-title">Edit Appointment</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> / 
                <a href="appointment_scheduling.php">Appointments</a> / 
                Edit Appointment
            </div>
        </div>
        
        <div class="edit-form">
            <form method="POST" action="update_appointment.php">
                <input type="hidden" name="id" value="<?php echo $appointment['id']; ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Appointment Number</label>
                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($appointment['appointment_number']); ?>" readonly style="background: #f9fafb;">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="scheduled" <?php echo $appointment['status'] == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="confirmed" <?php echo $appointment['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="in_progress" <?php echo $appointment['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $appointment['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $appointment['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="no_show" <?php echo $appointment['status'] == 'no_show' ? 'selected' : ''; ?>>No Show</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Patient *</label>
                        <select name="patient_id" class="form-select" required>
                            <?php foreach ($patients as $patient): ?>
                                <option value=""></option>
                            <option value="<?php echo $patient['id']; ?>" <?php echo $appointment['patient_id'] == $patient['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($patient['name'] . ' (' . $patient['patient_id'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Doctor *</label>
                        <select name="doctor_id" class="form-select" required>
                            <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['id']; ?>" <?php echo $appointment['doctor_id'] == $doctor['id'] ? 'selected' : ''; ?>>
                                Dr. <?php echo htmlspecialchars($doctor['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Date *</label>
                        <input type="date" name="appointment_date" class="form-input" 
                               value="<?php echo $appointment['appointment_date']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Time *</label>
                        <input type="time" name="appointment_time" class="form-input" 
                               value="<?php echo $appointment['appointment_time']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Type *</label>
                        <select name="appointment_type" class="form-select" required>
                            <option value="consultation" <?php echo $appointment['appointment_type'] == 'consultation' ? 'selected' : ''; ?>>Consultation</option>
                            <option value="followup" <?php echo $appointment['appointment_type'] == 'followup' ? 'selected' : ''; ?>>Follow-up</option>
                            <option value="emergency" <?php echo $appointment['appointment_type'] == 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                            <option value="procedure" <?php echo $appointment['appointment_type'] == 'procedure' ? 'selected' : ''; ?>>Procedure</option>
                            <option value="checkup" <?php echo $appointment['appointment_type'] == 'checkup' ? 'selected' : ''; ?>>Check-up</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Duration (minutes)</label>
                        <input type="number" name="duration" class="form-input" 
                               value="<?php echo $appointment['duration']; ?>" min="15" step="15">
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label">Reason for Visit *</label>
                        <textarea name="reason" class="form-textarea" required><?php echo htmlspecialchars($appointment['reason']); ?></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-textarea"><?php echo htmlspecialchars($appointment['notes']); ?></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="appointment_scheduling.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php include "includes/footer.php"; ?>