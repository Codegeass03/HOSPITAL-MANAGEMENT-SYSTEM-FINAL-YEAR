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

// Get today's stats
$today = date('Y-m-d');
$stats = [
    'today_appointments' => 0,
    'pending_lab_orders' => 0,
    'pending_prescriptions' => 0,
    'active_patients' => 0
];

// Today's appointments
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = ? AND status IN ('scheduled', 'confirmed')");
$stmt->execute([$today]);
$stats['today_appointments'] = $stmt->fetchColumn();

// Pending lab orders
$stmt = $pdo->prepare("SELECT COUNT(*) FROM lab_orders WHERE status = 'pending'");
$stmt->execute();
$stats['pending_lab_orders'] = $stmt->fetchColumn();

// Pending prescriptions
$stmt = $pdo->prepare("SELECT COUNT(*) FROM prescriptions WHERE status = 'pending'");
$stmt->execute();
$stats['pending_prescriptions'] = $stmt->fetchColumn();

// Active patients (seen in last 30 days)
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stmt->execute();
$stats['active_patients'] = $stmt->fetchColumn();

// Recent appointments for today
$recent_appointments_stmt = $pdo->prepare("
    SELECT a.*, p.first_name, p.last_name, p.patient_id, u.first_name as doctor_first_name, u.last_name as doctor_last_name
    FROM appointments a 
    JOIN patients p ON a.patient_id = p.id 
    JOIN users u ON a.doctor_id = u.id 
    WHERE a.appointment_date = ? AND a.status IN ('scheduled', 'confirmed')
    ORDER BY a.appointment_time 
    LIMIT 10
");
$recent_appointments_stmt->execute([$today]);
$recent_appointments = $recent_appointments_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EHR Dashboard - AuraCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 280px;
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
        
        .page-subtitle {
            color: #64748b;
            font-size: 0.875rem;
            margin: 0.5rem 0 0 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-icon.appointments { background: #dbeafe; color: #1e40af; }
        .stat-icon.lab { background: #fef3c7; color: #d97706; }
        .stat-icon.prescriptions { background: #dcfce7; color: #166534; }
        .stat-icon.patients { background: #e0e7ff; color: #4338ca; }
        
        .stat-content h3 {
            margin: 0;
            font-size: 1.875rem;
            font-weight: 700;
            color: #111827;
        }
        
        .stat-content p {
            margin: 0.25rem 0 0 0;
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        .card {
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
        
        .appointment-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .appointment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            transition: background-color 0.2s;
        }
        
        .appointment-item:hover {
            background: #f9fafb;
        }
        
        .patient-info h4 {
            margin: 0;
            font-size: 0.875rem;
            font-weight: 600;
            color: #111827;
        }
        
        .patient-info p {
            margin: 0.25rem 0 0 0;
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .appointment-time {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            text-decoration: none;
            color: #374151;
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            background: #f8fafc;
            border-color: #3b82f6;
            transform: translateX(4px);
        }
        
        .action-icon {
            width: 40px;
            height: 40px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        
        .action-icon.search { background: #dbeafe; color: #1e40af; }
        .action-icon.notes { background: #dcfce7; color: #166534; }
        .action-icon.lab { background: #fef3c7; color: #d97706; }
        .action-icon.prescription { background: #e0e7ff; color: #4338ca; }
        
        .action-text h4 {
            margin: 0;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .action-text p {
            margin: 0.25rem 0 0 0;
            font-size: 0.75rem;
            color: #6b7280;
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
            <div>
                <h1 class="page-title">
                    <i class="fas fa-file-medical"></i>
                    EHR Dashboard
                </h1>
                <p class="page-subtitle">Electronic Health Records Management System</p>
            </div>
            <div style="color: #6b7280; font-size: 0.875rem;">
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon appointments">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['today_appointments']; ?></h3>
                    <p>Today's Appointments</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon lab">
                    <i class="fas fa-vials"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['pending_lab_orders']; ?></h3>
                    <p>Pending Lab Orders</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon prescriptions">
                    <i class="fas fa-prescription"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['pending_prescriptions']; ?></h3>
                    <p>Pending Prescriptions</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon patients">
                    <i class="fas fa-user-injured"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['active_patients']; ?></h3>
                    <p>Active Patients (30 days)</p>
                </div>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-list"></i>
                        Today's Appointments
                    </h2>
                </div>
                <div class="card-body">
                    <?php if (count($recent_appointments) > 0): ?>
                    <div class="appointment-list">
                        <?php foreach ($recent_appointments as $apt): ?>
                        <div class="appointment-item">
                            <div class="patient-info">
                                <h4><?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?></h4>
                                <p>ID: <?php echo htmlspecialchars($apt['patient_id']); ?> • Dr. <?php echo htmlspecialchars($apt['doctor_first_name'] . ' ' . $apt['doctor_last_name']); ?></p>
                            </div>
                            <div class="appointment-time">
                                <?php echo date('h:i A', strtotime($apt['appointment_time'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Appointments Today</h3>
                        <p>There are no scheduled appointments for today.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-bolt"></i>
                        Quick Actions
                    </h2>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="patient_search.php" class="action-btn">
                            <div class="action-icon search">
                                <i class="fas fa-search"></i>
                            </div>
                            <div class="action-text">
                                <h4>Search Patient</h4>
                                <p>Find patient records</p>
                            </div>
                        </a>
                        
                        <a href="clinical_notes.php" class="action-btn">
                            <div class="action-icon notes">
                                <i class="fas fa-file-medical"></i>
                            </div>
                            <div class="action-text">
                                <h4>Clinical Notes</h4>
                                <p>Document patient visits</p>
                            </div>
                        </a>
                        
                        <a href="lab_orders.php" class="action-btn">
                            <div class="action-icon lab">
                                <i class="fas fa-vials"></i>
                            </div>
                            <div class="action-text">
                                <h4>Lab Orders</h4>
                                <p>Order laboratory tests</p>
                            </div>
                        </a>
                        
                        <a href="prescriptions.php" class="action-btn">
                            <div class="action-icon prescription">
                                <i class="fas fa-prescription"></i>
                            </div>
                            <div class="action-text">
                                <h4>Prescriptions</h4>
                                <p>Prescribe medications</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php include "includes/footer.php"; ?>