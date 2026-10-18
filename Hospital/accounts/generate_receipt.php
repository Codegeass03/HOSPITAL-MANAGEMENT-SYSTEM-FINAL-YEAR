<?php
session_start();
require_once "includes/db_connect.php";
include "admin/connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$payment_id = $_GET['payment_id'] ?? null;

if (!$payment_id) {
    die("Payment ID is required.");
}

// Fetch payment details
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            CONCAT(pt.first_name, ' ', pt.last_name) as patient_name,
            pt.patient_id as patient_number,
            pt.phone as patient_phone,
            pt.email as patient_email,
            pt.address as patient_address,
            CONCAT(u.first_name, ' ', u.last_name) as received_by_name
        FROM payments p
        INNER JOIN patients pt ON p.patient_id = pt.id
        LEFT JOIN users u ON p.received_by = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        die("Payment not found.");
    }
    
    // Fetch payment allocations with improved admission details
    $stmt = $pdo->prepare("
        SELECT 
            pa.*,
            CASE pa.service_type
                WHEN 'consultation' THEN CONCAT('Consultation - ', COALESCE(a.appointment_number, 'N/A'))
                WHEN 'lab' THEN CONCAT('Lab Test - ', COALESCE(lo.order_number, 'N/A'))
                WHEN 'pharmacy' THEN CONCAT('Pharmacy - ', COALESCE(pb.bill_no, 'N/A'))
                WHEN 'admission' THEN CONCAT('Admission Charges - ', COALESCE(w.ward_name, 'Ward'), ' (Bed: ', COALESCE(bd.bed_number, 'N/A'), ')')
                WHEN 'general' THEN CONCAT('General Bill - ', COALESCE(ba.bed_id, 'N/A'))
                ELSE CONCAT(UPPER(SUBSTRING(pa.service_type, 1, 1)), SUBSTRING(pa.service_type, 2))
            END as service_description
        FROM payment_allocations pa
        LEFT JOIN appointments a ON pa.service_type = 'consultation' AND pa.reference_id = a.id
        LEFT JOIN lab_orders lo ON pa.service_type = 'lab' AND pa.reference_id = lo.id
        LEFT JOIN pharmacy_billing pb ON pa.service_type = 'pharmacy' AND pa.reference_id = pb.id
        LEFT JOIN bed_assignments ba ON pa.service_type = 'admission' AND pa.reference_id = ba.id
        LEFT JOIN beds bd ON ba.bed_id = bd.id
        LEFT JOIN ward w ON bd.ward_id = w.id
        LEFT JOIN billing b ON pa.service_type = 'admission' AND pa.reference_id = b.id
        WHERE pa.payment_id = ?
        ORDER BY pa.id
    ");
    $stmt->execute([$payment_id]);
    $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no allocations found, try to get from billing table directly
    if (empty($allocations)) {
        $stmt = $pdo->prepare("
            SELECT 
                bi.item_type as service_type,
                bi.item_description as service_description,
                bi.total_amount as allocated_amount,
                bi.created_at
            FROM billing b
            INNER JOIN bill_items bi ON b.id = bi.billing_id
            WHERE b.patient_id = ?
            AND DATE(b.bill_date) = DATE(?)
            AND b.paid_amount > 0
            ORDER BY bi.id
        ");
        $stmt->execute([$payment['patient_id'], $payment['created_at']]);
        $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Since there's no facility_info table, create default facility info
    $facility = [
        'facility_name' => 'AuraCare Hospital Management System',
        'facility_address' => 'Nairobi, Kenya',
        'facility_phone' => '+254 700 000 000',
        'facility_email' => 'info@auracarehms.com',
        'logo_path' => null
    ];
    
} catch (PDOException $e) {
    die("Error fetching receipt data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - <?php echo htmlspecialchars($payment['payment_number']); ?></title>
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
            background: var(--bgcolor);
            color: var(--textcolor);
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .nav-bar {
            background: white;
            padding: 20px 40px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-bar h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--primarycolor);
            margin: 0;
        }

        .nav-bar p {
            font-size: 14px;
            color: #64748b;
            margin-top: 5px;
        }

        .receipt-container {
            background: white;
            padding: 2rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            font-family: 'Courier New', monospace;
            margin-bottom: 20px;
        }

        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #e5e7eb;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }

        .receipt-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: var(--primarycolor);
        }

        .receipt-subtitle {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .receipt-details {
            margin-bottom: 1.5rem;
        }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .receipt-divider {
            border-top: 1px dashed #e5e7eb;
            margin: 1rem 0;
        }

        .receipt-total {
            font-weight: bold;
            font-size: 1.125rem;
            color: var(--primarydark);
        }

        .receipt-footer {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.75rem;
            color: #6b7280;
        }

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
                box-shadow: none;
                margin: 0;
                padding: 1rem;
            }
            .no-print {
                display: none !important;
            }
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primarycolor);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primarydark);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        /* Service badges */
        .service-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 5px;
        }

        .badge-consultation { background: #dbeafe; color: #1e40af; }
        .badge-pharmacy { background: #dcfce7; color: #166534; }
        .badge-lab { background: #fef3c7; color: #92400e; }
        .badge-admission { background: #f3e8ff; color: #7c3aed; }
        .badge-general { background: #e0f2fe; color: #0c4a6e; }
        .badge-bed_charge { background: #f3e8ff; color: #7c3aed; }

        /* Facility info */
        .facility-info {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #ccc;
        }

        .facility-name {
            font-size: 18px;
            font-weight: bold;
            color: var(--primarydark);
            margin-bottom: 5px;
        }

        .facility-details {
            font-size: 12px;
            color: #666;
        }

        /* Patient info box */
        .patient-info-box {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border: 1px solid #e5e7eb;
        }

        .info-label {
            font-weight: 600;
            color: var(--primarydark);
        }

        /* Items table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        .items-table th {
            border-bottom: 1px solid #e5e7eb;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            background: #f9fafb;
        }

        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #f3f4f6;
        }

        .items-table tr:last-child td {
            border-bottom: 2px solid #e5e7eb;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-bar">
            <div>
                <h1><i class="fas fa-receipt"></i> Payment Receipt</h1>
                <p>Receipt No: <?php echo htmlspecialchars($payment['payment_number']); ?></p>
            </div>
            <div style="text-align: right;">
                <p style="font-size: 14px;color: #64748b;margin:0;">Generated Date</p>
                <p style="font-size: 16px;font-weight: 600;color: var(--primarydark);margin:5px 0 0 0;"><?php echo date('F j, Y H:i:s'); ?></p>
            </div>
        </div>

        <!-- Receipt Container -->
        <div class="receipt-container" id="receipt">
            <!-- Facility Information -->
            <div class="facility-info">
                <?php if (!empty($facility['logo_path']) && file_exists($facility['logo_path'])): ?>
                <div style="text-align: center; margin-bottom: 10px;">
                    <img src="<?php echo htmlspecialchars($facility['logo_path']); ?>" alt="Facility Logo" style="max-width: 100px; max-height: 60px;">
                </div>
                <?php endif; ?>
                
                <div class="facility-name">
                    <?php echo htmlspecialchars($facility['facility_name']); ?>
                </div>
                
                <div class="facility-details">
                    <?php if (!empty($facility['facility_address'])): ?>
                    <div><?php echo htmlspecialchars($facility['facility_address']); ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($facility['facility_phone'])): ?>
                    <div>Tel: <?php echo htmlspecialchars($facility['facility_phone']); ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($facility['facility_email'])): ?>
                    <div>Email: <?php echo htmlspecialchars($facility['facility_email']); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Receipt Header -->
            <div class="receipt-header">
                <div class="receipt-title">OFFICIAL PAYMENT RECEIPT</div>
                <div class="receipt-subtitle">Receipt No: <?php echo htmlspecialchars($payment['payment_number']); ?></div>
            </div>
            
            <!-- Patient Information -->
            <div class="patient-info-box">
                <div class="receipt-row">
                    <span class="info-label">Patient Name:</span>
                    <span><?php echo htmlspecialchars($payment['patient_name']); ?></span>
                </div>
                <div class="receipt-row">
                    <span class="info-label">Patient ID:</span>
                    <span><?php echo htmlspecialchars($payment['patient_number']); ?></span>
                </div>
                <?php if (!empty($payment['patient_phone'])): ?>
                <div class="receipt-row">
                    <span class="info-label">Phone:</span>
                    <span><?php echo htmlspecialchars($payment['patient_phone']); ?></span>
                </div>
                <?php endif; ?>
                <div class="receipt-row">
                    <span class="info-label">Receipt Date:</span>
                    <span><?php echo date('d/m/Y H:i:s', strtotime($payment['created_at'])); ?></span>
                </div>
            </div>
            
            <div class="receipt-divider"></div>
            
            <!-- Payment Details -->
            <div class="receipt-details">
                <div class="receipt-row">
                    <span class="info-label">Payment Method:</span>
                    <span><?php echo strtoupper(htmlspecialchars($payment['payment_method'])); ?></span>
                </div>
                
                <?php if (!empty($payment['payment_reference'])): ?>
                <div class="receipt-row">
                    <span class="info-label">Reference:</span>
                    <span><?php echo htmlspecialchars($payment['payment_reference']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($payment['received_by_name'])): ?>
                <div class="receipt-row">
                    <span class="info-label">Processed By:</span>
                    <span><?php echo htmlspecialchars($payment['received_by_name']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="receipt-divider"></div>
            
            <!-- Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_amount = 0;
                    foreach ($allocations as $item): 
                        $total_amount += $item['allocated_amount'];
                        
                        // Determine badge class based on service type
                        $service_type = strtolower($item['service_type']);
                        if ($service_type === 'bed_charge' || $service_type === 'admission') {
                            $badge_class = 'badge-admission';
                            $badge_text = 'ADM';
                        } elseif ($service_type === 'consultation') {
                            $badge_class = 'badge-consultation';
                            $badge_text = 'CON';
                        } elseif ($service_type === 'pharmacy') {
                            $badge_class = 'badge-pharmacy';
                            $badge_text = 'PHA';
                        } elseif ($service_type === 'lab') {
                            $badge_class = 'badge-lab';
                            $badge_text = 'LAB';
                        } else {
                            $badge_class = 'badge-general';
                            $badge_text = 'ADMISSION - BED TOTAL CHARGE';
                        }
                    ?>
                    <tr>
                        <td>
                            <?php 
                            // Display service description or fallback to service type
                            if (!empty($item['service_description'])) {
                                echo htmlspecialchars($item['service_description']);
                            } else {
                                // Fallback - format service type nicely
                                $formatted_type = ucwords(str_replace('_', ' ', $item['service_type']));
                                echo htmlspecialchars($formatted_type);
                            }
                            ?>
                            <span class="service-badge <?php echo $badge_class; ?>">
                                <?php echo $badge_text; ?>
                            </span>
                        </td>
                        <td class="text-right">
                            Ksh <?php echo number_format($item['allocated_amount'], 2); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (!empty($payment['notes'])): ?>
                    <tr>
                        <td colspan="2" style="font-size: 11px; color: #555; padding-top: 10px; border-bottom: none;">
                            <strong>Notes:</strong> <?php echo htmlspecialchars($payment['notes']); ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr style="background: #f8fafc;">
                        <td><strong>TOTAL PAID</strong></td>
                        <td class="text-right">
                            <strong>Ksh <?php echo number_format($total_amount, 2); ?></strong>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Amount in Words -->
            <div style="margin: 15px 0; padding: 10px; border: 1px dashed #e5e7eb; background: #f9fafb; border-radius: 5px;">
                <strong>Amount in Words:</strong><br>
                <?php echo amountToWords($total_amount); ?> Kenya Shillings Only
            </div>
            
            <!-- Thank You Message -->
            <div style="text-align: center; margin: 20px 0; padding: 15px; background: #f0fdf4; border-radius: 8px; border: 1px solid #d1fae5;">
                <div style="font-size: 16px; font-weight: bold; color: #059669;">
                    <i class="fas fa-check-circle"></i> THANK YOU FOR YOUR PAYMENT!
                </div>
                <div style="font-size: 12px; color: #666; margin-top: 5px;">
                    This receipt confirms your payment has been received
                </div>
            </div>
            
            <!-- Barcode Area -->
            <div style="text-align: center; margin: 20px 0; padding: 15px; border: 1px dashed #e5e7eb; border-radius: 5px;">
                <div style="font-family: monospace; font-size: 18px; letter-spacing: 2px; margin-bottom: 5px;">
                    *<?php echo htmlspecialchars($payment['payment_number']); ?>*
                </div>
                <div style="font-size: 10px; color: #666;">
                    Transaction ID: <?php echo htmlspecialchars($payment['payment_number']); ?>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="receipt-footer">
                <div style="margin-bottom: 5px;">
                    Generated on: <?php echo date('d/m/Y H:i:s'); ?>
                </div>
                <div style="color: #999; font-size: 9px; margin-top: 10px;">
                    For inquiries, contact: <?php echo htmlspecialchars($facility['facility_phone']); ?>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons no-print">
            <button class="btn btn-secondary" onclick="window.location.href='billing_dashboard.php'">
                <i class="fas fa-arrow-left"></i> Back
            </button>
            <button class="btn btn-primary" onclick="printReceipt()">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            <button class="btn btn-secondary" onclick="closeWindow()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>

    <script>
        function printReceipt() {
            window.print();
        }

        function closeWindow() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.close();
            }
        }
    </script>
</body>
</html>

<?php
// Function to convert amount to words
function amountToWords($number) {
    $decimal = round($number - ($no = floor($number)), 2) * 100;
    $hundred = null;
    $digits_length = strlen($no);
    $i = 0;
    $str = array();
    $words = array(
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
        5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen',
        14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen',
        18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
        40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy',
        80 => 'Eighty', 90 => 'Ninety'
    );
    $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');
    
    while ($i < $digits_length) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += $divider == 10 ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
            $str[] = ($number < 21) ? $words[$number] . ' ' . $digits[$counter] . $plural . ' ' . $hundred :
                $words[floor($number / 10) * 10] . ' ' . $words[$number % 10] . ' ' . $digits[$counter] . $plural . ' ' . $hundred;
        } else {
            $str[] = null;
        }
    }
    
    $shillings = implode('', array_reverse($str));
    $cents = ($decimal) ? " and " . ($words[$decimal] ? $words[$decimal] . ' Cents' : '') : '';
    
    return ucfirst(trim($shillings)) . $cents;
}
?>
