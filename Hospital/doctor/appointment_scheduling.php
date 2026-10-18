<?php
session_start();
include "includes/db_connect.php";


if (!isset($pdo)) {
    die("Database connection failed.");
}

// Check if user is logged in as a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: ../login.php?role=doctor");
    exit();
}

$doctor_id = $_SESSION['user_id'];

// Get messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success'], $_SESSION['error']);

// Fetch doctor's details
$doctor_stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ? AND role = 'Doctor'");
$doctor_stmt->execute([$doctor_id]);
$doctor = $doctor_stmt->fetch();

// Fetch patients for dropdown (only active patients)
$patients_stmt = $pdo->query("SELECT id, patient_id, CONCAT(first_name, ' ', last_name) AS name FROM patients WHERE status = 'active' ORDER BY first_name");
$patients = $patients_stmt->fetchAll();

// Fetch consultation services from services table
$services_stmt = $pdo->prepare("
    SELECT id, name, price, description 
    FROM services 
    WHERE category = 'Consultation' AND is_active = 1 
    ORDER BY price ASC
");
$services_stmt->execute();
$consultation_services = $services_stmt->fetchAll();

// Handle filter selection
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$where_clause = "WHERE a.doctor_id = :doctor_id";
$params = [':doctor_id' => $doctor_id];

switch ($filter) {
    case 'today':
        $where_clause .= " AND a.appointment_date = CURDATE()";
        break;
    case 'week':
        $where_clause .= " AND a.appointment_date >= CURDATE() AND a.appointment_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'upcoming':
        $where_clause .= " AND a.appointment_date > CURDATE()";
        break;
    case 'completed':
        $where_clause .= " AND a.status = 'completed'";
        break;
    case 'cancelled':
        $where_clause .= " AND a.status = 'cancelled'";
        break;
    default:
        $where_clause .= " AND a.appointment_date >= CURDATE()";
}

// Fetch appointments for the specific doctor with filter
$appointments_stmt = $pdo->prepare("
    SELECT 
        a.id,
        a.appointment_number,
        a.appointment_date,
        a.appointment_time,
        a.appointment_type,
        a.service_id,
        a.consultation_fee,
        a.status,
        a.reason,
        CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
        p.patient_id,
        p.phone AS patient_phone,
        s.name as service_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    LEFT JOIN services s ON a.service_id = s.id
    $where_clause
    ORDER BY a.appointment_date, a.appointment_time
    LIMIT 50
");

$appointments_stmt->execute($params);
$appointments = $appointments_stmt->fetchAll();

// Get statistics for this doctor
$stats_queries = [
    'scheduled' => "SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'scheduled' AND appointment_date >= CURDATE()",
    'confirmed' => "SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'confirmed' AND appointment_date >= CURDATE()",
    'completed' => "SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'completed' AND DATE(appointment_date) = CURDATE()",
    'cancelled' => "SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'cancelled' AND appointment_date >= CURDATE()"
];

$stats = [];
foreach ($stats_queries as $key => $query) {
    $stmt = $pdo->prepare($query);
    $stmt->execute([$doctor_id]);
    $stats[$key] = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Scheduling - MediCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
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
        
        @media (max-width: 768px) {
            .page-title {
                font-size: 1.5rem;
            }
        }
        
        .page-subtitle {
            color: #64748b;
            font-size: 0.875rem;
            margin: 0.5rem 0 0 0;
        }
        
        .add-btn {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
            white-space: nowrap;
        }
        
        .add-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        @media (max-width: 768px) {
            .add-btn {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
        }
        
        .doctor-welcome {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .doctor-welcome i {
            font-size: 1.5rem;
        }
        
        .doctor-welcome h3 {
            margin: 0;
            font-size: 1.25rem;
        }
        
        .doctor-welcome p {
            margin: 0.25rem 0 0 0;
            opacity: 0.9;
            font-size: 0.875rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
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
            cursor: pointer;
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
        
        @media (max-width: 768px) {
            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
            }
        }
        
        .stat-icon.scheduled { background: #dbeafe; color: #1e40af; }
        .stat-icon.confirmed { background: #d1fae5; color: #065f46; }
        .stat-icon.completed { background: #e0e7ff; color: #4338ca; }
        .stat-icon.cancelled { background: #fee2e2; color: #991b1b; }
        
        .stat-content h3 {
            margin: 0;
            font-size: 1.875rem;
            font-weight: 700;
            color: #111827;
        }
        
        @media (max-width: 768px) {
            .stat-content h3 {
                font-size: 1.5rem;
            }
        }
        
        .stat-content p {
            margin: 0.25rem 0 0 0;
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .appointments-section {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-top: 1.5rem;
        }
        
        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .section-header {
                padding: 1rem;
                flex-direction: column;
                align-items: stretch;
            }
        }
        
        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
        }
        
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .filter-tabs {
                justify-content: center;
            }
        }
        
        .filter-tab {
            padding: 0.5rem 1rem;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: #374151;
            white-space: nowrap;
        }
        
        .filter-tab:hover {
            background: #f3f4f6;
        }
        
        .filter-tab.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        @media (max-width: 768px) {
            .filter-tab {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
                flex: 1;
                text-align: center;
            }
            
            .filter-tabs {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 0.5rem;
                width: 100%;
            }
        }
        
        .table-container {
            overflow-x: auto;
            width: 100%;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        @media (max-width: 768px) {
            table {
                min-width: 600px;
            }
        }
        
        thead {
            background: #f9fafb;
        }
        
        th {
            padding: 0.875rem 1rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        @media (max-width: 768px) {
            th {
                padding: 0.75rem 0.5rem;
            }
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.875rem;
            color: #374151;
        }
        
        @media (max-width: 768px) {
            td {
                padding: 0.75rem 0.5rem;
            }
        }
        
        tr:hover {
            background: #f9fafb;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: capitalize;
            display: inline-block;
        }
        
        .status-scheduled { background: #dbeafe; color: #1e40af; }
        .status-confirmed { background: #d1fae5; color: #065f46; }
        .status-completed { background: #e0e7ff; color: #4338ca; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-no_show { background: #fef3c7; color: #92400e; }
        
        .action-btns {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .action-btns {
                gap: 0.25rem;
            }
        }
        
        .action-btn {
            padding: 0.375rem 0.75rem;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: #374151;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            white-space: nowrap;
        }
        
        @media (max-width: 768px) {
            .action-btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.7rem;
            }
        }
        
        .action-btn:hover {
            background: #f3f4f6;
        }
        
        .action-btn.vitals {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            border-color: #8b5cf6;
        }
        
        .action-btn.vitals:hover {
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
        }
        
        .mobile-view {
            display: none;
        }
        
        @media (max-width: 768px) {
            .desktop-view {
                display: none;
            }
            
            .mobile-view {
                display: block;
            }
            
            .appointment-card {
                background: white;
                border: 1px solid #e5e7eb;
                border-radius: 0.5rem;
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .appointment-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 1rem;
            }
            
            .appointment-number {
                font-weight: 600;
                color: #3b82f6;
                font-size: 0.9rem;
            }
            
            .appointment-status {
                font-size: 0.75rem;
                padding: 0.2rem 0.5rem;
                border-radius: 9999px;
            }
            
            .appointment-details {
                margin-bottom: 1rem;
            }
            
            .detail-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 0.5rem;
                font-size: 0.875rem;
            }
            
            .detail-label {
                color: #6b7280;
            }
            
            .detail-value {
                font-weight: 500;
                text-align: right;
            }
            
            .mobile-actions {
                display: flex;
                gap: 0.5rem;
                flex-wrap: wrap;
            }
        }
        
        /* Modal styles remain the same */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 0.75rem;
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        @media (max-width: 768px) {
            .modal-content {
                max-width: 100%;
            }
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .modal-header {
                padding: 1rem;
            }
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6b7280;
            cursor: pointer;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .modal-body {
                padding: 1rem;
            }
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
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
        
        @media (max-width: 768px) {
            .form-input, .form-select, .form-textarea {
                padding: 0.6rem;
            }
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }
        
        @media (max-width: 768px) {
            .modal-footer {
                padding: 1rem;
                flex-direction: column;
            }
        }
        
        .btn-secondary {
            padding: 0.75rem 1.5rem;
            border: 1px solid #d1d5db;
            background: white;
            color: #374151;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
        }
        
        .btn-primary {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .btn-secondary, .btn-primary {
                padding: 0.6rem 1rem;
                width: 100%;
            }
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .alert {
                padding: 0.75rem 1rem;
            }
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
            padding: 3rem 1rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            margin: 0 0 0.5rem 0;
            font-weight: 500;
        }
        
        .empty-state p {
            margin: 0;
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
        
        <div class="doctor-welcome">
            <i class="fas fa-user-md"></i>
            <div>
                <h3>Welcome, Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h3>
                <p>Here are your upcoming appointments and schedule</p>
            </div>
        </div>
        
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-calendar-check"></i>
                    Your Appointments
                </h1>
                <p class="page-subtitle">Manage your patient appointments and schedule</p>
            </div>
            <!-- <button class="add-btn" onclick="openModal()">
                <i class="fas fa-plus"></i>
                Schedule Appointment
            </button> -->
        </div>
        
        <div class="stats-grid">
            <?php
            $stats_data = [
                'scheduled' => ['icon' => 'calendar', 'label' => 'Scheduled', 'class' => 'scheduled'],
                'confirmed' => ['icon' => 'check-circle', 'label' => 'Confirmed', 'class' => 'confirmed'],
                'completed' => ['icon' => 'clipboard-check', 'label' => 'Completed Today', 'class' => 'completed'],
                'cancelled' => ['icon' => 'times-circle', 'label' => 'Cancelled', 'class' => 'cancelled']
            ];
            
            foreach ($stats_data as $status => $info) {
                $count = $stats[$status];
            ?>
            <div class="stat-card" onclick="window.location.href='?filter=<?php echo $status; ?>'">
                <div class="stat-icon <?php echo $info['class']; ?>">
                    <i class="fas fa-<?php echo $info['icon']; ?>"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $count; ?></h3>
                    <p><?php echo $info['label']; ?></p>
                </div>
            </div>
            <?php } ?>
        </div>
        
        <div class="appointments-section">
            <div class="section-header">
                <h2 class="section-title">My Appointments</h2>
                <div class="filter-tabs">
                    <a href="?filter=all" class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">
                        All Upcoming
                    </a>
                    <a href="?filter=today" class="filter-tab <?php echo $filter == 'today' ? 'active' : ''; ?>">
                        Today
                    </a>
                    <a href="?filter=week" class="filter-tab <?php echo $filter == 'week' ? 'active' : ''; ?>">
                        This Week
                    </a>
                    <a href="?filter=completed" class="filter-tab <?php echo $filter == 'completed' ? 'active' : ''; ?>">
                        Completed
                    </a>
                    <a href="?filter=cancelled" class="filter-tab <?php echo $filter == 'cancelled' ? 'active' : ''; ?>">
                        Cancelled
                    </a>
                </div>
            </div>
            
            <?php if (empty($appointments)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No appointments found</h3>
                <p><?php 
                    if ($filter == 'today') echo "You have no appointments scheduled for today.";
                    elseif ($filter == 'week') echo "You have no appointments scheduled for this week.";
                    elseif ($filter == 'completed') echo "You have no completed appointments.";
                    elseif ($filter == 'cancelled') echo "You have no cancelled appointments.";
                    else echo "You have no upcoming appointments.";
                ?></p>
            </div>
            <?php else: ?>
            
            <!-- Desktop View -->
            <div class="desktop-view">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Appointment #</th>
                                <th>Patient</th>
                                <th>Date & Time</th>
                                <th>Type</th>
                                <th>Fee</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $apt): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($apt['appointment_number']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($apt['patient_name']); ?></strong><br>
                                    <small style="color: #6b7280;"><?php echo htmlspecialchars($apt['patient_id']); ?></small>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?><br>
                                    <small style="color: #6b7280;"><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></small>
                                </td>
                                <td>
                                    <?php 
                                    if (!empty($apt['service_name'])) {
                                        echo htmlspecialchars($apt['service_name']);
                                    } else {
                                        echo ucfirst(str_replace('_', ' ', $apt['appointment_type']));
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($apt['consultation_fee'] > 0): ?>
                                        Ksh <?php echo number_format($apt['consultation_fee'], 2); ?>
                                    <?php else: ?>
                                        <span style="color: #6b7280;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $apt['status']; ?>">
                                        <?php echo ucfirst($apt['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="view_appointment.php?id=<?php echo $apt['id']; ?>" class="action-btn">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="edit_appointment.php?id=<?php echo $apt['id']; ?>" class="action-btn">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <button class="action-btn vitals" onclick="openVitalsModal(<?php echo $apt['id']; ?>, '<?php echo htmlspecialchars(addslashes($apt['patient_name'])); ?>', '<?php echo htmlspecialchars($apt['patient_id']); ?>')">
                                            <i class="fas fa-heartbeat"></i> Vitals
                                        </button>
                                        <?php if ($apt['status'] == 'scheduled' || $apt['status'] == 'confirmed'): ?>
                                        <a href="cancel_appointment.php?id=<?php echo $apt['id']; ?>" 
                                           class="action-btn" 
                                           onclick="return confirm('Cancel this appointment?');">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Mobile View -->
            <div class="mobile-view">
                <?php foreach ($appointments as $apt): ?>
                <div class="appointment-card">
                    <div class="appointment-header">
                        <div class="appointment-number"><?php echo htmlspecialchars($apt['appointment_number']); ?></div>
                        <span class="status-badge status-<?php echo $apt['status']; ?> appointment-status">
                            <?php echo ucfirst($apt['status']); ?>
                        </span>
                    </div>
                    
                    <div class="appointment-details">
                        <div class="detail-row">
                            <span class="detail-label">Patient:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($apt['patient_name']); ?><br>
                            <small><?php echo htmlspecialchars($apt['patient_id']); ?></small></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Date:</span>
                            <span class="detail-value"><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Time:</span>
                            <span class="detail-value"><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Type:</span>
                            <span class="detail-value">
                                <?php 
                                if (!empty($apt['service_name'])) {
                                    echo htmlspecialchars($apt['service_name']);
                                } else {
                                    echo ucfirst(str_replace('_', ' ', $apt['appointment_type']));
                                }
                                ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Fee:</span>
                            <span class="detail-value">
                                <?php if ($apt['consultation_fee'] > 0): ?>
                                    Ksh <?php echo number_format($apt['consultation_fee'], 2); ?>
                                <?php else: ?>
                                    <span style="color: #6b7280;">-</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mobile-actions">
                        <a href="view_appointment.php?id=<?php echo $apt['id']; ?>" class="action-btn" style="flex: 1;">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a href="edit_appointment.php?id=<?php echo $apt['id']; ?>" class="action-btn" style="flex: 1;">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <button class="action-btn vitals" style="flex: 1;" onclick="openVitalsModal(<?php echo $apt['id']; ?>, '<?php echo htmlspecialchars(addslashes($apt['patient_name'])); ?>', '<?php echo htmlspecialchars($apt['patient_id']); ?>')">
                            <i class="fas fa-heartbeat"></i> Vitals
                        </button>
                        <?php if ($apt['status'] == 'scheduled' || $apt['status'] == 'confirmed'): ?>
                        <a href="cancel_appointment.php?id=<?php echo $apt['id']; ?>" 
                           class="action-btn" 
                           onclick="return confirm('Cancel this appointment?');"
                           style="flex: 1;">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Schedule Appointment Modal -->
    <div class="modal" id="scheduleModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Schedule New Appointment</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="process_schedule_appointment.php">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Patient *</label>
                            <select name="patient_id" class="form-select" required>
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>">
                                    <?php echo htmlspecialchars($patient['name'] . ' (' . $patient['patient_id'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Doctor is automatically set to logged-in doctor -->
                        <input type="hidden" name="doctor_id" value="<?php echo $doctor_id; ?>">
                        
                        <div class="form-group">
                            <label class="form-label">Appointment Date *</label>
                            <input type="date" name="appointment_date" class="form-input" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Appointment Time *</label>
                            <input type="time" name="appointment_time" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Appointment Type *</label>
                            <select name="appointment_type" class="form-select" required onchange="updateConsultationFee(this)">
                                <option value="">Select Consultation Type</option>
                                <?php foreach ($consultation_services as $service): ?>
                                <option value="<?php echo htmlspecialchars($service['name']); ?>" 
                                        data-fee="<?php echo $service['price']; ?>"
                                        data-service-id="<?php echo $service['id']; ?>">
                                    <?php echo htmlspecialchars($service['name']); ?> - Ksh <?php echo number_format($service['price'], 2); ?>
                                    <?php if (!empty($service['description'])): ?>
                                        (<?php echo htmlspecialchars($service['description']); ?>)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                                <!-- Add non-consultation types -->
                                <option value="procedure" data-fee="0">Procedure (Fee varies)</option>
                                <option value="checkup" data-fee="0">Routine Check-up (Fee varies)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Duration (minutes)</label>
                            <input type="number" name="duration" class="form-input" 
                                   value="30" min="15" step="15">
                        </div>
                        
                        <!-- Hidden fields for service ID and consultation fee -->
                        <input type="hidden" name="service_id" id="serviceIdInput" value="">
                        <input type="hidden" name="consultation_fee" id="consultationFeeInput" value="0">
                        
                        <!-- Consultation fee display -->
                        <div class="form-group full-width" id="consultationFeeGroup" style="display: none;">
                            <label class="form-label">Consultation Fee</label>
                            <div class="fee-display">
                                Ksh <span id="consultationFeeDisplay">0.00</span>
                            </div>
                            <small style="color: #6b7280; margin-top: 0.25rem;">
                                This fee will be automatically added to the patient's bill.
                            </small>
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label">Reason for Visit *</label>
                            <textarea name="reason" class="form-textarea" 
                                      placeholder="Enter reason for appointment" required></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-textarea" 
                                      placeholder="Additional notes or special instructions"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Schedule Appointment</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Record Vitals Modal -->
    <div class="modal" id="vitalsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Record Patient Vitals</h2>
                <button class="modal-close" onclick="closeVitalsModal()">&times;</button>
            </div>
            <form method="POST" action="process_record_vitals.php">
                <input type="hidden" name="appointment_id" id="vitals_id">
                <div class="modal-body">
                    <div class="patient-info-card">
                        <div class="patient-name" id="vitals_patient_name"></div>
                        <div class="patient-details" id="vitals_patient_details"></div>
                    </div>
                    
                    <div class="vitals-grid">
                        <div class="form-group">
                            <label class="form-label">Blood Pressure (Systolic)</label>
                            <input type="number" name="blood_pressure_systolic" class="form-input" placeholder="e.g., 120" min="50" max="250">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Blood Pressure (Diastolic)</label>
                            <input type="number" name="blood_pressure_diastolic" class="form-input" placeholder="e.g., 80" min="30" max="150">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Heart Rate (bpm)</label>
                            <input type="number" name="heart_rate" class="form-input" placeholder="e.g., 72">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Temperature (°C)</label>
                            <input type="number" step="0.1" name="temperature" class="form-input" placeholder="e.g., 36.6">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Respiratory Rate</label>
                            <input type="number" name="respiratory_rate" class="form-input" placeholder="e.g., 16">
                        </div>
                        <div class="form-group">
                            <label class="form-label">SpO2 (%)</label>
                            <input type="number" name="spo2" class="form-input" placeholder="e.g., 98" min="0" max="100">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Weight (kg)</label>
                            <input type="number" step="0.1" name="weight" class="form-input" placeholder="e.g., 70.5" id="weight_input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Height (cm)</label>
                            <input type="number" step="0.1" name="height" class="form-input" placeholder="e.g., 175.0" id="height_input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Pain Level (0-10)</label>
                            <input type="number" name="pain_level" class="form-input" placeholder="0-10" min="0" max="10">
                        </div>
                    </div>
                    
                    <div class="bmi-calculator">
                        <div style="font-weight: 600; margin-bottom: 0.5rem;">BMI Calculator</div>
                        <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;">
                            Enter weight and height to calculate BMI
                        </div>
                        <div class="bmi-result" id="bmi_result">BMI: --</div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label">Additional Notes</label>
                        <textarea name="notes" class="form-textarea" placeholder="Any additional observations or notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeVitalsModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Save Vitals</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openModal() {
            document.getElementById('scheduleModal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('scheduleModal').classList.remove('show');
        }
        
        function openVitalsModal(appointmentId, patientName, patientId) {
            document.getElementById('vitals_id').value = appointmentId;
            document.getElementById('vitals_patient_name').textContent = patientName;
            document.getElementById('vitals_patient_details').textContent = `Patient ID: ${patientId}`;
            document.getElementById('vitalsModal').classList.add('show');
        }
        
        function closeVitalsModal() {
            document.getElementById('vitalsModal').classList.remove('show');
        }
        
        function updateConsultationFee(select) {
            const feeGroup = document.getElementById('consultationFeeGroup');
            const feeDisplay = document.getElementById('consultationFeeDisplay');
            const feeInput = document.getElementById('consultationFeeInput');
            const serviceIdInput = document.getElementById('serviceIdInput');
            
            if (select.value) {
                const selectedOption = select.options[select.selectedIndex];
                const fee = selectedOption.getAttribute('data-fee');
                const serviceId = selectedOption.getAttribute('data-service-id');
                
                feeDisplay.textContent = parseFloat(fee).toFixed(2);
                feeInput.value = fee;
                serviceIdInput.value = serviceId || '';
                feeGroup.style.display = 'block';
            } else {
                feeGroup.style.display = 'none';
                feeInput.value = '0';
                serviceIdInput.value = '';
            }
        }
        
        // Close modal when clicking outside
        document.getElementById('scheduleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        document.getElementById('vitalsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeVitalsModal();
            }
        });
        
        // BMI Calculator
        function calculateBMI() {
            const weight = parseFloat(document.getElementById('weight_input').value);
            const height = parseFloat(document.getElementById('height_input').value) / 100; // convert to meters
            
            if (weight && height) {
                const bmi = weight / (height * height);
                let category = '';
                
                if (bmi < 18.5) category = 'Underweight';
                else if (bmi < 25) category = 'Normal weight';
                else if (bmi < 30) category = 'Overweight';
                else category = 'Obese';
                
                document.getElementById('bmi_result').textContent = `BMI: ${bmi.toFixed(1)} (${category})`;
            } else {
                document.getElementById('bmi_result').textContent = 'BMI: --';
            }
        }
        
        document.getElementById('weight_input').addEventListener('input', calculateBMI);
        document.getElementById('height_input').addEventListener('input', calculateBMI);
        
        // Handle responsive behavior
        function handleResize() {
            const isMobile = window.innerWidth <= 768;
            const filterTabs = document.querySelector('.filter-tabs');
            
            if (isMobile && filterTabs) {
                // Ensure tabs are responsive on mobile
                filterTabs.style.display = 'grid';
            }
        }
        
        // Initial call and on resize
        handleResize();
        window.addEventListener('resize', handleResize);
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
            <!-- <li><a href="appointment_requests.php"><i class="fas fa-clock"></i>Requests & Approvals</a></li> -->
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