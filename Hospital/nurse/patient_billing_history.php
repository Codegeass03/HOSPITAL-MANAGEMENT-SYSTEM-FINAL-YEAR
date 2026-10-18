<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$patient_id = $_GET['patient_id'] ?? null;

if (!$patient_id) {
    header("Location: unified_billing_management.php");
    exit();
}

// Get patient details
$patient_query = "
    SELECT p.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name
    FROM patients p 
    WHERE p.id = ?
";

$patient_stmt = $pdo->prepare($patient_query);
$patient_stmt->execute([$patient_id]);
$patient = $patient_stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = "Patient not found!";
    header("Location: unified_billing_management.php");
    exit();
}

// Get patient's billing history
$bills_query = "
    SELECT 
        ub.*,
        COUNT(bi.id) as item_count
    FROM unified_billing ub
    LEFT JOIN billing_items bi ON ub.id = bi.billing_id
    WHERE ub.patient_id = ?
    GROUP BY ub.id
    ORDER BY ub.bill_date DESC
";

$bills_stmt = $pdo->prepare($bills_query);
$bills_stmt->execute([$patient_id]);
$bills = $bills_stmt->fetchAll();

// Calculate totals
$total_bills = count($bills);
$total_amount = 0;
$total_paid = 0;
$total_balance = 0;

foreach ($bills as $bill) {
    $total_amount += $bill['total_amount'];
    $total_paid += $bill['paid_amount'];
    $total_balance += $bill['balance_amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Billing History - Equity Afia</title>
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
        
        .patient-header {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
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
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .status-badge, .service-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-partial { background: #dbeafe; color: #1e40af; }
        .status-paid { background: #dcfce7; color: #065f46; }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            text-decoration: none;
            font-size: 0.75rem;
        }
        
        .btn-outline { background: transparent; border: 1px solid #d1d5db; }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 style="margin: 0; color: #111827;">
                    <i class="fas fa-history"></i> Patient Billing History
                </h1>
                <p style="margin: 0.5rem 0 0 0; color: #6b7280;">
                    Complete billing history for <?php echo htmlspecialchars($patient['patient_name']); ?>
                </p>
            </div>
            <div>
                <a href="unified_billing_management.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Bills
                </a>
                <a href="unified_payment.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary">
                    <i class="fas fa-money-bill-wave"></i> Process Payment
                </a>
            </div>
        </div>
        
        <!-- Patient Header -->
        <div class="patient-header">
            <h2 style="margin: 0 0 0.5rem 0; font-size: 1.5rem;">
                <?php echo htmlspecialchars($patient['patient_name']); ?>
            </h2>
            <p style="margin: 0; opacity: 0.9;">
                Patient ID: <?php echo htmlspecialchars($patient['patient_id']); ?> • 
                Phone: <?php echo htmlspecialchars($patient['phone']); ?>
            </p>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $total_bills; ?></h3>
                <p>Total Bills</p>
            </div>
            <div class="stat-card">
                <h3>Ksh <?php echo number_format($total_amount, 2); ?></h3>
                <p>Total Billed</p>
            </div>
            <div class="stat-card">
                <h3>Ksh <?php echo number_format($total_paid, 2); ?></h3>
                <p>Total Paid</p>
            </div>
            <div class="stat-card">
                <h3>Ksh <?php echo number_format($total_balance, 2); ?></h3>
                <p>Outstanding Balance</p>
            </div>
        </div>
        
        <!-- Bills Table -->
        <div class="bills-table">
            <div class="table-header">
                <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600; color: #1f2937;">
                    <i class="fas fa-list"></i> Billing History
                </h2>
            </div>
            
            <?php if (count($bills) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Bill No.</th>
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
                            <a href="bill_details.php?bill_id=<?php echo $bill['id']; ?>" class="btn btn-outline">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="text-align: center; padding: 3rem 2rem; color: #6b7280;">
                <i class="fas fa-receipt" style="font-size: 3rem; margin-bottom: 1rem; color: #d1d5db;"></i>
                <h3>No Bills Found</h3>
                <p>No billing records found for this patient.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>