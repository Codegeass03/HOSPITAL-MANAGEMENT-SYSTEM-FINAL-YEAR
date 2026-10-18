<?php
session_start();
include "includes/db_connect.php";

$order_id = $_GET['order_id'] ?? null;
if (!$order_id) {
    die("Order ID required");
}

// Get order details for report
$stmt = $pdo->prepare("
    SELECT 
        lo.*,
        p.patient_id,
        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
        p.age,
        p.gender,
        p.phone,
        p.address,
        CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
        CONCAT(uc.first_name, ' ', uc.last_name) as collected_by_name,
        CONCAT(ut.first_name, ' ', ut.last_name) as tested_by_name,
        loi.*
    FROM lab_orders lo
    JOIN patients p ON lo.patient_id = p.id
    JOIN users u ON lo.ordered_by = u.id
    LEFT JOIN users uc ON lo.collected_by = uc.id
    LEFT JOIN users ut ON lo.tested_by = ut.id
    JOIN lab_order_items loi ON lo.id = loi.lab_order_id
    WHERE lo.id = ?
");

$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();
$order = $order_items[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lab Report - <?php echo $order['patient_name']; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .patient-info, .test-results { margin-bottom: 20px; }
        .result-item { margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; }
        .result-value { font-weight: bold; }
        .abnormal { color: #dc2626; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>LABORATORY REPORT</h1>
        <p>MediCare Hospital Laboratory</p>
    </div>
    
    <div class="patient-info">
        <h3>Patient Information</h3>
        <p><strong>Name:</strong> <?php echo $order['patient_name']; ?></p>
        <p><strong>Patient ID:</strong> <?php echo $order['patient_id']; ?></p>
        <p><strong>Age/Gender:</strong> <?php echo $order['age']; ?> years, <?php echo $order['gender']; ?></p>
        <p><strong>Ordering Doctor:</strong> Dr. <?php echo $order['doctor_name']; ?></p>
    </div>
    
    <div class="test-results">
        <h3>Test Results</h3>
        <?php foreach ($order_items as $item): ?>
            <div class="result-item">
                <h4><?php echo $item['test_name']; ?></h4>
                <p><strong>Result:</strong> 
                    <span class="result-value"><?php echo $item['result_value']; ?> <?php echo $item['result_unit']; ?></span>
                    <?php if ($item['reference_range']): ?>
                        <span>(Reference: <?php echo $item['reference_range']; ?>)</span>
                    <?php endif; ?>
                </p>
                <?php if ($item['result_notes']): ?>
                    <p><strong>Notes:</strong> <?php echo $item['result_notes']; ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="footer">
        <p><strong>Report Generated:</strong> <?php echo date('F j, Y g:i A'); ?></p>
        <?php if ($order['tested_by_name']): ?>
            <p><strong>Tested By:</strong> <?php echo $order['tested_by_name']; ?></p>
        <?php endif; ?>
    </div>
    
    <div class="no-print" style="margin-top: 20px;">
        <button onclick="window.print()">Print Report</button>
        <button onclick="window.close()">Close</button>
    </div>
</body>
</html>