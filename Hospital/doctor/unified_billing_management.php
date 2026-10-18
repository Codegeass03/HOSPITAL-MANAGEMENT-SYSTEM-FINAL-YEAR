<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    try {
        $bill_id = $_POST['bill_id'];
        $payment_amount = $_POST['payment_amount'];
        $payment_method = $_POST['payment_method'];
        $payment_reference = $_POST['payment_reference'] ?? '';
        
        $pdo->beginTransaction();
        
        // Get current bill details
        $stmt = $pdo->prepare("SELECT * FROM unified_billing WHERE id = ?");
        $stmt->execute([$bill_id]);
        $bill = $stmt->fetch();
        
        if ($bill) {
            $new_paid = $bill['paid_amount'] + $payment_amount;
            $new_balance = $bill['balance_amount'] - $payment_amount;
            $status = $new_balance > 0 ? 'partial' : 'paid';
            
            // Update bill
            $stmt = $pdo->prepare("
                UPDATE unified_billing 
                SET paid_amount = ?, balance_amount = ?, status = ?, 
                    payment_method = COALESCE(payment_method, ?)
                WHERE id = ?
            ");
            $stmt->execute([$new_paid, $new_balance, $status, $payment_method, $bill_id]);
            
            // Record payment transaction
            $stmt = $pdo->prepare("
                INSERT INTO payment_transactions 
                (billing_id, payment_amount, payment_method, payment_reference, received_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$bill_id, $payment_amount, $payment_method, $payment_reference, $_SESSION['user_id']]);
            
            $pdo->commit();
            $_SESSION['success'] = "Payment processed successfully!";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error processing payment: " . $e->getMessage();
    }
    
    // header("Location: unified_billing_management.php");
    // exit();
}

// Get filter parameters
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_date = $_GET['date'] ?? '';
$filter_patient = $_GET['patient'] ?? '';

// Build query for all bills
$query = "
    SELECT 
        ub.*,
        p.first_name,
        p.last_name,
        p.patient_id as patient_number,
        COUNT(bi.id) as item_count,
        SUM(bi.total_amount) as items_total
    FROM unified_billing ub
    INNER JOIN patients p ON ub.patient_id = p.id
    LEFT JOIN billing_items bi ON ub.id = bi.billing_id
    WHERE 1=1
";

$params = [];

if (!empty($filter_type)) {
    $query .= " AND ub.service_type = ?";
    $params[] = $filter_type;
}

if (!empty($filter_status)) {
    $query .= " AND ub.status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_date)) {
    $query .= " AND DATE(ub.bill_date) = ?";
    $params[] = $filter_date;
}

if (!empty($filter_patient)) {
    $query .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_id LIKE ?)";
    $search_term = "%$filter_patient%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " GROUP BY ub.id ORDER BY ub.bill_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bills = $stmt->fetchAll();

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_bills,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_bills,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bills,
        SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial_bills,
        SUM(total_amount) as total_amount,
        SUM(paid_amount) as total_paid,
        SUM(balance_amount) as total_balance
    FROM unified_billing
    WHERE DATE(bill_date) = CURDATE()
";

$stats_stmt = $pdo->query($stats_query);
$stats = $stats_stmt->fetch();

// Get service-wise totals
$service_stats_query = "
    SELECT 
        service_type,
        COUNT(*) as count,
        SUM(total_amount) as total,
        SUM(paid_amount) as paid,
        SUM(balance_amount) as balance
    FROM unified_billing
    WHERE DATE(bill_date) = CURDATE()
    GROUP BY service_type
";

$service_stats_stmt = $pdo->query($service_stats_query);
$service_stats = $service_stats_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unified Billing Management - Equity Afia</title>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card.total { border-left: 4px solid #3b82f6; }
        .stat-card.paid { border-left: 4px solid #10b981; }
        .stat-card.pending { border-left: 4px solid #f59e0b; }
        .stat-card.balance { border-left: 4px solid #ef4444; }
        
        .service-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .service-stat-card {
            background: white;
            padding: 1rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 4px solid #8b5cf6;
        }
        
        .filters-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
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
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary { background: #3b82f6; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-outline { background: transparent; border: 1px solid #d1d5db; }
        
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
            justify-content: space-between;
            align-items: center;
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
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-partial { background: #dbeafe; color: #1e40af; }
        .status-paid { background: #dcfce7; color: #065f46; }
        
        .service-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .service-consultation { background: #e0e7ff; color: #3730a3; }
        .service-pharmacy { background: #f3e8ff; color: #7c3aed; }
        .service-lab { background: #f0f9ff; color: #0369a1; }
        .service-admission { background: #fef7cd; color: #854d0e; }
        
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
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
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
            <h1><i class="fas fa-file-invoice-dollar"></i> Unified Billing Management</h1>
            <p>Manage all patient billing records in one place</p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card total">
                <h3><?php echo $stats['total_bills'] ?? 0; ?></h3>
                <p>Today's Bills</p>
            </div>
            <div class="stat-card paid">
                <h3>Ksh <?php echo number_format($stats['total_paid'] ?? 0, 2); ?></h3>
                <p>Total Paid</p>
            </div>
            <div class="stat-card pending">
                <h3><?php echo $stats['pending_bills'] ?? 0; ?></h3>
                <p>Pending Bills</p>
            </div>
            <div class="stat-card balance">
                <h3>Ksh <?php echo number_format($stats['total_balance'] ?? 0, 2); ?></h3>
                <p>Outstanding Balance</p>
            </div>
        </div>
        
        <!-- Service-wise Statistics -->
        <div class="service-stats">
            <?php foreach ($service_stats as $service): ?>
            <div class="service-stat-card">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 600; text-transform: capitalize;">
                        <?php echo $service['service_type']; ?>
                    </span>
                    <span class="status-badge"><?php echo $service['count']; ?> bills</span>
                </div>
                <div style="margin-top: 0.5rem;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                        <span>Total:</span>
                        <span>Ksh <?php echo number_format($service['total'], 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                        <span>Balance:</span>
                        <span style="color: #ef4444;">Ksh <?php echo number_format($service['balance'], 2); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="form-group">
                    <label>Service Type</label>
                    <select name="type" class="form-control">
                        <option value="">All Services</option>
                        <option value="consultation" <?php echo $filter_type == 'consultation' ? 'selected' : ''; ?>>Consultation</option>
                        <option value="pharmacy" <?php echo $filter_type == 'pharmacy' ? 'selected' : ''; ?>>Pharmacy</option>
                        <option value="lab" <?php echo $filter_type == 'lab' ? 'selected' : ''; ?>>Laboratory</option>
                        <option value="admission" <?php echo $filter_type == 'admission' ? 'selected' : ''; ?>>Admission</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="partial" <?php echo $filter_status == 'partial' ? 'selected' : ''; ?>>Partial</option>
                        <option value="paid" <?php echo $filter_status == 'paid' ? 'selected' : ''; ?>>Paid</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="date" class="form-control" value="<?php echo $filter_date; ?>">
                </div>
                
                <div class="form-group">
                    <label>Patient Search</label>
                    <input type="text" name="patient" class="form-control" 
                           value="<?php echo htmlspecialchars($filter_patient); ?>" 
                           placeholder="Patient name or ID">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="unified_billing_management.php" class="btn btn-outline">
                        Clear
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Bills Table -->
        <div class="bills-table">
            <div class="table-header">
                <h2><i class="fas fa-list"></i> All Patient Bills</h2>
                <div>
                    <a href="create_bill.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create New Bill
                    </a>
                    <a href="unified_payment.php" class="btn btn-success">
                        <i class="fas fa-money-bill-wave"></i> Process Payment
                    </a>
                </div>
            </div>
            
            <?php if (count($bills) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Bill No.</th>
                        <th>Patient</th>
                        <th>Service Type</th>
                        <th>Date</th>
                        <th>Total Amount</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bills as $bill): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($bill['bill_number']); ?></strong>
                            <?php if ($bill['item_count'] > 0): ?>
                            <br><small><?php echo $bill['item_count']; ?> items</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($bill['first_name'] . ' ' . $bill['last_name']); ?></strong>
                            <br><small><?php echo htmlspecialchars($bill['patient_number']); ?></small>
                        </td>
                        <td>
                            <span class="service-badge service-<?php echo $bill['service_type']; ?>">
                                <?php echo ucfirst($bill['service_type']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($bill['bill_date'])); ?></td>
                        <td>Ksh <?php echo number_format($bill['total_amount'], 2); ?></td>
                        <td>Ksh <?php echo number_format($bill['paid_amount'], 2); ?></td>
                        <td><strong>Ksh <?php echo number_format($bill['balance_amount'], 2); ?></strong></td>
                        <td>
                            <span class="status-badge status-<?php echo $bill['status']; ?>">
                                <?php echo ucfirst($bill['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.5rem;">
                                <?php if ($bill['balance_amount'] > 0): ?>
                                <button class="btn btn-success btn-sm" 
                                        onclick="openPaymentModal(
                                            <?php echo $bill['id']; ?>,
                                            <?php echo $bill['balance_amount']; ?>,
                                            '<?php echo $bill['bill_number']; ?>'
                                        )">
                                    <i class="fas fa-money-bill"></i> Pay
                                </button>
                                <?php endif; ?>
                                
                                <a href="bill_details.php?bill_id=<?php echo $bill['id']; ?>" 
                                   class="btn btn-outline btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="text-align: center; padding: 3rem 2rem; color: #6b7280;">
                <i class="fas fa-receipt" style="font-size: 3rem; margin-bottom: 1rem; color: #d1d5db;"></i>
                <h3>No Bills Found</h3>
                <p>No billing records match your current filters.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Process Payment</h3>
                <button class="close" onclick="closePaymentModal()">&times;</button>
            </div>
            <form method="POST" id="paymentForm">
                <input type="hidden" name="bill_id" id="modal_bill_id">
                
                <div style="background: #f8fafc; padding: 1rem; border-radius: 0.5rem; margin: 1rem 0;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>Bill Number:</span>
                        <strong id="modal_bill_number"></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Outstanding Balance:</span>
                        <strong id="modal_balance">Ksh 0.00</strong>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Payment Amount</label>
                    <input type="number" class="form-control" id="payment_amount" name="payment_amount" 
                           step="0.01" min="0.01" required oninput="validatePayment()">
                </div>
                
                <div class="form-group">
                    <label>Payment Method</label>
                    <select class="form-control" id="payment_method" name="payment_method" required>
                        <option value="cash">Cash</option>
                        <option value="mpesa">M-Pesa</option>
                        <option value="card">Card</option>
                        <option value="insurance">Insurance</option>
                        <option value="bank">Bank Transfer</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Payment Reference</label>
                    <input type="text" class="form-control" name="payment_reference" 
                           placeholder="Reference number (optional)">
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-success" name="process_payment" style="flex: 1;">
                        <i class="fas fa-check"></i> Process Payment
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closePaymentModal()" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openPaymentModal(billId, balance, billNumber) {
            document.getElementById('modal_bill_id').value = billId;
            document.getElementById('modal_balance').textContent = 'Ksh ' + balance.toLocaleString();
            document.getElementById('modal_bill_number').textContent = billNumber;
            document.getElementById('payment_amount').max = balance;
            document.getElementById('payment_amount').value = balance;
            
            document.getElementById('paymentModal').style.display = 'flex';
        }
        
        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }
        
        function validatePayment() {
            const paymentAmount = parseFloat(document.getElementById('payment_amount').value) || 0;
            const currentBalance = parseFloat(document.getElementById('modal_balance').textContent.replace('Ksh ', '').replace(/,/g, ''));
            
            if (paymentAmount > currentBalance) {
                document.getElementById('payment_amount').value = currentBalance;
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('paymentModal');
            if (event.target === modal) {
                closePaymentModal();
            }
        }
    </script>
</body>
</html>