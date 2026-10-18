<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
include "admin/connection.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

date_default_timezone_set('Africa/Nairobi');

// Get messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success'], $_SESSION['error']);

// Get today's stats
$today = date('Y-m-d');
$stats = [
    'today_revenue' => 0,
    'pharmacy_revenue' => 0,
    'hospital_revenue' => 0,
    'pending_bills' => 0,
    'total_outstanding' => 0,
    'department_revenue' => [],
    'payment_methods' => []
];

try {
    // Get unified billing revenue (hospital services)
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(paid_amount), 0) as hospital_revenue,
            COALESCE(SUM(balance_amount), 0) as hospital_outstanding,
            COUNT(*) as hospital_bills
        FROM billing 
        WHERE DATE(bill_date) = ?
    ");
    $stmt->execute([$today]);
    $hospital_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['hospital_revenue'] = $hospital_data['hospital_revenue'];
    $stats['hospital_outstanding'] = $hospital_data['hospital_outstanding'];
    $stats['hospital_bills'] = $hospital_data['hospital_bills'];

    // Get pharmacy revenue from integrated system
    mysqli_select_db($link, "hospital");
    $pharmacy_query = "
        SELECT 
            COALESCE(SUM(pbd.total_amount), 0) as pharmacy_revenue,
            COUNT(DISTINCT pb.id) as pharmacy_bills
        FROM pharmacy_billing pb
        INNER JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
        WHERE DATE(pb.date) = '$today'
    ";
    $pharmacy_result = mysqli_query($link, $pharmacy_query);
    if ($pharmacy_result) {
        $pharmacy_row = mysqli_fetch_assoc($pharmacy_result);
        $stats['pharmacy_revenue'] = $pharmacy_row['pharmacy_revenue'] ?? 0;
        $stats['pharmacy_bills'] = $pharmacy_row['pharmacy_bills'] ?? 0;
    }

    // Total revenue
    $stats['today_revenue'] = $stats['hospital_revenue'] + $stats['pharmacy_revenue'];

    // Calculate TOTAL OUTSTANDING from ALL services (like unified_payment.php)
 // Calculate TOTAL OUTSTANDING from ALL services
$total_outstanding_calculated = 0;

try {
    // 1. Hospital bills outstanding
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(balance_amount), 0) as total_outstanding FROM billing WHERE balance_amount > 0");
    $stmt->execute();
    $hospital_outstanding = $stmt->fetchColumn();
    $total_outstanding_calculated += $hospital_outstanding;
    error_log("Hospital Outstanding: " . $hospital_outstanding);

    // 2. Lab orders outstanding
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(lo.total_amount - lo.paid_amount), 0) as lab_outstanding
        FROM lab_orders lo
        WHERE lo.payment_status IN ('unpaid', 'partial')
        AND (lo.total_amount - lo.paid_amount) > 0
    ");
    $stmt->execute();
    $lab_outstanding = $stmt->fetchColumn();
    $total_outstanding_calculated += $lab_outstanding;
    error_log("Lab Outstanding: " . $lab_outstanding);

    // 3. Pharmacy bills outstanding - FIXED QUERY
    mysqli_select_db($link, "hospital");
    
    // First check if we have the required columns
    $check_columns = "SHOW COLUMNS FROM pharmacy_billing LIKE 'balance_amount'";
    $column_result = mysqli_query($link, $check_columns);
    
    if (mysqli_num_rows($column_result) > 0) {
        // Use balance_amount column if it exists
        $pharmacy_query = "SELECT COALESCE(SUM(balance_amount), 0) as pharmacy_outstanding FROM pharmacy_billing WHERE COALESCE(balance_amount, 0) > 0";
    } else {
        // Calculate from total_amount and paid_amount
        $pharmacy_query = "SELECT COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0) as pharmacy_outstanding FROM pharmacy_billing WHERE (total_amount - COALESCE(paid_amount, 0)) > 0";
    }
    
    $pharmacy_result = mysqli_query($link, $pharmacy_query);
    $pharmacy_outstanding = 0;
    if ($pharmacy_result) {
        $pharmacy_row = mysqli_fetch_assoc($pharmacy_result);
        $pharmacy_outstanding = $pharmacy_row['pharmacy_outstanding'] ?? 0;
    }
    $total_outstanding_calculated += $pharmacy_outstanding;
    error_log("Pharmacy Outstanding: " . $pharmacy_outstanding);

    // 4. Consultation fees outstanding
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(a.consultation_fee), 0) as consultation_outstanding
        FROM appointments a
        WHERE a.status = 'completed'
        AND a.consultation_fee > 0
        AND NOT EXISTS (
            SELECT 1 FROM bill_items bi 
            WHERE bi.item_type = 'consultation'
            AND bi.service_date = a.appointment_date
            AND bi.bill_id IN (SELECT id FROM billing WHERE patient_id = a.patient_id)
        )
    ");
    $stmt->execute();
    $consultation_outstanding = $stmt->fetchColumn();
    $total_outstanding_calculated += $consultation_outstanding;
    error_log("Consultation Outstanding: " . $consultation_outstanding);

    // 5. Bed charges outstanding
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(DATEDIFF(CURDATE(), ba.admission_date) * b.price_per_day), 0) as admission_outstanding
        FROM bed_assignments ba
        INNER JOIN beds b ON ba.bed_id = b.id
        INNER JOIN ward w ON b.ward_id = w.id
        WHERE ba.status = 'active'
        AND ba.discharge_date IS NULL
        AND DATEDIFF(CURDATE(), ba.admission_date) > 0
    ");
    $stmt->execute();
    $admission_outstanding = $stmt->fetchColumn();
    $total_outstanding_calculated += $admission_outstanding;
    error_log("Admission Outstanding: " . $admission_outstanding);

    $stats['total_outstanding'] = $total_outstanding_calculated;
    error_log("TOTAL OUTSTANDING: " . $total_outstanding_calculated);

} catch (PDOException $e) {
    error_log("Error calculating outstanding balances: " . $e->getMessage());
    $stats['total_outstanding'] = 0;
}

    // Pending bills count (hospital only for now)
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending_bills FROM billing WHERE balance_amount > 0");
    $stmt->execute();
    $stats['pending_bills'] = $stmt->fetchColumn();

    // Payment methods distribution
    $stmt = $pdo->prepare("
        SELECT 
            payment_method,
            COUNT(*) as transaction_count,
            SUM(paid_amount) as total_amount
        FROM billing 
        WHERE DATE(bill_date) = ?
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ");
    $stmt->execute([$today]);
    $stats['payment_methods'] = $stmt->fetchAll();

    // Department-wise revenue
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(bi.department, 'General') as department,
            SUM(bi.total_amount) as revenue,
            COUNT(DISTINCT b.id) as bill_count
        FROM bill_items bi
        INNER JOIN billing b ON bi.bill_id = b.id
        WHERE DATE(b.bill_date) = ?
        GROUP BY COALESCE(bi.department, 'General')
        ORDER BY revenue DESC
    ");
    $stmt->execute([$today]);
    $dept_data = $stmt->fetchAll();

    // Add pharmacy as a department
    if ($stats['pharmacy_revenue'] > 0) {
        $dept_data[] = [
            'department' => 'Pharmacy',
            'revenue' => $stats['pharmacy_revenue'],
            'bill_count' => $stats['pharmacy_bills']
        ];
    }
    
    $stats['department_revenue'] = $dept_data;

    // Weekly revenue trend
    $stmt = $pdo->prepare("
        SELECT 
            DATE(bill_date) as date,
            SUM(paid_amount) as revenue,
            COUNT(*) as bill_count
        FROM billing 
        WHERE bill_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(bill_date)
        ORDER BY date ASC
    ");
    $stmt->execute();
    $weekly_revenue = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching dashboard stats: " . $e->getMessage());
}


// Get patients with outstanding balances - UPDATED to include all service types including pharmacy
// Get patients with outstanding balances
$patients_with_dues = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.patient_id,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.phone,
            (
                -- Count all types of bills with outstanding amounts
                (SELECT COUNT(*) FROM billing WHERE patient_id = p.id AND balance_amount > 0) +
                (SELECT COUNT(*) FROM lab_orders WHERE patient_id = p.id AND payment_status IN ('unpaid', 'partial') AND (total_amount - paid_amount) > 0) +
                (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id AND status = 'completed' AND consultation_fee > 0 AND NOT EXISTS (
                    SELECT 1 FROM bill_items bi 
                    WHERE bi.bill_id IN (SELECT id FROM billing WHERE patient_id = p.id)
                    AND bi.item_type = 'consultation'
                    AND bi.service_date = appointments.appointment_date
                )) +
                (SELECT COUNT(*) FROM pharmacy_billing WHERE patient_id = p.id AND COALESCE(balance_amount, 0) > 0)
            ) as bill_count,
            (
                -- Hospital bills
                COALESCE((SELECT SUM(balance_amount) FROM billing WHERE patient_id = p.id AND balance_amount > 0), 0) +
                -- Lab orders
                COALESCE((SELECT SUM(total_amount - paid_amount) FROM lab_orders WHERE patient_id = p.id AND payment_status IN ('unpaid', 'partial') AND (total_amount - paid_amount) > 0), 0) +
                -- Consultation fees
                COALESCE((SELECT SUM(consultation_fee) FROM appointments WHERE patient_id = p.id AND status = 'completed' AND consultation_fee > 0 AND NOT EXISTS (
                    SELECT 1 FROM bill_items bi 
                    WHERE bi.bill_id IN (SELECT id FROM billing WHERE patient_id = p.id)
                    AND bi.item_type = 'consultation'
                    AND bi.service_date = appointments.appointment_date
                )), 0) +
                -- Pharmacy bills
                COALESCE((SELECT SUM(COALESCE(balance_amount, 0)) FROM pharmacy_billing WHERE patient_id = p.id AND COALESCE(balance_amount, 0) > 0), 0)
            ) as total_due
        FROM patients p
        HAVING total_due > 0
        ORDER BY total_due DESC
        LIMIT 10
    ");
    $stmt->execute();
    $patients_with_dues = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching patients with dues: " . $e->getMessage());
}
    
    // Fallback query if the main one fails
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.patient_id,
                CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                p.phone,
                COUNT(DISTINCT b.id) as bill_count,
                COALESCE(SUM(b.balance_amount), 0) as total_due
            FROM patients p
            INNER JOIN billing b ON p.id = b.patient_id
            WHERE b.balance_amount > 0
            GROUP BY p.id, p.patient_id, p.first_name, p.last_name, p.phone
            ORDER BY total_due DESC
            LIMIT 10
        ");
        $stmt->execute();
        $patients_with_dues = $stmt->fetchAll();
    } catch (PDOException $e2) {
        error_log("Fallback query also failed: " . $e2->getMessage());
    }

// Get outstanding by service type - UPDATED to include all services
$outstanding_by_service = [];
try {
    // Hospital services
    $stmt = $pdo->prepare("
        SELECT 
            'hospital' as service_type,
            COUNT(*) as bill_count,
            SUM(total_amount) as total_amount,
            SUM(balance_amount) as outstanding_amount
        FROM billing 
        WHERE balance_amount > 0
    ");
    $stmt->execute();
    $hospital_outstanding = $stmt->fetch();
    if ($hospital_outstanding['outstanding_amount'] > 0) {
        $outstanding_by_service[] = $hospital_outstanding;
    }

    // Lab orders
    $stmt = $pdo->prepare("
        SELECT 
            'lab' as service_type,
            COUNT(*) as bill_count,
            SUM(total_amount) as total_amount,
            SUM(total_amount - paid_amount) as outstanding_amount
        FROM lab_orders 
        WHERE payment_status IN ('unpaid', 'partial')
        AND (total_amount - paid_amount) > 0
    ");
    $stmt->execute();
    $lab_outstanding = $stmt->fetch();
    if ($lab_outstanding['outstanding_amount'] > 0) {
        $outstanding_by_service[] = $lab_outstanding;
    }

    // Consultations
    $stmt = $pdo->prepare("
        SELECT 
            'consultation' as service_type,
            COUNT(*) as bill_count,
            SUM(consultation_fee) as total_amount,
            SUM(consultation_fee) as outstanding_amount
        FROM appointments 
        WHERE status = 'completed'
        AND consultation_fee > 0
        AND NOT EXISTS (
            SELECT 1 FROM bill_items bi 
            WHERE bi.bill_id IN (SELECT id FROM billing)
            AND bi.item_type = 'consultation'
            AND bi.service_date = appointments.appointment_date
        )
    ");
    $stmt->execute();
    $consultation_outstanding = $stmt->fetch();
    if ($consultation_outstanding['outstanding_amount'] > 0) {
        $outstanding_by_service[] = $consultation_outstanding;
    }

    // Admissions
    $stmt = $pdo->prepare("
        SELECT 
            'admission' as service_type,
            COUNT(*) as bill_count,
            SUM(DATEDIFF(CURDATE(), ba.admission_date) * b.price_per_day) as total_amount,
            SUM(DATEDIFF(CURDATE(), ba.admission_date) * b.price_per_day) as outstanding_amount
        FROM bed_assignments ba
        INNER JOIN beds b ON ba.bed_id = b.id
        WHERE ba.status = 'active'
        AND DATEDIFF(CURDATE(), ba.admission_date) * b.price_per_day > 0
    ");
    $stmt->execute();
    $admission_outstanding = $stmt->fetch();
    if ($admission_outstanding['outstanding_amount'] > 0) {
        $outstanding_by_service[] = $admission_outstanding;
    }

    // Pharmacy
    mysqli_select_db($link, "hospital");
    $pharmacy_outstanding_query = "
        SELECT 
            'pharmacy' as service_type,
            COUNT(*) as bill_count,
            COALESCE(SUM(pb.balance_amount), 0) as outstanding_amount,
            COALESCE(SUM(pb.balance_amount), 0) as total_amount
        FROM pharmacy_billing pb
        WHERE COALESCE(pb.balance_amount, 0) > 0
    ";
    $pharmacy_outstanding_result = mysqli_query($link, $pharmacy_outstanding_query);
    if ($pharmacy_outstanding_result) {
        $pharmacy_outstanding = mysqli_fetch_assoc($pharmacy_outstanding_result);
        if ($pharmacy_outstanding['outstanding_amount'] > 0) {
            $outstanding_by_service[] = $pharmacy_outstanding;
        }
    }

} catch (PDOException $e) {
    error_log("Error fetching outstanding by service: " . $e->getMessage());
}

// Get recent bills - COMBINED from both systems
// Get recent bills - COMBINED from both systems (LAST 7 DAYS, not just today)
$recent_bills = [];
try {
    // Get unified system bills (hospital services) - LAST 7 DAYS
    $stmt = $pdo->prepare("
        SELECT 
            b.id,
            b.bill_number,
            b.bill_date,
            b.patient_id,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_id as patient_number,
            b.total_amount,
            b.paid_amount,
            b.balance_amount,
            b.status,
            b.payment_method,
            'hospital' as source,
            COUNT(DISTINCT bi.id) as item_count
        FROM billing b
        INNER JOIN patients p ON b.patient_id = p.id
        LEFT JOIN bill_items bi ON b.id = bi.bill_id
        WHERE b.bill_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY b.id
        ORDER BY b.bill_date DESC
        LIMIT 8
    ");
    $stmt->execute();
    $unified_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get pharmacy bills from integrated system - LAST 7 DAYS
  // Get pharmacy bills from integrated system - LAST 7 DAYS
mysqli_select_db($link, "hospital");
$pharmacy_bills_query = "
    SELECT 
        pb.id,
        pb.bill_no as bill_number,
        pb.date as bill_date,
        pb.patient_id,
        pb.CustomerName as patient_name,
        COALESCE(p.patient_id, '') as patient_number,
        COALESCE(SUM(pbd.total_amount), 0) as total_amount,
        COALESCE(pb.paid_amount, 0) as paid_amount,
        COALESCE(pb.balance_amount, 0) as balance_amount,
        CASE 
            WHEN COALESCE(pb.balance_amount, 0) > 0 AND COALESCE(pb.paid_amount, 0) > 0 THEN 'partial'
            WHEN COALESCE(pb.balance_amount, 0) > 0 THEN 'pending'
            ELSE 'paid'
        END as status,
        pb.bill_type as payment_method,
        'pharmacy' as source,
        COUNT(pbd.id) as item_count
    FROM pharmacy_billing pb
    LEFT JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
    LEFT JOIN patients p ON pb.patient_id = p.id
    WHERE pb.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY pb.id, pb.paid_amount, pb.balance_amount, pb.payment_status
    ORDER BY pb.date DESC
    LIMIT 8
";
    
    $pharmacy_bills_result = mysqli_query($link, $pharmacy_bills_query);
    $pharmacy_bills = [];
    if ($pharmacy_bills_result) {
        while ($row = mysqli_fetch_assoc($pharmacy_bills_result)) {
            $pharmacy_bills[] = $row;
        }
    }

    // Merge and sort by date
    $recent_bills = array_merge($unified_bills, $pharmacy_bills);
    usort($recent_bills, function($a, $b) {
        return strtotime($b['bill_date']) - strtotime($a['bill_date']);
    });
    $recent_bills = array_slice($recent_bills, 0, 10);

} catch (PDOException $e) {
    error_log("Error fetching recent bills: " . $e->getMessage());
}

// Get top services/items by revenue
$top_items = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            bi.item_description,
            SUM(bi.quantity) as total_quantity,
            SUM(bi.total_amount) as total_revenue,
            COUNT(DISTINCT b.id) as bill_count
        FROM bill_items bi
        INNER JOIN billing b ON bi.bill_id = b.id
        WHERE DATE(b.bill_date) = ?
        GROUP BY bi.item_description
        ORDER BY total_revenue DESC
        LIMIT 5
    ");
    $stmt->execute([$today]);
    $top_items = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching top items: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Dashboard - Equity Afia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #3b82f6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1f2937;
            --light: #f8fafc;
        }
        
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .header-content h1 {
            margin: 0;
            color: var(--dark);
            font-size: 1.875rem;
            font-weight: 700;
        }
        
        .header-content p {
            margin: 0.5rem 0 0 0;
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .date-display {
            background: var(--primary);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .stat-card.revenue { border-left-color: var(--success); }
        .stat-card.pharmacy { border-left-color: #8b5cf6; }
        .stat-card.pending { border-left-color: var(--warning); }
        .stat-card.outstanding { border-left-color: var(--danger); }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-card.revenue .stat-icon { background: #dcfce7; color: var(--success); }
        .stat-card.pharmacy .stat-icon { background: #f3e8ff; color: #8b5cf6; }
        .stat-card.pending .stat-icon { background: #fef3c7; color: var(--warning); }
        .stat-card.outstanding .stat-icon { background: #fee2e2; color: var(--danger); }
        
        .stat-content h3 {
            margin: 0;
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .stat-content p {
            margin: 0.25rem 0 0 0;
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .stat-trend {
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.25rem;
        }
        
        .trend-up { color: var(--success); }
        .trend-down { color: var(--danger); }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .outstanding-section {
            margin-bottom: 2rem;
        }
        
        .outstanding-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .card {
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
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        .bill-list, .patient-list, .service-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .bill-item, .patient-item, .service-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        
        .bill-item:hover, .patient-item:hover, .service-item:hover {
            background: #f9fafb;
            border-color: var(--primary);
        }
        
        .bill-info h4, .patient-info h4, .service-info h4 {
            margin: 0;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .bill-info p, .patient-info p, .service-info p {
            margin: 0.25rem 0 0 0;
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .bill-amount {
            text-align: right;
        }
        
        .bill-total {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .bill-balance, .outstanding-amount {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--danger);
            margin-top: 0.25rem;
        }
        
        .view-details {
            font-size: 0.75rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .view-details:hover {
            text-decoration: underline;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-paid { background: #dcfce7; color: var(--success); }
        .status-pending { background: #fef3c7; color: var(--warning); }
        .status-partial { background: #dbeafe; color: var(--primary); }
        
        .source-badge {
            padding: 0.125rem 0.5rem;
            background: #dbeafe;
            color: var(--primary);
            border-radius: 0.25rem;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .source-badge.pharmacy {
            background: #f3e8ff;
            color: #8b5cf6;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateX(4px);
        }
        
        .action-btn:hover .action-icon {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .action-icon {
            width: 40px;
            height: 40px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: all 0.2s;
        }
        
        .action-btn:nth-child(1) .action-icon { background: #dcfce7; color: var(--success); }
        .action-btn:nth-child(2) .action-icon { background: #f3e8ff; color: #8b5cf6; }
        .action-btn:nth-child(3) .action-icon { background: #fef3c7; color: var(--warning); }
        .action-btn:nth-child(4) .action-icon { background: #fee2e2; color: var(--danger); }
        
        .action-text h4 {
            margin: 0;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .action-text p {
            margin: 0.25rem 0 0 0;
            font-size: 0.75rem;
            opacity: 0.8;
        }
        
        .department-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .department-card {
            background: white;
            padding: 1rem;
            border-radius: 0.75rem;
            border-left: 4px solid var(--primary);
            text-align: center;
        }
        
        .department-card h4 {
            margin: 0 0 0.5rem 0;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .department-revenue {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--success);
            margin: 0.5rem 0;
        }
        
        .department-count {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success {
            background: #dcfce7;
            color: var(--success);
            border: 1px solid #bbf7d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: var(--danger);
            border: 1px solid #fecaca;
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
        
        .view-all {
            text-align: center;
            padding: 1rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .view-all a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .view-all a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 1024px) {
            .dashboard-grid, .outstanding-grid {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
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
        
        <div class="dashboard-header">
            <div class="header-content">
                <h1><i class="fas fa-money-bill-wave"></i> Billing Dashboard</h1>
                <p>Unified financial overview - Hospital Services & Pharmacy Sales</p>
            </div>
            <div class="date-display">
                <i class="far fa-calendar"></i> <?php echo date('l, F j, Y'); ?>
            </div>
        </div>
        <div class="stats-grid">
            <div class="stat-card revenue">
                <div class="stat-icon">
                    <i class="fas fa-money-bill"></i>
                </div>
                <div class="stat-content">
                    <h3>Ksh <?php echo number_format($stats['today_revenue'], 2); ?></h3>
                    <p>Today's Total Revenue</p>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i> Combined Hospital & Pharmacy
                    </div>
                </div>
            </div>
            
            <div class="stat-card pharmacy">
                <div class="stat-icon">
                    <i class="fas fa-pills"></i>
                </div>
                <div class="stat-content">
                    <h3>Ksh <?php echo number_format($stats['pharmacy_revenue'], 2); ?></h3>
                    <p>Pharmacy Sales</p>
                    <div class="stat-trend">
                        <?php echo $stats['pharmacy_bills']; ?> bills today
                    </div>
                </div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['pending_bills']; ?></h3>
                    <p>Pending Bills</p>
                    <div class="stat-trend trend-down">
                        Require attention
                    </div>
                </div>
            </div>
            
            <div class="stat-card outstanding">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h3>Ksh <?php echo number_format($stats['total_outstanding'], 2); ?></h3>
                    <p>Total Outstanding</p>
                    <div class="stat-trend trend-down">
                        Uncollected payments
                    </div>
                </div>
            </div>
        </div>

        <!-- Outstanding Dues Section - MOVED TO TOP -->
        <div class="outstanding-section">
            <div class="outstanding-grid">
                <!-- Patients with Dues -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-users"></i>
                            Patients with Outstanding Dues
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($patients_with_dues) > 0): ?>
                        <div class="patient-list">
                            <?php foreach ($patients_with_dues as $patient): ?>
                            <div class="patient-item">
                                <div class="patient-info">
                                    <h4><i class="fas fa-user"></i> <?php echo htmlspecialchars($patient['patient_name']); ?></h4>
                                    <p>
                                        <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($patient['patient_id']); ?> • 
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone']); ?> • 
                                        <i class="fas fa-file-invoice"></i> <?php echo $patient['bill_count']; ?> bills
                                    </p>
                                </div>
                                <div class="bill-amount">
                                    <div class="outstanding-amount">
                                        Ksh <?php echo number_format($patient['total_due'], 2); ?>
                                    </div>
                                    <div style="margin-top: 0.5rem;">
                                        <a href="patient_billing.php?id=<?php echo $patient['id']; ?>" class="view-details">
                                            <i class="fas fa-eye"></i> View Bills
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <h3>No Outstanding Dues</h3>
                            <p>All patients are up to date with payments.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Outstanding by Service Type -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-chart-bar"></i>
                            Outstanding by Service Type
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($outstanding_by_service) > 0): ?>
                        <div class="service-list">
                            <?php foreach ($outstanding_by_service as $service): ?>
                            <div class="service-item">
                                <div class="service-info">
                                    <h4>
                                        <i class="fas fa-<?php 
                                            switch($service['service_type']) {
                                                case 'hospital': echo 'hospital'; break;
                                                case 'lab': echo 'flask'; break;
                                                case 'consultation': echo 'stethoscope'; break;
                                                case 'admission': echo 'procedures'; break;
                                                case 'pharmacy': echo 'pills'; break;
                                                default: echo 'file-invoice';
                                            }
                                        ?>"></i> 
                                        <?php echo ucfirst($service['service_type']); ?>
                                    </h4>
                                    <p>
                                        <i class="fas fa-file-invoice"></i> <?php echo $service['bill_count']; ?> bills • 
                                        <i class="fas fa-money-bill"></i> Ksh <?php echo number_format($service['total_amount'], 2); ?> total
                                    </p>
                                </div>
                                <div class="bill-amount">
                                    <div class="outstanding-amount">
                                        Ksh <?php echo number_format($service['outstanding_amount'], 2); ?>
                                    </div>
                                    <div class="bill-count" style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">
                                        Outstanding
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <h3>No Outstanding Bills</h3>
                            <p>All services are fully paid.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <!-- Left Column: Charts and Recent Bills -->
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                <!-- Revenue Chart -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            Today's Revenue Distribution
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                    
                </div>

                <!-- Recent Bills -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-clock"></i>
                            Recent Bills Today
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_bills) > 0): ?>
                        <div class="bill-list">
                            <?php foreach ($recent_bills as $bill): ?>
                            <div class="bill-item">
                                <div class="bill-info">
                                    <h4>
                                        <?php echo htmlspecialchars($bill['patient_name']); ?>
                                        <span class="source-badge <?php echo $bill['source']; ?>">
                                            <?php echo $bill['source']; ?>
                                        </span>
                                    </h4>
                                    <p>
                                        <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($bill['bill_number']); ?> • 
                                        <i class="far fa-clock"></i> <?php echo date('g:i A', strtotime($bill['bill_date'])); ?>
                                        <?php if ($bill['patient_number']): ?>
                                        • <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($bill['patient_number']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="bill-amount">
                                    <div class="bill-total">
                                        Ksh <?php echo number_format($bill['total_amount'], 2); ?>
                                    </div>
                                    <?php if ($bill['balance_amount'] > 0): ?>
                                    <div class="bill-balance">
                                        <i class="fas fa-exclamation-circle"></i>
                                        Ksh <?php echo number_format($bill['balance_amount'], 2); ?> due
                                    </div>
                                    <?php endif; ?>
                                    <div style="margin-top: 0.5rem;">
                                        <span class="status-badge status-<?php echo $bill['status']; ?>">
                                            <?php echo ucfirst($bill['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="view-all">
                            <a href="bills_details.php">
                                <i class="fas fa-list"></i> View All Bills
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <h3>No Bills Today</h3>
                            <p>No bills have been generated today.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Quick Actions and Department Revenue -->
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-bolt"></i>
                            Quick Actions
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="unified_billing_management.php" class="action-btn">
                                <div class="action-icon">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <div class="action-text">
                                    <h4>Bills</h4>
                                    <p>Hospital services</p>
                                </div>
                            </a>
                            
                            <a href="pharmacy_billing.php" class="action-btn">
                                <div class="action-icon">
                                    <i class="fas fa-pills"></i>
                                </div>
                                <div class="action-text">
                                    <h4>Pharmacy Sales</h4>
                                    <p>Medication dispensing</p>
                                </div>
                            </a>
                            
                            <a href="pending_payments.php" class="action-btn">
                                <div class="action-icon">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <div class="action-text">
                                    <h4>Process Pending Payments</h4>
                                    <p>Collect payments</p>
                                </div>
                            </a>
                            
                            <a href="billing_reports.php" class="action-btn">
                                <div class="action-icon">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <div class="action-text">
                                    <h4>View Reports</h4>
                                    <p>Financial analytics</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Department Revenue -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-chart-pie"></i>
                            Department Revenue
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($stats['department_revenue']) > 0): ?>
                        <div class="department-grid">
                            <?php foreach ($stats['department_revenue'] as $dept): ?>
                            <div class="department-card">
                                <h4>
                                    <i class="fas fa-<?php 
                                        switch($dept['department']) {
                                            case 'Pharmacy': echo 'pills'; break;
                                            case 'Laboratory': echo 'flask'; break;
                                            case 'Radiology': echo 'x-ray'; break;
                                            default: echo 'hospital';
                                        }
                                    ?>"></i>
                                    <?php echo htmlspecialchars($dept['department']); ?>
                                </h4>
                                <div class="department-revenue">
                                    Ksh <?php echo number_format($dept['revenue'], 2); ?>
                                </div>
                                <div class="department-count">
                                    <?php echo $dept['bill_count']; ?> bills
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-bar"></i>
                            <h3>No Revenue Data</h3>
                            <p>No department revenue today.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Services -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-star"></i>
                            Top Services Today
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($top_items) > 0): ?>
                        <div class="bill-list">
                            <?php foreach ($top_items as $item): ?>
                            <div class="bill-item">
                                <div class="bill-info">
                                    <h4><?php echo htmlspecialchars($item['item_description']); ?></h4>
                                    <p>
                                        <i class="fas fa-chart-bar"></i> <?php echo $item['bill_count']; ?> sales • 
                                        <i class="fas fa-box"></i> <?php echo $item['total_quantity']; ?> units
                                    </p>
                                </div>
                                <div class="bill-amount">
                                    <div class="bill-total">
                                        Ksh <?php echo number_format($item['total_revenue'], 2); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-star"></i>
                            <h3>No Services Data</h3>
                            <p>No service sales today.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'doughnut',
            data: {
                labels: ['Hospital Services', 'Pharmacy Sales'],
                datasets: [{
                    data: [
                        <?php echo $stats['hospital_revenue']; ?>,
                        <?php echo $stats['pharmacy_revenue']; ?>
                    ],
                    backgroundColor: ['#3b82f6', '#8b5cf6'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                return `${label}: Ksh ${value.toLocaleString()}`;
                            }
                        }
                    }
                }
            }
        });

        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>