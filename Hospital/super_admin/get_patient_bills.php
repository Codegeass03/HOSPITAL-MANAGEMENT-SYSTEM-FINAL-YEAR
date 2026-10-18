<?php
session_start();
include("admin/connection.php");
include("includes/db_connect.php");
include("config/database.php");
date_default_timezone_set('Africa/Nairobi');

header('Content-Type: application/json');

// Check if patient_id is provided
if (!isset($_GET['patient_id']) || empty($_GET['patient_id'])) {
    echo json_encode(['success' => false, 'message' => 'Patient ID is required']);
    exit();
}

$patient_id = intval($_GET['patient_id']);

// Get patient details
$patient_query = "SELECT id, patient_id as patient_number, CONCAT(first_name, ' ', last_name) as patient_name FROM patients WHERE id = ?";
$stmt = $database->prepare($patient_query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient_result = $stmt->get_result();
$patient = $patient_result->fetch_assoc();

if (!$patient) {
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit();
}

// Get patient's pending bills from consolidated_patient_bills view
$bills_query = "
SELECT 
    billing_id,
    service_type,
    bill_number,
    patient_id,
    patient_name,
    service_description,
    total_amount,
    paid_amount,
    balance_amount,
    status,
    bill_date
FROM consolidated_patient_bills 
WHERE patient_id = ? AND balance_amount > 0
ORDER BY bill_date DESC";

$stmt = $database->prepare($bills_query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$bills_result = $stmt->get_result();

$bills = [];
while ($row = $bills_result->fetch_assoc()) {
    $bills[] = $row;
}

// If no bills found in consolidated view, try to get bills from individual tables
if (empty($bills)) {
    // Get consultation bills
    $consultation_query = "
    SELECT 
        'consultation' as service_type,
        bill_number,
        total_amount,
        paid_amount,
        balance_amount,
        status,
        bill_date,
        'Consultation Fee' as service_description
    FROM billing 
    WHERE patient_id = ? AND balance_amount > 0 AND bill_number LIKE 'BL-%'";
    
    $stmt = $database->prepare($consultation_query);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $consultation_result = $stmt->get_result();
    
    while ($row = $consultation_result->fetch_assoc()) {
        $row['patient_id'] = $patient_id;
        $row['patient_name'] = $patient['patient_name'];
        $bills[] = $row;
    }
    
    // Get pharmacy bills
    $pharmacy_query = "
    SELECT 
        'pharmacy' as service_type,
        bill_no as bill_number,
        total_amount,
        paid_amount,
        balance_amount,
        payment_status as status,
        date as bill_date,
        CONCAT('Pharmacy Bill #', bill_no) as service_description
    FROM pharmacy_billing 
    WHERE patient_id = ? AND (balance_amount > 0 OR payment_status = 'pending')";
    
    $stmt = $database->prepare($pharmacy_query);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $pharmacy_result = $stmt->get_result();
    
    while ($row = $pharmacy_result->fetch_assoc()) {
        $row['patient_id'] = $patient_id;
        $row['patient_name'] = $patient['patient_name'];
        $bills[] = $row;
    }
    
    // Get lab bills
    $lab_query = "
    SELECT 
        'lab' as service_type,
        order_number as bill_number,
        total_amount,
        paid_amount,
        (total_amount - paid_amount) as balance_amount,
        payment_status as status,
        order_date as bill_date,
        CONCAT('Lab Order #', order_number) as service_description
    FROM lab_orders 
    WHERE patient_id = ? AND (total_amount - paid_amount) > 0";
    
    $stmt = $database->prepare($lab_query);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $lab_result = $stmt->get_result();
    
    while ($row = $lab_result->fetch_assoc()) {
        $row['patient_id'] = $patient_id;
        $row['patient_name'] = $patient['patient_name'];
        $bills[] = $row;
    }
}

// Prepare response
$response = [
    'success' => true,
    'patient' => $patient,
    'bills' => $bills
];

echo json_encode($response);
?>