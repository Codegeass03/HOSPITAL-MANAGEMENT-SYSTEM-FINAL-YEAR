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
            COALESCE(SUM(total_amount), 0) as today_revenue,
            COUNT(*) as payment_count
        FROM payments 
        WHERE DATE(created_at) = ?
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
        AND b.paid_amount > 0
        AND bi.item_type = 'consultation'
        
        UNION ALL
        
        SELECT 
            'lab' as service_type,
            COALESCE(SUM(lo.paid_amount), 0) as revenue,
            COUNT(DISTINCT lo.id) as bill_count
        FROM lab_orders lo
        WHERE DATE(lo.order_date) = ?
        AND lo.payment_status IN ('paid', 'partial')
        AND lo.paid_amount > 0
        
        UNION ALL
        
        SELECT 
            'admission' as service_type,
            COALESCE(SUM(bi.total_amount), 0) as revenue,
            COUNT(DISTINCT b.id) as bill_count
        FROM billing b
        INNER JOIN bill_items bi ON b.id = bi.bill_id
        WHERE DATE(b.bill_date) = ?
        AND b.paid_amount > 0
        AND bi.item_type = 'bed_charge'
        
        UNION ALL
        
        SELECT 
            'procedure' as service_type,
            COALESCE(SUM(bi.total_amount), 0) as revenue,
            COUNT(DISTINCT b.id) as bill_count
        FROM billing b
        INNER JOIN bill_items bi ON b.id = bi.bill_id
        WHERE DATE(b.bill_date) = ?
        AND b.paid_amount > 0
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
            COALESCE(SUM(pb.paid_amount), 0) as pharmacy_revenue,
            COUNT(DISTINCT pb.id) as pharmacy_bills
        FROM pharmacy_billing pb
        WHERE DATE(pb.date) = '$today'
        AND pb.paid_amount > 0
    ";
    $pharmacy_result = mysqli_query($link, $pharmacy_query);
    if ($pharmacy_result) {
        $pharmacy_row = mysqli_fetch_assoc($pharmacy_result);
        $stats['pharmacy_revenue'] = $pharmacy_row['pharmacy_revenue'] ?? 0;
        $stats['pharmacy_bills'] = $pharmacy_row['pharmacy_bills'] ?? 0;
    }

    // Calculate TOTAL OUTSTANDING from ALL services - CORRECTED QUERY
    $total_outstanding_calculated = 0;

    // 1. Hospital bills outstanding (balance_amount > 0)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(balance_amount), 0) as total_outstanding FROM billing WHERE balance_amount > 0");
    $stmt->execute();
    $hospital_outstanding = $stmt->fetchColumn();
    $total_outstanding_calculated += $hospital_outstanding;

    // 2. Lab orders outstanding - Check payment_status instead of paid_amount
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(lo.total_amount - COALESCE(lo.paid_amount, 0)), 0) as lab_outstanding
        FROM lab_orders lo
        WHERE lo.payment_status IN ('unpaid', 'partial')
        AND (lo.total_amount - COALESCE(lo.paid_amount, 0)) > 0
    ");
    $stmt->execute();
    $lab_outstanding = $stmt->fetchColumn();
    $total_outstanding_calculated += $lab_outstanding;

    // 3. Consultation fees outstanding - Fixed query to match unified payment logic
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(a.consultation_fee), 0) as consultation_outstanding
        FROM appointments a
        WHERE a.status = 'completed'
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
    ");
    $stmt->execute();
    $consultation_outstanding = $stmt->fetchColumn();
    $total_outstanding_calculated += $consultation_outstanding;

    // 4. Bed charges outstanding
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(COALESCE(ba.total_charges, 0) - COALESCE(ba.paid_amount, 0)), 0) as admission_outstanding
        FROM bed_assignments ba
        WHERE ba.status = 'active'
        AND (COALESCE(ba.total_charges, 0) - COALESCE(ba.paid_amount, 0)) > 0
    ");
    $stmt->execute();
    $admission_outstanding = $stmt->fetchColumn();
    $total_outstanding_calculated += $admission_outstanding;

    // 5. Pharmacy bills outstanding
    mysqli_select_db($link, "hospital");
    $check_columns = "SHOW COLUMNS FROM pharmacy_billing LIKE 'balance_amount'";
    $column_result = mysqli_query($link, $check_columns);
    
    if (mysqli_num_rows($column_result) > 0) {
        $pharmacy_query = "SELECT COALESCE(SUM(balance_amount), 0) as pharmacy_outstanding FROM pharmacy_billing WHERE COALESCE(balance_amount, 0) > 0";
    } else {
        $pharmacy_query = "SELECT COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0) as pharmacy_outstanding FROM pharmacy_billing WHERE (total_amount - COALESCE(paid_amount, 0)) > 0";
    }
    
    $pharmacy_result = mysqli_query($link, $pharmacy_query);
    $pharmacy_outstanding = 0;
    if ($pharmacy_result) {
        $pharmacy_row = mysqli_fetch_assoc($pharmacy_result);
        $pharmacy_outstanding = $pharmacy_row['pharmacy_outstanding'] ?? 0;
    }
    $total_outstanding_calculated += $pharmacy_outstanding;

    $stats['total_outstanding'] = $total_outstanding_calculated;

    // Pending bills count (hospital only for now)
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending_bills FROM billing WHERE balance_amount > 0");
    $stmt->execute();
    $stats['pending_bills'] = $stmt->fetchColumn();

    // Payment methods distribution FROM PAYMENTS TABLE
    $stmt = $pdo->prepare("
        SELECT 
            payment_method,
            COUNT(*) as transaction_count,
            SUM(total_amount) as total_amount
        FROM payments 
        WHERE DATE(created_at) = ?
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
            COALESCE(SUM(pb.paid_amount), 0) as revenue,
            COUNT(DISTINCT pb.id) as bill_count
        FROM pharmacy_billing pb
        WHERE DATE(pb.date) = ?
        AND pb.paid_amount > 0
        
        UNION ALL
        
        SELECT 
            'Laboratory' as department,
            COALESCE(SUM(lo.paid_amount), 0) as revenue,
            COUNT(DISTINCT lo.id) as bill_count
        FROM lab_orders lo
        WHERE DATE(lo.order_date) = ?
        AND lo.paid_amount > 0
        
        UNION ALL
        
        SELECT 
            'Consultation' as department,
            COALESCE(SUM(bi.total_amount), 0) as revenue,
            COUNT(DISTINCT b.id) as bill_count
        FROM billing b
        INNER JOIN bill_items bi ON b.id = bi.bill_id
        WHERE DATE(b.bill_date) = ?
        AND b.paid_amount > 0
        AND bi.item_type = 'consultation'
        
        UNION ALL
        
        SELECT 
            'Admission' as department,
            COALESCE(SUM(bi.total_amount), 0) as revenue,
            COUNT(DISTINCT b.id) as bill_count
        FROM billing b
        INNER JOIN bill_items bi ON b.id = bi.bill_id
        WHERE DATE(b.bill_date) = ?
        AND b.paid_amount > 0
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
        AND b.paid_amount > 0
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

// Get patients with outstanding balances - FIXED QUERY
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
                (SELECT COUNT(*) FROM lab_orders WHERE patient_id = p.id AND payment_status IN ('unpaid', 'partial') AND (total_amount - COALESCE(paid_amount, 0)) > 0) +
                (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id AND status = 'completed' AND consultation_fee > 0 AND NOT EXISTS (
                    SELECT 1 FROM bill_items bi 
                    WHERE bi.item_type = 'consultation'
                    AND bi.item_description LIKE CONCAT('%Appointment #', appointments.appointment_number, '%')
                    AND bi.bill_id IN (
                        SELECT id FROM billing 
                        WHERE patient_id = appointments.patient_id 
                        AND status = 'paid'
                    )
                )) +
                (SELECT COUNT(*) FROM pharmacy_billing WHERE patient_id = p.id AND COALESCE(balance_amount, 0) > 0)
            ) as bill_count,
            (
                -- Hospital bills
                COALESCE((SELECT SUM(balance_amount) FROM billing WHERE patient_id = p.id AND balance_amount > 0), 0) +
                -- Lab orders (fixed calculation)
                COALESCE((SELECT SUM(total_amount - COALESCE(paid_amount, 0)) FROM lab_orders WHERE patient_id = p.id AND payment_status IN ('unpaid', 'partial') AND (total_amount - COALESCE(paid_amount, 0)) > 0), 0) +
                -- Consultation fees (fixed to match unified payment logic)
                COALESCE((SELECT SUM(consultation_fee) FROM appointments WHERE patient_id = p.id AND status = 'completed' AND consultation_fee > 0 AND NOT EXISTS (
                    SELECT 1 FROM bill_items bi 
                    WHERE bi.item_type = 'consultation'
                    AND bi.item_description LIKE CONCAT('%Appointment #', appointments.appointment_number, '%')
                    AND bi.bill_id IN (
                        SELECT id FROM billing 
                        WHERE patient_id = appointments.patient_id 
                        AND status = 'paid'
                    )
                )), 0) +
                -- Pharmacy bills
                COALESCE((SELECT SUM(COALESCE(balance_amount, 0)) FROM pharmacy_billing WHERE patient_id = p.id AND COALESCE(balance_amount, 0) > 0), 0) +
                -- Admission/ bed charges
                COALESCE((SELECT SUM(COALESCE(ba.total_charges, 0) - COALESCE(ba.paid_amount, 0)) FROM bed_assignments ba WHERE ba.patient_id = p.id AND ba.status = 'active' AND (COALESCE(ba.total_charges, 0) - COALESCE(ba.paid_amount, 0)) > 0), 0)
            ) as total_due,
            -- Check if patient made any unified payments today (for information only)
            (
                SELECT COUNT(*) 
                FROM payments 
                WHERE patient_id = p.id 
                AND DATE(created_at) = ?
                AND status = 'completed'
            ) as payments_today
        FROM patients p
        HAVING total_due > 0
        ORDER BY total_due DESC
        LIMIT 10
    ");
    $stmt->execute([$today]);
    $patients_with_dues = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching patients with dues: " . $e->getMessage());
}

// Get outstanding by service type
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
            SUM(total_amount - COALESCE(paid_amount, 0)) as outstanding_amount
        FROM lab_orders 
        WHERE payment_status IN ('unpaid', 'partial')
        AND (total_amount - COALESCE(paid_amount, 0)) > 0
    ");
    $stmt->execute();
    $lab_outstanding = $stmt->fetch();
    if ($lab_outstanding['outstanding_amount'] > 0) {
        $outstanding_by_service[] = $lab_outstanding;
    }

    // Consultations - Fixed to match unified payment logic
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
            WHERE bi.item_type = 'consultation'
            AND bi.item_description LIKE CONCAT('%Appointment #', appointments.appointment_number, '%')
            AND bi.bill_id IN (
                SELECT id FROM billing 
                WHERE patient_id = appointments.patient_id 
                AND status = 'paid'
            )
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
            SUM(COALESCE(ba.total_charges, 0)) as total_amount,
            SUM(COALESCE(ba.total_charges, 0) - COALESCE(ba.paid_amount, 0)) as outstanding_amount
        FROM bed_assignments ba
        WHERE ba.status = 'active'
        AND (COALESCE(ba.total_charges, 0) - COALESCE(ba.paid_amount, 0)) > 0
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
            COALESCE(SUM(pb.total_amount), 0) as total_amount,
            COALESCE(SUM(pb.balance_amount), 0) as outstanding_amount
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
        AND b.paid_amount > 0
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
            CASE 
                WHEN lo.paid_amount >= lo.total_amount THEN 'paid'
                WHEN lo.paid_amount > 0 THEN 'partial'
                ELSE 'pending'
            END as status,
            'cash' as payment_method,
            'lab' as source,
            'lab' as service_types,
            GROUP_CONCAT(DISTINCT loi.test_name ORDER BY loi.id SEPARATOR '; ') as item_descriptions
        FROM lab_orders lo
        INNER JOIN patients p ON lo.patient_id = p.id
        LEFT JOIN lab_order_items loi ON lo.id = loi.lab_order_id
        WHERE DATE(lo.order_date) = ?
        AND lo.paid_amount > 0
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
            CASE 
                WHEN COALESCE(pb.balance_amount, 0) > 0 AND COALESCE(pb.paid_amount, 0) > 0 THEN 'partial'
                WHEN COALESCE(pb.balance_amount, 0) > 0 THEN 'pending'
                ELSE 'paid'
            END as status,
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
        AND pb.paid_amount > 0
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
        AND b.paid_amount > 0
        GROUP BY bi.item_type
        HAVING total_revenue > 0
        
        UNION ALL
        
        SELECT 
            'Pharmacy Sales' as service_name,
            'pharmacy' as service_type,
            COALESCE(SUM(pb.total_amount), 0) as total_quantity,
            COALESCE(SUM(pb.paid_amount), 0) as total_revenue,
            COUNT(DISTINCT pb.id) as bill_count
        FROM pharmacy_billing pb
        WHERE DATE(pb.date) = ?
        AND pb.paid_amount > 0
        
        UNION ALL
        
        SELECT 
            'Laboratory Tests' as service_name,
            'lab' as service_type,
            COALESCE(COUNT(lo.id), 0) as total_quantity,
            COALESCE(SUM(lo.paid_amount), 0) as total_revenue,
            COUNT(DISTINCT lo.id) as bill_count
        FROM lab_orders lo
        WHERE DATE(lo.order_date) = ?
        AND lo.paid_amount > 0
        
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
            p.id as payment_id,
            p.patient_id,
            p.payment_number,
            p.total_amount,
            p.payment_method,
            p.payment_reference,
            p.status,
            p.created_at as payment_date,
            p.received_by,
            CONCAT(pt.first_name, ' ', pt.last_name) as patient_name,
            pt.patient_id as patient_number,
            pt.phone as patient_phone,
            (
                SELECT GROUP_CONCAT(
                    CONCAT(
                        pa.service_type, ':', 
                        COALESCE(
                            CASE pa.service_type
                                WHEN 'consultation' THEN CONCAT('Consultation #', a.appointment_number)
                                WHEN 'lab' THEN CONCAT('Lab Order #', lo.order_number)
                                WHEN 'pharmacy' THEN CONCAT('Pharmacy Bill #', pb.bill_no)
                                WHEN 'admission' THEN CONCAT('Bed Charges #', ba.id)
                                WHEN 'general' THEN CONCAT('General Bill #', b.bill_number)
                                ELSE pa.service_type
                            END,
                            pa.service_type
                        ), ':', 
                        pa.allocated_amount
                    ) SEPARATOR '|'
                )
                FROM payment_allocations pa
                LEFT JOIN appointments a ON pa.service_type = 'consultation' AND pa.reference_id = a.id
                LEFT JOIN lab_orders lo ON pa.service_type = 'lab' AND pa.reference_id = lo.id
                LEFT JOIN pharmacy_billing pb ON pa.service_type = 'pharmacy' AND pa.reference_id = pb.id
                LEFT JOIN bed_assignments ba ON pa.service_type = 'admission' AND pa.reference_id = ba.id
                LEFT JOIN billing b ON pa.service_type = 'general' AND pa.reference_id = b.id
                WHERE pa.payment_id = p.id
            ) as allocation_details,
            (
                SELECT GROUP_CONCAT(DISTINCT pa.service_type SEPARATOR ', ')
                FROM payment_allocations pa
                WHERE pa.payment_id = p.id
            ) as service_types,
            (
                SELECT COUNT(*)
                FROM payment_allocations pa
                WHERE pa.payment_id = p.id
            ) as service_count
        FROM payments p
        INNER JOIN patients pt ON p.patient_id = pt.id
        WHERE p.status = 'completed'
        AND DATE(p.created_at) = ?
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$today]);
    $today_payment_history = $stmt->fetchAll();
    
    // Process allocation details for each payment
    foreach ($today_payment_history as &$payment) {
        $payment['departments'] = [];
        $payment['allocations_parsed'] = [];
        
        if (!empty($payment['allocation_details'])) {
            $allocations = explode('|', $payment['allocation_details']);
            foreach ($allocations as $allocation) {
                $parts = explode(':', $allocation);
                if (count($parts) >= 3) {
                    $service_type = $parts[0];
                    $description = $parts[1];
                    $amount = $parts[2];
                    
                    $payment['allocations_parsed'][] = [
                        'service_type' => $service_type,
                        'description' => $description,
                        'amount' => $amount
                    ];
                    
                    // Map to department
                    $dept = mapServiceToDepartment($service_type);
                    if (!in_array($dept, $payment['departments'])) {
                        $payment['departments'][] = $dept;
                    }
                }
            }
        }
        
        // Set payment numbers
        $payment['payment_numbers'] = [$payment['payment_number']];
        $payment['source_types'] = ['unified'];
        $payment['billing_date'] = date('Y-m-d', strtotime($payment['payment_date']));
    }
    
} catch (PDOException $e) {
    error_log("Error fetching payment history: " . $e->getMessage());
    $today_payment_history = [];
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

        <!-- Tabs Container: Pending Bills & Payment History -->
        <div class="tabs-container">
            <div class="tabs">
                <button class="tab active" onclick="switchTab('pending')">
                    <i class="fas fa-exclamation-circle"></i> Pending Bills
                </button>
                <button class="tab" onclick="switchTab('history')">
                    <i class="fas fa-history"></i> Today's Payments
                </button>
            </div>
            
            <!-- Pending Bills Tab -->
            <!-- Pending Bills Tab -->
<div id="pending" class="tab-content active">
    <div class="tab-header">
        <h3 class="tab-title">
            <i class="fas fa-exclamation-circle"></i>
            Pending Bills Overview
        </h3>
        <a href="pending_payments.php" class="view-details">
            <i class="fas fa-external-link-alt"></i> Go to Pending Payments
        </a>
    </div>
    
    <?php
    // Get pending bills for the dashboard (top 10)
    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            p.first_name,
            p.last_name,
            p.patient_id as patient_number,
            p.phone,
            DATEDIFF(CURDATE(), b.bill_date) as days_pending,
            (
                SELECT GROUP_CONCAT(DISTINCT bi.item_type ORDER BY bi.item_type SEPARATOR ', ')
                FROM bill_items bi
                WHERE bi.bill_id = b.id
            ) as service_types
        FROM billing b
        INNER JOIN patients p ON b.patient_id = p.id
        WHERE b.balance_amount > 0
        ORDER BY b.balance_amount DESC, b.bill_date ASC
        LIMIT 10
    ");
    $stmt->execute();
    $pending_bills_dashboard = $stmt->fetchAll();
    ?>
    
    <?php if (count($pending_bills_dashboard) > 0): ?>
    <div class="bill-list">
        <?php 
        $total_outstanding_dashboard = 0;
        foreach ($pending_bills_dashboard as $bill): 
            $total_outstanding_dashboard += $bill['balance_amount'];
        ?>
        <div class="bill-item">
            <div class="bill-info">
                <h4>
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($bill['first_name'] . ' ' . $bill['last_name']); ?>
                    <span class="source-badge hospital">
                        Bill #<?php echo htmlspecialchars($bill['bill_number']); ?>
                    </span>
                </h4>
                <p>
                    <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($bill['patient_number']); ?> • 
                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($bill['phone']); ?> • 
                    <i class="far fa-calendar"></i> <?php echo date('M d, Y', strtotime($bill['bill_date'])); ?>
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
                        <?php echo ucfirst(str_replace('_', ' ', trim($service))); ?>
                    </span>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
                <?php endif; ?>
                
                <!-- Days Pending -->
                <?php if ($bill['days_pending'] > 0): ?>
                <div style="margin-top: 0.25rem;">
                    <span style="font-size: 0.7rem; color: <?php echo $bill['days_pending'] > 30 ? '#ef4444' : '#f59e0b'; ?>;">
                        <i class="far fa-clock"></i> <?php echo $bill['days_pending']; ?> days pending
                    </span>
                </div>
                <?php endif; ?>
            </div>
            <div class="bill-amount">
                <div class="bill-total">
                    <small style="color: var(--gray);">Total:</small><br>
                    Ksh <?php echo number_format($bill['total_amount'], 2); ?>
                </div>
                <div class="bill-balance" style="margin-top: 0.5rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                    Ksh <?php echo number_format($bill['balance_amount'], 2); ?> due
                </div>
                <div style="margin-top: 0.5rem;">
                    <?php if ($bill['paid_amount'] > 0): ?>
                    <span class="status-badge status-partial">
                        Partially Paid
                    </span>
                    <?php else: ?>
                    <span class="status-badge status-pending">
                        Unpaid
                    </span>
                    <?php endif; ?>
                </div>
                <div style="margin-top: 0.5rem;">
                    <a href="unified_payment.php?patient_id=<?php echo $bill['patient_id']; ?>&bill_id=<?php echo $bill['id']; ?>" 
                       class="view-details">
                        <i class="fas fa-credit-card"></i> Pay Now
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Summary -->
    <div style="margin-top: 1rem; padding: 1rem; background: #fef3c7; border-radius: 0.5rem; border-left: 4px solid #f59e0b;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <strong><?php echo count($pending_bills_dashboard); ?> pending bills</strong>
                <div style="font-size: 0.875rem; color: #92400e;">
                    Total outstanding: <strong>Ksh <?php echo number_format($total_outstanding_dashboard, 2); ?></strong>
                </div>
            </div>
            <div>
                <a href="pending_payments.php" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                    <i class="fas fa-external-link-alt"></i> Manage All
                </a>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-check-circle"></i>
        <h3>No Pending Bills</h3>
        <p>All bills have been paid or are up to date.</p>
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
                    <a href="bills_details.php" class="view-details">
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
                                    Unified Payment
                                </span>
                            </h4>
                            <div class="patient-details">
                                <div>
                                    <i class="fas fa-hashtag"></i> ID: <?php echo htmlspecialchars($payment['patient_number']); ?>
                                </div>
                                <div>
                                    <i class="far fa-calendar"></i> <?php echo date('M d, Y', strtotime($payment['billing_date'])); ?>
                                </div>
                                <div>
                                    <i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($payment['payment_date'])); ?>
                                </div>
                                <div>
                                    <i class="fas fa-stethoscope"></i> 
                                    <?php 
                                    if (!empty($payment['service_types'])) {
                                        echo htmlspecialchars($payment['service_types']);
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <!-- Departments -->
                            <?php if (!empty($payment['departments'])): ?>
                            <div class="service-badges" style="margin-top: 0.5rem;">
                                <?php foreach ($payment['departments'] as $dept): ?>
                                <span class="service-badge <?php echo strtolower($dept); ?>">
                                    <?php echo htmlspecialchars($dept); ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Payment details -->
                            <?php if (!empty($payment['payment_number'])): ?>
                            <div style="font-size: 0.7rem; color: var(--gray); margin-top: 0.25rem;">
                                <i class="fas fa-hashtag"></i> 
                                Receipt: <?php echo htmlspecialchars($payment['payment_number']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="payment-amount">
                            <div class="payment-total">
                                Ksh <?php echo number_format($payment['total_amount'] ?? 0, 2); ?>
                            </div>
                            <div class="payment-paid">
                                <i class="fas fa-check-circle"></i>
                                Paid: Ksh <?php echo number_format($payment['total_amount'] ?? 0, 2); ?>
                            </div>
                            <div class="status-badge status-paid" style="margin-top: 0.5rem;">
                                Fully Paid
                            </div>
                           
                            <div style="margin-top: 0.5rem;">
                                <a href="generate_receipt.php?payment_id=<?php echo $payment['payment_id']; ?>" 
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
                    <p>No unified payments processed today.</p>
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

            <!-- Right Column: Department Revenue and Top Services -->
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
                            
                            <a href="bills_details.php" class="action-btn">
                                <div class="action-icon">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <div class="action-text">
                                    <h4>Hospital Bills</h4>
                                    <p>View all hospital bills</p>
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
        // Service Distribution Chart (Bar Chart instead of Doughnut)
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