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
    // Get ALL hospital items for this patient on this date
    $hospital_stmt = $pdo->prepare("
        SELECT 
            b.*,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_id as patient_number,
            p.phone as patient_phone,
            p.date_of_birth,
            p.gender,
            bi.item_description,
            bi.quantity,
            bi.unit_price,
            bi.total_amount as item_total,
            bi.item_type,
            bi.department,
            'hospital' as source_type
        FROM billing b
        INNER JOIN patients p ON b.patient_id = p.id
        LEFT JOIN bill_items bi ON b.id = bi.bill_id
        WHERE b.patient_id = ? 
        AND DATE(b.bill_date) = ?
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
            'pharmacy' as source_type,
            COALESCE(CONCAT(p.first_name, ' ', p.last_name), pb.CustomerName) as patient_name,
            COALESCE(p.patient_id, 'N/A') as patient_number,
            COALESCE(p.phone, '') as patient_phone,
            p.date_of_birth,
            p.gender
        FROM pharmacy_billing pb
        LEFT JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
        LEFT JOIN patients p ON pb.patient_id = p.id
        WHERE pb.patient_id = ? 
        AND DATE(pb.date) = ?
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
        $patient_info = $all_items[0];
        
        // Calculate totals and collect bill numbers
        $hospital_bill_numbers = [];
        $pharmacy_bill_numbers = [];
        $total_amount = 0;
        $paid_amount = 0;
        $balance_amount = 0;
        
        foreach ($all_items as $item) {
            if ($item['source_type'] === 'hospital' && !in_array($item['bill_number'], $hospital_bill_numbers)) {
                $hospital_bill_numbers[] = $item['bill_number'];
                $total_amount += $item['total_amount'];
                $paid_amount += $item['paid_amount'];
                $balance_amount += $item['balance_amount'];
            } elseif ($item['source_type'] === 'pharmacy' && !in_array($item['bill_no'], $pharmacy_bill_numbers)) {
                $pharmacy_bill_numbers[] = $item['bill_no'];
                $total_amount += $item['total_amount'];
                $paid_amount += $item['paid_amount'];
                $balance_amount += $item['balance_amount'];
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
    $patient_id = $patient_info['patient_number'] ?? 'N/A';
    $consolidated_bill_number = 'FCB-' . date('Ymd') . '-' . $patient_info['patient_id'];
    $receipt_date = date('M d, Y');
    $visit_date = date('M d, Y', strtotime($items[0]['bill_date'] ?? $items[0]['date'] ?? date('Y-m-d')));
    
    // Group items by department
    $items_by_department = [];
    foreach ($items as $item) {
        if (!empty($item['item_description'])) {
            $dept = $item['department'] ?? ($item['source_type'] === 'pharmacy' ? 'Pharmacy' : 'General');
            if (!isset($items_by_department[$dept])) {
                $items_by_department[$dept] = [];
            }
            
            $item_key = $item['item_description'] . '|' . $item['quantity'] . '|' . $item['unit_price'];
            if (!isset($items_by_department[$dept][$item_key])) {
                $items_by_department[$dept][$item_key] = [
                    'description' => $item['item_description'],
                    'type' => $item['item_type'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $item['item_total'],
                    'source' => $item['source_type']
                ];
            }
        }
    }
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Fully Consolidated Receipt - <?php echo $consolidated_bill_number; ?></title>
        <style>
            /* Reset all margins and paddings for print */
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                background: white !important;
                color: black !important;
                margin: 0 !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            /* Hide everything except the receipt container when printing */
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
                    background: white !important;
                    margin: 0 !important;
                    padding: 20px !important;
                    box-shadow: none !important;
                }
                
                .print-actions {
                    display: none !important;
                }
            }
            
            .receipt-container { 
                max-width: 800px; 
                margin: 20px auto; 
                background: white; 
                padding: 30px; 
                border-radius: 10px; 
                box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            }
            
            .header { 
                text-align: center; 
                border-bottom: 2px solid #3b82f6; 
                padding-bottom: 20px; 
                margin-bottom: 30px; 
            }
            
            .header h1 { 
                color: #3b82f6; 
                margin: 0; 
                font-size: 28px; 
            }
            
            .header h2 { 
                color: #6b7280; 
                margin: 10px 0 0 0; 
                font-size: 18px; 
                font-weight: normal; 
            }
            
            .header h3 { 
                color: #4b5563; 
                margin: 5px 0 0 0; 
                font-size: 16px; 
                font-weight: 600; 
            }
            
            .receipt-info { 
                display: grid; 
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
                gap: 20px; 
                margin-bottom: 30px; 
                background: #f9fafb; 
                padding: 20px; 
                border-radius: 8px; 
            }
            
            .info-group h3 { 
                color: #374151; 
                margin: 0 0 10px 0; 
                font-size: 14px; 
                font-weight: 600; 
            }
            
            .info-group p { 
                margin: 0; 
                color: #6b7280; 
                font-size: 14px; 
                line-height: 1.5; 
            }
            
            .bill-numbers-section { 
                margin-bottom: 20px; 
            }
            
            .bill-numbers { 
                display: flex; 
                flex-wrap: wrap; 
                gap: 10px; 
                margin-top: 10px; 
            }
            
            .bill-number-badge { 
                background: #e0f2fe; 
                color: #0369a1; 
                padding: 5px 10px; 
                border-radius: 4px; 
                font-size: 12px; 
                font-weight: 500; 
            }
            
            .bill-number-badge.pharmacy { 
                background: #f3e8ff; 
                color: #7c3aed; 
            }
            
            .department-section { 
                margin-bottom: 30px; 
            }
            
            .department-header { 
                background: #f1f5f9; 
                padding: 12px 15px; 
                border-radius: 6px; 
                margin-bottom: 15px; 
            }
            
            .department-header h3 { 
                margin: 0; 
                color: #374151; 
                font-size: 16px; 
                font-weight: 600; 
            }
            
            .items-table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-bottom: 20px; 
            }
            
            .items-table th { 
                background: #f8fafc; 
                color: #374151; 
                font-weight: 600; 
                padding: 12px; 
                text-align: left; 
                border-bottom: 2px solid #e5e7eb; 
                font-size: 13px; 
            }
            
            .items-table td { 
                padding: 12px; 
                border-bottom: 1px solid #e5e7eb; 
                color: #4b5563; 
                font-size: 13px; 
            }
            
            .items-table tr:last-child td { 
                border-bottom: none; 
            }
            
            .summary { 
                background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); 
                padding: 25px; 
                border-radius: 8px; 
                margin: 30px 0; 
                border: 2px solid #bae6fd; 
            }
            
            .summary-row { 
                display: flex; 
                justify-content: space-between; 
                padding: 10px 0; 
                border-bottom: 1px solid rgba(186, 230, 253, 0.5); 
            }
            
            .summary-row:last-child { 
                border-bottom: none; 
                font-weight: bold; 
                font-size: 18px; 
                color: #0369a1; 
            }
            
            .footer { 
                text-align: center; 
                margin-top: 40px; 
                padding-top: 20px; 
                border-top: 2px dashed #d1d5db; 
                color: #6b7280; 
                font-size: 12px; 
            }
            
            .footer p { 
                margin: 5px 0; 
            }
            
            .print-actions { 
                text-align: center; 
                margin-top: 30px; 
            }
            
            .print-actions button { 
                padding: 12px 30px; 
                margin: 0 10px; 
                border: none; 
                border-radius: 6px; 
                font-weight: 600; 
                cursor: pointer; 
                font-size: 14px; 
            }
            
            .print-btn { 
                background: #3b82f6; 
                color: white; 
            }
            
            .close-btn { 
                background: #6b7280; 
                color: white; 
            }
            
            /* Page break control for printing */
            @media print {
                .department-section {
                    page-break-inside: avoid;
                }
                
                .summary {
                    page-break-inside: avoid;
                }
                
                @page {
                    size: A4;
                    margin: 10mm;
                }
            }
        </style>
    </head>
    <body>
        <div class="receipt-container">
            <div class="header">
                <h1>EQUITY AFIA MEDICAL CENTER</h1>
                <h2>FULLY CONSOLIDATED RECEIPT</h2>
                <h3>All Services Rendered on <?php echo $visit_date; ?></h3>
            </div>
            
            <div class="receipt-info">
                <div class="info-group">
                    <h3>Receipt Information</h3>
                    <p><strong>Consolidated Receipt No:</strong> <?php echo $consolidated_bill_number; ?></p>
                    <p><strong>Visit Date:</strong> <?php echo $visit_date; ?></p>
                    <p><strong>Generated:</strong> <?php echo date('M d, Y H:i'); ?></p>
                </div>
                
                <div class="info-group">
                    <h3>Patient Information</h3>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($patient_name); ?></p>
                    <p><strong>Patient ID:</strong> <?php echo htmlspecialchars($patient_id); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient_info['patient_phone'] ?? 'N/A'); ?></p>
                </div>
            </div>
            
            <div class="bill-numbers-section">
                <h3>Original Bills Consolidated</h3>
                <div class="bill-numbers">
                    <?php if (!empty($hospital_bill_numbers)): ?>
                    <?php foreach ($hospital_bill_numbers as $bill): ?>
                    <span class="bill-number-badge">Hospital: <?php echo htmlspecialchars($bill); ?></span>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($pharmacy_bill_numbers)): ?>
                    <?php foreach ($pharmacy_bill_numbers as $bill): ?>
                    <span class="bill-number-badge pharmacy">Pharmacy: <?php echo htmlspecialchars($bill); ?></span>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php foreach ($items_by_department as $department => $dept_items): ?>
            <div class="department-section">
                <div class="department-header">
                    <h3><?php echo htmlspecialchars($department); ?> Services</h3>
                </div>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th width="50%">Service/Item Description</th>
                            <th width="20%">Type</th>
                            <th width="15%">Quantity</th>
                            <th width="15%">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dept_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['description']); ?></td>
                            <td><?php echo htmlspecialchars($item['type']); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>KSH <?php echo number_format($item['total'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
            
            <div class="summary">
                <div class="summary-row">
                    <span>Total Departments:</span>
                    <span><?php echo count($items_by_department); ?></span>
                </div>
                <div class="summary-row">
                    <span>Total Services/Items:</span>
                    <span><?php echo count($items); ?></span>
                </div>
                <div class="summary-row">
                    <span>Total Original Bills:</span>
                    <span><?php echo count($hospital_bill_numbers) + count($pharmacy_bill_numbers); ?></span>
                </div>
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span>KSH <?php echo number_format($total_amount, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Amount Paid:</span>
                    <span>KSH <?php echo number_format($paid_amount, 2); ?></span>
                </div>
                <?php if ($balance_amount > 0): ?>
                <div class="summary-row">
                    <span>Outstanding Balance:</span>
                    <span style="color: #dc2626; font-weight: 600;">KSH <?php echo number_format($balance_amount, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="summary-row">
                    <span>TOTAL AMOUNT:</span>
                    <span>KSH <?php echo number_format($total_amount, 2); ?></span>
                </div>
            </div>
            
            <div class="footer">
                <p><strong>Thank you for choosing Equity Afia Medical Center</strong></p>
                <p>For inquiries: +254 700 000 000 | Email: info@equityafia.co.ke</p>
                <p>Computer generated fully consolidated receipt - Valid without signature</p>
            </div>
            
            <div class="print-actions">
                <button onclick="window.print()" class="print-btn">
                    <i class="fas fa-print"></i> Print Consolidated Receipt
                </button>
                <button onclick="window.close()" class="close-btn">
                    <i class="fas fa-times"></i> Close Window
                </button>
            </div>
        </div>
        
        <?php if (isset($_GET['autoprint']) && $_GET['autoprint'] == 1): ?>
        <script>
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
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
    echo "<html><body><h1>Error: Patient ID and Billing Date are required.</h1></body></html>";
}
?>