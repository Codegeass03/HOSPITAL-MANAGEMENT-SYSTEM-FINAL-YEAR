<?php
session_start();
include("admin/connection.php");
include("includes/db_connect.php");
include("config/database.php");
date_default_timezone_set('Africa/Nairobi');

header('Content-Type: application/json');

// Check if receipt_number is provided
if (!isset($_GET['receipt_number']) || empty($_GET['receipt_number'])) {
    echo json_encode(['success' => false, 'message' => 'Receipt number is required']);
    exit();
}

$receipt_number = $database->real_escape_string($_GET['receipt_number']);

// Get receipt details
$receipt_query = "
SELECT 
    bp.receipt_number,
    bp.patient_name,
    bp.service_type,
    bp.description,
    bp.amount,
    bp.payment_method,
    bp.transaction_reference,
    bp.processed_by_name,
    bp.payment_date,
    p.patient_id as patient_number,
    GROUP_CONCAT(DISTINCT CONCAT_WS('|', bp.service_type, bp.reference_id, bp.description, bp.amount) SEPARATOR ';') as services_data
FROM billing_payments bp
LEFT JOIN patients p ON bp.patient_id = p.id
WHERE bp.receipt_number = ?
GROUP BY bp.receipt_number";

$stmt = $database->prepare($receipt_query);
$stmt->bind_param("s", $receipt_number);
$stmt->execute();
$receipt_result = $stmt->get_result();

if (!$receipt_result || $receipt_result->num_rows === 0) {
    // Try to get consolidated receipt
    $consolidated_query = "
    SELECT 
        cp.consolidated_receipt as receipt_number,
        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
        cp.total_amount as amount,
        'consolidated' as service_type,
        'Multiple Services Payment' as description,
        cp.services_included as services_data,
        '' as payment_method,
        '' as transaction_reference,
        CONCAT(u.first_name, ' ', u.last_name) as processed_by_name,
        cp.created_at as payment_date,
        p.patient_id as patient_number
    FROM consolidated_payments cp
    INNER JOIN patients p ON cp.patient_id = p.id
    LEFT JOIN users u ON cp.created_by = u.id
    WHERE cp.consolidated_receipt = ?";
    
    $stmt = $database->prepare($consolidated_query);
    $stmt->bind_param("s", $receipt_number);
    $stmt->execute();
    $consolidated_result = $stmt->get_result();
    
    if ($consolidated_result->num_rows > 0) {
        $receipt = $consolidated_result->fetch_assoc();
        $services = [];
        
        if (!empty($receipt['services_data'])) {
            $service_items = explode(',', $receipt['services_data']);
            foreach ($service_items as $item) {
                $parts = explode(':', $item);
                if (count($parts) >= 2) {
                    $services[] = [
                        'service_type' => $parts[0],
                        'amount' => $parts[1],
                        'description' => ucfirst($parts[0]) . ' Charges'
                    ];
                }
            }
        }
        
        $receipt['services'] = $services;
        echo json_encode(['success' => true, 'receipt' => $receipt]);
        exit();
    }
    
    echo json_encode(['success' => false, 'message' => 'Receipt not found']);
    exit();
}

$receipt = $receipt_result->fetch_assoc();

// Parse services data
$services = [];
if (!empty($receipt['services_data'])) {
    $service_items = explode(';', $receipt['services_data']);
    foreach ($service_items as $item) {
        $parts = explode('|', $item);
        if (count($parts) >= 4) {
            $services[] = [
                'service_type' => $parts[0],
                'reference_id' => $parts[1],
                'description' => $parts[2],
                'amount' => $parts[3]
            ];
        }
    }
}

$receipt['services'] = $services;

echo json_encode(['success' => true, 'receipt' => $receipt]);
?>