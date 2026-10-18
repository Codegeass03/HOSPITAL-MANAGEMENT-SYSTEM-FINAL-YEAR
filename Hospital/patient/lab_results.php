<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

// Check if user is logged in as a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Patient') {
    header("Location: login.php");
    exit;
}

// Get patient ID from session
$patient_id = $_SESSION['user_id'];

// Get patient info
$patient_stmt = $pdo->prepare("
    SELECT p.* 
    FROM patients p 
    WHERE p.id = ? OR EXISTS (
        SELECT 1 FROM users u 
        WHERE u.id = ? AND (u.phone = p.phone OR u.email = p.email)
    )
    LIMIT 1
");
$patient_stmt->execute([$patient_id, $patient_id]);
$patient = $patient_stmt->fetch();

if (!$patient) {
    $patient_stmt2 = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $patient_stmt2->execute([$patient_id]);
    $patient = $patient_stmt2->fetch();
    
    if (!$patient) {
        $_SESSION['error'] = "Patient record not found.";
        header("Location: patient_dashboard.php");
        exit;
    }
}

// Handle view detailed results
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $order_id = $_GET['view'];
    
    // Verify the order belongs to this patient
    $verify_stmt = $pdo->prepare("
        SELECT lo.* 
        FROM lab_orders lo 
        WHERE lo.id = ? AND lo.patient_id = ?
    ");
    $verify_stmt->execute([$order_id, $patient['id']]);
    $order = $verify_stmt->fetch();
    
    if (!$order) {
        $_SESSION['error'] = "Lab order not found or access denied.";
        header("Location: lab_results.php");
        exit;
    }
    
    // Get detailed order info with test items
    $detailed_stmt = $pdo->prepare("
        SELECT lo.*,
               CONCAT(p.first_name, ' ', p.last_name) as patient_name,
               p.patient_id as patient_number,
               p.age,
               p.gender,
               p.phone,
               p.address,
               CONCAT(u_ordered.first_name, ' ', u_ordered.last_name) as ordered_by_name,
               CONCAT(u_collected.first_name, ' ', u_collected.last_name) as collected_by_name,
               CONCAT(u_tested.first_name, ' ', u_tested.last_name) as tested_by_name,
               CONCAT(u_verified.first_name, ' ', u_verified.last_name) as verified_by_name
        FROM lab_orders lo
        JOIN patients p ON lo.patient_id = p.id
        LEFT JOIN users u_ordered ON lo.ordered_by = u_ordered.id
        LEFT JOIN users u_collected ON lo.collected_by = u_collected.id
        LEFT JOIN users u_tested ON lo.tested_by = u_tested.id
        LEFT JOIN users u_verified ON lo.verified_by = u_verified.id
        WHERE lo.id = ?
    ");
    $detailed_stmt->execute([$order_id]);
    $detailed_order = $detailed_stmt->fetch();
    
    // Get test items for this order
    $items_stmt = $pdo->prepare("
        SELECT loi.*,
               s.name,
               s.price
        FROM lab_order_items loi
        LEFT JOIN services s ON loi.service_id = s.id
        WHERE loi.lab_order_id = ?
        ORDER BY loi.id
    ");
    $items_stmt->execute([$order_id]);
    $test_items = $items_stmt->fetchAll();
    
    // Check if we're in print mode
    $print_mode = isset($_GET['print']) && $_GET['print'] == 'true';
    
    // If print mode, show the report layout
    if ($print_mode && $detailed_order['status'] === 'completed') {
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
                .abnormal-result {
                    color: #dc2626;
                    font-weight: 600;
                }
                .normal-result {
                    color: #059669;
                    font-weight: 600;
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
                            <strong>Patient Name:</strong> <?php echo htmlspecialchars($detailed_order['patient_name']); ?><br>
                            <strong>Patient ID:</strong> <?php echo htmlspecialchars($detailed_order['patient_number']); ?><br>
                            <strong>Age/Gender:</strong> <?php echo htmlspecialchars($detailed_order['age']); ?> years / <?php echo htmlspecialchars($detailed_order['gender']); ?>
                        </div>
                        <div>
                            <strong>Order Number:</strong> <?php echo htmlspecialchars($detailed_order['order_number']); ?><br>
                            <strong>Order Date:</strong> <?php echo date('M j, Y g:i A', strtotime($detailed_order['order_date'])); ?><br>
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
                                <th>Specimen</th>
                                <th>Result</th>
                                <th>Unit</th>
                                <th>Reference Range</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($test_items as $test): ?>
                                <?php if ($test['test_name']): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($test['test_name']); ?></td>
                                        <td><?php echo htmlspecialchars($test['specimen_type'] ?? ''); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($test['result_value'] ?? 'Pending'); ?></strong>
                                            <?php if ($test['result_value'] && $test['reference_range']): 
                                                $result = floatval(preg_replace('/[^0-9.]/', '', $test['result_value']));
                                                $range = $test['reference_range'];
                                                preg_match('/(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)/', $range, $matches);
                                                
                                                if (count($matches) === 3) {
                                                    $min = floatval($matches[1]);
                                                    $max = floatval($matches[2]);
                                                    $is_normal = ($result >= $min && $result <= $max);
                                                    echo '<br><span class="' . ($is_normal ? 'normal-result' : 'abnormal-result') . '">' . ($is_normal ? 'Normal' : 'Abnormal') . '</span>';
                                                }
                                            endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($test['result_unit'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($test['reference_range'] ?? ''); ?></td>
                                        <td><?php echo ucfirst($test['status']); ?></td>
                                        <td><?php echo htmlspecialchars($test['result_notes'] ?? ''); ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="signature-area">
                    <div class="signature-line">
                        <strong>Collected By:</strong><br>
                        <?php echo htmlspecialchars($detailed_order['collected_by_name'] ?? 'N/A'); ?><br>
                        <?php echo $detailed_order['collected_at'] ? date('M j, Y g:i A', strtotime($detailed_order['collected_at'])) : 'N/A'; ?>
                    </div>
                    <div class="signature-line">
                        <strong>Tested By:</strong><br>
                        <?php echo htmlspecialchars($detailed_order['tested_by_name'] ?? 'N/A'); ?><br>
                        <?php echo $detailed_order['tested_at'] ? date('M j, Y g:i A', strtotime($detailed_order['tested_at'])) : 'N/A'; ?>
                    </div>
                    <div class="signature-line">
                        <strong>Verified By:</strong><br>
                        <?php echo htmlspecialchars($detailed_order['verified_by_name'] ?? 'Lab Supervisor'); ?><br>
                        <?php echo $detailed_order['verified_at'] ? date('M j, Y g:i A', strtotime($detailed_order['verified_at'])) : date('M j, Y'); ?>
                    </div>
                </div>

                <?php if (!empty($detailed_order['clinical_notes'])): ?>
                    <div style="margin-top: 30px; padding: 15px; background: #f8fafc; border-radius: 5px;">
                        <strong>Clinical Notes:</strong><br>
                        <?php echo nl2br(htmlspecialchars($detailed_order['clinical_notes'])); ?>
                    </div>
                <?php endif; ?>

                <div class="footer">
                    <!-- <p>This is an electronically generated report. No signature required.</p> -->
                    <p>Report generated on: <?php echo date('M j, Y g:i A'); ?></p>
                </div>

                <div class="no-print" style="margin-top: 30px; text-align: center;">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                    <a href="lab_results.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Results
                    </a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Get filter parameters
$filter_date = $_GET['filter_date'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_test = $_GET['filter_test'] ?? '';

// Build query for lab orders
$query = "
    SELECT lo.*,
           CONCAT(u_ordered.first_name, ' ', u_ordered.last_name) as ordered_by_name,
           CONCAT(u_tested.first_name, ' ', u_tested.last_name) as tested_by_name,
           COUNT(loi.id) as test_count,
           SUM(CASE WHEN loi.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
           MAX(loi.tested_at) as latest_test_date
    FROM lab_orders lo
    LEFT JOIN users u_ordered ON lo.ordered_by = u_ordered.id
    LEFT JOIN users u_tested ON lo.tested_by = u_tested.id
    LEFT JOIN lab_order_items loi ON lo.id = loi.lab_order_id
    WHERE lo.patient_id = ?
    GROUP BY lo.id
";

$params = [$patient['id']];

// Apply filters
if (!empty($filter_date)) {
    $query .= " AND DATE(lo.order_date) = ?";
    $params[] = $filter_date;
}

if (!empty($filter_status)) {
    $query .= " AND lo.status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_test)) {
    $query .= " AND EXISTS (
        SELECT 1 FROM lab_order_items loi2 
        WHERE loi2.lab_order_id = lo.id 
        AND (loi2.test_name LIKE ? OR loi2.test_code LIKE ?)
    )";
    $search_term = "%$filter_test%";
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY lo.order_date DESC, lo.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$lab_orders = $stmt->fetchAll();

// Count statistics
$completed_count = 0;
$pending_count = 0;
$total_tests = 0;

foreach ($lab_orders as $order) {
    if ($order['status'] === 'completed') {
        $completed_count++;
    } elseif ($order['status'] === 'ordered' || $order['status'] === 'in_progress') {
        $pending_count++;
    }
    $total_tests += $order['test_count'];
}

// Get test names for filter
$test_names_stmt = $pdo->prepare("
    SELECT DISTINCT test_name 
    FROM lab_order_items loi
    INNER JOIN lab_orders lo ON loi.lab_order_id = lo.id
    WHERE lo.patient_id = ? AND loi.test_name IS NOT NULL AND loi.test_name != ''
    ORDER BY test_name
    LIMIT 20
");
$test_names_stmt->execute([$patient['id']]);
$test_names = $test_names_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

// If viewing detailed results, render the enhanced view
if (isset($_GET['view']) && isset($detailed_order) && !$print_mode): 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Test Results - AuraCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-header h1 {
            font-size: 1.875rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #64748b;
            font-size: 0.875rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #3b82f6;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }

        .back-link:hover {
            background-color: #eff6ff;
        }

        /* Report Actions */
        .report-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            text-decoration: none;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #d1d5db;
            color: #374151;
        }

        .btn-outline:hover {
            background: #f9fafb;
        }

        /* Enhanced Results Header */
        .results-header {
            background: white;
            border-radius: 0.75rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #3b82f6;
        }

        @media (max-width: 640px) {
            .results-header {
                padding: 1.5rem;
            }
        }

        .patient-header {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 2rem;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid #e5e7eb;
        }

        @media (max-width: 768px) {
            .patient-header {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 1rem;
            }
        }

        .patient-avatar {
            width: 80px;
            height: 80px;
            background: #3b82f6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }

        .patient-info {
            flex: 1;
        }

        .patient-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .patient-details {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.875rem;
            color: #64748b;
        }

        .patient-detail {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .status-in_progress {
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #f59e0b;
        }

        .status-ordered {
            background: #dbeafe;
            color: #1d4ed8;
            border: 1px solid #3b82f6;
        }

        .status-cancelled {
            background: #f3f4f6;
            color: #6b7280;
            border: 1px solid #9ca3af;
        }

        /* Order Meta Grid */
        .order-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .meta-card {
            background: #f8fafc;
            border-radius: 0.5rem;
            padding: 1.5rem;
            border-left: 3px solid #3b82f6;
        }

        .meta-label {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .meta-value {
            font-size: 1rem;
            color: #0f172a;
            font-weight: 600;
        }

        /* Enhanced Test Items */
        .test-items-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .test-item-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            transition: all 0.2s;
        }

        .test-item-card:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 640px) {
            .test-item-card {
                padding: 1rem;
            }
        }

        .test-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .test-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .test-subtitle {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.875rem;
            color: #64748b;
            flex-wrap: wrap;
        }

        .test-code {
            background: #f1f5f9;
            padding: 0.25rem 0.75rem;
            border-radius: 0.25rem;
            font-family: monospace;
        }

        /* Results Table */
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            font-size: 0.875rem;
        }

        .results-table th {
            background: #f8fafc;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }

        .results-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }

        .results-table tr:last-child td {
            border-bottom: none;
        }

        .result-value {
            font-weight: 600;
            font-size: 1rem;
            color: #0f172a;
        }

        .result-unit {
            font-size: 0.75rem;
            color: #64748b;
            margin-left: 0.25rem;
        }

        .reference-range {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        .abnormal {
            color: #dc2626;
            background: #fee2e2;
            padding: 0.125rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .normal {
            color: #059669;
            background: #d1fae5;
            padding: 0.125rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Notes Section */
        .notes-section {
            background: #f8fafc;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-top: 1rem;
            border-left: 3px solid #3b82f6;
        }

        .notes-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notes-content {
            font-size: 0.875rem;
            color: #374151;
            line-height: 1.6;
        }

        /* Processing Timeline */
        .processing-timeline {
            position: relative;
            padding: 1.5rem 0;
            margin-top: 1rem;
        }

        .processing-timeline::before {
            content: '';
            position: absolute;
            left: 1.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }

        .timeline-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .timeline-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            background: white;
            border: 2px solid #3b82f6;
            color: #3b82f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            margin-right: 1rem;
            position: relative;
            z-index: 1;
        }

        .timeline-content {
            flex: 1;
            background: white;
            padding: 1rem;
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
        }

        .timeline-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .timeline-details {
            font-size: 0.75rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .timeline-date {
            font-size: 0.75rem;
            color: #9ca3af;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid transparent;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fecaca;
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* Mobile Menu Button */
        @media (max-width: 1024px) {
            .mobile-menu-btn {
                display: block;
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 1001;
                background: white;
                border: none;
                border-radius: 0.375rem;
                padding: 0.5rem;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                cursor: pointer;
            }
        }

        /* Print Styles */
        @media print {
            .sidebar, .mobile-menu-btn, .back-link, .btn, .report-actions {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            
            .test-item-card {
                box-shadow: none !important;
                border: 1px solid #ddd;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

<!-- Mobile Menu Button -->
<button class="mobile-menu-btn" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Lab Test Results</h1>
            <p>Detailed results for order <?php echo htmlspecialchars($detailed_order['order_number']); ?></p>
        </div>
        <div>
            <a href="lab_results.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to All Results
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Results Header -->
    <div class="results-header">
        <div class="patient-header">
            <div class="patient-avatar">
                <?php echo strtoupper(substr($detailed_order['patient_name'], 0, 1)); ?>
            </div>
            <div class="patient-info">
                <h2 class="patient-name"><?php echo htmlspecialchars($detailed_order['patient_name']); ?></h2>
                <div class="patient-details">
                    <span class="patient-detail">
                        <i class="fas fa-id-card"></i> ID: <?php echo htmlspecialchars($detailed_order['patient_number']); ?>
                    </span>
                    <span class="patient-detail">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($detailed_order['age']); ?> years, <?php echo htmlspecialchars($detailed_order['gender']); ?>
                    </span>
                    <span class="patient-detail">
                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($detailed_order['phone']); ?>
                    </span>
                </div>
            </div>
            <div>
                <span class="status-badge status-<?php echo $detailed_order['status']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $detailed_order['status'])); ?>
                </span>
            </div>
        </div>

        <div class="order-meta-grid">
            <div class="meta-card">
                <div class="meta-label">Order Number</div>
                <div class="meta-value"><?php echo htmlspecialchars($detailed_order['order_number']); ?></div>
            </div>
            
            <div class="meta-card">
                <div class="meta-label">Order Date</div>
                <div class="meta-value">
                    <?php echo date('F j, Y g:i A', strtotime($detailed_order['order_date'])); ?>
                </div>
            </div>
            
            <div class="meta-card">
                <div class="meta-label">Ordered By</div>
                <div class="meta-value">
                    <?php echo htmlspecialchars($detailed_order['ordered_by_name'] ?? 'Unknown'); ?>
                </div>
            </div>
            
            <div class="meta-card">
                <div class="meta-label">Priority</div>
                <div class="meta-value">
                    <?php echo ucfirst($detailed_order['priority']); ?>
                </div>
            </div>
        </div>

        <?php if (!empty($detailed_order['clinical_notes'])): ?>
            <div class="notes-section">
                <div class="notes-title">
                    <i class="fas fa-sticky-note"></i> Clinical Notes
                </div>
                <div class="notes-content">
                    <?php echo nl2br(htmlspecialchars($detailed_order['clinical_notes'])); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Report Actions -->
        <div class="report-actions">
            <?php if ($detailed_order['status'] === 'completed'): ?>
                <a href="lab_results.php?view=<?php echo $order_id; ?>&print=true" target="_blank" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print Report
                </a>
                <!-- <a href="lab_results.php?view=<?php echo $order_id; ?>&print=true&download=pdf" class="btn btn-secondary">
                    <i class="fas fa-download"></i> Download PDF
                </a> -->
            <?php endif; ?>
            <a href="lab_results.php" class="btn btn-outline">
                <i class="fas fa-list"></i> Back to List
            </a>
        </div>
    </div>

    <!-- Test Items -->
    <div class="test-items-container">
        <?php if (empty($test_items)): ?>
            <div style="text-align: center; padding: 3rem 2rem; color: #64748b; background: white; border-radius: 0.75rem;">
                <i class="fas fa-flask" style="font-size: 3rem; margin-bottom: 1rem; color: #cbd5e1;"></i>
                <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem;">No Test Results Available</h3>
                <p>Test results for this order are not yet available or haven't been recorded.</p>
            </div>
        <?php else: ?>
            <?php foreach ($test_items as $test): ?>
                <div class="test-item-card">
                    <div class="test-item-header">
                        <div>
                            <h3 class="test-title"><?php echo htmlspecialchars($test['test_name']); ?></h3>
                            <div class="test-subtitle">
                                <?php if ($test['test_code']): ?>
                                    <span class="test-code">Code: <?php echo htmlspecialchars($test['test_code']); ?></span>
                                <?php endif; ?>
                                <?php if ($test['specimen_type']): ?>
                                    <span>
                                        <i class="fas fa-vial"></i> <?php echo htmlspecialchars($test['specimen_type']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($test['specimen_condition']): ?>
                                    <span style="font-size: 0.75rem; color: #9ca3af;">
                                        (<?php echo htmlspecialchars($test['specimen_condition']); ?>)
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <span class="status-badge status-<?php echo $test['status']; ?>" style="font-size: 0.7rem;">
                                <?php echo ucfirst(str_replace('_', ' ', $test['status'])); ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($test['status'] === 'completed' && ($test['result_value'] || $test['result_notes'])): ?>
                        <table class="results-table">
                            <thead>
                                <tr>
                                    <th>Parameter</th>
                                    <th>Result</th>
                                    <th>Unit</th>
                                    <th>Reference Range</th>
                                    <th>Interpretation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?php echo htmlspecialchars($test['test_name']); ?></td>
                                    <td>
                                        <span class="result-value">
                                            <?php echo htmlspecialchars($test['result_value']); ?>
                                            <?php if ($test['result_unit']): ?>
                                                <span class="result-unit"><?php echo htmlspecialchars($test['result_unit']); ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($test['result_unit'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($test['reference_range']): ?>
                                            <span class="reference-range">
                                                <?php echo htmlspecialchars($test['reference_range']); ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($test['result_value'] && $test['reference_range']): 
                                            $result = floatval(preg_replace('/[^0-9.]/', '', $test['result_value']));
                                            $range = $test['reference_range'];
                                            preg_match('/(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)/', $range, $matches);
                                            
                                            if (count($matches) === 3) {
                                                $min = floatval($matches[1]);
                                                $max = floatval($matches[2]);
                                                $is_normal = ($result >= $min && $result <= $max);
                                        ?>
                                            <span class="<?php echo $is_normal ? 'normal' : 'abnormal'; ?>">
                                                <?php echo $is_normal ? 'Normal' : 'Abnormal'; ?>
                                            </span>
                                        <?php } endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if ($test['instructions'] || $test['collection_notes']): ?>
                        <div class="notes-section">
                            <?php if ($test['instructions']): ?>
                                <div class="notes-title">
                                    <i class="fas fa-info-circle"></i> Instructions
                                </div>
                                <div class="notes-content">
                                    <?php echo nl2br(htmlspecialchars($test['instructions'])); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($test['collection_notes']): ?>
                                <div class="notes-title" style="margin-top: 1rem;">
                                    <i class="fas fa-clipboard-check"></i> Collection Notes
                                </div>
                                <div class="notes-content">
                                    <?php echo nl2br(htmlspecialchars($test['collection_notes'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($test['result_notes']): ?>
                        <div class="notes-section">
                            <div class="notes-title">
                                <i class="fas fa-comment-medical"></i> Result Notes
                            </div>
                            <div class="notes-content">
                                <?php echo nl2br(htmlspecialchars($test['result_notes'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="processing-timeline">
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="fas fa-syringe"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-title">Sample Collected</div>
                                <div class="timeline-details">
                                    <?php if ($test['collected_by']): ?>
                                        <span>By: <?php echo htmlspecialchars($test['collected_by']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($test['collected_at']): ?>
                                        <span class="timeline-date">
                                            <?php echo date('M d, Y g:i A', strtotime($test['collected_at'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($test['tested_at']): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <i class="fas fa-microscope"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-title">Test Performed</div>
                                    <div class="timeline-details">
                                        <?php if ($test['tested_by']): ?>
                                            <span>By: <?php echo htmlspecialchars($test['tested_by']); ?></span>
                                        <?php endif; ?>
                                        <span class="timeline-date">
                                            <?php echo date('M d, Y g:i A', strtotime($test['tested_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($test['verified_at']): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-title">Verified</div>
                                    <div class="timeline-details">
                                        <?php if ($test['verified_by']): ?>
                                            <span>By: <?php echo htmlspecialchars($test['verified_by']); ?></span>
                                        <?php endif; ?>
                                        <span class="timeline-date">
                                            <?php echo date('M d, Y g:i A', strtotime($test['verified_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    // Mobile sidebar toggle
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        if (sidebar.style.display === 'none' || !sidebar.style.display) {
            sidebar.style.display = 'block';
            mainContent.style.marginLeft = '280px';
        } else {
            sidebar.style.display = 'none';
            mainContent.style.marginLeft = '0';
        }
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        if (window.innerWidth <= 1024) {
            sidebar.style.display = 'none';
            mainContent.style.marginLeft = '0';
        } else {
            sidebar.style.display = 'block';
            mainContent.style.marginLeft = '280px';
        }
    });
    
    // Initialize on load
    document.addEventListener('DOMContentLoaded', function() {
        if (window.innerWidth <= 1024) {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                sidebar.style.display = 'none';
            }
        }
    });
</script>

</body>
</html>
<?php
// Exit here since we're showing detailed view
exit;
endif;
// End of detailed view section
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Test Results - AuraCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.875rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #64748b;
            font-size: 0.875rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 640px) {
            .stat-card {
                padding: 1rem;
            }
        }

        .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.completed {
            background: #d1fae5;
            color: #10b981;
        }

        .stat-icon.pending {
            background: #fef3c7;
            color: #f59e0b;
        }

        .stat-icon.total {
            background: #dbeafe;
            color: #3b82f6;
        }

        .stat-info {
            flex: 1;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #64748b;
        }

        /* Filter Section */
        .filter-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 640px) {
            .filter-card {
                padding: 1rem;
            }
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .filter-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #0f172a;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        @media (max-width: 640px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
        }

        .form-control {
            padding: 0.625rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            width: 100%;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #d1d5db;
            color: #374151;
        }

        .btn-outline:hover {
            background: #f9fafb;
        }

        /* Lab Orders List */
        .orders-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .order-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
        }

        @media (max-width: 640px) {
            .order-card {
                padding: 1rem;
            }
        }

        .order-card:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .order-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .order-number {
            font-size: 0.875rem;
            color: #64748b;
            background: #f1f5f9;
            padding: 0.125rem 0.5rem;
            border-radius: 0.25rem;
            display: inline-block;
        }

        .order-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-in_progress {
            background: #fef3c7;
            color: #d97706;
        }

        .status-ordered {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-cancelled {
            background: #f3f4f6;
            color: #6b7280;
        }

        .order-body {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 640px) {
            .order-body {
                grid-template-columns: 1fr;
            }
        }

        .order-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .info-label {
            color: #64748b;
            min-width: 100px;
        }

        .info-value {
            color: #0f172a;
            font-weight: 500;
        }

        .tests-summary {
            background: #f8fafc;
            border-radius: 0.5rem;
            padding: 1rem;
        }

        .summary-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .tests-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .test-badge {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.25rem;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            color: #374151;
        }

        .order-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        @media (max-width: 640px) {
            .order-actions {
                flex-direction: column;
            }
            
            .order-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #64748b;
        }

        @media (max-width: 640px) {
            .empty-state {
                padding: 2rem 1rem;
            }
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }

        .empty-state h3 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #64748b;
        }

        .empty-state p {
            font-size: 0.875rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* Mobile Menu Button */
        @media (max-width: 1024px) {
            .mobile-menu-btn {
                display: block;
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 1001;
                background: white;
                border: none;
                border-radius: 0.375rem;
                padding: 0.5rem;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                cursor: pointer;
            }
        }
    </style>
</head>
<body>

<!-- Mobile Menu Button -->
<button class="mobile-menu-btn" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<div class="main-content">
    <div class="page-header">
        <h1>Lab Test Results</h1>
        <p>View all your laboratory test results and orders</p>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <!-- <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon completed">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $completed_count; ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon pending">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $pending_count; ?></div>
                <div class="stat-label">In Progress</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon total">
                <i class="fas fa-flask"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $total_tests; ?></div>
                <div class="stat-label">Total Tests</div>
            </div>
        </div>
    </div> -->

    <!-- Filter Section -->
    <div class="filter-card">
        <div class="filter-header">
            <h2 class="filter-title">Filter Results</h2>
            <?php if ($filter_date || $filter_status || $filter_test): ?>
                <a href="lab_results.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
            <?php endif; ?>
        </div>
        
        <form method="GET" class="filter-form">
            <div class="form-group">
                <label class="form-label">Order Date</label>
                <input type="date" name="filter_date" class="form-control" value="<?php echo htmlspecialchars($filter_date); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="filter_status" class="form-control">
                    <option value="">All Status</option>
                    <option value="ordered" <?php echo $filter_status === 'ordered' ? 'selected' : ''; ?>>Ordered</option>
                    <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Test Name</label>
                <select name="filter_test" class="form-control">
                    <option value="">All Tests</option>
                    <?php foreach ($test_names as $test_name): ?>
                        <option value="<?php echo htmlspecialchars($test_name); ?>" <?php echo $filter_test === $test_name ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($test_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Lab Orders List -->
    <div class="orders-container">
        <?php if (empty($lab_orders)): ?>
            <div class="empty-state">
                <i class="fas fa-flask"></i>
                <h3>No Lab Results Found</h3>
                <p>
                    <?php if ($filter_date || $filter_status || $filter_test): ?>
                        No lab orders match your filter criteria. Try adjusting your filters.
                    <?php else: ?>
                        You don't have any lab test results yet. Tests will appear here once ordered by your doctor.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($lab_orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <h3 class="order-title">
                                Lab Order
                                <span class="order-number"><?php echo htmlspecialchars($order['order_number']); ?></span>
                            </h3>
                        </div>
                        
                        <span class="order-status status-<?php echo $order['status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                        </span>
                    </div>
                    
                    <div class="order-body">
                        <div class="order-info">
                            <div class="info-row">
                                <span class="info-label"><i class="far fa-calendar"></i> Order Date:</span>
                                <span class="info-value">
                                    <?php echo date('F j, Y', strtotime($order['order_date'])); ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-user-md"></i> Ordered By:</span>
                                <span class="info-value">
                                    <?php echo htmlspecialchars($order['ordered_by_name'] ?? 'Unknown'); ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-clock"></i> Priority:</span>
                                <span class="info-value">
                                    <?php echo ucfirst($order['priority']); ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-dollar-sign"></i> Amount:</span>
                                <span class="info-value">
                                    KES <?php echo number_format($order['total_amount'], 2); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="tests-summary">
                            <div class="summary-label">
                                <i class="fas fa-vial"></i> Tests (<?php echo $order['test_count']; ?>)
                            </div>
                            <?php 
                            // Get test names for this order
                            $test_items_stmt = $pdo->prepare("
                                SELECT test_name 
                                FROM lab_order_items 
                                WHERE lab_order_id = ? 
                                ORDER BY id 
                                LIMIT 5
                            ");
                            $test_items_stmt->execute([$order['id']]);
                            $order_tests = $test_items_stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                            ?>
                            <div class="tests-list">
                                <?php foreach ($order_tests as $test_name): ?>
                                    <span class="test-badge"><?php echo htmlspecialchars($test_name); ?></span>
                                <?php endforeach; ?>
                                <?php if ($order['test_count'] > 5): ?>
                                    <span class="test-badge">+<?php echo $order['test_count'] - 5; ?> more</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($order['clinical_notes'])): ?>
                        <div style="background: #f8fafc; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem;">
                            <div style="font-size: 0.875rem; font-weight: 500; color: #64748b; margin-bottom: 0.5rem;">
                                <i class="fas fa-sticky-note"></i> Clinical Notes
                            </div>
                            <div style="font-size: 0.875rem; color: #374151;">
                                <?php echo nl2br(htmlspecialchars(substr($order['clinical_notes'], 0, 200))); ?>
                                <?php if (strlen($order['clinical_notes']) > 200): ?>...<?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="order-actions">
                        <a href="lab_results.php?view=<?php echo $order['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                        
                        <?php if ($order['status'] === 'completed'): ?>
                            <a href="lab_results.php?view=<?php echo $order['id']; ?>&print=true" target="_blank" class="btn btn-outline">
                                <i class="fas fa-print"></i> View report
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    // Mobile sidebar toggle
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        if (sidebar.style.display === 'none' || !sidebar.style.display) {
            sidebar.style.display = 'block';
            mainContent.style.marginLeft = '280px';
        } else {
            sidebar.style.display = 'none';
            mainContent.style.marginLeft = '0';
        }
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        if (window.innerWidth <= 1024) {
            sidebar.style.display = 'none';
            mainContent.style.marginLeft = '0';
        } else {
            sidebar.style.display = 'block';
            mainContent.style.marginLeft = '280px';
        }
    });
    
    // Initialize on load
    document.addEventListener('DOMContentLoaded', function() {
        if (window.innerWidth <= 1024) {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                sidebar.style.display = 'none';
            }
        }
        
        // Auto-submit filters on change
        const filterInputs = document.querySelectorAll('.filter-form select, .filter-form input[type="date"]');
        filterInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (this.value) {
                    this.form.submit();
                }
            });
        });
    });
</script>

</body>
</html>