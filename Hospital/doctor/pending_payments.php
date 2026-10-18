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
        
        // Get current bill details
        $stmt = $pdo->prepare("SELECT paid_amount, balance_amount FROM billing WHERE id = ?");
        $stmt->execute([$bill_id]);
        $bill = $stmt->fetch();
        
        if ($bill) {
            $new_paid = $bill['paid_amount'] + $payment_amount;
            $new_balance = $bill['balance_amount'] - $payment_amount;
            $status = $new_balance > 0 ? 'pending' : 'paid';
            
            // Update bill
            $stmt = $pdo->prepare("
                UPDATE billing 
                SET paid_amount = ?, balance_amount = ?, status = ?, payment_method = ?
                WHERE id = ?
            ");
            $stmt->execute([$new_paid, $new_balance, $status, $payment_method, $bill_id]);
            
            $_SESSION['success'] = "Payment processed successfully!";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error processing payment: " . $e->getMessage();
    }
    
    // header("Location: pending_payments.php");
    // exit();
}

// Get pending bills
$stmt = $pdo->prepare("
    SELECT 
        b.*,
        p.first_name,
        p.last_name,
        p.patient_id as patient_number
    FROM billing b
    INNER JOIN patients p ON b.patient_id = p.id
    WHERE b.balance_amount > 0
    ORDER BY b.bill_date DESC
");
$stmt->execute();
$pending_bills = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Payments - Equity Afia</title>
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
        
        .stats-cards {
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
            border-left: 4px solid #f59e0b;
        }
        
        .stat-card h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .stat-card p {
            margin: 0.5rem 0 0 0;
            color: #6b7280;
            font-size: 0.875rem;
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
        
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-partial { background: #dbeafe; color: #1d4ed8; }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.375rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
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
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }
        
        .payment-summary {
            background: #f0f9ff;
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
            <h1><i class="fas fa-credit-card"></i> Pending Payments</h1>
            <p>Manage and process outstanding payments</p>
        </div>
        
        <?php
        $total_outstanding = 0;
        $total_bills = count($pending_bills);
        foreach ($pending_bills as $bill) {
            $total_outstanding += $bill['balance_amount'];
        }
        ?>
        
        <div class="stats-cards">
            <div class="stat-card">
                <h3><?php echo $total_bills; ?></h3>
                <p>Pending Bills</p>
            </div>
            <div class="stat-card">
                <h3>Ksh <?php echo number_format($total_outstanding, 2); ?></h3>
                <p>Total Outstanding</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($pending_bills, fn($bill) => $bill['paid_amount'] > 0)); ?></h3>
                <p>Partial Payments</p>
            </div>
        </div>
        
        <div class="bills-table">
            <div class="table-header">
                <h2><i class="fas fa-list"></i> Outstanding Bills</h2>
            </div>
            
            <?php if (count($pending_bills) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Bill No.</th>
                        <th>Patient</th>
                        <th>Bill Date</th>
                        <th>Total Amount</th>
                        <th>Paid Amount</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_bills as $bill): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($bill['bill_number']); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($bill['first_name'] . ' ' . $bill['last_name']); ?></strong>
                            <br><small><?php echo htmlspecialchars($bill['patient_number']); ?></small>
                        </td>
                        <td><?php echo date('M j, Y g:i A', strtotime($bill['bill_date'])); ?></td>
                        <td>Ksh <?php echo number_format($bill['total_amount'], 2); ?></td>
                        <td>Ksh <?php echo number_format($bill['paid_amount'], 2); ?></td>
                        <td><strong>Ksh <?php echo number_format($bill['balance_amount'], 2); ?></strong></td>
                        <td>
                            <span class="status-badge <?php echo $bill['paid_amount'] > 0 ? 'status-partial' : 'status-pending'; ?>">
                                <?php echo $bill['paid_amount'] > 0 ? 'Partial' : 'Pending'; ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick="openPaymentModal(<?php echo $bill['id']; ?>, <?php echo $bill['balance_amount']; ?>, '<?php echo $bill['bill_number']; ?>')">
                                <i class="fas fa-money-bill"></i> Pay
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>No Pending Payments</h3>
                <p>All bills have been fully paid.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Process Payment</h3>
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
                        <span>Outstanding Balance:</span>
                        <strong id="modal_balance">Ksh 0.00</strong>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="payment_amount">Payment Amount</label>
                    <input type="number" class="form-control" id="payment_amount" name="payment_amount" 
                           step="0.01" min="0.01" required oninput="validatePayment()">
                    <small style="color: #6b7280; font-size: 0.75rem;">Enter the amount being paid</small>
                </div>
                
                <div class="form-group">
                    <label for="payment_method">Payment Method</label>
                    <select class="form-control" id="payment_method" name="payment_method" required>
                        <option value="cash">Cash</option>
                        <option value="mpesa">M-Pesa</option>
                        <option value="card">Credit/Debit Card</option>
                        <option value="bank">Bank Transfer</option>
                    </select>
                </div>
                
                <div class="payment-summary" id="payment_summary" style="display: none;">
                    <div class="summary-row">
                        <span>New Balance:</span>
                        <strong id="new_balance">Ksh 0.00</strong>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" name="process_payment" style="flex: 1;">
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
            validatePayment();
        }
        
        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }
        
        function validatePayment() {
            const paymentAmount = parseFloat(document.getElementById('payment_amount').value) || 0;
            const currentBalance = parseFloat(document.getElementById('modal_balance').textContent.replace('Ksh ', '').replace(/,/g, ''));
            
            if (paymentAmount > 0 && paymentAmount <= currentBalance) {
                const newBalance = currentBalance - paymentAmount;
                document.getElementById('new_balance').textContent = 'Ksh ' + newBalance.toLocaleString();
                document.getElementById('payment_summary').style.display = 'block';
            } else {
                document.getElementById('payment_summary').style.display = 'none';
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