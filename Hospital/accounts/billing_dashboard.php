<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
include "admin/connection.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

date_default_timezone_set('Africa/Nairobi');

// Helper function to map service types to departments
function mapServiceToDepartment($service) {
    $map = [
        'consultation' => 'Consultation',
        'lab' => 'Laboratory',
        'pharmacy' => 'Pharmacy',
        'admission' => 'Admission',
        'general' => 'General',
        'hospital' => 'General',
        'bed_charge' => 'Admission',
        'radiology' => 'Radiology',
        'procedure' => 'Procedure',
        'medication' => 'Pharmacy',
        'emergency' => 'Emergency',
        'surgery' => 'Surgery'
    ];
    return $map[strtolower($service)] ?? ucfirst($service);
}

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
    'lab_revenue' => 0,
    'admission_revenue' => 0,
    'consultation_revenue' => 0,
    'pending_bills' => 0,
    'total_outstanding' => 0,
    'department_revenue' => [],
    'payment_methods' => []
];

try {
    // TODAY'S TOTAL REVENUE - From payments table
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as today_revenue,
            COUNT(*) as payment_count
        FROM payments 
        WHERE DATE(payment_date) = ?
        AND status = 'completed'
    ");
    $stmt->execute([$today]);
    $today_revenue_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['today_revenue'] = $today_revenue_data['today_revenue'];
    
    // Get TODAY'S HOSPITAL REVENUE breakdown by service type
    $stmt = $pdo->prepare("
        SELECT 
            'consultation' as service_type,
            COALESCE(SUM(bi.total_amount), 0) as revenue,
            COUNT(DISTINCT b.id) as bill_count
        FROM billing b
        INNER JOIN bill_items bi ON b.id = bi.bill_id
        WHERE DATE(b.bill_date) = ?
        AND b.status = 'paid'
        AND bi.item_type = 'consultation'
        
        UNION ALL
        
        SELECT 
            'lab' as service_type,
            COALESCE(SUM(lo.total_amount), 0) as revenue,
            COUNT(DISTINCT lo.id) as bill_count
        FROM lab_orders lo
        WHERE DATE(lo.order_date) = ?
        AND lo.payment_status = 'paid'
        
        UNION ALL
        
        SELECT 
            'admission' as service_type,
            COALESCE(SUM(bi.total_amount), 0) as revenue,
            COUNT(DISTINCT b.id) as bill_count
        FROM billing b
        INNER JOIN bill_items bi ON b.id = bi.bill_id
        WHERE DATE(b.bill_date) = ?
        AND b.status = 'paid'
        AND bi.item_type = 'bed_charge'
        
        UNION ALL
        
        SELECT 
            'procedure' as service_type,
            COALESCE(SUM(bi.total_amount), 0) as revenue,
            COUNT(DISTINCT b.id) as bill_count
        FROM billing b
        INNER JOIN bill_items bi ON b.id = bi.bill_id
        WHERE DATE(b.bill_date) = ?
        AND b.status = 'paid'
        AND bi.item_type = 'procedure'
    ");
    $stmt->execute([$today, $today, $today, $today]);
    $hospital_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate breakdown
    foreach ($hospital_breakdown as $service) {
        switch ($service['service_type']) {
            case 'consultation':
                $stats['consultation_revenue'] = $service['revenue'];
                break;
            case 'lab':
                $stats['lab_revenue'] = $service['revenue'];
                break;
            case 'admission':
                $stats['admission_revenue'] = $service['revenue'];
                break;
            case 'procedure':
                $stats['hospital_revenue'] += $service['revenue'];
                break;
        }
    }

    // Get TODAY'S PHARMACY REVENUE
    mysqli_select_db($link, "hospital");
    $pharmacy_query = "
        SELECT 
            COALESCE(SUM(pb.total_amount), 0) as pharmacy_revenue,
            COUNT(DISTINCT pb.id) as pharmacy_bills
        FROM pharmacy_billing pb
        WHERE DATE(pb.date) = '$today'
        AND pb.payment_status = 'paid'
    ";
    $pharmacy_result = mysqli_query($link, $pharmacy_query);
    if ($pharmacy_result) {
        $pharmacy_row = mysqli_fetch_assoc($pharmacy_result);
        $stats['pharmacy_revenue'] = $pharmacy_row['pharmacy_revenue'] ?? 0;
        $stats['pharmacy_bills'] = $pharmacy_row['pharmacy_bills'] ?? 0;
    }

    // Calculate TOTAL OUTSTANDING from the new view vw_patient_outstanding_balances
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(total_outstanding), 0) as total_outstanding,
            COUNT(*) as patient_count
        FROM vw_patient_outstanding_balances
    ");
    $stmt->execute();
    $outstanding_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_outstanding'] = $outstanding_result['total_outstanding'] ?? 0;

    // Get pending patients count
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending_patients FROM vw_patient_outstanding_balances");
    $stmt->execute();
    $pending_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['pending_bills'] = $pending_result['pending_patients'] ?? 0;

    // Payment methods distribution FROM PAYMENTS TABLE
    $stmt = $pdo->prepare("
        SELECT 
            payment_method,
            COUNT(*) as transaction_count,
            SUM(amount) as total_amount
        FROM payments 
        WHERE DATE(payment_date) = ?
        AND status = 'completed'
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ");
    $stmt->execute([$today]);
    $stats['payment_methods'] = $stmt->fetchAll();

    // Department-wise revenue from all services TODAY
    $stmt = $pdo->prepare("
        SELECT 
            'Pharmacy' as department,
            COALESCE(SUM(pb.total_amount), 0) as revenue,
            COUNT(DISTINCT pb.id) as bill_count
        FROM pharmacy_billing pb
        WHERE DATE(pb.date) = ?
        AND pb.payment_status = 'paid'
        
        UNION ALL
        
        SELECT 
            'Laboratory' as department,
            COALESCE(SUM(lo.total_amount), 0) as revenue,
            COUNT(DISTINCT lo.id) as bill_count
        FROM lab_orders lo
        WHERE DATE(lo.order_date) = ?
        AND lo.payment_status = 'paid'
        
        UNION ALL
        
        SELECT 
            'Consultation' as department,
            COALESCE(SUM(bi.total_amount), 0) as revenue,
            COUNT(DISTINCT b.id) as bill_count
        FROM billing b
        INNER JOIN bill_items bi ON b.id = bi.bill_id
        WHERE DATE(b.bill_date) = ?
        AND b.status = 'paid'
        AND bi.item_type = 'consultation'
        
        UNION ALL
        
        SELECT 
            'Admission' as department,
            COALESCE(SUM(bi.total_amount), 0) as revenue,
            COUNT(DISTINCT b.id) as bill_count
        FROM billing b
        INNER JOIN bill_items bi ON b.id = bi.bill_id
        WHERE DATE(b.bill_date) = ?
        AND b.status = 'paid'
        AND bi.item_type = 'bed_charge'
        
        UNION ALL
        
        SELECT 
            'General' as department,
            COALESCE(SUM(b.total_amount - bi.total_amount), 0) as revenue,
            COUNT(DISTINCT b.id) as bill_count
        FROM billing b
        LEFT JOIN (
            SELECT bill_id, SUM(total_amount) as total_amount
            FROM bill_items 
            WHERE item_type IN ('consultation', 'bed_charge', 'procedure')
            GROUP BY bill_id
        ) bi ON b.id = bi.bill_id
        WHERE DATE(b.bill_date) = ?
        AND b.status = 'paid'
        HAVING revenue > 0
    ");
    $stmt->execute([$today, $today, $today, $today, $today]);
    $dept_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format department revenue
    foreach ($dept_revenue as $dept) {
        if ($dept['revenue'] > 0) {
            $stats['department_revenue'][] = [
                'department' => $dept['department'],
                'revenue' => $dept['revenue'],
                'bill_count' => $dept['bill_count']
            ];
        }
    }

} catch (PDOException $e) {
    error_log("Error fetching dashboard stats: " . $e->getMessage());
}

// Get patients with outstanding balances - USING THE NEW VIEW
$patients_with_dues = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            patient_id,
            patient_number,
            patient_name,
            phone,
            email,
            total_outstanding,
            total_items,
            last_service_date
        FROM vw_patient_outstanding_balances
        ORDER BY total_outstanding DESC
        LIMIT 10
    ");
    $stmt->execute();
    $patients_with_dues = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching patients with dues: " . $e->getMessage());
}

// Get outstanding by service type - USING THE NEW DETAILED VIEW
$outstanding_by_service = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            service_type,
            COUNT(*) as bill_count,
            SUM(balance) as outstanding_amount,
            SUM(total_amount) as total_amount
        FROM vw_patient_outstanding_details
        WHERE balance > 0
        GROUP BY service_type
        ORDER BY outstanding_amount DESC
    ");
    $stmt->execute();
    $outstanding_by_service = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching outstanding by service: " . $e->getMessage());
}

// Get recent bills - TODAY ONLY from ALL services
$recent_bills = [];
try {
    // Get TODAY'S hospital bills with service breakdown
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
            GROUP_CONCAT(DISTINCT 
                CASE bi.item_type 
                    WHEN 'consultation' THEN 'consultation'
                    WHEN 'bed_charge' THEN 'admission'
                    WHEN 'lab_test' THEN 'lab'
                    WHEN 'procedure' THEN 'procedure'
                    ELSE 'other'
                END
                ORDER BY bi.item_type SEPARATOR ', '
            ) as service_types,
            (
                SELECT GROUP_CONCAT(DISTINCT bi.item_description ORDER BY bi.id SEPARATOR '; ')
                FROM bill_items bi
                WHERE bi.bill_id = b.id
            ) as item_descriptions
        FROM billing b
        INNER JOIN patients p ON b.patient_id = p.id
        LEFT JOIN bill_items bi ON b.id = bi.bill_id
        WHERE DATE(b.bill_date) = ?
        AND b.status = 'paid'
        GROUP BY b.id
        ORDER BY b.bill_date DESC
        LIMIT 8
    ");
    $stmt->execute([$today]);
    $hospital_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get TODAY'S lab orders as bills
    $stmt = $pdo->prepare("
        SELECT 
            lo.id,
            lo.order_number as bill_number,
            lo.order_date as bill_date,
            lo.patient_id,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_id as patient_number,
            lo.total_amount,
            lo.paid_amount,
            (lo.total_amount - lo.paid_amount) as balance_amount,
            lo.payment_status as status,
            'cash' as payment_method,
            'lab' as source,
            'lab' as service_types,
            GROUP_CONCAT(DISTINCT lt.test_name ORDER BY lt.test_name SEPARATOR '; ') as item_descriptions
        FROM lab_orders lo
        INNER JOIN patients p ON lo.patient_id = p.id
        LEFT JOIN lab_tests lt ON lo.test_id = lt.id
        WHERE DATE(lo.order_date) = ?
        AND lo.payment_status = 'paid'
        GROUP BY lo.id
        ORDER BY lo.order_date DESC
        LIMIT 8
    ");
    $stmt->execute([$today]);
    $lab_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get TODAY'S pharmacy bills
    mysqli_select_db($link, "hospital");
    $pharmacy_bills_query = "
        SELECT 
            pb.id,
            pb.bill_no as bill_number,
            pb.date as bill_date,
            pb.patient_id,
            pb.CustomerName as patient_name,
            COALESCE(p.patient_id, '') as patient_number,
            COALESCE(pb.total_amount, 0) as total_amount,
            COALESCE(pb.paid_amount, 0) as paid_amount,
            COALESCE(pb.balance_amount, 0) as balance_amount,
            pb.payment_status as status,
            COALESCE(pb.bill_type, 'cash') as payment_method,
            'pharmacy' as source,
            'pharmacy' as service_types,
            (
                SELECT GROUP_CONCAT(DISTINCT pbd.product_name ORDER BY pbd.id SEPARATOR '; ')
                FROM pharmacy_billing_details pbd
                WHERE pbd.billing_id = pb.id
            ) as item_descriptions
        FROM pharmacy_billing pb
        LEFT JOIN patients p ON pb.patient_id = p.id
        WHERE DATE(pb.date) = '$today'
        AND pb.payment_status = 'paid'
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
    
    // Merge all bills
    $recent_bills = array_merge($hospital_bills, $lab_bills, $pharmacy_bills);
    
    // Sort by date
    usort($recent_bills, function($a, $b) {
        return strtotime($b['bill_date']) - strtotime($a['bill_date']);
    });
    
    // Limit to 8 most recent
    $recent_bills = array_slice($recent_bills, 0, 8);

} catch (PDOException $e) {
    error_log("Error fetching recent bills: " . $e->getMessage());
}

// Get top services by revenue TODAY (all services)
$top_services = [];
try {
    // Get top services from billing items
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN bi.item_type = 'consultation' THEN 'Consultation'
                WHEN bi.item_type = 'bed_charge' THEN 'Admission'
                WHEN bi.item_type = 'lab_test' THEN 'Lab Test'
                WHEN bi.item_type = 'procedure' THEN 'Procedure'
                ELSE 'Other Services'
            END as service_name,
            bi.item_type as service_type,
            SUM(bi.quantity) as total_quantity,
            SUM(bi.total_amount) as total_revenue,
            COUNT(DISTINCT b.id) as bill_count
        FROM bill_items bi
        INNER JOIN billing b ON bi.bill_id = b.id
        WHERE DATE(b.bill_date) = ?
        AND b.status = 'paid'
        GROUP BY bi.item_type
        HAVING total_revenue > 0
        
        UNION ALL
        
        SELECT 
            'Pharmacy Sales' as service_name,
            'pharmacy' as service_type,
            COALESCE(SUM(pb.total_amount), 0) as total_quantity,
            COALESCE(SUM(pb.total_amount), 0) as total_revenue,
            COUNT(DISTINCT pb.id) as bill_count
        FROM pharmacy_billing pb
        WHERE DATE(pb.date) = ?
        AND pb.payment_status = 'paid'
        
        UNION ALL
        
        SELECT 
            'Laboratory Tests' as service_name,
            'lab' as service_type,
            COALESCE(COUNT(lo.id), 0) as total_quantity,
            COALESCE(SUM(lo.total_amount), 0) as total_revenue,
            COUNT(DISTINCT lo.id) as bill_count
        FROM lab_orders lo
        WHERE DATE(lo.order_date) = ?
        AND lo.payment_status = 'paid'
        
        ORDER BY total_revenue DESC
        LIMIT 5
    ");
    $stmt->execute([$today, $today, $today]);
    $top_services = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching top services: " . $e->getMessage());
}

// Get payment history for today
$today_payment_history = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.patient_id,
            p.amount,
            p.payment_method,
            p.reference_number,
            p.status,
            p.payment_date,
            p.notes,
            CONCAT(pt.first_name, ' ', pt.last_name) as patient_name,
            pt.patient_id as patient_number
        FROM payments p
        INNER JOIN patients pt ON p.patient_id = pt.id
        WHERE p.status = 'completed'
        AND DATE(p.payment_date) = ?
        ORDER BY p.payment_date DESC
        LIMIT 10
    ");
    $stmt->execute([$today]);
    $today_payment_history = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching payment history: " . $e->getMessage());
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
            --primary: #0891b2;
            --primary-light: #06b6d4;
            --primary-dark: #0e7490;
            --secondary: #14b8a6;
            --accent: #22d3ee;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #134e4a;
            --light: #f0fdfa;
            --gray: #64748b;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--light);
            color: var(--dark);
        }
        
        .main-content {
            margin-left: 300px;
            padding: 1rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem 0.5rem;
            }
        }
        
        /* Dashboard Header */
        .dashboard-header {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.5rem;
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(6, 182, 212, 0.1);
        }
        
        @media (min-width: 768px) {
            .dashboard-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }
        
        .header-content h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .header-content p {
            margin: 0.5rem 0 0 0;
            color: var(--gray);
            font-size: 0.875rem;
        }
        
        .date-display {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            box-shadow: 0 2px 4px rgba(6, 182, 212, 0.2);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }
        
        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .stat-card {
            background: white;
            padding: 1.25rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary);
            border: 1px solid rgba(6, 182, 212, 0.1);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(6, 182, 212, 0.1);
        }
        
        .stat-card.revenue { border-left-color: var(--success); }
        .stat-card.pharmacy { border-left-color: #8b5cf6; }
        .stat-card.lab { border-left-color: #f59e0b; }
        .stat-card.admission { border-left-color: #db2777; }
        .stat-card.pending { border-left-color: var(--warning); }
        .stat-card.outstanding { border-left-color: var(--danger); }
        
        .stat-icon {
            min-width: 50px;
            height: 50px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        
        .stat-card.revenue .stat-icon { background: #dcfce7; color: var(--success); }
        .stat-card.pharmacy .stat-icon { background: #f3e8ff; color: #8b5cf6; }
        .stat-card.lab .stat-icon { background: #fef3c7; color: var(--warning); }
        .stat-card.admission .stat-icon { background: #fce7f3; color: #db2777; }
        .stat-card.pending .stat-icon { background: #fef3c7; color: var(--warning); }
        .stat-card.outstanding .stat-icon { background: #fee2e2; color: var(--danger); }
        
        .stat-content {
            flex: 1;
            min-width: 0;
        }
        
        .stat-content h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            word-break: break-word;
        }
        
        .stat-content p {
            margin: 0.25rem 0 0 0;
            font-size: 0.875rem;
            color: var(--gray);
        }
        
        .stat-trend {
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.25rem;
        }
        
        .trend-up { color: var(--success); }
        .trend-down { color: var(--danger); }
        
        /* Tabs Styling */
        .tabs-container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(6, 182, 212, 0.1);
        }
        
        .tabs {
            display: flex;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .tabs::-webkit-scrollbar {
            height: 4px;
        }
        
        .tabs::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .tabs::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 2px;
        }
        
        .tab {
            padding: 1rem 1.25rem;
            background: transparent;
            border: none;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray);
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
            white-space: nowrap;
        }
        
        .tab:hover {
            color: var(--primary);
            background: rgba(6, 182, 212, 0.05);
        }
        
        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: white;
        }
        
        .tab-content {
            display: none;
            padding: 1.25rem;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .tab-header {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        
        @media (min-width: 768px) {
            .tab-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }
        
        .tab-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Main Layout Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        @media (min-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 2fr 1fr;
            }
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(6, 182, 212, 0.1);
        }
        
        .card:last-child {
            margin-bottom: 0;
        }
        
        .card-header {
            padding: 1.25rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
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
            padding: 1.25rem;
        }
        
        /* Chart Container */
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        @media (min-width: 768px) {
            .chart-container {
                height: 350px;
            }
        }
        
        /* Lists */
        .bill-list, .patient-list, .service-list, .payment-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .bill-item, .patient-item, .service-item, .payment-item {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            padding: 1rem;
            border: 1px solid rgba(6, 182, 212, 0.1);
            border-radius: 0.75rem;
            transition: all 0.2s;
            background: white;
        }
        
        @media (min-width: 640px) {
            .bill-item, .patient-item, .service-item, .payment-item {
                flex-direction: row;
                justify-content: space-between;
                align-items: flex-start;
            }
        }
        
        .bill-item:hover, .patient-item:hover, .service-item:hover, .payment-item:hover {
            background: #f8fafc;
            border-color: var(--primary-light);
            box-shadow: 0 2px 4px rgba(6, 182, 212, 0.1);
        }
        
        .bill-info, .patient-info, .service-info, .payment-info {
            flex: 1;
            min-width: 0;
        }
        
        .bill-info h4, .patient-info h4, .service-info h4, .payment-info h4 {
            margin: 0;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
        }
        
        .bill-info p, .patient-info p, .service-info p, .payment-info p {
            margin: 0.25rem 0 0 0;
            font-size: 0.75rem;
            color: var(--gray);
        }
        
        .bill-amount, .patient-amount, .service-amount, .payment-amount {
            text-align: right;
            min-width: 120px;
        }
        
        @media (max-width: 639px) {
            .bill-amount, .patient-amount, .service-amount, .payment-amount {
                text-align: left;
                width: 100%;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
        }
        
        .bill-total, .outstanding-amount, .payment-total {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .bill-balance, .payment-due {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--danger);
            margin-top: 0.25rem;
        }
        
        .payment-paid {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--success);
            margin-top: 0.25rem;
        }
        
        /* Badges */
        .source-badge {
            padding: 0.25rem 0.5rem;
            background: #dbeafe;
            color: var(--primary);
            border-radius: 0.375rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }
        
        .source-badge.hospital {
            background: #dbeafe;
            color: var(--primary);
        }
        
        .source-badge.pharmacy {
            background: #f3e8ff;
            color: #8b5cf6;
        }
        
        .source-badge.lab {
            background: #fef3c7;
            color: #d97706;
        }
        
        .source-badge.admission {
            background: #fce7f3;
            color: #db2777;
        }
        
        /* Service badges */
        .service-badges {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
            flex-wrap: wrap;
        }
        
        .service-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .service-badge.hospital { background: #dbeafe; color: var(--primary); }
        .service-badge.pharmacy { background: #f3e8ff; color: #8b5cf6; }
        .service-badge.lab { background: #fef3c7; color: var(--warning); }
        .service-badge.consultation { background: #dcfce7; color: var(--success); }
        .service-badge.admission { background: #fce7f3; color: #db2777; }
        .service-badge.general { background: #e5e7eb; color: #6b7280; }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
        }
        
        @media (min-width: 768px) {
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
        
        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 0.5rem;
            padding: 1rem;
            background: white;
            border: 1px solid rgba(6, 182, 212, 0.1);
            border-radius: 0.75rem;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.2s;
        }
        
        @media (min-width: 768px) {
            .action-btn {
                flex-direction: row;
                text-align: left;
            }
        }
        
        .action-btn:hover {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(6, 182, 212, 0.2);
            border-color: var(--primary-light);
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
            display: none;
        }
        
        @media (min-width: 768px) {
            .action-text p {
                display: block;
            }
        }
        
        /* Department Grid */
        .department-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
        }
        
        .department-card {
            background: white;
            padding: 1rem;
            border-radius: 0.75rem;
            border-left: 4px solid var(--primary);
            text-align: center;
            border: 1px solid rgba(6, 182, 212, 0.1);
            transition: all 0.2s;
        }
        
        .department-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(6, 182, 212, 0.1);
        }
        
        .department-card h4 {
            margin: 0 0 0.5rem 0;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .department-revenue {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--success);
            margin: 0.5rem 0;
        }
        
        .department-count {
            font-size: 0.75rem;
            color: var(--gray);
        }
        
        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
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
        
        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }
        
        .empty-state h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .empty-state p {
            margin: 0;
            font-size: 0.875rem;
        }
        
        /* View All Links */
        .view-all {
            text-align: center;
            padding: 1rem;
            border-top: 1px solid rgba(6, 182, 212, 0.1);
        }
        
        .view-all a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.2s;
        }
        
        .view-all a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        /* View Details Links */
        .view-details {
            font-size: 0.75rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            transition: color 0.2s;
        }
        
        .view-details:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        /* Outstanding Section */
        .outstanding-section {
            margin-bottom: 1.5rem;
        }
        
        .outstanding-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        
        @media (min-width: 768px) {
            .outstanding-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        /* Patient details in payment history */
        .patient-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.5rem;
            margin-top: 0.5rem;
            font-size: 0.75rem;
            color: var(--gray);
        }
        
        /* Service details in bill list */
        .service-details {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
            flex-wrap: wrap;
        }
        
        .item-description {
            font-size: 0.7rem;
            color: var(--gray);
            margin-top: 0.25rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Chart Legend */
        .chart-legend {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            color: var(--gray);
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
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
                <p>Comprehensive financial overview across all departments and services</p>
            </div>
            <div class="date-display">
                <i class="far fa-calendar"></i> <?php echo date('l, F j, Y'); ?>
            </div>
        </div>
        
        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card revenue">
                <div class="stat-icon">
                    <i class="fas fa-money-bill"></i>
                </div>
                <div class="stat-content">
                    <h3>Ksh <?php echo number_format($stats['today_revenue'], 2); ?></h3>
                    <p>Today's Total Revenue</p>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i> All services combined
                    </div>
                </div>
            </div>
            
            <div class="stat-card pharmacy">
                <div class="stat-icon">
                    <i class="fas fa-pills"></i>
                </div>
                <div class="stat-content">
                    <h3>Ksh <?php echo number_format($stats['pharmacy_revenue'], 2); ?></h3>
                    <p>Pharmacy Revenue</p>
                    <div class="stat-trend">
                        <?php echo $stats['pharmacy_bills'] ?? 0; ?> sales today
                    </div>
                </div>
            </div>
            
            <div class="stat-card lab">
                <div class="stat-icon">
                    <i class="fas fa-flask"></i>
                </div>
                <div class="stat-content">
                    <h3>Ksh <?php echo number_format($stats['lab_revenue'], 2); ?></h3>
                    <p>Lab Test Revenue</p>
                    <div class="stat-trend">
                        Laboratory services
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
                        <?php echo $stats['pending_bills']; ?> patients with dues
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Outstanding Stats -->
        <div class="stats-grid">
            <div class="stat-card consultation">
                <div class="stat-icon">
                    <i class="fas fa-stethoscope"></i>
                </div>
                <div class="stat-content">
                    <h3>Ksh <?php echo number_format($stats['consultation_revenue'], 2); ?></h3>
                    <p>Consultation Revenue</p>
                    <div class="stat-trend">
                        Doctor consultations
                    </div>
                </div>
            </div>
            
            <div class="stat-card admission">
                <div class="stat-icon">
                    <i class="fas fa-procedures"></i>
                </div>
                <div class="stat-content">
                    <h3>Ksh <?php echo number_format($stats['admission_revenue'], 2); ?></h3>
                    <p>Admission Revenue</p>
                    <div class="stat-trend">
                        Bed charges & admissions
                    </div>
                </div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['pending_bills']; ?></h3>
                    <p>Patients with Dues</p>
                    <div class="stat-trend trend-down">
                        Require attention
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Container: Pending Bills & Payment History -->
        <div class="tabs-container">
            <div class="tabs">
                <button class="tab active" onclick="switchTab('pending')">
                    <i class="fas fa-exclamation-circle"></i> Patients with Dues
                </button>
                <button class="tab" onclick="switchTab('history')">
                    <i class="fas fa-history"></i> Today's Payments
                </button>
                <button class="tab" onclick="switchTab('outstanding')">
                    <i class="fas fa-chart-pie"></i> Outstanding by Service
                </button>
            </div>
            
            <!-- Patients with Dues Tab -->
            <div id="pending" class="tab-content active">
                <div class="tab-header">
                    <h3 class="tab-title">
                        <i class="fas fa-exclamation-circle"></i>
                        Patients with Outstanding Dues
                    </h3>
                    <a href="pending_payments.php" class="view-details">
                        <i class="fas fa-external-link-alt"></i> View All Pending Payments
                    </a>
                </div>
                
                <?php if (count($patients_with_dues) > 0): ?>
                <div class="patient-list">
                    <?php foreach ($patients_with_dues as $patient): ?>
                    <div class="patient-item">
                        <div class="patient-info">
                            <h4>
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($patient['patient_name']); ?>
                                <span class="source-badge hospital">
                                    Patient #<?php echo htmlspecialchars($patient['patient_number']); ?>
                                </span>
                            </h4>
                            <p>
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone']); ?> • 
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email']); ?> • 
                                <i class="far fa-calendar"></i> Last service: <?php echo date('M d, Y', strtotime($patient['last_service_date'])); ?>
                            </p>
                            
                            <!-- Total Items -->
                            <div style="margin-top: 0.25rem;">
                                <span style="font-size: 0.7rem; color: var(--danger);">
                                    <i class="fas fa-file-invoice"></i> <?php echo $patient['total_items']; ?> outstanding item(s)
                                </span>
                            </div>
                        </div>
                        <div class="patient-amount">
                            <div class="outstanding-amount">
                                <i class="fas fa-exclamation-triangle"></i>
                                Ksh <?php echo number_format($patient['total_outstanding'], 2); ?> due
                            </div>
                            <div style="margin-top: 0.5rem;">
                                <a href="unified_payment.php?patient_id=<?php echo $patient['patient_id']; ?>" 
                                   class="view-details">
                                    <i class="fas fa-credit-card"></i> Process Payment
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Summary -->
                <div style="margin-top: 1rem; padding: 1rem; background: #fee2e2; border-radius: 0.5rem; border-left: 4px solid var(--danger);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong><?php echo count($patients_with_dues); ?> patients with outstanding dues</strong>
                            <div style="font-size: 0.875rem; color: #7f1d1d;">
                                Total outstanding: <strong>Ksh <?php echo number_format(array_sum(array_column($patients_with_dues, 'total_outstanding')), 2); ?></strong>
                            </div>
                        </div>
                        <div>
                            <a href="pending_payments.php" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                <i class="fas fa-external-link-alt"></i> Manage All Pending
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h3>No Patients with Outstanding Dues</h3>
                    <p>All payments are up to date.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Outstanding by Service Tab -->
            <div id="outstanding" class="tab-content">
                <div class="tab-header">
                    <h3 class="tab-title">
                        <i class="fas fa-chart-pie"></i>
                        Outstanding Amounts by Service Type
                    </h3>
                </div>
                
                <?php if (count($outstanding_by_service) > 0): ?>
                <div class="service-list">
                    <?php 
                    $total_outstanding_services = 0;
                    foreach ($outstanding_by_service as $service): 
                        $total_outstanding_services += $service['outstanding_amount'];
                    ?>
                    <div class="service-item">
                        <div class="service-info">
                            <h4>
                                <?php 
                                $icon = 'hospital';
                                $badge_class = 'general';
                                switch(strtolower($service['service_type'])) {
                                    case 'consultation':
                                        $icon = 'stethoscope';
                                        $badge_class = 'consultation';
                                        $service_name = 'Consultation Fees';
                                        break;
                                    case 'lab':
                                        $icon = 'flask';
                                        $badge_class = 'lab';
                                        $service_name = 'Laboratory Tests';
                                        break;
                                    case 'pharmacy':
                                        $icon = 'pills';
                                        $badge_class = 'pharmacy';
                                        $service_name = 'Pharmacy Bills';
                                        break;
                                    case 'admission':
                                        $icon = 'procedures';
                                        $badge_class = 'admission';
                                        $service_name = 'Bed Charges';
                                        break;
                                    case 'billing':
                                        $icon = 'file-invoice';
                                        $badge_class = 'hospital';
                                        $service_name = 'Hospital Bills';
                                        break;
                                    default:
                                        $service_name = ucfirst($service['service_type']);
                                }
                                ?>
                                <i class="fas fa-<?php echo $icon; ?>"></i>
                                <?php echo htmlspecialchars($service_name); ?>
                                <span class="service-badge <?php echo $badge_class; ?>">
                                    <?php echo $service['bill_count']; ?> items
                                </span>
                            </h4>
                            <p>
                                <i class="fas fa-chart-bar"></i> 
                                Total billed: Ksh <?php echo number_format($service['total_amount'], 2); ?>
                            </p>
                        </div>
                        <div class="service-amount">
                            <div class="outstanding-amount">
                                <i class="fas fa-exclamation-triangle"></i>
                                Ksh <?php echo number_format($service['outstanding_amount'], 2); ?>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--gray); margin-top: 0.25rem;">
                                Outstanding
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Summary -->
                <div style="margin-top: 1rem; padding: 1rem; background: #fef3c7; border-radius: 0.5rem; border-left: 4px solid var(--warning);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong>Total Outstanding by Service Type</strong>
                            <div style="font-size: 0.875rem; color: #92400e;">
                                Overall outstanding: <strong>Ksh <?php echo number_format($total_outstanding_services, 2); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h3>No Outstanding Services</h3>
                    <p>All services have been paid for.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Payment History Tab -->
            <div id="history" class="tab-content">
                <div class="tab-header">
                    <h3 class="tab-title">
                        <i class="fas fa-clock"></i>
                        Today's Payment History
                    </h3>
                    <a href="payments_list.php" class="view-details">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                
                <?php if (count($today_payment_history) > 0): ?>
                <div class="payment-list">
                    <?php foreach ($today_payment_history as $payment): ?>
                    <div class="payment-item">
                        <div class="payment-info">
                            <h4>
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($payment['patient_name']); ?>
                                <span class="source-badge unified">
                                    Receipt: <?php echo htmlspecialchars($payment['reference_number']); ?>
                                </span>
                            </h4>
                            <div class="patient-details">
                                <div>
                                    <i class="fas fa-hashtag"></i> ID: <?php echo htmlspecialchars($payment['patient_number']); ?>
                                </div>
                                <div>
                                    <i class="far fa-calendar"></i> <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                </div>
                                <div>
                                    <i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($payment['payment_date'])); ?>
                                </div>
                                <div>
                                    <i class="fas fa-credit-card"></i> <?php echo ucfirst($payment['payment_method']); ?>
                                </div>
                            </div>
                            
                            <!-- Payment notes -->
                            <?php if (!empty($payment['notes'])): ?>
                            <div style="font-size: 0.7rem; color: var(--gray); margin-top: 0.25rem;">
                                <i class="fas fa-sticky-note"></i> 
                                <?php echo htmlspecialchars(substr($payment['notes'], 0, 50)); ?>
                                <?php if (strlen($payment['notes']) > 50) echo '...'; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="payment-amount">
                            <div class="payment-total">
                                Ksh <?php echo number_format($payment['amount'], 2); ?>
                            </div>
                            <div class="payment-paid">
                                <i class="fas fa-check-circle"></i>
                                Paid: Ksh <?php echo number_format($payment['amount'], 2); ?>
                            </div>
                            <div class="status-badge status-paid" style="margin-top: 0.5rem;">
                                Completed
                            </div>
                           
                            <div style="margin-top: 0.5rem;">
                                <a href="generate_receipt.php?payment_id=<?php echo $payment['id']; ?>" 
                                   target="_blank"
                                   class="view-details">
                                    <i class="fas fa-print"></i> View Receipt
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <h3>No Payments Today</h3>
                    <p>No payments processed today.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Main Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Left Column: Charts and Recent Bills -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <!-- Revenue Chart -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-chart-bar"></i>
                            Today's Service Distribution
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="serviceChart"></canvas>
                        </div>
                        <div class="chart-legend">
                            <?php 
                            $serviceData = [
                                ['name' => 'Pharmacy', 'revenue' => $stats['pharmacy_revenue'], 'color' => '#8b5cf6'],
                                ['name' => 'Consultation', 'revenue' => $stats['consultation_revenue'], 'color' => '#10b981'],
                                ['name' => 'Laboratory', 'revenue' => $stats['lab_revenue'], 'color' => '#f59e0b'],
                                ['name' => 'Admission', 'revenue' => $stats['admission_revenue'], 'color' => '#db2777'],
                                ['name' => 'Other', 'revenue' => $stats['hospital_revenue'], 'color' => '#3b82f6']
                            ];
                            foreach ($serviceData as $service):
                                if ($service['revenue'] > 0):
                            ?>
                            <div class="legend-item">
                                <div class="legend-color" style="background-color: <?php echo $service['color']; ?>"></div>
                                <span><?php echo $service['name']; ?>: Ksh <?php echo number_format($service['revenue'], 2); ?></span>
                            </div>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Bills (All Services) -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-clock"></i>
                            Recent Bills Today (All Services)
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
                                            <?php echo ucfirst($bill['source']); ?>
                                        </span>
                                    </h4>
                                    <p>
                                        <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($bill['bill_number']); ?> • 
                                        <i class="far fa-clock"></i> <?php echo date('g:i A', strtotime($bill['bill_date'])); ?>
                                        <?php if (!empty($bill['patient_number'])): ?>
                                        • <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($bill['patient_number']); ?>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <!-- Service Types -->
                                    <?php if (!empty($bill['service_types'])): ?>
                                    <div class="service-details">
                                        <?php 
                                        $services = explode(', ', $bill['service_types']);
                                        foreach ($services as $service):
                                            if (!empty(trim($service))):
                                        ?>
                                        <span class="service-badge <?php echo trim($service); ?>">
                                            <?php echo ucfirst(trim($service)); ?>
                                        </span>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Item Descriptions -->
                                    <?php if (!empty($bill['item_descriptions'])): ?>
                                    <div class="item-description">
                                        <?php 
                                        $items = explode('; ', $bill['item_descriptions']);
                                        echo htmlspecialchars(implode(', ', array_slice($items, 0, 2)));
                                        if (count($items) > 2) echo '...';
                                        ?>
                                    </div>
                                    <?php endif; ?>
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
                                    <?php else: ?>
                                    <div class="payment-paid">
                                        <i class="fas fa-check-circle"></i>
                                        Paid: Ksh <?php echo number_format($bill['paid_amount'], 2); ?>
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

            <!-- Right Column: Quick Actions and Top Services -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
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
                            <a href="unified_payment.php" class="action-btn">
                                <div class="action-icon">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <div class="action-text">
                                    <h4>Process Payment</h4>
                                    <p>Unified payment processing</p>
                                </div>
                            </a>
                            
                            <a href="pending_payments.php" class="action-btn">
                                <div class="action-icon">
                                    <i class="fas fa-exclamation-circle"></i>
                                </div>
                                <div class="action-text">
                                    <h4>Pending Payments</h4>
                                    <p>View outstanding dues</p>
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

                <!-- Top Services -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-star"></i>
                            Top Services by Revenue
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($top_services) > 0): ?>
                        <div class="service-list">
                            <?php foreach ($top_services as $service): ?>
                            <div class="service-item">
                                <div class="service-info">
                                    <h4>
                                        <i class="fas fa-<?php 
                                            switch($service['service_type']) {
                                                case 'pharmacy': echo 'pills'; break;
                                                case 'lab': echo 'flask'; break;
                                                case 'consultation': echo 'stethoscope'; break;
                                                case 'bed_charge': echo 'procedures'; break;
                                                case 'procedure': echo 'cut'; break;
                                                default: echo 'hospital';
                                            }
                                        ?>"></i>
                                        <?php echo htmlspecialchars($service['service_name']); ?>
                                    </h4>
                                    <p>
                                        <i class="fas fa-chart-bar"></i> <?php echo $service['bill_count']; ?> transactions • 
                                        <i class="fas fa-box"></i> <?php echo $service['total_quantity']; ?> units/sessions
                                    </p>
                                </div>
                                <div class="service-amount">
                                    <div class="bill-total">
                                        Ksh <?php echo number_format($service['total_revenue'], 2); ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--gray); margin-top: 0.25rem;">
                                        Revenue
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="view-all">
                            <a href="billing_reports.php?report=services">
                                <i class="fas fa-chart-line"></i> View Service Analytics
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-star"></i>
                            <h3>No Services Today</h3>
                            <p>No service revenue recorded today.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Service Distribution Chart
        const serviceCtx = document.getElementById('serviceChart').getContext('2d');
        
        // Prepare data for chart
        const serviceLabels = [];
        const serviceData = [];
        const serviceColors = [];
        const serviceHoverColors = [];
        
        <?php
        $chartData = [
            ['Pharmacy', $stats['pharmacy_revenue'], '#8b5cf6', '#7c3aed'],
            ['Consultation', $stats['consultation_revenue'], '#10b981', '#059669'],
            ['Laboratory', $stats['lab_revenue'], '#f59e0b', '#d97706'],
            ['Admission', $stats['admission_revenue'], '#db2777', '#be185d'],
            ['Other Services', $stats['hospital_revenue'], '#3b82f6', '#2563eb']
        ];
        
        foreach ($chartData as $service):
            if ($service[1] > 0):
        ?>
        serviceLabels.push('<?php echo $service[0]; ?>');
        serviceData.push(<?php echo $service[1]; ?>);
        serviceColors.push('<?php echo $service[2]; ?>');
        serviceHoverColors.push('<?php echo $service[3]; ?>');
        <?php
            endif;
        endforeach;
        ?>
        
        const serviceChart = new Chart(serviceCtx, {
            type: 'bar',
            data: {
                labels: serviceLabels,
                datasets: [{
                    label: 'Revenue (Ksh)',
                    data: serviceData,
                    backgroundColor: serviceColors,
                    borderColor: serviceHoverColors,
                    borderWidth: 1,
                    borderRadius: 4,
                    hoverBackgroundColor: serviceHoverColors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: Ksh ${context.parsed.y.toLocaleString()}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Ksh ' + value.toLocaleString();
                            }
                        },
                        grid: {
                            color: 'rgba(6, 182, 212, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Tab switching function
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Set clicked tab as active
            event.currentTarget.classList.add('active');
        }

        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                sidebar.style.left = sidebar.style.left === '0px' ? '-260px' : '0px';
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
            
            // Adjust chart on window resize
            window.addEventListener('resize', function() {
                serviceChart.resize();
            });
        });
    </script>
</body>
</html>