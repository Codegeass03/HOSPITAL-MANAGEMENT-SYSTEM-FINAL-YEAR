<?php
// Start session and enforce authentication
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
include("../connection.php");

// Set header for JSON response
header('Content-Type: application/json');

// Check if receipt number is provided
if (!isset($_GET['receipt_no']) || empty($_GET['receipt_no'])) {
    echo json_encode(['success' => false, 'message' => 'Receipt number is required']);
    exit();
}

$receipt_no = $database->real_escape_string($_GET['receipt_no']);

try {
    // Get receipt header information
    $receipt_query = "SELECT 
        bp.receipt_no,
        bp.pid,
        bp.payment_date,
        bp.payment_method,
        bp.transaction_id,
        bp.status,
        bp.processed_by,
        p.pname,
        p.ptel,
        p.pemail
    FROM billing_payments bp
    INNER JOIN patient p ON bp.pid = p.pid
    WHERE bp.receipt_no = '$receipt_no'
    LIMIT 1";
    
    $receipt_result = $database->query($receipt_query);
    
    if (!$receipt_result || $receipt_result->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Receipt not found']);
        exit();
    }
    
    $receipt = $receipt_result->fetch_assoc();
    
    // Get all services/items in this receipt
    $services_query = "SELECT 
        type,
        description,
        amount,
        reference_id
    FROM billing_payments
    WHERE receipt_no = '$receipt_no'
    ORDER BY type";
    
    $services_result = $database->query($services_query);
    
    $services = [];
    $total_amount = 0;
    
    while ($service = $services_result->fetch_assoc()) {
        $services[] = [
            'type' => $service['type'],
            'description' => $service['description'],
            'amount' => floatval($service['amount']),
            'reference_id' => $service['reference_id']
        ];
        $total_amount += floatval($service['amount']);
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'receipt' => [
            'receipt_no' => $receipt['receipt_no'],
            'pid' => $receipt['pid'],
            'pname' => $receipt['pname'],
            'ptel' => $receipt['ptel'],
            'pemail' => $receipt['pemail'],
            'payment_date' => $receipt['payment_date'],
            'payment_method' => $receipt['payment_method'],
            'transaction_id' => $receipt['transaction_id'],
            'status' => $receipt['status'],
            'processed_by' => $receipt['processed_by'],
            'total_amount' => $total_amount,
            'services' => $services
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching receipt: ' . $e->getMessage()]);
}

$database->close();
?>