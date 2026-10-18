<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

// Check if appointment ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Appointment ID is required.";
    header("Location: appointments.php");
    exit();
}

$appointment_id = $_GET['id'];

// Fetch appointment details
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
        p.patient_id,
        p.date_of_birth,
        p.gender,
        p.phone,
        p.email,
        p.address,
        CONCAT(d.first_name, ' ', d.last_name) AS doctor_name,
        d.phone AS doctor_phone,
        d.email AS doctor_email,
        d.specialization
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users d ON a.doctor_id = d.id
    WHERE a.id = ?
");

$stmt->execute([$appointment_id]);
$appointment = $stmt->fetch();

if (!$appointment) {
    $_SESSION['error'] = "Appointment not found.";
    header("Location: appointments.php");
    exit();
}

// Get messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointment - MediCare HMS</title>
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
        
        .action-btns {
            display: flex;
            gap: 0.75rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            font-size: 0.875rem;
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
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .appointment-details {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .detail-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .info-grid {
            display: grid;
            gap: 1rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
        }
        
        .info-value {
            color: #6b7280;
            font-size: 0.875rem;
            text-align: right;
            max-width: 60%;
        }
        
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: capitalize;
        }
        
        .status-scheduled { background: #dbeafe; color: #1e40af; }
        .status-confirmed { background: #d1fae5; color: #065f46; }
        .status-completed { background: #e0e7ff; color: #4338ca; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-no_show { background: #fef3c7; color: #92400e; }
        
        .patient-info, .doctor-info {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .patient-name, .doctor-name {
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.25rem;
        }
        
        .patient-details, .doctor-details {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 0.75rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        
        .timeline-marker {
            position: absolute;
            left: -2rem;
            top: 0.25rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: #3b82f6;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #3b82f6;
        }
        
        .timeline-content {
            background: white;
            padding: 1rem;
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
        }
        
        .timeline-title {
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.25rem;
        }
        
        .timeline-description {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        
        .timeline-time {
            font-size: 0.75rem;
            color: #9ca3af;
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
        
        .notes-section {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
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
                <i class="fas fa-eye"></i>
                View Appointment
            </h1>
            <div class="action-btns">
                <a href="appointment_scheduling.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Appointments
                </a>
                <a href="edit_appointment.php?id=<?php echo $appointment_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i>
                    Edit Appointment
                </a>
                <!-- <?php if ($appointment['status'] == 'scheduled' || $appointment['status'] == 'confirmed'): ?>
                <a href="cancel_appointment.php?id=<?php echo $appointment_id; ?>" 
                   class="btn btn-danger"
                   onclick="return confirm('Are you sure you want to cancel this appointment?');">
                    <i class="fas fa-times"></i>
                    Cancel Appointment
                </a>
                <?php endif; ?>
                <?php if ($appointment['status'] == 'scheduled'): ?>
                <a href="confirm_appointment.php?id=<?php echo $appointment_id; ?>" 
                   class="btn btn-success"
                   onclick="return confirm('Mark this appointment as confirmed?');">
                    <i class="fas fa-check"></i>
                    Confirm
                </a>
                <?php endif; ?> -->
                <?php if ($appointment['status'] == 'confirmed'): ?>
                <a href="complete_appointment.php?id=<?php echo $appointment_id; ?>" 
                   class="btn btn-warning"
                   onclick="return confirm('Mark this appointment as completed?');">
                    <i class="fas fa-clipboard-check"></i>
                    Mark Complete
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="appointment-details">
            <div class="detail-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-info-circle"></i>
                        Appointment Information
                    </h2>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Appointment Number</span>
                            <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_number']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Status</span>
                            <span class="info-value">
                                <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Date & Time</span>
                            <span class="info-value">
                                <?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?><br>
                                <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Appointment Type</span>
                            <span class="info-value"><?php echo ucfirst($appointment['appointment_type']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Duration</span>
                            <span class="info-value"><?php echo $appointment['duration'] ? $appointment['duration'] . ' minutes' : 'Not specified'; ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Reason for Visit</span>
                            <span class="info-value"><?php echo htmlspecialchars($appointment['reason']); ?></span>
                        </div>
                        
                        <?php if ($appointment['notes']): ?>
                        <div class="info-item">
                            <span class="info-label">Additional Notes</span>
                            <span class="info-value"><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <span class="info-label">Created On</span>
                            <span class="info-value">
                                <?php echo date('M j, Y g:i A', strtotime($appointment['created_at'])); ?>
                            </span>
                        </div>
                        
                        <?php if ($appointment['updated_at'] && $appointment['updated_at'] != $appointment['created_at']): ?>
                        <div class="info-item">
                            <span class="info-label">Last Updated</span>
                            <span class="info-value">
                                <?php echo date('M j, Y g:i A', strtotime($appointment['updated_at'])); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div>
                <div class="detail-card" style="margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-user-injured"></i>
                            Patient Information
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="patient-info">
                            <div class="patient-name"><?php echo htmlspecialchars($appointment['patient_name']); ?></div>
                            <div class="patient-details">
                                <strong>Patient ID:</strong> <?php echo htmlspecialchars($appointment['patient_id']); ?><br>
                                <strong>Date of Birth:</strong> <?php echo date('M j, Y', strtotime($appointment['date_of_birth'])); ?><br>
                                <strong>Gender:</strong> <?php echo ucfirst($appointment['gender']); ?><br>
                                <strong>Phone:</strong> <?php echo htmlspecialchars($appointment['phone']); ?><br>
                                <?php if ($appointment['email']): ?>
                                <strong>Email:</strong> <?php echo htmlspecialchars($appointment['email']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="detail-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-user-md"></i>
                            Doctor Information
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="doctor-info">
                            <div class="doctor-name">Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></div>
                            <div class="doctor-details">
                                <?php if ($appointment['specialization']): ?>
                                <strong>Specialization:</strong> <?php echo htmlspecialchars($appointment['specialization']); ?><br>
                                <?php endif; ?>
                                <?php if ($appointment['doctor_phone']): ?>
                                <strong>Phone:</strong> <?php echo htmlspecialchars($appointment['doctor_phone']); ?><br>
                                <?php endif; ?>
                                <?php if ($appointment['doctor_email']): ?>
                                <strong>Email:</strong> <?php echo htmlspecialchars($appointment['doctor_email']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="notes-section">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-history"></i>
                    Appointment History
                </h2>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Appointment Created</div>
                            <div class="timeline-description">Appointment was scheduled in the system</div>
                            <div class="timeline-time"><?php echo date('M j, Y g:i A', strtotime($appointment['created_at'])); ?></div>
                        </div>
                    </div>
                    
                    <?php if ($appointment['status'] == 'confirmed'): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Appointment Confirmed</div>
                            <div class="timeline-description">Patient confirmed their attendance</div>
                            <div class="timeline-time">Status updated to confirmed</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($appointment['status'] == 'completed'): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Appointment Completed</div>
                            <div class="timeline-description">Appointment was successfully completed</div>
                            <div class="timeline-time">Status updated to completed</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($appointment['status'] == 'cancelled'): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Appointment Cancelled</div>
                            <div class="timeline-description">
                                <?php echo $appointment['cancellation_reason'] ? 'Reason: ' . htmlspecialchars($appointment['cancellation_reason']) : 'Appointment was cancelled'; ?>
                            </div>
                            <div class="timeline-time">Status updated to cancelled</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($appointment['updated_at'] && $appointment['updated_at'] != $appointment['created_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Appointment Updated</div>
                            <div class="timeline-description">Appointment details were modified</div>
                            <div class="timeline-time"><?php echo date('M j, Y g:i A', strtotime($appointment['updated_at'])); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Add confirmation for status changes
        document.addEventListener('DOMContentLoaded', function() {
            const actionLinks = document.querySelectorAll('a[href*="confirm_appointment"], a[href*="complete_appointment"], a[href*="cancel_appointment"]');
            actionLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!confirm(this.getAttribute('onclick')?.replace("return confirm('", "").replace("');", "") || 'Are you sure?')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>
<?php include "includes/footer.php"; ?>