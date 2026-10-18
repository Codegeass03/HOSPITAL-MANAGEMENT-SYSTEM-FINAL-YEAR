<?php
include "includes/db_connect.php";
include "admin/connection.php";

mysqli_select_db($link, "hospital");

// First, get all distinct pharmacy bills
$bills_query = "
    SELECT DISTINCT 
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
    ORDER BY pb.id
";

echo "<h3>Starting Pharmacy Bills Integration</h3>";

$bills_result = mysqli_query($link, $bills_query);

if (!$bills_result) {
    die("Query failed: " . mysqli_error($link));
}

$processed_bills = 0;
$errors = [];

// Check if we have any bills to process
$total_bills = mysqli_num_rows($bills_result);
echo "Found $total_bills bills to process<br><br>";

while ($bill = mysqli_fetch_assoc($bills_result)) {
    echo "Processing bill: {$bill['bill_no']}... ";
    
    try {
        // Check if bill already exists in unified system
        $check_stmt = $pdo->prepare("SELECT id FROM unified_billing WHERE bill_number = ?");
        $check_stmt->execute([$bill['bill_no']]);
        
        if (!$check_stmt->fetch()) {
            // Prepare the insert statement - CORRECTED with all required columns
            $insert_stmt = $pdo->prepare("
                INSERT INTO unified_billing 
                (patient_id, bill_number, bill_date, service_type, service_description, 
                 reference_id, total_amount, paid_amount, balance_amount, status, 
                 payment_method, payment_reference, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $service_description = "Pharmacy Bill - " . $bill['CustomerName'];
            
            // Execute with all required parameters
            $insert_stmt->execute([
                $bill['patient_id'] ?? null,        // 1. patient_id
                $bill['bill_no'],                   // 2. bill_number  
                $bill['date'],                      // 3. bill_date
                'pharmacy',                         // 4. service_type
                $service_description,               // 5. service_description
                $bill['id'] ?? null,                // 6. reference_id (original pharmacy_billing id)
                $bill['total_amount'] ?? 0,         // 7. total_amount
                $bill['paid_amount'] ?? 0,          // 8. paid_amount
                $bill['balance_amount'] ?? 0,       // 9. balance_amount
                $bill['payment_status'] ?? 'pending', // 10. status
                $bill['payment_method'] ?? null,    // 11. payment_method
                null,                               // 12. payment_reference
                1                                   // 13. created_by (system user)
            ]);
            
            $billing_id = $pdo->lastInsertId();
            
            // Now get and insert bill items for this bill
            $items_query = "
                SELECT 
                    product_name,
                    product_company,
                    quantity,
                    price,
                    total_amount,
                    product_unit,
                    packing_size
                FROM pharmacy_billing_details 
                WHERE billing_id = " . intval($bill['id']) . "
                AND product_name IS NOT NULL
            ";
            
            $items_result = mysqli_query($link, $items_query);
            $item_count = 0;
            
            if ($items_result) {
                while ($item = mysqli_fetch_assoc($items_result)) {
                    $item_stmt = $pdo->prepare("
                        INSERT INTO billing_items 
                        (billing_id, item_description, quantity, unit_price, total_amount, item_type)
                        VALUES (?, ?, ?, ?, ?, 'pharmacy')
                    ");
                    
                    $item_description = $item['product_name'];
                    if (!empty($item['product_company'])) {
                        $item_description .= " (" . $item['product_company'] . ")";
                    }
                    if (!empty($item['packing_size'])) {
                        $item_description .= " - " . $item['packing_size'];
                    }
                    
                    $item_stmt->execute([
                        $billing_id,
                        $item_description,
                        $item['quantity'] ?? 1,
                        $item['price'] ?? 0,
                        $item['total_amount'] ?? 0,
                        'pharmacy'
                    ]);
                    
                    $item_count++;
                }
                mysqli_free_result($items_result);
            }
            
            $processed_bills++;
            echo "✅ SUCCESS - {$item_count} items, Ksh " . number_format($bill['total_amount'] ?? 0, 2) . "<br>";
            
        } else {
            echo "⏭️ SKIPPED - Already exists<br>";
        }
        
    } catch (Exception $e) {
        $errors[] = $bill['bill_no'] . ": " . $e->getMessage();
        echo "❌ ERROR - " . $e->getMessage() . "<br>";
    }
    
    // Flush output
    flush();
    ob_flush();
}

echo "<br><h3>Integration Complete!</h3>";
echo "✅ Processed bills: <strong>$processed_bills</strong><br>";
echo "❌ Errors: <strong>" . count($errors) . "</strong><br>";

if (!empty($errors)) {
    echo "<br><strong>First 5 Errors:</strong><br>";
    foreach (array_slice($errors, 0, 5) as $error) {
        echo "• $error<br>";
    }
}

// Show final results
try {
    $total_count = $pdo->query("SELECT COUNT(*) as count FROM unified_billing WHERE service_type = 'pharmacy'")->fetch()['count'];
    $amount_sum = $pdo->query("SELECT SUM(total_amount) as total, SUM(paid_amount) as paid, SUM(balance_amount) as balance FROM unified_billing WHERE service_type = 'pharmacy'")->fetch();
    
    echo "<br><strong>Final Results:</strong><br>";
    echo "Total pharmacy bills in unified system: <strong>$total_count</strong><br>";
    echo "Total amount: Ksh " . number_format($amount_sum['total'] ?? 0, 2) . "<br>";
    echo "Paid amount: Ksh " . number_format($amount_sum['paid'] ?? 0, 2) . "<br>";
    echo "Balance: Ksh " . number_format($amount_sum['balance'] ?? 0, 2) . "<br>";
    
} catch (Exception $e) {
    echo "<br>Error getting final results: " . $e->getMessage() . "<br>";
}

// Free results
mysqli_free_result($bills_result);
?>