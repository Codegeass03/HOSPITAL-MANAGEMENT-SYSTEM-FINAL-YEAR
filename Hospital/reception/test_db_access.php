<?php
include "admin/connection.php"; // php_ims connection
include "includes/db_connect.php"; // hospital connection

echo "<h2>Testing Database Access</h2>";

// Test php_ims connection
echo "<h3>PHP_IMS Database (MySQLi):</h3>";
$result1 = mysqli_query($link, "SELECT DATABASE() as db");
$row1 = mysqli_fetch_assoc($result1);
echo "Connected to: " . $row1['db'] . "<br>";

// Test hospital connection (PDO)
echo "<h3>Hospital Database (PDO):</h3>";
try {
    $stmt = $pdo->query("SELECT DATABASE() as db");
    $row2 = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Connected to: " . $row2['db'] . "<br>";
    
    // Test pharmacy_billing access
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pharmacy_billing");
    $row3 = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Pharmacy bills count: " . $row3['count'] . "<br>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Test switching databases with MySQLi
echo "<h3>Testing MySQLi Database Switching:</h3>";
mysqli_select_db($link, "hospital");
$result2 = mysqli_query($link, "SELECT DATABASE() as db");
$row4 = mysqli_fetch_assoc($result2);
echo "MySQLi now connected to: " . $row4['db'] . "<br>";

$result3 = mysqli_query($link, "SELECT COUNT(*) as count FROM pharmacy_billing");
if ($result3) {
    $row5 = mysqli_fetch_assoc($result3);
    echo "Pharmacy bills count (MySQLi): " . $row5['count'] . "<br>";
} else {
    echo "Error accessing pharmacy_billing: " . mysqli_error($link) . "<br>";
}
?>