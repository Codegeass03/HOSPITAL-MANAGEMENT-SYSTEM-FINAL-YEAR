<?php
session_start();
if(!isset($_SESSION["username"])) {
    header("location: login.php");
    exit();
}

// Include database connection
include "includes/db_connect.php"; // hospital connection

$success_messages = [];
$error_messages = [];

// Function to check if column exists
function column_exists($link, $table, $column) {
    $query = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $result = mysqli_query($link, $query);
    return mysqli_num_rows($result) > 0;
}

// Function to add column if it doesn't exist
function add_column_if_not_exists($link, $table, $column, $definition) {
    global $success_messages, $error_messages;
    
    if (!column_exists($link, $table, $column)) {
        $query = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
        if (mysqli_query($link, $query)) {
            $success_messages[] = "✓ Added column '$column' to $table table";
        } else {
            $error_messages[] = "✗ Failed to add column '$column': " . mysqli_error($link);
        }
    } else {
        $success_messages[] = "✓ Column '$column' already exists in $table table";
    }
}

// Handle form submission
if (isset($_POST['update_database'])) {
    // Switch to hospital database
    mysqli_select_db($link, "hospital");
    
    // 1. Update pharmacy_billing table
    add_column_if_not_exists($link, "pharmacy_billing", "total_amount", "DECIMAL(10,2) DEFAULT 0.00");
    add_column_if_not_exists($link, "pharmacy_billing", "paid_amount", "DECIMAL(10,2) DEFAULT 0.00");
    add_column_if_not_exists($link, "pharmacy_billing", "balance_amount", "DECIMAL(10,2) DEFAULT 0.00");
    add_column_if_not_exists($link, "pharmacy_billing", "payment_status", "ENUM('pending', 'paid') DEFAULT 'pending'");
    add_column_if_not_exists($link, "pharmacy_billing", "payment_method", "VARCHAR(50) DEFAULT NULL");
    add_column_if_not_exists($link, "pharmacy_billing", "payment_date", "DATETIME DEFAULT NULL");
    add_column_if_not_exists($link, "pharmacy_billing", "payment_reference", "VARCHAR(100) DEFAULT NULL");
    add_column_if_not_exists($link, "pharmacy_billing", "updated_at", "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    
    // 2. Update pharmacy_billing_details table
    add_column_if_not_exists($link, "pharmacy_billing_details", "patient_id", "INT(10) UNSIGNED DEFAULT NULL");
    add_column_if_not_exists($link, "pharmacy_billing_details", "total_amount", "DECIMAL(10,2) GENERATED ALWAYS AS (price * quantity) STORED");
    add_column_if_not_exists($link, "pharmacy_billing_details", "created_at", "TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    
    // 3. Create billing_payments table if not exists
    $check_billing_payments = "SHOW TABLES LIKE 'billing_payments'";
    $result = mysqli_query($link, $check_billing_payments);
    
    if (mysqli_num_rows($result) == 0) {
        $create_billing_payments = "CREATE TABLE `billing_payments` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `receipt_number` varchar(50) NOT NULL,
            `patient_id` int(10) UNSIGNED NOT NULL,
            `patient_name` varchar(255) NOT NULL,
            `service_type` enum('consultation','pharmacy','lab','admission','consolidated') NOT NULL,
            `reference_id` varchar(50) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
            `payment_method` enum('cash','mpesa','card','insurance','bank_transfer') DEFAULT 'cash',
            `transaction_reference` varchar(100) DEFAULT NULL,
            `status` enum('pending','completed','failed','refunded') DEFAULT 'completed',
            `processed_by` int(10) UNSIGNED DEFAULT NULL,
            `processed_by_name` varchar(255) DEFAULT NULL,
            `payment_date` datetime DEFAULT CURRENT_TIMESTAMP,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `receipt_number` (`receipt_number`),
            KEY `patient_id` (`patient_id`),
            KEY `payment_date` (`payment_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        if (mysqli_query($link, $create_billing_payments)) {
            $success_messages[] = "✓ Created billing_payments table";
        } else {
            $error_messages[] = "✗ Failed to create billing_payments table: " . mysqli_error($link);
        }
    } else {
        $success_messages[] = "✓ billing_payments table already exists";
    }
    
    // 4. Add foreign key constraint for patient_id if it doesn't exist
    $check_fk_query = "SELECT COUNT(*) as fk_exists 
                      FROM information_schema.KEY_COLUMN_USAGE 
                      WHERE TABLE_SCHEMA = 'hospital' 
                      AND TABLE_NAME = 'pharmacy_billing' 
                      AND COLUMN_NAME = 'patient_id' 
                      AND REFERENCED_TABLE_NAME IS NOT NULL";
    
    $fk_result = mysqli_query($link, $check_fk_query);
    $fk_row = mysqli_fetch_assoc($fk_result);
    
    if ($fk_row['fk_exists'] == 0) {
        // Add foreign key constraint
        $add_fk_query = "ALTER TABLE `pharmacy_billing` 
                        ADD CONSTRAINT `fk_pharmacy_billing_patient` 
                        FOREIGN KEY (`patient_id`) 
                        REFERENCES `patients` (`id`) 
                        ON DELETE SET NULL";
        
        if (mysqli_query($link, $add_fk_query)) {
            $success_messages[] = "✓ Added foreign key constraint for patient_id";
        } else {
            $error_messages[] = "✗ Failed to add foreign key constraint: " . mysqli_error($link);
        }
    } else {
        $success_messages[] = "✓ Foreign key constraint already exists";
    }
    
    // 5. Create index for better performance
    $indexes = [
        "pharmacy_billing" => ["patient_id", "payment_status", "bill_no"],
        "pharmacy_billing_details" => ["billing_id", "patient_id", "product_name"],
        "billing_payments" => ["patient_id", "payment_date", "receipt_number"]
    ];
    
    foreach ($indexes as $table => $columns) {
        foreach ($columns as $column) {
            $check_index_query = "SHOW INDEX FROM `$table` WHERE Column_name = '$column'";
            $index_result = mysqli_query($link, $check_index_query);
            
            if (mysqli_num_rows($index_result) == 0) {
                $add_index_query = "ALTER TABLE `$table` ADD INDEX `idx_{$table}_{$column}` (`$column`)";
                if (mysqli_query($link, $add_index_query)) {
                    $success_messages[] = "✓ Added index for $table.$column";
                } else {
                    $error_messages[] = "✗ Failed to add index for $table.$column: " . mysqli_error($link);
                }
            }
        }
    }
    
    // 6. Migrate existing data to new structure (optional)
    if (isset($_POST['migrate_data']) && $_POST['migrate_data'] == 'yes') {
        // Update existing pharmacy_billing records to calculate totals
        $update_totals_query = "
            UPDATE pharmacy_billing pb
            SET pb.total_amount = (
                SELECT COALESCE(SUM(pbd.price * pbd.quantity), 0)
                FROM pharmacy_billing_details pbd
                WHERE pbd.billing_id = pb.id
            ),
            pb.balance_amount = (
                SELECT COALESCE(SUM(pbd.price * pbd.quantity), 0)
                FROM pharmacy_billing_details pbd
                WHERE pbd.billing_id = pb.id
            )
            WHERE pb.total_amount = 0 AND pb.balance_amount = 0
        ";
        
        if (mysqli_query($link, $update_totals_query)) {
            $affected_rows = mysqli_affected_rows($link);
            $success_messages[] = "✓ Migrated data for $affected_rows billing records";
        } else {
            $error_messages[] = "✗ Failed to migrate data: " . mysqli_error($link);
        }
    }
    
    // 7. Create consolidated payments table
    $check_consolidated = "SHOW TABLES LIKE 'consolidated_payments'";
    $result = mysqli_query($link, $check_consolidated);
    
    if (mysqli_num_rows($result) == 0) {
        $create_consolidated = "CREATE TABLE `consolidated_payments` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `consolidated_receipt` varchar(50) NOT NULL,
            `patient_id` int(10) UNSIGNED NOT NULL,
            `total_amount` decimal(10,2) NOT NULL,
            `paid_amount` decimal(10,2) NOT NULL,
            `services_included` text DEFAULT NULL,
            `status` enum('partial','completed','overpaid') DEFAULT 'completed',
            `created_by` int(10) UNSIGNED DEFAULT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `consolidated_receipt` (`consolidated_receipt`),
            KEY `patient_id` (`patient_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        if (mysqli_query($link, $create_consolidated)) {
            $success_messages[] = "✓ Created consolidated_payments table";
        } else {
            $error_messages[] = "✗ Failed to create consolidated_payments table: " . mysqli_error($link);
        }
    }
}

// Check current database structure
if ($link) {
    mysqli_select_db($link, "hospital");
    $current_structure = [];
    
    // Check pharmacy_billing structure
    $check_structure = "DESCRIBE pharmacy_billing";
    $result = mysqli_query($link, $check_structure);
    $pharmacy_billing_columns = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $pharmacy_billing_columns[] = $row['Field'];
        }
    } else {
        $error_messages[] = "✗ Failed to describe pharmacy_billing table: " . mysqli_error($link);
    }
    
    // Check pharmacy_billing_details structure
    $check_details = "DESCRIBE pharmacy_billing_details";
    $result = mysqli_query($link, $check_details);
    $pharmacy_billing_details_columns = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $pharmacy_billing_details_columns[] = $row['Field'];
        }
    } else {
        $error_messages[] = "✗ Failed to describe pharmacy_billing_details table: " . mysqli_error($link);
    }
    
    // Check if billing_payments exists
    $check_payments = "SHOW TABLES LIKE 'billing_payments'";
    $result = mysqli_query($link, $check_payments);
    $has_billing_payments = $result ? mysqli_num_rows($result) > 0 : false;
} else {
    $error_messages[] = "✗ Database connection is not established. Check your db_connect.php file.";
}

include "includes/sidebar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Pharmacy Database - Unified Billing System</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
        }

        .container {
            display: flex;
            width: 100%;
        }

        .sidebar {
            width: 250px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 20px;
            position: fixed;
            height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            min-height: 100vh;
        }

        .database-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .database-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #667eea;
        }

        .database-header h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .database-header p {
            color: #666;
            font-size: 16px;
        }

        .table-info {
            margin-bottom: 30px;
        }

        .table-info h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 20px;
        }

        .column-list {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .column-list ul {
            list-style: none;
            padding-left: 0;
        }

        .column-list li {
            padding: 8px 15px;
            margin: 5px 0;
            background: white;
            border-radius: 6px;
            border-left: 4px solid #28a745;
        }

        .column-list li.missing {
            border-left: 4px solid #dc3545;
            background: #ffe6e6;
        }

        .column-list li.existing {
            border-left: 4px solid #28a745;
        }

        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 10px;
        }

        .status-good {
            background: #28a745;
        }

        .status-warning {
            background: #ffc107;
        }

        .status-bad {
            background: #dc3545;
        }

        .update-form {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            border-radius: 15px;
            color: white;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #fff;
            color: #667eea;
        }

        .btn-primary:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .message-container {
            margin: 20px 0;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert i {
            font-size: 20px;
        }

        .database-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-card h4 {
            color: #666;
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
        }

        .stat-card .stat-value {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
        }

        .backup-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            color: #856404;
        }

        .backup-warning h4 {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
            }

            .main-content {
                margin-left: 0;
            }

            .database-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div style="text-align: center; margin-bottom: 30px;">
                <h2 style="color: white;">Database Tools</h2>
            </div>
            <div style="margin-top: 50px;">
                <a href="pharmacy.php" style="display: block; padding: 15px; color: white; text-decoration: none; border-radius: 8px; margin: 10px 0; background: rgba(255,255,255,0.1);">
                    <i class="fa fa-arrow-left"></i> Back to Pharmacy
                </a>
                <a href="sales.php" style="display: block; padding: 15px; color: white; text-decoration: none; border-radius: 8px; margin: 10px 0; background: rgba(255,255,255,0.1);">
                    <i class="fa fa-shopping-cart"></i> Sales Page
                </a>
                <a href="billing_dashboard.php" style="display: block; padding: 15px; color: white; text-decoration: none; border-radius: 8px; margin: 10px 0; background: rgba(255,255,255,0.1);">
                    <i class="fa fa-money-bill-wave"></i> Billing Dashboard
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="database-card">
                <div class="database-header">
                    <h1><i class="fa fa-database"></i> Database Structure Update</h1>
                    <p>Update your pharmacy database for the Unified Billing System</p>
                </div>

                <!-- Backup Warning -->
                <div class="backup-warning">
                    <h4><i class="fa fa-exclamation-triangle"></i> IMPORTANT: Backup Your Database</h4>
                    <p>Please ensure you have a backup of your database before proceeding. This update will modify your database structure and could potentially cause data loss if interrupted.</p>
                </div>

                <!-- Messages -->
                <div class="message-container">
                    <?php if (!empty($success_messages)): ?>
                        <?php foreach ($success_messages as $message): ?>
                            <div class="alert alert-success">
                                <i class="fa fa-check-circle"></i> <?php echo $message; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($error_messages)): ?>
                        <?php foreach ($error_messages as $message): ?>
                            <div class="alert alert-error">
                                <i class="fa fa-exclamation-circle"></i> <?php echo $message; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Current Database Stats -->
                <div class="database-stats">
                    <div class="stat-card">
                        <h4>Pharmacy Bills</h4>
                        <div class="stat-value">
                            <?php 
                            $count_query = "SELECT COUNT(*) as total FROM pharmacy_billing";
                            $result = mysqli_query($link, $count_query);
                            $row = mysqli_fetch_assoc($result);
                            echo $row['total'];
                            ?>
                        </div>
                    </div>
                    <div class="stat-card">
                        <h4>Missing Columns</h4>
                        <div class="stat-value" style="color: <?php echo (count(array_diff(['total_amount', 'paid_amount', 'balance_amount', 'payment_status'], $pharmacy_billing_columns)) > 0) ? '#dc3545' : '#28a745'; ?>">
                            <?php 
                            $missing = count(array_diff(['total_amount', 'paid_amount', 'balance_amount', 'payment_status'], $pharmacy_billing_columns));
                            echo $missing;
                            ?>
                        </div>
                    </div>
                    <div class="stat-card">
                        <h4>Database Status</h4>
                        <div class="stat-value">
                            <?php if ($missing == 0 && $has_billing_payments): ?>
                                <span style="color: #28a745;">Ready</span>
                            <?php elseif ($missing > 0): ?>
                                <span style="color: #dc3545;">Update Needed</span>
                            <?php else: ?>
                                <span style="color: #ffc107;">Partial</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Current Structure -->
                <div class="table-info">
                    <h3><i class="fa fa-table"></i> Current Database Structure</h3>
                    
                    <div class="column-list">
                        <h4>pharmacy_billing Table</h4>
                        <ul>
                            <?php 
                            $required_columns = ['total_amount', 'paid_amount', 'balance_amount', 'payment_status', 'payment_method', 'payment_date', 'payment_reference', 'updated_at'];
                            foreach ($required_columns as $column): 
                                $has_column = in_array($column, $pharmacy_billing_columns);
                            ?>
                                <li class="<?php echo $has_column ? 'existing' : 'missing'; ?>">
                                    <span class="status-indicator <?php echo $has_column ? 'status-good' : 'status-bad'; ?>"></span>
                                    <?php echo $column; ?>
                                    <?php if (!$has_column): ?>
                                        <span style="float: right; color: #dc3545; font-size: 12px;">MISSING</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="column-list">
                        <h4>pharmacy_billing_details Table</h4>
                        <ul>
                            <?php 
                            $required_details = ['patient_id', 'total_amount', 'created_at'];
                            foreach ($required_details as $column): 
                                $has_column = in_array($column, $pharmacy_billing_details_columns);
                            ?>
                                <li class="<?php echo $has_column ? 'existing' : 'missing'; ?>">
                                    <span class="status-indicator <?php echo $has_column ? 'status-good' : 'status-bad'; ?>"></span>
                                    <?php echo $column; ?>
                                    <?php if (!$has_column): ?>
                                        <span style="float: right; color: #dc3545; font-size: 12px;">MISSING</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="column-list">
                        <h4>Additional Tables</h4>
                        <ul>
                            <li class="<?php echo $has_billing_payments ? 'existing' : 'missing'; ?>">
                                <span class="status-indicator <?php echo $has_billing_payments ? 'status-good' : 'status-bad'; ?>"></span>
                                billing_payments table
                                <?php if (!$has_billing_payments): ?>
                                    <span style="float: right; color: #dc3545; font-size: 12px;">MISSING</span>
                                <?php endif; ?>
                            </li>
                            <li class="existing">
                                <span class="status-indicator status-good"></span>
                                patients table (foreign key reference)
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Update Form -->
                <div class="update-form">
                    <h3 style="margin-bottom: 20px; color: white;"><i class="fa fa-tools"></i> Update Database Structure</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="migrate_data">Migrate Existing Data?</label>
                            <div class="checkbox-group">
                                <input type="checkbox" id="migrate_data" name="migrate_data" value="yes">
                                <label for="migrate_data" style="margin-bottom: 0;">Yes, update existing records with calculated totals</label>
                            </div>
                            <small style="opacity: 0.8; display: block; margin-top: 5px;">This will calculate totals for all existing billing records</small>
                        </div>

                        <div class="form-group">
                            <p style="margin-bottom: 15px; font-size: 14px;">
                                <i class="fa fa-info-circle"></i> This update will:
                            </p>
                            <ul style="padding-left: 20px; font-size: 14px; opacity: 0.9;">
                                <li>Add missing columns to pharmacy_billing table</li>
                                <li>Add foreign key relationship with patients table</li>
                                <li>Create billing_payments table for payment tracking</li>
                                <li>Add indexes for better performance</li>
                                <li>Create consolidated_payments table for grouped billing</li>
                            </ul>
                        </div>

                        <div class="action-buttons">
                            <button type="submit" name="update_database" class="btn btn-primary">
                                <i class="fa fa-play"></i> Run Database Update
                            </button>
                            <a href="test_database.php" class="btn btn-warning">
                                <i class="fa fa-vial"></i> Test Connection
                            </a>
                            <a href="backup_database.php" class="btn btn-success">
                                <i class="fa fa-download"></i> Backup Database
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Post-Update Instructions -->
                <div class="database-card" style="background: #e8f4fd; border: 1px solid #b8daff;">
                    <h3 style="color: #004085; margin-bottom: 15px;"><i class="fa fa-lightbulb"></i> After Update Instructions</h3>
                    <ol style="padding-left: 20px; color: #004085;">
                        <li>Test the sales page to ensure bills are now saving correctly</li>
                        <li>Check that payment status shows as "PENDING" for new bills</li>
                        <li>Verify that totals are calculated automatically</li>
                        <li>Test the billing dashboard to see consolidated patient bills</li>
                        <li>If you encounter issues, restore from backup and contact support</li>
                    </ol>
                </div>

                <!-- Quick Test -->
                <div class="database-card" style="text-align: center;">
                    <h3 style="margin-bottom: 20px;">Quick Database Test</h3>
                    <button onclick="testDatabase()" class="btn btn-primary" style="margin: 10px;">
                        <i class="fa fa-vial"></i> Test Database Connection
                    </button>
                    <button onclick="testInsert()" class="btn btn-success" style="margin: 10px;">
                        <i class="fa fa-plus"></i> Test Data Insertion
                    </button>
                    <div id="testResult" style="margin-top: 20px;"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function testDatabase() {
            document.getElementById('testResult').innerHTML = '<div class="alert alert-success"><i class="fa fa-spinner fa-spin"></i> Testing database connection...</div>';
            
            $.ajax({
                url: 'test_db_connection.php',
                type: 'GET',
                success: function(response) {
                    document.getElementById('testResult').innerHTML = '<div class="alert alert-success"><i class="fa fa-check-circle"></i> ' + response + '</div>';
                },
                error: function() {
                    document.getElementById('testResult').innerHTML = '<div class="alert alert-error"><i class="fa fa-exclamation-circle"></i> Database connection failed!</div>';
                }
            });
        }

        function testInsert() {
            document.getElementById('testResult').innerHTML = '<div class="alert alert-success"><i class="fa fa-spinner fa-spin"></i> Testing data insertion...</div>';
            
            $.ajax({
                url: 'test_db_insert.php',
                type: 'GET',
                success: function(response) {
                    document.getElementById('testResult').innerHTML = '<div class="alert alert-success"><i class="fa fa-check-circle"></i> ' + response + '</div>';
                },
                error: function() {
                    document.getElementById('testResult').innerHTML = '<div class="alert alert-error"><i class="fa fa-exclamation-circle"></i> Data insertion test failed!</div>';
                }
            });
        }

        // Auto-refresh page after 30 seconds if there are errors
        setTimeout(function() {
            if (document.querySelector('.alert-error')) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>