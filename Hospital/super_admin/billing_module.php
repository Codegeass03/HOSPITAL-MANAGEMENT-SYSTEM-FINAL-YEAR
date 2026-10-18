<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit();
} 
require_once 'config/database.php';

$username = $_SESSION['username'] ?? 'System';

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $patient_id = intval($_POST['patient_id']);
    $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : null;
    $patient_name = $database->real_escape_string($_POST['patient_name']);
    $consultation_fee = floatval($_POST['consultation_fee']);
    $pharmacy_fee = floatval($_POST['pharmacy_fee']);
    $lab_fee = floatval($_POST['lab_fee']);
    $admission_fee = floatval($_POST['admission_fee']);
    $total_amount = floatval($_POST['total_amount']);
    $payment_method = $database->real_escape_string($_POST['payment_method']);
    $transaction_id = isset($_POST['transaction_id']) ? $database->real_escape_string($_POST['transaction_id']) : '';
    $processed_by = $database->real_escape_string($username);

    $database->begin_transaction();

    try {
        // Generate receipt number
        $receipt_result = $database->query("SELECT MAX(id) as max_id FROM billing_payments");
        $max_id = $receipt_result->fetch_assoc()['max_id'] ?? 0;
        $receipt_no = 'RCPT-' . str_pad(($max_id + 1), 6, "0", STR_PAD_LEFT);

        // Process consultation payment
        if ($consultation_fee > 0 && $appointment_id) {
            // Update appointment payment status
            $update_appointment = $database->prepare("UPDATE appointments SET payment_status = 'paid' WHERE id = ?");
            $update_appointment->bind_param("i", $appointment_id);
            $update_appointment->execute();
            $update_appointment->close();

            // Insert payment record
            $stmt = $database->prepare("INSERT INTO billing_payments (receipt_number, patient_id, patient_name, service_type, reference_id, description, amount, payment_method, transaction_reference, status, processed_by, payment_date) VALUES (?, ?, ?, 'consultation', ?, 'Consultation Fee', ?, ?, ?, 'completed', ?, NOW())");
            $desc = 'Consultation Fee - Appointment #APT-' . $appointment_id;
            $stmt->bind_param("sisissss", $receipt_no, $patient_id, $patient_name, $appointment_id, $consultation_fee, $payment_method, $transaction_id, $processed_by);
            $stmt->execute();
            $stmt->close();
        }

        // Process pharmacy payment
        if ($pharmacy_fee > 0) {
            // Update pharmacy billing status
            $update_pharmacy = $database->prepare("UPDATE pharmacy_billing SET payment_status = 'paid', updated_at = NOW() WHERE patient_id = ? AND payment_status = 'pending'");
            $update_pharmacy->bind_param("i", $patient_id);
            $update_pharmacy->execute();
            $update_pharmacy->close();

            // Insert payment record
            $stmt = $database->prepare("INSERT INTO billing_payments (receipt_number, patient_id, patient_name, service_type, description, amount, payment_method, transaction_reference, status, processed_by, payment_date) VALUES (?, ?, ?, 'pharmacy', 'Pharmacy Charges', ?, ?, ?, 'completed', ?, NOW())");
            $stmt->bind_param("sisdsss", $receipt_no, $patient_id, $patient_name, $pharmacy_fee, $payment_method, $transaction_id, $processed_by);
            $stmt->execute();
            $stmt->close();
        }

        // Process lab payment
        if ($lab_fee > 0) {
            // Update lab orders payment status
            $update_lab = $database->prepare("UPDATE lab_orders SET payment_status = 'paid' WHERE patient_id = ? AND payment_status = 'unpaid'");
            $update_lab->bind_param("i", $patient_id);
            $update_lab->execute();
            $update_lab->close();

            // Insert payment record
            $stmt = $database->prepare("INSERT INTO billing_payments (receipt_number, patient_id, patient_name, service_type, description, amount, payment_method, transaction_reference, status, processed_by, payment_date) VALUES (?, ?, ?, 'lab', 'Laboratory Tests', ?, ?, ?, 'completed', ?, NOW())");
            $stmt->bind_param("sisdsss", $receipt_no, $patient_id, $patient_name, $lab_fee, $payment_method, $transaction_id, $processed_by);
            $stmt->execute();
            $stmt->close();
        }

        // Process admission payment
        if ($admission_fee > 0) {
            // Update bed assignments payment status for pending payments
            $update_admission = $database->prepare("UPDATE bed_assignments SET payment_status = 'paid' WHERE patient_id = ? AND payment_status = 'pending' AND status = 'discharged'");
            $update_admission->bind_param("i", $patient_id);
            $update_admission->execute();
            $update_admission->close();

            // Insert payment record
            $stmt = $database->prepare("INSERT INTO billing_payments (receipt_number, patient_id, patient_name, service_type, description, amount, payment_method, transaction_reference, status, processed_by, payment_date) VALUES (?, ?, ?, 'admission', 'Hospital Admission Charges', ?, ?, ?, 'completed', ?, NOW())");
            $stmt->bind_param("sisdsss", $receipt_no, $patient_id, $patient_name, $admission_fee, $payment_method, $transaction_id, $processed_by);
            $stmt->execute();
            $stmt->close();
        }

        $database->commit();
        $_SESSION['payment_success'] = "Payment processed successfully! Receipt No: $receipt_no";
        header("Location: billing_module.php");
        exit();
    } catch (Exception $e) {
        $database->rollback();
        $_SESSION['payment_error'] = "Error: " . $e->getMessage();
    }
}

// Get pending bills - 
$pending_bills_query = "SELECT 
    patient_id,
    patient_name,
    phone,
    email,
    appointment_id,
    appointment_number,
    appointment_date,
    consultation_fee,
    appointment_payment,
    pharmacy_total,
    lab_total,
    admission_total
FROM (
    SELECT 
        p.id as patient_id,
        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
        p.phone,
        p.email,
        
        -- Get the latest unpaid appointment for this patient
        (SELECT a2.id FROM appointments a2 
         WHERE a2.patient_id = p.id 
         AND a2.status = 'completed' 
         AND a2.payment_status IN ('pending', 'partial')
         ORDER BY a2.appointment_date DESC 
         LIMIT 1) as appointment_id,
        
        (SELECT a2.appointment_number FROM appointments a2 
         WHERE a2.patient_id = p.id 
         AND a2.status = 'completed' 
         AND a2.payment_status IN ('pending', 'partial')
         ORDER BY a2.appointment_date DESC 
         LIMIT 1) as appointment_number,
        
        (SELECT a2.appointment_date FROM appointments a2 
         WHERE a2.patient_id = p.id 
         AND a2.status = 'completed' 
         AND a2.payment_status IN ('pending', 'partial')
         ORDER BY a2.appointment_date DESC 
         LIMIT 1) as appointment_date,
        
        COALESCE((SELECT a2.consultation_fee FROM appointments a2 
         WHERE a2.patient_id = p.id 
         AND a2.status = 'completed' 
         AND a2.payment_status IN ('pending', 'partial')
         ORDER BY a2.appointment_date DESC 
         LIMIT 1), 0) as consultation_fee,
        
        (SELECT a2.payment_status FROM appointments a2 
         WHERE a2.patient_id = p.id 
         AND a2.status = 'completed' 
         AND a2.payment_status IN ('pending', 'partial')
         ORDER BY a2.appointment_date DESC 
         LIMIT 1) as appointment_payment,
        
        -- Sum all pending pharmacy charges for this patient
        COALESCE((SELECT SUM(pb2.total_amount) 
                  FROM pharmacy_billing pb2 
                  WHERE pb2.patient_id = p.id 
                  AND pb2.payment_status = 'pending'), 0) as pharmacy_total,
        
        -- Sum all unpaid lab charges for this patient
        COALESCE((SELECT SUM(lo2.total_amount) 
                  FROM lab_orders lo2 
                  WHERE lo2.patient_id = p.id 
                  AND lo2.payment_status = 'unpaid'), 0) as lab_total,
        
        -- Sum all pending admission charges for this patient
        COALESCE((SELECT SUM(
            CASE 
                WHEN ba2.actual_discharge_date IS NOT NULL 
                THEN GREATEST(1, DATEDIFF(ba2.actual_discharge_date, ba2.admission_date)) * b2.price_per_day
                WHEN ba2.expected_discharge_date IS NOT NULL 
                THEN GREATEST(1, DATEDIFF(ba2.expected_discharge_date, ba2.admission_date)) * b2.price_per_day
                ELSE b2.price_per_day
            END
        )
        FROM bed_assignments ba2
        JOIN beds b2 ON ba2.bed_id = b2.id
        WHERE ba2.patient_id = p.id 
        AND ba2.status = 'discharged' 
        AND ba2.payment_status = 'pending'), 0) as admission_total
        
    FROM patients p
    WHERE p.id IN (
        SELECT DISTINCT patient_id FROM (
            SELECT patient_id FROM appointments 
            WHERE status = 'completed' 
            AND payment_status IN ('pending', 'partial')
            AND consultation_fee > 0
            
            UNION
            
            SELECT patient_id FROM pharmacy_billing 
            WHERE payment_status = 'pending'
            AND total_amount > 0
            
            UNION
            
            SELECT patient_id FROM lab_orders 
            WHERE payment_status = 'unpaid'
            AND total_amount > 0
            
            UNION
            
            SELECT patient_id FROM bed_assignments 
            WHERE status = 'discharged' 
            AND payment_status = 'pending'
        ) AS pending_patients
    )
) AS patient_bills
WHERE (consultation_fee > 0 OR pharmacy_total > 0 OR lab_total > 0 OR admission_total > 0)
ORDER BY appointment_date DESC";

$pending_bills_result = $database->query($pending_bills_query);

// Get payment history
$payment_history_query = "SELECT 
    bp.receipt_number,
    bp.patient_id,
    bp.patient_name,
    bp.service_type,
    bp.description,
    bp.amount,
    bp.payment_method,
    bp.transaction_reference,
    bp.status,
    bp.processed_by,
    bp.payment_date,
    p.phone
FROM billing_payments bp
LEFT JOIN patients p ON bp.patient_id = p.id
WHERE bp.status = 'completed'
ORDER BY bp.payment_date DESC
LIMIT 50";

$payment_history_result = $database->query($payment_history_query);

// Get statistics
$total_revenue_today = $database->query("SELECT COALESCE(SUM(amount), 0) as total FROM billing_payments WHERE DATE(payment_date) = CURDATE() AND status='completed'")->fetch_assoc()['total'];
$total_revenue_month = $database->query("SELECT COALESCE(SUM(amount), 0) as total FROM billing_payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE()) AND status='completed'")->fetch_assoc()['total'];
$pending_count = $pending_bills_result ? $pending_bills_result->num_rows : 0;
$payments_today = $database->query("SELECT COUNT(DISTINCT receipt_number) as count FROM billing_payments WHERE DATE(payment_date) = CURDATE() AND status='completed'")->fetch_assoc()['count'];

// Get consolidated payment history
$consolidated_history_query = "SELECT 
    bp.receipt_number,
    bp.patient_id,
    bp.patient_name,
    p.phone,
    bp.payment_method,
    bp.transaction_reference,
    bp.status,
    bp.processed_by,
    MIN(bp.payment_date) as payment_date,
    SUM(bp.amount) as total_amount,
    SUM(CASE WHEN bp.service_type = 'consultation' THEN bp.amount ELSE 0 END) as consultation_amount,
    SUM(CASE WHEN bp.service_type = 'pharmacy' THEN bp.amount ELSE 0 END) as pharmacy_amount,
    SUM(CASE WHEN bp.service_type = 'lab' THEN bp.amount ELSE 0 END) as lab_amount,
    SUM(CASE WHEN bp.service_type = 'admission' THEN bp.amount ELSE 0 END) as admission_amount,
    GROUP_CONCAT(DISTINCT bp.service_type ORDER BY bp.service_type SEPARATOR ', ') as services
FROM billing_payments bp
LEFT JOIN patients p ON bp.patient_id = p.id
WHERE bp.status = 'completed'
GROUP BY bp.receipt_number, bp.patient_id, bp.patient_name, p.phone, bp.payment_method, bp.transaction_reference, bp.status, bp.processed_by
ORDER BY MIN(bp.payment_date) DESC
LIMIT 50";

$consolidated_history_result = $database->query($consolidated_history_query);

$success_message = $_SESSION['payment_success'] ?? '';
$error_message = $_SESSION['payment_error'] ?? '';
unset($_SESSION['payment_success'], $_SESSION['payment_error']);

include "includes/sidebar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Billing - Equity Afia Medical Hospital</title>
    <style>
        :root {
            --primarycolor: #0a19a0;
            --primarydark: #0e7490;
            --primarylight: #0d039c;
            --secondarycolor: #14b8a6;
            --accentcolor: #22d3ee;
            --bgcolor: #f0fdfa;
            --textcolor: #4e134e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bgcolor);
            color: var(--textcolor);
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .menu {
            width: 280px;
            background: linear-gradient(135deg, var(--primarycolor) 0%, var(--primarydark) 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .menu::-webkit-scrollbar {
            width: 6px;
        }

        .menu::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }

        .menu::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }

        .menu::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }

        .menu-container {
            width: 100%;
        }

        .profile-container {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 10px;
        }

        .profile-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .profile-subtitle {
            font-size: 13px;
            opacity: 0.8;
        }

        .logout-btn {
            width: 100%;
            padding: 10px;
            margin-top: 15px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .menu-row {
            margin: 5px 0;
        }

        .menu-btn {
            padding: 15px 20px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .menu-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        .menu-btn.menu-active {
            background: rgba(255,255,255,0.2);
            border-left: 4px solid white;
        }

        .menu-btn::before {
            content: '';
            width: 24px;
            height: 24px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            filter: brightness(0) invert(1);
        }

        .menu-icon-dashbord::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Crect x='3' y='3' width='7' height='7'/%3E%3Crect x='14' y='3' width='7' height='7'/%3E%3Crect x='14' y='14' width='7' height='7'/%3E%3Crect x='3' y='14' width='7' height='7'/%3E%3C/svg%3E");
        }

        .menu-icon-doctor::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2'/%3E%3Ccircle cx='9' cy='7' r='4'/%3E%3Cpath d='M22 21v-2a4 4 0 0 0-3-3.87'/%3E%3Cpath d='M16 3.13a4 4 0 0 1 0 7.75'/%3E%3C/svg%3E");
        }

        .menu-icon-schedule::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3E%3Cline x1='16' y1='2' x2='16' y2='6'/%3E%3Cline x1='8' y1='2' x2='8' y2='6'/%3E%3Cline x1='3' y1='10' x2='21' y2='10'/%3E%3C/svg%3E");
        }

        .menu-icon-appoinment::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M9 11l3 3L22 4'/%3E%3Cpath d='M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'/%3E%3C/svg%3E");
        }

        .menu-icon-patient::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2'/%3E%3Ccircle cx='12' cy='7' r='4'/%3E%3C/svg%3E");
        }

        .menu-icon-pharmacy::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z'/%3E%3Cpath d='M9 12h6'/%3E%3Cpath d='M12 9v6'/%3E%3C/svg%3E");
        }

        .menu-icon-billing::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cline x1='12' y1='1' x2='12' y2='23'/%3E%3Cpath d='M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'/%3E%3C/svg%3E");
        }

        .menu-icon-reports::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cline x1='18' y1='20' x2='18' y2='10'/%3E%3Cline x1='12' y1='20' x2='12' y2='4'/%3E%3Cline x1='6' y1='20' x2='6' y2='14'/%3E%3C/svg%3E");
        }

        .menu-text {
            font-size: 15px;
            color: white;
            text-decoration: none;
        }

        .non-style-link-menu {
            text-decoration: none;
            color: white;
            display: block;
        }

        /* Main Content */
        .dash-body {
            flex: 1;
            margin-left: 280px;
            padding: 30px 50px;
        }

        .nav-bar {
            background: white;
            padding: 20px 40px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);
            border-left: 4px solid var(--primarycolor);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(8, 145, 178, 0.2);
        }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--primarycolor);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }

        /* Section Titles */
        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--primarycolor);
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--accentcolor);
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }

        .tab {
            padding: 12px 24px;
            background: transparent;
            border: none;
            color: #64748b;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab:hover {
            color: var(--primarycolor);
        }

        .tab.active {
            color: var(--primarycolor);
            border-bottom-color: var(--primarycolor);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Tables */
        .dashbord-tables {
            width: 100%;
            margin-top: 30px;
        }

        .sub-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .table-headin {
            background: var(--primarycolor);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        .sub-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }

        .sub-table tbody tr:hover {
            background: #f0fdfa;
        }

        .sub-table tbody td {
            padding: 12px 15px;
            font-size: 14px;
            color: #334155;
        }

        .scroll {
            overflow-y: auto;
            max-height: 500px;
        }

        .scroll::-webkit-scrollbar {
            width: 8px;
        }

        .scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .scroll::-webkit-scrollbar-thumb {
            background: var(--primarycolor);
            border-radius: 4px;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-paid {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-partial {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-cash {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-mpesa {
            background: #dcfce7;
            color: #166534;
        }

        .badge-card {
            background: #e0e7ff;
            color: #4338ca;
        }

        .badge-insurance {
            background: #ede9fe;
            color: #5b21b6;
        }

        /* Buttons */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--primarycolor);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primarydark);
        }

        .btn-secondary{
            background: #09cc23;
            color: white;
            font-size: 18px;
            font-weight: bold;
            border-radius: 15px;
            margin-bottom: 8px;
            padding: 14px 20px;
            cursor: pointer;

        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-info {
            background: var(--accentcolor);
            color: white;
        }

        .btn-info:hover {
            background: var(--primarylight);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }

        .modal-content {
            background: white;
            margin: 3% auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }

        .modal-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--primarycolor);
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primarycolor);
        }

        .form-control[readonly] {
            background: #f9fafb;
        }

        .bill-summary {
            background: #f0fdfa;
            border: 2px solid var(--accentcolor);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .bill-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .bill-row:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 18px;
            color: var(--primarydark);
            margin-top: 10px;
            padding-top: 15px;
            border-top: 2px solid var(--primarycolor);
        }

        .search-box {
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            max-width: 400px;
            padding: 10px 15px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        .info-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }

        .info-label {
            font-weight: 600;
            color: var(--primarydark);
        }

        .service-item {
            background: #f9fafb;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .action-buttons .btn {
            flex: 1;
            min-width: 80px;
            justify-content: center;
        }

        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        @keyframes transitionIn-Y-over {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes transitionIn-Y-bottom {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .dashbord-tables {
            animation: transitionIn-Y-over 0.5s;
        }

        .stats-grid {
            animation: transitionIn-Y-bottom 0.5s;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Main Content -->
        <div class="dash-body">
            <table border="0" width="100%" style="border-spacing: 0;margin:0;padding:0;">
                <tr>
                    <td colspan="2" class="nav-bar">
                        <div>
                            <p style="font-size: 28px;font-weight: 700;color: var(--primarycolor);margin:0;">Billing & Payments</p>
                            <p style="font-size: 14px;color: #64748b;margin-top:5px;">Process payments and manage billing</p>
                        </div>
                        <div style="text-align: right;">
                            <p style="font-size: 14px;color: #64748b;margin:0;">Today's Date</p>
                            <p style="font-size: 16px;font-weight: 600;color: var(--primarydark);margin:5px 0 0 0;"><?php echo date('F j, Y'); ?></p>
                        </div>
                    </td>
                </tr>

                <!-- Success/Error Alerts -->
                <?php if ($success_message): ?>
                <tr>
                    <td colspan="2">
                        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ($error_message): ?>
                <tr>
                    <td colspan="2">
                        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
                    </td>
                </tr>
                <?php endif; ?>

                <section>
                 <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;">
                                <button onclick="window.location.href='reports.php'" class="btn-secondary">View Reports</button>
                            </div>
                </section>
                <!-- Statistics -->
                <tr>
                    <td colspan="2">
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-value">KSh <?php echo number_format($total_revenue_today, 2); ?></div>
                                <div class="stat-label">Today's Revenue</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value">KSh <?php echo number_format($total_revenue_month, 2); ?></div>
                                <div class="stat-label">This Month</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $pending_count; ?></div>
                                <div class="stat-label">Pending Bills</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $payments_today; ?></div>
                                <div class="stat-label">Payments Today</div>
                            </div>
                            
                            </div>
                        </div>
                    </td>
                </tr>

                <!-- Tabs -->
                <tr>
                    <td colspan="2">
                        <div class="tabs">
                            <button class="tab active" onclick="switchTab('pending')">Pending Bills</button>
                            <button class="tab" onclick="switchTab('history')">Payment History</button>
                            <button class="tab" onclick="switchTab('consolidated')">Consolidated History</button>
                        </div>
                    </td>
                </tr>

                <!-- Pending Bills -->
                <tr>
                    <td colspan="2">
                        <div id="pending" class="tab-content active">
                            <div class="search-box">
                                <input type="text" id="searchPending" placeholder="Search by patient name..." onkeyup="searchTable('pendingTable')">
                            </div>
                            <div class="scroll">
                                <table width="100%" class="sub-table" border="0" id="pendingTable">
                                    <thead>
                                        <tr>
                                            <th class="table-headin">Appointment Date</th>
                                            <th class="table-headin">Patient</th>
                                            <th class="table-headin">Contact</th>
                                            <th class="table-headin">Consultation</th>
                                            <th class="table-headin">Pharmacy</th>
                                            <th class="table-headin">Lab</th>
                                            <th class="table-headin">Admission</th>
                                            <th class="table-headin">Total</th>
                                            <th class="table-headin">Status</th>
                                            <th class="table-headin">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if($pending_bills_result && $pending_bills_result->num_rows > 0): ?>
                                            <?php while($row = $pending_bills_result->fetch_assoc()): 
                                                $consultation = floatval($row['consultation_fee'] ?? 0);
                                                $pharmacy = floatval($row['pharmacy_total'] ?? 0);
                                                $lab = floatval($row['lab_total'] ?? 0);
                                                $admission = floatval($row['admission_total'] ?? 0);
                                                $total = $consultation + $pharmacy + $lab + $admission;
                                                $status = 'pending';
                                                if ($row['appointment_payment'] == 'partial') $status = 'partial';
                                            ?>
                                            <tr>
                                                <td><?php echo $row['appointment_date'] ? date('M d, Y', strtotime($row['appointment_date'])) : 'N/A'; ?></td>
                                                <td><strong><?php echo htmlspecialchars($row['patient_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                                <td>KSh <?php echo number_format($consultation, 2); ?></td>
                                                <td>KSh <?php echo number_format($pharmacy, 2); ?></td>
                                                <td>KSh <?php echo number_format($lab, 2); ?></td>
                                                <td>KSh <?php echo number_format($admission, 2); ?></td>
                                                <td><strong>KSh <?php echo number_format($total, 2); ?></strong></td>
                                                <td><span class="badge badge-<?php echo $status; ?>"><?php echo ucfirst($status); ?></span></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="btn btn-primary btn-sm" onclick='openPaymentModal(<?php echo json_encode($row); ?>)'>Process</button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="10" style="text-align:center;padding:30px;">No pending bills</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </td>
                </tr>

                <!-- Individual Payment History -->
                <tr>
                    <td colspan="2">
                        <div id="history" class="tab-content">
                            <div class="search-box">
                                <input type="text" id="searchHistory" placeholder="Search payment history..." onkeyup="searchTable('historyTable')">
                            </div>
                            <div class="scroll">
                                <table width="100%" class="sub-table" border="0" id="historyTable">
                                    <thead>
                                        <tr>
                                            <th class="table-headin">Receipt No</th>
                                            <th class="table-headin">Date</th>
                                            <th class="table-headin">Patient</th>
                                            <th class="table-headin">Service Type</th>
                                            <th class="table-headin">Description</th>
                                            <th class="table-headin">Amount</th>
                                            <th class="table-headin">Method</th>
                                            <th class="table-headin">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if($payment_history_result && $payment_history_result->num_rows > 0): ?>
                                            <?php while($row = $payment_history_result->fetch_assoc()): 
                                                $method_badge = 'badge-cash';
                                                if($row['payment_method'] == 'mpesa') $method_badge = 'badge-mpesa';
                                                if($row['payment_method'] == 'card') $method_badge = 'badge-card';
                                                if($row['payment_method'] == 'insurance') $method_badge = 'badge-insurance';
                                            ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($row['receipt_number']); ?></strong></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($row['payment_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                                                <td><?php echo ucfirst(htmlspecialchars($row['service_type'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['description']); ?></td>
                                                <td><strong>KSh <?php echo number_format($row['amount'], 2); ?></strong></td>
                                                <td><span class="badge <?php echo $method_badge; ?>"><?php echo ucfirst(htmlspecialchars($row['payment_method'])); ?></span></td>
                                                <td><span class="badge badge-paid">Paid</span></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="8" style="text-align:center;padding:30px;">No payment history</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </td>
                </tr>

                <!-- Consolidated Payment History -->
                <tr>
                    <td colspan="2">
                        <div id="consolidated" class="tab-content">
                            <div class="search-box">
                                <input type="text" id="searchConsolidated" placeholder="Search consolidated history..." onkeyup="searchTable('consolidatedTable')">
                            </div>
                            <div class="scroll">
                                <table width="100%" class="sub-table" border="0" id="consolidatedTable">
                                    <thead>
                                        <tr>
                                            <th class="table-headin">Receipt No</th>
                                            <th class="table-headin">Date</th>
                                            <th class="table-headin">Patient</th>
                                            <th class="table-headin">Services</th>
                                            <th class="table-headin">Consultation</th>
                                            <th class="table-headin">Pharmacy</th>
                                            <th class="table-headin">Lab</th>
                                            <th class="table-headin">Admission</th>
                                            <th class="table-headin">Total Amount</th>
                                            <th class="table-headin">Method</th>
                                            <th class="table-headin">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if($consolidated_history_result && $consolidated_history_result->num_rows > 0): ?>
                                            <?php while($row = $consolidated_history_result->fetch_assoc()): 
                                                $method_badge = 'badge-cash';
                                                if($row['payment_method'] == 'mpesa') $method_badge = 'badge-mpesa';
                                                if($row['payment_method'] == 'card') $method_badge = 'badge-card';
                                                if($row['payment_method'] == 'insurance') $method_badge = 'badge-insurance';
                                            ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($row['receipt_number']); ?></strong></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($row['payment_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['patient_name']); ?><br><small style="color:#64748b;"><?php echo htmlspecialchars($row['phone']); ?></small></td>
                                                <td><?php echo htmlspecialchars($row['services']); ?></td>
                                                <td>KSh <?php echo number_format($row['consultation_amount'], 2); ?></td>
                                                <td>KSh <?php echo number_format($row['pharmacy_amount'], 2); ?></td>
                                                <td>KSh <?php echo number_format($row['lab_amount'], 2); ?></td>
                                                <td>KSh <?php echo number_format($row['admission_amount'], 2); ?></td>
                                                <td><strong>KSh <?php echo number_format($row['total_amount'], 2); ?></strong></td>
                                                <td><span class="badge <?php echo $method_badge; ?>"><?php echo ucfirst(htmlspecialchars($row['payment_method'])); ?></span></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="btn btn-info btn-sm" onclick='viewConsolidatedReceipt(<?php echo json_encode($row); ?>)'>View</button>
                                                        <button class="btn btn-success btn-sm" onclick='printConsolidatedReceipt(<?php echo json_encode($row); ?>)'>Print</button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="11" style="text-align:center;padding:30px;">No consolidated payment history</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Process Payment</h2>
                <span class="close" onclick="closeModal('paymentModal')">&times;</span>
            </div>
            <form method="POST" id="paymentForm">
                <input type="hidden" name="patient_id" id="patient_id">
                <input type="hidden" name="appointment_id" id="appointment_id">
                <input type="hidden" name="patient_name" id="patient_name">
                <input type="hidden" name="consultation_fee" id="consultation_fee">
                <input type="hidden" name="pharmacy_fee" id="pharmacy_fee">
                <input type="hidden" name="lab_fee" id="lab_fee">
                <input type="hidden" name="admission_fee" id="admission_fee">
                <input type="hidden" name="total_amount" id="total_amount">

                <div class="info-card">
                    <h3 style="color: var(--primarycolor); margin-bottom: 15px;">Patient Information</h3>
                    <div class="info-row">
                        <span class="info-label">Name:</span>
                        <span id="modal_patient_name"></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Contact:</span>
                        <span id="modal_phone"></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Appointment Date:</span>
                        <span id="modal_appointment_date"></span>
                    </div>
                </div>

                <div class="bill-summary">
                    <h3 style="color: var(--primarydark); margin-bottom: 15px;">Bill Summary</h3>
                    <div class="bill-row">
                        <span>Consultation Fee:</span>
                        <span id="modal_consultation">KSh 0.00</span>
                    </div>
                    <div class="bill-row">
                        <span>Pharmacy Charges:</span>
                        <span id="modal_pharmacy">KSh 0.00</span>
                    </div>
                    <div class="bill-row">
                        <span>Laboratory Tests:</span>
                        <span id="modal_lab">KSh 0.00</span>
                    </div>
                    <div class="bill-row">
                        <span>Admission Charges:</span>
                        <span id="modal_admission">KSh 0.00</span>
                    </div>
                    <div class="bill-row">
                        <span>Total Amount:</span>
                        <span id="modal_total">KSh 0.00</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Method *</label>
                    <select class="form-control" name="payment_method" id="payment_method" required onchange="toggleTransactionField()">
                        <option value="">-- Select Payment Method --</option>
                        <option value="cash">Cash</option>
                        <option value="mpesa">M-Pesa</option>
                        <option value="card">Card</option>
                        <option value="insurance">Insurance</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                </div>

                <div class="form-group" id="transaction_field" style="display: none;">
                    <label class="form-label">Transaction ID / Reference Number</label>
                    <input type="text" class="form-control" name="transaction_id" placeholder="Enter transaction reference">
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closeModal('paymentModal')" style="background: #e5e7eb; color: #374151;">Cancel</button>
                    <button type="submit" name="process_payment" class="btn btn-success" id="processPaymentBtn">Complete Payment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Consolidated Receipt Modal -->
    <div id="receiptModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2 class="modal-title">Payment Receipt</h2>
                <span class="close" onclick="closeModal('receiptModal')">&times;</span>
            </div>
            
            <div id="receiptContent" style="padding: 20px;"></div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn" onclick="closeModal('receiptModal')" style="background: #e5e7eb; color: #374151;">Close</button>
                <button type="button" class="btn btn-success" onclick="printCurrentReceipt()">Print Receipt</button>
            </div>
        </div>
    </div>

    <script>
        let currentReceipt = null;

        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(btn => btn.classList.remove('active'));
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        function openPaymentModal(bill) {
            document.getElementById('patient_id').value = bill.patient_id;
            document.getElementById('appointment_id').value = bill.appointment_id || '';
            document.getElementById('patient_name').value = bill.patient_name;
            
            const consultation_fee = parseFloat(bill.consultation_fee) || 0;
            const pharmacy_fee = parseFloat(bill.pharmacy_total) || 0;
            const lab_fee = parseFloat(bill.lab_total) || 0;
            const admission_fee = parseFloat(bill.admission_total) || 0;
            const total_amount = consultation_fee + pharmacy_fee + lab_fee + admission_fee;
            
            document.getElementById('consultation_fee').value = consultation_fee;
            document.getElementById('pharmacy_fee').value = pharmacy_fee;
            document.getElementById('lab_fee').value = lab_fee;
            document.getElementById('admission_fee').value = admission_fee;
            document.getElementById('total_amount').value = total_amount;
            
            document.getElementById('modal_patient_name').textContent = bill.patient_name;
            document.getElementById('modal_phone').textContent = bill.phone || 'N/A';
            document.getElementById('modal_appointment_date').textContent = bill.appointment_date ? new Date(bill.appointment_date).toLocaleDateString() : 'N/A';
            document.getElementById('modal_consultation').textContent = 'KSh ' + consultation_fee.toLocaleString('en-KE', {minimumFractionDigits: 2});
            document.getElementById('modal_pharmacy').textContent = 'KSh ' + pharmacy_fee.toLocaleString('en-KE', {minimumFractionDigits: 2});
            document.getElementById('modal_lab').textContent = 'KSh ' + lab_fee.toLocaleString('en-KE', {minimumFractionDigits: 2});
            document.getElementById('modal_admission').textContent = 'KSh ' + admission_fee.toLocaleString('en-KE', {minimumFractionDigits: 2});
            document.getElementById('modal_total').textContent = 'KSh ' + total_amount.toLocaleString('en-KE', {minimumFractionDigits: 2});
            
            document.getElementById('paymentModal').style.display = 'block';
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
            if(id === 'paymentModal') {
                document.getElementById('paymentForm').reset();
                document.getElementById('transaction_field').style.display = 'none';
            }
        }

        function toggleTransactionField() {
            const method = document.getElementById('payment_method').value;
            document.getElementById('transaction_field').style.display = 
                ['mpesa', 'card', 'insurance', 'bank_transfer'].includes(method) ? 'block' : 'none';
        }

        function searchTable(tableId) {
            const inputMap = {
                'pendingTable': 'searchPending',
                'historyTable': 'searchHistory',
                'consolidatedTable': 'searchConsolidated'
            };
            const inputId = inputMap[tableId];
            const filter = document.getElementById(inputId).value.toUpperCase();
            const rows = document.getElementById(tableId).getElementsByTagName('tr');
            for (let i = 1; i < rows.length; i++) {
                rows[i].style.display = rows[i].textContent.toUpperCase().includes(filter) ? '' : 'none';
            }
        }

        function viewConsolidatedReceipt(receipt) {
            currentReceipt = receipt;
            const consultAmt = parseFloat(receipt.consultation_amount) || 0;
            const pharmAmt = parseFloat(receipt.pharmacy_amount) || 0;
            const labAmt = parseFloat(receipt.lab_amount) || 0;
            const admAmt = parseFloat(receipt.admission_amount) || 0;
            const totalAmt = parseFloat(receipt.total_amount) || 0;

            let servicesHtml = '';
            if (consultAmt > 0) {
                servicesHtml += `<div class="service-item"><div><strong>Consultation</strong><br><small style="color:#64748b;">Consultation Fee</small></div><span>KSh ${consultAmt.toLocaleString('en-KE', {minimumFractionDigits: 2})}</span></div>`;
            }
            if (pharmAmt > 0) {
                servicesHtml += `<div class="service-item"><div><strong>Pharmacy</strong><br><small style="color:#64748b;">Pharmacy Charges</small></div><span>KSh ${pharmAmt.toLocaleString('en-KE', {minimumFractionDigits: 2})}</span></div>`;
            }
            if (labAmt > 0) {
                servicesHtml += `<div class="service-item"><div><strong>Laboratory</strong><br><small style="color:#64748b;">Laboratory Tests</small></div><span>KSh ${labAmt.toLocaleString('en-KE', {minimumFractionDigits: 2})}</span></div>`;
            }
            if (admAmt > 0) {
                servicesHtml += `<div class="service-item"><div><strong>Admission</strong><br><small style="color:#64748b;">Hospital Admission</small></div><span>KSh ${admAmt.toLocaleString('en-KE', {minimumFractionDigits: 2})}</span></div>`;
            }

            document.getElementById('receiptContent').innerHTML = `
                <div style="border: 2px solid var(--primarycolor); border-radius: 8px; padding: 30px;">
                    <div style="text-align: center; border-bottom: 2px solid var(--primarycolor); padding-bottom: 20px; margin-bottom: 20px;">
                        <h2 style="color: var(--primarycolor); margin: 0;">Equity Afia Medical Hospital</h2>
                        <p style="color: #64748b; margin: 5px 0;">Consolidated Payment Receipt</p>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb;"><strong>Receipt No:</strong><span>${receipt.receipt_number}</span></div>
                        <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb;"><strong>Date:</strong><span>${new Date(receipt.payment_date).toLocaleString()}</span></div>
                        <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb;"><strong>Patient:</strong><span>${receipt.patient_name}</span></div>
                        <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb;"><strong>Contact:</strong><span>${receipt.phone || 'N/A'}</span></div>
                    </div>
                    <div style="background: #f0fdfa; padding: 15px; border-radius: 6px; margin: 20px 0;">
                        <h3 style="color: var(--primarydark); margin-bottom: 15px;">Services Rendered</h3>
                        ${servicesHtml}
                        <div style="display: flex; justify-content: space-between; padding: 15px 0; border-top: 2px solid var(--primarycolor); margin-top: 15px; font-size: 18px; font-weight: bold; color: var(--primarycolor);">
                            <span>Total Amount Paid:</span><span>KSh ${totalAmt.toLocaleString('en-KE', {minimumFractionDigits: 2})}</span>
                        </div>
                    </div>
                    <div style="margin-top: 20px;">
                        <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb;"><strong>Payment Method:</strong><span>${(receipt.payment_method || '').toUpperCase()}</span></div>
                        ${receipt.transaction_reference ? `<div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb;"><strong>Transaction ID:</strong><span>${receipt.transaction_reference}</span></div>` : ''}
                        <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb;"><strong>Processed By:</strong><span>${receipt.processed_by && receipt.processed_by !== '0' ? receipt.processed_by : '<?php echo htmlspecialchars($username); ?>'}</span></div>
                        <div style="display: flex; justify-content: space-between; padding: 10px 0;"><strong>Status:</strong><span style="background: #d1fae5; color: #065f46; padding: 4px 12px; border-radius: 20px;">PAID</span></div>
                    </div>
                    <div style="margin-top: 30px; padding-top: 20px; border-top: 2px dashed #d1d5db; text-align: center; color: #64748b; font-size: 12px;">
                        <p style="font-weight: 600; color: var(--primarycolor); font-size: 14px;">⚕️ IMPORTANT NOTICE ⚕️</p>
                        <p><strong>Please present this receipt when collecting medicine or for follow-up consultations.</strong></p>
                        <p style="margin-top: 10px;">Thank you for choosing Equity Afia Medical Hospital</p>
                    </div>
                </div>`;
            document.getElementById('receiptModal').style.display = 'block';
        }

        function printConsolidatedReceipt(receipt) {
            currentReceipt = receipt;
            printCurrentReceipt();
        }

        function printCurrentReceipt() {
            if (!currentReceipt) return;
            const r = currentReceipt;
            const consultAmt = parseFloat(r.consultation_amount) || 0;
            const pharmAmt = parseFloat(r.pharmacy_amount) || 0;
            const labAmt = parseFloat(r.lab_amount) || 0;
            const admAmt = parseFloat(r.admission_amount) || 0;
            const totalAmt = parseFloat(r.total_amount) || 0;

            let servicesHtml = '';
            if (consultAmt > 0) servicesHtml += `<div class="svc"><span>Consultation Fee</span><span>KSh ${consultAmt.toLocaleString('en-KE', {minimumFractionDigits: 2})}</span></div>`;
            if (pharmAmt > 0) servicesHtml += `<div class="svc"><span>Pharmacy Charges</span><span>KSh ${pharmAmt.toLocaleString('en-KE', {minimumFractionDigits: 2})}</span></div>`;
            if (labAmt > 0) servicesHtml += `<div class="svc"><span>Laboratory Tests</span><span>KSh ${labAmt.toLocaleString('en-KE', {minimumFractionDigits: 2})}</span></div>`;
            if (admAmt > 0) servicesHtml += `<div class="svc"><span>Hospital Admission</span><span>KSh ${admAmt.toLocaleString('en-KE', {minimumFractionDigits: 2})}</span></div>`;

            const w = window.open('', '', 'width=800,height=600');
            w.document.write(`<!DOCTYPE html><html><head><title>Receipt - ${r.receipt_number}</title>
            <style>
                body{font-family:'Segoe UI',sans-serif;padding:20px;max-width:800px;margin:0 auto}
                .container{border:2px solid #0891b2;border-radius:8px;padding:30px}
                .header{text-align:center;border-bottom:2px solid #0891b2;padding-bottom:20px;margin-bottom:20px}
                .header h2{color:#0891b2;margin:0}
                .row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #e5e7eb}
                .highlight{background:#f0fdfa;padding:15px;border-radius:6px;margin:20px 0}
                .svc{display:flex;justify-content:space-between;background:#f9fafb;padding:10px 15px;border-radius:6px;margin-bottom:8px}
                .total{display:flex;justify-content:space-between;padding:15px 0;border-top:2px solid #0891b2;margin-top:15px;font-size:18px;color:#0891b2;font-weight:bold}
                .footer{margin-top:30px;padding-top:20px;border-top:2px dashed #d1d5db;text-align:center;color:#64748b;font-size:12px}
                @media print{body{padding:0}}
            </style></head><body>
            <div class="container">
                <div class="header"><h2>Equity Afia Medical Hospital</h2><p style="color:#64748b">Consolidated Payment Receipt</p></div>
                <div class="row"><strong>Receipt No:</strong><span>${r.receipt_number}</span></div>
                <div class="row"><strong>Date:</strong><span>${new Date(r.payment_date).toLocaleString()}</span></div>
                <div class="row"><strong>Patient:</strong><span>${r.patient_name}</span></div>
                <div class="row"><strong>Contact:</strong><span>${r.phone || 'N/A'}</span></div>
                <div class="highlight">
                    <h3 style="color:#0891b2;margin-bottom:15px">Services Rendered</h3>
                    ${servicesHtml}
                    <div class="total"><span>Total Amount Paid:</span><span>KSh ${totalAmt.toLocaleString('en-KE', {minimumFractionDigits: 2})}</span></div>
                </div>
                <div class="row"><strong>Payment Method:</strong><span>${(r.payment_method || '').toUpperCase()}</span></div>
                ${r.transaction_reference ? `<div class="row"><strong>Transaction ID:</strong><span>${r.transaction_reference}</span></div>` : ''}
                <div class="row"><strong>Processed By:</strong><span>${r.processed_by && r.processed_by !== '0' ? r.processed_by : '<?php echo htmlspecialchars($username); ?>'}</span></div>
                <div class="row"><strong>Status:</strong><span style="background:#d1fae5;color:#065f46;padding:4px 12px;border-radius:20px">PAID</span></div>
                <div class="footer">
                    <p style="font-weight:600;color:#0891b2;font-size:14px">⚕️ IMPORTANT NOTICE ⚕️</p>
                    <p><strong>Please present this receipt for medicine dispensing or follow-up consultations.</strong></p>
                    <p>Thank you for choosing Equity Afia Medical Hospital</p>
                    <p style="margin-top:10px">Generated: ${new Date().toLocaleString()}</p>
                </div>
            </div></body></html>`);
            w.document.close();
            w.focus();
            w.print();
            setTimeout(() => { if(w && !w.closed) w.close(); }, 500);
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        }

        // Initialize first tab as active
        document.addEventListener('DOMContentLoaded', function() {
            switchTab('pending');
        });
    </script>
</body>
</html>