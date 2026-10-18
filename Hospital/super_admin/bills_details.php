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
$group_by = $_GET['group_by'] ?? 'patient_date'; // Group by patient and date
$status_filter = $_GET['status'] ?? 'all'; // Added status filter
$source_filter = $_GET['source'] ?? 'all'; // Added source filter

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
$grouped_bills = [];
$total_revenue = 0;
$total_paid = 0;
$total_outstanding = 0;
$bills = [];

try {
    // Base query for hospital bills - IMPROVED TO MATCH UNIFIED PAYMENT LOGIC
    $hospital_query = "
        SELECT 
            b.*,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_id as patient_number,
            p.phone as patient_phone,
            p.email as patient_email,
            DATE(b.bill_date) as billing_date,
            COUNT(bi.id) as item_count,
            GROUP_CONCAT(DISTINCT 
                CASE bi.item_type 
                    WHEN 'consultation' THEN 'Consultation'
                    WHEN 'bed_charge' THEN 'Admission'
                    WHEN 'lab_test' THEN 'Laboratory'
                    WHEN 'procedure' THEN 'Procedure'
                    ELSE bi.item_type 
                END 
                ORDER BY bi.item_type SEPARATOR ', '
            ) as service_types,
            GROUP_CONCAT(DISTINCT bi.item_description ORDER BY bi.id SEPARATOR '; ') as item_descriptions,
            'hospital' as source,
            NULL as products,
            CASE 
                WHEN b.balance_amount > 0 THEN 'partial'
                WHEN b.paid_amount >= b.total_amount THEN 'paid'
                WHEN b.paid_amount > 0 THEN 'partial'
                ELSE 'pending'
            END as display_status
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
    
    // Apply status filter
    if ($status_filter !== 'all') {
        if ($status_filter === 'outstanding') {
            $hospital_query .= " AND b.balance_amount > 0";
        } elseif ($status_filter === 'paid') {
            $hospital_query .= " AND b.balance_amount = 0 AND b.paid_amount > 0";
        } elseif ($status_filter === 'pending') {
            $hospital_query .= " AND (b.paid_amount = 0 OR b.paid_amount IS NULL)";
        }
    }
    
    $hospital_query .= " GROUP BY b.id ORDER BY b.bill_date DESC";
    
    $stmt = $pdo->prepare($hospital_query);
    $stmt->execute($params);
    $hospital_bills = $stmt->fetchAll();
    
    // Pharmacy bills - IMPROVED QUERY
    $pharmacy_bills = [];
    if (($source_filter === 'all' || $source_filter === 'pharmacy') && ($bill_type === 'all' || $bill_type === 'pharmacy')) {
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
                COALESCE(p.email, '') as patient_email,
                DATE(pb.date) as billing_date,
                COALESCE(pb.total_amount, 0) as total_amount,
                COALESCE(pb.paid_amount, 0) as paid_amount,
                COALESCE(pb.balance_amount, pb.total_amount - COALESCE(pb.paid_amount, 0)) as balance_amount,
                CASE 
                    WHEN COALESCE(pb.balance_amount, pb.total_amount - COALESCE(pb.paid_amount, 0)) > 0 THEN 'partial'
                    WHEN COALESCE(pb.paid_amount, 0) >= COALESCE(pb.total_amount, 0) THEN 'paid'
                    WHEN COALESCE(pb.paid_amount, 0) > 0 THEN 'partial'
                    ELSE 'pending'
                END as status,
                pb.bill_type as payment_method,
                'pharmacy' as source,
                'pharmacy' as display_status,
                COUNT(pbd.id) as item_count,
                GROUP_CONCAT(DISTINCT CONCAT(pbd.product_name, ' (', pbd.quantity, ' x ', FORMAT(pbd.price, 2), ')') SEPARATOR '; ') as products
            FROM pharmacy_billing pb
            LEFT JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
            LEFT JOIN patients p ON pb.patient_id = p.id
            WHERE 1=1
        ";
        
        $pharmacy_params = [];
        $pharmacy_param_types = '';
        
        if ($patient_id) {
            $pharmacy_query .= " AND (pb.patient_id = ? OR pb.CustomerName LIKE CONCAT('%', (SELECT CONCAT(first_name, ' ', last_name) FROM patients WHERE id = ?), '%'))";
            $pharmacy_params[] = $patient_id;
            $pharmacy_params[] = $patient_id;
            $pharmacy_param_types .= 'ii';
        }
        
        if ($date_from) {
            $pharmacy_query .= " AND DATE(pb.date) >= ?";
            $pharmacy_params[] = $date_from;
            $pharmacy_param_types .= 's';
        }
        
        if ($date_to) {
            $pharmacy_query .= " AND DATE(pb.date) <= ?";
            $pharmacy_params[] = $date_to;
            $pharmacy_param_types .= 's';
        }
        
        // Apply status filter
        if ($status_filter !== 'all') {
            if ($status_filter === 'outstanding') {
                $pharmacy_query .= " AND COALESCE(pb.balance_amount, pb.total_amount - COALESCE(pb.paid_amount, 0)) > 0";
            } elseif ($status_filter === 'paid') {
                $pharmacy_query .= " AND COALESCE(pb.balance_amount, pb.total_amount - COALESCE(pb.paid_amount, 0)) = 0 AND COALESCE(pb.paid_amount, 0) > 0";
            } elseif ($status_filter === 'pending') {
                $pharmacy_query .= " AND (COALESCE(pb.paid_amount, 0) = 0 OR pb.paid_amount IS NULL)";
            }
        }
        
        $pharmacy_query .= " GROUP BY pb.id, pb.paid_amount, pb.balance_amount, pb.payment_status 
                           HAVING 1=1";
        
        // Apply source filter if specified
        if ($source_filter !== 'all' && $source_filter !== 'pharmacy') {
            $pharmacy_query .= " AND 1=0"; // Exclude pharmacy if not selected
        }
        
        $pharmacy_query .= " ORDER BY pb.date DESC";
        
        if (!empty($pharmacy_params)) {
            $pharmacy_stmt = mysqli_prepare($link, $pharmacy_query);
            mysqli_stmt_bind_param($pharmacy_stmt, $pharmacy_param_types, ...$pharmacy_params);
            mysqli_stmt_execute($pharmacy_stmt);
            $result = mysqli_stmt_get_result($pharmacy_stmt);
        } else {
            $result = mysqli_query($link, $pharmacy_query);
        }
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $pharmacy_bills[] = $row;
            }
        } else {
            error_log("Pharmacy query error: " . mysqli_error($link));
        }
    }
    
    // Lab orders - IMPROVED QUERY
    $lab_bills = [];
    if (($source_filter === 'all' || $source_filter === 'lab') && ($bill_type === 'all' || $bill_type === 'lab')) {
        $lab_query = "
            SELECT 
                lo.id,
                lo.order_number as bill_number,
                lo.order_date as bill_date,
                lo.patient_id,
                CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                p.patient_id as patient_number,
                p.phone as patient_phone,
                p.email as patient_email,
                DATE(lo.order_date) as billing_date,
                lo.total_amount,
                COALESCE(lo.paid_amount, 0) as paid_amount,
                (lo.total_amount - COALESCE(lo.paid_amount, 0)) as balance_amount,
                lo.payment_status,
                CASE 
                    WHEN lo.payment_status = 'paid' THEN 'paid'
                    WHEN lo.payment_status = 'partial' THEN 'partial'
                    WHEN lo.paid_amount >= lo.total_amount THEN 'paid'
                    WHEN lo.paid_amount > 0 THEN 'partial'
                    ELSE 'pending'
                END as status,
                'cash' as payment_method,
                'lab' as source,
                CASE 
                    WHEN lo.payment_status = 'paid' THEN 'paid'
                    WHEN lo.payment_status = 'partial' THEN 'partial'
                    WHEN lo.paid_amount >= lo.total_amount THEN 'paid'
                    WHEN lo.paid_amount > 0 THEN 'partial'
                    ELSE 'pending'
                END as display_status,
                COUNT(loi.id) as item_count,
                GROUP_CONCAT(DISTINCT loi.test_name ORDER BY loi.id SEPARATOR '; ') as item_descriptions,
                'Laboratory' as service_types,
                NULL as products
            FROM lab_orders lo
            INNER JOIN patients p ON lo.patient_id = p.id
            LEFT JOIN lab_order_items loi ON lo.id = loi.lab_order_id
            WHERE 1=1
        ";
        
        $lab_params = [];
        
        if ($patient_id) {
            $lab_query .= " AND lo.patient_id = ?";
            $lab_params[] = $patient_id;
        }
        
        if ($date_from) {
            $lab_query .= " AND DATE(lo.order_date) >= ?";
            $lab_params[] = $date_from;
        }
        
        if ($date_to) {
            $lab_query .= " AND DATE(lo.order_date) <= ?";
            $lab_params[] = $date_to;
        }
        
        // Apply status filter
        if ($status_filter !== 'all') {
            if ($status_filter === 'outstanding') {
                $lab_query .= " AND (lo.total_amount - COALESCE(lo.paid_amount, 0)) > 0";
            } elseif ($status_filter === 'paid') {
                $lab_query .= " AND (lo.total_amount - COALESCE(lo.paid_amount, 0)) = 0 AND COALESCE(lo.paid_amount, 0) > 0";
            } elseif ($status_filter === 'pending') {
                $lab_query .= " AND (COALESCE(lo.paid_amount, 0) = 0 OR lo.paid_amount IS NULL)";
            }
        }
        
        // Apply source filter if specified
        if ($source_filter !== 'all' && $source_filter !== 'lab') {
            $lab_query .= " AND 1=0"; // Exclude lab if not selected
        }
        
        $lab_query .= " GROUP BY lo.id ORDER BY lo.order_date DESC";
        
        $stmt = $pdo->prepare($lab_query);
        $stmt->execute($lab_params);
        $lab_bills = $stmt->fetchAll();
    }
    
    // Consultation appointments (unbilled consultations)
    $consultation_bills = [];
    if ($patient_id && ($status_filter === 'all' || $status_filter === 'outstanding') && ($source_filter === 'all' || $source_filter === 'hospital')) {
        $consultation_query = "
            SELECT 
                a.id,
                CONCAT('CONS-', a.appointment_number) as bill_number,
                a.appointment_date as bill_date,
                a.patient_id,
                CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                p.patient_id as patient_number,
                p.phone as patient_phone,
                p.email as patient_email,
                DATE(a.appointment_date) as billing_date,
                a.consultation_fee as total_amount,
                0 as paid_amount,
                a.consultation_fee as balance_amount,
                'pending' as status,
                'pending' as display_status,
                'cash' as payment_method,
                'consultation' as source,
                'Consultation' as service_types,
                CONCAT('Consultation - ', d.specialization) as item_descriptions,
                1 as item_count,
                NULL as products
            FROM appointments a
            INNER JOIN patients p ON a.patient_id = p.id
            INNER JOIN doctors d ON a.doctor_id = d.id
            WHERE a.patient_id = ?
            AND a.status = 'completed'
            AND a.consultation_fee > 0
            AND NOT EXISTS (
                SELECT 1 FROM bill_items bi 
                WHERE bi.item_type = 'consultation'
                AND bi.item_description LIKE CONCAT('%Appointment #', a.appointment_number, '%')
                AND bi.bill_id IN (
                    SELECT id FROM billing 
                    WHERE patient_id = a.patient_id 
                    AND status = 'paid'
                )
            )
        ";
        
        if ($date_from) {
            $consultation_query .= " AND DATE(a.appointment_date) >= ?";
        }
        
        if ($date_to) {
            $consultation_query .= " AND DATE(a.appointment_date) <= ?";
        }
        
        $consultation_query .= " ORDER BY a.appointment_date DESC";
        
        $cons_params = [$patient_id];
        if ($date_from) $cons_params[] = $date_from;
        if ($date_to) $cons_params[] = $date_to;
        
        $stmt = $pdo->prepare($consultation_query);
        $stmt->execute($cons_params);
        $consultation_bills = $stmt->fetchAll();
    }
    
    // Merge bills based on bill_type filter
    if (($bill_type === 'all' || $bill_type === 'hospital') && ($source_filter === 'all' || $source_filter === 'hospital' || $source_filter === 'consultation')) {
        $bills = array_merge($bills, $hospital_bills);
        $bills = array_merge($bills, $consultation_bills);
    }
    if (($bill_type === 'all' || $bill_type === 'pharmacy') && ($source_filter === 'all' || $source_filter === 'pharmacy')) {
        $bills = array_merge($bills, $pharmacy_bills);
    }
    if (($bill_type === 'all' || $bill_type === 'lab') && ($source_filter === 'all' || $source_filter === 'lab')) {
        $bills = array_merge($bills, $lab_bills);
    }
    
    // Sort by date
    usort($bills, function($a, $b) {
        return strtotime($b['bill_date']) - strtotime($a['bill_date']);
    });
    
    // Calculate totals
    foreach ($bills as $bill) {
        $total_revenue += (float)$bill['total_amount'];
        $total_paid += (float)$bill['paid_amount'];
        $total_outstanding += (float)$bill['balance_amount'];
    }
    
    // Group bills by patient and date (or other criteria)
    if ($group_by === 'patient_date') {
        foreach ($bills as $bill) {
            $key = $bill['patient_id'] . '_' . $bill['billing_date'];
            
            if (!isset($grouped_bills[$key])) {
                $grouped_bills[$key] = [
                    'patient_id' => $bill['patient_id'],
                    'patient_name' => $bill['patient_name'],
                    'patient_number' => $bill['patient_number'],
                    'patient_phone' => $bill['patient_phone'],
                    'patient_email' => $bill['patient_email'],
                    'billing_date' => $bill['billing_date'],
                    'total_amount' => 0,
                    'paid_amount' => 0,
                    'balance_amount' => 0,
                    'bills' => [],
                    'service_types' => [],
                    'sources' => [],
                    'display_status' => 'paid'
                ];
            }
            
            // Add bill to group
            $grouped_bills[$key]['bills'][] = $bill;
            
            // Update totals
            $grouped_bills[$key]['total_amount'] += (float)$bill['total_amount'];
            $grouped_bills[$key]['paid_amount'] += (float)$bill['paid_amount'];
            $grouped_bills[$key]['balance_amount'] += (float)$bill['balance_amount'];
            
            // Track service types
            if (!empty($bill['service_types'])) {
                $types = explode(',', $bill['service_types']);
                foreach ($types as $type) {
                    $clean_type = trim($type);
                    if (!empty($clean_type) && !in_array($clean_type, $grouped_bills[$key]['service_types'])) {
                        $grouped_bills[$key]['service_types'][] = $clean_type;
                    }
                }
            }
            
            // Track sources
            if (!in_array($bill['source'], $grouped_bills[$key]['sources'])) {
                $grouped_bills[$key]['sources'][] = $bill['source'];
            }
            
            // Determine overall status
            if ($bill['balance_amount'] > 0) {
                $grouped_bills[$key]['display_status'] = 'partial';
            } elseif ((float)$bill['balance_amount'] === 0.0 && (float)$bill['paid_amount'] === 0.0) {
                $grouped_bills[$key]['display_status'] = 'pending';
            }
        }
    } else {
        // Default: group by individual bill
        foreach ($bills as $bill) {
            $key = $bill['id'] . '_' . $bill['source'];
            $grouped_bills[$key] = [
                'patient_id' => $bill['patient_id'],
                'patient_name' => $bill['patient_name'],
                'patient_number' => $bill['patient_number'],
                'patient_phone' => $bill['patient_phone'],
                'patient_email' => $bill['patient_email'],
                'billing_date' => $bill['billing_date'],
                'total_amount' => (float)$bill['total_amount'],
                'paid_amount' => (float)$bill['paid_amount'],
                'balance_amount' => (float)$bill['balance_amount'],
                'bills' => [$bill],
                'service_types' => !empty($bill['service_types']) ? explode(',', $bill['service_types']) : [],
                'sources' => [$bill['source']],
                'display_status' => $bill['display_status'] ?? $bill['status'] ?? 'pending'
            ];
        }
    }
    
    // Sort grouped bills by date
    usort($grouped_bills, function($a, $b) {
        return strtotime($b['billing_date']) - strtotime($a['billing_date']);
    });
    
} catch (PDOException $e) {
    error_log("Error fetching bills: " . $e->getMessage());
    $error_message = "Error loading bills data: " . $e->getMessage();
}

// Handle receipt generation for group
if (isset($_GET['generate_group_receipt'])) {
    $patient_id = $_GET['patient_id'];
    $billing_date = $_GET['billing_date'];
    generateGroupReceipt($patient_id, $billing_date, $pdo, $link);
    exit;
}

function generateGroupReceipt($patient_id, $billing_date, $pdo, $link) {
    // Get all bills for this patient on this date
    $all_bills = [];
    
    // Hospital bills
    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_id as patient_number,
            p.phone as patient_phone,
            bi.item_description,
            bi.quantity,
            bi.unit_price,
            bi.total_amount as item_total,
            bi.item_type,
            'hospital' as source
        FROM billing b
        INNER JOIN patients p ON b.patient_id = p.id
        LEFT JOIN bill_items bi ON b.id = bi.bill_id
        WHERE b.patient_id = ? 
        AND DATE(b.bill_date) = ?
        AND b.paid_amount > 0
    ");
    $stmt->execute([$patient_id, $billing_date]);
    $hospital_bills = $stmt->fetchAll();
    $all_bills = array_merge($all_bills, $hospital_bills);
    
    // Pharmacy bills
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
            'pharmacy' as source,
            'medication' as item_type,
            pbd.product_name as item_description
        FROM pharmacy_billing pb
        LEFT JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
        LEFT JOIN patients p ON pb.patient_id = p.id
        WHERE pb.patient_id = ? 
        AND DATE(pb.date) = ?
        AND pb.paid_amount > 0
    ";
    
    $pharmacy_stmt = mysqli_prepare($link, $pharmacy_query);
    mysqli_stmt_bind_param($pharmacy_stmt, "is", $patient_id, $billing_date);
    mysqli_stmt_execute($pharmacy_stmt);
    $result = mysqli_stmt_get_result($pharmacy_stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $all_bills[] = $row;
    }
    
    // Lab bills
    $stmt = $pdo->prepare("
        SELECT 
            lo.*,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_id as patient_number,
            p.phone as patient_phone,
            loi.test_name as item_description,
            1 as quantity,
            lo.total_amount as unit_price,
            lo.total_amount as item_total,
            'lab_test' as item_type,
            'lab' as source
        FROM lab_orders lo
        INNER JOIN patients p ON lo.patient_id = p.id
        LEFT JOIN lab_order_items loi ON lo.id = loi.lab_order_id
        WHERE lo.patient_id = ? 
        AND DATE(lo.order_date) = ?
        AND lo.paid_amount > 0
    ");
    $stmt->execute([$patient_id, $billing_date]);
    $lab_bills = $stmt->fetchAll();
    $all_bills = array_merge($all_bills, $lab_bills);
    
    if (count($all_bills) > 0) {
        $first_bill = $all_bills[0];
        $total_amount = array_sum(array_column($all_bills, 'total_amount'));
        $paid_amount = array_sum(array_column($all_bills, 'paid_amount'));
        $balance_amount = $total_amount - $paid_amount;
        
        $receipt_html = generateConsolidatedReceiptHTML($first_bill, $all_bills, $total_amount, $paid_amount, $balance_amount);
        echo $receipt_html;
    }
}

function generateConsolidatedReceiptHTML($main_bill, $items, $total_amount, $paid_amount, $balance_amount) {
    $patient_name = $main_bill['patient_name'] ?? 'N/A';
    $patient_id = $main_bill['patient_number'] ?? 'N/A';
    $bill_date = date('M d, Y', strtotime($main_bill['bill_date'] ?? $main_bill['date'] ?? $main_bill['order_date'] ?? 'now'));
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Consolidated Receipt - <?php echo $patient_name; ?></title>
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
            .source-badge { font-size: 8px; padding: 1px 3px; border-radius: 2px; background: #eee; }
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
                <div class="subtitle">Consolidated Receipt</div>
            </div>
            
            <div class="details">
                <div class="row"><span>Date:</span><span><?php echo $bill_date; ?></span></div>
                <div class="row"><span>Patient:</span><span><?php echo $patient_name; ?></span></div>
                <div class="row"><span>Patient ID:</span><span><?php echo $patient_id; ?></span></div>
                <div class="row"><span>Services:</span><span><?php echo count($items); ?> bills consolidated</span></div>
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
                    <div class="item-desc">
                        <?php echo substr($item['item_description'], 0, 25); ?>
                        <span class="source-badge"><?php echo strtoupper($item['source']); ?></span>
                    </div>
                    <div class="item-qty"><?php echo $item['quantity'] ?? 1; ?></div>
                    <div class="item-price"><?php echo number_format($item['unit_price'] ?? $item['price'] ?? 0, 2); ?></div>
                    <div class="item-total"><?php echo number_format($item['item_total'], 2); ?></div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <div class="divider"></div>
            
            <div class="row">
                <span>Subtotal:</span>
                <span>KSH <?php echo number_format($total_amount, 2); ?></span>
            </div>
            <div class="row">
                <span>Paid:</span>
                <span>KSH <?php echo number_format($paid_amount, 2); ?></span>
            </div>
            <?php if ($balance_amount > 0): ?>
            <div class="row">
                <span>Balance:</span>
                <span>KSH <?php echo number_format($balance_amount, 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="row total">
                <span>TOTAL:</span>
                <span>KSH <?php echo number_format($total_amount, 2); ?></span>
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

// Add outstanding payments link functionality
if (isset($_GET['view_outstanding'])) {
    // Redirect to unified payment page for this patient
    if ($patient_id) {
        header("Location: unified_payment.php?patient_id=" . $patient_id);
        exit;
    }
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
        :root {
            --primary: #0891b2;
            --primary-light: #06b6d4;
            --primary-dark: #0e7490;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #134e4a;
            --light: #f0fdfa;
            --gray: #64748b;
        }
        
        .main-content {
            margin-left: 300px;
            padding: 2rem;
            background: var(--light);
            min-height: 100vh;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .page-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filters-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            border: 1px solid rgba(6, 182, 212, 0.1);
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
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            padding: 0.5rem 0.75rem;
            border: 1px solid rgba(6, 182, 212, 0.2);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: var(--gray);
            color: white;
        }
        
        .btn-secondary:hover {
            background: #475569;
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-info {
            background: var(--primary-light);
            color: white;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
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
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            text-align: center;
            border: 1px solid rgba(6, 182, 212, 0.1);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-value {
            font-size: 1.875rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-value.revenue { color: var(--success); }
        .stat-value.paid { color: var(--primary); }
        .stat-value.outstanding { color: var(--danger); }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--gray);
            font-weight: 500;
        }
        
        .bills-table {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid rgba(6, 182, 212, 0.1);
        }
        
        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(6, 182, 212, 0.1);
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .table-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(6, 182, 212, 0.1);
        }
        
        th {
            background: #f8fafc;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        td {
            font-size: 0.875rem;
            color: #374151;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-paid { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-partial { background: #dbeafe; color: #1e40af; }
        
        .source-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .source-badge.hospital {
            background: #dbeafe;
            color: var(--primary);
        }
        
        .source-badge.pharmacy {
            background: #dcfce7;
            color: var(--success);
        }
        
        .source-badge.lab {
            background: #fef3c7;
            color: var(--warning);
        }
        
        .source-badge.consultation {
            background: #f3e8ff;
            color: #8b5cf6;
        }
        
        .service-badges {
            display: flex;
            gap: 0.25rem;
            flex-wrap: wrap;
            margin-top: 0.25rem;
        }
        
        .service-badge {
            padding: 0.125rem 0.375rem;
            border-radius: 0.25rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .service-badge.hospital { background: #dbeafe; color: var(--primary); }
        .service-badge.pharmacy { background: #dcfce7; color: var(--success); }
        .service-badge.lab { background: #fef3c7; color: var(--warning); }
        .service-badge.consultation { background: #f3e8ff; color: #8b5cf6; }
        .service-badge.admission { background: #fce7f3; color: #db2777; }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid;
        }
        
        .alert-success {
            background: #dcfce7;
            color: var(--success);
            border-color: #bbf7d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: var(--danger);
            border-color: #fecaca;
        }
        
        /* Patient group header */
        .patient-group-header {
            background: #f8fafc;
            border: 1px solid rgba(6, 182, 212, 0.2);
            border-radius: 0.75rem;
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }
        
        .patient-group-header:hover {
            background: #f1f5f9;
            border-color: var(--primary-light);
        }
        
        .patient-group-main {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }
        
        .patient-group-info {
            flex: 1;
            min-width: 300px;
        }
        
        .patient-group-name {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .patient-group-details {
            font-size: 0.75rem;
            color: var(--gray);
            margin-top: 0.5rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.5rem;
        }
        
        .patient-group-amount {
            text-align: right;
            min-width: 200px;
        }
        
        .patient-group-total {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .patient-group-status {
            margin-top: 0.5rem;
        }
        
        .bills-count {
            font-size: 0.75rem;
            color: var(--gray);
            margin-top: 0.25rem;
        }
        
        .expandable-bills {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        
        .expandable-bills.expanded {
            max-height: 1000px;
        }
        
        .bill-detail {
            background: white;
            border: 1px solid rgba(6, 182, 212, 0.1);
            border-radius: 0.5rem;
            padding: 0.75rem;
            margin-top: 0.5rem;
            font-size: 0.75rem;
        }
        
        .toggle-bills-btn {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 0.75rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0;
            margin-top: 0.5rem;
        }
        
        .toggle-bills-btn:hover {
            text-decoration: underline;
        }
        
        .outstanding-alert {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .outstanding-alert-content {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                
            }
            
            .patient-group-main {
                flex-direction: column;
            }
            
            .patient-group-amount {
                text-align: left;
                width: 100%;
            }
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
                <p style="color: var(--gray); margin-top: 0.5rem;">View and manage patient billing history</p>
            </div>
            <div>
                <a href="billing_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <!-- Outstanding Payments Alert -->
        <?php if ($patient_id && $total_outstanding > 0): ?>
        <div class="outstanding-alert">
            <div class="outstanding-alert-content">
                <i class="fas fa-exclamation-triangle" style="color: #d97706; font-size: 1.5rem;"></i>
                <div>
                    <strong style="color: #92400e;">Outstanding Payments Detected!</strong>
                    <p style="margin: 0.25rem 0 0 0; color: #92400e;">
                        This patient has KSH <?php echo number_format($total_outstanding, 2); ?> in unpaid balances.
                    </p>
                </div>
            </div>
            <a href="unified_payment.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-warning">
                <i class="fas fa-credit-card"></i> Process Payment
            </a>
        </div>
        <?php endif; ?>
        
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
                            <option value="lab" <?php echo $bill_type === 'lab' ? 'selected' : ''; ?>>Laboratory</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="outstanding" <?php echo $status_filter === 'outstanding' ? 'selected' : ''; ?>>Outstanding Only</option>
                            <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid Only</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending Only</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Source</label>
                        <select name="source" class="form-control">
                            <option value="all" <?php echo $source_filter === 'all' ? 'selected' : ''; ?>>All Sources</option>
                            <option value="hospital" <?php echo $source_filter === 'hospital' ? 'selected' : ''; ?>>Hospital Services</option>
                            <option value="pharmacy" <?php echo $source_filter === 'pharmacy' ? 'selected' : ''; ?>>Pharmacy</option>
                            <option value="lab" <?php echo $source_filter === 'lab' ? 'selected' : ''; ?>>Laboratory</option>
                            <option value="consultation" <?php echo $source_filter === 'consultation' ? 'selected' : ''; ?>>Consultations</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Group By</label>
                        <select name="group_by" class="form-control">
                            <option value="patient_date" <?php echo $group_by === 'patient_date' ? 'selected' : ''; ?>>Patient & Date</option>
                            <option value="individual" <?php echo $group_by === 'individual' ? 'selected' : ''; ?>>Individual Bills</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1rem; flex-wrap: wrap;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter Bills
                    </button>
                    <a href="bills_details.php" class="btn btn-secondary">
                        <i class="fas fa-refresh"></i> Clear Filters
                    </a>
                    <?php if ($patient_id && $total_outstanding > 0): ?>
                    <a href="unified_payment.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-warning">
                        <i class="fas fa-credit-card"></i> Process Payment (KSH <?php echo number_format($total_outstanding, 2); ?>)
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <?php if ($patient_details): ?>
        <!-- Patient Information -->
        <div class="filters-card">
            <h3 style="margin: 0 0 1rem 0; color: var(--dark); display: flex; align-items: center; gap: 0.5rem;">
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
                <?php if ($total_outstanding > 0): ?>
                <div style="grid-column: 1 / -1;">
                    <strong style="color: var(--danger);">Outstanding Balance:</strong> 
                    <span style="color: var(--danger); font-weight: bold;">
                        KSH <?php echo number_format($total_outstanding, 2); ?>
                    </span>
                    <a href="unified_payment.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-danger btn-sm" style="margin-left: 1rem;">
                        <i class="fas fa-credit-card"></i> Pay Now
                    </a>
                </div>
                <?php endif; ?>
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
                <div class="stat-value"><?php echo count($grouped_bills); ?></div>
                <div class="stat-label">
                    <?php echo $group_by === 'patient_date' ? 'Patient Visits' : 'Total Bills'; ?>
                </div>
            </div>
        </div>
        
        <!-- Grouped Bills Table -->
        <div class="bills-table">
            <div class="table-header">
                <h2 class="table-title">
                    <i class="fas fa-list"></i>
                    Billing History
                    <?php if ($group_by === 'patient_date'): ?>
                    <span style="font-size: 0.875rem; color: var(--gray); margin-left: 0.5rem;">
                        (Grouped by Patient & Date)
                    </span>
                    <?php endif; ?>
                </h2>
                <div style="font-size: 0.875rem; color: var(--gray);">
                    Showing <?php echo count($grouped_bills); ?> <?php echo $group_by === 'patient_date' ? 'visits' : 'bills'; ?>
                    <?php if ($total_outstanding > 0): ?>
                    • <span style="color: var(--danger);">KSH <?php echo number_format($total_outstanding, 2); ?> outstanding</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="table-container">
                <?php if (count($grouped_bills) > 0): ?>
                    <?php foreach ($grouped_bills as $index => $group): ?>
                    <div class="patient-group-header" id="group-<?php echo $index; ?>">
                        <div class="patient-group-main">
                            <div class="patient-group-info">
                                <div class="patient-group-name">
                                    <?php echo htmlspecialchars($group['patient_name']); ?>
                                    <span style="font-size: 0.75rem; color: var(--gray);">
                                        (ID: <?php echo htmlspecialchars($group['patient_number']); ?>)
                                    </span>
                                </div>
                                
                                <div class="patient-group-details">
                                    <div>
                                        <i class="far fa-calendar"></i>
                                        <?php echo date('M d, Y', strtotime($group['billing_date'])); ?>
                                    </div>
                                    <div>
                                        <i class="fas fa-phone"></i>
                                        <?php echo htmlspecialchars($group['patient_phone'] ?? 'N/A'); ?>
                                    </div>
                                    <div>
                                        <i class="fas fa-envelope"></i>
                                        <?php echo htmlspecialchars($group['patient_email'] ?? 'N/A'); ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($group['service_types'])): ?>
                                <div class="service-badges" style="margin-top: 0.5rem;">
                                    <?php foreach ($group['service_types'] as $service_type): ?>
                                    <span class="service-badge <?php echo strtolower($service_type); ?>">
                                        <?php echo ucfirst($service_type); ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="bills-count">
                                    <?php echo count($group['bills']); ?> bill(s) on this date
                                    <?php if (!empty($group['sources'])): ?>
                                    • Sources: <?php echo implode(', ', array_map('ucfirst', $group['sources'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="patient-group-amount">
                                <div class="patient-group-total">
                                    KSH <?php echo number_format($group['total_amount'], 2); ?>
                                </div>
                                
                                <div style="font-size: 0.875rem;">
                                    <span style="color: var(--success);">
                                        Paid: KSH <?php echo number_format($group['paid_amount'], 2); ?>
                                    </span>
                                    <?php if ($group['balance_amount'] > 0): ?>
                                    <div style="color: var(--danger); margin-top: 0.25rem; font-weight: 600;">
                                        Balance: KSH <?php echo number_format($group['balance_amount'], 2); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="patient-group-status" style="margin-top: 0.5rem;">
                                    <span class="status-badge status-<?php echo $group['display_status']; ?>">
                                        <?php echo ucfirst($group['display_status']); ?>
                                    </span>
                                </div>
                                
                                <div class="action-buttons" style="margin-top: 0.75rem;">
                                    <?php if (count($group['bills']) > 1): ?>
                                    <button type="button" 
                                            class="toggle-bills-btn" 
                                            onclick="toggleBills(<?php echo $index; ?>)">
                                        <i class="fas fa-chevron-down" id="icon-<?php echo $index; ?>"></i>
                                        View <?php echo count($group['bills']); ?> bills
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (count($group['bills']) > 0): ?>
                                    <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                                        <?php if (count($group['bills']) > 1): ?>
                                        <a href="?generate_group_receipt=1&patient_id=<?php echo $group['patient_id']; ?>&billing_date=<?php echo $group['billing_date']; ?>&patient_id=<?php echo $patient_id ?? ''; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&bill_type=<?php echo $bill_type; ?>&group_by=<?php echo $group_by; ?>&status=<?php echo $status_filter; ?>&source=<?php echo $source_filter; ?>" 
                                           target="_blank"
                                           class="btn btn-info btn-sm">
                                            <i class="fas fa-receipt"></i> Consolidated Receipt
                                        </a>
                                        <?php endif; ?>
                                        
                                        <a href="unified_payment.php?patient_id=<?php echo $group['patient_id']; ?>" 
                                           class="btn btn-warning btn-sm" 
                                           <?php if ($group['balance_amount'] > 0): ?>style="background: var(--danger);"<?php endif; ?>>
                                            <i class="fas fa-credit-card"></i> 
                                            <?php echo $group['balance_amount'] > 0 ? 'Pay Balance' : 'View Patient'; ?>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Expandable bill details -->
                        <?php if (count($group['bills']) > 1): ?>
                        <div class="expandable-bills" id="bills-<?php echo $index; ?>">
                            <?php foreach ($group['bills'] as $bill): ?>
                            <div class="bill-detail">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem;">
                                    <div>
                                        <strong style="color: var(--dark);">
                                            <?php echo htmlspecialchars($bill['bill_number'] ?? 'N/A'); ?>
                                        </strong>
                                        <span class="source-badge <?php echo $bill['source']; ?>" style="margin-left: 0.5rem;">
                                            <?php echo ucfirst($bill['source']); ?>
                                        </span>
                                        <div style="font-size: 0.7rem; color: var(--gray); margin-top: 0.25rem;">
                                            <?php echo date('g:i A', strtotime($bill['bill_date'] ?? 'now')); ?>
                                        </div>
                                    </div>
                                    
                                    <div style="text-align: right;">
                                        <div style="font-weight: 600; color: var(--dark);">
                                            KSH <?php echo number_format($bill['total_amount'], 2); ?>
                                        </div>
                                        <div style="font-size: 0.7rem; color: <?php echo $bill['balance_amount'] > 0 ? 'var(--danger)' : 'var(--success)'; ?>;">
                                            <?php if ($bill['balance_amount'] > 0): ?>
                                            Balance: KSH <?php echo number_format($bill['balance_amount'], 2); ?>
                                            <?php else: ?>
                                            Paid: KSH <?php echo number_format($bill['paid_amount'], 2); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($bill['item_descriptions']) || !empty($bill['products'])): ?>
                                <div style="margin-top: 0.5rem; font-size: 0.7rem; color: var(--gray);">
                                    <?php 
                                    $description = !empty($bill['item_descriptions']) ? $bill['item_descriptions'] : ($bill['products'] ?? '');
                                    if (!empty($description)) {
                                        $items = explode('; ', $description);
                                        echo htmlspecialchars(implode(', ', array_slice($items, 0, 3)));
                                        if (count($items) > 3) echo '...';
                                    }
                                    ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="action-buttons" style="margin-top: 0.5rem;">
                                    <a href="?generate_receipt=1&bill_id=<?php echo $bill['id']; ?>&source=<?php echo $bill['source']; ?>&patient_id=<?php echo $patient_id ?? ''; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&bill_type=<?php echo $bill_type; ?>&group_by=<?php echo $group_by; ?>&status=<?php echo $status_filter; ?>&source=<?php echo $source_filter; ?>" 
                                       target="_blank"
                                       class="btn btn-info btn-sm" 
                                       style="padding: 0.125rem 0.375rem; font-size: 0.7rem;">
                                        <i class="fas fa-receipt"></i> Receipt
                                    </a>
                                    <?php if ($bill['balance_amount'] > 0): ?>
                                    <a href="unified_payment.php?patient_id=<?php echo $bill['patient_id']; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       style="padding: 0.125rem 0.375rem; font-size: 0.7rem;">
                                        <i class="fas fa-credit-card"></i> Pay
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-invoice"></i>
                    <h3>No Bills Found</h3>
                    <p>No bills match your current filters.</p>
                    <?php if ($status_filter === 'outstanding' && $patient_id): ?>
                    <p style="margin-top: 1rem;">
                        <a href="unified_payment.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary">
                            <i class="fas fa-credit-card"></i> Check for Outstanding Payments
                        </a>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleBills(index) {
            const billsDiv = document.getElementById('bills-' + index);
            const icon = document.getElementById('icon-' + index);
            
            if (billsDiv.classList.contains('expanded')) {
                billsDiv.classList.remove('expanded');
                icon.className = 'fas fa-chevron-down';
            } else {
                billsDiv.classList.add('expanded');
                icon.className = 'fas fa-chevron-up';
            }
        }
        
        // Auto-expand if there's only one group
        document.addEventListener('DOMContentLoaded', function() {
            const groups = document.querySelectorAll('.patient-group-header');
            if (groups.length === 1) {
                toggleBills(0);
            }
            
            // Highlight groups with outstanding balances
            groups.forEach((group, index) => {
                const balanceElement = group.querySelector('.patient-group-amount .patient-group-total + div');
                if (balanceElement && balanceElement.textContent.includes('Balance:')) {
                    group.style.borderColor = 'var(--danger)';
                    group.style.background = '#fef2f2';
                }
            });
        });
    </script>
</body>
</html>