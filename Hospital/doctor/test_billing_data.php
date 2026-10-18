<?php
include "includes/db_connect.php";
include "admin/connection.php";

echo "<h2>Billing Data Test</h2>";

// Test pharmacy billing data
echo "<h3>Latest Pharmacy Bills:</h3>";
mysqli_select_db($link, "hospital");
$result = mysqli_query($link, "
    SELECT pb.*, COUNT(pbd.id) as detail_count, SUM(pbd.total_amount) as total 
    FROM pharmacy_billing pb 
    LEFT JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id 
    GROUP BY pb.id 
    ORDER BY pb.id DESC 
    LIMIT 5
");

echo "<table border='1'>";
echo "<tr><th>ID</th><th>Bill No</th><th>Customer</th><th>Date</th><th>Patient ID</th><th>Details Count</th><th>Total</th></tr>";
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['bill_no']}</td>";
    echo "<td>{$row['CustomerName']}</td>";
    echo "<td>{$row['date']}</td>";
    echo "<td>{$row['patient_id']}</td>";
    echo "<td>{$row['detail_count']}</td>";
    echo "<td>{$row['total']}</td>";
    echo "</tr>";
}
echo "</table>";

// Test combined view
echo "<h3>Combined View (v_combined_billing):</h3>";
try {
    $stmt = $pdo->prepare("SELECT * FROM v_combined_billing ORDER BY date DESC LIMIT 5");
    $stmt->execute();
    $combined = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Source</th><th>Bill No</th><th>Customer</th><th>Date</th><th>Amount</th><th>Patient ID</th></tr>";
    foreach ($combined as $row) {
        echo "<tr>";
        echo "<td>{$row['source']}</td>";
        echo "<td>{$row['bill_no']}</td>";
        echo "<td>{$row['customer_name']}</td>";
        echo "<td>{$row['date']}</td>";
        echo "<td>{$row['total_amount']}</td>";
        echo "<td>{$row['patient_id']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>