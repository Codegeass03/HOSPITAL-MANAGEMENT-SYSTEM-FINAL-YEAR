<?php
include "includes/db_connect.php";
include "admin/connection.php";

mysqli_select_db($link, "hospital");

// First, let's check the structure of the unified_billing table
echo "<h3>Checking unified_billing table structure:</h3>";
try {
    $structure_query = "DESCRIBE unified_billing";
    $structure_stmt = $pdo->query($structure_query);
    $columns = $structure_stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in unified_billing: " . implode(', ', $columns) . "<br><br>";
} catch (Exception $e) {
    echo "Error checking structure: " . $e->getMessage() . "<br>";
}

// Get a sample bill to debug
$sample_query = "
    SELECT 
        pb.id,
        pb.patient_id,
        pb.bill_no,
        pb.date,
        pb.total_amount,
        pb.paid_amount,
        pb.balance_amount,
        pb.payment_status,
        pb.payment_method,
        pb.CustomerName
    FROM pharmacy_billing pb
    WHERE pb.patient_id IS NOT NULL
    LIMIT 1
";

echo "<h3>Sample Bill Data:</h3>";
$sample_result = mysqli_query($link, $sample_query);
if ($sample_result && $sample_bill = mysqli_fetch_assoc($sample_bill)) {
    echo "<pre>";
    print_r($sample_bill);
    echo "</pre>";
    
    // Test the insert with this sample
    echo "<h3>Testing Insert with Sample Bill:</h3>";
    try {
        $test_stmt = $pdo->prepare("
            INSERT INTO unified_billing 
            (patient_id, bill_number, bill_date, service_type, service_description, 
             total_amount, paid_amount, balance_amount, status, payment_method)
            VALUES (?, ?, ?, 'pharmacy', ?, ?, ?, ?, ?, ?)
        ");
        
        $service_description = "Pharmacy Bill - " . $sample_bill['CustomerName'];
        
        $params = [
            $sample_bill['patient_id'],
            $sample_bill['bill_no'],
            $sample_bill['date'],
            $service_description,
            $sample_bill['total_amount'],
            $sample_bill['paid_amount'],
            $sample_bill['balance_amount'],
            $sample_bill['payment_status'],
            $sample_bill['payment_method']
        ];
        
        echo "Number of parameters in query: 10<br>";
        echo "Number of values being bound: " . count($params) . "<br>";
        echo "Parameters: <pre>";
        print_r($params);
        echo "</pre>";
        
        $test_stmt->execute($params);
        echo "✅ Test insert successful!<br>";
        
        // Clean up test record
        $pdo->query("DELETE FROM unified_billing WHERE bill_number = '" . $sample_bill['bill_no'] . "'");
        
    } catch (Exception $e) {
        echo "❌ Test insert failed: " . $e->getMessage() . "<br>";
    }
} else {
    echo "No sample bill found or query failed.<br>";
}
?>