<?php
session_start();
include "includes/db_connect.php";
date_default_timezone_set('Africa/Nairobi');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Lab Technician' && $_SESSION['role'] !== 'Super Admin' && $_SESSION['role'] !== 'Doctor')) {
    header("Location: login.php");
    exit();
}

$order_id = $_GET['order_id'] ?? null;
if (!$order_id) {
    header("Location: lab_module.php");
    exit();
}

// Get complete order details for report
$stmt = $pdo->prepare("
    SELECT 
        lo.*,
        p.patient_id,
        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
        p.age,
        p.gender,
        p.phone,
        p.address,
        CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
        CONCAT(uc.first_name, ' ', uc.last_name) as collected_by_name,
        CONCAT(ut.first_name, ' ', ut.last_name) as tested_by_name,
        loi.test_name,
        loi.specimen_type,
        loi.result_value,
        loi.result_unit,
        loi.reference_range,
        loi.result_notes,
        loi.tested_at as item_tested_at
    FROM lab_orders lo
    JOIN patients p ON lo.patient_id = p.id
    JOIN users u ON lo.ordered_by = u.id
    LEFT JOIN users uc ON lo.collected_by = uc.id
    LEFT JOIN users ut ON lo.tested_by = ut.id
    LEFT JOIN lab_order_items loi ON lo.id = loi.lab_order_id
    WHERE lo.id = ? AND lo.status = 'completed'
");

$stmt->execute([$order_id]);
$report_data = $stmt->fetchAll();

if (!$report_data) {
    header("Location: lab_module.php");
    exit();
}

$report = $report_data[0];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Report - AuraCare HMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            color: #333; 
            background: #f8fafc;
        }
        .report-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        .report-header { 
            text-align: center; 
            border-bottom: 2px solid #3b82f6; 
            padding-bottom: 20px; 
            margin-bottom: 30px; 
        }
        .hospital-name { 
            font-size: 24px; 
            font-weight: bold; 
            margin-bottom: 5px; 
            color: #1e293b;
        }
        .report-title { 
            font-size: 20px; 
            margin: 10px 0; 
            color: #3b82f6;
        }
        .patient-info, .test-results { 
            margin-bottom: 30px; 
        }
        .info-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px; 
        }
        .result-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
        }
        .result-table th, .result-table td { 
            border: 1px solid #ddd; 
            padding: 12px; 
            text-align: left; 
        }
        .result-table th { 
            background-color: #f1f5f9; 
            font-weight: 600;
        }
        .signature-area { 
            margin-top: 50px; 
            display: grid; 
            grid-template-columns: 1fr 1fr 1fr; 
            gap: 20px; 
        }
        .signature-line { 
            border-top: 1px solid #333; 
            padding-top: 5px; 
            text-align: center; 
        }
        .footer { 
            margin-top: 50px; 
            text-align: center; 
            font-size: 12px; 
            color: #666; 
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            margin: 0.25rem;
        }
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .no-print { 
            margin-top: 30px; 
            text-align: center; 
        }
        @media print {
            .no-print { display: none; }
            body { 
                padding: 0; 
                background: white;
            }
            .report-container {
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="report-header">
            <div class="hospital-name">Equity Afia Medical Center</div>
            <div>CBD Street, Nairobi City</div>
            <div>Phone: (+254) 780965421 | Email: lab@auracarehospital.com</div>
            <div class="report-title">LABORATORY TEST REPORT</div>
        </div>

        <div class="patient-info">
            <div class="info-grid">
                <div>
                    <strong>Patient Name:</strong> <?php echo htmlspecialchars($report['patient_name']); ?><br>
                    <strong>Patient ID:</strong> <?php echo htmlspecialchars($report['patient_id']); ?><br>
                    <strong>Age/Gender:</strong> <?php echo $report['age']; ?> years / <?php echo $report['gender']; ?>
                </div>
                <div>
                    <strong>Order Number:</strong> <?php echo htmlspecialchars($report['order_number'] ?? 'N/A'); ?><br>
                    <strong>Order Date:</strong> <?php echo date('M j, Y g:i A', strtotime($report['order_date'])); ?><br>
                    <strong>Report Date:</strong> <?php echo date('M j, Y g:i A'); ?>
                </div>
            </div>
        </div>

        <div class="test-results">
            <h3>Test Results</h3>
            <table class="result-table">
                <thead>
                    <tr>
                        <th>Test Name</th>
                        <th>Result</th>
                        <th>Unit</th>
                        <th>Reference Range</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data as $item): ?>
                        <?php if ($item['test_name'] && $item['result_value']): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['test_name']); ?></td>
                                <td><strong><?php echo htmlspecialchars($item['result_value']); ?></strong></td>
                                <td><?php echo htmlspecialchars($item['result_unit'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($item['reference_range'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($item['result_notes'] ?? ''); ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="signature-area">
            <div class="signature-line">
                <strong>Collected By:</strong><br>
                <?php echo htmlspecialchars($report['collected_by_name'] ?? 'N/A'); ?><br>
                <?php echo $report['collected_at'] ? date('M j, Y g:i A', strtotime($report['collected_at'])) : 'N/A'; ?>
            </div>
            <div class="signature-line">
                <strong>Tested By:</strong><br>
                <?php echo htmlspecialchars($report['tested_by_name'] ?? 'N/A'); ?><br>
                <?php echo $report['tested_at'] ? date('M j, Y g:i A', strtotime($report['tested_at'])) : 'N/A'; ?>
            </div>
            <div class="signature-line">
                <strong>Verified By:</strong><br>
                Lab Supervisor<br>
                <?php echo date('M j, Y'); ?>
            </div>
        </div>

        <div class="footer">
            <p>This is an electronically generated report. No signature required.</p>
            <p>Report generated on: <?php echo date('M j, Y g:i A'); ?></p>
        </div>

        <div class="no-print" style="margin-top: 30px; text-align: center;">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print Report
            </button>
            <a href="lab_module.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Lab
            </a>
        </div>
    </div>
</body>
</html>