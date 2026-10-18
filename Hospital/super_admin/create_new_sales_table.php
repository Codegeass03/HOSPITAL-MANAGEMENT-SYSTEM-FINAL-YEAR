<?php
session_start();
// if(!isset($_SESSION["username"]) || $_SESSION["role"] != "super_admin") {
//     header("location: login.php");
//     exit();
// }

// Database connection
$conn = new mysqli("localhost", "root", "", "hospital");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Creating New Pharmacy Sales Tables</h2>";

// 1. Create pharmacy_sales table (main sales header)
$sql1 = "CREATE TABLE IF NOT EXISTS `pharmacy_sales` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `receipt_no` VARCHAR(50) NOT NULL UNIQUE,
    `patient_id` INT(10) UNSIGNED DEFAULT NULL,
    `patient_name` VARCHAR(255) NOT NULL,
    `bill_type` ENUM('Cash','Mpesa','Debit','Insurance','Bank Transfer') DEFAULT 'Cash',
    `sale_date` DATETIME NOT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `balance_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_status` ENUM('pending','paid','partial') DEFAULT 'pending',
    `prescription_id` INT(11) DEFAULT NULL,
    `dispensed_by` VARCHAR(100) DEFAULT NULL,
    `created_by` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `receipt_no` (`receipt_no`),
    KEY `patient_id` (`patient_id`),
    KEY `sale_date` (`sale_date`),
    KEY `payment_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql1) === TRUE) {
    echo "<p style='color:green'>✓ pharmacy_sales table created successfully</p>";
} else {
    echo "<p style='color:red'>✗ Error creating pharmacy_sales table: " . $conn->error . "</p>";
}

// 2. Create pharmacy_sale_items table (line items)
$sql2 = "CREATE TABLE IF NOT EXISTS `pharmacy_sale_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `sale_id` INT(11) NOT NULL,
    `receipt_no` VARCHAR(50) NOT NULL,
    `product_company` VARCHAR(255) NOT NULL,
    `product_name` VARCHAR(255) NOT NULL,
    `product_unit` VARCHAR(50) NOT NULL,
    `packing_size` VARCHAR(50) NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `quantity` INT(11) NOT NULL,
    `total_price` DECIMAL(10,2) GENERATED ALWAYS AS (unit_price * quantity) STORED,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `sale_id` (`sale_id`),
    KEY `receipt_no` (`receipt_no`),
    KEY `product_name` (`product_name`),
    FOREIGN KEY (`sale_id`) REFERENCES `pharmacy_sales`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql2) === TRUE) {
    echo "<p style='color:green'>✓ pharmacy_sale_items table created successfully</p>";
} else {
    echo "<p style='color:red'>✗ Error creating pharmacy_sale_items table: " . $conn->error . "</p>";
}

// 3. Create pharmacy_payments table for tracking payments
$sql3 = "CREATE TABLE IF NOT EXISTS `pharmacy_payments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `sale_id` INT(11) NOT NULL,
    `receipt_no` VARCHAR(50) NOT NULL,
    `patient_id` INT(10) UNSIGNED DEFAULT NULL,
    `patient_name` VARCHAR(255) NOT NULL,
    `amount_paid` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('Cash','Mpesa','Debit','Insurance','Bank Transfer') DEFAULT 'Cash',
    `transaction_ref` VARCHAR(100) DEFAULT NULL,
    `paid_by` VARCHAR(100) DEFAULT NULL,
    `payment_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `sale_id` (`sale_id`),
    KEY `receipt_no` (`receipt_no`),
    KEY `patient_id` (`patient_id`),
    FOREIGN KEY (`sale_id`) REFERENCES `pharmacy_sales`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql3) === TRUE) {
    echo "<p style='color:green'>✓ pharmacy_payments table created successfully</p>";
} else {
    echo "<p style='color:red'>✗ Error creating pharmacy_payments table: " . $conn->error . "</p>";
}

// 4. Create prescription_sales table to link prescriptions to sales
$sql4 = "CREATE TABLE IF NOT EXISTS `prescription_sales` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `prescription_id` INT(11) NOT NULL,
    `sale_id` INT(11) NOT NULL,
    `receipt_no` VARCHAR(50) NOT NULL,
    `dispensed_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `dispensed_by` VARCHAR(100) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `prescription_sale` (`prescription_id`, `sale_id`),
    KEY `prescription_id` (`prescription_id`),
    KEY `sale_id` (`sale_id`),
    FOREIGN KEY (`sale_id`) REFERENCES `pharmacy_sales`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql4) === TRUE) {
    echo "<p style='color:green'>✓ prescription_sales table created successfully</p>";
} else {
    echo "<p style='color:red'>✗ Error creating prescription_sales table: " . $conn->error . "</p>";
}

echo "<hr>";
echo "<h3>Database Structure Created Successfully!</h3>";
echo "<p>The following tables have been created:</p>";
echo "<ul>";
echo "<li><strong>pharmacy_sales</strong> - Main sales header table</li>";
echo "<li><strong>pharmacy_sale_items</strong> - Sales line items</li>";
echo "<li><strong>pharmacy_payments</strong> - Payment tracking</li>";
echo "<li><strong>prescription_sales</strong> - Prescription linkage</li>";
echo "</ul>";
echo "<p><a href='pharmacy_new_sales.php'>Go to New Sales Page</a></p>";

$conn->close();
?>