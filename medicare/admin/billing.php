<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login.php");
    exit();
}

include("../connection.php");
date_default_timezone_set('Africa/Nairobi');

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $pid = intval($_POST['pid']);
    $appoid = intval($_POST['appoid']);
    $pname = $database->real_escape_string($_POST['pname']);
    $consultation_fee = floatval($_POST['consultation_fee']);
    $pharmacy_fee = floatval($_POST['pharmacy_fee']);
    $total_amount = floatval($_POST['total_amount']);
    $payment_method = $database->real_escape_string($_POST['payment_method']);
    $transaction_id = isset($_POST['transaction_id']) ? $database->real_escape_string($_POST['transaction_id']) : '';
    $processed_by = $database->real_escape_string($_SESSION['user']);
    $billing_id = isset($_POST['billing_id']) ? intval($_POST['billing_id']) : 0;

    // Generate receipt number
    $receipt_result = $database->query("SELECT MAX(CAST(SUBSTRING(receipt_no, 6) AS UNSIGNED)) as max_receipt FROM billing_payments");
    $max_receipt = $receipt_result->fetch_assoc()['max_receipt'] ?? 0;
    $receipt_no = 'RCPT-' . str_pad(($max_receipt + 1), 6, "0", STR_PAD_LEFT);

    $database->begin_transaction();

    try {
        // Insert consultation payment if applicable
        if ($consultation_fee > 0) {
            $stmt = $database->prepare("INSERT INTO billing_payments (pid, receipt_no, type, reference_id, description, amount, payment_method, status, transaction_id, paid_by, processed_by, payment_date) VALUES (?, ?, 'consultation', ?, 'Consultation Fee', ?, ?, 'paid', ?, ?, ?, NOW())");
            $stmt->bind_param("isidssss", $pid, $receipt_no, $appoid, $consultation_fee, $payment_method, $transaction_id, $pname, $processed_by);
            if (!$stmt->execute()) throw new Exception("Failed consultation payment: " . $stmt->error);
            $stmt->close();

            // Update patient charges
            $update_charges = $database->prepare("UPDATE patient_charges SET status='paid', paid_date=NOW(), paid_amount=? WHERE appoid=? AND charge_type='consultation' AND pid=?");
            $update_charges->bind_param("dii", $consultation_fee, $appoid, $pid);
            $update_charges->execute();
            $update_charges->close();
        }

        // Insert pharmacy payment if applicable
        if ($pharmacy_fee > 0 && $billing_id > 0) {
            $stmt = $database->prepare("INSERT INTO billing_payments (pid, receipt_no, type, reference_id, description, amount, payment_method, status, transaction_id, paid_by, processed_by, payment_date) VALUES (?, ?, 'pharmacy', ?, 'Pharmacy Charges', ?, ?, 'paid', ?, ?, ?, NOW())");
            $stmt->bind_param("isidssss", $pid, $receipt_no, $billing_id, $pharmacy_fee, $payment_method, $transaction_id, $pname, $processed_by);
            if (!$stmt->execute()) throw new Exception("Failed pharmacy payment: " . $stmt->error);
            $stmt->close();

            // Update billing status
            $update_billing = $database->prepare("UPDATE billing SET payment_status='paid', billing_stage='paid' WHERE id=?");
            $update_billing->bind_param("i", $billing_id);
            $update_billing->execute();
            $update_billing->close();
        }

        // Update appointment status
        $update_appointment = $database->prepare("UPDATE appointment SET billing_status='paid', workflow_stage='payment_complete' WHERE appoid=?");
        $update_appointment->bind_param("i", $appoid);
        $update_appointment->execute();
        $update_appointment->close();

        // Update ehr_prescriptions status if exists
        $update_prescriptions = $database->prepare("UPDATE ehr_prescriptions SET status='payment_complete' WHERE appoid=?");
        $update_prescriptions->bind_param("i", $appoid);
        $update_prescriptions->execute();
        $update_prescriptions->close();

        $database->commit();
        $_SESSION['payment_success'] = "Payment processed successfully! Receipt No: $receipt_no";
        header("Location: billing.php");
        exit();
    } catch (Exception $e) {
        $database->rollback();
        $_SESSION['payment_error'] = "Error: " . $e->getMessage();
    }
}

// Get pending bills
$pending_bills_query = "SELECT DISTINCT
    a.appoid, a.pid, a.appodate, a.billing_status, a.workflow_stage,
    p.pname, p.ptel, p.pemail,
    COALESCE(pc.amount, 0) as consultation_fee,
    COALESCE(pharm.total, 0) as pharmacy_fee,
    (COALESCE(pc.amount, 0) + COALESCE(pharm.total, 0)) as total_amount,
    b.id as billing_id, b.bill_no
FROM appointment a
INNER JOIN patient p ON a.pid = p.pid
LEFT JOIN patient_charges pc ON a.appoid = pc.appoid AND pc.status = 'pending' AND pc.charge_type = 'consultation'
LEFT JOIN billing b ON a.pid = b.pid AND a.appoid = b.appoid AND b.payment_status = 'pending'
LEFT JOIN (SELECT billing_id, SUM(price * quantity) as total FROM billing_details GROUP BY billing_id) pharm ON b.id = pharm.billing_id
WHERE a.status = 'Complete' 
AND ((pc.status = 'pending' AND pc.amount > 0) OR (b.payment_status = 'pending' AND pharm.total > 0))
ORDER BY a.appodate DESC";

$pending_bills_result = $database->query($pending_bills_query);

// Get CONSOLIDATED payment history - grouped by receipt number with all charges combined
$payment_history_query = "SELECT 
    bp.receipt_no,
    bp.pid,
    MIN(bp.payment_date) as payment_date,
    bp.payment_method,
    bp.transaction_id,
    bp.status,
    bp.processed_by,
    p.pname,
    p.ptel,
    SUM(bp.amount) as total_amount,
    MAX(CASE WHEN bp.type = 'consultation' THEN bp.amount ELSE 0 END) as consultation_amount,
    MAX(CASE WHEN bp.type = 'pharmacy' THEN bp.amount ELSE 0 END) as pharmacy_amount,
    GROUP_CONCAT(DISTINCT bp.type ORDER BY bp.type SEPARATOR ', ') as services,
    GROUP_CONCAT(DISTINCT CONCAT(bp.type, ':', bp.amount) ORDER BY bp.type SEPARATOR '|') as service_details
FROM billing_payments bp
INNER JOIN patient p ON bp.pid = p.pid
WHERE bp.status = 'paid'
GROUP BY bp.receipt_no, bp.pid, bp.payment_method, bp.transaction_id, bp.status, bp.processed_by, p.pname, p.ptel
ORDER BY MIN(bp.payment_date) DESC
LIMIT 50";

$payment_history_result = $database->query($payment_history_query);

// Get statistics
$total_revenue_today = $database->query("SELECT COALESCE(SUM(amount), 0) as total FROM billing_payments WHERE DATE(payment_date) = CURDATE() AND status='paid'")->fetch_assoc()['total'];
$total_revenue_month = $database->query("SELECT COALESCE(SUM(amount), 0) as total FROM billing_payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE()) AND status='paid'")->fetch_assoc()['total'];
$pending_count = $pending_bills_result ? $pending_bills_result->num_rows : 0;
$payments_today = $database->query("SELECT COUNT(DISTINCT receipt_no) as count FROM billing_payments WHERE DATE(payment_date) = CURDATE() AND status='paid'")->fetch_assoc()['count'];

$success_message = $_SESSION['payment_success'] ?? '';
$error_message = $_SESSION['payment_error'] ?? '';
unset($_SESSION['payment_success'], $_SESSION['payment_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing - Smart Clinic Management System</title>
    <style>
        :root {
            --primarycolor: #0891b2;
            --primarydark: #0e7490;
            --primarylight: #06b6d4;
            --secondarycolor: #14b8a6;
            --accentcolor: #22d3ee;
            --bgcolor: #f0fdfa;
            --textcolor: #134e4a;
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
            max-width: 700px;
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
        <!-- Sidebar Menu -->
        <div class="menu">
            <table class="menu-container" border="0">
                <tr>
                    <td style="padding:10px" colspan="2">
                        <table border="0" class="profile-container">
                            <tr>
                                <td width="30%" style="padding-left:20px">
                                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='50' fill='%23e0f2fe'/%3E%3Cpath d='M50 45c8.284 0 15-6.716 15-15s-6.716-15-15-15-15 6.716-15 15 6.716 15 15 15zm0 7.5c-10 0-30 5-30 15V75h60v-7.5c0-10-20-15-30-15z' fill='%230891b2'/%3E%3C/svg%3E" alt="" width="100%" style="border-radius:50%">
                                </td>
                                <td style="padding:0px;margin:0px;">
                                    <p class="profile-title">Administrator</p>
                                    <p class="profile-subtitle"><?php echo htmlspecialchars($_SESSION['user']); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <a href="../logout.php"><input type="button" value="Log out" class="logout-btn"></a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-dashbord">
                        <a href="index.php" class="non-style-link-menu"><p class="menu-text">Dashboard</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor">
                        <a href="staff.php" class="non-style-link-menu"><p class="menu-text">Staff Management</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor">
                        <a href="doctors.php" class="non-style-link-menu"><p class="menu-text">Doctor Management</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-schedule">
                        <a href="schedule.php" class="non-style-link-menu"><p class="menu-text">Schedule</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appoinment">
                        <a href="appointment.php" class="non-style-link-menu"><p class="menu-text">Appointment</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-patient">
                        <a href="patient.php" class="non-style-link-menu"><p class="menu-text">Patients</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-pharmacy">
                        <a href="pharmacy.php" class="non-style-link-menu"><p class="menu-text">Pharmacy</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-billing menu-active">
                        <a href="billing.php" class="non-style-link-menu"><p class="menu-text">Billing</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-reports">
                        <a href="reports.php" class="non-style-link-menu"><p class="menu-text">Reports</p></a>
                    </td>
                </tr>
            </table>
        </div>

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
                    </td>
                </tr>

                <!-- Tabs -->
                <tr>
                    <td colspan="2">
                        <div class="tabs">
                            <button class="tab active" onclick="switchTab('pending')">Pending Bills</button>
                            <button class="tab" onclick="switchTab('history')">Payment History</button>
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
                                            <th class="table-headin">Date</th>
                                            <th class="table-headin">Patient</th>
                                            <th class="table-headin">Contact</th>
                                            <th class="table-headin">Consultation</th>
                                            <th class="table-headin">Pharmacy</th>
                                            <th class="table-headin">Total</th>
                                            <th class="table-headin">Status</th>
                                            <th class="table-headin">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if($pending_bills_result && $pending_bills_result->num_rows > 0): ?>
                                            <?php while($row = $pending_bills_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($row['appodate'])); ?></td>
                                                <td><strong><?php echo htmlspecialchars($row['pname']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($row['ptel']); ?></td>
                                                <td>KSh <?php echo number_format($row['consultation_fee'], 2); ?></td>
                                                <td>KSh <?php echo number_format($row['pharmacy_fee'], 2); ?></td>
                                                <td><strong>KSh <?php echo number_format($row['total_amount'], 2); ?></strong></td>
                                                <td><span class="badge badge-pending">Pending</span></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="btn btn-primary btn-sm" onclick='openPaymentModal(<?php echo json_encode($row); ?>)'>Process</button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="8" style="text-align:center;padding:30px;">No pending bills</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </td>
                </tr>

                <!-- Payment History - CONSOLIDATED BILLS -->
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
                                            <th class="table-headin">Consultation</th>
                                            <th class="table-headin">Pharmacy</th>
                                            <th class="table-headin">Total Amount</th>
                                            <th class="table-headin">Method</th>
                                            <th class="table-headin">Status</th>
                                            <th class="table-headin">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if($payment_history_result && $payment_history_result->num_rows > 0): ?>
                                            <?php while($row = $payment_history_result->fetch_assoc()): 
                                                $method_badge = 'badge-cash';
                                                if($row['payment_method'] == 'mpesa') $method_badge = 'badge-mpesa';
                                                if($row['payment_method'] == 'card') $method_badge = 'badge-card';
                                            ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($row['receipt_no']); ?></strong></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($row['payment_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['pname']); ?><br><small style="color:#64748b;"><?php echo htmlspecialchars($row['ptel']); ?></small></td>
                                                <td>KSh <?php echo number_format($row['consultation_amount'], 2); ?></td>
                                                <td>KSh <?php echo number_format($row['pharmacy_amount'], 2); ?></td>
                                                <td><strong>KSh <?php echo number_format($row['total_amount'], 2); ?></strong></td>
                                                <td><span class="badge <?php echo $method_badge; ?>"><?php echo strtoupper(htmlspecialchars($row['payment_method'])); ?></span></td>
                                                <td><span class="badge badge-paid">Paid</span></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="btn btn-info btn-sm" onclick='viewReceipt(<?php echo json_encode($row); ?>)'>View</button>
                                                        <button class="btn btn-success btn-sm" onclick='printReceipt(<?php echo json_encode($row); ?>)'>Print</button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="9" style="text-align:center;padding:30px;">No payment history</td></tr>
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
                <input type="hidden" name="pid" id="pid">
                <input type="hidden" name="appoid" id="appoid">
                <input type="hidden" name="pname" id="pname">
                <input type="hidden" name="consultation_fee" id="consultation_fee">
                <input type="hidden" name="pharmacy_fee" id="pharmacy_fee">
                <input type="hidden" name="total_amount" id="total_amount">
                <input type="hidden" name="billing_id" id="billing_id">

                <div class="info-card">
                    <h3 style="color: var(--primarycolor); margin-bottom: 15px;">Patient Information</h3>
                    <div class="info-row">
                        <span class="info-label">Name:</span>
                        <span id="modal_pname"></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Contact:</span>
                        <span id="modal_ptel"></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Appointment Date:</span>
                        <span id="modal_appodate"></span>
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
                    </select>
                </div>

                <!-- <div class="form-group" id="transaction_field" style="display: none;">
                    <label class="form-label">Transaction ID / Reference Number</label>
                    <input type="text" class="form-control" name="transaction_id" placeholder="Enter transaction reference">
                </div> -->

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closeModal('paymentModal')" style="background: #e5e7eb; color: #374151;">Cancel</button>
                    <button type="submit" name="process_payment" class="btn btn-success" id="processPaymentBtn">Complete Payment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Receipt Modal -->
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
            document.getElementById('pid').value = bill.pid;
            document.getElementById('appoid').value = bill.appoid;
            document.getElementById('pname').value = bill.pname;
            document.getElementById('consultation_fee').value = bill.consultation_fee || 0;
            document.getElementById('pharmacy_fee').value = bill.pharmacy_fee || 0;
            document.getElementById('total_amount').value = bill.total_amount || 0;
            document.getElementById('billing_id').value = bill.billing_id || 0;
            
            document.getElementById('modal_pname').textContent = bill.pname;
            document.getElementById('modal_ptel').textContent = bill.ptel || 'N/A';
            document.getElementById('modal_appodate').textContent = bill.appodate ? new Date(bill.appodate).toLocaleDateString() : 'N/A';
            document.getElementById('modal_consultation').textContent = 'KSh ' + parseFloat(bill.consultation_fee || 0).toLocaleString('en-KE', {minimumFractionDigits: 2});
            document.getElementById('modal_pharmacy').textContent = 'KSh ' + parseFloat(bill.pharmacy_fee || 0).toLocaleString('en-KE', {minimumFractionDigits: 2});
            document.getElementById('modal_total').textContent = 'KSh ' + parseFloat(bill.total_amount || 0).toLocaleString('en-KE', {minimumFractionDigits: 2});
            
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
                ['mpesa', 'card', 'bank_transfer'].includes(method) ? 'block' : 'none';
        }

        function searchTable(tableId) {
            const inputId = tableId === 'pendingTable' ? 'searchPending' : 'searchHistory';
            const filter = document.getElementById(inputId).value.toUpperCase();
            const rows = document.getElementById(tableId).getElementsByTagName('tr');
            for (let i = 1; i < rows.length; i++) {
                rows[i].style.display = rows[i].textContent.toUpperCase().includes(filter) ? '' : 'none';
            }
        }

        // View consolidated receipt
        function viewReceipt(receipt) {
            currentReceipt = receipt;
            const consultAmt = parseFloat(receipt.consultation_amount) || 0;
            const pharmAmt = parseFloat(receipt.pharmacy_amount) || 0;
            const totalAmt = parseFloat(receipt.total_amount) || 0;

            let servicesHtml = '';
            if (consultAmt > 0) {
                servicesHtml += `<div class="service-item"><div><strong>Consultation</strong><br><small style="color:#64748b;">Consultation Fee</small></div><span>KSh ${consultAmt.toLocaleString('en-KE', {minimumFractionDigits: 2})}</span></div>`;
            }
            if (pharmAmt > 0) {
                servicesHtml += `<div class="service-item"><div><strong>Pharmacy</strong><br><small style="color:#64748b;">Pharmacy Charges</small></div><span>KSh ${pharmAmt.toLocaleString('en-KE', {minimumFractionDigits: 2})}</span></div>`;
            }

            document.getElementById('receiptContent').innerHTML = `
                <div style="border: 2px solid var(--primarycolor); border-radius: 8px; padding: 30px;">
                    <div style="text-align: center; border-bottom: 2px solid var(--primarycolor); padding-bottom: 20px; margin-bottom: 20px;">
                        <h2 style="color: var(--primarycolor); margin: 0;">Smart Clinic Management System</h2>
                        <p style="color: #64748b; margin: 5px 0;">Consolidated Payment Receipt</p>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb;"><strong>Receipt No:</strong><span>${receipt.receipt_no}</span></div>
                        <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb;"><strong>Date:</strong><span>${new Date(receipt.payment_date).toLocaleString()}</span></div>
                        <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb;"><strong>Patient:</strong><span>${receipt.pname}</span></div>
                        <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb;"><strong>Contact:</strong><span>${receipt.ptel || 'N/A'}</span></div>
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
                        ${receipt.transaction_id ? `<div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb;"><strong>Transaction ID:</strong><span>${receipt.transaction_id}</span></div>` : ''}
                        <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb;"><strong>Processed By:</strong><span>${receipt.processed_by || 'N/A'}</span></div>
                        <div style="display: flex; justify-content: space-between; padding: 10px 0;"><strong>Status:</strong><span style="background: #d1fae5; color: #065f46; padding: 4px 12px; border-radius: 20px;">PAID</span></div>
                    </div>
                    <div style="margin-top: 30px; padding-top: 20px; border-top: 2px dashed #d1d5db; text-align: center; color: #64748b; font-size: 12px;">
                        <p style="font-weight: 600; color: var(--primarycolor); font-size: 14px;">⚕️ IMPORTANT NOTICE ⚕️</p>
                        <p><strong>Please present this receipt to the pharmacy to pick your medicine.</strong></p>
                        <p style="margin-top: 10px;">Thank you for choosing Smart Clinic Management System</p>
                    </div>
                </div>`;
            document.getElementById('receiptModal').style.display = 'block';
        }

        function printReceipt(receipt) {
            currentReceipt = receipt;
            printCurrentReceipt();
        }

        function printCurrentReceipt() {
            if (!currentReceipt) return;
            const r = currentReceipt;
            const consultAmt = parseFloat(r.consultation_amount) || 0;
            const pharmAmt = parseFloat(r.pharmacy_amount) || 0;
            const totalAmt = parseFloat(r.total_amount) || 0;

            let servicesHtml = '';
            if (consultAmt > 0) servicesHtml += `<div class="svc"><span>Consultation Fee</span><span>KSh ${consultAmt.toLocaleString('en-KE', {minimumFractionDigits: 2})}</span></div>`;
            if (pharmAmt > 0) servicesHtml += `<div class="svc"><span>Pharmacy Charges</span><span>KSh ${pharmAmt.toLocaleString('en-KE', {minimumFractionDigits: 2})}</span></div>`;

            const w = window.open('', '', 'width=800,height=600');
            w.document.write(`<!DOCTYPE html><html><head><title>Receipt - ${r.receipt_no}</title>
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
                <div class="header"><h2>Smart Clinic Management System</h2><p style="color:#64748b">Consolidated Payment Receipt</p></div>
                <div class="row"><strong>Receipt No:</strong><span>${r.receipt_no}</span></div>
                <div class="row"><strong>Date:</strong><span>${new Date(r.payment_date).toLocaleString()}</span></div>
                <div class="row"><strong>Patient:</strong><span>${r.pname}</span></div>
                <div class="row"><strong>Contact:</strong><span>${r.ptel || 'N/A'}</span></div>
                <div class="highlight">
                    <h3 style="color:#0891b2;margin-bottom:15px">Services Rendered</h3>
                    ${servicesHtml}
                    <div class="total"><span>Total Amount Paid:</span><span>KSh ${totalAmt.toLocaleString('en-KE', {minimumFractionDigits: 2})}</span></div>
                </div>
                <div class="row"><strong>Payment Method:</strong><span>${(r.payment_method || '').toUpperCase()}</span></div>
                ${r.transaction_id ? `<div class="row"><strong>Transaction ID:</strong><span>${r.transaction_id}</span></div>` : ''}
                <div class="row"><strong>Processed By:</strong><span>${r.processed_by || 'N/A'}</span></div>
                <div class="row"><strong>Status:</strong><span style="background:#d1fae5;color:#065f46;padding:4px 12px;border-radius:20px">PAID</span></div>
                <div class="footer">
                    <p style="font-weight:600;color:#0891b2;font-size:14px">⚕️ IMPORTANT NOTICE ⚕️</p>
                    <p><strong>Please present this receipt to the pharmacy for medicine dispensing.</strong></p>
                    <p>Thank you for choosing Smart Clinic Management System</p>
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
    </script>
</body>
</html>