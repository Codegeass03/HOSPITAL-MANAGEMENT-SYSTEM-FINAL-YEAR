<?php
session_start();
include "includes/db_connect.php";

// Add this helper function at the top of your PHP file
function formatTime($timeString) {
    if (empty($timeString)) {
        return 'Not specified';
    }
    
    // Check if it's already a time format (HH:MM:SS or HH:MM)
    if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $timeString)) {
        $timeParts = explode(':', $timeString);
        $hour = intval($timeParts[0]);
        $minute = $timeParts[1];
        $period = $hour >= 12 ? 'PM' : 'AM';
        $hour12 = $hour % 12;
        $hour12 = $hour12 == 0 ? 12 : $hour12;
        return sprintf('%d:%02d %s', $hour12, $minute, $period);
    }
    
    // If it's the old ENUM value, convert to display format
    switch(strtolower($timeString)) {
        case 'morning':
            return 'Morning (8:00 AM - 12:00 PM)';
        case 'afternoon':
            return 'Afternoon (12:00 PM - 5:00 PM)';
        case 'evening':
            return 'Evening (5:00 PM - 8:00 PM)';
        default:
            return htmlspecialchars($timeString);
    }
}

// Get current tab
$tab = $_GET['tab'] ?? 'pending';

// Fetch consultation services from services table for the approve modal
$services_stmt = $pdo->prepare("
    SELECT id, name, price, description 
    FROM services 
    WHERE category = 'Consultation' AND is_active = 1 
    ORDER BY price ASC
");
$services_stmt->execute();
$consultation_services = $services_stmt->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['request_id'])) {
        $request_id = $_POST['request_id'];
        $action = $_POST['action'];
        $reason = $_POST['reason'] ?? '';
        $doctor_id = $_POST['doctor_id'] ?? null;
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_time = $_POST['appointment_time'] ?? '';
        $service_id = $_POST['service_id'] ?? null;
        $consultation_fee = $_POST['consultation_fee'] ?? 0;
        
        // For reschedule action, combine date and time
        if ($action === 'reschedule') {
            if (!empty($appointment_date) && !empty($appointment_time)) {
                $appointment_datetime = $appointment_date . ' ' . $appointment_time . ':00';
            } else {
                $appointment_datetime = '';
            }
        } else {
            // For approve action, use the datetime field
            $appointment_datetime = $_POST['appointment_datetime'] ?? '';
        }
        
        if ($action === 'approve') {
            if (empty($doctor_id) || empty($appointment_datetime)) {
                $_SESSION['error'] = "Please select a doctor and appointment time.";
                header("Location: appointment_requests.php?tab=$tab");
                exit;
            }
            
            try {
                $pdo->beginTransaction();
                
                // Get request details
                $request_stmt = $pdo->prepare("
                    SELECT * FROM appointment_requests WHERE id = ?
                ");
                $request_stmt->execute([$request_id]);
                $request = $request_stmt->fetch();
                
                if ($request) {
                    // Convert datetime to separate date and time
                    $appointment_date = date('Y-m-d', strtotime($appointment_datetime));
                    $appointment_time = date('H:i:s', strtotime($appointment_datetime));
                    
                    // Generate appointment number
                    $appointment_number = 'APT-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                    
                    // Create actual appointment from the request with service details
                    $appointment_stmt = $pdo->prepare("
                        INSERT INTO appointments 
                        (appointment_number, patient_id, doctor_id, appointment_date, appointment_time, 
                         appointment_type, service_id, consultation_fee, reason, notes, status, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, NOW())
                    ");
                    
                    $appointment_stmt->execute([
                        $appointment_number,
                        $request['patient_id'],
                        $doctor_id,
                        $appointment_date,
                        $appointment_time,
                        $request['type'],
                        $service_id,
                        $consultation_fee,
                        $request['notes'],
                        "Approved from request #" . $request_id,
                        $_SESSION['user_id']
                    ]);
                    
                    // Get the inserted appointment ID
                    $appointment_id = $pdo->lastInsertId();
                    
                    // Update the appointment request with service details and mark as approved
                    $update_request_stmt = $pdo->prepare("
                        UPDATE appointment_requests 
                        SET status = 'approved',
                            service_id = ?,
                            consultation_fee = ?,
                            doctor_id = ?,
                            processed_by = ?, 
                            processed_at = NOW() 
                        WHERE id = ?
                    ");
                    $update_request_stmt->execute([
                        $service_id,
                        $consultation_fee,
                        $doctor_id,
                        $_SESSION['user_id'], 
                        $request_id
                    ]);
                    
                    // Remove from waiting list if exists
                    $waiting_stmt = $pdo->prepare("
                        UPDATE appointment_waiting_list 
                        SET status = 'scheduled' 
                        WHERE patient_id = ? AND status = 'waiting'
                    ");
                    $waiting_stmt->execute([$request['patient_id']]);
                    
                    // Create billing record automatically
                    $billing_stmt = $pdo->prepare("
                        INSERT INTO billing 
                        (patient_id, appointment_id, total_amount, paid_amount, balance_amount, status, created_at)
                        VALUES (?, ?, ?, 0, ?, 'unpaid', NOW())
                    ");
                    $billing_stmt->execute([
                        $request['patient_id'],
                        $appointment_id,
                        $consultation_fee,
                        $consultation_fee
                    ]);
                }
                
                $pdo->commit();
                $_SESSION['success'] = "Appointment request approved and scheduled successfully. Consultation fee: Ksh " . number_format($consultation_fee, 2);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error'] = "Error approving appointment: " . $e->getMessage();
            }
            
        } elseif ($action === 'reject') {
            // Reject the request
            $stmt = $pdo->prepare("
                UPDATE appointment_requests 
                SET status = 'rejected', 
                    rejection_reason = ?,
                    processed_by = ?, 
                    processed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$reason, $_SESSION['user_id'], $request_id]);
            
            // Also remove from waiting list
            $waiting_stmt = $pdo->prepare("
                UPDATE appointment_waiting_list 
                SET status = 'cancelled' 
                WHERE patient_id = (SELECT patient_id FROM appointment_requests WHERE id = ?)
                AND status = 'waiting'
            ");
            $waiting_stmt->execute([$request_id]);
            
            $_SESSION['success'] = "Appointment request rejected successfully.";
        } elseif ($action === 'reschedule') {
            // Reschedule the appointment
            if (empty($appointment_date) || empty($appointment_time)) {
                $_SESSION['error'] = "Please select a new appointment date and time.";
                header("Location: appointment_requests.php?tab=$tab");
                exit;
            }
            
            try {
                $pdo->beginTransaction();
                
                // Get the appointment details from appointment_requests table
                $stmt = $pdo->prepare("
                    SELECT ar.*, 
                           (SELECT a.id 
                            FROM appointments a 
                            WHERE a.patient_id = ar.patient_id 
                              AND a.status IN ('scheduled', 'confirmed')
                            ORDER BY a.created_at DESC 
                            LIMIT 1) as appointment_id,
                           (SELECT a.appointment_date 
                            FROM appointments a 
                            WHERE a.patient_id = ar.patient_id 
                              AND a.status IN ('scheduled', 'confirmed')
                            ORDER BY a.created_at DESC 
                            LIMIT 1) as appointment_date,
                           (SELECT a.appointment_time 
                            FROM appointments a 
                            WHERE a.patient_id = ar.patient_id 
                              AND a.status IN ('scheduled', 'confirmed')
                            ORDER BY a.created_at DESC 
                            LIMIT 1) as appointment_time
                    FROM appointment_requests ar
                    WHERE ar.id = ?
                ");
                $stmt->execute([$request_id]);
                $appointment = $stmt->fetch();
                
                if ($appointment) {
                    // Convert time to proper format
                    $new_date = $appointment_date;
                    $new_time = $appointment_time . ':00';
                    $new_doctor_id = $doctor_id ?: $appointment['doctor_id'];
                    
                    if ($appointment['appointment_id']) {
                        // Update existing appointment
                        $update_stmt = $pdo->prepare("
                            UPDATE appointments 
                            SET appointment_date = ?, 
                                appointment_time = ?,
                                doctor_id = ?,
                                status = 'rescheduled'
                            WHERE id = ?
                        ");
                        $update_stmt->execute([
                            $new_date,
                            $new_time,
                            $new_doctor_id,
                            $appointment['appointment_id']
                        ]);
                    } else {
                        // Create new appointment if doesn't exist
                        $appointment_stmt = $pdo->prepare("
                            INSERT INTO appointments 
                            (appointment_number, patient_id, doctor_id, appointment_date, appointment_time, 
                             appointment_type, service_id, consultation_fee, reason, notes, status, created_by, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, NOW())
                        ");
                        
                        // Generate appointment number
                        $appointment_number = 'APT-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                        
                        $appointment_stmt->execute([
                            $appointment_number,
                            $appointment['patient_id'],
                            $new_doctor_id,
                            $new_date,
                            $new_time,
                            $appointment['type'],
                            $appointment['service_id'],
                            $appointment['consultation_fee'],
                            $appointment['notes'],
                            "Rescheduled from request #" . $request_id,
                            $_SESSION['user_id']
                        ]);
                    }
                    
                    // Update the appointment request with new preferred date/time
                    $update_request_stmt = $pdo->prepare("
                        UPDATE appointment_requests 
                        SET preferred_date = ?,
                            preferred_time = ?,
                            doctor_id = ?
                        WHERE id = ?
                    ");
                    $update_request_stmt->execute([
                        $new_date,
                        $appointment_time,
                        $new_doctor_id,
                        $request_id
                    ]);
                }
                
                $pdo->commit();
                $_SESSION['success'] = "Appointment rescheduled successfully.";
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error'] = "Error rescheduling appointment: " . $e->getMessage();
            }
        }
        
        header("Location: appointment_requests.php?tab=$tab");
        exit;
    }
}

// ============================================================================
// FIXED FUNCTION - Removes duplicate entries caused by multiple appointments
// ============================================================================
function getAppointmentRequests($pdo, $status) {
    $sql = "
        SELECT ar.*, 
               CONCAT(p.first_name, ' ', p.last_name) as patient_name,
               p.phone as patient_phone,
               CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
               u.first_name as processed_by_name,
               s.name as service_name,
               s.price as service_price,
               (SELECT a.appointment_date 
                FROM appointments a 
                WHERE a.patient_id = ar.patient_id 
                  AND a.status IN ('scheduled', 'confirmed')
                ORDER BY a.created_at DESC 
                LIMIT 1) as scheduled_date,
               (SELECT a.appointment_time 
                FROM appointments a 
                WHERE a.patient_id = ar.patient_id 
                  AND a.status IN ('scheduled', 'confirmed')
                ORDER BY a.created_at DESC 
                LIMIT 1) as scheduled_time
        FROM appointment_requests ar
        LEFT JOIN patients p ON ar.patient_id = p.id
        LEFT JOIN users d ON ar.doctor_id = d.id
        LEFT JOIN users u ON ar.processed_by = u.id
        LEFT JOIN services s ON ar.service_id = s.id
        WHERE ar.status = ?
        ORDER BY 
            CASE WHEN ar.urgency = 'High' THEN 1
                 WHEN ar.urgency = 'Medium' THEN 2
                 ELSE 3
            END,
            ar.preferred_date,
            ar.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status]);
    return $stmt->fetchAll();
}

$pending_requests = getAppointmentRequests($pdo, 'pending');
$approved_requests = getAppointmentRequests($pdo, 'approved');
$rejected_requests = getAppointmentRequests($pdo, 'rejected');

// Get all doctors for dropdown
$doctors_stmt = $pdo->query("
    SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as name, 
           dp.specialty
    FROM users u 
    LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
    WHERE u.role = 'Doctor' AND u.status = 'active'
    ORDER BY u.first_name
");
$doctors = $doctors_stmt->fetchAll();

// Get waiting list count
$waiting_stmt = $pdo->query("
    SELECT COUNT(*) as count FROM appointment_waiting_list WHERE status = 'waiting'
");
$waiting_count = $waiting_stmt->fetch()['count'];
include "includes/sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Requests - AuraCare HMS</title>
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

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
            }
        }

        /* Tabs Navigation */
        .requests-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            background: white;
            padding: 0.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            flex-wrap: wrap;
        }

        .tab-btn {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            background: transparent;
            color: #64748b;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-width: 120px;
        }

        @media (max-width: 640px) {
            .tab-btn {
                min-width: 100px;
                padding: 0.5rem 0.75rem;
                font-size: 0.875rem;
            }
        }

        .tab-btn:hover {
            background: #f8fafc;
            color: #374151;
        }

        .tab-btn.active {
            background: #3b82f6;
            color: white;
        }

        .tab-badge {
            background: #ef4444;
            color: white;
            border-radius: 9999px;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .tab-btn.active .tab-badge {
            background: white;
            color: #3b82f6;
        }

        /* Content Cards */
        .content-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .card-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0;
        }

        .card-header p {
            color: #64748b;
            font-size: 0.875rem;
        }

        .card-content {
            padding: 1.5rem;
        }

        @media (max-width: 640px) {
            .card-header, .card-content {
                padding: 1rem;
            }
        }

        /* Request Cards */
        .request-cards {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .request-card {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1.5rem;
            transition: all 0.2s;
        }

        @media (max-width: 640px) {
            .request-card {
                padding: 1rem;
            }
        }

        .request-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .patient-info {
            flex: 1;
            min-width: 200px;
        }

        .patient-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .patient-contact {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .doctor-info {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .request-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 640px) {
            .request-details {
                grid-template-columns: 1fr;
            }
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-label {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
        }

        .detail-value {
            font-size: 0.875rem;
            color: #0f172a;
            font-weight: 500;
        }

        .urgency-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .urgency-high {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .urgency-medium {
            background: #fffbeb;
            color: #d97706;
            border: 1px solid #fed7aa;
        }

        .urgency-normal {
            background: #f0f9ff;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }

        .request-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        @media (max-width: 640px) {
            .request-actions {
                flex-direction: column;
            }
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            min-width: 100px;
            justify-content: center;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #d1d5db;
            color: #374151;
        }

        .btn-outline:hover {
            background: #f9fafb;
        }

        /* Modal */
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
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 640px) {
            .modal-content {
                padding: 1.5rem;
            }
        }

        .modal-header {
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }

        .form-control {
            width: 100%;
            padding: 0.625rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .modal-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
            flex-wrap: wrap;
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

        /* Fee Display */
        .fee-display {
            padding: 0.75rem;
            background: #f0f9ff;
            border: 1px solid #e0f2fe;
            border-radius: 0.5rem;
            font-weight: 600;
            color: #0369a1;
            margin-top: 0.5rem;
        }
        
        .required::after {
            content: " *";
            color: #ef4444;
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
        <h1>Appointment Requests</h1>
        <p>Manage patient appointment requests.</p>
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

    <div class="requests-tabs">
        <button class="tab-btn <?php echo $tab === 'pending' ? 'active' : ''; ?>" onclick="changeTab('pending')">
            <i class="fas fa-clock"></i>
            Pending
            <?php if (count($pending_requests) > 0): ?>
                <span class="tab-badge"><?php echo count($pending_requests); ?></span>
            <?php endif; ?>
        </button>
        <button class="tab-btn <?php echo $tab === 'approved' ? 'active' : ''; ?>" onclick="changeTab('approved')">
            <i class="fas fa-check"></i>
            Approved
        </button>
        <button class="tab-btn <?php echo $tab === 'rejected' ? 'active' : ''; ?>" onclick="changeTab('rejected')">
            <i class="fas fa-times"></i>
            Rejected
        </button>
    </div>

    <?php if ($tab === 'pending'): ?>
        <!-- Pending requests content -->
        <div class="content-card">
            <div class="card-header">
                <div>
                    <h2>Pending Appointment Requests</h2>
                    <p>Review and process incoming appointment requests.</p>
                </div>
                <div>
                    <span class="urgency-badge urgency-high" style="margin-right: 0.5rem;">High: <?php echo count(array_filter($pending_requests, fn($r) => $r['urgency'] === 'High')); ?></span>
                    <span class="urgency-badge urgency-medium" style="margin-right: 0.5rem;">Medium: <?php echo count(array_filter($pending_requests, fn($r) => $r['urgency'] === 'Medium')); ?></span>
                    <span class="urgency-badge urgency-normal">Normal: <?php echo count(array_filter($pending_requests, fn($r) => $r['urgency'] === 'Normal')); ?></span>
                </div>
            </div>
            <div class="card-content">
                <?php if (empty($pending_requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Pending Requests</h3>
                        <p>All appointment requests have been processed.</p>
                    </div>
                <?php else: ?>
                    <div class="request-cards">
                        <?php foreach ($pending_requests as $request): ?>
                            <div class="request-card">
                                <div class="request-header">
                                    <div class="patient-info">
                                        <div class="patient-name"><?php echo htmlspecialchars($request['patient_name']); ?></div>
                                        <div class="patient-contact">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($request['patient_phone'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="doctor-info">
                                            <i class="fas fa-user-md"></i>
                                            <?php 
                                            if ($request['doctor_id']) {
                                                echo "Requested: Dr. " . htmlspecialchars($request['doctor_name']);
                                            } else {
                                                echo "Any available doctor";
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <span class="urgency-badge urgency-<?php echo strtolower($request['urgency']); ?>">
                                        <?php echo htmlspecialchars($request['urgency']); ?>
                                    </span>
                                </div>
                                
                                <div class="request-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Preferred Date</span>
                                        <span class="detail-value"><?php echo date('M d, Y', strtotime($request['preferred_date'])); ?></span>
                                    </div>
                                   <!-- In the pending requests section, replace the time display -->
                                    <div class="detail-item">
                                        <span class="detail-label">Preferred Time</span>
                                        <span class="detail-value"><?php echo formatTime($request['preferred_time']); ?></span>
                                    </div>

                                    <!-- In the approved requests section -->
                                    <div class="detail-item">
                                        <span class="detail-label">Scheduled Time</span>
                                        <span class="detail-value">
                                            <?php 
                                            if ($request['scheduled_time']) {
                                                echo formatTime($request['scheduled_time']);
                                            } else {
                                                echo formatTime($request['preferred_time']);
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Type</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($request['type']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Submitted</span>
                                        <span class="detail-value"><?php echo date('M d, g:i A', strtotime($request['created_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($request['notes'])): ?>
                                    <div class="detail-item" style="grid-column: 1 / -1; margin-bottom: 1rem;">
                                        <span class="detail-label">Patient Notes</span>
                                        <span class="detail-value" style="font-style: italic;">"<?php echo htmlspecialchars($request['notes']); ?>"</span>
                                    </div>
                                <?php endif; ?>

                                <div class="request-actions">
                                    <button class="btn btn-outline" onclick="showRejectModal(<?php echo $request['id']; ?>)">
                                        <i class="fas fa-times"></i>
                                        Reject
                                    </button>
                                    <button class="btn btn-primary" onclick="showApproveModal(<?php echo $request['id']; ?>, '<?php echo $request['preferred_date']; ?>', '<?php echo $request['preferred_time']; ?>', <?php echo $request['doctor_id'] ?: 'null'; ?>)">
                                        <i class="fas fa-check"></i>
                                        Approve
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($tab === 'approved'): ?>
        <div class="content-card">
            <div class="card-header">
                <h2>Approved Requests</h2>
                <p>View all approved appointment requests.</p>
            </div>
            <div class="card-content">
                <?php if (empty($approved_requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3>No Approved Requests</h3>
                        <p>No appointment requests have been approved yet.</p>
                    </div>
                <?php else: ?>
                    <div class="request-cards">
                        <?php foreach ($approved_requests as $request): ?>
                            <div class="request-card">
                                <div class="request-header">
                                    <div class="patient-info">
                                        <div class="patient-name"><?php echo htmlspecialchars($request['patient_name']); ?></div>
                                        <div class="doctor-info">
                                            <i class="fas fa-user-md"></i>
                                            <?php 
                                            if ($request['doctor_id']) {
                                                echo "Dr. " . htmlspecialchars($request['doctor_name']);
                                            } else {
                                                echo "Any available doctor";
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <span class="urgency-badge urgency-<?php echo strtolower($request['urgency']); ?>">
                                        <?php echo htmlspecialchars($request['urgency']); ?>
                                    </span>
                                </div>
                                
                                <div class="request-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Scheduled Date</span>
                                        <span class="detail-value">
                                            <?php 
                                            if ($request['scheduled_date']) {
                                                echo date('M d, Y', strtotime($request['scheduled_date']));
                                            } else {
                                                echo date('M d, Y', strtotime($request['preferred_date']));
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Scheduled Time</span>
                                        <span class="detail-value">
                                            <?php 
                                            if ($request['scheduled_time']) {
                                                echo date('g:i A', strtotime($request['scheduled_time']));
                                            } else {
                                                echo htmlspecialchars($request['preferred_time']);
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Service</span>
                                        <span class="detail-value">
                                            <?php 
                                            if ($request['service_name']) {
                                                echo htmlspecialchars($request['service_name']);
                                            } else {
                                                echo htmlspecialchars($request['type']);
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Consultation Fee</span>
                                        <span class="detail-value">
                                            <?php 
                                            if ($request['consultation_fee'] > 0) {
                                                echo 'Ksh ' . number_format($request['consultation_fee'], 2);
                                            } else {
                                                echo '<span style="color: #64748b;">Not set</span>';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Approved By</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($request['processed_by_name'] ?? 'System'); ?></span>
                                    </div>
                                </div>
                                
                                <div class="request-actions">
                                    <button class="btn btn-outline" onclick="viewAppointment(<?php echo $request['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                        View Details
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($tab === 'rejected'): ?>
        <div class="content-card">
            <div class="card-header">
                <h2>Rejected Requests</h2>
                <p>View all rejected appointment requests.</p>
            </div>
            <div class="card-content">
                <?php if (empty($rejected_requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-times-circle"></i>
                        <h3>No Rejected Requests</h3>
                        <p>No appointment requests have been rejected yet.</p>
                    </div>
                <?php else: ?>
                    <div class="request-cards">
                        <?php foreach ($rejected_requests as $request): ?>
                            <div class="request-card">
                                <div class="request-header">
                                    <div class="patient-info">
                                        <div class="patient-name"><?php echo htmlspecialchars($request['patient_name']); ?></div>
                                        <div class="doctor-info">
                                            <i class="fas fa-user-md"></i>
                                            <?php 
                                            if ($request['doctor_id']) {
                                                echo "Dr. " . htmlspecialchars($request['doctor_name']);
                                            } else {
                                                echo "Any available doctor";
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <span class="urgency-badge urgency-<?php echo strtolower($request['urgency']); ?>">
                                        <?php echo htmlspecialchars($request['urgency']); ?>
                                    </span>
                                </div>
                                
                                <div class="request-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Preferred Date</span>
                                        <span class="detail-value"><?php echo date('M d, Y', strtotime($request['preferred_date'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Type</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($request['type']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Rejected By</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($request['processed_by_name'] ?? 'System'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Rejected On</span>
                                        <span class="detail-value"><?php echo date('M d, g:i A', strtotime($request['processed_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($request['rejection_reason'])): ?>
                                    <div class="detail-item" style="grid-column: 1 / -1; margin-bottom: 1rem;">
                                        <span class="detail-label">Reason for Rejection</span>
                                        <span class="detail-value" style="color: #ef4444; font-style: italic;"><?php echo htmlspecialchars($request['rejection_reason']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="request-actions">
                                    <button class="btn btn-outline" onclick="reconsiderRequest(<?php echo $request['id']; ?>)">
                                        <i class="fas fa-redo"></i>
                                        Reconsider
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Approve Modal with Service Selection -->
<div id="approveModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Approve Appointment Request</h3>
        </div>
        <form id="approveForm" method="POST">
            <input type="hidden" name="request_id" id="approveRequestId">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="service_id" id="serviceIdInput">
            <input type="hidden" name="consultation_fee" id="consultationFeeInput">
            
            <div class="form-group">
                <label class="form-label required">Assign Doctor</label>
                <select class="form-control" name="doctor_id" id="doctorSelect" required>
                    <option value="">Select a doctor</option>
                    <?php foreach ($doctors as $doctor): ?>
                        <option value="<?php echo $doctor['id']; ?>">
                            Dr. <?php echo htmlspecialchars($doctor['name']); ?>
                            <?php if ($doctor['specialty']): ?>
                                (<?php echo htmlspecialchars($doctor['specialty']); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label required">Appointment Date & Time</label>
                <input type="datetime-local" class="form-control" name="appointment_datetime" id="appointmentDatetime" required min="<?php echo date('Y-m-d\TH:i'); ?>">
                <small class="text-muted">Preferred: <span id="preferredTimeDisplay"></span></small>
            </div>
            
            <div class="form-group">
                <label class="form-label required">Consultation Service</label>
                <select class="form-control" name="consultation_service" id="consultationServiceSelect" required onchange="updateConsultationFee(this)">
                    <option value="">Select consultation service</option>
                    <?php foreach ($consultation_services as $service): ?>
                        <option value="<?php echo $service['id']; ?>" data-fee="<?php echo $service['price']; ?>">
                            <?php echo htmlspecialchars($service['name']); ?> - Ksh <?php echo number_format($service['price'], 2); ?>
                            <?php if ($service['description']): ?>
                                (<?php echo htmlspecialchars($service['description']); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" id="feeDisplayGroup" style="display: none;">
                <label class="form-label">Consultation Fee</label>
                <div class="fee-display">
                    Ksh <span id="feeDisplayAmount">0.00</span>
                </div>
                <small style="color: #64748b;">This fee will be automatically added to billing</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Notes (Optional)</label>
                <textarea class="form-control" name="notes" placeholder="Add any additional notes..."></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('approveModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="approveSubmitBtn">
                    Approve & Schedule
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Reject Appointment Request</h3>
        </div>
        <form id="rejectForm" method="POST">
            <input type="hidden" name="request_id" id="rejectRequestId">
            <input type="hidden" name="action" value="reject">
            
            <div class="form-group">
                <label class="form-label required">Reason for Rejection</label>
                <textarea class="form-control" name="reason" placeholder="Please provide a reason for rejecting this appointment request..." required></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Alternative Suggestion (Optional)</label>
                <textarea class="form-control" name="alternative" placeholder="Suggest alternative dates/times or doctors..."></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('rejectModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Reject Request</button>
            </div>
        </form>
    </div>
</div>

<script>
   function showApproveModal(requestId, preferredDate, preferredTime, doctorId) {
    document.getElementById('approveRequestId').value = requestId;
    
    // Format the preferred time for display
    let formattedPreferredTime = 'Not specified';
    if (preferredTime) {
        // Check if it's a time string (contains numbers and colons)
        if (preferredTime.match(/^\d{1,2}:\d{2}/)) {
            const timeParts = preferredTime.split(':');
            const hour = parseInt(timeParts[0]);
            const minute = timeParts[1];
            const period = hour >= 12 ? 'PM' : 'AM';
            const hour12 = hour % 12 || 12;
            formattedPreferredTime = `${hour12}:${minute} ${period}`;
        } else {
            // Handle old ENUM values
            formattedPreferredTime = preferredTime;
        }
    }
    
    document.getElementById('preferredTimeDisplay').textContent = 
        `${preferredDate} (${formattedPreferredTime})`;
    
    // Set default doctor if specified in request
    if (doctorId && doctorId !== 'null') {
        document.getElementById('doctorSelect').value = doctorId;
    }
    
    // Set default appointment datetime
    let defaultDateTime = new Date();
    defaultDateTime.setDate(defaultDateTime.getDate() + 1);
    
    // Try to use the preferred time if available
    if (preferredTime && preferredTime.match(/^\d{1,2}:\d{2}/)) {
        const timeParts = preferredTime.split(':');
        const hour = parseInt(timeParts[0]);
        const minute = parseInt(timeParts[1]);
        defaultDateTime.setHours(hour, minute, 0);
    } else {
        defaultDateTime.setHours(9, 0, 0); // Default to 9 AM if no specific time
    }
    
    const formattedDateTime = defaultDateTime.toISOString().slice(0, 16);
    document.getElementById('appointmentDatetime').value = formattedDateTime;
    document.getElementById('appointmentDatetime').min = new Date().toISOString().slice(0, 16);
    
    document.getElementById('approveModal').style.display = 'flex';
}

    function showRejectModal(requestId) {
        document.getElementById('rejectRequestId').value = requestId;
        document.getElementById('rejectModal').style.display = 'flex';
    }

    function updateConsultationFee(select) {
        const feeGroup = document.getElementById('feeDisplayGroup');
        const feeDisplay = document.getElementById('feeDisplayAmount');
        const feeInput = document.getElementById('consultationFeeInput');
        const serviceIdInput = document.getElementById('serviceIdInput');
        
        if (select.value) {
            const selectedOption = select.options[select.selectedIndex];
            const fee = selectedOption.getAttribute('data-fee');
            
            feeDisplay.textContent = parseFloat(fee).toFixed(2);
            feeInput.value = fee;
            serviceIdInput.value = select.value;
            feeGroup.style.display = 'block';
        } else {
            feeGroup.style.display = 'none';
            feeInput.value = '0';
            serviceIdInput.value = '';
        }
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    function changeTab(tab) {
        window.location.href = `appointment_requests.php?tab=${tab}`;
    }
    
    function viewAppointment(requestId) {
        window.location.href = `appointment_scheduling.php?view=${requestId}`;
    }
    
    function reconsiderRequest(requestId) {
        if (confirm('Are you sure you want to reconsider this rejected request?')) {
            window.location.href = `reconsider_request.php?id=${requestId}`;
        }
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    };
    
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
    });
</script>

</body>
</html>