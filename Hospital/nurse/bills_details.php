<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
include "admin/connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get messages
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Get filter parameters
$patient_id = $_GET['patient_id'] ?? null;
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$bill_type = $_GET['bill_type'] ?? 'all';

// Get patient details if patient_id is provided
$patient_details = null;
if ($patient_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                   p.patient_id as patient_number
            FROM patients p 
            WHERE p.id = ?
        ");
        $stmt->execute([$patient_id]);
        $patient_details = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching patient details: " . $e->getMessage());
    }
}

// Fetch bills based on filters
$bills = [];
$total_revenue = 0;
$total_paid = 0;
$total_outstanding = 0;

try {
    // Base query for hospital bills
    $hospital_query = "
        SELECT 
            b.*,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_id as patient_number,
            p.phone as patient_phone,
            COUNT(bi.id) as item_count,
            GROUP_CONCAT(DISTINCT bi.item_type) as service_types,
            'hospital' as source,
            NULL as products
        FROM billing b
        INNER JOIN patients p ON b.patient_id = p.id
        LEFT JOIN bill_items bi ON b.id = bi.bill_id
        WHERE 1=1
    ";
    
    $params = [];
    $types = '';
    
    if ($patient_id) {
        $hospital_query .= " AND b.patient_id = ?";
        $params[] = $patient_id;
        $types .= 'i';
    }
    
    if ($date_from) {
        $hospital_query .= " AND DATE(b.bill_date) >= ?";
        $params[] = $date_from;
        $types .= 's';
    }
    
    if ($date_to) {
        $hospital_query .= " AND DATE(b.bill_date) <= ?";
        $params[] = $date_to;
        $types .= 's';
    }
    
    $hospital_query .= " GROUP BY b.id ORDER BY b.bill_date DESC";
    
    $stmt = $pdo->prepare($hospital_query);
    $stmt->execute($params);
    $hospital_bills = $stmt->fetchAll();
    
    // Pharmacy bills
    $pharmacy_bills = [];
    if ($patient_id || (!$patient_id && $bill_type !== 'hospital')) {
        mysqli_select_db($link, "hospital");
        
        $pharmacy_query = "
            SELECT 
                pb.id,
                pb.bill_no as bill_number,
                pb.date as bill_date,
                pb.patient_id,
                pb.CustomerName as patient_name,
                COALESCE(p.patient_id, '') as patient_number,
                COALESCE(p.phone, '') as patient_phone,
                COALESCE(SUM(pbd.total_amount), 0) as total_amount,
                COALESCE(pb.paid_amount, 0) as paid_amount,
                COALESCE(pb.balance_amount, 0) as balance_amount,
                COALESCE(pb.payment_status, 'paid') as status,
                pb.bill_type as payment_method,
                'pharmacy' as source,
                COUNT(pbd.id) as item_count,
                GROUP_CONCAT(DISTINCT CONCAT(pbd.product_name, ' (', pbd.quantity, ' x ', pbd.price, ')') SEPARATOR '; ') as products
            FROM pharmacy_billing pb
            LEFT JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
            LEFT JOIN patients p ON pb.patient_id = p.id
            WHERE 1=1
        ";
        
        $pharmacy_params = [];
        
        if ($patient_id) {
            $pharmacy_query .= " AND pb.patient_id = ?";
            $pharmacy_params[] = $patient_id;
        }
        
        if ($date_from) {
            $pharmacy_query .= " AND DATE(pb.date) >= ?";
            $pharmacy_params[] = $date_from;
        }
        
        if ($date_to) {
            $pharmacy_query .= " AND DATE(pb.date) <= ?";
            $pharmacy_params[] = $date_to;
        }
        
        $pharmacy_query .= " GROUP BY pb.id, pb.paid_amount, pb.balance_amount, pb.payment_status 
                           ORDER BY pb.date DESC";
        
        $pharmacy_stmt = mysqli_prepare($link, $pharmacy_query);
        
        if ($pharmacy_params) {
            $types = str_repeat('s', count($pharmacy_params));
            mysqli_stmt_bind_param($pharmacy_stmt, $types, ...$pharmacy_params);
        }
        
        mysqli_stmt_execute($pharmacy_stmt);
        $result = mysqli_stmt_get_result($pharmacy_stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $pharmacy_bills[] = $row;
        }
    }
    
    // Merge bills based on bill_type filter
    if ($bill_type === 'all' || $bill_type === 'hospital') {
        $bills = array_merge($bills, $hospital_bills);
    }
    if ($bill_type === 'all' || $bill_type === 'pharmacy') {
        $bills = array_merge($bills, $pharmacy_bills);
    }
    
    // Sort by date
    usort($bills, function($a, $b) {
        return strtotime($b['bill_date']) - strtotime($a['bill_date']);
    });
    
    // Calculate totals
    foreach ($bills as $bill) {
        $total_revenue += $bill['total_amount'];
        $total_paid += $bill['paid_amount'];
        $total_outstanding += $bill['balance_amount'];
    }
    
} catch (PDOException $e) {
    error_log("Error fetching bills: " . $e->getMessage());
    $error_message = "Error loading bills data: " . $e->getMessage();
}

// Handle receipt generation
if (isset($_GET['generate_receipt'])) {
    $bill_id = $_GET['bill_id'];
    $source = $_GET['source'];
    
    generateReceipt($bill_id, $source, $pdo, $link);
    exit;
}

// Function to generate receipt
function generateReceipt($bill_id, $source, $pdo, $link) {
    if ($source === 'hospital') {
        // Hospital bill receipt
        $stmt = $pdo->prepare("
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
                bi.item_type
            FROM billing b
            INNER JOIN patients p ON b.patient_id = p.id
            LEFT JOIN bill_items bi ON b.id = bi.bill_id
            WHERE b.id = ?
        ");
        $stmt->execute([$bill_id]);
        $bill_data = $stmt->fetchAll();
        
        if ($bill_data) {
            $main_bill = $bill_data[0];
            $items = $bill_data;
            
            // Generate receipt HTML
            $receipt_html = generateHospitalReceiptHTML($main_bill, $items);
            echo $receipt_html;
        }
    } else {
        // Pharmacy bill receipt
        mysqli_select_db($link, "hospital");
        
        $pharmacy_query = "
            SELECT 
                pb.*,
                pbd.product_name,
                pbd.quantity,
                pbd.price,
                pbd.total_amount as item_total,
                p.patient_id as patient_number,
                p.first_name,
                p.last_name,
                p.phone as patient_phone,
                p.date_of_birth,
                p.gender
            FROM pharmacy_billing pb
            LEFT JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
            LEFT JOIN patients p ON pb.patient_id = p.id
            WHERE pb.id = ?
        ";
        
        $pharmacy_stmt = mysqli_prepare($link, $pharmacy_query);
        mysqli_stmt_bind_param($pharmacy_stmt, "i", $bill_id);
        mysqli_stmt_execute($pharmacy_stmt);
        $result = mysqli_stmt_get_result($pharmacy_stmt);
        
        $pharmacy_data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $pharmacy_data[] = $row;
        }
        
        if ($pharmacy_data) {
            $main_bill = $pharmacy_data[0];
            $items = $pharmacy_data;
            
            // Generate receipt HTML
            $receipt_html = generatePharmacyReceiptHTML($main_bill, $items);
            echo $receipt_html;
        }
    }
}

function generateHospitalReceiptHTML($bill, $items) {
    $patient_name = $bill['patient_name'] ?? 'N/A';
    $patient_id = $bill['patient_number'] ?? 'N/A';
    $bill_number = $bill['bill_number'] ?? 'N/A';
    $bill_date = date('M d, Y g:i A', strtotime($bill['bill_date']));
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Receipt - <?php echo $bill_number; ?></title>
        <style>
            body { font-family: 'Courier New', monospace; margin: 0; padding: 20px; font-size: 12px; }
            .receipt { max-width: 300px; margin: 0 auto; }
            .header { text-align: center; border-bottom: 2px dashed #000; padding-bottom: 10px; margin-bottom: 10px; }
            .title { font-size: 16px; font-weight: bold; margin-bottom: 5px; }
            .subtitle { font-size: 10px; margin-bottom: 10px; }
            .details { margin-bottom: 15px; }
            .row { display: flex; justify-content: space-between; margin-bottom: 3px; }
            .items { margin: 15px 0; }
            .item-row { display: flex; justify-content: space-between; margin-bottom: 5px; border-bottom: 1px dotted #ccc; padding-bottom: 3px; }
            .item-desc { flex: 2; }
            .item-qty { flex: 1; text-align: center; }
            .item-price { flex: 1; text-align: right; }
            .item-total { flex: 1; text-align: right; }
            .divider { border-top: 2px dashed #000; margin: 10px 0; }
            .total { font-weight: bold; font-size: 14px; }
            .footer { text-align: center; margin-top: 20px; font-size: 10px; }
            @media print { body { margin: 0; } .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="receipt">
            <div class="header">
                <div class="title">EQUITY AFIA MEDICAL CENTER</div>
                <div class="subtitle">Hospital Services Receipt</div>
            </div>
            
            <div class="details">
                <div class="row"><span>Receipt No:</span><span><?php echo $bill_number; ?></span></div>
                <div class="row"><span>Date:</span><span><?php echo $bill_date; ?></span></div>
                <div class="row"><span>Patient:</span><span><?php echo $patient_name; ?></span></div>
                <div class="row"><span>Patient ID:</span><span><?php echo $patient_id; ?></span></div>
                <div class="row"><span>Payment Method:</span><span><?php echo strtoupper($bill['payment_method'] ?? 'CASH'); ?></span></div>
            </div>
            
            <div class="divider"></div>
            
            <div class="items">
                <div class="item-row" style="font-weight: bold;">
                    <div class="item-desc">Description</div>
                    <div class="item-qty">Qty</div>
                    <div class="item-price">Price</div>
                    <div class="item-total">Total</div>
                </div>
                <?php foreach ($items as $item): ?>
                <?php if (!empty($item['item_description'])): ?>
                <div class="item-row">
                    <div class="item-desc"><?php echo $item['item_description']; ?></div>
                    <div class="item-qty"><?php echo $item['quantity']; ?></div>
                    <div class="item-price"><?php echo number_format($item['unit_price'], 2); ?></div>
                    <div class="item-total"><?php echo number_format($item['item_total'], 2); ?></div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <div class="divider"></div>
            
            <div class="row">
                <span>Subtotal:</span>
                <span>KSH <?php echo number_format($bill['total_amount'], 2); ?></span>
            </div>
            <div class="row">
                <span>Paid:</span>
                <span>KSH <?php echo number_format($bill['paid_amount'], 2); ?></span>
            </div>
            <?php if ($bill['balance_amount'] > 0): ?>
            <div class="row">
                <span>Balance:</span>
                <span>KSH <?php echo number_format($bill['balance_amount'], 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="row total">
                <span>TOTAL:</span>
                <span>KSH <?php echo number_format($bill['total_amount'], 2); ?></span>
            </div>
            
            <div class="footer">
                <p>Thank you for choosing Equity Afia Medical Center</p>
                <p>For inquiries: +254 700 000 000</p>
                <p>Computer generated receipt</p>
            </div>
            
            <div class="no-print" style="text-align: center; margin-top: 20px;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    Print Receipt
                </button>
                <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
                    Close
                </button>
            </div>
        </div>
        
        <script>
            window.onload = function() {
                window.print();
            };
        </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function generatePharmacyReceiptHTML($bill, $items) {
    $patient_name = $bill['CustomerName'] ?? ($bill['first_name'] . ' ' . $bill['last_name'] ?? 'N/A');
    $patient_id = $bill['patient_number'] ?? 'N/A';
    $bill_number = $bill['bill_no'] ?? 'N/A';
    $bill_date = date('M d, Y g:i A', strtotime($bill['date']));
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Receipt - <?php echo $bill_number; ?></title>
        <style>
            body { font-family: 'Courier New', monospace; margin: 0; padding: 20px; font-size: 12px; }
            .receipt { max-width: 300px; margin: 0 auto; }
            .header { text-align: center; border-bottom: 2px dashed #000; padding-bottom: 10px; margin-bottom: 10px; }
            .title { font-size: 16px; font-weight: bold; margin-bottom: 5px; }
            .subtitle { font-size: 10px; margin-bottom: 10px; }
            .details { margin-bottom: 15px; }
            .row { display: flex; justify-content: space-between; margin-bottom: 3px; }
            .items { margin: 15px 0; }
            .item-row { display: flex; justify-content: space-between; margin-bottom: 5px; border-bottom: 1px dotted #ccc; padding-bottom: 3px; }
            .item-desc { flex: 2; }
            .item-qty { flex: 1; text-align: center; }
            .item-price { flex: 1; text-align: right; }
            .item-total { flex: 1; text-align: right; }
            .divider { border-top: 2px dashed #000; margin: 10px 0; }
            .total { font-weight: bold; font-size: 14px; }
            .footer { text-align: center; margin-top: 20px; font-size: 10px; }
            @media print { body { margin: 0; } .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="receipt">
            <div class="header">
                <div class="title">Equity Afia Medical Center Pharmacy</div>
                <div class="subtitle">Pharmacy Receipt</div>
            </div>
            
            <div class="details">
                <div class="row"><span>Receipt No:</span><span><?php echo $bill_number; ?></span></div>
                <div class="row"><span>Date:</span><span><?php echo $bill_date; ?></span></div>
                <div class="row"><span>Patient:</span><span><?php echo $patient_name; ?></span></div>
                <div class="row"><span>Patient ID:</span><span><?php echo $patient_id; ?></span></div>
                <div class="row"><span>Payment Method:</span><span><?php echo strtoupper($bill['bill_type'] ?? 'CASH'); ?></span></div>
            </div>
            
            <div class="divider"></div>
            
            <div class="items">
                <div class="item-row" style="font-weight: bold;">
                    <div class="item-desc">Product</div>
                    <div class="item-qty">Qty</div>
                    <div class="item-price">Price</div>
                    <div class="item-total">Total</div>
                </div>
                <?php foreach ($items as $item): ?>
                <?php if (!empty($item['product_name'])): ?>
                <div class="item-row">
                    <div class="item-desc"><?php echo $item['product_name']; ?></div>
                    <div class="item-qty"><?php echo $item['quantity']; ?></div>
                    <div class="item-price"><?php echo number_format($item['price'], 2); ?></div>
                    <div class="item-total"><?php echo number_format($item['item_total'], 2); ?></div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <div class="divider"></div>
            
            <div class="row">
                <span>Subtotal:</span>
                <span>KSH <?php echo number_format($bill['total_amount'], 2); ?></span>
            </div>
            <div class="row">
                <span>Paid:</span>
                <span>KSH <?php echo number_format($bill['paid_amount'] ?? $bill['total_amount'], 2); ?></span>
            </div>
            <?php if (($bill['balance_amount'] ?? 0) > 0): ?>
            <div class="row">
                <span>Balance:</span>
                <span>KSH <?php echo number_format($bill['balance_amount'], 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="row total">
                <span>TOTAL:</span>
                <span>KSH <?php echo number_format($bill['total_amount'], 2); ?></span>
            </div>
            
            <div class="footer">
                <p>Thank you for choosing Equity Afia Medical Center</p>
                <p>For inquiries: +254 700 000 000</p>
                <p>Computer generated receipt</p>
            </div>
            
            <div class="no-print" style="text-align: center; margin-top: 20px;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    Print Receipt
                </button>
                <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
                    Close
                </button>
            </div>
        </div>
        
        <script>
            window.onload = function() {
                window.print();
            };
        </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bills History & Details - Equity Afia Medical Center</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 260px;
            padding-left: 250px;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 1.875rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
        }
        
        .filters-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-success {
            background: #059669;
            color: white;
        }
        
        .btn-info {
            background: #0ea5e9;
            color: white;
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
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.875rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-value.revenue { color: #059669; }
        .stat-value.paid { color: #3b82f6; }
        .stat-value.outstanding { color: #dc2626; }
        
        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .bills-table {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        
        .table-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
        }
        
        .table-container {
            overflow-x: auto;
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
        
        .status-paid { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-partial { background: #dbeafe; color: #1e40af; }
        
        .source-badge {
            padding: 0.25rem 0.5rem;
            background: #dbeafe;
            color: #1e40af;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .source-badge.pharmacy {
            background: #dcfce7;
            color: #166534;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
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
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
        <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>
        
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Bills History & Details
                </h1>
                <p class="page-subtitle">View and manage patient billing history</p>
            </div>
            <div>
                <a href="billing_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-card">
            <form method="GET">
                <div class="filters-grid">
                    <div class="form-group">
                        <label class="form-label">Patient Search</label>
                        <input type="text" 
                               name="patient_id" 
                               class="form-control" 
                               placeholder="Enter Patient ID"
                               value="<?php echo htmlspecialchars($patient_id ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Date From</label>
                        <input type="date" 
                               name="date_from" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Date To</label>
                        <input type="date" 
                               name="date_to" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Bill Type</label>
                        <select name="bill_type" class="form-control">
                            <option value="all" <?php echo $bill_type === 'all' ? 'selected' : ''; ?>>All Bills</option>
                            <option value="hospital" <?php echo $bill_type === 'hospital' ? 'selected' : ''; ?>>Hospital Services</option>
                            <option value="pharmacy" <?php echo $bill_type === 'pharmacy' ? 'selected' : ''; ?>>Pharmacy</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter Bills
                    </button>
                    <a href="bills_details.php" class="btn btn-secondary">
                        <i class="fas fa-refresh"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>
        
        <?php if ($patient_details): ?>
        <!-- Patient Information -->
        <div class="filters-card">
            <h3 style="margin: 0 0 1rem 0; color: #111827;">
                <i class="fas fa-user"></i> Patient Information
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div>
                    <strong>Name:</strong> <?php echo htmlspecialchars($patient_details['patient_name']); ?>
                </div>
                <div>
                    <strong>Patient ID:</strong> <?php echo htmlspecialchars($patient_details['patient_number']); ?>
                </div>
                <div>
                    <strong>Phone:</strong> <?php echo htmlspecialchars($patient_details['phone']); ?>
                </div>
                <div>
                    <strong>Email:</strong> <?php echo htmlspecialchars($patient_details['email'] ?? 'N/A'); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value revenue">KSH <?php echo number_format($total_revenue, 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-value paid">KSH <?php echo number_format($total_paid, 2); ?></div>
                <div class="stat-label">Total Paid</div>
            </div>
            <div class="stat-card">
                <div class="stat-value outstanding">KSH <?php echo number_format($total_outstanding, 2); ?></div>
                <div class="stat-label">Total Outstanding</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($bills); ?></div>
                <div class="stat-label">Total Bills</div>
            </div>
        </div>
        
        <!-- Bills Table -->
        <div class="bills-table">
            <div class="table-header">
                <h2 class="table-title">
                    <i class="fas fa-list"></i>
                    Billing History
                </h2>
            </div>
            
            <div class="table-container">
                <?php if (count($bills) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Bill Number</th>
                            <th>Date</th>
                            <th>Patient</th>
                            <th>Type</th>
                            <th>Total Amount</th>
                            <th>Paid Amount</th>
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
                                <span class="source-badge <?php echo $bill['source']; ?>">
                                    <?php echo $bill['source']; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($bill['bill_date'])); ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($bill['patient_name']); ?></div>
                                <small style="color: #6b7280;">ID: <?php echo htmlspecialchars($bill['patient_number']); ?></small>
                            </td>
                            <td>
                                <?php if ($bill['source'] === 'pharmacy'): ?>
                                Pharmacy
                                <?php else: ?>
                                <?php 
                                $service_types = $bill['service_types'] ? explode(',', $bill['service_types']) : [];
                                echo implode(', ', array_slice($service_types, 0, 2));
                                if (count($service_types) > 2) echo '...';
                                ?>
                                <?php endif; ?>
                            </td>
                            <td><strong>KSH <?php echo number_format($bill['total_amount'], 2); ?></strong></td>
                            <td>KSH <?php echo number_format($bill['paid_amount'], 2); ?></td>
                            <td>
                                <?php if ($bill['balance_amount'] > 0): ?>
                                <span style="color: #dc2626; font-weight: 600;">
                                    KSH <?php echo number_format($bill['balance_amount'], 2); ?>
                                </span>
                                <?php else: ?>
                                <span style="color: #059669;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $bill['status']; ?>">
                                    <?php echo ucfirst($bill['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?generate_receipt=1&bill_id=<?php echo $bill['id']; ?>&source=<?php echo $bill['source']; ?>&patient_id=<?php echo $patient_id; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&bill_type=<?php echo $bill_type; ?>" 
                                       target="_blank"
                                       class="btn btn-info" 
                                       style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                        <i class="fas fa-receipt"></i> Receipt
                                    </a>
                                    <?php if ($bill['source'] === 'hospital'): ?>
                                    <a href="patient_billing.php?id=<?php echo $bill['patient_id']; ?>" 
                                       class="btn btn-success" 
                                       style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-invoice"></i>
                    <h3>No Bills Found</h3>
                    <p>No bills match your current filters.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>