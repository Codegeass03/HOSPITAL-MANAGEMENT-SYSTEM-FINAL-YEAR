<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

// Check if user is logged in as a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Patient') {
    header("Location: login.php");
    exit;
}

// Get patient ID from session
$patient_id = $_SESSION['user_id'];

// Get patient info
$patient_stmt = $pdo->prepare("
    SELECT p.* 
    FROM patients p 
    WHERE p.id = ? OR EXISTS (
        SELECT 1 FROM users u 
        WHERE u.id = ? AND (u.phone = p.phone OR u.email = p.email)
    )
    LIMIT 1
");
$patient_stmt->execute([$patient_id, $patient_id]);
$patient = $patient_stmt->fetch();

if (!$patient) {
    $patient_stmt2 = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $patient_stmt2->execute([$patient_id]);
    $patient = $patient_stmt2->fetch();
    
    if (!$patient) {
        $_SESSION['error'] = "Patient record not found.";
        header("Location: patient_dashboard.php");
        exit;
    }
}

// Handle cancel appointment
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['id'])) {
    $appointment_id = $_GET['id'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET status = 'cancelled', 
                cancelled_at = NOW(),
                cancelled_by = ?
            WHERE id = ? AND patient_id = ?
        ");
        
        $stmt->execute([$patient['id'], $appointment_id, $patient['id']]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Appointment cancelled successfully.";
        } else {
            $_SESSION['error'] = "Appointment not found or you don't have permission to cancel it.";
        }
        
        header("Location: appointment_history.php");
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error cancelling appointment: " . $e->getMessage();
        header("Location: appointment_history.php");
        exit;
    }
}

// Handle reschedule request
if (isset($_GET['action']) && $_GET['action'] === 'reschedule' && isset($_GET['id'])) {
    $appointment_id = $_GET['id'];
    
    try {
        // Get appointment details
        $stmt = $pdo->prepare("
            SELECT a.*, 
                   CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                   dp.specialty as doctor_specialty
            FROM appointments a
            LEFT JOIN users d ON a.doctor_id = d.id
            LEFT JOIN doctor_profiles dp ON d.id = dp.user_id
            WHERE a.id = ? AND a.patient_id = ?
        ");
        
        $stmt->execute([$appointment_id, $patient['id']]);
        $appointment = $stmt->fetch();
        
        if ($appointment) {
            // Add to waiting list for rescheduling
            $waiting_stmt = $pdo->prepare("
                INSERT INTO appointment_waiting_list 
                (patient_id, doctor_id, preferred_date, preferred_time, reason, priority, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'high', 'waiting', NOW())
            ");
            
            $reason = "Reschedule request for appointment #" . $appointment_id . ". " . 
                     "Original: " . $appointment['appointment_date'] . " " . $appointment['appointment_time'];
            
            $waiting_stmt->execute([
                $patient['id'],
                $appointment['doctor_id'],
                $appointment['appointment_date'],
                $appointment['appointment_time'],
                $reason
            ]);
            
            // Mark appointment as rescheduled
            $update_stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = 'rescheduled', 
                    rescheduled_at = NOW()
                WHERE id = ?
            ");
            
            $update_stmt->execute([$appointment_id]);
            
            $_SESSION['success'] = "Reschedule request submitted. Our staff will contact you with new appointment options.";
        } else {
            $_SESSION['error'] = "Appointment not found.";
        }
        
        header("Location: appointment_history.php");
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error processing reschedule request: " . $e->getMessage();
        header("Location: appointment_history.php");
        exit;
    }
}

// Get filter parameters
$filter_date = $_GET['filter_date'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_type = $_GET['filter_type'] ?? '';

// Build query for appointments
$query = "
    SELECT a.*, 
           CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
           dp.specialty as doctor_specialty,
          
           CASE 
               WHEN a.appointment_date < CURDATE() THEN 'past'
               WHEN a.appointment_date = CURDATE() THEN 'today'
               ELSE 'future'
           END as time_category,
           CASE 
               WHEN a.status = 'completed' THEN 'completed'
               WHEN a.status IN ('cancelled', 'no_show') THEN 'cancelled'
               WHEN a.status IN ('scheduled', 'confirmed') AND a.appointment_date >= CURDATE() THEN 'upcoming'
               ELSE 'past'
           END as display_status
    FROM appointments a
    LEFT JOIN users d ON a.doctor_id = d.id
    LEFT JOIN doctor_profiles dp ON d.id = dp.user_id
    LEFT JOIN users u ON d.id = u.id
    WHERE a.patient_id = ?
";

$params = [$patient['id']];

// Apply filters
if (!empty($filter_date)) {
    $query .= " AND a.appointment_date = ?";
    $params[] = $filter_date;
}

if (!empty($filter_status)) {
    if ($filter_status === 'upcoming') {
        $query .= " AND a.status IN ('scheduled', 'confirmed') AND a.appointment_date >= CURDATE()";
    } elseif ($filter_status === 'past') {
        $query .= " AND (a.appointment_date < CURDATE() OR a.status IN ('completed', 'cancelled', 'no_show'))";
    } else {
        $query .= " AND a.status = ?";
        $params[] = $filter_status;
    }
}

if (!empty($filter_type)) {
    $query .= " AND a.appointment_type = ?";
    $params[] = $filter_type;
}

$query .= " ORDER BY 
    CASE 
        WHEN a.appointment_date >= CURDATE() THEN 0
        ELSE 1
    END,
    a.appointment_date ASC,
    a.appointment_time ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Get appointment types for filter
$types_stmt = $pdo->prepare("
    SELECT DISTINCT appointment_type 
    FROM appointments 
    WHERE patient_id = ? AND appointment_type IS NOT NULL AND appointment_type != ''
    ORDER BY appointment_type
");
$types_stmt->execute([$patient['id']]);
$appointment_types = $types_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

// Count statistics
$upcoming_count = 0;
$today_count = 0;
$past_count = 0;
foreach ($appointments as $appt) {
    if ($appt['display_status'] === 'upcoming') {
        $upcoming_count++;
    } elseif ($appt['appointment_date'] == date('Y-m-d')) {
        $today_count++;
    } elseif ($appt['display_status'] === 'past' || $appt['display_status'] === 'completed' || $appt['display_status'] === 'cancelled') {
        $past_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment History - MediCare HMS</title>
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

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .main-content {
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

        /* Stats Cards */
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
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 640px) {
            .stat-card {
                padding: 1rem;
            }
        }

        .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.upcoming {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .stat-icon.today {
            background: #fef3c7;
            color: #d97706;
        }

        .stat-icon.past {
            background: #f3f4f6;
            color: #6b7280;
        }

        .stat-info {
            flex: 1;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #64748b;
        }

        /* Filter Section */
        .filter-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 640px) {
            .filter-card {
                padding: 1rem;
            }
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .filter-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #0f172a;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        @media (max-width: 640px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
        }

        .form-control {
            padding: 0.625rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            width: 100%;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #d1d5db;
            color: #374151;
        }

        .btn-outline:hover {
            background: #f9fafb;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        /* Appointments List */
        .appointments-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .appointment-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
        }

        @media (max-width: 640px) {
            .appointment-card {
                padding: 1rem;
            }
        }

        .appointment-card:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .appointment-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .appointment-ref {
            font-size: 0.875rem;
            color: #64748b;
        }

        .appointment-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-upcoming {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-today {
            background: #fef3c7;
            color: #d97706;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-rescheduled {
            background: #f3e8ff;
            color: #7c3aed;
        }

        .status-scheduled {
            background: #e0f2fe;
            color: #0369a1;
        }

        .appointment-body {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 640px) {
            .appointment-body {
                grid-template-columns: 1fr;
            }
        }

        .doctor-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .doctor-avatar {
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            background: #3b82f6;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 600;
            overflow: hidden;
        }

        .doctor-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .doctor-details h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .doctor-details p {
            font-size: 0.875rem;
            color: #64748b;
        }

        .appointment-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .detail-label {
            color: #64748b;
            min-width: 100px;
        }

        .detail-value {
            color: #0f172a;
            font-weight: 500;
        }

        .appointment-notes {
            background: #f8fafc;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
        }

        .notes-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .notes-content {
            font-size: 0.875rem;
            color: #374151;
            line-height: 1.5;
        }

        .appointment-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        @media (max-width: 640px) {
            .appointment-actions {
                flex-direction: column;
            }
            
            .appointment-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #64748b;
        }

        @media (max-width: 640px) {
            .empty-state {
                padding: 2rem 1rem;
            }
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }

        .empty-state h3 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #64748b;
        }

        .empty-state p {
            font-size: 0.875rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* Mobile Menu Button */
        @media (max-width: 1024px) {
            .mobile-menu-btn {
                display: block;
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 1001;
                background: white;
                border: none;
                border-radius: 0.375rem;
                padding: 0.5rem;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                cursor: pointer;
            }
        }

        /* Confirmation Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal-content {
            background: white;
            border-radius: 0.75rem;
            padding: 2rem;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            margin-bottom: 1rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
        }

        .modal-body {
            margin: 1.5rem 0;
            color: #64748b;
            line-height: 1.5;
        }

        .modal-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }

        /* View Details Modal */
        .details-modal {
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .details-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .details-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        @media (max-width: 640px) {
            .details-grid {
                grid-template-columns: 1fr;
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
        <h1>Appointment History</h1>
        <p>View and manage all your past and upcoming appointments</p>
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

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon upcoming">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $upcoming_count; ?></div>
                <div class="stat-label">Upcoming</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon today">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $today_count; ?></div>
                <div class="stat-label">Today</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon past">
                <i class="fas fa-history"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $past_count; ?></div>
                <div class="stat-label">Past</div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-card">
        <div class="filter-header">
            <h2 class="filter-title">Filter Appointments</h2>
            <?php if ($filter_date || $filter_status || $filter_type): ?>
                <a href="appointment_history.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
            <?php endif; ?>
        </div>
        
        <form method="GET" class="filter-form">
            <div class="form-group">
                <label class="form-label">Date</label>
                <input type="date" name="filter_date" class="form-control" value="<?php echo htmlspecialchars($filter_date); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="filter_status" class="form-control">
                    <option value="">All Status</option>
                    <option value="upcoming" <?php echo $filter_status === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                    <option value="past" <?php echo $filter_status === 'past' ? 'selected' : ''; ?>>Past</option>
                    <option value="scheduled" <?php echo $filter_status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="confirmed" <?php echo $filter_status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Type</label>
                <select name="filter_type" class="form-control">
                    <option value="">All Types</option>
                    <?php foreach ($appointment_types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $filter_type === $type ? 'selected' : ''; ?>>
                            <?php echo ucfirst(htmlspecialchars($type)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Appointments List -->
    <div class="appointments-container">
        <?php if (empty($appointments)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Appointments Found</h3>
                <p>
                    <?php if ($filter_date || $filter_status || $filter_type): ?>
                        No appointments match your filter criteria. Try adjusting your filters.
                    <?php else: ?>
                        You don't have any appointments yet. Book your first appointment to get started.
                    <?php endif; ?>
                </p>
                <?php if (!$filter_date && !$filter_status && !$filter_type): ?>
                    <a href="book_appointment.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-plus"></i> Book New Appointment
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($appointments as $appointment): ?>
                <div class="appointment-card">
                    <div class="appointment-header">
                        <div>
                            <h3 class="appointment-title">
                                <?php echo htmlspecialchars($appointment['appointment_type'] ?? 'Appointment'); ?>
                                <span class="appointment-ref">#<?php echo htmlspecialchars($appointment['appointment_number'] ?? $appointment['id']); ?></span>
                            </h3>
                        </div>
                        
                        <span class="appointment-status status-<?php echo $appointment['display_status']; ?>">
                            <?php 
                            if ($appointment['appointment_date'] == date('Y-m-d')) {
                                echo 'Today';
                            } elseif ($appointment['display_status'] === 'upcoming') {
                                echo 'Upcoming';
                            } else {
                                echo ucfirst($appointment['status']);
                            }
                            ?>
                        </span>
                    </div>
                    
                    <div class="appointment-body">
                        <div class="doctor-info">
                            <div class="doctor-avatar">
                                <?php if (!empty($appointment['doctor_image'])): ?>
                                    <img src="../uploads/profiles/<?php echo htmlspecialchars($appointment['doctor_image']); ?>" alt="Doctor">
                                <?php else: ?>
                                    <?php 
                                    $initials = strtoupper(
                                        substr($appointment['doctor_name'] ?? 'D', 0, 1) . 
                                        substr(explode(' ', $appointment['doctor_name'] ?? 'R')[1] ?? '', 0, 1)
                                    );
                                    echo $initials;
                                    ?>
                                <?php endif; ?>
                            </div>
                            <div class="doctor-details">
                                <h4>Dr. <?php echo htmlspecialchars($appointment['doctor_name'] ?? 'Not Assigned'); ?></h4>
                                <p><?php echo htmlspecialchars($appointment['doctor_specialty'] ?? 'General Practice'); ?></p>
                            </div>
                        </div>
                        
                        <div class="appointment-details">
                            <div class="detail-row">
                                <span class="detail-label"><i class="far fa-calendar"></i> Date:</span>
                                <span class="detail-value">
                                    <?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?>
                                    (<?php echo date('l', strtotime($appointment['appointment_date'])); ?>)
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="far fa-clock"></i> Time:</span>
                                <span class="detail-value">
                                    <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-map-marker-alt"></i> Location:</span>
                                <span class="detail-value">Main Hospital </span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($appointment['reason']) || !empty($appointment['notes'])): ?>
                        <div class="appointment-notes">
                            <div class="notes-label">Notes:</div>
                            <div class="notes-content">
                                <?php echo nl2br(htmlspecialchars($appointment['reason'] . ($appointment['notes'] ? "\n\n" . $appointment['notes'] : ''))); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="appointment-actions">
                        <!-- View Details Button (Always shown) -->
                        <button class="btn btn-outline" onclick="showAppointmentDetails(<?php echo htmlspecialchars(json_encode($appointment)); ?>)">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                        
                        <?php 
                        $is_upcoming = in_array($appointment['status'], ['scheduled', 'confirmed']) && 
                                     strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']) > time();
                        $is_today = $appointment['appointment_date'] == date('Y-m-d');
                        ?>
                        
                        <?php if ($is_upcoming): ?>
                            <!-- Reschedule Button (Future appointments only) -->
                            <button class="btn btn-success" onclick="confirmReschedule(<?php echo $appointment['id']; ?>, '<?php echo htmlspecialchars($appointment['appointment_type']); ?>')">
                                <i class="fas fa-calendar-alt"></i> Reschedule
                            </button>
                            
                            <!-- Cancel Button (Future appointments only) -->
                            <button class="btn btn-danger" onclick="confirmCancel(<?php echo $appointment['id']; ?>, '<?php echo htmlspecialchars($appointment['appointment_type']); ?>')">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        <?php elseif ($is_today): ?>
                            <!-- Check-in Button (Today's appointments) -->
                            <button class="btn btn-primary">
                                <i class="fas fa-check-in"></i> Check In
                            </button>
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
            <h3 class="modal-title">Cancel Appointment</h3>
        </div>
        <div class="modal-body" id="cancelModalBody">
            <!-- Content will be inserted by JavaScript -->
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="closeModal('cancelModal')">No, Keep It</button>
            <a href="#" id="cancelConfirmBtn" class="btn btn-danger">Yes, Cancel Appointment</a>
        </div>
    </div>
</div>

<!-- Reschedule Confirmation Modal -->
<div id="rescheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Request Reschedule</h3>
        </div>
        <div class="modal-body" id="rescheduleModalBody">
            <!-- Content will be inserted by JavaScript -->
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="closeModal('rescheduleModal')">Cancel</button>
            <a href="#" id="rescheduleConfirmBtn" class="btn btn-success">Request Reschedule</a>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div id="detailsModal" class="modal">
    <div class="modal-content details-modal">
        <div class="modal-header">
            <h3 class="modal-title">Appointment Details</h3>
        </div>
        <div class="modal-body" id="detailsModalBody">
            <!-- Content will be inserted by JavaScript -->
        </div>
        <div class="modal-actions">
            <button class="btn btn-primary" onclick="closeModal('detailsModal')">Close</button>
        </div>
    </div>
</div>

<script>
    // Cancel confirmation
    function confirmCancel(appointmentId, appointmentType) {
        document.getElementById('cancelModalBody').innerHTML = `
            <p>Are you sure you want to cancel this appointment?</p>
            <p><strong>Appointment Type:</strong> ${appointmentType}</p>
            <p>Cancelling less than 24 hours before your appointment may incur a cancellation fee.</p>
            <p><em>This action cannot be undone.</em></p>
        `;
        
        document.getElementById('cancelConfirmBtn').href = `appointment_history.php?action=cancel&id=${appointmentId}`;
        document.getElementById('cancelModal').style.display = 'flex';
    }

    // Reschedule confirmation
    function confirmReschedule(appointmentId, appointmentType) {
        document.getElementById('rescheduleModalBody').innerHTML = `
            <p>Request to reschedule this appointment?</p>
            <p><strong>Appointment Type:</strong> ${appointmentType}</p>
            <p>Our staff will contact you with available time slots for rescheduling.</p>
            <p>You can also call our scheduling department at (555) 123-4567.</p>
        `;
        
        document.getElementById('rescheduleConfirmBtn').href = `appointment_history.php?action=reschedule&id=${appointmentId}`;
        document.getElementById('rescheduleModal').style.display = 'flex';
    }

    // Show appointment details
    function showAppointmentDetails(appointment) {
        const appointmentDate = new Date(appointment.appointment_date + 'T' + appointment.appointment_time);
        const formattedDate = appointmentDate.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        const formattedTime = appointmentDate.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit'
        });
        
        let statusBadge = '';
        if (appointment.appointment_date === '<?php echo date("Y-m-d"); ?>') {
            statusBadge = '<span class="appointment-status status-today" style="display: inline-block;">Today</span>';
        } else if (appointment.display_status === 'upcoming') {
            statusBadge = '<span class="appointment-status status-upcoming" style="display: inline-block;">Upcoming</span>';
        } else {
            statusBadge = `<span class="appointment-status status-${appointment.display_status}" style="display: inline-block;">${appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1)}</span>`;
        }
        
        document.getElementById('detailsModalBody').innerHTML = `
            <div class="details-section">
                <h4 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Appointment Information
                </h4>
                <div class="details-grid">
                    <div>
                        <div class="detail-label">Reference Number</div>
                        <div class="detail-value">#${appointment.appointment_number || appointment.id}</div>
                    </div>
                    <div>
                        <div class="detail-label">Type</div>
                        <div class="detail-value">${appointment.appointment_type || 'Consultation'}</div>
                    </div>
                    <div>
                        <div class="detail-label">Status</div>
                        <div class="detail-value">${statusBadge}</div>
                    </div>
                    <div>
                        <div class="detail-label">Booked On</div>
                        <div class="detail-value">${new Date(appointment.created_at).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric'
                        })}</div>
                    </div>
                </div>
            </div>
            
            <div class="details-section">
                <h4 class="section-title">
                    <i class="fas fa-calendar-alt"></i>
                    Date & Time
                </h4>
                <div class="details-grid">
                    <div>
                        <div class="detail-label">Date</div>
                        <div class="detail-value">${formattedDate}</div>
                    </div>
                    <div>
                        <div class="detail-label">Time</div>
                        <div class="detail-value">${formattedTime}</div>
                    </div>
                    <div>
                        <div class="detail-label">Duration</div>
                        <div class="detail-value">${appointment.duration || '30 minutes'}</div>
                    </div>
                    <div>
                        <div class="detail-label">Location</div>
                        <div class="detail-value">${appointment.room_number ? `Main Hospital - Room ${appointment.room_number}` : 'Main Hospital'}</div>
                    </div>
                </div>
            </div>
            
            <div class="details-section">
                <h4 class="section-title">
                    <i class="fas fa-user-md"></i>
                    Doctor Information
                </h4>
                <div class="details-grid">
                    <div>
                        <div class="detail-label">Doctor</div>
                        <div class="detail-value">Dr. ${appointment.doctor_name || 'To be assigned'}</div>
                    </div>
                    <div>
                        <div class="detail-label">Specialty</div>
                        <div class="detail-value">${appointment.doctor_specialty || 'General Practice'}</div>
                    </div>
                    <div>
                        <div class="detail-label">Department</div>
                        <div class="detail-value">${appointment.department || 'Outpatient'}</div>
                    </div>
                </div>
            </div>
            
            ${appointment.reason || appointment.notes ? `
            <div class="details-section">
                <h4 class="section-title">
                    <i class="fas fa-sticky-note"></i>
                    Notes
                </h4>
                <div style="background: #f8fafc; padding: 1rem; border-radius: 0.5rem;">
                    <p style="margin: 0; color: #374151; line-height: 1.6;">
                        ${(appointment.reason || '') + (appointment.notes ? '\n\n' + appointment.notes : '')}
                    </p>
                </div>
            </div>
            ` : ''}
            
            ${appointment.status === 'completed' && (appointment.diagnosis || appointment.prescription || appointment.follow_up_date) ? `
            <div class="details-section">
                <h4 class="section-title">
                    <i class="fas fa-file-medical"></i>
                    Visit Summary
                </h4>
                <div class="details-grid">
                    ${appointment.diagnosis ? `
                    <div>
                        <div class="detail-label">Diagnosis</div>
                        <div class="detail-value">${appointment.diagnosis}</div>
                    </div>
                    ` : ''}
                    
                    ${appointment.prescription ? `
                    <div>
                        <div class="detail-label">Prescription</div>
                        <div class="detail-value">${appointment.prescription}</div>
                    </div>
                    ` : ''}
                    
                    ${appointment.follow_up_date ? `
                    <div>
                        <div class="detail-label">Follow-up Date</div>
                        <div class="detail-value">${new Date(appointment.follow_up_date).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric'
                        })}</div>
                    </div>
                    ` : ''}
                </div>
            </div>
            ` : ''}
        `;
        
        document.getElementById('detailsModal').style.display = 'flex';
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
        
        // Show success message if in URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('message')) {
            const message = urlParams.get('message');
            if (message === 'cancelled') {
                alert('Appointment cancelled successfully.');
                // Remove message from URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }
    });
</script>

</body>
</html>