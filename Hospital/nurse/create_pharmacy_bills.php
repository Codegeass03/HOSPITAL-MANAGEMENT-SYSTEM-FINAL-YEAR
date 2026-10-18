<?php
session_start();
include "includes/db_connect.php";
include "admin/connection.php";

if ($_POST['create_pharmacy_bill']) {
    $patient_id = $_POST['patient_id'];
    $items = $_POST['items']; // Array of pharmacy items
    
    mysqli_select_db($link, "hospital");
    
    // Generate bill number
    $bill_number = 'PH-' . date('YmdHis') . '-' . rand(100, 999);
    
    // Calculate total
    $total_amount = 0;
    foreach ($items as $item) {
        $total_amount += $item['quantity'] * $item['price'];
    }
    
    // Insert pharmacy bill as PENDING
    $insert_bill = "
        INSERT INTO pharmacy_billing (
            bill_no, date, patient_id, CustomerName, 
            total_amount, paid_amount, balance_amount, payment_status,
            created_by, created_at
        ) VALUES (
            '$bill_number', NOW(), '$patient_id', '$customer_name',
            '$total_amount', 0, '$total_amount', 'pending',
            '{$_SESSION['user_id']}', NOW()
        )
    ";
    
    mysqli_query($link, $insert_bill);
    $bill_id = mysqli_insert_id($link);
    
    // Insert bill items
    foreach ($items as $item) {
        $insert_item = "
            INSERT INTO pharmacy_billing_details (
                billing_id, product_name, product_company, batch_no,
                expiry_date, quantity, price, total_amount
            ) VALUES (
                '$bill_id', '{$item['name']}', '{$item['company']}', '{$item['batch']}',
                '{$item['expiry']}', '{$item['quantity']}', '{$item['price']}', '{$item['total']}'
            )
        ";
        mysqli_query($link, $insert_item);
    }
    
    $_SESSION['success'] = "Pharmacy bill created successfully! Patient can pay at billing counter.";
    header("Location: patient_billing.php?id=$patient_id");
    exit();
}
?>