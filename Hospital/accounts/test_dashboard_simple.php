<?php
session_start();
include "includes/db_connect.php";
include "admin/connection.php";

echo "<h2>Simple Dashboard Test</h2>";

// Test pharmacy bills directly
echo "<h3>Pharmacy Bills (Last 5):</h3>";
mysqli_select_db($link, "hospital");
$result = mysqli_query($link, "
    SELECT 
        pb.id, pb.bill_no, pb.CustomerName, pb.date, 
        COALESCE(SUM(pbd.total_amount), 0) as total,
        COUNT(pbd.id) as items,
        pb.patient_id
    FROM pharmacy_billing pb
    LEFT JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
    GROUP BY pb.id
    ORDER BY pb.date DESC 
    LIMIT 5
");

echo "<table border='1' style='width: 100%;'>";
echo "<tr><th>Bill No</th><th>Customer</th><th>Date</th><th>Total</th><th>Items</th><th>Patient ID</th></tr>";
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>{$row['bill_no']}</td>";
    echo "<td>{$row['CustomerName']}</td>";
    echo "<td>{$row['date']}</td>";
    echo "<td>Ksh " . number_format($row['total'], 2) . "</td>";
    echo "<td>{$row['items']}</td>";
    echo "<td>{$row['patient_id']}</td>";
    echo "</tr>";
}
echo "</table>";

// Test today's pharmacy revenue
echo "<h3>Today's Pharmacy Revenue:</h3>";
$today = date('Y-m-d');
$revenue_query = "
    SELECT COALESCE(SUM(pbd.total_amount), 0) as today_revenue
    FROM pharmacy_billing pb
    INNER JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
    WHERE DATE(pb.date) = '$today'
";
$revenue_result = mysqli_query($link, $revenue_query);
$revenue_row = mysqli_fetch_assoc($revenue_result);
echo "<p><strong>Today's Pharmacy Revenue: Ksh " . number_format($revenue_row['today_revenue'], 2) . "</strong></p>";
?>