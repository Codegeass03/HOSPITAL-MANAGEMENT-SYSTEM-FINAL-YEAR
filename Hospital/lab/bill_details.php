<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$bill_id = $_GET['bill_id'] ?? null;

if (!$bill_id) {
    // header("Location: unified_billing_management.php");
    // exit();
}

// Get bill details
$bill_query = "
    SELECT 
        ub.*,
        p.first_name,
        p.last_name,
        p.patient_id as patient_number,
        p.phone,
        p.email,
        p.date_of_birth,
        p.gender,
        u.username as created_by_name
    FROM unified_billing ub
    INNER JOIN patients p ON ub.patient_id = p.id
    LEFT JOIN users u ON ub.created_by = u.id
    WHERE ub.id = ?
";

$bill_stmt = $pdo->prepare($bill_query);
$bill_stmt->execute([$bill_id]);
$bill = $bill_stmt->fetch();

if (!$bill) {
    $_SESSION['error'] = "Bill not found!";
    // header("Location: unified_billing_management.php");
    // exit();
}

// Get bill items
$items_query = "
    SELECT *
    FROM billing_items 
    WHERE billing_id = ?
    ORDER BY id
";

$items_stmt = $pdo->prepare($items_query);
$items_stmt->execute([$bill_id]);
$bill_items = $items_stmt->fetchAll();

// Get payment history
$payments_query = "
    SELECT *
    FROM payment_transactions 
    WHERE billing_id = ?
    ORDER BY transaction_date DESC
";

$payments_stmt = $pdo->prepare($payments_query);
$payments_stmt->execute([$bill_id]);
$payments = $payments_stmt->fetchAll();

// Calculate totals
$items_total = 0;
foreach ($bill_items as $item) {
    $items_total += $item['total_amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill Details - Equity Afia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 280px;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .bill-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .bill-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
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
        
        .card-body {
            padding: 1.5rem;
        }
        
        .patient-info {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .bill-summary {
            background: #f0f9ff;
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .summary-item {
            text-align: center;
        }
        
        .summary-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }
        
        .summary-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #111827;
        }
        
        .summary-value.paid { color: #059669; }
        .summary-value.balance { color: #dc2626; }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin: 1rem 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
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
        
        .service-pharmacy { background: #f3e8ff; color: #7c3aed; }
        .service-consultation { background: #e0e7ff; color: #3730a3; }
        .service-lab { background: #f0f9ff; color: #0369a1; }
        .service-admission { background: #fef7cd; color: #854d0e; }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .btn-primary { background: #3b82f6; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-outline { background: transparent; border: 1px solid #d1d5db; color: #374151; }
        
        .payment-item {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .payment-item:last-child {
            border-bottom: none;
        }
        
        .payment-amount {
            font-weight: 600;
            color: #059669;
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
        
        .print-only {
            display: none;
        }
        
        @media print {
            .main-content {
                margin-left: 0;
                padding: 0;
                background: white;
            }
            
            .page-header,
            .btn,
            .sidebar {
                display: none;
            }
            
            .print-only {
                display: block;
            }
            
            .bill-container {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .bill-card {
                box-shadow: none;
                border: 1px solid #e5e7eb;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 style="margin: 0; color: #111827;">
                    <i class="fas fa-file-invoice"></i> Bill Details
                </h1>
                <p style="margin: 0.5rem 0 0 0; color: #6b7280;">
                    Bill #<?php echo htmlspecialchars($bill['bill_number']); ?>
                </p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <button onclick="window.print()" class="btn btn-outline">
                    <i class="fas fa-print"></i> Print
                </button>
                <a href="unified_billing_management.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Bills
                </a>
                <?php if ($bill['balance_amount'] > 0): ?>
                <a href="unified_payment.php?patient_id=<?php echo $bill['patient_id']; ?>" class="btn btn-success">
                    <i class="fas fa-money-bill-wave"></i> Process Payment
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="bill-container">
            <!-- Left Column: Bill Details -->
            <div>
                <!-- Patient Information -->
                <div class="bill-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-user"></i> Patient Information
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="patient-info">
                            <h2 style="margin: 0 0 0.5rem 0; font-size: 1.5rem;">
                                <?php echo htmlspecialchars($bill['first_name'] . ' ' . $bill['last_name']); ?>
                            </h2>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                                <div>
                                    <div style="font-size: 0.875rem; opacity: 0.9;">Patient ID</div>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($bill['patient_number']); ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 0.875rem; opacity: 0.9;">Phone</div>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($bill['phone']); ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 0.875rem; opacity: 0.9;">Date of Birth</div>
                                    <div style="font-weight: 600;"><?php echo date('M j, Y', strtotime($bill['date_of_birth'])); ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 0.875rem; opacity: 0.9;">Gender</div>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($bill['gender']); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bill Summary -->
                        <div class="bill-summary">
                            <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 1rem;">
                                <h3 style="margin: 0; color: #111827;">Bill Summary</h3>
                                <span class="service-badge service-<?php echo $bill['service_type']; ?>">
                                    <?php echo ucfirst($bill['service_type']); ?> Bill
                                </span>
                            </div>
                            
                            <div class="summary-grid">
                                <div class="summary-item">
                                    <div class="summary-label">Total Amount</div>
                                    <div class="summary-value">Ksh <?php echo number_format($bill['total_amount'], 2); ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Paid Amount</div>
                                    <div class="summary-value paid">Ksh <?php echo number_format($bill['paid_amount'], 2); ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Balance</div>
                                    <div class="summary-value balance">Ksh <?php echo number_format($bill['balance_amount'], 2); ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Status</div>
                                    <div>
                                        <span class="status-badge status-<?php echo $bill['status']; ?>">
                                            <?php echo ucfirst($bill['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Progress Bar -->
                            <?php if ($bill['total_amount'] > 0): ?>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo min(100, ($bill['paid_amount'] / $bill['total_amount']) * 100); ?>%;"></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.875rem; color: #6b7280;">
                                <span><?php echo number_format(($bill['paid_amount'] / $bill['total_amount']) * 100, 1); ?>% Paid</span>
                                <span>Ksh <?php echo number_format($bill['balance_amount'], 2); ?> remaining</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Bill Details -->
                        <div style="margin-top: 2rem;">
                            <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 1rem;">
                                <h3 style="margin: 0; color: #111827;">Bill Details</h3>
                                <div style="font-size: 0.875rem; color: #6b7280;">
                                    Bill Date: <?php echo date('F j, Y g:i A', strtotime($bill['bill_date'])); ?>
                                </div>
                            </div>
                            
                            <table>
                                <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Total Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($bill_items) > 0): ?>
                                        <?php foreach ($bill_items as $item): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($item['item_description']); ?></div>
                                                <?php if ($item['item_type']): ?>
                                                <div style="font-size: 0.75rem; color: #6b7280;">Type: <?php echo ucfirst($item['item_type']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td>Ksh <?php echo number_format($item['unit_price'], 2); ?></td>
                                            <td><strong>Ksh <?php echo number_format($item['total_amount'], 2); ?></strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" style="text-align: center; color: #6b7280; padding: 2rem;">
                                                No detailed items found for this bill.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background: #f9fafb;">
                                        <td colspan="3" style="text-align: right; font-weight: 600;">Total Amount:</td>
                                        <td style="font-weight: 700; font-size: 1.125rem;">
                                            Ksh <?php echo number_format($bill['total_amount'], 2); ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Service Description -->
                <?php if (!empty($bill['service_description'])): ?>
                <div class="bill-card" style="margin-top: 1.5rem;">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-info-circle"></i> Service Description
                        </h2>
                    </div>
                    <div class="card-body">
                        <p style="margin: 0; line-height: 1.6;"><?php echo htmlspecialchars($bill['service_description']); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Right Column: Payment History & Actions -->
            <div>
                <!-- Payment History -->
                <div class="bill-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-history"></i> Payment History
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($payments) > 0): ?>
                            <div style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($payments as $payment): ?>
                                <div class="payment-item">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: #111827;">
                                            <?php echo date('M j, Y g:i A', strtotime($payment['transaction_date'])); ?>
                                        </div>
                                        <div style="font-size: 0.875rem; color: #6b7280;">
                                            <?php echo ucfirst($payment['payment_method']); ?>
                                            <?php if ($payment['payment_reference']): ?>
                                             • Ref: <?php echo htmlspecialchars($payment['payment_reference']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="payment-amount">
                                        Ksh <?php echo number_format($payment['payment_amount'], 2); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-receipt"></i>
                                <h3>No Payments Yet</h3>
                                <p>No payment transactions recorded for this bill.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="bill-card" style="margin-top: 1.5rem;">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-bolt"></i> Quick Actions
                        </h2>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <a href="unified_payment.php?patient_id=<?php echo $bill['patient_id']; ?>" 
                               class="btn btn-success" style="justify-content: center;">
                                <i class="fas fa-money-bill-wave"></i> Make Payment
                            </a>
                            
                            <button onclick="window.print()" class="btn btn-outline" style="justify-content: center;">
                                <i class="fas fa-print"></i> Print Bill
                            </button>
                            
                            <a href="patient_billing_history.php?patient_id=<?php echo $bill['patient_id']; ?>" 
                               class="btn btn-outline" style="justify-content: center;">
                                <i class="fas fa-file-medical"></i> View Patient History
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Bill Information -->
                <div class="bill-card" style="margin-top: 1.5rem;">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-cog"></i> Bill Information
                        </h2>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <div style="display: flex; justify-content: between;">
                                <span style="color: #6b7280;">Bill Number:</span>
                                <span style="font-weight: 600;"><?php echo htmlspecialchars($bill['bill_number']); ?></span>
                            </div>
                            <div style="display: flex; justify-content: between;">
                                <span style="color: #6b7280;">Service Type:</span>
                                <span class="service-badge service-<?php echo $bill['service_type']; ?>">
                                    <?php echo ucfirst($bill['service_type']); ?>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: between;">
                                <span style="color: #6b7280;">Bill Date:</span>
                                <span style="font-weight: 600;"><?php echo date('M j, Y g:i A', strtotime($bill['bill_date'])); ?></span>
                            </div>
                            <div style="display: flex; justify-content: between;">
                                <span style="color: #6b7280;">Created By:</span>
                                <span style="font-weight: 600;"><?php echo htmlspecialchars($bill['created_by_name'] ?? 'System'); ?></span>
                            </div>
                            <?php if ($bill['payment_method']): ?>
                            <div style="display: flex; justify-content: between;">
                                <span style="color: #6b7280;">Payment Method:</span>
                                <span style="font-weight: 600;"><?php echo ucfirst($bill['payment_method']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Print Header (Hidden on screen) -->
        <div class="print-only">
            <div style="text-align: center; margin-bottom: 2rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 1rem;">
                <h1 style="margin: 0; color: #111827;">Equity Afia Medical Centre</h1>
                <p style="margin: 0.5rem 0 0 0; color: #6b7280;">Bill Invoice</p>
            </div>
        </div>
    </div>

    <script>
        // Auto-print if print parameter is set
        <?php if (isset($_GET['print'])): ?>
        window.onload = function() {
            window.print();
        }
        <?php endif; ?>
        
        // Add print styles
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                @page {
                    margin: 0.5in;
                    size: letter;
                }
                body {
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .main-content {
                    margin: 0 !important;
                    padding: 0 !important;
                }
                .page-header, .btn, .sidebar {
                    display: none !important;
                }
                .bill-card {
                    break-inside: avoid;
                    box-shadow: none !important;
                    border: 1px solid #ddd !important;
                    margin-bottom: 1rem;
                }
                .patient-info {
                    background: #f8fafc !important;
                    color: #000 !important;
                    border: 1px solid #ddd;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>