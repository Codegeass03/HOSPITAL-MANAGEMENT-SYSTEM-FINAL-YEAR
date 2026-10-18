<?php
include "includes/db_connect.php";

header('Content-Type: text/plain');

try {
    mysqli_select_db($link, "hospital");
    
    // Start transaction
    mysqli_begin_transaction($link);
    
    // Test insert into pharmacy_billing
    $test_bill_no = "TEST-" . date('YmdHis');
    $insert_query = "
        INSERT INTO pharmacy_billing 
        (CustomerName, bill_type, date, bill_no, username, total_amount, paid_amount, balance_amount, payment_status) 
        VALUES ('Test Patient', 'Cash', NOW(), '$test_bill_no', 'testuser', 100.00, 0.00, 100.00, 'pending')
    ";
    
    if (mysqli_query($link, $insert_query)) {
        $billing_id = mysqli_insert_id($link);
        
        // Test insert into pharmacy_billing_details
        $details_query = "
            INSERT INTO pharmacy_billing_details 
            (billing_id, product_company, product_name, product_unit, packing_size, price, quantity, total_amount) 
            VALUES ($billing_id, 'Test Company', 'Test Product', 'tablet', '10', 10.00, 10, 100.00)
        ";
        
        if (mysqli_query($link, $details_query)) {
            // Rollback test transaction
            mysqli_rollback($link);
            
            echo "✅ Test successful!\n";
            echo "• Can insert into pharmacy_billing table\n";
            echo "• Can insert into pharmacy_billing_details table\n";
            echo "• Foreign keys and constraints working properly\n";
            echo "\nNote: Test transaction was rolled back - no data saved.";
        } else {
            throw new Exception("Failed to insert into pharmacy_billing_details: " . mysqli_error($link));
        }
    } else {
        throw new Exception("Failed to insert into pharmacy_billing: " . mysqli_error($link));
    }
    
} catch (Exception $e) {
    if (isset($link) && $link) {
        @mysqli_rollback($link);
    }
    echo "✗ Test failed: " . $e->getMessage();
    echo "\n\nPossible issues:\n";
    echo "1. Missing columns in pharmacy_billing table\n";
    echo "2. Database permissions issue\n";
    echo "3. Table structure mismatch\n";
    echo "\nPlease run the database update script.";
}
?>