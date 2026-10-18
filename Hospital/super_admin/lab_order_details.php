<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

// if (!isset($_SESSION['user_id'])) {
//     header("Location: login.php");
//     exit();
// }

$user_role = $_SESSION['role'] ?? '';
$order_id = $_GET['order_id'] ?? null;

// if (!$order_id) {
//     header("Location: lab_module.php");
//     exit();
// }

// Get order details
$stmt = $pdo->prepare("
    SELECT 
        lo.*,
        p.patient_id,
        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
        p.age,
        p.gender,
        p.phone,
        CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
        CONCAT(uc.first_name, ' ', uc.last_name) as collected_by_name,
        CONCAT(ut.first_name, ' ', ut.last_name) as tested_by_name
    FROM lab_orders lo
    JOIN patients p ON lo.patient_id = p.id
    JOIN users u ON lo.ordered_by = u.id
    LEFT JOIN users uc ON lo.collected_by = uc.id
    LEFT JOIN users ut ON lo.tested_by = ut.id
    WHERE lo.id = ?
");

$stmt->execute([$order_id]);
$order = $stmt->fetch();

// if (!$order) {
//     $_SESSION['error'] = "Lab order not found.";
//     header("Location: lab_module.php");
//     exit();
// }

// Get test items with results
$items_stmt = $pdo->prepare("
    SELECT * FROM lab_order_items 
    WHERE lab_order_id = ? 
    ORDER BY id
");
$items_stmt->execute([$order_id]);
$test_items = $items_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Order Details - AuraCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            max-width: 1200px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .detail-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }
        
        .detail-card h3 {
            margin-top: 0;
            margin-bottom: 1rem;
            color: #1e293b;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .result-item {
            background: #f8fafc;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        
        .result-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
        }
        
        .result-normal { color: #10b981; }
        .result-abnormal { color: #ef4444; }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-ordered { background: #fef3c7; color: #d97706; }
        .status-in_progress { background: #dbeafe; color: #1d4ed8; }
        .status-completed { background: #d1fae5; color: #065f46; }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #f8fafc;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div style="display: flex; justify-content: space-between; margin-bottom: 2rem;">
            <h1><i class="fas fa-file-medical"></i> Lab Order Details</h1>
            <a href="lab_module.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
        
        <div class="details-grid">
            <div class="detail-card">
                <h3>Patient Information</h3>
                <div class="detail-item">
                    <span>Name:</span>
                    <strong><?php echo htmlspecialchars($order['patient_name']); ?></strong>
                </div>
                <div class="detail-item">
                    <span>Patient ID:</span>
                    <span><?php echo htmlspecialchars($order['patient_id']); ?></span>
                </div>
                <div class="detail-item">
                    <span>Age/Gender:</span>
                    <span><?php echo $order['age']; ?> years, <?php echo $order['gender']; ?></span>
                </div>
                <div class="detail-item">
                    <span>Phone:</span>
                    <span><?php echo htmlspecialchars($order['phone']); ?></span>
                </div>
            </div>
            
            <div class="detail-card">
                <h3>Order Information</h3>
                <div class="detail-item">
                    <span>Order Date:</span>
                    <span><?php echo date('M j, Y g:i A', strtotime($order['order_date'])); ?></span>
                </div>
                <div class="detail-item">
                    <span>Ordering Doctor:</span>
                    <span>Dr. <?php echo htmlspecialchars($order['doctor_name']); ?></span>
                </div>
                <div class="detail-item">
                    <span>Status:</span>
                    <span class="status-badge status-<?php echo $order['status']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span>Priority:</span>
                    <span><?php echo ucfirst($order['priority']); ?></span>
                </div>
            </div>
        </div>
        
        <?php if (!empty($order['clinical_notes'])): ?>
        <div class="detail-card" style="margin-bottom: 2rem;">
            <h3>Clinical Notes</h3>
            <p style="color: #64748b; margin: 0;"><?php echo htmlspecialchars($order['clinical_notes']); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="detail-card">
            <h3>Test Results</h3>
            
            <?php if (empty($test_items)): ?>
                <p style="text-align: center; color: #64748b; padding: 2rem;">No tests ordered.</p>
            <?php else: ?>
                <?php foreach ($test_items as $item): ?>
                <div class="result-item">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                        <h4 style="margin: 0; color: #1e293b;"><?php echo htmlspecialchars($item['test_name']); ?></h4>
                        <span style="color: #64748b; font-size: 0.875rem;">
                            Specimen: <?php echo htmlspecialchars($item['specimen_type']); ?>
                        </span>
                    </div>
                    
                    <?php if ($item['result_value'] && $order['status'] === 'completed'): ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                            <div>
                                <div class="result-value">
                                    <?php echo htmlspecialchars($item['result_value']); ?>
                                    <span style="font-size: 1rem; color: #64748b;">
                                        <?php echo htmlspecialchars($item['result_unit']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($item['reference_range']): ?>
                            <div style="color: #64748b;">
                                <strong>Reference:</strong> <?php echo htmlspecialchars($item['reference_range']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($item['tested_at']): ?>
                            <div style="color: #64748b; text-align: right;">
                                <strong>Tested:</strong> <?php echo date('M j, Y', strtotime($item['tested_at'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($item['result_notes']): ?>
                        <div style="margin-top: 1rem; padding: 1rem; background: #f0f9ff; border-radius: 0.5rem;">
                            <strong style="color: #0369a1;">Interpretation:</strong>
                            <p style="color: #64748b; margin: 0.5rem 0 0 0;">
                                <?php echo htmlspecialchars($item['result_notes']); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem; color: #64748b;">
                            <i class="fas fa-hourglass-half" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                            <p>Results pending</p>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <?php if ($order['collected_by_name'] || $order['tested_by_name']): ?>
        <div class="detail-card">
            <h3>Workflow History</h3>
            <?php if ($order['collected_by_name']): ?>
            <div class="detail-item">
                <span>Sample Collected:</span>
                <span>
                    <?php echo htmlspecialchars($order['collected_by_name']); ?> 
                    on <?php echo date('M j, Y g:i A', strtotime($order['collected_at'])); ?>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if ($order['tested_by_name'] && $order['status'] === 'completed'): ?>
            <div class="detail-item">
                <span>Testing Completed:</span>
                <span>
                    <?php echo htmlspecialchars($order['tested_by_name']); ?> 
                    on <?php echo date('M j, Y g:i A', strtotime($order['completed_at'])); ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div style="display: flex; gap: 1rem; margin-top: 2rem; justify-content: flex-end;">
            <a href="lab_module.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Lab
            </a>
            
            <?php if ($order['status'] === 'completed'): ?>
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print Report
                </button>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>