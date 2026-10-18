<?php
session_start();
include "admin/connection.php";
include "includes/sidebar.php";

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Set database to hospital
mysqli_select_db($link, "hospital");

// Handle bill payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $bill_id = $_POST['bill_id'];
    $payment_method = $_POST['payment_method'];
    $payment_amount = $_POST['payment_amount'];
    
    try {
        // Get current bill details
        $bill_query = "SELECT * FROM pharmacy_billing WHERE id = '$bill_id'";
        $bill_result = mysqli_query($link, $bill_query);
        $bill = mysqli_fetch_assoc($bill_result);
        
        if ($bill) {
            // Update payment status and method
            $update_query = "UPDATE pharmacy_billing SET 
                            payment_status = 'paid', 
                            payment_method = '$payment_method',
                            paid_amount = '$payment_amount',
                            payment_date = NOW() 
                            WHERE id = '$bill_id'";
            
            if (mysqli_query($link, $update_query)) {
                $_SESSION['success'] = "Payment processed successfully for Bill #" . $bill['bill_no'];
            } else {
                throw new Exception("Error updating payment: " . mysqli_error($link));
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    // header("Location: pharmacy_billing.php");
    // exit();
}

// Handle bill deletion
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    try {
        // Start transaction
        mysqli_begin_transaction($link);
        
        // Delete bill details first
        $delete_details = "DELETE FROM pharmacy_billing_details WHERE billing_id = '$delete_id'";
        mysqli_query($link, $delete_details);
        
        // Delete main bill
        $delete_bill = "DELETE FROM pharmacy_billing WHERE id = '$delete_id'";
        mysqli_query($link, $delete_bill);
        
        mysqli_commit($link);
        $_SESSION['success'] = "Bill deleted successfully";
        
    } catch (Exception $e) {
        mysqli_rollback($link);
        $_SESSION['error'] = "Error deleting bill: " . $e->getMessage();
    }
    
    header("Location: pharmacy_billing.php");
    exit();
}

// Get filter parameters
$filter_date = $_GET['date'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_customer = $_GET['customer'] ?? '';

// Build query with filters
$query = "SELECT pb.*, 
          COALESCE(SUM(pbd.total_amount), 0) as total_amount,
          COUNT(pbd.id) as item_count
          FROM pharmacy_billing pb
          LEFT JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
          WHERE 1=1";

if (!empty($filter_date)) {
    $query .= " AND DATE(pb.date) = '$filter_date'";
}

if (!empty($filter_status)) {
    $query .= " AND pb.payment_status = '$filter_status'";
}

if (!empty($filter_customer)) {
    $query .= " AND (pb.CustomerName LIKE '%$filter_customer%' OR pb.CustomerContact LIKE '%$filter_customer%')";
}

$query .= " GROUP BY pb.id ORDER BY pb.date DESC, pb.id DESC";

$result = mysqli_query($link, $query);
$bills = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $bills[] = $row;
    }
}

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total_bills,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_bills,
                SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_bills,
                COALESCE(SUM(pbd.total_amount), 0) as total_revenue
                FROM pharmacy_billing pb
                LEFT JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
                WHERE DATE(pb.date) = CURDATE()";

$stats_result = mysqli_query($link, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get today's revenue
$today_revenue = $stats['total_revenue'] ?? 0;
$total_bills = $stats['total_bills'] ?? 0;
$paid_bills = $stats['paid_bills'] ?? 0;
$pending_bills = $stats['pending_bills'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Billing - Equity Afia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        .page-header {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            margin: 0;
            color: #1f2937;
            font-size: 1.875rem;
            font-weight: 700;
        }
        
        .page-header p {
            margin: 0.5rem 0 0 0;
            color: #6b7280;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
            border-left: 4px solid #8b5cf6;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .stat-card.revenue { border-left-color: #10b981; }
        .stat-card.total { border-left-color: #3b82f6; }
        .stat-card.paid { border-left-color: #10b981; }
        .stat-card.pending { border-left-color: #f59e0b; }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-card.revenue .stat-icon { background: #dcfce7; color: #10b981; }
        .stat-card.total .stat-icon { background: #dbeafe; color: #3b82f6; }
        .stat-card.paid .stat-icon { background: #dcfce7; color: #10b981; }
        .stat-card.pending .stat-icon { background: #fef3c7; color: #f59e0b; }
        
        .stat-content h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .stat-content p {
            margin: 0.25rem 0 0 0;
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .filters-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .filters-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .filters-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }
        
        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: #8b5cf6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #7c3aed;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid #d1d5db;
            color: #374151;
        }
        
        .btn-outline:hover {
            background: #f9fafb;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }
        
        .bills-table {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .table-header h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
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
            color: #374151;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        td {
            font-size: 0.875rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-paid { background: #dcfce7; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        
        .bill-type {
            padding: 0.25rem 0.5rem;
            background: #e0e7ff;
            color: #3730a3;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .bill-type.walkin { background: #dbeafe; color: #1e40af; }
        .bill-type.insurance { background: #f3e8ff; color: #7c3aed; }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }
        
        .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
        }
        
        .payment-summary {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1rem 0;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }
        
        .view-details {
            color: #8b5cf6;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .view-details:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .filters-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>
        
        <div class="page-header">
            <h1><i class="fas fa-pills"></i> Pharmacy Billing</h1>
            <p>Manage pharmacy sales and process payments</p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card revenue">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-content">
                    <h3>Ksh <?php echo number_format($today_revenue, 2); ?></h3>
                    <p>Today's Revenue</p>
                </div>
            </div>
            
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_bills; ?></h3>
                    <p>Today's Bills</p>
                </div>
            </div>
            
            <div class="stat-card paid">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $paid_bills; ?></h3>
                    <p>Paid Bills</p>
                </div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $pending_bills; ?></h3>
                    <p>Pending Bills</p>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-card">
            <div class="filters-header">
                <h3 class="filters-title">Filter Bills</h3>
            </div>
            <form method="GET" class="filters-form">
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>">
                </div>
                
                <div class="form-group">
                    <label for="status">Payment Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="paid" <?php echo $filter_status == 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="customer">Customer Search</label>
                    <input type="text" class="form-control" id="customer" name="customer" 
                           value="<?php echo htmlspecialchars($filter_customer); ?>" 
                           placeholder="Customer name or contact...">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="pharmacy_billing.php" class="btn btn-outline">
                        <i class="fas fa-refresh"></i> Clear
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Bills Table -->
        <div class="bills-table">
            <div class="table-header">
                <h2><i class="fas fa-list"></i> Pharmacy Bills</h2>
                <!-- <a href="pharmacy_billing.php" class="btn btn-primary" target="_blank">
                    <i class="fas fa-plus"></i> Create New Bill
                </a> -->
            </div>
            
            <?php if (count($bills) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Bill No.</th>
                        <th>Customer</th>
                        <th>Date & Time</th>
                        <th>Items</th>
                        <th>Total Amount</th>
                        <th>Bill Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bills as $bill): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($bill['bill_no']); ?></strong>
                            <?php if ($bill['patient_id']): ?>
                            <br><small>PID: <?php echo htmlspecialchars($bill['patient_id']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($bill['CustomerName']); ?></strong>
                            <?php if ($bill['CustomerName']): ?>
                            <br><small><?php echo htmlspecialchars($bill['CustomerName']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M j, Y g:i A', strtotime($bill['date'])); ?></td>
                        <td><?php echo $bill['item_count']; ?> items</td>
                        <td><strong>Ksh <?php echo number_format($bill['total_amount'], 2); ?></strong></td>
                        <td>
                            <span class="bill-type <?php echo $bill['bill_type']; ?>">
                                <?php echo ucfirst($bill['bill_type']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $bill['payment_status']; ?>">
                                <?php echo ucfirst($bill['payment_status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if ($bill['payment_status'] == 'pending'): ?>
                                <button class="btn btn-success btn-sm" 
                                        onclick="openPaymentModal(
                                            '<?php echo $bill['id']; ?>',
                                            '<?php echo $bill['bill_no']; ?>',
                                            '<?php echo $bill['total_amount']; ?>',
                                            '<?php echo htmlspecialchars($bill['CustomerName']); ?>'
                                        )">
                                    <i class="fas fa-money-bill"></i> Pay
                                </button>
                                <?php endif; ?>
                                
                                <a href="view_bill_details.php?bill_id=<?php echo $bill['id']; ?>" 
                                   class="btn btn-outline btn-sm" target="_blank">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                
                                <button class="btn btn-danger btn-sm" 
                                        onclick="confirmDelete(<?php echo $bill['id']; ?>, '<?php echo $bill['bill_no']; ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <h3>No Bills Found</h3>
                <p>No pharmacy bills match your current filters.</p>
                <a href="pharmacy_billing.php" class="btn btn-primary" target="_blank" style="margin-top: 1rem;">
                    <i class="fas fa-plus"></i> Create First Bill
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Process Pharmacy Bill Payment</h3>
                <button class="close" onclick="closePaymentModal()">&times;</button>
            </div>
            <form method="POST" id="paymentForm">
                <input type="hidden" name="bill_id" id="modal_bill_id">
                
                <div class="payment-summary">
                    <div class="summary-row">
                        <span>Bill Number:</span>
                        <strong id="modal_bill_number"></strong>
                    </div>
                    <div class="summary-row">
                        <span>Customer:</span>
                        <strong id="modal_customer"></strong>
                    </div>
                    <div class="summary-row">
                        <span>Total Amount:</span>
                        <strong id="modal_total">Ksh 0.00</strong>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="payment_amount">Payment Amount</label>
                    <input type="number" class="form-control" id="payment_amount" name="payment_amount" 
                           step="0.01" min="0.01" required readonly>
                    <small style="color: #6b7280; font-size: 0.75rem;">Full payment required for pharmacy bills</small>
                </div>
                
                <div class="form-group">
                    <label for="payment_method">Payment Method</label>
                    <select class="form-control" id="payment_method" name="payment_method" required>
                        <option value="cash">Cash</option>
                        <option value="mpesa">M-Pesa</option>
                        <option value="card">Credit/Debit Card</option>
                        <option value="insurance">Insurance</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-success" name="process_payment" style="flex: 1;">
                        <i class="fas fa-check"></i> Confirm Payment
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closePaymentModal()" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openPaymentModal(billId, billNumber, totalAmount, customerName) {
            document.getElementById('modal_bill_id').value = billId;
            document.getElementById('modal_bill_number').textContent = billNumber;
            document.getElementById('modal_total').textContent = 'Ksh ' + parseFloat(totalAmount).toLocaleString();
            document.getElementById('modal_customer').textContent = customerName;
            document.getElementById('payment_amount').value = totalAmount;
            
            document.getElementById('paymentModal').style.display = 'flex';
        }
        
        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }
        
        function confirmDelete(billId, billNumber) {
            if (confirm('Are you sure you want to delete bill ' + billNumber + '? This action cannot be undone.')) {
                window.location.href = 'pharmacy_billing.php?delete_id=' + billId;
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('paymentModal');
            if (event.target === modal) {
                closePaymentModal();
            }
        }
        
        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>