<?php
session_start();
include "includes/db_connect.php";
include "admin/connection.php";

// Don't include sidebar.php here

if (!isset($pdo)) {
    die("Database connection failed.");
}

// Get parameters
$patient_id = $_GET['patient_id'] ?? null;
$billing_date = $_GET['billing_date'] ?? date('Y-m-d');
$autoprint = isset($_GET['autoprint']) && $_GET['autoprint'] == 1;

function generateFullyConsolidatedReceipt($patient_id, $billing_date, $pdo, $link) {
    // Get patient details first
    $patient_stmt = $pdo->prepare("
        SELECT 
            p.*,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_id as patient_number
        FROM patients p 
        WHERE p.id = ?
    ");
    $patient_stmt->execute([$patient_id]);
    $patient_info = $patient_stmt->fetch();
    
    if (!$patient_info) {
        echo "<html><body><h1>Patient not found.</h1></body></html>";
        return;
    }
    
    // Get ALL hospital items for this patient on this date
    $hospital_stmt = $pdo->prepare("
        SELECT 
            b.*,
            bi.item_description,
            bi.quantity,
            bi.unit_price,
            bi.total_amount as item_total,
            bi.item_type,
            bi.department,
            'hospital' as source_type
        FROM billing b
        LEFT JOIN bill_items bi ON b.id = bi.bill_id
        WHERE b.patient_id = ? 
        AND DATE(b.bill_date) = ?
        AND (b.total_amount > 0 OR bi.total_amount > 0)
        ORDER BY bi.department, bi.item_type, bi.item_description
    ");
    $hospital_stmt->execute([$patient_id, $billing_date]);
    $hospital_items = $hospital_stmt->fetchAll();
    
    // Get ALL pharmacy items for this patient on this date
    mysqli_select_db($link, "hospital");
    $pharmacy_query = "
        SELECT 
            pb.*,
            pbd.product_name as item_description,
            pbd.quantity,
            pbd.price as unit_price,
            pbd.total_amount as item_total,
            'pharmacy' as item_type,
            'Pharmacy' as department,
            'pharmacy' as source_type
        FROM pharmacy_billing pb
        LEFT JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
        WHERE pb.patient_id = ? 
        AND DATE(pb.date) = ?
        AND pb.total_amount > 0
        ORDER BY pbd.product_name
    ";
    
    $pharmacy_stmt = mysqli_prepare($link, $pharmacy_query);
    mysqli_stmt_bind_param($pharmacy_stmt, "is", $patient_id, $billing_date);
    mysqli_stmt_execute($pharmacy_stmt);
    $pharmacy_result = mysqli_stmt_get_result($pharmacy_stmt);
    
    $pharmacy_items = [];
    while ($row = mysqli_fetch_assoc($pharmacy_result)) {
        $pharmacy_items[] = $row;
    }
    
    // Combine all items
    $all_items = array_merge($hospital_items, $pharmacy_items);
    
    if (count($all_items) > 0) {
        // Calculate totals and collect bill numbers
        $hospital_bill_numbers = [];
        $pharmacy_bill_numbers = [];
        $total_amount = 0;
        $paid_amount = 0;
        $balance_amount = 0;
        
        // Process hospital bills
        $processed_hospital_bills = [];
        foreach ($hospital_items as $item) {
            if (!empty($item['bill_number']) && !in_array($item['bill_number'], $processed_hospital_bills)) {
                $hospital_bill_numbers[] = $item['bill_number'];
                $processed_hospital_bills[] = $item['bill_number'];
                $total_amount += $item['total_amount'] ?? 0;
                $paid_amount += $item['paid_amount'] ?? 0;
                $balance_amount += $item['balance_amount'] ?? 0;
            }
        }
        
        // Process pharmacy bills
        $processed_pharmacy_bills = [];
        foreach ($pharmacy_items as $item) {
            if (!empty($item['bill_no']) && !in_array($item['bill_no'], $processed_pharmacy_bills)) {
                $pharmacy_bill_numbers[] = $item['bill_no'];
                $processed_pharmacy_bills[] = $item['bill_no'];
                $total_amount += $item['total_amount'] ?? 0;
                $paid_amount += $item['paid_amount'] ?? 0;
                $balance_amount += $item['balance_amount'] ?? 0;
            }
        }
        
        // Generate fully consolidated receipt HTML
        generateFullyConsolidatedReceiptHTML($patient_info, $all_items, $total_amount, $paid_amount, $balance_amount, $hospital_bill_numbers, $pharmacy_bill_numbers);
    } else {
        echo "<html><body><h1>No billing data found for the specified patient and date.</h1></body></html>";
    }
}

function generateFullyConsolidatedReceiptHTML($patient_info, $items, $total_amount, $paid_amount, $balance_amount, $hospital_bill_numbers, $pharmacy_bill_numbers) {
    $patient_name = $patient_info['patient_name'] ?? 'N/A';
    $patient_id_num = $patient_info['patient_number'] ?? 'N/A';
    $patient_phone = $patient_info['phone'] ?? 'N/A';
    $patient_dob = $patient_info['date_of_birth'] ? date('M d, Y', strtotime($patient_info['date_of_birth'])) : 'N/A';
    $patient_gender = $patient_info['gender'] ?? 'N/A';
    
    $consolidated_bill_number = 'FCB-' . date('Ymd') . '-' . rand(1000, 9999);
    $receipt_date = date('M d, Y');
    $receipt_time = date('H:i:s');
    
    // Group items by department/service type
    $items_by_department = [];
    foreach ($items as $item) {
        if (!empty($item['item_description']) || !empty($item['department'])) {
            $dept = $item['department'] ?? ($item['source_type'] === 'pharmacy' ? 'Pharmacy' : 'General Services');
            if (!isset($items_by_department[$dept])) {
                $items_by_department[$dept] = [];
            }
            
            // Use item description or generate one from item type
            $description = !empty($item['item_description']) ? $item['item_description'] : ucfirst(str_replace('_', ' ', $item['item_type'] ?? 'service'));
            $item_key = $description . '|' . ($item['quantity'] ?? 1) . '|' . ($item['unit_price'] ?? $item['item_total'] ?? 0);
            
            if (!isset($items_by_department[$dept][$item_key])) {
                $items_by_department[$dept][$item_key] = [
                    'description' => $description,
                    'type' => $item['item_type'] ?? 'service',
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? $item['item_total'] ?? 0,
                    'total' => $item['item_total'] ?? $item['total_amount'] ?? 0,
                    'source' => $item['source_type'] ?? 'hospital'
                ];
            }
        }
    }
    
    // Calculate department totals
    $department_totals = [];
    foreach ($items_by_department as $dept => $dept_items) {
        $dept_total = 0;
        foreach ($dept_items as $item) {
            $dept_total += $item['total'];
        }
        $department_totals[$dept] = $dept_total;
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Consolidated Medical Receipt - <?php echo $consolidated_bill_number; ?></title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root {
                --primarycolor: #0891b2;
                --primarydark: #0e7490;
                --primarylight: #06b6d4;
                --secondarycolor: #14b8a6;
                --accentcolor: #22d3ee;
                --bgcolor: #f0fdfa;
                --textcolor: #134e4a;
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: white;
                color: var(--textcolor);
                padding: 20px;
            }

            /* Hide everything when printing */
            @media print {
                body * {
                    visibility: hidden;
                }
                
                .receipt-container, .receipt-container * {
                    visibility: visible;
                }
                
                .receipt-container {
                    position: absolute;
                    left: 0;
                    top: 0;
                    width: 100%;
                    margin: 0;
                    padding: 20px;
                    box-shadow: none;
                }
                
                .print-actions {
                    display: none !important;
                }
            }

            .receipt-container {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }

            .header {
                text-align: center;
                padding-bottom: 20px;
                margin-bottom: 20px;
                border-bottom: 3px solid var(--primarycolor);
            }

            .clinic-title {
                font-size: 24px;
                font-weight: 700;
                color: var(--primarycolor);
                margin-bottom: 5px;
            }

            .clinic-subtitle {
                font-size: 14px;
                color: #64748b;
                margin-bottom: 15px;
            }

            .consolidated-badge {
                display: inline-block;
                background: var(--accentcolor);
                color: white;
                padding: 5px 15px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                margin-bottom: 15px;
            }

            .receipt-info {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin-bottom: 25px;
            }

            .info-card {
                background: #f8fafc;
                border-radius: 8px;
                padding: 15px;
                border: 1px solid #e5e7eb;
            }

            .info-title {
                font-size: 14px;
                font-weight: 600;
                color: var(--primarydark);
                margin-bottom: 10px;
                padding-bottom: 5px;
                border-bottom: 1px solid #e5e7eb;
            }

            .info-row {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                font-size: 13px;
            }

            .info-label {
                font-weight: 500;
                color: #64748b;
            }

            .bill-numbers-section {
                background: #f0fdfa;
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 25px;
                border: 1px solid var(--accentcolor);
            }

            .bill-numbers-title {
                font-size: 14px;
                font-weight: 600;
                color: var(--primarydark);
                margin-bottom: 10px;
            }

            .bill-numbers {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }

            .bill-badge {
                padding: 4px 10px;
                border-radius: 4px;
                font-size: 11px;
                font-weight: 500;
            }

            .bill-badge.hospital {
                background: #dbeafe;
                color: #1e40af;
            }

            .bill-badge.pharmacy {
                background: #dcfce7;
                color: #166534;
            }

            .services-section {
                margin-bottom: 30px;
            }

            .department-card {
                background: white;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                margin-bottom: 15px;
                overflow: hidden;
            }

            .department-header {
                background: var(--primarycolor);
                color: white;
                padding: 12px 15px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .department-name {
                font-size: 14px;
                font-weight: 600;
            }

            .department-total {
                font-size: 13px;
                background: rgba(255,255,255,0.2);
                padding: 2px 10px;
                border-radius: 12px;
            }

            .items-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 12px;
            }

            .items-table th {
                background: #f8fafc;
                color: #374151;
                font-weight: 600;
                padding: 10px;
                text-align: left;
                border-bottom: 1px solid #e5e7eb;
            }

            .items-table td {
                padding: 10px;
                border-bottom: 1px solid #f1f5f9;
                color: #4b5563;
            }

            .items-table tr:last-child td {
                border-bottom: none;
            }

            .summary-section {
                background: linear-gradient(135deg, #f0fdfa 0%, #e0f2fe 100%);
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 25px;
                border: 2px solid var(--accentcolor);
            }

            .summary-row {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-bottom: 1px solid rgba(8, 145, 178, 0.1);
            }

            .summary-row:last-child {
                border-bottom: none;
                font-weight: 700;
                font-size: 18px;
                color: var(--primarydark);
                margin-top: 10px;
                padding-top: 15px;
                border-top: 2px solid var(--primarycolor);
            }

            .summary-label {
                font-weight: 600;
                color: var(--primarydark);
            }

            .balance-row {
                background: #fee2e2;
                padding: 8px 15px;
                border-radius: 6px;
                margin-top: 10px;
            }

            .balance-row .summary-label {
                color: #991b1b;
            }

            .balance-row .summary-value {
                color: #dc2626;
                font-weight: 600;
            }

            .footer {
                text-align: center;
                padding-top: 20px;
                margin-top: 20px;
                border-top: 2px dashed #d1d5db;
                color: #64748b;
                font-size: 11px;
            }

            .footer p {
                margin: 5px 0;
            }

            .print-actions {
                text-align: center;
                margin-top: 30px;
                display: flex;
                justify-content: center;
                gap: 15px;
            }

            .btn {
                padding: 10px 25px;
                border: none;
                border-radius: 6px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: all 0.3s;
            }

            .btn-print {
                background: var(--primarycolor);
                color: white;
            }

            .btn-print:hover {
                background: var(--primarydark);
            }

            .btn-close {
                background: #6b7280;
                color: white;
            }

            .btn-close:hover {
                background: #4b5563;
            }

            /* Service type badges */
            .service-badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 10px;
                font-weight: 500;
                margin-left: 5px;
            }

            .badge-consultation { background: #dbeafe; color: #1e40af; }
            .badge-pharmacy { background: #dcfce7; color: #166534; }
            .badge-lab { background: #fef3c7; color: #92400e; }
            .badge-admission { background: #f3e8ff; color: #7c3aed; }
            .badge-general { background: #e0f2fe; color: #0c4a6e; }

            /* Print-specific styles */
            @page {
                size: A4;
                margin: 15mm;
            }

            @media print {
                @page {
                    margin: 10mm;
                }
                
                body {
                    padding: 0;
                }
                
                .receipt-container {
                    padding: 15px;
                    border-radius: 0;
                    box-shadow: none;
                }
            }
        </style>
    </head>
    <body>
        <div class="receipt-container">
            <!-- Header -->
            <div class="header">
                <h1 class="clinic-title">AURACARE HMS</h1>
                <p class="clinic-subtitle">Comprehensive Medical Services Receipt</p>
                <div class="consolidated-badge">
                    <i class="fas fa-file-contract"></i> FULLY CONSOLIDATED RECEIPT
                </div>
            </div>

            <!-- Receipt Information -->
            <div class="receipt-info">
                <div class="info-card">
                    <div class="info-title">
                        <i class="fas fa-receipt"></i> Receipt Details
                    </div>
                    <div class="info-row">
                        <span class="info-label">Receipt Number:</span>
                        <span style="font-weight: 600; color: var(--primarycolor);"><?php echo $consolidated_bill_number; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Issue Date:</span>
                        <span><?php echo $receipt_date; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Time:</span>
                        <span><?php echo $receipt_time; ?></span>
                    </div>
                </div>

                <div class="info-card">
                    <div class="info-title">
                        <i class="fas fa-user"></i> Patient Information
                    </div>
                    <div class="info-row">
                        <span class="info-label">Patient Name:</span>
                        <span style="font-weight: 600;"><?php echo htmlspecialchars($patient_name); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Patient ID:</span>
                        <span><?php echo htmlspecialchars($patient_id_num); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Contact:</span>
                        <span><?php echo htmlspecialchars($patient_phone); ?></span>
                    </div>
                </div>
            </div>

            <!-- Consolidated Bill Numbers -->
            <?php if (!empty($hospital_bill_numbers) || !empty($pharmacy_bill_numbers)): ?>
            <div class="bill-numbers-section">
                <div class="bill-numbers-title">
                    <i class="fas fa-file-invoice"></i> Consolidated Original Bills
                </div>
                <div class="bill-numbers">
                    <?php if (!empty($hospital_bill_numbers)): ?>
                    <?php foreach ($hospital_bill_numbers as $bill): ?>
                    <span class="bill-badge hospital">
                        <i class="fas fa-hospital"></i> <?php echo htmlspecialchars($bill); ?>
                    </span>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($pharmacy_bill_numbers)): ?>
                    <?php foreach ($pharmacy_bill_numbers as $bill): ?>
                    <span class="bill-badge pharmacy">
                        <i class="fas fa-prescription-bottle"></i> <?php echo htmlspecialchars($bill); ?>
                    </span>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Services Section -->
            <div class="services-section">
                <h3 style="color: var(--primarycolor); margin-bottom: 15px; font-size: 16px;">
                    <i class="fas fa-stethoscope"></i> Services Rendered
                </h3>
                
                <?php foreach ($items_by_department as $department => $dept_items): ?>
                <div class="department-card">
                    <div class="department-header">
                        <span class="department-name">
                            <i class="fas fa-clinic-medical"></i> <?php echo htmlspecialchars($department); ?>
                        </span>
                        <span class="department-total">
                            KSh <?php echo number_format($department_totals[$department], 2); ?>
                        </span>
                    </div>
                    
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th width="50%">Service/Item</th>
                                <th width="20%">Type</th>
                                <th width="15%">Qty</th>
                                <th width="15%">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dept_items as $item): 
                                $badge_class = 'badge-' . strtolower(str_replace(' ', '-', $item['type']));
                            ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($item['description']); ?>
                                    <?php if ($item['type']): ?>
                                    <span class="service-badge <?php echo $badge_class; ?>">
                                        <?php echo ucfirst($item['type']); ?>
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($item['type']); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>KSh <?php echo number_format($item['total'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Financial Summary -->
            <div class="summary-section">
                <h3 style="color: var(--primarydark); margin-bottom: 15px; font-size: 16px;">
                    <i class="fas fa-calculator"></i> Financial Summary
                </h3>
                
                <div class="summary-row">
                    <span class="summary-label">Total Services:</span>
                    <span><?php echo count($items_by_department); ?> Departments</span>
                </div>
                
                <?php if (!empty($hospital_bill_numbers) || !empty($pharmacy_bill_numbers)): ?>
                <div class="summary-row">
                    <span class="summary-label">Original Bills:</span>
                    <span><?php echo count($hospital_bill_numbers) + count($pharmacy_bill_numbers); ?> Bills</span>
                </div>
                <?php endif; ?>
                
                <div class="summary-row">
                    <span class="summary-label">Total Amount Billed:</span>
                    <span class="summary-value">KSh <?php echo number_format($total_amount, 2); ?></span>
                </div>
                
                <?php if ($paid_amount > 0): ?>
                <div class="summary-row">
                    <span class="summary-label">Amount Paid:</span>
                    <span style="color: #059669; font-weight: 600;">KSh <?php echo number_format($paid_amount, 2); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($balance_amount > 0): ?>
                <div class="balance-row summary-row">
                    <span class="summary-label">Outstanding Balance:</span>
                    <span class="summary-value">KSh <?php echo number_format($balance_amount, 2); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="summary-row">
                    <span class="summary-label">NET TOTAL:</span>
                    <span class="summary-value">KSh <?php echo number_format($total_amount, 2); ?></span>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer">
                <p><strong>Important Notice:</strong> This is a consolidated receipt covering all services rendered.</p>
                <p>For inquiries, contact: +254 700 000 000 | Email: info@auracarehms.co.ke</p>
                <p>Generated on: <?php echo date('M d, Y H:i:s'); ?></p>
              
            </div>

            <!-- Print Actions -->
            <div class="print-actions">
                <button onclick="window.print()" class="btn btn-print">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <button onclick="window.close()" class="btn btn-close">
                    <i class="fas fa-times"></i> Close Window
                </button>
            </div>
        </div>

        <?php if (isset($_GET['autoprint']) && $_GET['autoprint'] == 1): ?>
        <script>
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 1000);
            };
        </script>
        <?php endif; ?>
    </body>
    </html>
    <?php
}

// Check if parameters are provided
if ($patient_id && $billing_date) {
    generateFullyConsolidatedReceipt($patient_id, $billing_date, $pdo, $link);
} else {
    echo "<html><body>
        <div style='text-align: center; padding: 50px;'>
            <h1 style='color: #ef4444;'>Error: Missing Parameters</h1>
            <p style='color: #64748b; margin-top: 10px;'>Patient ID and Billing Date are required to generate the consolidated receipt.</p>
            <button onclick='window.close()' style='margin-top: 20px; padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 6px; cursor: pointer;'>
                Close Window
            </button>
        </div>
    </body></html>";
}
?>