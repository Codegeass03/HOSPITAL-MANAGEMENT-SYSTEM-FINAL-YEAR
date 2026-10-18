<?php
session_start();


$conn = new mysqli("localhost", "root", "", "hospital");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check what tables exist
echo "<h2>Unified Billing System Setup</h2>";

// Check for existing pharmacy tables
$check_pharmacy = $conn->query("SHOW TABLES LIKE 'pharmacy_sales'");
if ($check_pharmacy->num_rows > 0) {
    echo "<p style='color:green'>✓ pharmacy_sales table exists</p>";
    
    // Check structure
    $structure = $conn->query("DESCRIBE pharmacy_sales");
    $columns = [];
    while($row = $structure->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    // Check for invoice_id column
    if (!in_array('invoice_id', $columns)) {
        echo "<p style='color:orange'>⚠️ Adding invoice linking columns to pharmacy_sales...</p>";
        
        $alter_sql = "ALTER TABLE `pharmacy_sales` 
                     ADD COLUMN `invoice_id` INT(11) DEFAULT NULL AFTER `prescription_id`,
                     ADD COLUMN `invoice_no` VARCHAR(50) DEFAULT NULL AFTER `invoice_id`,
                     ADD COLUMN `billing_status` ENUM('pending','added_to_invoice','paid','cancelled') DEFAULT 'pending' AFTER `payment_status`";
        
        if ($conn->query($alter_sql)) {
            echo "<p style='color:green'>✓ Added invoice linking columns</p>";
        } else {
            echo "<p style='color:red'>✗ Error: " . $conn->error . "</p>";
        }
    }
} else {
    echo "<p style='color:orange'>⚠️ Creating pharmacy_sales table...</p>";
    
    $create_pharmacy = "CREATE TABLE `pharmacy_sales` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `receipt_no` VARCHAR(50) NOT NULL,
        `patient_id` INT(10) UNSIGNED DEFAULT NULL,
        `patient_name` VARCHAR(255) NOT NULL,
        `bill_type` ENUM('Cash','Mpesa','Debit','Insurance','Bank Transfer') DEFAULT 'Cash',
        `sale_date` DATETIME NOT NULL,
        `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `balance_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `payment_status` ENUM('pending','paid','partial') DEFAULT 'pending',
        `billing_status` ENUM('pending','added_to_invoice','paid','cancelled') DEFAULT 'pending',
        `prescription_id` INT(11) DEFAULT NULL,
        `invoice_id` INT(11) DEFAULT NULL,
        `invoice_no` VARCHAR(50) DEFAULT NULL,
        `dispensed_by` VARCHAR(100) DEFAULT NULL,
        `created_by` VARCHAR(100) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_receipt_no` (`receipt_no`),
        KEY `idx_patient_id` (`patient_id`),
        KEY `idx_sale_date` (`sale_date`),
        KEY `idx_payment_status` (`payment_status`),
        KEY `idx_billing_status` (`billing_status`),
        KEY `idx_invoice_id` (`invoice_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if ($conn->query($create_pharmacy)) {
        echo "<p style='color:green'>✓ Created pharmacy_sales table</p>";
        
        // Create pharmacy_sale_items
        $create_items = "CREATE TABLE `pharmacy_sale_items` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `sale_id` INT(11) NOT NULL,
            `invoice_id` INT(11) DEFAULT NULL,
            `invoice_no` VARCHAR(50) DEFAULT NULL,
            `receipt_no` VARCHAR(50) NOT NULL,
            `product_company` VARCHAR(255) NOT NULL,
            `product_name` VARCHAR(255) NOT NULL,
            `product_unit` VARCHAR(50) NOT NULL,
            `packing_size` VARCHAR(50) NOT NULL,
            `unit_price` DECIMAL(10,2) NOT NULL,
            `quantity` INT(11) NOT NULL,
            `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_sale_id` (`sale_id`),
            KEY `idx_receipt_no` (`receipt_no`),
            KEY `idx_invoice_id` (`invoice_id`),
            KEY `idx_product_name` (`product_name`),
            FOREIGN KEY (`sale_id`) REFERENCES `pharmacy_sales`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        if ($conn->query($create_items)) {
            echo "<p style='color:green'>✓ Created pharmacy_sale_items table</p>";
        }
    }
}

// Create unified billing tables
echo "<hr><h3>Creating Unified Billing Tables</h3>";

$tables = [
    // Consolidated invoices
    "CREATE TABLE IF NOT EXISTS `consolidated_invoices` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `invoice_no` VARCHAR(50) NOT NULL,
        `patient_id` INT(10) UNSIGNED NOT NULL,
        `patient_name` VARCHAR(255) NOT NULL,
        `visit_date` DATE NOT NULL,
        `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `balance_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `payment_status` ENUM('pending','partial','paid','cancelled') DEFAULT 'pending',
        `created_by` VARCHAR(100) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_invoice_no` (`invoice_no`),
        KEY `idx_patient_id` (`patient_id`),
        KEY `idx_visit_date` (`visit_date`),
        KEY `idx_payment_status` (`payment_status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    
    // Invoice items
    "CREATE TABLE IF NOT EXISTS `invoice_items` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `invoice_id` INT(11) NOT NULL,
        `invoice_no` VARCHAR(50) NOT NULL,
        `service_type` ENUM('consultation','pharmacy','lab','admission','procedure','other') NOT NULL,
        `service_id` INT(11) NOT NULL,
        `reference_no` VARCHAR(50) NOT NULL,
        `description` TEXT NOT NULL,
        `unit_price` DECIMAL(10,2) NOT NULL,
        `quantity` INT(11) NOT NULL DEFAULT 1,
        `total_amount` DECIMAL(10,2) NOT NULL,
        `item_date` DATETIME NOT NULL,
        `department` VARCHAR(100) DEFAULT NULL,
        `status` ENUM('pending','billed','cancelled') DEFAULT 'pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_invoice_id` (`invoice_id`),
        KEY `idx_invoice_no` (`invoice_no`),
        KEY `idx_service_type` (`service_type`),
        KEY `idx_reference_no` (`reference_no`),
        KEY `idx_service_id` (`service_id`),
        FOREIGN KEY (`invoice_id`) REFERENCES `consolidated_invoices`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    
    // Invoice payments
    "CREATE TABLE IF NOT EXISTS `invoice_payments` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `invoice_id` INT(11) NOT NULL,
        `invoice_no` VARCHAR(50) NOT NULL,
        `patient_id` INT(10) UNSIGNED NOT NULL,
        `patient_name` VARCHAR(255) NOT NULL,
        `payment_method` ENUM('cash','mpesa','card','insurance','bank_transfer','cheque') NOT NULL,
        `amount_paid` DECIMAL(10,2) NOT NULL,
        `transaction_ref` VARCHAR(100) DEFAULT NULL,
        `payment_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `received_by` VARCHAR(100) DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_invoice_id` (`invoice_id`),
        KEY `idx_invoice_no` (`invoice_no`),
        KEY `idx_patient_id` (`patient_id`),
        KEY `idx_payment_date` (`payment_date`),
        FOREIGN KEY (`invoice_id`) REFERENCES `consolidated_invoices`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
];

foreach ($tables as $sql) {
    if ($conn->query($sql)) {
        echo "<p style='color:green'>✓ Table created</p>";
    } else {
        echo "<p style='color:red'>✗ Error: " . $conn->error . "</p>";
    }
}

// Create views
echo "<hr><h3>Creating Views</h3>";

$views = [
    // Billing dashboard view
    "CREATE OR REPLACE VIEW `billing_dashboard_view` AS
    SELECT 
        ci.id,
        ci.invoice_no,
        ci.patient_id,
        ci.patient_name,
        ci.visit_date,
        ci.total_amount,
        ci.paid_amount,
        ci.balance_amount,
        ci.payment_status,
        ci.created_at,
        COUNT(ii.id) as total_items,
        GROUP_CONCAT(DISTINCT ii.service_type ORDER BY ii.service_type SEPARATOR ', ') as services_included,
        MAX(ii.item_date) as last_service_date
    FROM consolidated_invoices ci
    LEFT JOIN invoice_items ii ON ci.id = ii.invoice_id
    GROUP BY ci.id
    ORDER BY ci.created_at DESC",
    
    // Pending pharmacy bills view
    "CREATE OR REPLACE VIEW `pending_pharmacy_bills_view` AS
    SELECT 
        ps.id,
        ps.receipt_no,
        ps.patient_id,
        ps.patient_name,
        ps.sale_date,
        ps.total_amount,
        ps.paid_amount,
        ps.balance_amount,
        ps.billing_status,
        ps.prescription_id,
        ps.dispensed_by,
        ps.created_at,
        COUNT(psi.id) as item_count,
        GROUP_CONCAT(DISTINCT psi.product_name SEPARATOR ', ') as products
    FROM pharmacy_sales ps
    LEFT JOIN pharmacy_sale_items psi ON ps.id = psi.sale_id
    WHERE ps.billing_status = 'pending'
    GROUP BY ps.id
    ORDER BY ps.sale_date DESC"
];

foreach ($views as $sql) {
    if ($conn->query($sql)) {
        echo "<p style='color:green'>✓ View created</p>";
    } else {
        echo "<p style='color:red'>✗ Error: " . $conn->error . "</p>";
    }
}

echo "<hr><h2>✅ Setup Complete!</h2>";
echo "<p><strong>Tables Created:</strong></p>";
echo "<ul>";
echo "<li>consolidated_invoices - Main invoice table</li>";
echo "<li>invoice_items - Individual service items</li>";
echo "<li>invoice_payments - Payment tracking</li>";
echo "<li>pharmacy_sales - Pharmacy transactions</li>";
echo "<li>pharmacy_sale_items - Pharmacy line items</li>";
echo "</ul>";
echo "<p><strong>Views Created:</strong></p>";
echo "<ul>";
echo "<li>billing_dashboard_view - For dashboard display</li>";
echo "<li>pending_pharmacy_bills_view - Pending pharmacy bills</li>";
echo "</ul>";
echo "<p><a href='pharmacy_new_sales.php' style='background:#667eea;color:white;padding:10px 20px;border-radius:5px;text-decoration:none;'>Go to Pharmacy Sales</a></p>";
echo "<p><a href='billing_dashboard.php' style='background:#28a745;color:white;padding:10px 20px;border-radius:5px;text-decoration:none;'>Go to Billing Dashboard</a></p>";

$conn->close();
?>