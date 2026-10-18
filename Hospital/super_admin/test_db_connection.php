<?php
include "includes/db_connect.php";

header('Content-Type: text/plain');

try {
    // Test hospital database connection
    mysqli_select_db($link, "hospital");
    
    // Test basic queries
    $tables = ['pharmacy_billing', 'pharmacy_billing_details', 'patients', 'prescriptions'];
    $results = [];
    
    foreach ($tables as $table) {
        $query = "SHOW TABLES LIKE '$table'";
        $result = mysqli_query($link, $query);
        $results[$table] = mysqli_num_rows($result) > 0 ? '✓' : '✗';
    }
    
    // Check column counts
    $required_columns = [
        'pharmacy_billing' => ['total_amount', 'paid_amount', 'balance_amount', 'payment_status'],
        'pharmacy_billing_details' => ['patient_id', 'total_amount']
    ];
    
    foreach ($required_columns as $table => $columns) {
        foreach ($columns as $column) {
            $query = "SHOW COLUMNS FROM `$table` LIKE '$column'";
            $result = mysqli_query($link, $query);
            $results["$table.$column"] = mysqli_num_rows($result) > 0 ? '✓' : '✗';
        }
    }
    
    // Output results
    echo "Database Connection Test Results:\n\n";
    echo "Hospital Database: ✓ Connected\n";
    
    foreach ($results as $key => $status) {
        echo "$key: $status\n";
    }
    
    echo "\n" . (strpos(implode('', $results), '✗') ? 
        "⚠️ Some issues detected. Run the database update." : 
        "✅ All tests passed! Database is ready.");
    
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage();
}
?>