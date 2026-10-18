<?php
session_start();
include "includes/db_connect.php";


// Get current tab
$tab = $_GET['tab'] ?? 'pending';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['request_id'])) {
        $request_id = $_POST['request_id'];
        $action = $_POST['action'];
        $reason = $_POST['reason'] ?? '';
        $doctor_id = $_POST['doctor_id'] ?? null;
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_time = $_POST['appointment_time'] ?? '';
        
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
                
                // Approve the request
                $stmt = $pdo->prepare("
                    UPDATE appointment_requests 
                    SET status = 'approved', 
                        processed_by = ?, 
                        processed_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $request_id]);
                
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
                    
                    // Create actual appointment from the request
                    $appointment_stmt = $pdo->prepare("
                        INSERT INTO appointments 
                        (appointment_number, patient_id, doctor_id, appointment_date, appointment_time, 
                         appointment_type, reason, notes, status, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, NOW())
                    ");
                    
                    // Generate appointment number
                    $appointment_number = 'APT-' . date('Ymd') . '-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
                    
                    $appointment_stmt->execute([
                        $appointment_number,
                        $request['patient_id'],
                        $doctor_id,
                        $appointment_date,
                        $appointment_time,
                        $request['type'],
                        $request['notes'],
                        "Approved from request #" . $request_id,
                        $_SESSION['user_id']
                    ]);
                    
                    // Remove from waiting list if exists
                    $waiting_stmt = $pdo->prepare("
                        UPDATE appointment_waiting_list 
                        SET status = 'scheduled' 
                        WHERE patient_id = ? AND doctor_id = ? AND status = 'waiting'
                    ");
                    $waiting_stmt->execute([$request['patient_id'], $doctor_id]);
                }
                
                $pdo->commit();
                $_SESSION['success'] = "Appointment request approved and scheduled successfully.";
                
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
                    SELECT ar.*, a.id as appointment_id, a.appointment_date, a.appointment_time
                    FROM appointment_requests ar
                    LEFT JOIN appointments a ON a.appointment_number LIKE CONCAT('%', ar.id)
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
                             appointment_type, reason, notes, status, created_by, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, NOW())
                        ");
                        
                        // Generate appointment number
                        $appointment_number = 'APT-' . date('Ymd') . '-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
                        
                        $appointment_stmt->execute([
                            $appointment_number,
                            $appointment['patient_id'],
                            $new_doctor_id,
                            $new_date,
                            $new_time,
                            $appointment['type'],
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
                    
                    // Add reschedule history (if history table exists)
                    try {
                        $history_stmt = $pdo->prepare("
                            INSERT INTO appointment_reschedule_history 
                            (request_id, old_date, old_time, old_doctor_id, 
                             new_date, new_time, new_doctor_id, reason, changed_by, changed_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $history_stmt->execute([
                            $request_id,
                            $appointment['preferred_date'],
                            $appointment['preferred_time'],
                            $appointment['doctor_id'],
                            $new_date,
                            $appointment_time,
                            $new_doctor_id,
                            $_POST['reschedule_reason'] ?? 'Rescheduled',
                            $_SESSION['user_id']
                        ]);
                    } catch (Exception $e) {
                        // Silently fail if history table doesn't exist
                        error_log("History table error: " . $e->getMessage());
                    }
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

// Get appointment requests based on tab
function getAppointmentRequests($pdo, $status) {
    $sql = "
        SELECT ar.*, 
               CONCAT(p.first_name, ' ', p.last_name) as patient_name,
               p.phone as patient_phone,
               CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
               u.first_name as processed_by_name,
               a.appointment_date as scheduled_date,
               a.appointment_time as scheduled_time
        FROM appointment_requests ar
        LEFT JOIN patients p ON ar.patient_id = p.id
        LEFT JOIN users d ON ar.doctor_id = d.id
        LEFT JOIN users u ON ar.processed_by = u.id
        LEFT JOIN appointments a ON a.appointment_number LIKE CONCAT('%', ar.id)
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Requests - MediCare HMS</title>
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

        /* Tables */
        .requests-table {
            width: 100%;
            border-collapse: collapse;
            overflow-x: auto;
            display: block;
        }

        .requests-table th {
            background: #f8fafc;
            padding: 0.75rem 1rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .requests-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.875rem;
            white-space: nowrap;
        }

        .requests-table tr:last-child td {
            border-bottom: none;
        }

        @media (max-width: 768px) {
            .requests-table {
                display: block;
                overflow-x: auto;
            }
        }

        .table-patient {
            font-weight: 600;
            color: #0f172a;
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
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

        
        /* Reschedule Modal Specific Styles */
        .current-schedule {
            background: #f0f9ff;
            border: 1px solid #e0f2fe;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .schedule-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-top: 0.5rem;
        }
        
        .schedule-item {
            display: flex;
            flex-direction: column;
        }
        
        .schedule-label {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 500;
        }
        
        .schedule-value {
            font-size: 0.875rem;
            font-weight: 600;
            color: #0f172a;
        }
        
        .reschedule-reason {
            margin-top: 1rem;
        }
        
        @media (max-width: 640px) {
            .schedule-details {
                grid-template-columns: 1fr;
            }
        }
        
        /* Date picker improvements */
        .datetime-picker {
            position: relative;
        }
        
        .datetime-picker input[type="datetime-local"] {
            padding-right: 2.5rem;
        }
        
        .datetime-picker i {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            pointer-events: none;
        }
        
        /* Doctor availability indicator */
        .availability-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }
        
        .available {
            background: #d1fae5;
            color: #065f46;
        }
        
        .limited {
            background: #fef3c7;
            color: #92400e;
        }
        
        .unavailable {
            background: #fee2e2;
            color: #dc2626;
        }
        
        /* Conflict warning */
        .conflict-warning {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 0.375rem;
            padding: 0.75rem;
            margin-top: 0.5rem;
            display: none;
        }
        
        .conflict-warning.show {
            display: block;
        }
        
        .conflict-warning i {
            color: #f59e0b;
            margin-right: 0.5rem;
        }
        
        /* Required field indicator */
        .required::after {
            content: " *";
            color: #ef4444;
        }
        
        /* Loading spinner */
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 1rem;
        }
        
        .loading-spinner.active {
            display: block;
        }
        
        .spinner {
            display: inline-block;
            width: 1.5rem;
            height: 1.5rem;
            border: 3px solid #e2e8f0;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
    
        .schedule-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .schedule-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .schedule-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .schedule-label {
            font-weight: 500;
            color: #495057;
        }
        
        .schedule-value {
            font-weight: 600;
            color: #212529;
        }
        
        .current-appointment {
            background: #e7f5ff;
            border: 1px solid #a5d8ff;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .new-appointment {
            background: #e6fcf5;
            border: 1px solid #96f2d7;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .appointment-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .datetime-group {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .datetime-group {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
        
        .time-slot {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .time-slot:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        
        .time-slot.selected {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .quick-times {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .quick-time-btn {
            padding: 0.5rem 0.75rem;
            background: #f1f3f5;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        
        .quick-time-btn:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        
        .doctor-availability {
            margin-top: 1rem;
        }
        
        .availability-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 0.375rem;
        }
        
        .availability-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        
        .available-dot {
            background: #51cf66;
        }
        
        .busy-dot {
            background: #ff6b6b;
        }
        
        .limited-dot {
            background: #ffd43b;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    
        
        .schedule-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .schedule-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .schedule-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .schedule-label {
            font-weight: 500;
            color: #495057;
        }
        
        .schedule-value {
            font-weight: 600;
            color: #212529;
        }
        
        .current-appointment {
            background: #e7f5ff;
            border: 1px solid #a5d8ff;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .new-appointment {
            background: #e6fcf5;
            border: 1px solid #96f2d7;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .appointment-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .datetime-group {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .datetime-group {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
        
        .time-slot {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .time-slot:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        
        .time-slot.selected {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .quick-times {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .quick-time-btn {
            padding: 0.5rem 0.75rem;
            background: #f1f3f5;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        
        .quick-time-btn:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        
        .doctor-availability {
            margin-top: 1rem;
        }
        
        .availability-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 0.375rem;
        }
        
        .availability-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        
        .available-dot {
            background: #51cf66;
        }
        
        .busy-dot {
            background: #ff6b6b;
        }
        
        .limited-dot {
            background: #ffd43b;
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
        <!-- <button class="tab-btn <?php echo $tab === 'waiting' ? 'active' : ''; ?>" onclick="changeTab('waiting')">
            <i class="fas fa-list"></i>
            Waiting List
            <?php if ($waiting_count > 0): ?>
                <span class="tab-badge"><?php echo $waiting_count; ?></span>
            <?php endif; ?>
        </button> -->
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
                                    <div class="detail-item">
                                        <span class="detail-label">Preferred Time</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($request['preferred_time']); ?></span>
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
                                    <button class="btn btn-primary" onclick="showApproveModal(<?php echo $request['id']; ?>, '<?php echo $request['preferred_date']; ?>', '<?php echo $request['preferred_time']; ?>')">
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
                                        <span class="detail-label">Current Date</span>
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
                                        <span class="detail-label">Current Time</span>
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
                                        <span class="detail-label">Type</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($request['type']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Approved By</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($request['processed_by_name'] ?? 'System'); ?></span>
                                    </div>
                                </div>
                                
                                <div class="request-actions">
                                    <button class="btn btn-outline" onclick="viewAppointment(<?php echo $request['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                        View
                                    </button>
                                    <!-- <button class="btn btn-primary" onclick="showRescheduleModal(
                                        <?php echo $request['id']; ?>,
                                        '<?php echo htmlspecialchars($request['patient_name']); ?>',
                                        '<?php echo $request['doctor_id'] ? htmlspecialchars($request['doctor_name']) : 'Any Doctor'; ?>',
                                        '<?php echo $request['scheduled_date'] ? date('Y-m-d', strtotime($request['scheduled_date'])) : $request['preferred_date']; ?>',
                                        '<?php echo $request['scheduled_time'] ? date('H:i', strtotime($request['scheduled_time'])) : $request['preferred_time']; ?>',
                                        <?php echo $request['doctor_id'] ?: 'null'; ?>
                                    )">
                                        <i class="fas fa-calendar-alt"></i>
                                        Reschedule
                                    </button> -->
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
        
    <?php elseif ($tab === 'waiting'): ?>
        <div class="content-card">
            <div class="card-header">
                <h2>Appointment Waiting List</h2>
                <p>Patients waiting for available appointment slots.</p>
            </div>
            <div class="card-content">
                <?php 
                // Get waiting list
                $waiting_stmt = $pdo->query("
                    SELECT wl.*, 
                           CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                           CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                           p.phone as patient_phone
                    FROM appointment_waiting_list wl
                    LEFT JOIN patients p ON wl.patient_id = p.id
                    LEFT JOIN users d ON wl.doctor_id = d.id
                    WHERE wl.status = 'waiting'
                    ORDER BY 
                        CASE WHEN wl.priority = 'urgent' THEN 1
                             WHEN wl.priority = 'high' THEN 2
                             WHEN wl.priority = 'medium' THEN 3
                             ELSE 4
                        END,
                        wl.created_at
                ");
                $waiting_list = $waiting_stmt->fetchAll();
                ?>
                
                <?php if (empty($waiting_list)): ?>
                    <div class="empty-state">
                        <i class="fas fa-list"></i>
                        <h3>Waiting List Empty</h3>
                        <p>No patients are currently on the waiting list.</p>
                    </div>
                <?php else: ?>
                    <div class="request-cards">
                        <?php foreach ($waiting_list as $waiting): ?>
                            <div class="request-card">
                                <div class="request-header">
                                    <div class="patient-info">
                                        <div class="patient-name"><?php echo htmlspecialchars($waiting['patient_name']); ?></div>
                                        <div class="patient-contact">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($waiting['patient_phone'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="doctor-info">
                                            <i class="fas fa-user-md"></i>
                                            <?php 
                                            if ($waiting['doctor_id']) {
                                                echo "Dr. " . htmlspecialchars($waiting['doctor_name']);
                                            } else {
                                                echo "Any available doctor";
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <span class="urgency-badge urgency-<?php echo strtolower($waiting['priority']); ?>">
                                        <?php echo htmlspecialchars($waiting['priority']); ?>
                                    </span>
                                </div>
                                
                                <div class="request-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Preferred Date</span>
                                        <span class="detail-value"><?php echo date('M d, Y', strtotime($waiting['preferred_date'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Preferred Time</span>
                                        <span class="detail-value"><?php echo date('g:i A', strtotime($waiting['preferred_time'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Reason</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($waiting['reason'] ?? 'No reason specified'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Added to List</span>
                                        <span class="detail-value"><?php echo date('M d, g:i A', strtotime($waiting['created_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <div class="request-actions">
                                    <button class="btn btn-outline" onclick="removeFromWaitingList(<?php echo $waiting['id']; ?>)">
                                        <i class="fas fa-times"></i>
                                        Remove
                                    </button>
                                    <button class="btn btn-primary" onclick="scheduleFromWaitingList(<?php echo $waiting['id']; ?>)">
                                        <i class="fas fa-calendar-check"></i>
                                        Schedule Now
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

<!-- Approve Modal -->
<div id="approveModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Approve Appointment Request</h3>
        </div>
        <form id="approveForm" method="POST">
            <input type="hidden" name="request_id" id="approveRequestId">
            <input type="hidden" name="action" value="approve">
            
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
            
            <div class="form-group datetime-picker">
                <label class="form-label required">Appointment Date & Time</label>
                <input type="datetime-local" class="form-control" name="appointment_datetime" id="appointmentDatetime" required min="<?php echo date('Y-m-d\TH:i'); ?>">
                <i class="fas fa-calendar-alt"></i>
                <small class="text-muted">Based on patient's preferred time: <span id="preferredTimeDisplay"></span></small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Notes (Optional)</label>
                <textarea class="form-control" name="notes" placeholder="Add any additional notes for the appointment..."></textarea>
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

<!-- Reschedule Modal - Fixed -->
<div id="rescheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-calendar-alt"></i>
                Reschedule Appointment
            </h3>
        </div>
        <form id="rescheduleForm" method="POST">
            <input type="hidden" name="request_id" id="rescheduleRequestId">
            <input type="hidden" name="action" value="reschedule">
            
            <div class="current-appointment">
                <div class="appointment-title">
                    <i class="fas fa-calendar-check"></i>
                    Current Appointment Details
                </div>
                <div class="schedule-info">
                    <div class="schedule-item">
                        <span class="schedule-label">Patient:</span>
                        <span class="schedule-value" id="currentPatientName"></span>
                    </div>
                    <div class="schedule-item">
                        <span class="schedule-label">Current Doctor:</span>
                        <span class="schedule-value" id="currentDoctorName"></span>
                    </div>
                    <div class="schedule-item">
                        <span class="schedule-label">Current Date:</span>
                        <span class="schedule-value" id="currentDate"></span>
                    </div>
                    <div class="schedule-item">
                        <span class="schedule-label">Current Time:</span>
                        <span class="schedule-value" id="currentTime"></span>
                    </div>
                </div>
            </div>
            
            <div class="new-appointment">
                <div class="appointment-title">
                    <i class="fas fa-calendar-plus"></i>
                    New Appointment Details
                </div>
                
                <div class="form-group">
                    <label class="form-label">Change Doctor (Optional)</label>
                    <select class="form-control" name="doctor_id" id="rescheduleDoctorSelect">
                        <option value="">Keep current doctor</option>
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
                
                <div class="datetime-group">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label required">New Date</label>
                        <input type="date" class="form-control" name="appointment_date" id="rescheduleDate" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label required">New Time</label>
                        <select class="form-control" name="appointment_time" id="rescheduleTime" required>
                            <option value="">Select time</option>
                            <?php
                            // Generate time slots
                            $start_time = strtotime('08:00');
                            $end_time = strtotime('18:00');
                            $interval = 30 * 60; // 30 minutes in seconds
                            
                            for ($time = $start_time; $time <= $end_time; $time += $interval) {
                                $time_value = date('H:i', $time);
                                $time_display = date('g:i A', $time);
                                echo "<option value='$time_value'>$time_display</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="quick-times">
                    <small>Quick select:</small>
                    <?php
                    $quick_times = ['09:00', '10:00', '11:00', '14:00', '15:00', '16:00'];
                    foreach ($quick_times as $qt) {
                        $display = date('g:i A', strtotime($qt));
                        echo "<button type='button' class='quick-time-btn' onclick=\"setRescheduleTime('$qt')\">$display</button>";
                    }
                    ?>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label required">Reason for Rescheduling</label>
                <select class="form-control" name="reschedule_reason" required>
                    <option value="">Select a reason</option>
                    <option value="doctor_unavailable">Doctor Unavailable</option>
                    <option value="patient_request">Patient Request</option>
                    <option value="emergency">Emergency Situation</option>
                    <option value="scheduling_conflict">Scheduling Conflict</option>
                    <option value="facility_issue">Facility Issue</option>
                    <option value="other">Other Reason</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Additional Notes (Optional)</label>
                <textarea class="form-control" name="reschedule_notes" rows="3" placeholder="Add any additional notes about the rescheduling..."></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('rescheduleModal')">
                    <i class="fas fa-times"></i>
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary" id="rescheduleSubmitBtn">
                    <i class="fas fa-calendar-check"></i>
                    Confirm Reschedule
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Fix the issue with missing showApproveModal function
    function showApproveModal(requestId, preferredDate, preferredTime) {
        document.getElementById('approveRequestId').value = requestId;
        document.getElementById('preferredTimeDisplay').textContent = preferredDate + ' (' + preferredTime + ')';
        
        // Set default appointment datetime to tomorrow at 9:00 AM
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        tomorrow.setHours(9, 0, 0);
        
        const formattedDateTime = tomorrow.toISOString().slice(0, 16);
        document.getElementById('appointmentDatetime').value = formattedDateTime;
        document.getElementById('appointmentDatetime').min = new Date().toISOString().slice(0, 16);
        
        document.getElementById('approveModal').style.display = 'flex';
    }

    // Fix the issue with missing showRejectModal function
    function showRejectModal(requestId) {
        document.getElementById('rejectRequestId').value = requestId;
        document.getElementById('rejectModal').style.display = 'flex';
    }

    // Fix the reschedule modal function
    function showRescheduleModal(requestId, patientName, doctorName, currentDate, currentTime, currentDoctorId) {
        document.getElementById('rescheduleRequestId').value = requestId;
        document.getElementById('currentPatientName').textContent = patientName;
        document.getElementById('currentDoctorName').textContent = doctorName;
        
        // Format current date for display
        if (currentDate) {
            const currentDateObj = new Date(currentDate);
            document.getElementById('currentDate').textContent = currentDateObj.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        } else {
            document.getElementById('currentDate').textContent = 'Not scheduled';
        }
        
        // Format current time for display
        if (currentTime) {
            const currentTimeObj = new Date('2000-01-01T' + currentTime);
            document.getElementById('currentTime').textContent = currentTimeObj.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        } else {
            document.getElementById('currentTime').textContent = 'Not scheduled';
        }
        
        // Set default reschedule date to tomorrow
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.getElementById('rescheduleDate').value = tomorrow.toISOString().split('T')[0];
        document.getElementById('rescheduleDate').min = new Date().toISOString().split('T')[0];
        
        // Set default time to 9:00 AM
        document.getElementById('rescheduleTime').value = '09:00';
        
        if (currentDoctorId && currentDoctorId !== 'null') {
            document.getElementById('rescheduleDoctorSelect').value = currentDoctorId;
        }
        
        // Show the modal
        document.getElementById('rescheduleModal').style.display = 'flex';
    }
    
    function setRescheduleTime(time) {
        document.getElementById('rescheduleTime').value = time;
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
    
    function removeFromWaitingList(waitingId) {
        if (confirm('Are you sure you want to remove this patient from the waiting list?')) {
            window.location.href = `appointment_requests.php?action=remove_waiting&id=${waitingId}&tab=waiting`;
        }
    }
    
    function scheduleFromWaitingList(waitingId) {
        window.location.href = `appointment_requests.php?action=schedule_waiting&id=${waitingId}&tab=waiting`;
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

<aside class="sidebar">
  <div class="sidebar-header">
    <div class="logo">
      <div class="logo-icon">
        <i class="fas fa-heartbeat"></i>
      </div>
      <div class="logo-text">
        <h2>AuraCare HMS</h2>
      
      </div>
      <ul style="list-style: none; padding: 0; margin: 0;">
         <li>
          <a href="logout.php" class="menu-item logout-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
          </a>
        </li>
      </ul>
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
      <div class="section-label">Employee Administartion</div>
      <ul class="sidebar-menu">
        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-users"></i>
            <span>Staff Management</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="manage_users.php"><i class="fas fa-user-md"></i>Employee List</a></li>
            <li><a href="departments.php"><i class="fas fa-building"></i>Departments</a></li>
            <li><a href="doctors_schedule.php"><i class="fas fa-calendar-alt"></i>Doctor Schedule</a></li>
            <li><a href="services.php"><i class="fas fa-concierge-bell"></i>Services</a></li>
          </ul>
        </li>
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
          <a href="lab_module.php" class="menu-item">
            <i class="fas fa-vials"></i>
            <span>Laboratory</span>
          </a>
        </li>

        <li>
          <a href="billing_dashboard.php" class="menu-item">
            <i class="fas fa-money-bill-wave"></i>
            <span>Billing & Finance</span>
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

    <div class="nav-section">
      <div class="section-label">Operations</div>
      <ul class="sidebar-menu">
        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-warehouse"></i>
            <span>Inventory & Supply</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="supplier.php"><i class="fas fa-truck"></i>Manage Suppliers</a></li>
            <li><a href="inventorylist.php"><i class="fas fa-boxes"></i>Manage Inventory</a></li>
          </ul>
        </li>

        <li>
          <a href="reports.php" class="menu-item">
            <i class="fas fa-chart-line"></i>
            <span>Reports</span>
          </a>
        </li>
        
        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-chart-line"></i>
            <span>Reports & Analytics</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
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
        <span class="user-name">Administrator</span>
        <span class="user-role">Super Admin</span>
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
</body>
</html>

