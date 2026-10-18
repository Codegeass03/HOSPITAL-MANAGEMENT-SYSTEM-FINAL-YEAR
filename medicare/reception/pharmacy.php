<?php
session_start();

if(isset($_SESSION["user"])){
    if(($_SESSION["user"])=="" or $_SESSION['usertype']!='a'){
        header("location: ../login.php");
    }
}else{
    header("location: ../login.php");
}

include("../connection.php");

// Handle dispensing action
if(isset($_POST['dispense_prescription'])){
    $prescription_id = intval($_POST['prescription_id']);
    $appoid = intval($_POST['appoid']);
    $pid = intval($_POST['pid']);
    
    // Update prescription status
    $update_prescription = $database->prepare("UPDATE ehr_prescriptions SET status='dispensed' WHERE id=?");
    $update_prescription->bind_param("i", $prescription_id);
    $update_prescription->execute();
    
    // Update appointment workflow
    $update_appointment = $database->prepare("UPDATE appointment SET workflow_stage='dispensed' WHERE appoid=?");
    $update_appointment->bind_param("i", $appoid);
    $update_appointment->execute();
    
    echo "<script>alert('Prescription dispensed successfully!'); window.location.href='pharmacy.php';</script>";
}

// Handle status update (for dropdown actions)
if(isset($_POST['update_prescription_status'])){
    $prescription_id = intval($_POST['prescription_id']);
    $new_status = $database->real_escape_string($_POST['new_status']);
    $appoid = intval($_POST['appoid']);
    
    $update_prescription = $database->prepare("UPDATE ehr_prescriptions SET status=? WHERE id=?");
    $update_prescription->bind_param("si", $new_status, $prescription_id);
    $update_prescription->execute();
    
    // Update appointment workflow based on status
    $workflow_stage = 'dispensed';
    if($new_status == 'payment_complete') $workflow_stage = 'payment_complete';
    if($new_status == 'released') $workflow_stage = 'completed';
    
    $update_appointment = $database->prepare("UPDATE appointment SET workflow_stage=? WHERE appoid=?");
    $update_appointment->bind_param("si", $workflow_stage, $appoid);
    $update_appointment->execute();
    
    echo "<script>alert('Status updated successfully!'); window.location.href='pharmacy.php';</script>";
}

// Handle adding medication to bill
if(isset($_POST['add_to_bill'])){
    $prescription_id = intval($_POST['prescription_id']);
    $appoid = intval($_POST['appoid']);
    $pid = intval($_POST['pid']);
    $product_company = $database->real_escape_string($_POST['product_company']);
    $product_name = $database->real_escape_string($_POST['product_name']);
    $quantity = intval($_POST['quantity']);
    $price = floatval($_POST['price']);
    $pname = $database->real_escape_string($_POST['pname']);
    
    // Check if billing record exists for this patient and appointment
    $check_bill = $database->prepare("SELECT * FROM billing WHERE pid=? AND appoid=?");
    $check_bill->bind_param("ii", $pid, $appoid);
    $check_bill->execute();
    $bill_result = $check_bill->get_result();
    
    if($bill_result->num_rows == 0){
        // Create new billing record
        $bill_no = "PHARM-" . str_pad($pid, 4, "0", STR_PAD_LEFT) . $appoid;
        $insert_bill = $database->prepare("INSERT INTO billing (pid, appoid, CustomerName, bill_type, date, bill_no, username, payment_status, billing_stage) VALUES (?, ?, ?, 'Cash', NOW(), ?, ?, 'pending', 'pending_pharmacy')");
        $insert_bill->bind_param("iisss", $pid, $appoid, $pname, $bill_no, $_SESSION['user']);
        $insert_bill->execute();
        $billing_id = $database->insert_id;
    } else {
        $bill_data = $bill_result->fetch_assoc();
        $billing_id = $bill_data['id'];
    }
    
    // Add billing detail
    $insert_detail = $database->prepare("INSERT INTO billing_details (billing_id, product_company, product_name, price, quantity) VALUES (?, ?, ?, ?, ?)");
    $insert_detail->bind_param("issdi", $billing_id, $product_company, $product_name, $price, $quantity);
    $insert_detail->execute();
    
    // Update stock
    $update_stock = $database->prepare("UPDATE stock_master SET product_quantity = product_quantity - ? WHERE product_name=? AND product_company=?");
    $update_stock->bind_param("iss", $quantity, $product_name, $product_company);
    $update_stock->execute();
    
    // Update prescription status to dispensed
    $update_prescription = $database->prepare("UPDATE ehr_prescriptions SET status='dispensed' WHERE id=?");
    $update_prescription->bind_param("i", $prescription_id);
    $update_prescription->execute();
    
    // Update appointment workflow
    $update_appointment = $database->prepare("UPDATE appointment SET workflow_stage='dispensed' WHERE appoid=?");
    $update_appointment->bind_param("i", $appoid);
    $update_appointment->execute();
    
    // Update billing stage to ready for payment
    $update_billing_stage = $database->prepare("UPDATE billing SET billing_stage='ready_for_payment' WHERE id=?");
    $update_billing_stage->bind_param("i", $billing_id);
    $update_billing_stage->execute();
    
    echo "<script>alert('Medication added to bill successfully!'); window.location.href='pharmacy.php';</script>";
}

// Get statistics
$total_products = $database->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'] ?? 0;
$low_stock_count = $database->query("SELECT COUNT(*) as count FROM stock_master WHERE product_quantity < 10")->fetch_assoc()['count'] ?? 0;
$total_suppliers = $database->query("SELECT COUNT(*) as count FROM suppliers")->fetch_assoc()['count'] ?? 0;

// Get pending prescriptions grouped by patient
$pending_regular_prescriptions = $database->query("
    SELECT p.pid, p.pname, p.ptel, p.pemail,
           COUNT(pres.id) as prescription_count,
           GROUP_CONCAT(DISTINCT pres.medication_name SEPARATOR '; ') as medications
    FROM patient p
    INNER JOIN prescriptions pres ON p.pid = pres.patient_id 
    WHERE pres.status = 'active' 
    AND (pres.dispensed = 0 OR pres.dispensed IS NULL)
    GROUP BY p.pid, p.pname, p.ptel, p.pemail
");

// For e-prescriptions
$pending_eprescriptions_list = $database->query("
    SELECT p.pid, p.pname, p.ptel, p.pemail,
           COUNT(ep.id) as prescription_count,
           GROUP_CONCAT(DISTINCT ep.medication SEPARATOR '; ') as medications
    FROM patient p
    INNER JOIN appointment a ON p.pid = a.pid
    INNER JOIN ehr_prescriptions ep ON a.appoid = ep.appoid
    WHERE ep.status = 'pending_pharmacy'
    GROUP BY p.pid, p.pname, p.ptel, p.pemail
");

// Combine both results
$pending_patients_data = [];
$patient_ids = [];

if ($pending_regular_prescriptions && $pending_regular_prescriptions->num_rows > 0) {
    while($row = $pending_regular_prescriptions->fetch_assoc()) {
        if (!in_array($row['pid'], $patient_ids)) {
            $row['prescription_type'] = 'regular';
            $pending_patients_data[] = $row;
            $patient_ids[] = $row['pid'];
        }
    }
}

if ($pending_eprescriptions_list && $pending_eprescriptions_list->num_rows > 0) {
    while($row = $pending_eprescriptions_list->fetch_assoc()) {
        if (!in_array($row['pid'], $patient_ids)) {
            $row['prescription_type'] = 'ehr';
            $pending_patients_data[] = $row;
            $patient_ids[] = $row['pid'];
        }
    }
}

// Get pharmacy bills with REAL payment status from billing_payments
$pharmacy_bills = $database->query("
    SELECT b.*, 
           p.pname as patient_name,
           p.ptel,
           a.appodate,
           COALESCE((SELECT SUM(bd.price * bd.quantity) 
            FROM billing_details bd 
            WHERE bd.billing_id = b.id), 0) as total_amount,
           COALESCE(bp.status, 'pending') as actual_payment_status,
           bp.receipt_no,
           bp.payment_date as paid_date,
           bp.payment_method
    FROM billing b
    LEFT JOIN patient p ON b.pid = p.pid
    LEFT JOIN appointment a ON b.appoid = a.appoid
    LEFT JOIN billing_payments bp ON b.id = bp.reference_id AND bp.type = 'pharmacy'
    ORDER BY b.date DESC
    LIMIT 20
");

// Get pending prescriptions (all statuses for tracking)
$pending_prescriptions = "SELECT ep.*, a.pid, a.appodate, p.pname, p.ptel, d.docname,
                         b.id as billing_id, b.bill_no, b.payment_status as bill_payment_status,
                         COALESCE(bp.status, 'pending') as payment_status,
                         bp.receipt_no
                         FROM ehr_prescriptions ep
                         INNER JOIN appointment a ON ep.appoid = a.appoid
                         INNER JOIN patient p ON a.pid = p.pid
                         INNER JOIN schedule s ON a.scheduleid = s.scheduleid
                         INNER JOIN doctor d ON s.docid = d.docid
                         LEFT JOIN billing b ON a.appoid = b.appoid AND a.pid = b.pid
                         LEFT JOIN billing_payments bp ON b.id = bp.reference_id AND bp.type = 'pharmacy'
                         ORDER BY ep.created_at DESC";
$prescriptions_result = $database->query($pending_prescriptions);

// Get inventory items
$inventory_query = "SELECT * FROM stock_master WHERE product_quantity > 0 ORDER BY product_name";
$inventory_result = $database->query($inventory_query);

// Get low stock items
$low_stock = $database->query("
    SELECT * FROM stock_master 
    WHERE product_quantity < 10
    ORDER BY product_quantity ASC
    LIMIT 5
");

// Get recent purchases
$recent_purchases = $database->query("
    SELECT pm.*, s.businessname 
    FROM purchase_master pm
    LEFT JOIN suppliers s ON pm.party_name = s.businessname
    ORDER BY pm.purchase_date DESC
    LIMIT 5
");

date_default_timezone_set('Africa/Nairobi');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy - Smart Clinic Management System</title>
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

        /* Sidebar Styles - Identical to other files */
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

        .menu-icon-pills::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z'/%3E%3Cpath d='M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z'/%3E%3Cpath d='M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0'/%3E%3Cpath d='M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5'/%3E%3C/svg%3E");
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

        /* Main Content - Identical to other files */
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

        /* Statistics Cards - Matching dashboard style */
        .stats-container {
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

        .stat-number {
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

        /* Pharmacy Menu - Updated to match dashboard cards */
        .pharmacy-menu {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .pharmacy-menu-item {
            background: white;
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.08);
            border-top: 3px solid var(--primarycolor);
        }

        .pharmacy-menu-item:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 20px rgba(8, 145, 178, 0.15);
        }

        .pharmacy-menu-item i {
            font-size: 48px;
            margin-bottom: 20px;
            color: var(--primarycolor);
        }

        .pharmacy-menu-item h3 {
            margin: 0 0 8px 0;
            font-size: 20px;
            font-weight: 600;
            color: var(--primarydark);
        }

        .pharmacy-menu-item p {
            margin: 0;
            font-size: 14px;
            color: #64748b;
        }

        /* Tabs - Matching dashboard button style */
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
            animation: transitionIn-Y-bottom 0.5s;
        }

        .tab-content.active {
            display: block;
        }

        /* Tables - Matching dashboard table style */
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow-x: auto;
            margin-bottom: 30px;
        }

        .table-container table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--primarycolor);
            color: white;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }

        tbody tr:hover {
            background: #f0fdfa;
        }

        td {
            padding: 12px 15px;
            font-size: 14px;
            color: #334155;
        }

        /* Badges - Enhanced status badges */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-pending {
            background: rgba(251, 191, 36, 0.1);
            color: #92400e;
        }

        .badge-dispensed {
            background: rgba(59, 130, 246, 0.1);
            color: #1e40af;
        }

        .badge-paid {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
        }

        .badge-released {
            background: rgba(139, 92, 246, 0.1);
            color: #5b21b6;
        }

        .badge-low-stock {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
        }

        .badge-in-stock {
            background: rgba(16, 185, 129, 0.1);
            color: #166534;
        }

        .badge-primary {
            background: rgba(8, 145, 178, 0.1);
            color: var(--primarycolor);
        }

        .badge-warning {
            background: rgba(251, 191, 36, 0.1);
            color: #92400e;
        }

        /* Buttons - Enhanced button styles */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(8, 145, 178, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, var(--accentcolor) 0%, var(--primarylight) 100%);
            color: white;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        /* Dropdown styles - Enhanced from first file */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-btn {
            background: var(--primarycolor);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }

        .dropdown-btn:hover {
            background: var(--primarydark);
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: white;
            min-width: 180px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
            z-index: 100;
            border-radius: 8px;
            overflow: hidden;
            animation: fadeIn 0.3s;
        }

        .dropdown-content a, .dropdown-content button {
            color: #334155;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            width: 100%;
            text-align: left;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }

        .dropdown-content a:hover, .dropdown-content button:hover {
            background: #f0fdfa;
            color: var(--primarycolor);
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        /* Modal - Enhanced modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            animation: slideDown 0.3s;
            max-height: 85vh;
            overflow-y: auto;
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
            font-size: 24px;
            font-weight: 700;
            color: var(--primarycolor);
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close:hover {
            color: var(--primarydark);
        }

        /* Form Elements - Enhanced */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--textcolor);
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primarycolor);
            box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.1);
        }

        .form-control[readonly] {
            background: #f9fafb;
            opacity: 0.7;
        }

        /* Prescription Card - Enhanced */
        .prescription-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .prescription-card:hover {
            box-shadow: 0 4px 12px rgba(8, 145, 178, 0.15);
        }

        /* Search Box - Enhanced */
        .search-box {
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            max-width: 400px;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primarycolor);
            box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.1);
        }

        /* Additional Sections - Enhanced */
        .section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.08);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f1f5f9;
        }

        .section-header h3 {
            color: var(--primarycolor);
            font-size: 20px;
            font-weight: 600;
        }

        .low-stock-alert {
            color: var(--primarydark);
            font-weight: 600;
        }

        /* Action buttons container for responsive design */
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

        /* Empty State - Enhanced */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #cbd5e1;
        }

        /* Animations - Matching other files */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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

        .table-container {
            animation: transitionIn-Y-over 0.5s;
        }

        .pharmacy-menu {
            animation: transitionIn-Y-bottom 0.6s;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .dash-body {
                padding: 20px 30px;
            }
        }

        @media (max-width: 992px) {
            .dash-body {
                margin-left: 0;
                padding: 20px;
            }
            .menu {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            .menu.active {
                transform: translateX(0);
            }
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            .pharmacy-menu {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .nav-bar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .stats-container {
                grid-template-columns: 1fr;
            }
            .pharmacy-menu {
                grid-template-columns: 1fr;
            }
            .tabs {
                flex-wrap: wrap;
            }
            .tab {
                flex: 1;
                min-width: 120px;
                text-align: center;
            }
            .table-container {
                padding: 10px;
            }
            .table-container table {
                min-width: 1000px;
            }
            th, td {
                padding: 10px 8px;
                font-size: 12px;
            }
        }

        @media (max-width: 576px) {
            .dash-body {
                padding: 15px;
            }
            .modal-content {
                margin: 5% auto;
                padding: 20px;
                width: 95%;
            }
            .btn {
                padding: 10px 16px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar Menu - Enhanced with proper icons -->
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
                    <td class="menu-btn menu-icon-pills menu-active">
                        <a href="pharmacy.php" class="non-style-link-menu"><p class="menu-text">Pharmacy</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-billing">
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
            <div class="nav-bar">
                <div>
                    <p style="font-size: 28px;font-weight: 700;color: var(--primarycolor);margin:0;">Pharmacy Management</p>
                    <p style="font-size: 14px;color: #64748b;margin-top:5px;">Dispense prescriptions and manage inventory</p>
                </div>
                <div style="text-align: right;">
                    <p style="font-size: 14px;color: #64748b;margin:0;">Today's Date</p>
                    <p style="font-size: 16px;font-weight: 600;color: var(--primarydark);margin:5px 0 0 0;"><?php echo date('F j, Y'); ?></p>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_products; ?></div>
                    <div class="stat-label">Total Products</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $low_stock_count; ?></div>
                    <div class="stat-label">Low Stock Items</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_suppliers; ?></div>
                    <div class="stat-label">Total Suppliers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($pending_patients_data); ?></div>
                    <div class="stat-label">Pending Prescriptions</div>
                </div>
            </div>

            <!-- Pharmacy Menu -->
            <div class="pharmacy-menu">
                <a href="pharmacy_inventory.php" class="pharmacy-menu-item">
                    <i class="fas fa-boxes"></i>
                    <h3>Inventory</h3>
                    <p>Manage stock & products</p>
                </a>
                <a href="pharmacy_suppliers.php" class="pharmacy-menu-item">
                    <i class="fas fa-building"></i>
                    <h3>Suppliers</h3>
                    <p>Manage suppliers</p>
                </a>
                <a href="pharmacy_purchase.php" class="pharmacy-menu-item">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Purchase</h3>
                    <p>Buy new stock</p>
                </a>
                <a href="pharmacy_dispense.php" class="pharmacy-menu-item">
                    <i class="fas fa-pills"></i>
                    <h3>Dispense</h3>
                    <p>Dispense prescriptions</p>
                </a>
                <a href="pharmacy_products.php" class="pharmacy-menu-item">
                    <i class="fas fa-list-alt"></i>
                    <h3>Products</h3>
                    <p>Product catalog</p>
                </a>
                <a href="pharmacy_bills.php" class="pharmacy-menu-item">
                    <i class="fas fa-file-invoice"></i>
                    <h3>View Bills</h3>
                    <p>Sales transactions</p>
                </a>
            </div>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" onclick="switchTab('prescriptions')">E-Prescriptions</button>
                <button class="tab" onclick="switchTab('bills')">Pharmacy Bills</button>
                <button class="tab" onclick="switchTab('inventory')">Inventory</button>
            </div>

            <!-- Prescriptions Tab -->
            <div id="prescriptions" class="tab-content active">
                <div class="search-box">
                    <input type="text" id="searchPrescription" placeholder="Search by patient name, medication..." onkeyup="searchTable('prescriptionTable')">
                </div>

                <div class="table-container">
                    <table id="prescriptionTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Medication</th>
                                <th>Dosage</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if($prescriptions_result && $prescriptions_result->num_rows > 0) {
                                while($row = $prescriptions_result->fetch_assoc()) {
                                    // Status badge
                                    $status_class = 'badge-pending';
                                    $status_text = 'Pending';
                                    if($row['status'] == 'dispensed') { $status_class = 'badge-dispensed'; $status_text = 'Dispensed'; }
                                    if($row['status'] == 'payment_complete') { $status_class = 'badge-paid'; $status_text = 'Paid'; }
                                    if($row['status'] == 'released') { $status_class = 'badge-released'; $status_text = 'Released'; }
                                    
                                    // Payment status
                                    $payment_class = 'badge-pending';
                                    $payment_text = 'Unpaid';
                                    if($row['payment_status'] == 'paid') { $payment_class = 'badge-paid'; $payment_text = 'Paid'; }
                                    
                                    echo "<tr>
                                        <td>".date('M d, Y', strtotime($row['created_at']))."</td>
                                        <td><strong>".$row['pname']."</strong><br><small>".$row['ptel']."</small></td>
                                        <td>".$row['docname']."</td>
                                        <td><strong>".$row['medication']."</strong></td>
                                        <td>".$row['dosage']." - ".$row['frequency']."</td>
                                        <td><span class='badge $status_class'>$status_text</span></td>
                                        <td><span class='badge $payment_class'>$payment_text</span></td>
                                        <td>";
if($row['status'] == 'pending_pharmacy') {
    echo "<div class='action-buttons'>
        <button class='btn btn-primary btn-sm' onclick='openDispenseModal(".json_encode($row).")'>Dispense</button>
    </div>";
} else {
    // Dropdown for status management
    echo "<div class='dropdown'>
        <button class='dropdown-btn'>Actions ▼</button>
        <div class='dropdown-content'>";
    
    // Debug info (remove after testing)
    echo "<!-- DEBUG: status={$row['status']}, payment_status={$row['payment_status']}, bill_payment_status={$row['bill_payment_status']} -->";
    
    // Show "Mark as Released" if payment is completed
    if($row['payment_status'] == 'paid' || $row['bill_payment_status'] == 'paid') {
        echo "<form method='POST' style='margin:0;'>
            <input type='hidden' name='prescription_id' value='".$row['id']."'>
            <input type='hidden' name='appoid' value='".$row['appoid']."'>
            <input type='hidden' name='new_status' value='released'>
            <button type='submit' name='update_prescription_status'>✓ Mark as Released</button>
        </form>";
    }
    
    echo "<button onclick='viewDetails(".json_encode($row).")'>👁 View Details</button>";
    
    if($row['receipt_no']) {
        echo "<button onclick='alert(\"Receipt: ".$row['receipt_no']."\")'>🧾 View Receipt</button>";
    }
    
    echo "</div></div>";
}
echo "</td><tr>";
                                }
                            } else {
                                echo "<tr><td colspan='8' style='text-align:center;padding:30px;'>
                                    <div class='empty-state'>
                                        <i class='fas fa-file-prescription'></i>
                                        <p>No prescriptions found</p>
                                        <small>All prescriptions have been processed</small>
                                    </div>
                                </td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pharmacy Bills Tab -->
            <div id="bills" class="tab-content">
                <div class="search-box">
                    <input type="text" id="searchBills" placeholder="Search bills..." onkeyup="searchTable('billsTable')">
                </div>

                <div class="table-container">
                    <table id="billsTable">
                        <thead>
                            <tr>
                                <th>Bill No</th>
                                <th>Date</th>
                                <th>Patient</th>
                                <th>Amount (KSh)</th>
                                <th>Payment Status</th>
                                <th>Receipt No</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if($pharmacy_bills && $pharmacy_bills->num_rows > 0) {
                                while($row = $pharmacy_bills->fetch_assoc()) {
                                    $payment_class = $row['actual_payment_status'] == 'paid' ? 'badge-paid' : 'badge-pending';
                                    $payment_text = $row['actual_payment_status'] == 'paid' ? 'Paid' : 'Pending';
                                    
                                    echo "<tr>
                                        <td><strong>".$row['bill_no']."</strong></td>
                                        <td>".date('M d, Y', strtotime($row['date']))."</td>
                                        <td>".$row['patient_name']."<br><small>".$row['ptel']."</small></td>
                                        <td><strong>KSh ".number_format($row['total_amount'], 2)."</strong></td>
                                        <td><span class='badge $payment_class'>$payment_text</span></td>
                                        <td>".($row['receipt_no'] ?? '-')."</td>
                                        <td>
                                            <div class='action-buttons'>
                                                <button class='btn btn-info btn-sm' onclick='viewBillDetails(".$row['id'].")'>View</button>
                                            </div>
                                        </td>
                                    </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7' style='text-align:center;padding:30px;'>
                                    <div class='empty-state'>
                                        <i class='fas fa-file-invoice'></i>
                                        <p>No pharmacy bills found</p>
                                    </div>
                                </td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Inventory Tab -->
            <div id="inventory" class="tab-content">
                <div class="search-box">
                    <input type="text" id="searchInventory" placeholder="Search inventory..." onkeyup="searchTable('inventoryTable')">
                </div>

                <div class="table-container">
                    <table id="inventoryTable">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Company</th>
                                <th>Unit</th>
                                <th>Pack Size</th>
                                <th>Quantity</th>
                                <th>Price (KSh)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $inventory_result->data_seek(0);
                            if($inventory_result->num_rows > 0) {
                                while($row = $inventory_result->fetch_assoc()) {
                                    $stock_status = $row['product_quantity'] < 20 ? 'badge-low-stock' : 'badge-in-stock';
                                    $stock_text = $row['product_quantity'] < 20 ? 'Low Stock' : 'In Stock';
                                    
                                    echo "<tr>
                                        <td><strong>".$row['product_name']."</strong></td>
                                        <td>".$row['product_company']."</td>
                                        <td>".$row['product_unit']."</td>
                                        <td>".$row['packing_size']."</td>
                                        <td><strong>".$row['product_quantity']."</strong></td>
                                        <td>".number_format($row['product_selling_price'], 2)."</td>
                                        <td><span class='badge $stock_status'>$stock_text</span></td>
                                    </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7' style='text-align:center;padding:30px;'>
                                    <div class='empty-state'>
                                        <i class='fas fa-boxes'></i>
                                        <p>No inventory items found</p>
                                    </div>
                                </td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Low Stock Alert Section -->
            <div class="section">
                <div class="section-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Low Stock Alert</h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Company</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Price</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($low_stock && $low_stock->num_rows > 0) {
                                while($row = $low_stock->fetch_assoc()) {
                                    echo "<tr>
                                        <td>" . htmlspecialchars($row['product_name']) . "</td>
                                        <td>" . htmlspecialchars($row['product_company']) . "</td>
                                        <td class='low-stock-alert'>" . htmlspecialchars($row['product_quantity']) . "</td>
                                        <td>" . htmlspecialchars($row['product_unit']) . "</td>
                                        <td>KES " . number_format($row['product_selling_price'], 2) . "</td>
                                        <td><a href='pharmacy_purchase.php?product=" . urlencode($row['product_name']) . "' class='btn btn-sm btn-primary'>Order Now</a></td>
                                    </tr>";
                                }
                            } else {
                                echo "<tr>
                                        <td colspan='6'>
                                            <div class='empty-state'>
                                                <i class='fas fa-check-circle'></i>
                                                <p>All items are well stocked!</p>
                                            </div>
                                        </td>
                                      </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Purchases Section -->
            <div class="section">
                <div class="section-header">
                    <h3><i class="fas fa-history"></i> Recent Purchases</h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Company</th>
                                <th>Supplier</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($recent_purchases->num_rows > 0) {
                                while($row = $recent_purchases->fetch_assoc()) {
                                    $total = $row['quantity'] * $row['price'];
                                    echo "<tr>
                                        <td>" . date('M d, Y', strtotime($row['purchase_date'])) . "</td>
                                        <td>" . htmlspecialchars($row['product_name']) . "</td>
                                        <td>" . htmlspecialchars($row['company_name']) . "</td>
                                        <td>" . htmlspecialchars($row['businessname'] ?? 'N/A') . "</td>
                                        <td>" . htmlspecialchars($row['quantity']) . " " . htmlspecialchars($row['unit']) . "</td>
                                        <td>KES " . number_format($row['price'], 2) . "</td>
                                        <td>KES " . number_format($total, 2) . "</td>
                                    </tr>";
                                }
                            } else {
                                echo "<tr>
                                        <td colspan='7'>
                                            <div class='empty-state'>
                                                <i class='fas fa-shopping-cart'></i>
                                                <p>No recent purchases</p>
                                            </div>
                                        </td>
                                      </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Dispense Modal -->
    <div id="dispenseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Dispense Medication</h2>
                <span class="close" onclick="closeModal('dispenseModal')">&times;</span>
            </div>
            <form method="POST" id="dispenseForm">
                <input type="hidden" name="prescription_id" id="prescription_id">
                <input type="hidden" name="appoid" id="appoid">
                <input type="hidden" name="pid" id="pid">
                <input type="hidden" name="pname" id="pname">
                
                <div class="form-group">
                    <label class="form-label">Patient Name</label>
                    <input type="text" class="form-control" id="modal_pname" readonly>
                </div>

                <div class="form-group">
                    <label class="form-label">Medication</label>
                    <input type="text" class="form-control" id="modal_medication" readonly>
                </div>

                <div class="form-group">
                    <label class="form-label">Dosage & Frequency</label>
                    <input type="text" class="form-control" id="modal_dosage" readonly>
                </div>

                <div class="form-group">
                    <label class="form-label">Duration</label>
                    <input type="text" class="form-control" id="modal_duration" readonly>
                </div>

                <div class="form-group">
                    <label class="form-label">Instructions</label>
                    <textarea class="form-control" id="modal_instructions" rows="3" readonly></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Select Product from Inventory *</label>
                    <select class="form-control" name="product_company" id="product_select" required onchange="updateProductDetails()">
                        <option value="">-- Select Product --</option>
                        <?php
                        $inventory_result->data_seek(0);
                        while($row = $inventory_result->fetch_assoc()) {
                            echo "<option value='".$row['product_company']."' 
                                         data-name='".$row['product_name']."' 
                                         data-price='".$row['product_selling_price']."'
                                         data-stock='".$row['product_quantity']."'>
                                  ".$row['product_name']." - ".$row['product_company']." (Stock: ".$row['product_quantity'].")
                                  </option>";
                        }
                        ?>
                    </select>
                </div>

                <input type="hidden" name="product_name" id="product_name">
                <input type="hidden" name="price" id="product_price">

                <div class="form-group">
                    <label class="form-label">Quantity to Dispense *</label>
                    <input type="number" class="form-control" name="quantity" id="quantity" min="1" required>
                    <small id="stock_info" style="color: #64748b;"></small>
                </div>

                <div class="form-group">
                    <label class="form-label">Total Amount (KSh)</label>
                    <input type="text" class="form-control" id="total_amount" readonly>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closeModal('dispenseModal')" style="background: #e5e7eb; color: #374151;">Cancel</button>
                    <button type="submit" name="add_to_bill" class="btn btn-primary">Add to Bill & Dispense</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Details Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Prescription Details</h2>
                <span class="close" onclick="closeModal('viewModal')">&times;</span>
            </div>
            <div id="viewContent"></div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            const tabButtons = document.querySelectorAll('.tab');
            tabButtons.forEach(btn => btn.classList.remove('active'));
            
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        function openDispenseModal(prescription) {
            document.getElementById('prescription_id').value = prescription.id;
            document.getElementById('appoid').value = prescription.appoid;
            document.getElementById('pid').value = prescription.pid;
            document.getElementById('pname').value = prescription.pname;
            document.getElementById('modal_pname').value = prescription.pname;
            document.getElementById('modal_medication').value = prescription.medication;
            document.getElementById('modal_dosage').value = prescription.dosage + ' - ' + prescription.frequency;
            document.getElementById('modal_duration').value = prescription.duration;
            document.getElementById('modal_instructions').value = prescription.instructions || 'No special instructions';
            
            document.getElementById('dispenseModal').style.display = 'block';
        }

        function viewDetails(prescription) {
            const content = `
                <div class="prescription-card">
                    <div style="margin-bottom: 15px;">
                        <strong style="color: var(--primarycolor);">Patient:</strong> ${prescription.pname}<br>
                        <strong style="color: var(--primarycolor);">Doctor:</strong> ${prescription.docname}<br>
                        <strong style="color: var(--primarycolor);">Date:</strong> ${new Date(prescription.created_at).toLocaleDateString()}
                    </div>
                    <div class="medication-details">
                        <strong>Medication:</strong> ${prescription.medication}<br>
                        <strong>Dosage:</strong> ${prescription.dosage}<br>
                        <strong>Frequency:</strong> ${prescription.frequency}<br>
                        <strong>Duration:</strong> ${prescription.duration}<br>
                        <strong>Instructions:</strong> ${prescription.instructions || 'None'}
                    </div>
                    <div style="margin-top: 15px;">
                        <span class="badge badge-dispensed">Dispensed</span>
                    </div>
                </div>
            `;
            document.getElementById('viewContent').innerHTML = content;
            document.getElementById('viewModal').style.display = 'block';
        }

        function viewBillDetails(billingId) {
            alert('View bill details for ID: ' + billingId);
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            if(modalId === 'dispenseModal') {
                document.getElementById('dispenseForm').reset();
            }
        }

        function updateProductDetails() {
            const select = document.getElementById('product_select');
            const selectedOption = select.options[select.selectedIndex];
            
            if(selectedOption.value) {
                const productName = selectedOption.getAttribute('data-name');
                const price = parseFloat(selectedOption.getAttribute('data-price'));
                const stock = parseInt(selectedOption.getAttribute('data-stock'));
                
                document.getElementById('product_name').value = productName;
                document.getElementById('product_price').value = price;
                document.getElementById('quantity').max = stock;
                document.getElementById('stock_info').textContent = `Available stock: ${stock} units`;
                
                document.getElementById('quantity').addEventListener('input', function() {
                    const quantity = parseInt(this.value) || 0;
                    const total = (price * quantity).toFixed(2);
                    document.getElementById('total_amount').value = 'KSh ' + total;
                });
            }
        }

        function searchTable(tableId) {
            const input = tableId === 'prescriptionTable' ? 
                         document.getElementById('searchPrescription') : 
                         tableId === 'billsTable' ? 'searchBills' : 'searchInventory';
            const filter = document.getElementById(input).value.toUpperCase();
            const table = document.getElementById(tableId);
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                let txtValue = tr[i].textContent || tr[i].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = '';
                } else {
                    tr[i].style.display = 'none';
                }
            }
        }

        window.onclick = function(event) {
            const dispenseModal = document.getElementById('dispenseModal');
            const viewModal = document.getElementById('viewModal');
            if (event.target == dispenseModal) {
                closeModal('dispenseModal');
            }
            if (event.target == viewModal) {
                closeModal('viewModal');
            }
        }
    </script>
</body>
</html>