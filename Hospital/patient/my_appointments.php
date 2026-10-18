<?php
session_start();
include "includes/db_connect.php";


// Check if user is logged in as a patient
if (!isset($_SESSION['patient_id']) || $_SESSION['role'] !== 'Patient') {
    header("Location: login.php");
    exit;
}

// Get patient information directly from patients table
$patient_stmt = $pdo->prepare("
    SELECT * FROM patients 
    WHERE id = ?
    LIMIT 1
");
$patient_stmt->execute([$_SESSION['patient_id']]);
$patient = $patient_stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = "Patient record not found.";
    header("Location: dashboard.php");
    exit;
}

// Handle cancellation
if (isset($_GET['action']) && $_GET['action'] == 'cancel' && isset($_GET['id'])) {
    $request_id = $_GET['id'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE appointment_requests 
            SET status = 'cancelled', 
                rejection_reason = 'Cancelled by patient',
                processed_at = NOW()
            WHERE id = ? AND patient_id = ? AND status = 'pending'
        ");
        
        $stmt->execute([$request_id, $patient['id']]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Appointment request cancelled successfully.";
        } else {
            $_SESSION['error'] = "Unable to cancel request. It may have already been processed.";
        }
        
        header("Location: my_appointments.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error cancelling request: " . $e->getMessage();
        header("Location: my_appointments.php");
        exit;
    }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$filter_date = $_GET['date'] ?? '';

// Get patient's appointment requests
$query = "
    SELECT ar.*, 
           CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
           d.specialization,
           d.department,
           CONCAT(p.first_name, ' ', p.last_name) as processed_by_name,
           CASE 
               WHEN ar.preferred_date < CURDATE() AND ar.status = 'pending' THEN 'expired'
               WHEN ar.preferred_date < CURDATE() AND ar.status IN ('approved', 'scheduled') THEN 'completed'
               ELSE ar.status
           END as display_status
    FROM appointment_requests ar
    LEFT JOIN users d ON ar.doctor_id = d.id AND d.role = 'Doctor'
    LEFT JOIN users p ON ar.processed_by = p.id
    WHERE ar.patient_id = ?
";

$params = [$patient['id']];

// Apply filters
if ($filter_status !== 'all') {
    if ($filter_status === 'active') {
        $query .= " AND ar.status IN ('pending', 'approved') AND ar.preferred_date >= CURDATE()";
    } else {
        $query .= " AND ar.status = ?";
        $params[] = $filter_status;
    }
}

if (!empty($filter_date)) {
    $query .= " AND DATE(ar.preferred_date) = ?";
    $params[] = $filter_date;
}

$query .= " ORDER BY 
    CASE ar.status
        WHEN 'pending' THEN 1
        WHEN 'approved' THEN 2
        WHEN 'rejected' THEN 3
        WHEN 'cancelled' THEN 4
        ELSE 5
    END,
    ar.preferred_date DESC,
    ar.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointment_requests = $stmt->fetchAll();

// Count requests by status
$count_query = "
    SELECT status, COUNT(*) as count 
    FROM appointment_requests 
    WHERE patient_id = ? 
    GROUP BY status
";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute([$patient['id']]);
$status_counts_raw = $count_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Set default counts if none exist
$status_counts = [
    'pending' => $status_counts_raw['pending'] ?? 0,
    'approved' => $status_counts_raw['approved'] ?? 0,
    'rejected' => $status_counts_raw['rejected'] ?? 0,
    'cancelled' => $status_counts_raw['cancelled'] ?? 0
];

// Get upcoming scheduled appointments (not requests) - using appointments table instead of appointment_requests
$scheduled_query = "
    SELECT a.*, 
           CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
           d.specialization,
           d.department
    FROM appointments a
    LEFT JOIN users d ON a.doctor_id = d.id AND d.role = 'Doctor'
    WHERE a.patient_id = ? 
    AND a.appointment_date >= CURDATE()
    AND a.status IN ('scheduled', 'confirmed')
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 5
";

$scheduled_stmt = $pdo->prepare($scheduled_query);
$scheduled_stmt->execute([$patient['id']]);
$scheduled_appointments = $scheduled_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - MediCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.875rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #64748b;
            font-size: 0.875rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .stat-card:hover, .stat-card.active {
            border-color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .stat-icon.pending { background-color: #fef3c7; color: #d97706; }
        .stat-icon.approved { background-color: #dcfce7; color: #166534; }
        .stat-icon.rejected { background-color: #fee2e2; color: #991b1b; }
        .stat-icon.cancelled { background-color: #f3f4f6; color: #6b7280; }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
        }

        /* Upcoming Section */
        .upcoming-section {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
        }

        .view-all {
            color: #3b82f6;
            text-decoration: none;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .upcoming-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .upcoming-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 0.75rem;
            text-decoration: none;
            color: inherit;
            transition: background-color 0.3s ease;
        }

        .upcoming-item:hover {
            background-color: #f1f5f9;
        }

        .upcoming-info {
            flex: 1;
        }

        .upcoming-date {
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .upcoming-doctor {
            color: #64748b;
            font-size: 0.875rem;
        }

        .upcoming-status {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            background-color: #dcfce7;
            color: #166534;
        }

        /* Filter Card */
        .filter-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .filter-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-control {
            padding: 0.625rem 0.875rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Appointment Cards */
        .appointments-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .appointment-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .appointment-header {
            padding: 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }

        .appointment-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .appointment-ref {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: normal;
            margin-left: 0.5rem;
        }

        .appointment-status {
            padding: 0.375rem 1rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending { background-color: #fef3c7; color: #d97706; }
        .status-approved { background-color: #dcfce7; color: #166534; }
        .status-rejected { background-color: #fee2e2; color: #991b1b; }
        .status-cancelled { background-color: #f3f4f6; color: #6b7280; }
        .status-expired { background-color: #fef3c7; color: #92400e; }
        .status-completed { background-color: #dbeafe; color: #1e40af; }

        .appointment-body {
            padding: 1.5rem;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }

        @media (max-width: 768px) {
            .appointment-body {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }

        .doctor-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .doctor-avatar {
            width: 4rem;
            height: 4rem;
            border-radius: 50%;
            background-color: #3b82f6;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.25rem;
        }

        .doctor-details h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .doctor-details p {
            color: #64748b;
            font-size: 0.875rem;
        }

        .appointment-details {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .detail-label {
            min-width: 120px;
            color: #64748b;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-value {
            color: #0f172a;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .appointment-notes {
            margin: 0 1.5rem 1.5rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
        }

        .notes-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .notes-content {
            color: #4b5563;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .appointment-actions {
            padding: 1rem 1.5rem 1.5rem;
            border-top: 1px solid #f1f5f9;
            display: flex;
            gap: 0.75rem;
        }

        /* Buttons */
        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2563eb;
        }

        .btn-danger {
            background-color: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .btn-outline {
            background-color: transparent;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .btn-outline:hover {
            background-color: #f9fafb;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .empty-state i {
            font-size: 3rem;
            color: #d1d5db;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #6b7280;
            margin-bottom: 1.5rem;
            max-width: 28rem;
            margin-left: auto;
            margin-right: auto;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }

        .modal-content {
            background: white;
            border-radius: 1rem;
            width: 100%;
            max-width: 28rem;
            overflow: hidden;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-actions {
            padding: 1rem 1.5rem 1.5rem;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        /* Mobile Menu Button */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 100;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 0.5rem;
            padding: 0.75rem;
            cursor: pointer;
            font-size: 1.25rem;
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
        }
    </style>
</head>
<body>

<!-- Mobile Menu Button -->
<button class="mobile-menu-btn" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<div class="main-content">
    <div class="page-header">
        <h1>My Appointments</h1>
        <p>View and manage your appointment requests and scheduled appointments</p>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Quick Stats -->
    <div class="stats-grid">
        <a href="my_appointments.php?status=all" class="stat-card <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
            <div class="stat-icon pending">
                <i class="fas fa-list"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo count($appointment_requests); ?></div>
                <div class="stat-label">Total Requests</div>
            </div>
        </a>
        
        <a href="my_appointments.php?status=pending" class="stat-card <?php echo $filter_status === 'pending' ? 'active' : ''; ?>">
            <div class="stat-icon pending">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $status_counts['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </a>
        
        <a href="my_appointments.php?status=approved" class="stat-card <?php echo $filter_status === 'approved' ? 'active' : ''; ?>">
            <div class="stat-icon approved">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $status_counts['approved']; ?></div>
                <div class="stat-label">Approved</div>
            </div>
        </a>
        
        <a href="my_appointments.php?status=rejected" class="stat-card <?php echo $filter_status === 'rejected' ? 'active' : ''; ?>">
            <div class="stat-icon rejected">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $status_counts['rejected']; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </a>
        
        <a href="my_appointments.php?status=cancelled" class="stat-card <?php echo $filter_status === 'cancelled' ? 'active' : ''; ?>">
            <div class="stat-icon cancelled">
                <i class="fas fa-ban"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $status_counts['cancelled']; ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
        </a>
    </div>

    <!-- Upcoming Scheduled Appointments -->
    <?php if (!empty($scheduled_appointments)): ?>
        <div class="upcoming-section">
            <div class="section-header">
                <h2 class="section-title">Upcoming Scheduled Appointments</h2>
                <a href="booking_history.php" class="view-all">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="upcoming-list">
                <?php foreach ($scheduled_appointments as $appointment): ?>
                    <a href="booking_history.php?view=<?php echo $appointment['id']; ?>" class="upcoming-item">
                        <div class="upcoming-info">
                            <div class="upcoming-date">
                                <?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?> 
                                at <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                            </div>
                            <div class="upcoming-doctor">
                                <?php 
                                if ($appointment['doctor_name']) {
                                    echo "With Dr. " . htmlspecialchars($appointment['doctor_name']);
                                    if ($appointment['specialization']) {
                                        echo " - " . htmlspecialchars($appointment['specialization']);
                                    }
                                } else {
                                    echo "Doctor to be assigned";
                                }
                                ?>
                            </div>
                        </div>
                        <span class="upcoming-status">
                            <?php echo ucfirst($appointment['status']); ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filter Section -->
    <div class="filter-card">
        <div class="filter-header">
            <h2 class="filter-title">Filter Appointment Requests</h2>
            <?php if ($filter_status !== 'all' || !empty($filter_date)): ?>
                <a href="my_appointments.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
            <?php endif; ?>
        </div>
        
        <form method="GET" class="filter-form">
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control" onchange="this.form.submit()">
                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active (Pending & Approved)</option>
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Preferred Date</label>
                <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($filter_date); ?>" onchange="this.form.submit()">
            </div>
            
            <div class="form-group">
                <a href="book_appointment.php" class="btn btn-primary" style="text-decoration: none;">
                    <i class="fas fa-plus"></i> New Appointment Request
                </a>
            </div>
        </form>
    </div>

    <!-- Appointment Requests List -->
    <div class="appointments-container">
        <?php if (empty($appointment_requests)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Appointment Requests Found</h3>
                <p>
                    <?php if ($filter_status !== 'all' || !empty($filter_date)): ?>
                        No appointment requests match your filter criteria.
                    <?php else: ?>
                        You haven't made any appointment requests yet.
                    <?php endif; ?>
                </p>
                <a href="book_appointment.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Book New Appointment
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($appointment_requests as $request): ?>
                <div class="appointment-card">
                    <div class="appointment-header">
                        <div>
                            <h3 class="appointment-title">
                                <?php echo htmlspecialchars(ucfirst($request['type'])); ?> Appointment
                                <span class="appointment-ref">Request #<?php echo $request['id']; ?></span>
                            </h3>
                            <div style="font-size: 0.875rem; color: #64748b; margin-top: 0.25rem;">
                                Submitted: <?php echo date('M d, Y g:i A', strtotime($request['created_at'])); ?>
                            </div>
                        </div>
                        
                        <span class="appointment-status status-<?php echo $request['display_status']; ?>">
                            <?php echo ucfirst($request['display_status']); ?>
                        </span>
                    </div>
                    
                    <div class="appointment-body">
                        <div class="doctor-info">
                            <div class="doctor-avatar">
                                <?php 
                                if ($request['doctor_name']) {
                                    $initials = strtoupper(
                                        substr($request['doctor_name'], 0, 1) . 
                                        substr(explode(' ', $request['doctor_name'])[1] ?? '', 0, 1)
                                    );
                                    echo $initials;
                                } else {
                                    echo 'DR';
                                }
                                ?>
                            </div>
                            <div class="doctor-details">
                                <h4>
                                    <?php 
                                    if ($request['doctor_name']) {
                                        echo "Dr. " . htmlspecialchars($request['doctor_name']);
                                    } else {
                                        echo "Any Available Doctor";
                                    }
                                    ?>
                                </h4>
                                <p>
                                    <?php 
                                    if ($request['department']) {
                                        echo htmlspecialchars($request['department']);
                                    } else {
                                        echo "General Practice";
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="appointment-details">
                            <div class="detail-row">
                                <span class="detail-label"><i class="far fa-calendar"></i> Preferred Date:</span>
                                <span class="detail-value">
                                    <?php echo date('F j, Y', strtotime($request['preferred_date'])); ?>
                                    (<?php echo date('l', strtotime($request['preferred_date'])); ?>)
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="far fa-clock"></i> Preferred Time:</span>
                                <span class="detail-value">
                                    <?php echo htmlspecialchars($request['preferred_time']); ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-exclamation-triangle"></i> Urgency:</span>
                                <span class="detail-value">
                                    <?php echo htmlspecialchars($request['urgency']); ?>
                                </span>
                            </div>
                            <?php if ($request['display_status'] === 'approved' && $request['processed_at']): ?>
                                <div class="detail-row">
                                    <span class="detail-label"><i class="fas fa-check"></i> Approved On:</span>
                                    <span class="detail-value">
                                        <?php echo date('M d, Y g:i A', strtotime($request['processed_at'])); ?>
                                    </span>
                                </div>
                                <?php if ($request['processed_by_name']): ?>
                                    <div class="detail-row">
                                        <span class="detail-label"><i class="fas fa-user"></i> Approved By:</span>
                                        <span class="detail-value">
                                            <?php echo htmlspecialchars($request['processed_by_name']); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($request['display_status'] === 'rejected' && $request['processed_at']): ?>
                                <div class="detail-row">
                                    <span class="detail-label"><i class="fas fa-times"></i> Rejected On:</span>
                                    <span class="detail-value">
                                        <?php echo date('M d, Y g:i A', strtotime($request['processed_at'])); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($request['notes'])): ?>
                        <div class="appointment-notes">
                            <div class="notes-label">Reason for Appointment:</div>
                            <div class="notes-content">
                                <?php echo nl2br(htmlspecialchars($request['notes'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($request['display_status'] === 'rejected' && !empty($request['rejection_reason'])): ?>
                        <div class="appointment-notes" style="background: #fef2f2; border-color: #fecaca;">
                            <div class="notes-label" style="color: #991b1b;">
                                <i class="fas fa-exclamation-circle"></i> Rejection Reason:
                            </div>
                            <div class="notes-content" style="color: #991b1b;">
                                <?php echo nl2br(htmlspecialchars($request['rejection_reason'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($request['display_status'] === 'expired'): ?>
                        <div class="appointment-notes" style="background: #fffbeb; border-color: #fde68a;">
                            <div class="notes-label" style="color: #92400e;">
                                <i class="fas fa-exclamation-triangle"></i> Note:
                            </div>
                            <div class="notes-content" style="color: #92400e;">
                                This request has expired because the preferred date has passed. Please submit a new request if you still need an appointment.
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="appointment-actions">
                        <?php if ($request['display_status'] === 'pending' && strtotime($request['preferred_date']) >= strtotime(date('Y-m-d'))): ?>
                            <button class="btn btn-danger" onclick="confirmCancel(<?php echo $request['id']; ?>)">
                                <i class="fas fa-times"></i> Cancel Request
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($request['display_status'] === 'approved'): ?>
                            <a href="booking_history.php" class="btn btn-primary">
                                <i class="fas fa-calendar-check"></i> View Scheduled Appointment
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($request['display_status'] === 'rejected' || $request['display_status'] === 'cancelled' || $request['display_status'] === 'expired'): ?>
                            <a href="book_appointment.php" class="btn btn-primary">
                                <i class="fas fa-redo"></i> Request New Appointment
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Cancel Confirmation Modal -->
<div id="cancelModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Cancel Appointment Request</h3>
        </div>
        <div class="modal-body" id="cancelModalBody">
            <p>Are you sure you want to cancel this appointment request?</p>
            <p>This action cannot be undone.</p>
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="closeModal('cancelModal')">No, Keep It</button>
            <a href="#" id="cancelConfirmBtn" class="btn btn-danger">Yes, Cancel Request</a>
        </div>
    </div>
</div>

<script>
    // Cancel confirmation
    function confirmCancel(requestId) {
        document.getElementById('cancelModalBody').innerHTML = `
            <p>Are you sure you want to cancel this appointment request?</p>
            <p>This action cannot be undone.</p>
        `;
        
        document.getElementById('cancelConfirmBtn').href = `my_appointments.php?action=cancel&id=${requestId}`;
        document.getElementById('cancelModal').style.display = 'flex';
    }

    // Close modal
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    }

    // Mobile sidebar toggle
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        if (sidebar.style.display === 'none' || !sidebar.style.display) {
            sidebar.style.display = 'block';
            mainContent.style.marginLeft = '280px';
        } else {
            sidebar.style.display = 'none';
            mainContent.style.marginLeft = '0';
        }
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        if (window.innerWidth <= 1024) {
            sidebar.style.display = 'none';
            mainContent.style.marginLeft = '0';
        } else {
            sidebar.style.display = 'block';
            mainContent.style.marginLeft = '280px';
        }
    });
    
    // Initialize on load
    document.addEventListener('DOMContentLoaded', function() {
        if (window.innerWidth <= 1024) {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                sidebar.style.display = 'none';
            }
        }
        
        // Auto-submit status filter
        const statusSelect = document.querySelector('select[name="status"]');
        if (statusSelect) {
            statusSelect.addEventListener('change', function() {
                this.form.submit();
            });
        }
        
        // Auto-submit date filter
        const dateInput = document.querySelector('input[name="date"]');
        if (dateInput) {
            dateInput.addEventListener('change', function() {
                this.form.submit();
            });
        }
    });
</script>

</body>
</html>


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
          <a href="patient_profile.php" class="menu-item">
            <i class="fas fa-user-injured"></i>
            <span>User Profile</span>
          </a>
        </li>
        
         <li>
          <a href="patient_medical_records.php" class="menu-item">
            <i class="fas fa-file-medical"></i>
            <span>Electronic Medical Records</span>
          </a>
        </li>

        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-calendar-check"></i>
            <span>Appointments</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="book_appointment.php"><i class="fas fa-plus-circle"></i>Schedule Appointment</a></li>
            <li><a href="my_appointments.php"><i class="fas fa-calendar"></i>Appointment Status</a></li>
            <li><a href="booking_history.php"><i class="fas fa-calendar-week"></i>Appointment History</a></li>
          </ul>
        </li>

    

    <div class="nav-section">
      <div class="section-label">Operations</div>
      <ul class="sidebar-menu">

        <li>
          <a href="lab_results.php" class="menu-item">
            <i class="fas fa-chart-line"></i>
            <span>Reports</span>
          </a>
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
        <span class="user-name">Patient Dashboard</span>
       
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