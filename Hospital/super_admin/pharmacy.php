<?php
// Start session at the very beginning
session_start();

include "admin/connection.php";
include "includes/db_connect.php";
include "includes/sidebar.php";

// Make sure PDO is available
if (!isset($pdo)) {
    die("Database connection failed. PDO not initialized.");
}

// Get medications statistics
$total_medications = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as total FROM stock_master"))['total'];
$low_stock = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as total FROM stock_master WHERE product_quantity <= 10"))['total'];
$out_of_stock = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as total FROM stock_master WHERE product_quantity = 0"))['total'];
$total_value = mysqli_fetch_assoc(mysqli_query($link, "SELECT SUM(product_quantity * product_selling_price) as total FROM stock_master"))['total'];

// Get pending prescriptions count - Using PDO for consistency
try {
    $pending_stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM prescriptions 
        WHERE status = 'active' AND dispensed = 0
    ");
    $pending_prescriptions = $pending_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (PDOException $e) {
    $pending_prescriptions = 0;
}

// Handle prescription dispensing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['dispense_prescription'])) {
    $prescription_id = $_POST['prescription_id'];
    $quantity_dispensed = $_POST['quantity_dispensed'];
    $medication_id = $_POST['medication_id'];
    
    // Start transaction using PDO
    try {
        $pdo->beginTransaction();
        
        // Update stock using MySQLi (since stock_master uses MySQLi)
        $update_stock = "UPDATE stock_master SET product_quantity = product_quantity - ? WHERE id = ?";
        $stmt_mysqli = mysqli_prepare($link, $update_stock);
        mysqli_stmt_bind_param($stmt_mysqli, "ii", $quantity_dispensed, $medication_id);
        mysqli_stmt_execute($stmt_mysqli);
        
        // Mark prescription as dispensed AND completed using PDO
        $update_prescription = "UPDATE prescriptions SET dispensed = 1, status = 'completed', dispensed_date = NOW(), dispensed_by = ? WHERE id = ?";
        $stmt_pdo = $pdo->prepare($update_prescription);
        $user_id = $_SESSION['user_id'] ?? 0; // Use null coalescing to avoid undefined index
        $stmt_pdo->execute([$user_id, $prescription_id]);
        
        $pdo->commit();
        $_SESSION['success'] = "Prescription dispensed and marked as completed successfully!";
        header("Location: pharmacy.php?tab=prescriptions");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error dispensing prescription: " . $e->getMessage();
    }
}

// Get active tab
$active_tab = $_GET['tab'] ?? 'inventory';

// Get messages
$success_message = $_SESSION['success'] ?? null;
$error_message = $_SESSION['error'] ?? null;

// Clear messages after reading
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
/* Statistics Cards */
.medications-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    text-align: center;
    border-left: 4px solid #3b82f6;
}

.stat-card.success { border-left-color: #10b981; }
.stat-card.warning { border-left-color: #f59e0b; }
.stat-card.danger { border-left-color: #ef4444; }
.stat-card.info { border-left-color: #8b5cf6; }
.stat-card.purple { border-left-color: #a855f7; }

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 0.5rem 0;
}

.stat-label {
    color: #64748b;
    font-size: 0.875rem;
    font-weight: 500;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Tabs */
.tabs-container {
    background: white;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
    overflow: hidden;
}

.tabs {
    display: flex;
    border-bottom: 2px solid #e5e7eb;
}

.tab {
    padding: 1rem 2rem;
    border: none;
    background: none;
    font-size: 0.875rem;
    font-weight: 500;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.tab:hover {
    color: #3b82f6;
    background: #f8fafc;
}

.tab.active {
    color: #3b82f6;
    border-bottom: 2px solid #3b82f6;
    margin-bottom: -2px;
}

.tab-badge {
    background: #ef4444;
    color: white;
    padding: 0.125rem 0.5rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Search and Filters */
.search-filters {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    align-items: center;
    flex-wrap: wrap;
}

.search-box {
    position: relative;
    flex: 1;
    max-width: 400px;
}

.search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
}

.search-box input {
    width: 100%;
    padding: 0.75rem 0.75rem 0.75rem 2.5rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
}

.search-box input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.type-filter, .status-filter {
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    background: white;
    min-width: 150px;
}

.type-filter:focus, .status-filter:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.page-title {
    font-size: 1.875rem;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.page-subtitle {
    color: #64748b;
    margin-top: 0.5rem;
}

.add-medication-btn {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 0.375rem;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: background-color 0.2s;
}

.add-medication-btn:hover {
    background: #2563eb;
}

/* Table Styles */
.medications-table-container {
    background: white;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-top: 1.5rem;
}

.medications-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.medications-table th {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: #1565c0;
    border-bottom: 1px solid #90caf9;
    font-size: 0.875rem;
}

.medications-table td {
    padding: 1rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.medications-table tbody tr {
    transition: background-color 0.2s;
}

.medications-table tbody tr:hover {
    background-color: #f8fafc;
}

.medications-table tbody tr:last-child td {
    border-bottom: none;
}

/* Status Badges */
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.status-in-stock {
    background: #dcfce7;
    color: #166534;
}

.status-low-stock {
    background: #fef3c7;
    color: #92400e;
}

.status-out-of-stock {
    background: #fee2e2;
    color: #dc2626;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-dispensed {
    background: #dcfce7;
    color: #166534;
}

.status-completed {
    background: #dbeafe;
    color: #1e40af;
}

/* Table Actions */
.actions-cell {
    white-space: nowrap;
}

.table-actions {
    display: flex;
    gap: 0.5rem;
}

.table-actions .action-btn {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    text-decoration: none;
    border: 1px solid #e2e8f0;
    border-radius: 0.375rem;
    background: white;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.table-actions .action-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.action-btn-primary {
    background: #3b82f6 !important;
    color: white !important;
    border-color: #3b82f6 !important;
}

.action-btn-primary:hover {
    background: #2563eb !important;
    border-color: #2563eb !important;
}

.action-btn-success {
    background: #10b981 !important;
    color: white !important;
    border-color: #10b981 !important;
}

.action-btn-success:hover {
    background: #059669 !important;
    border-color: #059669 !important;
}

.action-btn-danger {
    color: #ef4444 !important;
    border-color: #fecaca !important;
}

.action-btn-danger:hover {
    background: #fef2f2 !important;
    color: #dc2626 !important;
    border-color: #fca5a5 !important;
}

/* Quantity Cell */
.quantity-cell {
    font-weight: 600;
    font-family: 'Courier New', monospace;
}

/* Product Name */
.medication-name {
    font-weight: 600;
    color: #1e293b;
}

/* Empty Table State */
.empty-table-state {
    padding: 0 !important;
}

.empty-table-state .empty-state {
    padding: 3rem;
    text-align: center;
    background: white;
}

.empty-table-state .empty-state i {
    font-size: 3rem;
    color: #9ca3af;
    margin-bottom: 1rem;
}

.empty-table-state .empty-state h3 {
    color: #374151;
    margin: 0 0 0.5rem 0;
}

.empty-table-state .empty-state p {
    color: #6b7280;
    margin: 0;
}

/* Action Buttons Container */
.action-buttons {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.secondary-btn {
    background: #6b7280;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 0.375rem;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: background-color 0.2s;
}

.secondary-btn:hover {
    background: #4b5563;
}

.warning-btn {
    background: #f59e0b;
}

.warning-btn:hover {
    background: #d97706;
}

.danger-btn {
    background: #ef4444;
}

.danger-btn:hover {
    background: #dc2626;
}

.secondary-btn.inventory-btn {
    background: #b406d3;
}
.secondary-btn.inventory-btn:hover {
    background: #b29adb;
}


.secondary-btn.supplier-btn {
    background: #1a06d3;
}
.secondary-btn.supplier-btn:hover {
    background: #198d8d;
}



.secondary-btn.inventory-btn:hover {
    background: #7c3aed;
}
/* Alert Messages */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.alert-error {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 50;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 0;
    border-radius: 0.75rem;
    width: 90%;
    max-width: 600px;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6b7280;
}

.modal-body {
    padding: 1.5rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #374151;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
}

.form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.patient-info {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

.patient-info p {
    margin: 0.25rem 0;
    color: #475569;
}

.patient-info strong {
    color: #1e293b;
}
</style>
</head>
<body>
<div class="main-content">
    <?php if ($success_message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle"></i>
        <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-clinic-medical"></i>
                Pharmacy Management
            </h1>
            <p class="page-subtitle">
                Manage medications inventory and prescriptions
            </p>
        </div>
        <a href="add_products.php" class="add-medication-btn">
            <i class="fas fa-plus"></i>
            Add Supplier Products
        </a>
    </div>

    <!-- Quick Action Buttons -->
    <div class="action-buttons">
        <a href="purchase.php" class="secondary-btn">
            <i class="fas fa-shopping-cart"></i>
            Purchase Medications
        </a>
        <a href="supplier.php" class="secondary-btn supplier-btn">
            <i class="fas fa-truck"></i>
            Supplier Management
        </a>
        <a href="inventorylist.php" class="secondary-btn inventory-btn">
            <i class="fas fa-boxes"></i>
            Inventory Management
        </a>
        <a href="sales.php" class="secondary-btn warning-btn">
            <i class="fas fa-cash-register"></i>
            Process Sale
        </a>
        <a href="bills.php" class="secondary-btn danger-btn">
            <i class="fas fa-receipt"></i>
            View Bills
        </a>
    </div>

    <!-- Medications Statistics -->
    <div class="medications-stats">
        <div class="stat-card">
            <p class="stat-number"><?php echo $total_medications; ?></p>
            <p class="stat-label">Total Medications</p>
        </div>
        
        <div class="stat-card success">
            <p class="stat-number"><?php echo $total_medications - $low_stock - $out_of_stock; ?></p>
            <p class="stat-label">In Stock</p>
        </div>
        
        <div class="stat-card warning">
            <p class="stat-number"><?php echo $low_stock; ?></p>
            <p class="stat-label">Low Stock</p>
        </div>
        
        <div class="stat-card danger">
            <p class="stat-number"><?php echo $out_of_stock; ?></p>
            <p class="stat-label">Out of Stock</p>
        </div>
        
        <div class="stat-card info">
            <p class="stat-number">Ksh <?php echo number_format($total_value ?: 0, 2); ?></p>
            <p class="stat-label">Total Value</p>
        </div>
        
        <div class="stat-card purple">
            <p class="stat-number"><?php echo $pending_prescriptions; ?></p>
            <p class="stat-label">Pending Prescriptions</p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-container">
        <div class="tabs">
            <a href="?tab=inventory" class="tab <?php echo $active_tab == 'inventory' ? 'active' : ''; ?>">
                <i class="fas fa-boxes"></i>
                Inventory
            </a>
            <a href="?tab=prescriptions" class="tab <?php echo $active_tab == 'prescriptions' ? 'active' : ''; ?>">
                <i class="fas fa-prescription"></i>
                Prescriptions
                <?php if ($pending_prescriptions > 0): ?>
                <span class="tab-badge"><?php echo $pending_prescriptions; ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <?php if ($active_tab == 'inventory'): ?>
    <!-- INVENTORY TAB -->
    <!-- Search and Filters -->
    <div class="search-filters">
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" placeholder="Search medications by name or manufacturer..." id="searchInput">
        </div>
        <select class="type-filter" id="companyFilter">
            <option value="">All Manufacturers</option>
            <?php
            $companies = mysqli_query($link, "SELECT DISTINCT product_company FROM stock_master ORDER BY product_company");
            while($company = mysqli_fetch_assoc($companies)) {
                echo '<option value="' . htmlspecialchars($company['product_company']) . '">' . htmlspecialchars($company['product_company']) . '</option>';
            }
            ?>
        </select>
        <select class="status-filter" id="stockFilter">
            <option value="">All Stock Status</option>
            <option value="in-stock">In Stock</option>
            <option value="low-stock">Low Stock</option>
            <option value="out-of-stock">Out of Stock</option>
        </select>
    </div>

    <!-- Medications Table -->
    <div class="medications-table-container">
        <table class="medications-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Manufacturer</th>
                    <th>Medication Name</th>
                    <th>Unit</th>
                    <th>Packing Size</th>
                    <th>Quantity</th>
                    <th>Selling Price</th>
                    <th>Total Value</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $res = mysqli_query($link, "SELECT * FROM stock_master ORDER BY product_name");
                $hasMedications = false;
                $count = 0;
                
                while($row = mysqli_fetch_array($res)) {
                    $hasMedications = true;
                    $count++;
                    $quantity = $row["product_quantity"];
                    $stockStatus = 'in-stock';
                    $statusClass = 'status-in-stock';
                    $statusText = 'In Stock';
                    
                    if ($quantity == 0) {
                        $stockStatus = 'out-of-stock';
                        $statusClass = 'status-out-of-stock';
                        $statusText = 'Out of Stock';
                    } elseif ($quantity <= 10) {
                        $stockStatus = 'low-stock';
                        $statusClass = 'status-low-stock';
                        $statusText = 'Low Stock';
                    }
                    
                    $totalValue = $quantity * $row["product_selling_price"];
                ?>
                <tr class="medication-row" data-company="<?php echo htmlspecialchars($row["product_company"]); ?>" data-stock="<?php echo $stockStatus; ?>">
                    <td><?php echo $count; ?></td>
                    <td><?php echo htmlspecialchars($row["product_company"]); ?></td>
                    <td class="medication-name"><?php echo htmlspecialchars($row["product_name"]); ?></td>
                    <td><?php echo htmlspecialchars($row["product_unit"]); ?></td>
                    <td><?php echo htmlspecialchars($row["packing_size"]); ?></td>
                    <td class="quantity-cell"><?php echo $quantity; ?></td>
                    <td>Ksh <?php echo number_format($row["product_selling_price"], 2); ?></td>
                    <td>Ksh <?php echo number_format($totalValue, 2); ?></td>
                    <td>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <?php echo $statusText; ?>
                        </span>
                    </td>
                    <td class="actions-cell">
                        <div class="table-actions">
                            <a href="edit_stock_master.php?id=<?php echo $row["id"]; ?>" class="action-btn action-btn-primary">
                                <i class="fas fa-edit"></i>
                                Edit
                            </a>
                            <a href="delete_products.php?id=<?php echo $row["id"]; ?>" class="action-btn action-btn-danger" onclick="return confirm('Are you sure you want to delete this medication?');">
                                <i class="fas fa-trash"></i>
                                Delete
                            </a>
                        </div>
                    </td>
                </tr>
                <?php } ?>
                
                <?php if (!$hasMedications): ?>
                <tr>
                    <td colspan="10" class="empty-table-state">
                        <div class="empty-state">
                            <i class="fas fa-pills"></i>
                            <h3>No Medications Found</h3>
                            <p>Get started by adding your first medication to the inventory.</p>
                            <a href="add_products.php" class="add-medication-btn" style="margin-top: 1rem;">
                                <i class="fas fa-plus"></i>
                                Add First Medication
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($active_tab == 'prescriptions'): ?>
    <!-- PRESCRIPTIONS TAB -->
    <div class="search-filters">
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" placeholder="Search prescriptions by patient name or medication..." id="prescriptionSearchInput">
        </div>
        <select class="status-filter" id="prescriptionStatusFilter">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="completed">Completed</option>
        </select>
    </div>

    <!-- Prescriptions Table -->
    <div class="medications-table-container">
        <table class="medications-table">
            <thead>
                <tr>
                    <th>Prescription ID</th>
                    <th>Patient Details</th>
                    <th>Medication</th>
                    <th>Dosage</th>
                    <th>Frequency</th>
                    <th>Duration</th>
                    <th>Prescribing Doctor</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Fetch prescriptions using PDO
                try {
                    $prescription_query = "
                        SELECT p.*, 
                               pt.first_name, pt.last_name, pt.patient_id,
                               u.first_name as doctor_first_name, u.last_name as doctor_last_name,
                               p.dispensed, p.dispensed_date
                        FROM prescriptions p
                        JOIN patients pt ON p.patient_id = pt.id
                        JOIN users u ON p.doctor_id = u.id
                        WHERE p.status IN ('active', 'completed')
                        ORDER BY CASE 
                            WHEN p.status = 'active' THEN 1 
                            WHEN p.status = 'completed' THEN 2 
                            ELSE 3 
                        END, p.prescription_date DESC
                    ";
                    $prescription_stmt = $pdo->query($prescription_query);
                    $prescriptions = $prescription_stmt->fetchAll(PDO::FETCH_ASSOC);
                    $hasPrescriptions = count($prescriptions) > 0;
                    
                    foreach($prescriptions as $prescription) {
                        $status = $prescription['status'];
                        $dispensed = $prescription['dispensed'];
                        
                        // Determine status class and text
                        if ($status == 'completed') {
                            $statusClass = 'status-completed';
                            $statusText = 'Completed';
                        } elseif ($dispensed) {
                            $statusClass = 'status-dispensed';
                            $statusText = 'Dispensed';
                        } else {
                            $statusClass = 'status-pending';
                            $statusText = 'Pending';
                        }
                ?>
                <tr class="prescription-row" 
                    data-patient="<?php echo htmlspecialchars($prescription['first_name'] . ' ' . $prescription['last_name']); ?>"
                    data-medication="<?php echo htmlspecialchars($prescription['medication_name']); ?>"
                    data-status="<?php echo $status; ?>">
                    <td><strong>#RX-<?php echo str_pad($prescription['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                    <td>
                        <strong><?php echo htmlspecialchars($prescription['first_name'] . ' ' . $prescription['last_name']); ?></strong><br>
                        <small style="color: #64748b;">ID: <?php echo htmlspecialchars($prescription['patient_id']); ?></small>
                    </td>
                    <td class="medication-name"><?php echo htmlspecialchars($prescription['medication_name']); ?></td>
                    <td><?php echo htmlspecialchars($prescription['dosage']); ?></td>
                    <td><?php echo htmlspecialchars($prescription['frequency']); ?></td>
                    <td><?php echo htmlspecialchars($prescription['duration']); ?></td>
                    <td>Dr. <?php echo htmlspecialchars($prescription['doctor_first_name'] . ' ' . $prescription['doctor_last_name']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($prescription['prescription_date'])); ?></td>
                    <td>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <?php echo $statusText; ?>
                        </span>
                        <?php if ($dispensed && $prescription['dispensed_date']): ?>
                        <br><small style="color: #64748b;"><?php echo date('M d, Y', strtotime($prescription['dispensed_date'])); ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="actions-cell">
                        <div class="table-actions">
<?php if ($status == 'active' && !$dispensed): ?>
    <a href="sales.php?prescription_id=<?php echo $prescription['id']; ?>" 
       class="action-btn action-btn-success"
       onclick="return confirm('Process sale for this prescription?');">
        <i class="fas fa-cash-register"></i>
        Dispense
    </a>
<?php elseif ($status == 'completed'): ?>
                                <button class="action-btn" disabled style="opacity: 0.5;">
                                    <i class="fas fa-check-circle"></i>
                                    Completed
                                </button>
                            <?php else: ?>
                                <button class="action-btn" disabled style="opacity: 0.5;">
                                    <i class="fas fa-clock"></i>
                                    Processing
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php 
                    }
                } catch (PDOException $e) {
                    echo '<tr><td colspan="10" style="color: red; text-align: center;">Error loading prescriptions: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                    $hasPrescriptions = false;
                }
                ?>
                
                <?php if (!$hasPrescriptions): ?>
                <tr>
                    <td colspan="10" class="empty-table-state">
                        <div class="empty-state">
                            <i class="fas fa-prescription"></i>
                            <h3>No Prescriptions Found</h3>
                            <p>No active prescriptions to dispense at the moment.</p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Dispense Prescription Modal -->
<div id="dispenseModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-hand-holding-medical"></i>
                Dispense Prescription
            </h3>
            <button class="modal-close" onclick="closeDispenseModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="patient-info" id="patientInfo">
                <!-- Patient info will be populated by JavaScript -->
            </div>
            
            <form method="POST" id="dispenseForm">
                <input type="hidden" name="dispense_prescription" value="1">
                <input type="hidden" name="prescription_id" id="dispense_prescription_id">
                
                <div class="form-group">
                    <label for="medication_id">Select Medication from Stock:</label>
                    <select name="medication_id" id="medication_id" class="form-control" required>
                        <option value="">Select Medication</option>
                        <?php
                        $stock_query = mysqli_query($link, "SELECT id, product_name, product_company, product_quantity FROM stock_master WHERE product_quantity > 0 ORDER BY product_name");
                        while($stock = mysqli_fetch_assoc($stock_query)) {
                            echo '<option value="' . $stock['id'] . '" data-available="' . $stock['product_quantity'] . '">';
                            echo htmlspecialchars($stock['product_name']) . ' - ' . htmlspecialchars($stock['product_company']) . ' (Available: ' . $stock['product_quantity'] . ')';
                            echo '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="quantity_dispensed">Quantity to Dispense:</label>
                    <input type="number" name="quantity_dispensed" id="quantity_dispensed" 
                           class="form-control" required min="1" 
                           placeholder="Enter quantity">
                    <small id="availableQuantity" style="color: #64748b; display: none;">
                        Available: <span id="availableQty">0</span>
                    </small>
                </div>
                
                <button type="submit" class="action-btn action-btn-success" style="width: 100%; padding: 0.75rem;">
                    <i class="fas fa-check"></i> Dispense Medication
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Inventory Tab - Search functionality
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const medicationRows = document.querySelectorAll('.medication-row');
        
        medicationRows.forEach(row => {
            const medicationName = row.querySelector('.medication-name').textContent.toLowerCase();
            const manufacturer = row.cells[1].textContent.toLowerCase();
            
            if (medicationName.includes(searchTerm) || manufacturer.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
}

// Manufacturer filter
const companyFilter = document.getElementById('companyFilter');
if (companyFilter) {
    companyFilter.addEventListener('change', function() {
        filterMedications();
    });
}

// Stock status filter
const stockFilter = document.getElementById('stockFilter');
if (stockFilter) {
    stockFilter.addEventListener('change', function() {
        filterMedications();
    });
}

function filterMedications() {
    const selectedCompany = document.getElementById('companyFilter').value;
    const selectedStock = document.getElementById('stockFilter').value;
    const medicationRows = document.querySelectorAll('.medication-row');
    
    medicationRows.forEach(row => {
        const company = row.getAttribute('data-company');
        const stock = row.getAttribute('data-stock');
        
        const companyMatch = !selectedCompany || company === selectedCompany;
        const stockMatch = !selectedStock || stock === selectedStock;
        
        if (companyMatch && stockMatch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Prescriptions Tab - Search functionality
const prescriptionSearchInput = document.getElementById('prescriptionSearchInput');
if (prescriptionSearchInput) {
    prescriptionSearchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const prescriptionRows = document.querySelectorAll('.prescription-row');
        
        prescriptionRows.forEach(row => {
            const patientName = row.getAttribute('data-patient').toLowerCase();
            const medication = row.getAttribute('data-medication').toLowerCase();
            
            if (patientName.includes(searchTerm) || medication.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
}

// Prescription status filter
const prescriptionStatusFilter = document.getElementById('prescriptionStatusFilter');
if (prescriptionStatusFilter) {
    prescriptionStatusFilter.addEventListener('change', function() {
        const selectedStatus = this.value;
        const prescriptionRows = document.querySelectorAll('.prescription-row');
        
        prescriptionRows.forEach(row => {
            const status = row.getAttribute('data-status');
            
            if (!selectedStatus || status === selectedStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
}

// Dispense Modal Functions
function openDispenseModal(prescriptionId, medicationName, patientName, patientId) {
    document.getElementById('dispense_prescription_id').value = prescriptionId;
    
    const patientInfo = document.getElementById('patientInfo');
    patientInfo.innerHTML = `
        <p><strong>Patient:</strong> ${patientName}</p>
        <p><strong>Patient ID:</strong> ${patientId}</p>
        <p><strong>Prescribed Medication:</strong> ${medicationName}</p>
    `;
    
    document.getElementById('dispenseModal').style.display = 'block';
}

function closeDispenseModal() {
    document.getElementById('dispenseModal').style.display = 'none';
    document.getElementById('dispenseForm').reset();
}

// Show available quantity when medication is selected
const medicationSelect = document.getElementById('medication_id');
if (medicationSelect) {
    medicationSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const available = selectedOption.getAttribute('data-available');
        
        if (available) {
            document.getElementById('availableQty').textContent = available;
            document.getElementById('availableQuantity').style.display = 'block';
            document.getElementById('quantity_dispensed').max = available;
        } else {
            document.getElementById('availableQuantity').style.display = 'none';
        }
    });
}

// Validate quantity before submission
const dispenseForm = document.getElementById('dispenseForm');
if (dispenseForm) {
    dispenseForm.addEventListener('submit', function(e) {
        const medicationSelect = document.getElementById('medication_id');
        const quantityInput = document.getElementById('quantity_dispensed');
        const selectedOption = medicationSelect.options[medicationSelect.selectedIndex];
        const available = parseInt(selectedOption.getAttribute('data-available'));
        const requested = parseInt(quantityInput.value);
        
        if (requested > available) {
            e.preventDefault();
            alert(`Cannot dispense ${requested} units. Only ${available} units available in stock.`);
            return false;
        }
    });
}

// Close modals when clicking outside
window.onclick = function(event) {
    const dispenseModal = document.getElementById('dispenseModal');
    
    if (event.target == dispenseModal) {
        closeDispenseModal();
    }
}

// Initialize filters on page load
if (typeof filterMedications === 'function') {
    filterMedications();
}
</script>

<?php
include "includes/footer.php";
?>
</body>
</html>