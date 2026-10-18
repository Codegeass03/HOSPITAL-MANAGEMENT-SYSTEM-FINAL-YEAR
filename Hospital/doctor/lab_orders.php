<?php
session_start();
include "includes/db_connect.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}



// Get messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success'], $_SESSION['error']);

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE lab_orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);
    } catch (PDOException $e) {
        $error_message = "Error updating lab order: " . $e->getMessage();
    }
}

include "includes/sidebar.php";

// Handle file upload for results
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['results_file'])) {
    $order_id = $_POST['order_id'];
    $file = $_FILES['results_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $file_name = "lab_results_" . $order_id . "_" . time() . "." . $file_ext;
            $upload_path = "../uploads/lab_results/" . $file_name;
            
            // Create directory if it doesn't exist
            if (!is_dir('../uploads/lab_results/')) {
                mkdir('../uploads/lab_results/', 0777, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Update status to completed and store results file path in clinical_notes
                $stmt = $pdo->prepare("UPDATE lab_orders SET status = 'completed', clinical_notes = ?, completed_at = NOW() WHERE id = ?");
                $stmt->execute(["Results file: " . $file_name, $order_id]);
                
                $_SESSION['success'] = "Lab results uploaded successfully!";
                header("Location: lab_orders.php");
                exit();
            } else {
                $error_message = "Error uploading file.";
            }
        } else {
            $error_message = "Invalid file type. Allowed: " . implode(', ', $allowed_ext);
        }
    }
}

// Handle individual test result updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_test_result'])) {
    $item_id = $_POST['item_id'];
    $result_value = $_POST['result_value'];
    $result_unit = $_POST['result_unit'];
    $reference_range = $_POST['reference_range'];
    $result_notes = $_POST['result_notes'];
    $status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE lab_order_items SET result_value = ?, result_unit = ?, reference_range = ?, result_notes = ?, status = ?, tested_at = NOW() WHERE id = ?");
        $stmt->execute([$result_value, $result_unit, $reference_range, $result_notes, $status, $item_id]);
        
        $_SESSION['success'] = "Test result updated successfully!";
        header("Location: lab_orders.php");
        exit();
    } catch (PDOException $e) {
        $error_message = "Error updating test result: " . $e->getMessage();
    }
}

// Fetch patients for the dropdown
try {
    $patients_stmt = $pdo->query("SELECT id, first_name, last_name, patient_id FROM patients ORDER BY first_name, last_name");
    $patients = $patients_stmt->fetchAll();
} catch (PDOException $e) {
    $patients = [];
    error_log("Error fetching patients: " . $e->getMessage());
}

// Fetch lab orders with filters
$status_filter = $_GET['status'] ?? '';
$search_term = $_GET['search'] ?? '';

// Updated query using lab_order_items table
$query = "
    SELECT 
        lo.*, 
        p.first_name, 
        p.last_name, 
        p.patient_id,
        lo_orderer.first_name as orderer_first_name,
        lo_orderer.last_name as orderer_last_name,
        lo_collector.first_name as collector_first_name,
        lo_collector.last_name as collector_last_name,
        GROUP_CONCAT(DISTINCT loi.test_name SEPARATOR ', ') as test_names,
        COUNT(loi.id) as test_count,
        SUM(CASE WHEN loi.status = 'completed' THEN 1 ELSE 0 END) as completed_tests
    FROM lab_orders lo
    JOIN patients p ON lo.patient_id = p.id
    JOIN users lo_orderer ON lo.ordered_by = lo_orderer.id
    LEFT JOIN users lo_collector ON lo.collected_by = lo_collector.id
    LEFT JOIN lab_order_items loi ON lo.id = loi.lab_order_id
    WHERE 1=1
";

$params = [];

if ($status_filter) {
    $query .= " AND lo.status = ?";
    $params[] = $status_filter;
}

if ($search_term) {
    $query .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_id LIKE ? OR loi.test_name LIKE ?)";
    $search_like = "%$search_term%";
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
}

$query .= " GROUP BY lo.id ORDER BY lo.order_date DESC, lo.id DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $lab_orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching lab orders: " . $e->getMessage();
    $lab_orders = [];
}

// Get status counts for filters
try {
    $status_counts_stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM lab_orders 
        GROUP BY status
    ");
    $status_counts = $status_counts_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $status_counts = [];
    error_log("Error getting status counts: " . $e->getMessage());
}

// Function to fetch individual test items for a lab order
function getLabOrderItems($pdo, $lab_order_id) {
    $stmt = $pdo->prepare("
        SELECT loi.* 
        FROM lab_order_items loi 
        WHERE loi.lab_order_id = ? 
        ORDER BY loi.id
    ");
    $stmt->execute([$lab_order_id]);
    return $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Orders - AuraCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0;
            color: #334155;
            font-size: 1.875rem;
            font-weight: 600;
        }
        
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .search-box {
            flex: 1;
            min-width: 300px;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }
        
        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
        }
        
        .status-filters {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .status-filter {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 2rem;
            font-size: 0.875rem;
            text-decoration: none;
            color: #6b7280;
            transition: all 0.2s;
        }
        
        .status-filter.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .status-filter:hover {
            background: #f3f4f6;
        }
        
        .status-filter.active:hover {
            background: #2563eb;
        }
        
        .card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        th {
            background: #f9fafb;
            font-weight: 600;
            font-size: 0.875rem;
            color: #374151;
        }
        
        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-ordered { background: #e0e7ff; color: #4338ca; }
        .status-in_progress { background: #fef3c7; color: #d97706; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #f3f4f6; color: #6b7280; }
        
        .test-status-ordered { background: #e0e7ff; color: #4338ca; }
        .test-status-collected { background: #dbeafe; color: #1e40af; }
        .test-status-in_progress { background: #fef3c7; color: #d97706; }
        .test-status-completed { background: #dcfce7; color: #166534; }
        
        .priority {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .priority-routine { background: #dbeafe; color: #1e40af; }
        .priority-urgent { background: #fef3c7; color: #d97706; }
        .priority-stat { background: #fee2e2; color: #dc2626; }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-info {
            background: #06b6d4;
            color: white;
        }
        
        .btn-info:hover {
            background: #0891b2;
        }
        
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
            justify-content: between;
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
        
        /* Responsive Modal Styles */
.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 0;
    border-radius: 0.75rem;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow-y: auto;
}

/* For mobile screens */
@media (max-width: 768px) {
    .modal-content {
        width: 95%;
        margin: 2% auto;
    }
    
    .main-content {
        margin-left: 0 !important;
        padding: 1rem !important;
    }
}

/* Responsive form layout */
.form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-col {
    flex: 1;
    min-width: 250px;
}

/* Test selection responsive styles */
.test-selection-container {
    width: 100%;
    overflow: hidden;
}

.test-select-wrapper {
    position: relative;
    width: 100%;
}

.test-select-wrapper select[multiple] {
    width: 100%;
    min-height: 150px;
    max-height: 200px;
    overflow-y: auto;
    padding: 0.5rem;
    resize: vertical;
}

/* Selected tests responsive display */
#selectedTestsList {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-height: 150px;
    overflow-y: auto;
    padding: 0.75rem;
}

.selected-test-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0.75rem;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.selected-test-name {
    flex: 1;
    min-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.selected-test-price {
    color: #059669;
    font-weight: 600;
    white-space: nowrap;
}

/* Modal body responsive padding */
.modal-body {
    padding: 1.5rem;
}

@media (max-width: 640px) {
    .modal-body {
        padding: 1rem;
    }
    
    .modal-header {
        padding: 1rem 1.5rem;
    }
    
    .form-group {
        margin-bottom: 0.75rem;
    }
    
    .btn {
        padding: 0.75rem;
        width: 100%;
    }
    
    .filters {
        flex-direction: column;
    }
    
    .search-box {
        min-width: 100% !important;
    }
    
    .status-filters {
        overflow-x: auto;
        padding-bottom: 0.5rem;
    }
}

/* Form control responsive sizing */
.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    box-sizing: border-box;
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

/* Button group responsiveness */
.button-group {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.button-group .btn {
    flex: 1;
    min-width: 120px;
}

/* Table responsiveness */
@media (max-width: 1024px) {
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    table {
        min-width: 800px;
    }
}

/* Adjust sidebar for smaller screens */
@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }
    
    .main-content {
        margin-left: 0;
        padding: 1rem;
    }
}
        .form-group {
            margin-bottom: 1rem;
            position: relative;
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
        
        .text-muted {
            color: #6b7280;
        }
        
        .test-items {
            background: #f8fafc;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .test-item {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 0.75rem;
        }
        
        .test-item-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .test-item-title {
            font-weight: 600;
            color: #111827;
        }
        
        .test-item-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            font-size: 0.875rem;
        }
        
        .test-result {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 0.75rem;
            margin-top: 0.5rem;
            border-radius: 0.25rem;
        }
        
        .expandable {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .expandable:hover {
            background: #f9fafb;
        }
        
        .test-details {
            display: none;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .patient-results {
            position: absolute;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            max-height: 200px;
            overflow-y: auto;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: none;
        }
        
        .patient-result-item {
            padding: 0.75rem;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .patient-result-item:hover {
            background: #f3f4f6;
        }
        
        .patient-result-item:last-child {
            border-bottom: none;
        }

        /* Enhanced dropdown styling */
        select[multiple] {
            min-height: 120px;
            padding: 0.5rem;
        }

        select[multiple] option {
            padding: 0.5rem 0.75rem;
            border-radius: 0.25rem;
            margin-bottom: 0.125rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        select[multiple] option:hover {
            background-color: #3b82f6 !important;
            color: white !important;
        }

        select[multiple] option:checked {
            background-color: #10b981 !important;
            color: white !important;
            font-weight: 500;
        }

        select[multiple] optgroup {
            font-weight: 600;
            background-color: #f8fafc;
            color: #374151;
            padding: 0.5rem;
        }

        /* Selected tests display */
        .selected-test-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0.75rem;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .selected-test-name {
            font-weight: 500;
            color: #374151;
        }

        .selected-test-price {
            color: #059669;
            font-weight: 600;
        }

        .remove-test-btn {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 0.25rem;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }

        .remove-test-btn:hover {
            background: #dc2626;
        }
    </style>
</head>
<body>
    <div class="main-content" style="padding-left: 100px;">
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
                    <i class="fas fa-vials"></i>
                    Lab Orders
                </h1>
                <p class="page-subtitle">Manage laboratory test orders and results</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="openOrderLabsModal()">
                    <i class="fas fa-plus"></i> Order Labs
                </button>
            </div>
        </div>
        
        <div class="filters">
            <div class="search-box">
                <form method="GET">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="search" placeholder="Search by patient name, ID, or test type..." 
                           value="<?php echo htmlspecialchars($search_term); ?>">
                </form>
            </div>
            <div class="status-filters">
                <a href="?status=" class="status-filter <?php echo !$status_filter ? 'active' : ''; ?>">
                    All (<?php echo array_sum($status_counts); ?>)
                </a>
                <?php foreach ($status_counts as $status => $count): ?>
                <a href="?status=<?php echo $status; ?>" 
                   class="status-filter <?php echo $status_filter == $status ? 'active' : ''; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $status)); ?> (<?php echo $count; ?>)
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-list"></i>
                    Lab Orders
                </h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Patient</th>
                            <th>Tests</th>
                            <th>Order Date</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Ordered By</th>
                            <th>Progress</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($lab_orders) > 0): ?>
                            <?php foreach ($lab_orders as $order): ?>
                            <tr class="expandable" onclick="toggleOrderDetails(<?php echo $order['id']; ?>)">
                                <td>
                                    #LO-<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?>
                                    <?php if ($order['order_number']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($order['order_number']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong><br>
                                    <small class="text-muted">ID: <?php echo htmlspecialchars($order['patient_id']); ?></small>
                                </td>
                                <td>
                                    <?php if ($order['test_names']): ?>
                                        <?php 
                                        $test_names = explode(', ', $order['test_names']);
                                        if (count($test_names) > 2) {
                                            echo htmlspecialchars($test_names[0]) . ', ' . htmlspecialchars($test_names[1]) . '...';
                                        } else {
                                            echo htmlspecialchars($order['test_names']);
                                        }
                                        ?>
                                        <br><small class="text-muted"><?php echo $order['test_count']; ?> test(s)</small>
                                    <?php else: ?>
                                        <span class="text-muted">No tests specified</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order['order_date']): ?>
                                        <?php echo date('M j, Y g:i A', strtotime($order['order_date'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order['priority']): ?>
                                        <span class="priority priority-<?php echo $order['priority']; ?>">
                                            <?php echo ucfirst($order['priority']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Routine</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($order['orderer_first_name']): ?>
                                        Dr. <?php echo htmlspecialchars($order['orderer_first_name'] . ' ' . $order['orderer_last_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order['test_count'] > 0): ?>
                                        <small class="text-muted">
                                            <?php echo $order['completed_tests']; ?>/<?php echo $order['test_count']; ?> completed
                                        </small>
                                        <div style="background: #e5e7eb; height: 4px; border-radius: 2px; margin-top: 0.25rem;">
                                            <div style="background: #10b981; height: 100%; border-radius: 2px; width: <?php echo ($order['completed_tests'] / $order['test_count']) * 100; ?>%;"></div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">No tests</span>
                                    <?php endif; ?>
                                </td>
                               <td>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <!-- <button class="btn btn-sm btn-primary" 
                onclick="event.stopPropagation(); openUpdateModal(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>')">
            <i class="fas fa-edit"></i> Update
        </button> -->
        
        <!-- View Results button for completed orders -->
        <?php if ($order['status'] === 'completed'): ?>
            <a href="lab_reports.php?order_id=<?php echo $order['id']; ?>" class="btn btn-sm btn-success" target="_blank" onclick="event.stopPropagation();">
                <i class="fas fa-file-medical"></i> Results
            </a>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['role']) && ($_SESSION['role'] == 'lab_tech' || $_SESSION['role'] == 'super_admin')): ?>
        <button class="btn btn-sm btn-info" 
                onclick="event.stopPropagation(); openOrderDetails(<?php echo $order['id']; ?>)">
            <i class="fas fa-eye"></i> Details
        </button>
        <?php endif; ?>
    </div>
</td>
                            </tr>
                            <tr id="order-details-<?php echo $order['id']; ?>" style="display: none;">
                                <td colspan="9" style="background: #f8fafc; padding: 0;">
                                    <div class="test-items">
                                        <h4 style="margin: 0 0 1rem 0; color: #374151;">Test Details</h4>
                                        <?php 
                                        $test_items = getLabOrderItems($pdo, $order['id']);
                                        if (count($test_items) > 0): 
                                            foreach ($test_items as $item): 
                                        ?>
                                        <div class="test-item">
                                            <div class="test-item-header">
                                                <div class="test-item-title">
                                                    <?php echo htmlspecialchars($item['test_name']); ?>
                                                    <?php if ($item['test_code']): ?>
                                                        <small class="text-muted">(<?php echo htmlspecialchars($item['test_code']); ?>)</small>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="status test-status-<?php echo $item['status']; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                                </span>
                                            </div>
                                            <div class="test-item-details">
                                                <div>
                                                    <strong>Specimen:</strong> <?php echo htmlspecialchars($item['specimen_type'] ?? 'N/A'); ?>
                                                </div>
                                                <div>
                                                    <strong>Instructions:</strong> <?php echo htmlspecialchars($item['instructions'] ?? 'N/A'); ?>
                                                </div>
                                            </div>
                                            <?php if ($item['result_value']): ?>
                                            <div class="test-result">
                                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 0.5rem;">
                                                    <div><strong>Result:</strong> <?php echo htmlspecialchars($item['result_value']); ?> <?php echo htmlspecialchars($item['result_unit'] ?? ''); ?></div>
                                                    <div><strong>Range:</strong> <?php echo htmlspecialchars($item['reference_range'] ?? 'N/A'); ?></div>
                                                    <div><strong>Status:</strong> <?php echo ucfirst($item['status']); ?></div>
                                                </div>
                                                <?php if ($item['result_notes']): ?>
                                                    <div><strong>Notes:</strong> <?php echo htmlspecialchars($item['result_notes']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($_SESSION['role'] == 'lab_tech' || $_SESSION['role'] == 'super_admin'): ?>
                                            <div style="margin-top: 0.75rem;">
                                                <button class="btn btn-sm btn-success" 
                                                        onclick="openTestResultModal(<?php echo $item['id']; ?>, '<?php echo $item['result_value'] ?? ''; ?>', '<?php echo $item['result_unit'] ?? ''; ?>', '<?php echo $item['reference_range'] ?? ''; ?>', '<?php echo $item['result_notes'] ?? ''; ?>', '<?php echo $item['status']; ?>')">
                                                    <i class="fas fa-flask"></i> Enter Result
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php else: ?>
                                        <div style="text-align: center; padding: 2rem; color: #6b7280;">
                                            <i class="fas fa-vial" style="font-size: 2rem; margin-bottom: 1rem; display: block; color: #d1d5db;"></i>
                                            No test items found for this order
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 3rem; color: #6b7280;">
                                    <i class="fas fa-vials" style="font-size: 3rem; margin-bottom: 1rem; display: block; color: #d1d5db;"></i>
                                    No lab orders found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Order Labs Modal -->
    <div id="orderLabsModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3 class="modal-title">Order Laboratory Tests</h3>
                <button class="modal-close" onclick="closeOrderLabsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="process_lab_order.php" id="orderLabsForm">
                    <!-- Simple Patient Selection Dropdown -->
                    <div class="form-group">
                        <label for="patient_id">Patient: *</label>
                        <select name="patient_id" id="patient_id" class="form-control" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>">
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="priority">Priority:</label>
                        <select name="priority" id="priority" class="form-control" required>
                            <option value="routine">Routine</option>
                            <option value="urgent">Urgent</option>
                            <option value="stat">Stat</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="clinical_notes">Clinical Notes:</label>
                        <textarea name="clinical_notes" id="clinical_notes" class="form-control" rows="3" 
                                  placeholder="Enter clinical notes and indications..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="selected_tests">Select Lab Tests:</label>
                        
                        <!-- Search Box for Tests -->
                        <div class="search-box" style="margin-bottom: 0.5rem;">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="testSearch" placeholder="Search tests..." style="width: 100%; padding: 0.75rem 0.75rem 0.75rem 2.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem;">
                        </div>

                        <select name="tests[]" id="selected_tests" class="form-control" multiple="multiple" style="height: auto; min-height: 120px;" onchange="updateTotalPrice()">
                            <?php
                            // Fetch lab-related services from services table
                            try {
                                $services_stmt = $pdo->prepare("
                                    SELECT * FROM services 
                                    WHERE (category LIKE '%lab%' OR category LIKE '%laboratory%' OR name LIKE '%test%' OR name LIKE '%lab%')
                                    AND is_active = 1 
                                    ORDER BY category, name
                                ");
                                $services_stmt->execute();
                                $lab_services = $services_stmt->fetchAll();
                                $current_category = '';
                                
                                if (count($lab_services) > 0) {
                                    foreach ($lab_services as $service) {
                                        if ($service['category'] != $current_category) {
                                            if ($current_category != '') echo '</optgroup>';
                                            echo '<optgroup label="' . htmlspecialchars($service['category'] ?? 'Laboratory Services') . '" data-category="' . htmlspecialchars($service['category']) . '">';
                                            $current_category = $service['category'];
                                        }
                                        $display_text = htmlspecialchars($service['name']) . ' - KSH ' . number_format($service['price'], 2);
                                        $full_description = htmlspecialchars($service['description'] ?? 'No description available');
                                        echo '<option value="' . $service['id'] . '" data-price="' . $service['price'] . '" data-name="' . htmlspecialchars($service['name']) . '" title="' . $full_description . '">' . $display_text . '</option>';
                                    }
                                    if ($current_category != '') echo '</optgroup>';
                                } else {
                                    // Fallback to all services
                                    $all_services_stmt = $pdo->prepare("SELECT * FROM services WHERE is_active = 1 ORDER BY category, name");
                                    $all_services_stmt->execute();
                                    $all_services = $all_services_stmt->fetchAll();
                                    $current_category = '';
                                    
                                    if (count($all_services) > 0) {
                                        foreach ($all_services as $service) {
                                            if ($service['category'] != $current_category) {
                                                if ($current_category != '') echo '</optgroup>';
                                                echo '<optgroup label="' . htmlspecialchars($service['category'] ?? 'Services') . '" data-category="' . htmlspecialchars($service['category']) . '">';
                                                $current_category = $service['category'];
                                            }
                                            $display_text = htmlspecialchars($service['name']) . ' - KSH ' . number_format($service['price'], 2);
                                            $full_description = htmlspecialchars($service['description'] ?? 'No description available');
                                            echo '<option value="' . $service['id'] . '" data-price="' . $service['price'] . '" data-name="' . htmlspecialchars($service['name']) . '" title="' . $full_description . '">' . $display_text . '</option>';
                                        }
                                        if ($current_category != '') echo '</optgroup>';
                                    } else {
                                        echo '<option value="" disabled>No services available.</option>';
                                    }
                                }
                            } catch (PDOException $e) {
                                echo '<option value="" disabled>Error loading services.</option>';
                            }
                            ?>
                        </select>
                        
                        <small class="text-muted" style="display: block; margin-top: 0.5rem;">
                            • Hold Ctrl/Cmd to select multiple tests<br>
                            • Use search to filter tests quickly
                        </small>
                        
                        <!-- Selected Tests Display -->
                        <div id="selectedTestsDisplay" style="margin-top: 1rem; display: none;">
                            <div style="font-weight: 500; color: #374151; margin-bottom: 0.5rem;">Selected Tests:</div>
                            <div id="selectedTestsList" style="background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 0.375rem; padding: 0.75rem; max-height: 120px; overflow-y: auto;"></div>
                        </div>
                        
                        <!-- Total Price Display -->
                        <div style="margin-top: 1rem; padding: 1rem; background: #f8fafc; border-radius: 0.375rem; border: 1px solid #e5e7eb;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <strong>Total Selected Amount:</strong>
                                <span id="totalPrice" style="font-weight: bold; color: #059669; font-size: 1.1em;">KSH 0.00</span>
                            </div>
                            <small class="text-muted">This amount will be automatically added to the patient's billing record</small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success" style="width: 100%;">
                        <i class="fas fa-paper-plane"></i> Submit Lab Order
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Lab Order Status</h3>
                <button class="modal-close" onclick="closeUpdateModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="updateForm">
                    <input type="hidden" name="order_id" id="update_order_id">
                    <input type="hidden" name="update_status" value="1">
                    
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status" class="form-control" required>
                            <option value="ordered">Ordered</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Enter Test Result Modal -->
    <div id="testResultModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Enter Test Result</h3>
                <button class="modal-close" onclick="closeTestResultModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="testResultForm">
                    <input type="hidden" name="item_id" id="test_item_id">
                    <input type="hidden" name="update_test_result" value="1">
                    
                    <div class="form-group">
                        <label for="result_value">Result Value:</label>
                        <input type="text" name="result_value" id="result_value" class="form-control" 
                               placeholder="Enter test result value">
                    </div>
                    
                    <div class="form-group">
                        <label for="result_unit">Unit:</label>
                        <input type="text" name="result_unit" id="result_unit" class="form-control" 
                               placeholder="e.g., mg/dL, mmol/L">
                    </div>
                    
                    <div class="form-group">
                        <label for="reference_range">Reference Range:</label>
                        <input type="text" name="reference_range" id="reference_range" class="form-control" 
                               placeholder="e.g., 3.4 - 7.0 mg/dL">
                    </div>
                    
                    <div class="form-group">
                        <label for="result_notes">Result Notes:</label>
                        <textarea name="result_notes" id="result_notes" class="form-control" rows="4" 
                                  placeholder="Enter result interpretation and notes..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="test_status">Status:</label>
                        <select name="status" id="test_status" class="form-control" required>
                            <option value="ordered">Ordered</option>
                            <option value="collected">Collected</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-success" style="width: 100%;">
                        <i class="fas fa-save"></i> Save Result
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function toggleOrderDetails(orderId) {
            const detailsRow = document.getElementById('order-details-' + orderId);
            if (detailsRow.style.display === 'none') {
                detailsRow.style.display = 'table-row';
            } else {
                detailsRow.style.display = 'none';
            }
        }
        
        function openUpdateModal(orderId, currentStatus) {
            document.getElementById('update_order_id').value = orderId;
            document.getElementById('status').value = currentStatus;
            document.getElementById('updateModal').style.display = 'block';
        }
        
        function closeUpdateModal() {
            document.getElementById('updateModal').style.display = 'none';
        }
        
        function openTestResultModal(itemId, resultValue, resultUnit, referenceRange, resultNotes, currentStatus) {
            document.getElementById('test_item_id').value = itemId;
            document.getElementById('result_value').value = resultValue || '';
            document.getElementById('result_unit').value = resultUnit || '';
            document.getElementById('reference_range').value = referenceRange || '';
            document.getElementById('result_notes').value = resultNotes || '';
            document.getElementById('test_status').value = currentStatus;
            document.getElementById('testResultModal').style.display = 'block';
        }
        
        function closeTestResultModal() {
            document.getElementById('testResultModal').style.display = 'none';
        }
        
        function openOrderDetails(orderId) {
            toggleOrderDetails(orderId);
        }
        
        function openOrderLabsModal() {
            document.getElementById('orderLabsModal').style.display = 'block';
            // Clear previous selections
            document.getElementById('patient_id').value = '';
            document.getElementById('clinical_notes').value = '';
            
            // Reset priority to default
            document.getElementById('priority').value = 'routine';
            
            // Clear test selections and reset total
            const select = document.getElementById('selected_tests');
            select.selectedIndex = -1;
            updateTotalPrice();
        }
        
        function closeOrderLabsModal() {
            document.getElementById('orderLabsModal').style.display = 'none';
        }
        
        function updateTotalPrice() {
            let total = 0;
            const select = document.getElementById('selected_tests');
            const selectedOptions = Array.from(select.selectedOptions);
            const selectedTestsDisplay = document.getElementById('selectedTestsDisplay');
            const selectedTestsList = document.getElementById('selectedTestsList');
            
            // Clear previous display
            selectedTestsList.innerHTML = '';
            
            // Update selected tests display
            if (selectedOptions.length > 0) {
                selectedTestsDisplay.style.display = 'block';
                
                selectedOptions.forEach(option => {
                    const testName = option.text.split(' - KSH')[0];
                    const testPrice = parseFloat(option.getAttribute('data-price'));
                    total += testPrice;
                    
                    const testItem = document.createElement('div');
                    testItem.className = 'selected-test-item';
                    testItem.innerHTML = `
                        <span class="selected-test-name">${testName}</span>
                        <div style="display: flex; align-items: center;">
                            <span class="selected-test-price">KSH ${testPrice.toFixed(2)}</span>
                            <button type="button" class="remove-test-btn" onclick="removeTest(${option.value})" title="Remove test">×</button>
                        </div>
                    `;
                    selectedTestsList.appendChild(testItem);
                });
            } else {
                selectedTestsDisplay.style.display = 'none';
            }
            
            // Update total price
            document.getElementById('totalPrice').textContent = 'KSH ' + total.toFixed(2);
        }

        // Function to remove a test
        function removeTest(testId) {
            const select = document.getElementById('selected_tests');
            const option = select.querySelector(`option[value="${testId}"]`);
            
            if (option) {
                option.selected = false;
                updateTotalPrice();
            }
        }

        // Test search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const testSearch = document.getElementById('testSearch');
            const selectedTests = document.getElementById('selected_tests');
            
            if (testSearch && selectedTests) {
                testSearch.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const options = selectedTests.getElementsByTagName('option');
                    const optgroups = selectedTests.getElementsByTagName('optgroup');
                    
                    let visibleGroups = 0;
                    
                    // Filter options and optgroups
                    for (let optgroup of optgroups) {
                        let visibleOptions = 0;
                        const optionsInGroup = optgroup.getElementsByTagName('option');
                        
                        for (let option of optionsInGroup) {
                            const optionText = option.textContent.toLowerCase();
                            const optionName = option.getAttribute('data-name').toLowerCase();
                            
                            if (optionText.includes(searchTerm) || optionName.includes(searchTerm)) {
                                option.style.display = '';
                                visibleOptions++;
                            } else {
                                option.style.display = 'none';
                            }
                        }
                        
                        // Show/hide optgroup based on visible options
                        if (visibleOptions > 0 || searchTerm === '') {
                            optgroup.style.display = '';
                            visibleGroups++;
                        } else {
                            optgroup.style.display = 'none';
                        }
                    }
                });
            }
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const updateModal = document.getElementById('updateModal');
            const testResultModal = document.getElementById('testResultModal');
            const orderLabsModal = document.getElementById('orderLabsModal');
            
            if (event.target == updateModal) {
                closeUpdateModal();
            }
            if (event.target == testResultModal) {
                closeTestResultModal();
            }
            if (event.target == orderLabsModal) {
                closeOrderLabsModal();
            }
        }
    </script>
</body>
</html>
<?php include "includes/footer.php"; ?>